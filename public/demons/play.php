<?php
require_once 'config.php'; // Include the configuration file

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
set_time_limit(0); // Prevent script timeout

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(http_response_code(204));
}

if (empty($_GET['id'])) {
    exit("Error: Missing or invalid 'id' parameter.");
}

// Extract stream ID by removing .m3u8 extension if present
$id = preg_replace('/\.m3u8$/', '', $_GET['id']);
$id = urlencode($id);

// Use credentials from config
$host = $stalkerCredentials['host'];
$mac = $stalkerCredentials['mac'];
$token = generate_token(); // Generate or reuse token

$streamUrlEndpoint = "http://{$host}/stalker_portal/server/load.php?type=itv&action=create_link&cmd=ffrt%20http://localhost/ch/{$id}&JsHttpRequest=1-xml";

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
curl_setopt($ch, CURLOPT_URL, $streamUrlEndpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
curl_setopt($ch, CURLOPT_TIMEOUT, 0); // No timeout for streaming
curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
curl_setopt($ch, CURLOPT_HEADER, true); // Capture headers for debugging

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headersReceived = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);
$error = curl_error($ch);
$final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

if ($response === false) {
    exit("Error: cURL request failed. $error");
}

if ($httpCode != 200) {
    // Retry with a new token
    $token = generate_token(true); // Force regenerate token
    $headers[2] = "Authorization: Bearer {$token}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $streamUrlEndpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    curl_setopt($ch, CURLOPT_HEADER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headersReceived = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    $error = curl_error($ch);
    $final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    if ($httpCode != 200) {
        exit("Error: Failed to fetch stream URL. HTTP $httpCode\nHeaders: $headersReceived\nBody: $body");
    }
}

$data = json_decode($body, true);
$streamUrl = $data['js']['cmd'] ?? '';
if (empty($streamUrl)) {
    exit("Error: Failed to retrieve stream URL for channel ID: {$id}. Response: $body");
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $streamUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG200 stbapp ver: 2 rev: 250 Safari/533.3",
    "Accept: */*",
    "Connection: keep-alive",
    "Accept-Encoding: gzip"
]);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
curl_setopt($ch, CURLOPT_TIMEOUT, 0);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headersReceived = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);
$error = curl_error($ch);
$final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

if ($response === false) {
    exit("Error: cURL request failed for stream. $error");
}

if ($httpCode != 200) {
    exit("Error: Failed to fetch the m3u8 file. HTTP $httpCode\nHeaders: $headersReceived\nBody: $body");
}

$baseUrl = parse_url($final_url, PHP_URL_SCHEME) . '://' . parse_url($final_url, PHP_URL_HOST);
if ($port = parse_url($final_url, PHP_URL_PORT)) {
    $baseUrl .= ":$port";
}

$processedResponse = implode("\n", array_map(function ($line) use ($baseUrl) {
    if (preg_match('#\.ts($|\?)#', $line) && !filter_var($line, FILTER_VALIDATE_URL)) {
        return $baseUrl . '/' . ltrim($line, '/');
    }
    return $line;
}, explode("\n", $body)));

header('Content-Type: application/vnd.apple.mpegurl');
header('Content-Disposition: attachment; filename="' . $id . '.m3u8"');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
echo trim($processedResponse);
?>
