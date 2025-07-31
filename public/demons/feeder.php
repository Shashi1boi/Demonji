<?php
require_once 'config.php'; // Include the configuration file

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

if (!isset($_GET['url'])) {
    http_response_code(400);
    echo json_encode(['error' => 'URL parameter is missing']);
    exit;
}

$targetUrl = $_GET['url'];
$host = $stalkerCredentials['host'];
$mac = $stalkerCredentials['mac'];
$token = generate_token();

$parsedUrl = parse_url($targetUrl);
if (!$parsedUrl || !isset($parsedUrl['scheme'], $parsedUrl['host'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid URL']);
    exit;
}

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
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_ENCODING, 'gzip');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headersReceived = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);
$error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => "Failed to fetch data: $error"]);
    exit;
}

if ($httpCode >= 400) {
    // Retry with a new token
    $token = generate_token(true);
    $headers[2] = "Authorization: Bearer {$token}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headersReceived = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode >= 400) {
        http_response_code($httpCode);
        echo json_encode(['error' => "Server returned error: HTTP $httpCode", 'headers' => $headersReceived, 'response' => $body, 'details' => $error]);
        exit;
    }
}

echo $body;
?>
