<?php require_once 'config.php'; ?>
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
            max-width: 600px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.2);
        }
        h2 {
            color: #f0f0f0;
            text-align: center;
            margin-bottom: 20px;
        }
        .server-select, select, input {
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            background: #0f3460;
            color: white;
            border: none;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .advanced-toggle {
            background: none;
            border: none;
            color: #00d8ff;
            cursor: pointer;
            font-size: 14px;
            margin: 10px 0;
            text-align: left;
            width: 100%;
        }
        .advanced-section {
            display: none;
            background: rgba(15,52,96,0.5);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .playlist-box {
            background: #0f3460;
            border-radius: 12px;
            padding: 15px;
            margin: 20px 0;
        }
        .playlist-url {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .playlist-url input {
            flex: 1;
            background: #1a1a2e;
            color: #00d8ff;
            font-family: monospace;
            font-size: 12px;
            margin: 0;
        }
        .btn {
            background: #e94560;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn:hover {
            background: #ff6b6b;
            transform: translateY(-2px);
        }
        .generate-btn {
            width: 100%;
            background: #00d8ff;
            color: #1a1a2e;
            font-weight: bold;
            margin-top: 10px;
        }
        .note {
            font-size: 12px;
            color: #ccc;
            text-align: center;
            margin-top: 20px;
        }
        .loading {
            display: none;
            text-align: center;
            color: #00d8ff;
            margin: 10px 0;
        }
        label {
            display: block;
            font-size: 12px;
            margin-bottom: 5px;
            color: #ccc;
        }
        .row2 {
            display: flex;
            gap: 10px;
        }
        .row2 > div {
            flex: 1;
        }
        .auto-badge {
            font-size: 11px;
            color: #00d8ff;
            margin-left: 8px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2><i class="fas fa-tv"></i> Demonji · Stalker Portal</h2>
    <select id="serverSelect" class="server-select">
        <?php foreach ($stalkerServers as $s): ?>
            <option value="<?= htmlspecialchars($s['id']) ?>"><?= htmlspecialchars($s['name']) ?></option>
        <?php endforeach; ?>
    </select>

    <!-- Advanced Options Toggle -->
    <button class="advanced-toggle" onclick="toggleAdvanced()">
        <i class="fas fa-sliders-h"></i> Advanced Configuration
    </button>
    <div id="advancedSection" class="advanced-section">
        <div class="row2">
            <div>
                <label>MAG Model <span class="auto-badge">(Auto-detected if empty)</span></label>
                <input type="text" id="model" placeholder="Leave empty for auto">
            </div>
            <div>
                <label>SN Cut (optional)</label>
                <input type="text" id="sn_cut" placeholder="Auto-generate if empty">
            </div>
        </div>
        <div class="row2">
            <div>
                <label>Device ID</label>
                <input type="text" id="device_id" placeholder="Auto from MAC">
            </div>
            <div>
                <label>Device ID 2</label>
                <input type="text" id="device_id2" placeholder="Same as Device ID">
            </div>
        </div>
        <div>
            <label>Signature</label>
            <input type="text" id="signature" placeholder="Auto from SN Cut + MAC">
        </div>
        <div>
            <label>Stream Proxy Mode</label>
            <select id="proxy_mode">
                <option value="redirect">Redirect (direct CDN, zero server bandwidth)</option>
                <option value="proxy">Proxy (through server, hides real URL)</option>
            </select>
        </div>
    </div>

    <div class="playlist-box">
        <div style="margin-bottom: 10px; color: #ccc;">Your M3U Playlist URL:</div>
        <div class="playlist-url">
            <input type="text" id="playlistUrl" readonly value="">
            <button class="btn" onclick="copyToClipboard()"><i class="fas fa-copy"></i></button>
        </div>
        <button class="btn generate-btn" onclick="updatePlaylistUrl()"><i class="fas fa-sync-alt"></i> Generate / Refresh</button>
    </div>
    <div class="loading" id="loading">Generating playlist, please wait...</div>
    <div class="note">
        <i class="fas fa-shield-alt"></i> No login, no storage – all settings are in the URL.<br>
        Leave MAG model empty to auto‑detect the correct model for your portal.
    </div>
</div>

<script>
    const baseUrl = window.location.href.split('?')[0].replace('filter.php', 'playlist.php');
    const playlistInput = document.getElementById('playlistUrl');
    const serverSelect = document.getElementById('serverSelect');
    const loadingDiv = document.getElementById('loading');

    function toggleAdvanced() {
        const sec = document.getElementById('advancedSection');
        sec.style.display = sec.style.display === 'none' ? 'block' : 'none';
    }

    function updatePlaylistUrl() {
        const serverId = serverSelect.value;
        const model = document.getElementById('model').value.trim();
        const sn_cut = document.getElementById('sn_cut').value.trim();
        const device_id = document.getElementById('device_id').value.trim();
        const device_id2 = document.getElementById('device_id2').value.trim();
        const signature = document.getElementById('signature').value.trim();
        const proxy_mode = document.getElementById('proxy_mode').value;

        let params = new URLSearchParams();
        params.set('server', serverId);
        if (model) params.set('model', model);
        if (sn_cut) params.set('sn_cut', sn_cut);
        if (device_id) params.set('device_id', device_id);
        if (device_id2) params.set('device_id2', device_id2);
        if (signature) params.set('signature', signature);
        params.set('proxy', proxy_mode);

        const url = baseUrl + '?' + params.toString();
        playlistInput.value = url;

        loadingDiv.style.display = 'block';
        // HEAD request to verify
        fetch(url, { method: 'HEAD' })
            .then(res => {
                if (res.ok) alert('Playlist generated successfully!');
                else alert('Error: Server might be unreachable or invalid.');
            })
            .catch(() => alert('Could not reach the playlist generator.'))
            .finally(() => loadingDiv.style.display = 'none');
    }

    function copyToClipboard() {
        playlistInput.select();
        document.execCommand('copy');
        alert('Playlist URL copied!');
    }

    // Load/save advanced settings from localStorage
    function loadSaved() {
        const saved = localStorage.getItem('demonji_advanced');
        if (saved) {
            const data = JSON.parse(saved);
            if (data.model) document.getElementById('model').value = data.model;
            if (data.sn_cut) document.getElementById('sn_cut').value = data.sn_cut;
            if (data.device_id) document.getElementById('device_id').value = data.device_id;
            if (data.device_id2) document.getElementById('device_id2').value = data.device_id2;
            if (data.signature) document.getElementById('signature').value = data.signature;
            if (data.proxy_mode) document.getElementById('proxy_mode').value = data.proxy_mode;
        }
    }
    function saveAdvanced() {
        const data = {
            model: document.getElementById('model').value,
            sn_cut: document.getElementById('sn_cut').value,
            device_id: document.getElementById('device_id').value,
            device_id2: document.getElementById('device_id2').value,
            signature: document.getElementById('signature').value,
            proxy_mode: document.getElementById('proxy_mode').value
        };
        localStorage.setItem('demonji_advanced', JSON.stringify(data));
    }
    document.getElementById('model').addEventListener('input', saveAdvanced);
    document.getElementById('sn_cut').addEventListener('input', saveAdvanced);
    document.getElementById('device_id').addEventListener('input', saveAdvanced);
    document.getElementById('device_id2').addEventListener('input', saveAdvanced);
    document.getElementById('signature').addEventListener('input', saveAdvanced);
    document.getElementById('proxy_mode').addEventListener('change', saveAdvanced);
    loadSaved();

    // Generate on page load
    updatePlaylistUrl();
</script>
</body>
</html>
