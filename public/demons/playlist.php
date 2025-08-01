<?php
require_once 'config.php'; // Include the configuration file

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(0); // Prevent script timeout

$host = $stalkerCredentials['host'];
$mac = $stalkerCredentials['mac'];
$token = generate_token();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$server = $_SERVER['HTTP_HOST'];
$requestUri = $_SERVER['REQUEST_URI'];
$m3u8Url = $protocol . $server . dirname($requestUri) . "/play.php?id=";

function generateId($cmd) {
    $cmdParts = explode("/", $cmd);
    if (isset($cmdParts[2]) && $cmdParts[2] === "localhost") {
        $cmd = str_ireplace('ffrt http://localhost/ch/', '', $cmd);
    } else if (isset($cmdParts[2]) && $cmdParts[2] === "") {
        $cmd = str_ireplace('ffrt http:///ch/', '', $cmd);
    }
    return $cmd;
}

function getImageUrl($channel, $host) {
    $imageExtensions = [".png", ".jpg"];
    $emptyReplacements = ['', ""];
    $logo = str_replace($imageExtensions, $emptyReplacements, $channel['logo'] ?? '');
    if (is_numeric($logo)) {
        return 'http://' . $host . '/stalker_portal/misc/logos/320/' . $channel['logo'];
    }
    return "https://i.ibb.co/39Nz2wg/stalker.png";
}

// Get selected categories from query parameter
$selectedCategories = [];
if (isset($_GET['categories']) && !empty($_GET['categories'])) {
    $selectedCategories = explode(',', $_GET['categories']);
    $selectedCategories = array_map('trim', $selectedCategories);
}

$apiUrl = "http://{$host}/stalker_portal/server/load.php?type=itv&action=get_all_channels&JsHttpRequest=1-xml";
$headers = [
    "Cookie: mac={$mac}; stb_lang=en; timezone=GMT",
    "User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG200 stbapp ver: 2 rev: 250 Safari/533.3",
    "Authorization: Bearer {$token}",
    "Accept: */*",
    "Referer: http://{$host}/stalker_portal/c/",
    "Connection: Keep-Alive",
    "Accept-Encoding: gzip"
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HEADER, true);

$apiResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headersReceived = substr($apiResponse, 0, $headerSize);
$body = substr($apiResponse, $headerSize);
$error = curl_error($ch);
curl_close($ch);

if ($apiResponse === false || $httpCode >= 400) {
    // Retry with a new token
    $token = generate_token(true);
    $headers[2] = "Authorization: Bearer {$token}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $apiResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headersReceived = substr($apiResponse, 0, $headerSize);
    $body = substr($apiResponse, $headerSize);
    $error = curl_error($ch);
    curl_close($ch);
}

if ($apiResponse === false || $httpCode >= 400) {
    http_response_code(500);
    die("Error: Unable to fetch channels from API. HTTP $httpCode\nHeaders: $headersReceived\nBody: $body\nError: $error");
}

$channels = json_decode($body, true);
if (!isset($channels['js']['data']) || !is_array($channels['js']['data'])) {
    http_response_code(500);
    die("Error: Invalid channel data received from API. Response: $body");
}

$channels = $channels['js']['data'];

$categoryApiUrl = "http://{$host}/stalker_portal/server/load.php?type=itv&action=get_genres&JsHttpRequest=1-xml";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $categoryApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HEADER, true);

$categoryResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$categoryHeaders = substr($categoryResponse, 0, $headerSize);
$categoryBody = substr($categoryResponse, $headerSize);
$error = curl_error($ch);
curl_close($ch);

$categories = [];
if ($categoryResponse !== false && $httpCode < 400) {
    $categoryData = json_decode($categoryBody, true);
    if (isset($categoryData['js']) && is_array($categoryData['js'])) {
        $categories = $categoryData['js'];
    }
}

$categoryMap = [];
foreach ($categories as $cat) {
    if ($cat['id'] !== '*') {
        $categoryMap[$cat['id']] = $cat['title'] ?? 'Unknown';
    }
}

$m3uContent = "#EXTM3U\n";
$streamCount = 0;

foreach ($channels as $channel) {
    $channelId = generateId($channel['cmd'] ?? '');
    $channelName = $channel['name'] ?? 'Unknown Channel';
    $categoryId = $channel['tv_genre_id'] ?? '';
    $streamIcon = getImageUrl($channel, $host);
    if (empty($channelId)) {
        continue;
    }

    // Filter streams by selected categories if provided
    if (!empty($selectedCategories) && !in_array($categoryId, $selectedCategories)) {
        continue;
    }

    $streamUrl = "$m3u8Url{$channelId}.m3u8"; // Append .m3u8 to the stream ID
    $categoryName = $categoryMap[$categoryId] ?? 'Unknown';

    $m3uContent .= "#EXTINF:-1 tvg-id=\"$channelId\" tvg-name=\"$channelName\" tvg-logo=\"$streamIcon\" group-title=\"$categoryName\",$channelName\n$streamUrl\n";
    $streamCount++;
}

if ($streamCount === 0) {
    http_response_code(500);
    die("Error: No valid channels found in API response. Response: $body");
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: audio/x-mpegurl');
header('Content-Disposition: inline; filename="playlist.m3u"');
echo $m3uContent;
exit();
?>
