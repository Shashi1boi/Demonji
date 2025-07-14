<?php
// CONFIG
$SEGMENT_CACHE_TIME = 20;
$CACHE_DIR = 'cache/';
$LOG_FILE = 'logs/segment_errors.log';
$TIMEOUT = 2; // Reduced timeout

$TEST_SEGMENTS = [
    'https://test-streams.mux.dev/x36xhzz/url_0/193039199_mp4_h264_aac_hd_7.ts',
    'https://test-streams.mux.dev/x36xhzz/url_1/193039199_mp4_h264_aac_hd_7.ts',
    'https://test-streams.mux.dev/x36xhzz/url_2/193039199_mp4_h264_aac_hd_7.ts'
];

// Fallback segment data (base64-encoded small .ts file to bypass external fetch)
$FALLBACK_SEGMENT = base64_decode(
    'dTsAAAABAAAAAAABAAQAAABkAAAAAQAAAAEAAAABAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA' .
    'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA' .
    'AAAAAAACAAAABAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA' .
    'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA' .
    'AAAAAAACAAAABAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA' .
    'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA' .
    'AAAAAAACAAAABAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA' .
    'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA' .
    'AAAAAAACAAAABAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA' .
    'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA' .
    'AAAAAA==' // Minimal valid .ts segment for testing
);

// Ensure cache and log directories exist
if (!is_dir($CACHE_DIR)) {
    @mkdir($CACHE_DIR, 0777, true);
}
if (!is_dir('logs')) {
    @mkdir('logs', 0777, true);
}

// Cleanup old segments
$now = time();
foreach (glob($CACHE_DIR . '*.ts') as $file) {
    if ($now - @filemtime($file) > $SEGMENT_CACHE_TIME) {
        @unlink($file);
    }
}

// Headers
header('Content-Type: video/mp2t');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');

// Log request details
file_put_contents($LOG_FILE, "[" . date('Y-m-d H:i:s') . "] Request: " . $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);

// Test mode: Check if script loads
if (isset($_GET['testmode']) && $_GET['testmode'] === '1') {
    echo "Segment.php is running";
    exit;
}

// 1. Try test segments
$test_index = isset($_GET['test']) ? intval($_GET['test']) % 3 : rand(0, 2);
$test_url = $TEST_SEGMENTS[$test_index];
$cache_file = $CACHE_DIR . md5($test_url) . '.ts';

if (file_exists($cache_file)) {
    $data = @file_get_contents($cache_file);
    if (!empty($data)) {
        file_put_contents($LOG_FILE, "[" . date('Y-m-d H:i:s') . "] Served cached test segment: $cache_file\n", FILE_APPEND);
        echo $data;
        exit;
    }
    file_put_contents($LOG_FILE, "[" . date('Y-m-d H:i:s') . "] Empty cached test segment: $cache_file\n", FILE_APPEND);
    @unlink($cache_file);
}

$start_time = microtime(true);
$data = fetchSegment($test_url);
$duration = microtime(true) - $start_time;
if ($data !== false && !empty($data)) {
    file_put_contents($LOG_FILE, "[" . date('Y-m-d H:i:s') . "] Test segment fetched in {$duration}s: $test_url\n", FILE_APPEND);
    @file_put_contents($cache_file, $data);
    echo $data;
    exit;
}
file_put_contents($LOG_FILE, "[" . date('Y-m-d H:i:s') . "] Failed test segment in {$duration}s: $test_url\n", FILE_APPEND);

// 2. Try original segment
if (isset($_GET['seg']) && !empty($_GET['seg'])) {
    $url = "http://filex.tv:8080/live/Home329/Sohailhome/" . $_GET['seg'];
    $cache_file = $CACHE_DIR . md5($url) . '.ts';
    
    if (file_exists($cache_file)) {
        $data = @file_get_contents($cache_file);
        if (!empty($data)) {
            file_put_contents($LOG_FILE, "[" . date('Y-m-d H:i:s') . "] Served cached segment: $cache_file\n", FILE_APPEND);
            echo $data;
            exit;
        }
        file_put_contents($LOG_FILE, "[" . date('Y-m-d H:i:s') . "] Empty cached segment: $cache_file\n", FILE_APPEND);
        @unlink($cache_file);
    }
    
    $start_time = microtime(true);
    $data = fetchSegment($url);
    $duration = microtime(true) - $start_time;
    if ($data !== false && !empty($data)) {
        file_put_contents($LOG_FILE, "[" . date('Y-m-d H:i:s') . "] Segment fetched in {$duration}s: $url\n", FILE_APPEND);
        @file_put_contents($cache_file, $data);
        echo $data;
        exit;
    }
    file_put_contents($LOG_FILE, "[" . date('Y-m-d H:i:s') . "] Failed segment in {$duration}s: $url\n", FILE_APPEND);
}

// 3. Serve fallback segment
file_put_contents($LOG_FILE, "[" . date('Y-m-d H:i:s') . "] Serving fallback segment for request: " . $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);
echo $FALLBACK_SEGMENT;
exit;

function fetchSegment($url) {
    global $LOG_FILE;
    $context = stream_context_create([
        'http' => [
            'timeout' => 2,
            'header' => "User-Agent: Mozilla/5.0 (compatible; StreamFetcher/1.0)\r\n"
        ],
        'ssl' => ['verify_peer' => false]
    ]);
    $data = @file_get_contents($url, false, $context);
    if ($data === false) {
        $error = error_get_last();
        $status = isset($http_response_header) ? $http_response_header[0] : 'No response';
        file_put_contents($LOG_FILE, "[" . date('Y-m-d H:i:s') . "] Failed to fetch segment: $url, Status: $status, Error: " . ($error['message'] ?? 'Unknown') . "\n", FILE_APPEND);
    }
    return $data;
}
?>