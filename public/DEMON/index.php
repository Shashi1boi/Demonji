<?php
require_once 'StalkerLite.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(120);

// --- Playlist generation request ---
if (isset($_GET['action']) && $_GET['action'] === 'playlist') {
    $portalUrl = $_GET['url'] ?? '';
    $mac       = $_GET['mac'] ?? '';
    $model     = $_GET['model'] ?? '';
    $proxyMode = $_GET['proxy'] ?? 'redirect';
    
    $extras = [];
    if (!empty($_GET['sn_cut']))     $extras['sn_cut'] = $_GET['sn_cut'];
    if (!empty($_GET['device_id']))  $extras['device_id'] = $_GET['device_id'];
    if (!empty($_GET['device_id2'])) $extras['device_id2'] = $_GET['device_id2'];
    if (!empty($_GET['signature']))  $extras['signature'] = $_GET['signature'];
    
    $selectedCategories = [];
    if (!empty($_GET['categories'])) {
        $selectedCategories = explode(',', $_GET['categories']);
        $selectedCategories = array_map('trim', $selectedCategories);
    }
    
    if (empty($portalUrl) || empty($mac)) die("# ERROR: Missing portal URL or MAC address\n");
    
    // Auto-detect model if not provided
    if (empty($model)) {
        $commonModels = ['MAG524', 'MAG544w3', 'MAG500A', 'MAG520', 'MAG522', 'MAG528', 'MAG540', 'MAG250', 'MAG254', 'MAG256', 'MAG322', 'MAG324', 'MAG349', 'MAG351', 'MAG420', 'MAG424'];
        $detected = null;
        foreach ($commonModels as $testModel) {
            $test = new StalkerLite($portalUrl, $mac, $testModel, $extras);
            $testConnect = $test->connect();
            if ($testConnect['success']) {
                $detected = $testModel;
                break;
            }
        }
        if (!$detected) die("# ERROR: Could not auto-detect MAG model. Please specify one manually.\n");
        $model = $detected;
    }
    
    $stalker = new StalkerLite($portalUrl, $mac, $model, $extras);
    $connect = $stalker->connect();
    if (!$connect['success']) die("# ERROR: Handshake failed – " . ($connect['error'] ?? 'unknown') . "\n");
    
    $channels = $stalker->getChannels();
    if (empty($channels)) die("# ERROR: No channels found\n");
    
    // Filter by categories
    if (!empty($selectedCategories)) {
        $filtered = [];
        foreach ($channels as $ch) {
            if (in_array($ch['genre_id'], $selectedCategories)) $filtered[] = $ch;
        }
        if (empty($filtered)) die("# ERROR: No channels match selected categories\n");
        $channels = $filtered;
    }
    
    // Build stream proxy URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $self = $protocol . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/index.php';
    
    // Forward all advanced params to stream resolver
    $forwardParams = [
        'action' => 'stream',
        'url' => $portalUrl,
        'mac' => $mac,
        'model' => $model,
        'proxy' => $proxyMode,
    ];
    foreach (['sn_cut', 'device_id', 'device_id2', 'signature'] as $p) {
        if (!empty($_GET[$p])) $forwardParams[$p] = $_GET[$p];
    }
    $forwardQuery = http_build_query($forwardParams);
    
    header('Content-Type: audio/x-mpegurl');
    header('Content-Disposition: inline; filename="playlist.m3u"');
    echo "#EXTM3U\n";
    echo "# Demonji Stalker Playlist\n";
    echo "# Portal: $portalUrl\n";
    echo "# Model: $model\n";
    echo "# Proxy: $proxyMode\n";
    if (!empty($selectedCategories)) echo "# Categories: " . implode(',', $selectedCategories) . "\n";
    echo "# Channels: " . count($channels) . "\n\n";
    
    foreach ($channels as $ch) {
        $streamUrl = $self . '?' . $forwardQuery . '&id=' . urlencode($ch['id']);
        echo '#EXTINF:-1 tvg-id="' . htmlspecialchars($ch['id']) . '" tvg-name="' . htmlspecialchars($ch['name']) . '" tvg-logo="' . htmlspecialchars($ch['logo']) . '" group-title="' . htmlspecialchars($ch['genre_name'] ?? 'General') . '",' . $ch['name'] . "\n";
        echo $streamUrl . "\n";
    }
    exit;
}

