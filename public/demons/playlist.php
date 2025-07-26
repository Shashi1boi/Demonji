<?php
// Disable HTML error output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

include "config.php";

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$server = $_SERVER['HTTP_HOST'] ?? '';
$currentScript = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

// Validate credentials
if (empty($url) || empty($mac) || empty($sn) || empty($device_id_1) || empty($device_id_2)) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Missing required credentials"]);
    exit;
}

function generateId($cmd) {
    $cmdParts = explode("/", $cmd);
    if ($cmdParts[2] === "localhost") {
        $cmd = str_ireplace('ffrt http://localhost/ch/', '', $cmd);
    } else if ($cmdParts[2] === "") {
        $cmd = str_ireplace('ffrt http:///ch/', '', $cmd);
    }
    return $cmd;
}

function getImageUrl($channel, $host) {    
    $imageExtensions = [".png", ".jpg"];
    $emptyReplacements = ['', ""];
    
    $logo = str_replace($imageExtensions, $emptyReplacements, $channel['logo']);
    if (is_numeric($logo)) {
        return 'http://' . $host . '/stalker_portal/misc/logos/320/' . $channel['logo'];
    } else {
        return "https://i.ibb.co/39Nz2wgJ/stalker.png";
    }
}

// Handle AJAX request for channels
if (isset($_GET['action']) && $_GET['action'] === 'get_channels') {
    $Playlist_url = "http://$host/stalker_portal/server/load.php?type=itv&action=get_all_channels&JsHttpRequest=1-xml";
    $Playlist_HED = [
        "User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG200 stbapp ver: 2 rev: 250 Safari/533.3",
        "Authorization: Bearer " . generate_token($host, $mac, $sn, $device_id_1, $device_id_2, $sig),
        "X-User-Agent: Model: MAG250; Link: WiFi",
        "Referer: http://$host/stalker_portal/c/",
        "Accept: */*",
        "Host: $host",
        "Connection: Keep-Alive",
        "Accept-Encoding: gzip",
    ];

    $playlist_result = Info($Playlist_url, $Playlist_HED, $mac);
    $playlist_result_data = $playlist_result["Info_arr"]["data"] ?? '';
    $playlist_json_data = json_decode($playlist_result_data, true);
    
    if (!$playlist_json_data || !isset($playlist_json_data["js"]["data"])) {
        log_error("Channel fetch failed. URL: $Playlist_url, Response: $playlist_result_data");
        echo json_encode(["error" => "Failed to fetch channels"]);
        exit;
    }
    
    header('Content-Type: application/json');
    echo json_encode($playlist_json_data["js"]["data"] ?? []);
    exit;
}

// Get selected categories
$selectedCategories = [];
if (isset($_GET['categories']) && !empty($_GET['categories'])) {
    $selectedCategories = explode(',', $_GET['categories']);
    $selectedCategories = array_map('trim', $selectedCategories);
}

$Playlist_url = "http://$host/stalker_portal/server/load.php?type=itv&action=get_all_channels&JsHttpRequest=1-xml";
$Playlist_HED = [
    "User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG200 stbapp ver: 2 rev: 250 Safari/533.3",
    "Authorization: Bearer " . generate_token($host, $mac, $sn, $device_id_1, $device_id_2, $sig),
    "X-User-Agent: Model: MAG250; Link: WiFi",
    "Referer: http://$host/stalker_portal/c/",
    "Accept: */*",
    "Host: $host",
    "Connection: Keep-Alive",
    "Accept-Encoding: gzip",
];

$playlist_result = Info($Playlist_url, $Playlist_HED, $mac);
$playlist_result_data = $playlist_result["Info_arr"]["data"] ?? '';
$playlist_json_data = json_decode($playlist_result_data, true);

if (empty($playlist_json_data) || !isset($playlist_json_data["js"]["data"])) {
    log_error("Playlist generation failed. URL: $Playlist_url, Response: $playlist_result_data");
    header('Content-Type: application/json');
    echo json_encode(["error" => "Empty or invalid response from the server"]);
    exit;
}

$timestamp = date('l jS \of F Y h:i:s A');
$tvCategories = group_title($host, $mac, $sn, $device_id_1, $device_id_2, $sig, true);
$playlistContent = "#EXTM3U\n#DATE:- $timestamp\n" . PHP_EOL;

foreach ($playlist_json_data["js"]["data"] as $channel) {
    $genreId = $channel['tv_genre_id'];
    if (!empty($selectedCategories) && !in_array($genreId, $selectedCategories)) {
        continue;
    }

    $cmd = $channel['cmd'];
    $id = generateId($cmd);
    $playPath = str_replace("playlist.php", "play.php?id=" . $id . "&" . http_build_query([
        'url' => $url,
        'mac' => $mac,
        'sn' => $sn,
        'device_id_1' => $device_id_1,
        'device_id_2' => $device_id_2,
        'sig' => $sig
    ]), $currentScript);
    $playlistContent .= '#EXTINF:-1 tvg-id="' . $id . '" tvg-logo="' . getImageUrl($channel, $host) . '" group-title="' . ($tvCategories[$genreId] ?? 'Unknown') . '",' . $channel['name'] . "\r\n";
    $playlistContent .= "{$protocol}{$server}{$playPath}" . PHP_EOL . PHP_EOL;
}

header('Content-Type: audio/x-mpegurl');
header('Content-Disposition: inline; filename="playlist.m3u"');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
echo $playlistContent;
?>
