<?php
// Enable error reporting for debugging (remove on production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers – allow all origins
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: *');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check if 'url' parameter is provided
if (!isset($_GET['url']) || empty($_GET['url'])) {
    http_response_code(400);
    die('Missing url parameter');
}

$targetUrl = $_GET['url'];

// Validate URL (basic)
if (!filter_var($targetUrl, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    die('Invalid URL');
}

// Use cURL if available, otherwise fallback to file_get_contents
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; CORS-Proxy/1.0)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        http_response_code(500);
        die("Proxy error: " . $error);
    }
} else {
    // Fallback using file_get_contents (may need allow_url_fopen)
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (compatible; CORS-Proxy/1.0)\r\n"
        ]
    ]);
    $response = file_get_contents($targetUrl, false, $context);
    if ($response === false) {
        http_response_code(500);
        die('Proxy error: file_get_contents failed');
    }
    $httpCode = 200;
    $contentType = 'application/octet-stream';
}

// Forward the response with the same status code and content type
if ($httpCode !== 200) {
    http_response_code($httpCode);
}
if ($contentType) {
    header('Content-Type: ' . $contentType);
}
echo $response;
