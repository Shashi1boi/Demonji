<?php
require_once 'config.php';
require_once 'StalkerLite.php';

set_time_limit(120);
error_reporting(E_ALL);
ini_set('display_errors', 0);

// --- API endpoint: get categories as JSON ---
if (isset($_GET['action']) && $_GET['action'] === 'categories') {
    $serverId = $_GET['server'] ?? '';
    $server = getStalkerServerById($serverId);
    if (!$server) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid server ID']);
        exit;
    }
    
    $url = $server['url'];
    $mac = $server['mac'];
    $model = $_GET['model'] ?? '';
    
    $extras = [];
    if (!empty($_GET['sn_cut']))     $extras['sn_cut'] = $_GET['sn_cut'];
    if (!empty($_GET['device_id']))  $extras['device_id'] = $_GET['device_id'];
    if (!empty($_GET['device_id2'])) $extras['device_id2'] = $_GET['device_id2'];
    if (!empty($_GET['signature']))  $extras['signature'] = $_GET['signature'];
    
    // Auto-detect model if empty (simplified for categories)
    if (empty($model)) {
        $commonModels = ['MAG524', 'MAG544w3', 'MAG500A', 'MAG520', 'MAG522', 'MAG528', 'MAG540', 'MAG250', 'MAG254', 'MAG256', 'MAG322', 'MAG324', 'MAG349', 'MAG351', 'MAG420', 'MAG424'];
        foreach ($commonModels as $testModel) {
            $test = new StalkerLite($url, $mac, $testModel, $extras);
            if ($test->connect()['success']) {
                $model = $testModel;
                break;
            }
        }
        if (!$model) {
            http_response_code(503);
            echo json_encode(['error' => 'Could not auto-detect model']);
            exit;
        }
    }
    
    $stalker = new StalkerLite($url, $mac, $model, $extras);
    $connect = $stalker->connect();
    if (!$connect['success']) {
        http_response_code(503);
        echo json_encode(['error' => 'Handshake failed: ' . ($connect['error'] ?? 'unknown')]);
        exit;
    }
    
    $genres = $stalker->getGenres();
    $categories = [];
    foreach ($genres as $id => $name) {
        $categories[] = ['id' => (string)$id, 'name' => $name];
    }
    header('Content-Type: application/json');
    echo json_encode($categories);
    exit;
}

// --- Normal playlist generation ---
$serverId = $_GET['server'] ?? '';
$server = getStalkerServerById($serverId);
if (!$server) {
    http_response_code(400);
    die("# ERROR: Invalid server ID\n");
}

$url   = $server['url'];
$mac   = $server['mac'];
$model = $_GET['model'] ?? '';  // empty = auto-detect

// Advanced optional params
$extras = [];
if (!empty($_GET['sn_cut']))     $extras['sn_cut'] = $_GET['sn_cut'];
if (!empty($_GET['device_id']))  $extras['device_id'] = $_GET['device_id'];
if (!empty($_GET['device_id2'])) $extras['device_id2'] = $_GET['device_id2'];
if (!empty($_GET['signature']))  $extras['signature'] = $_GET['signature'];

$proxyMode = $_GET['proxy'] ?? 'redirect';

// Category filter
$selectedCategories = [];
if (!empty($_GET['categories'])) {
    $selectedCategories = explode(',', $_GET['categories']);
    $selectedCategories = array_map('trim', $selectedCategories);
}

// --- Auto-detect model if not provided ---
if (empty($model)) {
    $commonModels = [
        'MAG524', 'MAG544w3', 'MAG500A', 'MAG520', 'MAG522', 'MAG528', 'MAG540',
        'MAG250', 'MAG254', 'MAG256', 'MAG322', 'MAG324', 'MAG349', 'MAG351', 'MAG420', 'MAG424'
    ];
    $detectedModel = null;
    foreach ($commonModels as $testModel) {
        $testStalker = new StalkerLite($url, $mac, $testModel, $extras);
        $testConnect = $testStalker->connect();
        if ($testConnect['success']) {
            $detectedModel = $testModel;
            break;
        }
    }
    if (!$detectedModel) {
        http_response_code(503);
        die("# ERROR: Could not auto-detect MAG model. Please specify one manually in advanced options.\n");
    }
    $model = $detectedModel;
}

$stalker = new StalkerLite($url, $mac, $model, $extras);
$connect = $stalker->connect();
if (!$connect['success']) {
    http_response_code(503);
    die("# ERROR: Handshake failed – " . ($connect['error'] ?? 'unknown') . "\n");
}

$channels = $stalker->getChannels();
if (empty($channels)) {
    http_response_code(503);
    die("# ERROR: No channels found\n");
}

// --- Filter channels by selected categories ---
if (!empty($selectedCategories)) {
    $filteredChannels = [];
    foreach ($channels as $ch) {
        if (in_array($ch['genre_id'], $selectedCategories)) {
            $filteredChannels[] = $ch;
        }
    }
    if (empty($filteredChannels)) {
        http_response_code(404);
        die("# ERROR: No channels match the selected categories.\n");
    }
    $channels = $filteredChannels;
}

// Build self URL for stream resolver
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$self = $protocol . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/play.php';

// Forward all relevant params to play.php
$forwardParams = [];
$forwardParams['model'] = $model;
foreach (['sn_cut', 'device_id', 'device_id2', 'signature'] as $p) {
    if (!empty($_GET[$p])) $forwardParams[$p] = $_GET[$p];
}
$forwardParams['proxy'] = $proxyMode;
$forwardQuery = http_build_query($forwardParams);

// Output M3U
header('Content-Type: audio/x-mpegurl');
header('Content-Disposition: inline; filename="' . preg_replace('/[^a-z0-9]/i', '_', $server['name']) . '.m3u"');
header("Cache-Control: no-store, no-cache, must-revalidate");

echo "#EXTM3U\n";
echo "# Playlist generated by Demonji Stalker Manager\n";
echo "# Server: " . $server['name'] . "\n";
echo "# Detected/Used model: " . $model . "\n";
echo "# Proxy mode: " . $proxyMode . "\n";
if (!empty($selectedCategories)) {
    echo "# Category filter: " . implode(',', $selectedCategories) . "\n";
}
echo "# Channels: " . count($channels) . "\n\n";

foreach ($channels as $ch) {
    $id   = $ch['id'];
    $name = $ch['name'];
    $logo = $ch['logo'];
    $group= $ch['genre_name'] ?? 'General';
    $streamUrl = $self . '?server=' . urlencode($serverId) . '&id=' . urlencode($id);
    if ($forwardQuery) $streamUrl .= '&' . $forwardQuery;
    
    echo '#EXTINF:-1 tvg-id="' . htmlspecialchars($id) . '" tvg-name="' . htmlspecialchars($name) . '" tvg-logo="' . htmlspecialchars($logo) . '" group-title="' . htmlspecialchars($group) . '",' . $name . "\n";
    echo $streamUrl . "\n";
}
?>
