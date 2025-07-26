<?php
require_once 'config.php'; // Include the configuration file

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(0); // Prevent script timeout

// Get server ID from query parameter
$serverId = isset($_GET['server']) ? $_GET['server'] : null;
$credentials = getCredentialsById($serverId);

if (!$credentials) {
    http_response_code(500);
    die("Error: Invalid or missing server ID.");
}

// Use credentials from config
$baseUrl = $credentials['host'];
$user = $credentials['username'];
$password = $credentials['password'];

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$requestUri = $_SERVER['REQUEST_URI'];
$m3u8Url = $protocol . $host . dirname($requestUri) . "/stream/$serverId/";

if (empty($baseUrl) || empty($user) || empty($password)) {
    http_response_code(500);
    die("Error: Missing url, user, or password in script.");
}

$parsedUrl = parse_url($baseUrl);
$hostname = $parsedUrl['host'] ?? '';
if (empty($hostname)) {
    http_response_code(500);
    die("Error: Invalid URL: Unable to extract hostname.");
}

$apiUrl = "$baseUrl/player_api.php?username=$user&password=$password&action=get_live_streams";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
    "Accept: */*",
    "Connection: keep-alive"
]);
$apiResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($apiResponse === false || $httpCode >= 400) {
    http_response_code(500);
    die("Error: Unable to fetch streams from API at $apiUrl. HTTP $httpCode - $error");
}

$streams = json_decode($apiResponse, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    die("Error: Invalid API response format from $apiUrl: " . json_last_error_msg());
}

if (!is_array($streams)) {
    http_response_code(500);
    die("Error: API response is not an array of streams.");
}

$categoryApiUrl = "$baseUrl/player_api.php?username=$user&password=$password&action=get_live_categories";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $categoryApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
    "Accept: */*",
    "Connection: keep-alive"
]);
$categoryResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

$categories = [];
if ($categoryResponse !== false && $httpCode < 400) {
    $categories = json_decode($categoryResponse, true) ?: [];
    if (!is_array($categories)) {
        $categories = [];
    }
}

$categoryMap = [];
foreach ($categories as $cat) {
    $categoryMap[$cat['category_id']] = $cat['category_name'] ?? 'Unknown';
}

$m3uContent = "#EXTM3U\n";
$streamCount = 0;

foreach ($streams as $stream) {
    $streamId = $stream['stream_id'] ?? '';
    $streamName = $stream['name'] ?? 'Unknown Stream';
    $categoryId = $stream['category_id'] ?? '';
    $streamIcon = !empty($stream['stream_icon']) ? $stream['stream_icon'] : 'https://i.ibb.co/xK5zSMkD/xtream.png';
    if (empty($streamId)) {
        continue;
    }

    // Filter streams by selected categories if provided
    $selectedCategories = [];
    if (isset($_GET['categories']) && !empty($_GET['categories'])) {
        $selectedCategories = explode(',', $_GET['categories']);
        $selectedCategories = array_map('trim', $selectedCategories);
    }
    if (!empty($selectedCategories) && !in_array($categoryId, $selectedCategories)) {
        continue;
    }

    $streamUrl = "$m3u8Url$streamId/master.m3u8";
    $categoryName = $categoryMap[$categoryId] ?? 'Unknown';

    $m3uContent .= "#EXTINF:-1 tvg-id=\"$streamId\" tvg-name=\"$streamName\" tvg-logo=\"$streamIcon\" group-title=\"$categoryName\",$streamName\n$streamUrl\n";
    $streamCount++;
}

if ($streamCount === 0) {
    http_response_code(500);
    die("Error: No valid streams found in API response.");
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: audio/x-mpegurl');
header('Content-Disposition: inline; filename="playlist.m3u"');
echo $m3uContent;
exit();
?>
