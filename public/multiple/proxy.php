<?php
// HLS Streaming Proxy with CORS Support
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Range");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Max-Age: 1728000");
    header("Content-Length: 0");
    exit(0);
}

$targetUrl = $_GET['url'] ?? '';
if (empty($targetUrl)) {
    http_response_code(400);
    die('URL parameter is required');
}

// Special handling for .m3u8 playlists
if (preg_match('/\.m3u8($|\?)/i', $targetUrl)) {
    handleM3U8Playlist($targetUrl);
    exit;
}

// Handle .ts segments and other files
handleFileDownload($targetUrl);

function handleM3U8Playlist($playlistUrl) {
    $content = file_get_contents($playlistUrl);
    if ($content === false) {
        http_response_code(502);
        die('Failed to fetch playlist');
    }

    // Rewrite URLs in the playlist to point back through our proxy
    $content = preg_replace_callback('/((?:URI|URL)=")([^"]+)"/', function($matches) {
        return $matches[1] . getProxyUrl($matches[2]) . '"';
    }, $content);

    $content = preg_replace('/\n([^#][^\n]*\.ts)/', "\n" . getProxyUrl('$1'), $content);

    header('Content-Type: application/vnd.apple.mpegurl');
    header('Content-Length: ' . strlen($content));
    echo $content;
}

function handleFileDownload($fileUrl) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $fileUrl,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADERFUNCTION => function($curl, $header) {
            $forwardHeaders = [
                'content-type',
                'content-length',
                'accept-ranges',
                'content-range',
                'content-disposition'
            ];
            
            $headerParts = explode(':', $header, 2);
            if (count($headerParts) === 2) {
                $headerName = strtolower(trim($headerParts[0]));
                if (in_array($headerName, $forwardHeaders)) {
                    header($header);
                }
            }
            return strlen($header);
        },
        CURLOPT_WRITEFUNCTION => function($curl, $data) {
            echo $data;
            return strlen($data);
        },
        CURLOPT_BUFFERSIZE => 131072, // 128KB chunks for .ts files
        CURLOPT_RANGE => $_SERVER['HTTP_RANGE'] ?? '', // Support byte-range requests
        CURLOPT_NOPROGRESS => false,
        CURLOPT_PROGRESSFUNCTION => function($resource, $dl_size, $dl, $ul_size, $ul) {
            return connection_aborted() ? 1 : 0;
        }
    ]);

    // Execute request
    curl_exec($ch);
    
    if (curl_errno($ch)) {
        http_response_code(502);
        die('Proxy error: ' . curl_error($ch));
    }
    
    curl_close($ch);
}

function getProxyUrl($relativeUrl) {
    $base = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
    return $base . '?url=' . urlencode($relativeUrl);
}
?>
