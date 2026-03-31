<?php
/**
 * Advanced Universal CORS Proxy for IPTV & HLS Streams
 * Bypasses CORS and User-Agent blocking.
 */

// 1. Setup CORS Headers for the Browser
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Range");
header("Access-Control-Expose-Headers: Content-Length, Content-Range");

// Handle Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// 2. Validate URL
$url = isset($_GET['url']) ? $_GET['url'] : null;
if (!$url) {
    http_response_code(400);
    die("Error: 'url' parameter is missing.");
}

// Ensure URL is decoded
$url = urldecode($url);

// 3. Setup Request Headers (Spoofing)
$options = [
    "http" => [
        "method" => "GET",
        "follow_location" => 1, // Follow redirects automatically
        "header" => [
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            "Accept: */*",
            "Connection: keep-alive",
            "Referer: http://172.110.220.239/" // Fake the referer to match the server IP
        ],
        "timeout" => 15,
        "ignore_errors" => true
    ]
];

// If the browser sends a Range request (common in video), pass it to the source
if (isset($_SERVER['HTTP_RANGE'])) {
    $options['http']['header'][] = "Range: " . $_SERVER['HTTP_RANGE'];
}

$context = stream_context_create($options);

// 4. Open the Stream and Proxy Headers
$fp = fopen($url, 'rb', false, $context);

if (!$fp) {
    http_response_code(502);
    die("Proxy Error: Failed to connect to remote server.");
}

// Forward headers from the IPTV server back to the browser
$meta = stream_get_meta_data($fp);
if (isset($meta['wrapper_data'])) {
    foreach ($meta['wrapper_data'] as $header) {
        // Skip restricted headers that PHP/Server handles
        if (stripos($header, 'Transfer-Encoding') === false && 
            stripos($header, 'Access-Control-Allow-Origin') === false) {
            header($header);
        }
    }
}

// 5. Stream the Body (Chunked Transfer)
// Use a 8KB buffer to keep memory low and speed high
while (!feof($fp)) {
    echo fread($fp, 8192);
    flush(); // Send chunk to browser immediately
}

fclose($fp);
?>