// --- Stream resolver endpoint ---
if (isset($_GET['action']) && $_GET['action'] === 'stream') {
    $portalUrl = $_GET['url'] ?? '';
    $mac       = $_GET['mac'] ?? '';
    $model     = $_GET['model'] ?? 'MAG250';
    $channelId = $_GET['id'] ?? '';
    $proxyMode = $_GET['proxy'] ?? 'redirect';
    $debug     = isset($_GET['debug']);
    
    $extras = [];
    if (!empty($_GET['sn_cut']))     $extras['sn_cut'] = $_GET['sn_cut'];
    if (!empty($_GET['device_id']))  $extras['device_id'] = $_GET['device_id'];
    if (!empty($_GET['device_id2'])) $extras['device_id2'] = $_GET['device_id2'];
    if (!empty($_GET['signature']))  $extras['signature'] = $_GET['signature'];
    
    if (!$portalUrl || !$mac || !$channelId) {
        if ($debug) { header('Content-Type: text/plain'); die("DEBUG: Missing parameters\n"); }
        http_response_code(400);
        die('Missing parameters');
    }
    
    // Debug: output all params
    if ($debug) {
        header('Content-Type: text/plain');
        echo "=== DEMONJI STREAM DEBUG ===\n";
        echo "Portal URL: $portalUrl\n";
        echo "MAC: $mac\n";
        echo "Model: $model\n";
        echo "Proxy Mode: $proxyMode\n";
        echo "Channel ID: $channelId\n";
        echo "Extras: " . print_r($extras, true) . "\n";
    }
    
    $stalker = new StalkerLite($portalUrl, $mac, $model, $extras);
    $connect = $stalker->connect();
    if (!$connect['success']) {
        if ($debug) { echo "Handshake failed: " . ($connect['error'] ?? 'unknown') . "\n"; exit; }
        http_response_code(503);
        die('Handshake failed');
    }
    if ($debug) echo "Handshake OK, token: " . substr($stalker->getToken(), 0, 20) . "...\n";
    
    $channels = $stalker->getChannels();
    if ($debug) echo "Total channels fetched: " . count($channels) . "\n";
    
    $cmd = null;
    foreach ($channels as $ch) {
        if ($ch['id'] == $channelId) {
            $cmd = $ch['cmd'];
            if ($debug) echo "Found channel: {$ch['name']} (ID {$ch['id']})\nCMD: $cmd\n";
            break;
        }
    }
    if (!$cmd) {
        if ($debug) { echo "Channel ID $channelId not found in channel list.\n"; exit; }
        http_response_code(404);
        die('Channel not found');
    }
    
    $streamUrl = $stalker->createLink($cmd);
    if ($debug) {
        echo "createLink returned: $streamUrl\n";
        if (empty($streamUrl)) echo "ERROR: createLink returned empty!\n";
        exit;
    }
    
    if (empty($streamUrl)) {
        http_response_code(502);
        die('Could not resolve stream URL');
    }
    
    if ($proxyMode === 'proxy') {
        // Simple proxy – forward with headers
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $streamUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ['User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) MAG200']
        ]);
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200 || !$data) { 
            header('Location: ' . $streamUrl); 
            exit; 
        }
        if (strpos($data, '#EXTM3U') !== false) {
            $base = parse_url($streamUrl, PHP_URL_SCHEME) . '://' . parse_url($streamUrl, PHP_URL_HOST);
            $lines = explode("\n", $data);
            foreach ($lines as &$line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) continue;
                if (!filter_var($line, FILTER_VALIDATE_URL)) {
                    $line = $base . '/' . ltrim($line, '/');
                }
            }
            header('Content-Type: application/vnd.apple.mpegurl');
            echo implode("\n", $lines);
        } else {
            header('Content-Type: video/mp2t');
            echo $data;
        }
        exit;
    }
    
    // Redirect mode
    header('Location: ' . $streamUrl);
    exit;
}

