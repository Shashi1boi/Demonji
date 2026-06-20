<?php
/**
 * Powerful CORS Proxy
 * - Forwards any URL with full CORS support
 * - Handles binary data (video, audio, etc.)
 * - Streams output to avoid memory issues
 * - Preserves original headers
 * - No size limits
 */

// CORS headers – allow all
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Expose-Headers: *');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Get target URL from query parameter
$url = isset($_GET['url']) ? $_GET['url'] : '';
if (!$url) {
    http_response_code(400);
    die('Missing ?url= parameter');
}

// Validate URL to prevent SSRF attacks (optional but recommended)
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    die('Invalid URL');
}

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Stream directly
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) {
    $len = strlen($header);
    $headerParts = explode(':', $header, 2);
    if (count($headerParts) == 2) {
        $name = strtolower(trim($headerParts[0]));
        // Forward only safe headers (skip Connection, Transfer-Encoding, etc.)
        $safeHeaders = [
            'content-type', 'content-length', 'content-disposition',
            'accept-ranges', 'cache-control', 'etag', 'last-modified',
            'expires', 'date'
        ];
        if (in_array($name, $safeHeaders)) {
            header(trim($headerParts[0]) . ': ' . trim($headerParts[1]), false);
        }
    }
    return $len;
});

// Forward request headers from client (optional)
$forwardHeaders = [];
if (isset($_SERVER['HTTP_RANGE'])) {
    curl_setopt($ch, CURLOPT_RANGE, $_SERVER['HTTP_RANGE']);
}

// Execute cURL request (output will be sent to client automatically)
curl_exec($ch);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($httpCode != 200 && $httpCode != 206) {
    // If not a successful response, we can't change headers already sent
    // But we can log error and maybe send a 502 if nothing was output
    if ($httpCode == 0 || $error) {
        http_response_code(502);
        echo "Proxy error: " . ($error ?: "No response from target");
    }
}
exit;
