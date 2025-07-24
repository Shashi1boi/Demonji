<?php
include 'config.php';

$show_popup = false;
$popup_message = '';
$popup_type = '';

$currentUrl = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$scriptName = basename($_SERVER['SCRIPT_NAME']);
$playlistUrl = empty($scriptName) || $scriptName == "index.php" ? rtrim($currentUrl, "/") . "/playlist.php" : str_replace($scriptName, "playlist.php", $currentUrl);

// Sanitize inputs if submitted
$storedData = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sanitize = function ($input) {
        return htmlspecialchars(trim($input));
    };
    $storedData = [
        "url" => $sanitize($_POST['url'] ?? ''),
        "mac" => $sanitize($_POST['mac'] ?? ''),
        "serial_number" => $sanitize($_POST['sn'] ?? ''),
        "device_id_1" => $sanitize($_POST['device_id_1'] ?? ''),
        "device_id_2" => $sanitize($_POST['device_id_2'] ?? ''),
        "signature" => $sanitize($_POST['sig'] ?? '')
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Stalker to M3U</title>
    <style>
        body {
            min-height: 100vh;
            height: auto;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            color: #e0e0e0;
            padding: 20px;
        }

        .container {
            background: rgba(34, 40, 49, 0.9);
            border-radius: 20px;
            padding: 30px;
            margin: 20px;
            overflow: auto;
            max-height: 90vh;
            width: 90%;
            max-width: 600px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        h2 {
            color: #00d4ff;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.5);
            margin-bottom: 25px;
        }

        .form-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 12px 0;
        }

        .form-group label {
            flex: 1;
            text-align: left;
            font-weight: 600;
            color: #a0a0a0;
        }

        .form-group input {
            flex: 2;
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            color: #e0e0e0;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            border-color: #00d4ff;
            box-shadow: 0 0 8px rgba(0, 212, 255, 0.3);
            outline: none;
        }

        input::placeholder {
            color: rgba(224, 224, 224, 0.4);
        }

        .checkbox-container {
            max-height: 300px;
            overflow-y: auto;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            margin: 20px 0;
        }

        .checkbox-container .form-group {
            padding: 8px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.03);
            transition: background 0.2s;
        }

        .checkbox-container .form-group:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .checkbox-container input[type="checkbox"] {
            transform: scale(1.2);
            cursor: pointer;
            margin-right: 10px;
        }

        button {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        button.connect-btn {
            background: linear-gradient(45deg, #0077b6, #023e8a);
            color: white;
        }

        button.connect-btn:hover {
            background: linear-gradient(45deg, #0096c7, #0353a4);
            box-shadow: 0 0px 10px rgba(0, 150, 199, 0.4);
            transform: translateY(-2px);
        }

        button.save-btn {
            background: linear-gradient(45deg, #0077b6, #023e8a);
            color: white;
        }

        button.save-btn:hover {
            background: linear-gradient(45deg, #0096c7, #0353a4);
            box-shadow: 0 0px 10px rgba(0, 150, 199, 0.4);
            transform: translateY(-2px);
        }

        .playlist-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }

        .playlist-container label {
            font-weight: 600;
            color: #a0a0a0;
        }

        .playlist-container input {
            flex: 1;
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            color: #e0e0e0;
            font-size: 16px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: rgba(0, 212, 255, 0.2);
            border-color: #00d4ff;
            box-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
        }

        .btn i {
            font-size: 16px;
            color: #e0e0e0;
        }

        .search-container {
            margin-bottom: 20px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: #e0e0e0;
            font-size: 14px;
            box-sizing: border-box;
        }

        .search-input:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 5px rgba(0, 212, 255, 0.5);
        }

        .search-container::after {
            content: '🔍';
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0a0a0;
        }

        .popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(34, 40, 49, 0.95);
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
            max-width: 90%;
            text-align: center;
            border: 2px solid #00d4ff;
        }

        .popup button {
            width: auto;
            padding: 10px 20px;
            margin: 0 auto;
            display: inline-block;
            background: linear-gradient(45deg, #0077b6, #023e8a);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .popup button:hover {
            background: linear-gradient(45deg, #0096c7, #0353a4);
            box-shadow: 0 0px 10px rgba(0, 150, 199, 0.6);
            transform: translateY(-2px);
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            z-index: 999;
        }

        #loadingIndicator {
            font-size: 18px;
            font-weight: bold;
            display: none;
            color: #00d4ff;
            margin: 20px 0;
        }

        @media (max-width: 480px) {
            .container {
                padding: 20px;
            }

            h2 {
                font-size: 1.5em;
            }

            .form-group {
                flex-direction: column;
                align-items: flex-start;
                padding: 10px;
            }

            .form-group label {
                padding-left: 0;
                margin-bottom: 5px;
            }

            .popup {
                width: 80%;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Stalker to M3U</h2>
        <form method="post">
            <div class="form-group">
                <label>URL:</label>
                <input type="text" name="url" placeholder="Enter URL" value="<?= htmlspecialchars($storedData['url'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>MAC Address:</label>
                <input type="text" name="mac" placeholder="Enter MAC Address" value="<?= htmlspecialchars($storedData['mac'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Serial Number:</label>
                <input type="text" name="sn" placeholder="Enter Serial Number" value="<?= htmlspecialchars($storedData['serial_number'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Device ID 1:</label>
                <input type="text" name="device_id_1" placeholder="Enter Device ID 1" value="<?= htmlspecialchars($storedData['device_id_1'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Device ID 2:</label>
                <input type="text" name="device_id_2" placeholder="Enter Device ID 2" value="<?= htmlspecialchars($storedData['device_id_2'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Signature:</label>
                <input type="text" name="sig" placeholder="Enter Signature (Optional)" value="<?= htmlspecialchars($storedData['signature'] ?? '') ?>">
            </div>
            <button type="submit" class="connect-btn">Load Groups</button>
        </form>

        <?php if (!empty($storedData)): ?>
            <div class="search-container">
                <input type="text" id="groupSearch" class="search-input" placeholder="Search groups..." oninput="filterGroups()">
            </div>
            <div id="loadingIndicator">Loading groups...</div>
            <div class="checkbox-container" id="groupList">
                <div class="form-group">
                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                    <label for="selectAll">Select All</label>
                </div>
            </div>
            <button class="save-btn" onclick="saveM3U()">Generate Playlist</button>
            <div class="playlist-container">
                <label>Playlist:</label>
                <input type="text" id="playlist_url" value="<?= htmlspecialchars($playlistUrl) ?>" readonly>
                <div class="action-buttons">
                    <button class="btn" onclick="copyToClipboard()">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 20px; font-size: 18px;">
            <strong>with ❤️ by DEMONJI</strong>
        </div>
    </div>

    <div id="overlay" class="overlay" onclick="hidePopup()"></div>
    <div id="popup" class="popup <?php echo $popup_type; ?>">
        <p id="popup-message"></p>
        <div id="popup-buttons">
            <button onclick="hidePopup()">OK</button>
        </div>
    </div>

    <script>
        let groups = [];
        let channels = [];
        let selectedGroups = new Set();
        const basePlaylistUrl = <?= json_encode($playlistUrl) ?>;

        async function fetchGroupsAndChannels() {
            document.getElementById("loadingIndicator").style.display = "block";
            document.getElementById("groupList").style.display = "none";

            const credentials = {
                url: <?= json_encode($storedData['url'] ?? '') ?>,
                mac: <?= json_encode($storedData['mac'] ?? '') ?>,
                sn: <?= json_encode($storedData['serial_number'] ?? '') ?>,
                device_id_1: <?= json_encode($storedData['device_id_1'] ?? '') ?>,
                device_id_2: <?= json_encode($storedData['device_id_2'] ?? '') ?>,
                sig: <?= json_encode($storedData['signature'] ?? '') ?>
            };

            try {
                const groupRes = await fetch(`config.php?action=get_groups&${new URLSearchParams(credentials)}`);
                if (!groupRes.ok) {
                    throw new Error(`HTTP error! Status: ${groupRes.status}`);
                }
                groups = await groupRes.json();
                if (!Object.keys(groups).length) {
                    throw new Error("No groups received from server.");
                }

                const channelRes = await fetch(`playlist.php?action=get_channels&${new URLSearchParams(credentials)}`);
                if (!channelRes.ok) {
                    throw new Error(`HTTP error! Status: ${channelRes.status}`);
                }
                channels = await channelRes.json();
                if (!Array.isArray(channels)) {
                    throw new Error("Invalid channel data received from server.");
                }

                displayGroups(groups);
                showPopup("Groups and channels loaded successfully!");
            } catch (error) {
                console.error("Error fetching data:", error);
                showPopup(`Failed to fetch groups or channels. Error: ${error.message}. Please check your credentials or server URL.`);
            } finally {
                document.getElementById("loadingIndicator").style.display = "none";
                document.getElementById("groupList").style.display = "block";
            }
        }

        function displayGroups(filteredGroups) {
            const groupList = document.getElementById("groupList");
            const selectAllDiv = groupList.querySelector('.form-group') || document.createElement('div');
            groupList.innerHTML = '';
            groupList.appendChild(selectAllDiv);

            Object.keys(filteredGroups).forEach(id => {
                const formGroup = document.createElement("div");
                formGroup.className = "form-group";

                const checkbox = document.createElement("input");
                checkbox.type = "checkbox";
                checkbox.value = id;
                checkbox.id = `group_${id}`;
                checkbox.checked = selectedGroups.has(id);
                checkbox.addEventListener("change", () => {
                    if (checkbox.checked) {
                        selectedGroups.add(id);
                    } else {
                        selectedGroups.delete(id);
                    }
                    updateSelectAllCheckbox();
                    updatePlaylistUrl();
                });

                const label = document.createElement("label");
                label.htmlFor = `group_${id}`;
                label.textContent = filteredGroups[id];

                formGroup.appendChild(checkbox);
                formGroup.appendChild(label);
                groupList.appendChild(formGroup);
            });

            updateSelectAllCheckbox();
            updatePlaylistUrl();
        }

        function filterGroups() {
            const searchValue = document.getElementById("groupSearch").value.toLowerCase();
            const filtered = Object.fromEntries(
                Object.entries(groups).filter(([id, title]) => title.toLowerCase().includes(searchValue))
            );
            displayGroups(filtered);
        }

        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById("selectAll");
            const isChecked = selectAllCheckbox.checked;
            const searchValue = document.getElementById("groupSearch").value.toLowerCase();
            const filteredGroups = searchValue 
                ? Object.fromEntries(Object.entries(groups).filter(([id, title]) => title.toLowerCase().includes(searchValue)))
                : groups;

            if (isChecked) {
                Object.keys(filteredGroups).forEach(id => selectedGroups.add(id));
            } else {
                Object.keys(filteredGroups).forEach(id => selectedGroups.delete(id));
            }

            document.querySelectorAll('.checkbox-container input[type="checkbox"]:not(#selectAll)').forEach(checkbox => {
                checkbox.checked = isChecked;
            });

            updateSelectAllCheckbox();
            updatePlaylistUrl();
        }

        function updateSelectAllCheckbox() {
            const selectAllCheckbox = document.getElementById("selectAll");
            const allCheckboxes = document.querySelectorAll('.checkbox-container input[type="checkbox"]:not(#selectAll)');
            const allChecked = Array.from(allCheckboxes).every(checkbox => checkbox.checked);
            const someChecked = Array.from(allCheckboxes).some(checkbox => checkbox.checked);

            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = someChecked && !allChecked;
        }

        function updatePlaylistUrl() {
            const playlistInput = document.getElementById("playlist_url");
            const selected = Array.from(selectedGroups);
            const credentials = {
                url: <?= json_encode($storedData['url'] ?? '') ?>,
                mac: <?= json_encode($storedData['mac'] ?? '') ?>,
                sn: <?= json_encode($storedData['serial_number'] ?? '') ?>,
                device_id_1: <?= json_encode($storedData['device_id_1'] ?? '') ?>,
                device_id_2: <?= json_encode($storedData['device_id_2'] ?? '') ?>,
                sig: <?= json_encode($storedData['signature'] ?? '') ?>
            };
            const params = new URLSearchParams(credentials);
            if (selected.length > 0) {
                params.append('categories', selected.join(','));
            }
            playlistInput.value = `${basePlaylistUrl}?${params.toString()}`;
        }

        async function saveM3U() {
            const selected = Array.from(selectedGroups);
            if (!selected.length) {
                showPopup("No groups selected. Please select at least one group.");
                return;
            }

            const filteredChannels = channels.filter(ch => selected.includes(ch.tv_genre_id));
            if (!filteredChannels.length) {
                showPopup("No channels found for the selected groups.");
                return;
            }

            const credentials = {
                url: <?= json_encode($storedData['url'] ?? '') ?>,
                mac: <?= json_encode($storedData['mac'] ?? '') ?>,
                sn: <?= json_encode($storedData['serial_number'] ?? '') ?>,
                device_id_1: <?= json_encode($storedData['device_id_1'] ?? '') ?>,
                device_id_2: <?= json_encode($storedData['device_id_2'] ?? '') ?>,
                sig: <?= json_encode($storedData['signature'] ?? '') ?>
            };
            const params = new URLSearchParams(credentials);
            params.append('categories', selected.join(','));

            const playlistUrlWithCategories = `${basePlaylistUrl}?${params.toString()}`;
            document.getElementById("playlist_url").value = playlistUrlWithCategories;

            try {
                const response = await fetch(playlistUrlWithCategories);
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                showPopup(`Playlist generated! Use this URL: ${playlistUrlWithCategories}`);
            } catch (error) {
                console.error("Error verifying playlist:", error);
                showPopup(`Failed to generate playlist. Error: ${error.message}. Please try again.`);
            }
        }

        function copyToClipboard() {
            const playlistUrl = document.getElementById("playlist_url");
            playlistUrl.select();
            try {
                document.execCommand('copy');
                showPopup("Playlist URL copied to clipboard!");
            } catch (err) {
                console.error("Failed to copy: ", err);
                showPopup("Failed to copy URL. Please copy manually.");
            }
        }

        function showPopup(message) {
            document.getElementById("popup-message").textContent = message;
            document.getElementById("popup").style.display = "block";
            document.getElementById("overlay").style.display = "block";
        }

        function hidePopup() {
            document.getElementById("popup").style.display = "none";
            document.getElementById("overlay").style.display = "none";
        }

        <?php if (!empty($storedData)): ?>
            window.onload = fetchGroupsAndChannels;
        <?php endif; ?>

        <?php if ($show_popup): ?>
            document.addEventListener('DOMContentLoaded', () => {
                showPopup('<?= $popup_message ?>');
            });
        <?php endif; ?>
    </script>
</body>
</html>
