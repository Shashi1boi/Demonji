<?php
/**
 * Simple CORS Proxy – forwards any GET request
 */

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check for url parameter
if (!isset($_GET['url'])) {
    http_response_code(400);
    die('Missing url parameter');
}

$targetUrl = $_GET['url'];
if (!filter_var($targetUrl, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    die('Invalid URL');
}

// Fetch the target URL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; CORS-Proxy/1.0)');
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($response === false) {
    http_response_code(500);
    die('Proxy error: Failed to fetch URL');
}

// Forward the response
if ($httpCode !== 200) {
    http_response_code($httpCode);
}
if ($contentType) {
    header('Content-Type: ' . $contentType);
}
echo $response;
