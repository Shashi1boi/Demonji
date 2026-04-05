<?php
require_once 'config.php';
require_once 'StalkerLite.php';

$serverId = $_GET['server'] ?? '';
$channelId = $_GET['id'] ?? '';
if (!$serverId || !$channelId) {
    http_response_code(400);
    die('Missing server or channel ID');
}

$server = getStalkerServerById($serverId);
if (!$server) {
    http_response_code(400);
    die('Invalid server');
}

// Gather advanced params from the request (including auto-detected model)
$model = $_GET['model'] ?? 'MAG250';
$extras = [];
if (isset($_GET['sn_cut']))     $extras['sn_cut'] = $_GET['sn_cut'];
if (isset($_GET['device_id']))  $extras['device_id'] = $_GET['device_id'];
if (isset($_GET['device_id2'])) $extras['device_id2'] = $_GET['device_id2'];
if (isset($_GET['signature']))  $extras['signature'] = $_GET['signature'];

$proxyMode = $_GET['proxy'] ?? 'redirect';

$stalker = new StalkerLite($server['url'], $server['mac'], $model, $extras);
$connect = $stalker->connect();
if (!$connect['success']) {
    http_response_code(503);
    die('Handshake failed');
}

// Find the channel cmd
$channels = $stalker->getChannels();
$cmd = null;
foreach ($channels as $ch) {
    if ($ch['id'] == $channelId) {
        $cmd = $ch['cmd'];
        break;
    }
}
if (!$cmd) {
    http_response_code(404);
    die('Channel not found');
}

$streamUrl = $stalker->createLink($cmd);
if (!$streamUrl) {
    http_response_code(502);
    die('Could not resolve stream URL');
}

// --- Proxy mode (fetch and relay) ---
if ($proxyMode === 'proxy') {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $streamUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG200 stbapp ver: 2 rev: 250 Safari/533.3'
        ]
    ]);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || empty($data)) {
        // Fallback to redirect
        header('Location: ' . $streamUrl);
        exit;
    }
    
    // Check if it's an HLS manifest
    if (strpos($data, '#EXTM3U') !== false) {
        // Rewrite relative URLs to absolute
        $base = parse_url($streamUrl, PHP_URL_SCHEME) . '://' . parse_url($streamUrl, PHP_URL_HOST);
        $lines = explode("\n", $data);
        $newLines = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                $newLines[] = $line;
                continue;
            }
            if (!filter_var($line, FILTER_VALIDATE_URL)) {
                $line = $base . '/' . ltrim($line, '/');
            }
            $newLines[] = $line;
        }
        header('Content-Type: application/vnd.apple.mpegurl');
        echo implode("\n", $newLines);
    } else {
        // Assume binary stream (TS, MP4)
        header('Content-Type: video/mp2t');
        echo $data;
    }
    exit;
}

// --- Default: redirect directly to CDN (no server bandwidth) ---
header('Location: ' . $streamUrl, true, 302);
exit;
?>
