<?php
// CONFIG
$STREAM_BASE = "http://filex.tv:8080/live/Home329/Sohailhome/";
$FALLBACK_STREAMS = [
    "https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8" // Removed invalid backup1.com
];
$LOG_FILE = 'logs/playlist_errors.log';
$TIMEOUT = 3; // Reduced timeout

// Ensure log directory exists
if (!is_dir('logs')) {
    mkdir('logs', 0777, true);
}

// Check if 'id' parameter is provided
if (!isset($_GET['id'])) {
    file_put_contents($LOG_FILE, "[" . date('Y-m-d H:i:s') . "] Error: ID parameter missing\n", FILE_APPEND);
    http_response_code(400);
    die("ID parameter required");
}
$id = $_GET['id'];

header('Content-Type: application/vnd.apple.mpegurl');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

// 1. Try main stream
$source = $STREAM_BASE . $id . ".m3u8";
$start_time = microtime(true);
if ($playlist = tryFetchStream($source)) {
    $duration = microtime(true) - $start_time;
    file_put_contents($LOG_FILE, "[" . date('Y-m-d H:i:s') . "] Main stream fetched in {$duration}s: $source\n", FILE_APPEND);
    echo createLivePlaylist($playlist, $id);
    exit;
}
$duration = microtime(true) - $start_time;
file_put_contents($LOG_FILE, "[" . date('Y-m-d H:i:s') . "] Failed main stream in {$duration}s: $source\n", FILE_APPEND);

// 2. Try fallback stream
foreach ($FALLBACK_STREAMS as $index => $fallback) {
    $start_time = microtime(true);
    if ($playlist = tryFetchStream($fallback)) {
        $duration = microtime(true) - $start_time;
        file_put_contents($LOG_FILE, "[" . date('Y-m-d H:i:s') . "] Fallback $index fetched in {$duration}s: $fallback\n", FILE_APPEND);
        echo createLivePlaylist($playlist, $id);
        exit;
    }
    $duration = microtime(true) - $start_time;
    file_put_contents($LOG_FILE, "[" . date('Y-m-d H:i:s') . "] Failed fallback $index in {$duration}s: $fallback\n", FILE_APPEND);
}

// 3. Fallback to test playlist
file_put_contents($LOG_FILE, "[" . date('Y-m-d H:i:s') . "] Using test playlist for ID: $id\n", FILE_APPEND);
echo createTestPlaylist($id);

function tryFetchStream($url) {
    global $LOG_FILE;
    $context = stream_context_create([
        'http' => [
            'timeout' => 3,
            'header' => "User-Agent: Mozilla/5.0 (compatible; StreamFetcher/1.0)\r\n"
        ],
        'ssl' => ['verify_peer' => false]
    ]);
    $data = @file_get_contents($url, false, $context);
    if ($data === false) {
        $error = error_get_last();
        $status = isset($http_response_header) ? $http_response_header[0] : 'No response';
        file_put_contents($LOG_FILE, "[" . date('Y-m-d H:i:s') . "] Failed to fetch: $url, Status: $status, Error: " . ($error['message'] ?? 'Unknown') . "\n", FILE_APPEND);
    }
    return $data;
}

function createLivePlaylist($content, $id) {
    preg_match_all('/#EXTINF:([\d.]+),\s*.*?\n([^\s]+\.ts)/', $content, $matches, PREG_SET_ORDER);
    
    if (empty($matches)) {
        global $LOG_FILE;
        file_put_contents($LOG_FILE, "[" . date('Y-m-d H:i:s') . "] Empty playlist for ID: $id, falling back to test playlist\n", FILE_APPEND);
        return createTestPlaylist($id);
    }
    
    $durations = array_map(function($m) { return (float)$m[1]; }, $matches);
    $max_duration = max($durations);
    $target_duration = ceil($max_duration);
    
    $output = "#EXTM3U\n";
    $output .= "#EXT-X-VERSION:3\n";
    $output .= "#EXT-X-TARGETDURATION:$target_duration\n";
    $output .= "#EXT-X-MEDIA-SEQUENCE:" . time() . "\n";
    $output .= "#EXT-X-PLAYLIST-TYPE:VOD\n";
    
    foreach ($matches as $match) {
        $duration = (float)$match[1];
        $segment = $match[2];
        $output .= "#EXTINF:" . sprintf("%.3f", $duration) . ",\n";
        $output .= "segment.php?id=$id&seg=" . urlencode(basename($segment)) . "&t=" . time() . "\n";
    }
    $output .= "#EXT-X-ENDLIST\n";
    
    return $output;
}

function createTestPlaylist($id) {
    $output = "#EXTM3U\n";
    $output .= "#EXT-X-VERSION:3\n";
    $output .= "#EXT-X-TARGETDURATION:10\n";
    $output .= "#EXT-X-MEDIA-SEQUENCE:" . time() . "\n";
    $output .= "#EXT-X-PLAYLIST-TYPE:VOD\n";
    
    for ($i = 0; $i < 3; $i++) {
        $output .= "#EXTINF:10.000,\n";
        $output .= "segment.php?id=$id&test=$i&t=" . time() . "\n";
    }
    $output .= "#EXT-X-ENDLIST\n";
    
    return $output;
}
?>
