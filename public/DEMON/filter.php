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
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.2);
        }
        h2 {
            color: #f0f0f0;
            text-align: center;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .server-select {
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            background: #0f3460;
            color: white;
            border: none;
            font-size: 16px;
            margin-bottom: 20px;
            cursor: pointer;
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
            padding: 10px;
            border-radius: 8px;
            border: none;
            background: #1a1a2e;
            color: #00d8ff;
            font-family: monospace;
            font-size: 12px;
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
            padding: 12px;
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

    <div class="playlist-box">
        <div style="margin-bottom: 10px; color: #ccc;">Your M3U Playlist URL:</div>
        <div class="playlist-url">
            <input type="text" id="playlistUrl" readonly value="">
            <button class="btn" onclick="copyToClipboard()"><i class="fas fa-copy"></i></button>
        </div>
        <button class="btn generate-btn" onclick="updatePlaylistUrl()"><i class="fas fa-sync-alt"></i> Generate / Refresh</button>
    </div>
    <div class="loading" id="loading">Fetching channels, please wait...</div>
    <div class="note">
        <i class="fas fa-shield-alt"></i> No login, no storage – playlist generated live.<br>
        Paste URL into any IPTV player (TiviMate, VLC, OTT Navigator).
    </div>
</div>

<script>
    const baseUrl = window.location.href.split('?')[0].replace('filter.php', 'playlist.php');
    const playlistInput = document.getElementById('playlistUrl');
    const serverSelect = document.getElementById('serverSelect');
    const loadingDiv = document.getElementById('loading');

    function updatePlaylistUrl() {
        const serverId = serverSelect.value;
        const url = `${baseUrl}?server=${encodeURIComponent(serverId)}`;
        playlistInput.value = url;
        // Optional: verify that the playlist is reachable
        loadingDiv.style.display = 'block';
        fetch(url, { method: 'HEAD' })
            .then(res => {
                if (res.ok) alert('Playlist is ready!');
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

    // Generate on page load
    updatePlaylistUrl();
</script>
</body>
</html>