// --- API: fetch categories (JSON) ---
if (isset($_GET['action']) && $_GET['action'] === 'categories') {
    $portalUrl = $_GET['url'] ?? '';
    $mac       = $_GET['mac'] ?? '';
    $model     = $_GET['model'] ?? '';
    
    $extras = [];
    if (!empty($_GET['sn_cut']))     $extras['sn_cut'] = $_GET['sn_cut'];
    if (!empty($_GET['device_id']))  $extras['device_id'] = $_GET['device_id'];
    if (!empty($_GET['device_id2'])) $extras['device_id2'] = $_GET['device_id2'];
    if (!empty($_GET['signature']))  $extras['signature'] = $_GET['signature'];
    
    if (!$portalUrl || !$mac) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing URL or MAC']);
        exit;
    }
    
    // Auto-detect model if needed
    if (empty($model)) {
        $commonModels = ['MAG524', 'MAG544w3', 'MAG500A', 'MAG520', 'MAG522', 'MAG528', 'MAG540', 'MAG250', 'MAG254', 'MAG256', 'MAG322', 'MAG324', 'MAG349', 'MAG351', 'MAG420', 'MAG424'];
        foreach ($commonModels as $testModel) {
            $test = new StalkerLite($portalUrl, $mac, $testModel, $extras);
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
    
    $stalker = new StalkerLite($portalUrl, $mac, $model, $extras);
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

// --- Main UI (HTML form) ---
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demonji – Stalker Portal Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            font-family: 'Segoe UI', Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 30px;
            width: 100%;
            max-width: 750px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        h2 { color: #f0f0f0; text-align: center; margin-bottom: 20px; }
        input, select {
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            background: #0f3460;
            color: white;
            border: none;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .row2 { display: flex; gap: 10px; }
        .row2 > div { flex: 1; }
        .btn {
            background: #e94560;
            border: none;
            padding: 12px;
            border-radius: 12px;
            color: white;
            cursor: pointer;
            width: 100%;
            font-weight: bold;
            margin-top: 10px;
        }
        .btn-primary { background: #00d8ff; color: #1a1a2e; }
        .btn-secondary { background: #0f3460; margin-top: 5px; }
        .category-section, .advanced-section {
            background: rgba(15,52,96,0.5);
            border-radius: 12px;
            padding: 15px;
            margin: 15px 0;
            display: none;
        }
        .checkbox-container {
            max-height: 200px;
            overflow-y: auto;
            margin-top: 10px;
        }
        .category-item { display: flex; align-items: center; padding: 5px 0; gap: 10px; }
        .category-item label { color: #ddd; cursor: pointer; }
        .loading { text-align: center; color: #00d8ff; display: none; margin: 10px 0; }
        .playlist-url {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .playlist-url input {
            flex: 1;
            background: #1a1a2e;
            color: #00d8ff;
            font-family: monospace;
            margin: 0;
        }
        .note { font-size: 12px; color: #ccc; text-align: center; margin-top: 20px; }
        .adv-toggle {
            background: none;
            border: none;
            color: #00d8ff;
            cursor: pointer;
            font-size: 14px;
            text-align: left;
            margin: 10px 0;
            width: 100%;
        }
        .auto-badge { font-size: 11px; color: #00d8ff; margin-left: 8px; }
    </style>
</head>
<body>
<div class="container">
    <h2><i class="fas fa-tv"></i> Demonji · Stalker Portal</h2>
    
    <input type="text" id="portalUrl" placeholder="Portal URL (e.g., http://your-server.com/stalker_portal/c/)" value="">
    <input type="text" id="mac" placeholder="MAC Address (e.g., 00:1A:79:AA:BB:CC)" value="">
    
    <div class="row2">
        <div><input type="text" id="model" placeholder="MAG Model (leave empty for auto-detect)"></div>
        <div>
            <select id="proxyMode">
                <option value="redirect">Redirect (direct CDN, zero server bandwidth)</option>
                <option value="proxy">Proxy (through server, hides real URL)</option>
            </select>
        </div>
    </div>
    
    <!-- Advanced Options Toggle -->
    <button class="adv-toggle" onclick="toggleAdvanced()">
        <i class="fas fa-sliders-h"></i> Advanced Configuration (SN Cut, Device IDs, Signature)
    </button>
    <div id="advancedSection" class="advanced-section">
        <div class="row2">
            <div>
                <label>SN Cut <span class="auto-badge">(auto if empty)</span></label>
                <input type="text" id="sn_cut" placeholder="Auto-generate from MAC">
            </div>
            <div>
                <label>Device ID</label>
                <input type="text" id="device_id" placeholder="Auto from MAC">
            </div>
        </div>
        <div class="row2">
            <div>
                <label>Device ID 2</label>
                <input type="text" id="device_id2" placeholder="Same as Device ID">
            </div>
            <div>
                <label>Signature</label>
                <input type="text" id="signature" placeholder="Auto from SN Cut + MAC">
            </div>
        </div>
    </div>
    
    <button class="btn" id="fetchCategoriesBtn" onclick="fetchCategories()"><i class="fas fa-list"></i> Load Categories</button>
    
    <div id="categorySection" class="category-section">
        <div class="row2" style="margin-bottom: 10px;">
            <input type="text" id="searchCat" placeholder="Search categories..." oninput="filterCategories()">
            <button class="btn" style="width: auto; padding: 0 15px;" onclick="toggleSelectAll()">Select All</button>
        </div>
        <div id="categoryList" class="checkbox-container">Loading...</div>
    </div>
    
    <div class="loading" id="loading">Processing...</div>
    
    <button class="btn btn-primary" id="generateBtn" onclick="generatePlaylist()"><i class="fas fa-link"></i> Generate Playlist URL</button>
    
    <div id="resultBox" style="display: none;">
        <div class="playlist-url">
            <input type="text" id="playlistUrl" readonly>
            <button class="btn" style="width: auto;" onclick="copyUrl()"><i class="fas fa-copy"></i></button>
        </div>
        <div class="note">Copy this URL into any IPTV player (TiviMate, VLC, etc.)<br>All settings are embedded – you can bookmark it.</div>
    </div>
    
    <div class="note">
        <i class="fas fa-shield-alt"></i> No storage, no login – everything is passed in the URL.<br>
        Advanced options are optional – leave empty for automatic generation.<br>
        <strong>Troubleshooting:</strong> Append <code>&debug=1</code> to any stream URL to see diagnostic output.
    </div>
</div>

<script>
let categories = [];
let selectedCategories = new Set();

function toggleAdvanced() {
    const sec = document.getElementById('advancedSection');
    sec.style.display = sec.style.display === 'none' ? 'block' : 'none';
}

async function fetchCategories() {
    const url = document.getElementById('portalUrl').value.trim();
    const mac = document.getElementById('mac').value.trim();
    const model = document.getElementById('model').value.trim();
    const proxyMode = document.getElementById('proxyMode').value;
    const sn_cut = document.getElementById('sn_cut').value.trim();
    const device_id = document.getElementById('device_id').value.trim();
    const device_id2 = document.getElementById('device_id2').value.trim();
    const signature = document.getElementById('signature').value.trim();
    
    if (!url || !mac) {
        alert('Please enter Portal URL and MAC address');
        return;
    }
    
    const loading = document.getElementById('loading');
    loading.style.display = 'block';
    
    let params = new URLSearchParams();
    params.set('action', 'categories');
    params.set('url', url);
    params.set('mac', mac);
    if (model) params.set('model', model);
    params.set('proxy', proxyMode);
    if (sn_cut) params.set('sn_cut', sn_cut);
    if (device_id) params.set('device_id', device_id);
    if (device_id2) params.set('device_id2', device_id2);
    if (signature) params.set('signature', signature);
    
    try {
        const res = await fetch('?' + params.toString());
        if (!res.ok) throw new Error('Failed to fetch categories');
        categories = await res.json();
        if (!Array.isArray(categories)) categories = [];
        displayCategories(categories);
        document.getElementById('categorySection').style.display = 'block';
    } catch (err) {
        alert('Error fetching categories: ' + err.message);
    } finally {
        loading.style.display = 'none';
    }
}

function displayCategories(cats) {
    const container = document.getElementById('categoryList');
    container.innerHTML = '';
    if (cats.length === 0) {
        container.innerHTML = '<div style="color:#ccc;">No categories found. All channels will be included.</div>';
        return;
    }
    cats.forEach(cat => {
        const div = document.createElement('div');
        div.className = 'category-item';
        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.value = cat.id;
        cb.id = `cat_${cat.id}`;
        cb.checked = selectedCategories.has(cat.id);
        cb.addEventListener('change', () => {
            if (cb.checked) selectedCategories.add(cat.id);
            else selectedCategories.delete(cat.id);
        });
        const label = document.createElement('label');
        label.htmlFor = `cat_${cat.id}`;
        label.textContent = cat.name;
        div.appendChild(cb);
        div.appendChild(label);
        container.appendChild(div);
    });
}

function filterCategories() {
    const search = document.getElementById('searchCat').value.toLowerCase();
    const filtered = categories.filter(c => c.name.toLowerCase().includes(search));
    displayCategories(filtered);
}

function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('#categoryList input[type="checkbox"]');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => {
        cb.checked = !allChecked;
        if (cb.checked) selectedCategories.add(cb.value);
        else selectedCategories.delete(cb.value);
    });
}

function generatePlaylist() {
    const url = document.getElementById('portalUrl').value.trim();
    const mac = document.getElementById('mac').value.trim();
    const model = document.getElementById('model').value.trim();
    const proxyMode = document.getElementById('proxyMode').value;
    const sn_cut = document.getElementById('sn_cut').value.trim();
    const device_id = document.getElementById('device_id').value.trim();
    const device_id2 = document.getElementById('device_id2').value.trim();
    const signature = document.getElementById('signature').value.trim();
    
    if (!url || !mac) {
        alert('Please enter Portal URL and MAC address');
        return;
    }
    
    let params = new URLSearchParams();
    params.set('action', 'playlist');
    params.set('url', url);
    params.set('mac', mac);
    if (model) params.set('model', model);
    params.set('proxy', proxyMode);
    if (sn_cut) params.set('sn_cut', sn_cut);
    if (device_id) params.set('device_id', device_id);
    if (device_id2) params.set('device_id2', device_id2);
    if (signature) params.set('signature', signature);
    
    const selected = Array.from(selectedCategories);
    if (selected.length > 0) {
        params.set('categories', selected.join(','));
    }
    
    const playlistUrl = window.location.href.split('?')[0] + '?' + params.toString();
    document.getElementById('playlistUrl').value = playlistUrl;
    document.getElementById('resultBox').style.display = 'block';
}

function copyUrl() {
    const input = document.getElementById('playlistUrl');
    input.select();
    document.execCommand('copy');
    alert('Playlist URL copied!');
}
</script>
</body>
</html>
