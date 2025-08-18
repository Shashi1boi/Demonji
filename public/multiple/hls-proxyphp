<?php
// HLS Proxy with Proper Format Handling
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Range");
header("Access-Control-Expose-Headers: Content-Length, Content-Range");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Max-Age: 86400");
    exit(0);
}

// Get the target URL
$url = isset($_GET['url']) ? urldecode($_GET['url']) : '';
if (empty($url)) {
    http_response_code(400);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'URL parameter is required']));
}

// Determine content type based on URL
if (preg_match('/\.m3u8($|\?)/i', $url)) {
    handleM3U8($url);
} elseif (preg_match('/\.ts($|\?)/i', $url)) {
    handleTS($url);
} else {
    http_response_code(400);
    die('Unsupported file type');
}

function handleM3U8($playlistUrl) {
    $ch = curl_init($playlistUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Accept: application/vnd.apple.mpegurl, */*'
        ]
    ]);
    
    $content = curl_exec($ch);
    if (curl_errno($ch)) {
        http_response_code(502);
        die('Failed to fetch playlist');
    }
    
    // Rewrite URLs to maintain proxy chain
    $content = preg_replace_callback('/((?:URI|URL)=")([^"]+)"/', function($m) {
        return $m[1] . getProxyUrl($m[2]) . '"';
    }, $content);
    
    $content = preg_replace('/\n([^#][^\n]*\.ts(?:\?[^\n]*)?)/', "\n" . getProxyUrl('$1'), $content);
    
    header('Content-Type: application/vnd.apple.mpegurl');
    header('Content-Length: ' . strlen($content));
    echo $content;
    curl_close($ch);
}

function handleTS($segmentUrl) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $segmentUrl,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_WRITEFUNCTION => function($ch, $data) {
            echo $data;
            return strlen($data);
        },
        CURLOPT_HEADERFUNCTION => function($ch, $header) {
            $forward = ['content-length', 'content-range', 'accept-ranges'];
            $parts = explode(':', $header, 2);
            if (count($parts) === 2 && in_array(strtolower(trim($parts[0])), $forward)) {
                header($header);
            }
            return strlen($header);
        }
    ]);
    
    header('Content-Type: video/MP2T');
    curl_exec($ch);
    
    if (curl_errno($ch)) {
        http_response_code(502);
        die('Failed to fetch segment');
    }
    curl_close($ch);
}

function getProxyUrl($targetUrl) {
    $base = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . 
            $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
    return $base . '?url=' . urlencode($targetUrl);
}
?>
