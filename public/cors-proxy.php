<?php
/**
 * Universal CORS Proxy – Fixed URL extraction
 */

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Expose-Headers: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Extract target URL ---
$url = '';

// 1. Query parameter (preferred, most reliable)
if (isset($_GET['url']) && $_GET['url'] !== '') {
    $url = $_GET['url'];
} 
else {
    // 2. Path-based: reconstruct from REQUEST_URI
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $requestUri = $_SERVER['REQUEST_URI'];
    
    // Remove query string from request URI to avoid interference? Actually we want to keep it.
    // But we need to split at the script name position.
    $pos = strpos($requestUri, $scriptName);
    if ($pos !== false) {
        // Get everything after the script name
        $afterScript = substr($requestUri, $pos + strlen($scriptName));
        // Remove leading slash if present
        if (strlen($afterScript) > 0 && $afterScript[0] === '/') {
            $afterScript = substr($afterScript, 1);
        }
        // Now $afterScript contains the target URL including query string (if any)
        $url = $afterScript;
    }
}

if (empty($url)) {
    http_response_code(400);
    die('Missing target URL. Use ?url=... or /proxy.php/https://...');
}

// --- Sanitize and validate the URL ---

// Remove any leading/trailing whitespace and control characters
$url = trim($url);
$url = preg_replace('/[\x00-\x1F\x7F]/', '', $url); // strip control chars

// If the URL appears to be double-encoded (e.g., contains %25), decode it once
if (strpos($url, '%25') !== false) {
    $url = rawurldecode($url);
}

// Try to parse; if it fails, attempt to re-encode spaces and invalid characters
$parsed = parse_url($url);
if ($parsed === false) {
    // Try to encode spaces and other problematic characters
    $url = str_replace(' ', '%20', $url);
    // Also encode other potentially unsafe characters (but keep :// and ? & =)
    // Use a simple approach: encode only characters that are not in the allowed set
    $url = preg_replace_callback('/[^a-zA-Z0-9\-._~!$&\'()*+,;=:@\/?%]/', function($m) {
        return rawurlencode($m[0]);
    }, $url);
    $parsed = parse_url($url);
    if ($parsed === false) {
        http_response_code(400);
        die('URL rejected: Malformed input after sanitization. Please encode your URL.');
    }
}

// Validate scheme
if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'])) {
    http_response_code(400);
    die('Invalid URL scheme. Only HTTP/HTTPS allowed.');
}

// Rebuild the URL to ensure it's properly formed (optional)
// But we keep the original $url as the target.

// --- cURL execution (same as before) ---
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) {
    $len = strlen($header);
    $headerParts = explode(':', $header, 2);
    if (count($headerParts) === 2) {
        $name = strtolower(trim($headerParts[0]));
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

if (isset($_SERVER['HTTP_RANGE'])) {
    curl_setopt($ch, CURLOPT_RANGE, $_SERVER['HTTP_RANGE']);
}

curl_exec($ch);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($httpCode == 0 || $error) {
    if (!headers_sent()) {
        http_response_code(502);
        echo "Proxy error: " . ($error ?: "No response from target");
    }
}
exit;
