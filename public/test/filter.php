<?php
// Hardcoded Xtream Codes credentials
$serverURL = "http://filex.tv:8080"; // Replace with your Xtream server URL
$username = "646467"; // Replace with your username
$password = "646467"; // Replace with your password

$parsedUrl = parse_url($serverURL);
$hostname = isset($parsedUrl['host']) ? $parsedUrl['host'] : 'playlist';
$playlistName = preg_replace('/[^a-zA-Z0-9]/', '', $hostname);

$currentUrl = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$scriptName = basename($_SERVER['SCRIPT_NAME']);
if (empty($scriptName) || $scriptName == "index.php") {    
    $playlistUrl = rtrim($currentUrl, "/") . "/playlist.php";
} else {    
    $playlistUrl = str_replace($scriptName, "playlist.php", $currentUrl);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xtream Categories Filter</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            height: auto;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #F7F7FF, #E6E6FA);
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            color: #333333;
        }

        .container {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            padding: 30px;
            margin: 20px;
            overflow: auto;
            max-height: 85vh;
            width: 90%;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #FF9999;
            margin: 0 0 25px 0;
            text-align: center;
        }

        .checkbox-container {
            max-height: 250px;
            overflow-y: auto;
            padding: 15px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 12px 0;
            padding: 8px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.5);
            transition: background 0.2s;
        }

        .form-group:hover {
            background: rgba(255, 255, 255, 0.7);
        }

        .form-group label {
            flex: 1;
            text-align: left;
            font-weight: 500;
            color: #666666;
            padding-left: 10px;
            cursor: pointer;
        }

        .form-group input[type="checkbox"] {
            transform: scale(1.2);
            cursor: pointer;
            margin-right: 10px;
        }

        button.save-btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            background: linear-gradient(45deg, #99E2B7, #A3BFFA);
            color: #333333;
        }

        button.save-btn:hover {
            background: linear-gradient(45deg, #80D4AA, #8C9EFF);
            box-shadow: 0 0 10px rgba(128, 212, 170, 0.4);
            transform: translateY(-2px);
        }

        .popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.95);
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: none;
            max-width: 90%;
            text-align: center;
            border: 2px solid #FF9999;
        }

        .popup p {
            margin: 0 0 20px;
            color: #333333;
        }

        .popup button {
            width: auto;
            padding: 10px 20px;
            margin: 0 auto;
            display: inline-block;
            background: linear-gradient(45deg, #99E2B7, #A3BFFA);
            color: #333333;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .popup button:hover {
            background: linear-gradient(45deg, #80D4AA, #8C9EFF);
            box-shadow: 0 0 10px rgba(128, 212, 170, 0.6);
            transform: translateY(-2px);
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            display: none;
            z-index: 999;
        }

        .search-container {
            margin-bottom: 20px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.5);
            color: #333333;
            font-size: 14px;
            box-sizing: border-box;
        }

        .search-input:focus {
            outline: none;
            border-color: #FF9999;
            box-shadow: 0 0 5px rgba(255, 153, 153, 0.5);
        }

        .search-container::after {
            content: '🔍';
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666666;
        }

        #loadingIndicator {
            font-size: 18px;
            font-weight: bold;
            display: none;
            color: #FF9999;
            margin: 20px 0;
        }

        .playlist-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }

        .playlist-container input {
            flex: 1;
            padding: 12px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.5);
            color: #333333;
            font-size: 14px;
        }

        .btn {
            padding: 10px;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.1);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: rgba(255, 153, 153, 0.2);
            border-color: #FF9999;
            box-shadow: 0 0 10px rgba(255, 153, 153, 0.3);
        }

        .btn i {
            font-size: 16px;
            color: #333333;
        }

        @media (max-width: 480px) {
            .container {
                padding: 20px;
                background: rgba(255, 255, 255, 0.9);
                border: 1px solid rgba(0, 0, 0, 0.1);
            }

            h2 {
                font-size: 1.5em;
                color: #FF9999;    
            }

            .form-group {
                flex-direction: column;
                align-items: flex-start;
                padding: 10px;
                background: rgba(255, 255, 255, 0.5);
            }

            .form-group:hover {
                background: rgba(255, 255, 255, 0.7);
            }

            .form-group label {
                padding-left: 0;
                margin-top: 5px;
                color: #666666;
            }

            .popup {
                width: 80%;
                padding: 15px;
                background: rgba(255, 255, 255, 0.95);
                border: 2px solid #FF9999;
            }

            .playlist-container {
                flex-direction: column;
                align-items: stretch;
            }

            .playlist-container input {
                margin-bottom: 10px;
                background: rgba(255, 255, 255, 0.5);
                border: 1px solid rgba(0, 0, 0, 0.1);
                color: #333333;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Xtream Categories Filter</h2>
        <div class="search-container">
            <input type="text" id="searchBox" class="search-input" placeholder="Search categories..." oninput="filterCategories()">
        </div>
        <div id="loadingIndicator">Loading categories...</div>
        <div class="checkbox-container" id="categoryList">
            <div class="form-group">
                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                <label for="selectAll">Select All</label>
            </div>
        </div>
        <button class="save-btn" onclick="saveM3U()">Save</button>
        <div class="playlist-container">
            <input type="text" id="playlist_url" value="<?= htmlspecialchars($playlistUrl) ?>" readonly>
            <button class="btn" onclick="copyToClipboard()">
                <i class="fas fa-copy"></i>
            </button>
        </div>
    </div>

    <div class="overlay" id="overlay"></div>
    <div class="popup" id="popup">
        <p id="popupMessage"></p>
        <button onclick="closePopup()">OK</button>
    </div>

    <script>
        let categories = [];
        let channels = [];
        let selectedCategories = new Set();

        const server = <?php echo json_encode($serverURL); ?>;
        const user = <?php echo json_encode($username); ?>;
        const pass = <?php echo json_encode($password); ?>;
        const basePlaylistUrl = <?php echo json_encode($playlistUrl); ?>;

        async function fetchCategoriesAndChannels() {
            if (!server || !user || !pass) {
                showPopup("IPTV details are missing in script.");
                return;
            }

            const baseURL = `${server}/player_api.php?username=${user}&password=${pass}`;
            document.getElementById("loadingIndicator").style.display = "block";
            document.getElementById("categoryList").style.display = "none";

            try {                
                const categoryRes = await fetch(`feeder.php?url=${encodeURIComponent(baseURL + '&action=get_live_categories')}`);
                const categoryText = await categoryRes.text();
                
                if (!categoryRes.ok) {
                    let errorData;
                    try {
                        errorData = JSON.parse(categoryText);
                    } catch {
                        errorData = { error: categoryText || 'Unknown error' };
                    }
                    throw new Error(`HTTP error! Status: ${categoryRes.status} - ${errorData.error || categoryRes.statusText}`);
                }

                categories = JSON.parse(categoryText);
                if (!Array.isArray(categories)) {
                    throw new Error("Invalid category data received from server.");
                }
                displayCategories(categories);
                
                const channelRes = await fetch(`feeder.php?url=${encodeURIComponent(baseURL + '&action=get_live_streams')}`);
                const channelText = await channelRes.text();                

                if (!channelRes.ok) {
                    let errorData;
                    try {
                        errorData = JSON.parse(channelText);
                    } catch {
                        errorData = { error: channelText || 'Unknown error' };
                    }
                    throw new Error(`HTTP error! Status: ${channelRes.status} - ${errorData.error || channelRes.statusText}`);
                }

                channels = JSON.parse(channelText);
                if (!Array.isArray(channels)) {
                    throw new Error("Invalid channel data received from server.");
                }
                showPopup("Categories and channels loaded successfully!");
            } catch (error) {
                console.error("Error fetching data:", error);
                showPopup(`Failed to fetch categories or channels. Error: ${error.message}. Please check your credentials or server URL.`);
            } finally {
                document.getElementById("loadingIndicator").style.display = "none";
                document.getElementById("categoryList").style.display = "block";
            }
        }

        function displayCategories(filteredCategories) {
            const categoryList = document.getElementById("categoryList");
            const selectAllDiv = categoryList.querySelector('.form-group') || document.createElement('div');
            categoryList.innerHTML = '';
            categoryList.appendChild(selectAllDiv);

            filteredCategories.forEach(cat => {
                const formGroup = document.createElement("div");
                formGroup.className = "form-group";

                const checkbox = document.createElement("input");
                checkbox.type = "checkbox";
                checkbox.value = cat.category_id;
                checkbox.id = `cat_${cat.category_id}`;
                checkbox.checked = selectedCategories.has(cat.category_id);
                checkbox.addEventListener("change", () => {
                    if (checkbox.checked) {
                        selectedCategories.add(cat.category_id);
                    } else {
                        selectedCategories.delete(cat.category_id);
                    }
                    updateSelectAllCheckbox();
                    updatePlaylistUrl();
                });

                const label = document.createElement("label");
                label.htmlFor = `cat_${cat.category_id}`;
                label.textContent = cat.category_name;

                formGroup.appendChild(checkbox);
                formGroup.appendChild(label);
                categoryList.appendChild(formGroup);
            });

            updateSelectAllCheckbox();
            updatePlaylistUrl();
        }

        function filterCategories() {
            const searchValue = document.getElementById("searchBox").value.toLowerCase();
            const filtered = categories.filter(cat => cat.category_name.toLowerCase().includes(searchValue));
            displayCategories(filtered);
        }

        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById("selectAll");
            const isChecked = selectAllCheckbox.checked;
            const searchValue = document.getElementById("searchBox").value.toLowerCase();
            const filteredCategories = searchValue 
                ? categories.filter(cat => cat.category_name.toLowerCase().includes(searchValue))
                : categories;

            if (isChecked) {
                filteredCategories.forEach(cat => selectedCategories.add(cat.category_id));
            } else {
                filteredCategories.forEach(cat => selectedCategories.delete(cat.category_id));
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
            const selected = Array.from(selectedCategories);
            if (selected.length > 0) {
                playlistInput.value = `${basePlaylistUrl}?categories=${encodeURIComponent(selected.join(','))}`;
            } else {
                playlistInput.value = basePlaylistUrl;
            }
        }

        async function saveM3U() {
            const selected = Array.from(selectedCategories);
            if (!selected.length) {
                showPopup("No categories selected. Please select at least one category.");
                return;
            }

            const filteredChannels = channels.filter(ch => selected.includes(ch.category_id));
            if (!filteredChannels.length) {
                showPopup("No channels found for the selected categories.");
                return;
            }

            const playlistUrlWithCategories = `${basePlaylistUrl}?categories=${encodeURIComponent(selected.join(','))}`;
            document.getElementById("playlist_url").value = playlistUrlWithCategories;

            try {
                const response = await fetch(playlistUrlWithCategories);
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                showPopup(`Category filter applied! Use this playlist URL: ${playlistUrlWithCategories}`);
            } catch (error) {
                console.error("Error verifying playlist:", error);
                showPopup(`Failed to verify playlist. Error: ${error.message}. Please try again.`);
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
            const popup = document.getElementById("popup");
            const popupMessage = document.getElementById("popupMessage");
            const overlay = document.getElementById("overlay");
            popupMessage.textContent = message;
            popup.style.display = "block";
            overlay.style.display = "block";
        }

        function closePopup() {
            const popup = document.getElementById("popup");
            const overlay = document.getElementById("overlay");
            popup.style.display = "none";
            overlay.style.display = "none";
        }

        window.onload = fetchCategoriesAndChannels;
    </script>
</body>
</html>
