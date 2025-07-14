<?php
// CONFIG - Optimized for high concurrency
$SEGMENT_CACHE_TIME = 20;
$CACHE_DIR = 'cache/';
$LOG_FILE = 'logs/segment_errors.log';
$TIMEOUT = 2;
$STREAM_BASE = "http://filex.tv:8080/Home329/Sohailhome/";

// Simple memory caching array
$memory_cache = [];

// Lightweight logging
function log_message($message) {
    global $LOG_FILE;
    static $log_handle = null;
    
    if ($log_handle === null) {
        if (!is_dir('logs')) @mkdir('logs', 0777, true);
        $log_handle = fopen($LOG_FILE, 'a');
    }
    
    if ($log_handle) {
        fwrite($log_handle, "[" . date('Y-m-d H:i:s') . "] " . $message . "\n");
    }
}

// Ensure cache directory exists
if (!is_dir($CACHE_DIR)) {
    @mkdir($CACHE_DIR, 0777, true);
}

// Cleanup old segments (less frequent)
if (rand(1, 100) === 1) { // Only clean up 1% of requests
    $now = time();
    foreach (glob($CACHE_DIR . '*.ts') as $file) {
        if ($now - @filemtime($file) > $SEGMENT_CACHE_TIME) {
            @unlink($file);
        }
    }
}

// Headers
header('Content-Type: video/mp2t');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');

// Test mode
if (isset($_GET['testmode']) && $_GET['testmode'] === '1') {
    echo "Segment.php is running";
    exit;
}

// 1. Check memory cache first
$cache_key = md5($_SERVER['REQUEST_URI']);
if (isset($memory_cache[$cache_key])) {
    echo $memory_cache[$cache_key];
    exit;
}

// 2. Try main stream
if (isset($_GET['main']) && isset($_GET['id']) && !empty($_GET['id'])) {
    $url = $STREAM_BASE . $_GET['id'];
    $cache_file = $CACHE_DIR . md5($url) . '.ts';
    
    if (file_exists($cache_file)) {
        $data = @file_get_contents($cache_file);
        if (!empty($data)) {
            $memory_cache[$cache_key] = $data; // Cache in memory
            echo $data;
            exit;
        }
        @unlink($cache_file);
    }
    
    $context = stream_context_create([
        'http' => [
            'timeout' => $TIMEOUT,
            'header' => "Connection: close\r\n"
        ]
    ]);
    
    $data = @file_get_contents($url, false, $context);
    if ($data !== false && !empty($data)) {
        @file_put_contents($cache_file, $data);
        $memory_cache[$cache_key] = $data;
        echo $data;
        exit;
    }
}

// 3. Try test segments (simplified)
$test_url = 'https://test-streams.mux.dev/x36xhzz/url_' . (rand(0, 2)) . '/193039199_mp4_h264_aac_hd_7.ts';
$cache_file = $CACHE_DIR . md5($test_url) . '.ts';

if (file_exists($cache_file)) {
    $data = @file_get_contents($cache_file);
    if (!empty($data)) {
        $memory_cache[$cache_key] = $data;
        echo $data;
        exit;
    }
    @unlink($cache_file);
}

$context = stream_context_create([
    'http' => [
        'timeout' => $TIMEOUT,
        'header' => "Connection: close\r\n"
    ]
]);

$data = @file_get_contents($test_url, false, $context);
if ($data !== false && !empty($data)) {
    @file_put_contents($cache_file, $data);
    $memory_cache[$cache_key] = $data;
    echo $data;
    exit;
}

// 4. Serve fallback segment (keep your existing fallback)
echo $FALLBACK_SEGMENT;
exit;
?>