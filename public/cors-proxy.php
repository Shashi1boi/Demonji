<?php
/**
 * Universal CORS Proxy
 * - Accepts target URL via query parameter (?url=...) OR path segment (/proxy.php/https://...)
 * - Streams binary data (HLS, video, audio) with full CORS
 * - Forwards range requests, preserves safe headers
 * - No memory buffering, no size limits
 */

// Always send CORS headers first
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Expose-Headers: *');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Extract target URL ---

$url = '';

// 1. Try query parameter first
if (isset($_GET['url']) && !empty($_GET['url'])) {
    $url = $_GET['url'];
}
// 2. Fallback: extract from PATH_INFO (e.g., /cors-proxy.php/https://example.com/stream)
elseif (isset($_SERVER['PATH_INFO']) && strlen($_SERVER['PATH_INFO']) > 1) {
    // Remove leading slash, then decode (it may be URL-encoded)
    $path = ltrim($_SERVER['PATH_INFO'], '/');
    // If the path contains a question mark, it might have been double-encoded; we take everything after the first slash
    $url = $path; // assume the whole path is the URL (including query strings)
    // But if the original URL had ? and =, the path might have them raw; we keep as is.
}
// 3. Also check REQUEST_URI if PATH_INFO is not set (some servers)
elseif (isset($_SERVER['REQUEST_URI'])) {
    // Try to extract from REQUEST_URI after script name
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $requestUri = $_SERVER['REQUEST_URI'];
    // Remove query string
    $requestUri = strtok($requestUri, '?');
    if (strpos($requestUri, $scriptName) === 0) {
        $potential = substr($requestUri, strlen($scriptName));
        if (!empty($potential) && $potential[0] === '/') {
            $url = ltrim($potential, '/');
        }
    }
}

if (empty($url)) {
    http_response_code(400);
    die('Missing target URL. Use ?url=... or /proxy.php/https://...');
}

// Validate URL scheme (allow http/https only)
$parsed = parse_url($url);
if (!$parsed || !in_array(strtolower($parsed['scheme'] ?? ''), ['http', 'https'])) {
    http_response_code(400);
    die('Invalid or unsupported URL scheme. Only HTTP/HTTPS allowed.');
}

// --- Initialize cURL ---
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);  // stream directly
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) {
    $len = strlen($header);
    $headerParts = explode(':', $header, 2);
    if (count($headerParts) === 2) {
        $name = strtolower(trim($headerParts[0]));
        // Forward only safe and cache-friendly headers
        $safeHeaders = [
            'content-type', 'content-length', 'content-disposition',
            'accept-ranges', 'cache-control', 'etag', 'last-modified',
            'expires', 'date', 'age', 'vary'
        ];
        if (in_array($name, $safeHeaders)) {
            header(trim($headerParts[0]) . ': ' . trim($headerParts[1]), false);
        }
    }
    return $len;
});

// Forward client Range header (for seeking)
if (isset($_SERVER['HTTP_RANGE'])) {
    curl_setopt($ch, CURLOPT_RANGE, $_SERVER['HTTP_RANGE']);
}

// Execute and stream output
curl_exec($ch);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// If no content was sent and there's an error, respond with a 502
if ($httpCode == 0 || $error) {
    // Only send error if headers haven't been sent yet (i.e., nothing was output)
    if (!headers_sent()) {
        http_response_code(502);
        echo "Proxy error: " . ($error ?: "No response from target");
    }
}
exit;
