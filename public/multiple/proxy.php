<?php
// HLS Streaming Proxy with Proper TS Segment Handling
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Range");
header("Access-Control-Expose-Headers: Content-Length, Content-Range");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Max-Age: 1728000");
    header("Content-Length: 0");
    exit(0);
}

// Get the target URL
$query = $_SERVER['QUERY_STRING'];
$url = '';
if (preg_match('/url=(.+)/', $query, $matches)) {
    $url = urldecode($matches[1]);
}

if (empty($url)) {
    http_response_code(400);
    die('URL parameter is required');
}

// Special handling for .m3u8 playlists
if (preg_match('/\.m3u8($|\?)/i', $url)) {
    proxyM3U8Playlist($url);
    exit;
}

// Handle .ts segments
proxyTSsegment($url);

function proxyM3U8Playlist($playlistUrl) {
    $ch = curl_init($playlistUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => false,
        CURLOPT_HTTPHEADER => [
            'Connection: keep-alive',
            'Accept: */*',
        ],
    ]);
    
    $content = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch) || $status !== 200) {
        http_response_code($status ?: 502);
        die('Failed to fetch playlist');
    }
    
    curl_close($ch);
    
    // Rewrite URLs in the playlist to point back through our proxy
    $baseUrl = getBaseUrl($playlistUrl);
    $proxyBase = getProxyBaseUrl();
    
    $content = preg_replace_callback('/((?:URI|URL)=")([^"]+)"/', function($m) use ($proxyBase) {
        return $m[1] . $proxyBase . urlencode($m[2]) . '"';
    }, $content);
    
    $content = preg_replace_callback('/\n([^#][^\n]*\.ts(?:\?[^\n]*)?)/', function($m) use ($proxyBase, $baseUrl) {
        $segment = $m[1];
        if (!preg_match('/^https?:\/\//i', $segment)) {
            $segment = rtrim($baseUrl, '/') . '/' . ltrim($segment, '/');
        }
        return "\n" . $proxyBase . urlencode($segment);
    }, $content);
    
    header('Content-Type: application/vnd.apple.mpegurl');
    header('Content-Length: ' . strlen($content));
    echo $content;
}

function proxyTSsegment($segmentUrl) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $segmentUrl,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => false,
        CURLOPT_HTTPHEADER => [
            'Connection: keep-alive',
            'Accept: */*',
        ],
        CURLOPT_BUFFERSIZE => 131072,
        CURLOPT_RANGE => $_SERVER['HTTP_RANGE'] ?? '',
        CURLOPT_WRITEFUNCTION => function($ch, $data) {
            echo $data;
            return strlen($data);
        },
    ]);
    
    // Forward important headers
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) {
        $forward = ['content-type', 'content-length', 'content-range', 'accept-ranges'];
        $parts = explode(':', $header, 2);
        if (count($parts) === 2 && in_array(strtolower(trim($parts[0])), $forward)) {
            header($header);
        }
        return strlen($header);
    });
    
    curl_exec($ch);
    
    if (curl_errno($ch)) {
        http_response_code(502);
        die('Proxy error: ' . curl_error($ch));
    }
    
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    if (empty($contentType)) {
        header('Content-Type: video/MP2T');
    }
    
    curl_close($ch);
}

function getBaseUrl($url) {
    $parts = parse_url($url);
    $scheme = $parts['scheme'] ?? 'https';
    $host = $parts['host'] ?? '';
    $path = dirname($parts['path'] ?? '/');
    return "$scheme://$host$path";
}

function getProxyBaseUrl() {
    $scheme = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    return "$scheme$host$script?url=";
}
?>
