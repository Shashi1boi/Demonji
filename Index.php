<?php
// CONFIG - Optimized for high concurrency
$STREAM_BASE = "http://filex.tv:8080/Home329/Sohailhome/";
$FALLBACK_STREAMS = [
    "https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8"
];

// Lightweight logging
function log_message($message) {
    static $log_handle = null;
    
    if ($log_handle === null) {
        if (!is_dir('logs')) @mkdir('logs', 0777, true);
        $log_handle = fopen('logs/playlist_errors.log', 'a');
    }
    
    if ($log_handle) {
        fwrite($log_handle, "[" . date('Y-m-d H:i:s') . "] " . $message . "\n");
    }
}

// Check if 'id' parameter is provided
if (!isset($_GET['id'])) {
    log_message("Error: ID parameter missing");
    http_response_code(400);
    die("ID parameter required");
}

$id = $_GET['id'];

header('Content-Type: application/vnd.apple.mpegurl');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

// Simple memory caching
static $playlist_cache = [];
$cache_key = md5($id);
if (isset($playlist_cache[$cache_key])) {
    echo $playlist_cache[$cache_key];
    exit;
}

// 1. Try main stream
$source = $STREAM_BASE . $id;
$context = stream_context_create([
    'http' => [
        'timeout' => 3,
        'header' => "Connection: close\r\n"
    ]
]);

if ($response = @file_get_contents($source, false, $context)) {
    $playlist = createSingleStreamPlaylist($id);
    $playlist_cache[$cache_key] = $playlist;
    echo $playlist;
    exit;
}

// 2. Try fallback M3U8 stream
foreach ($FALLBACK_STREAMS as $fallback) {
    if ($playlist_content = @file_get_contents($fallback, false, $context)) {
        $playlist = createLivePlaylist($playlist_content, $id);
        $playlist_cache[$cache_key] = $playlist;
        echo $playlist;
        exit;
    }
}

// 3. Fallback to test playlist
$playlist = createTestPlaylist($id);
$playlist_cache[$cache_key] = $playlist;
echo $playlist;
exit;

// Keep your existing playlist generation functions
function createSingleStreamPlaylist($id) {
    return "#EXTM3U\n#EXT-X-VERSION:3\n#EXT-X-TARGETDURATION:10\n#EXT-X-MEDIA-SEQUENCE:" . time() . "\n#EXTINF:10.000,\nSegment.php?id=$id&main=1&t=" . time() . "\n#EXT-X-ENDLIST\n";
}

function createLivePlaylist($content, $id) {
    preg_match_all('/#EXTINF:([\d.]+),\s*.*?\n([^\s]+\.ts)/', $content, $matches, PREG_SET_ORDER);
    
    if (empty($matches)) {
        return createTestPlaylist($id);
    }
    
    $output = "#EXTM3U\n#EXT-X-VERSION:3\n#EXT-X-TARGETDURATION:" . ceil(max(array_map(function($m) { return (float)$m[1]; }, $matches))) . "\n";
    $output .= "#EXT-X-MEDIA-SEQUENCE:" . time() . "\n";
    
    foreach ($matches as $match) {
        $output .= "#EXTINF:" . sprintf("%.3f", (float)$match[1]) . ",\nSegment.php?id=$id&seg=" . urlencode(basename($match[2])) . "&t=" . time() . "\n";
    }
    $output .= "#EXT-X-ENDLIST\n";
    
    return $output;
}

function createTestPlaylist($id) {
    $output = "#EXTM3U\n#EXT-X-VERSION:3\n#EXT-X-TARGETDURATION:10\n#EXT-X-MEDIA-SEQUENCE:" . time() . "\n";
    for ($i = 0; $i < 3; $i++) {
        $output .= "#EXTINF:10.000,\nSegment.php?id=$id&test=$i&t=" . time() . "\n";
    }
    $output .= "#EXT-X-ENDLIST\n";
    return $output;
}
?>