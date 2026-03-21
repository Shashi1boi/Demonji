<?php
/**
 * YouTube Proxy using yt-dlp - Most reliable method
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Path to yt-dlp (adjust if needed)
define('YT_DLP', '/usr/local/bin/yt-dlp');

function extractVideoId($input) {
    $patterns = [
        '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
        '/youtu\.be\/([a-zA-Z0-9_-]{11})/',
        '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
        '/^([a-zA-Z0-9_-]{11})$/'
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input, $matches)) {
            return $matches[1];
        }
    }
    return null;
}

function runYtdlp($args, $timeout = 30) {
    $cmd = YT_DLP . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];
    $process = proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($process)) {
        return ['error' => 'Failed to start process'];
    }
    
    $output = '';
    $stderr = '';
    $start = time();
    
    while (true) {
        $read = [$pipes[1], $pipes[2]];
        $write = null;
        $except = null;
        $num = stream_select($read, $write, $except, 1, 0);
        if ($num === false) break;
        foreach ($read as $stream) {
            if ($stream === $pipes[1]) {
                $output .= fread($stream, 8192);
            } elseif ($stream === $pipes[2]) {
                $stderr .= fread($stream, 8192);
            }
        }
        if (time() - $start > $timeout) {
            proc_terminate($process);
            return ['error' => 'Timeout'];
        }
        $status = proc_get_status($process);
        if (!$status['running']) break;
    }
    
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $ret = proc_close($process);
    
    if ($ret !== 0) {
        return ['error' => "yt-dlp failed: $stderr"];
    }
    return ['output' => trim($output)];
}

// --------------------------------------------------------------------
// INFO endpoint - returns video info and formats
// --------------------------------------------------------------------
if (isset($_GET['info'])) {
    $videoId = extractVideoId($_GET['info']);
    if (!$videoId) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid video ID']));
    }
    
    // Get JSON info from yt-dlp
    $result = runYtdlp(['-j', "https://youtu.be/$videoId"]);
    if (isset($result['error'])) {
        http_response_code(500);
        die(json_encode(['error' => $result['error']]));
    }
    
    $info = json_decode($result['output'], true);
    if (!$info) {
        http_response_code(500);
        die(json_encode(['error' => 'Invalid JSON from yt-dlp']));
    }
    
    // Extract formats
    $formats = [];
    if (isset($info['formats'])) {
        foreach ($info['formats'] as $f) {
            if (empty($f['url'])) continue;
            $hasVideo = $f['vcodec'] !== 'none';
            $hasAudio = $f['acodec'] !== 'none';
            $formats[] = [
                'format_id' => $f['format_id'],
                'ext' => $f['ext'],
                'height' => $f['height'] ?? null,
                'width' => $f['width'] ?? null,
                'fps' => $f['fps'] ?? null,
                'vcodec' => $f['vcodec'] ?? 'none',
                'acodec' => $f['acodec'] ?? 'none',
                'url' => $f['url'],
                'quality' => $f['format_note'] ?? ($f['height'] ? "{$f['height']}p" : 'audio'),
                'hasVideo' => $hasVideo,
                'hasAudio' => $hasAudio,
                'filesize' => $f['filesize'] ?? null
            ];
        }
    }
    
    // Find best format (video+audio combined, highest quality)
    $bestFormat = null;
    foreach ($formats as $f) {
        if ($f['hasVideo'] && $f['hasAudio']) {
            $bestFormat = $f;
            break;
        }
    }
    if (!$bestFormat && !empty($formats)) {
        $bestFormat = $formats[0];
    }
    
    // Extract subtitles
    $subtitles = [];
    if (isset($info['subtitles'])) {
        foreach ($info['subtitles'] as $lang => $subs) {
            foreach ($subs as $sub) {
                $subtitles[] = [
                    'language' => $lang,
                    'name' => $sub['name'] ?? $lang,
                    'url' => $sub['url']
                ];
            }
        }
    }
    
    $response = [
        'title' => $info['title'] ?? 'Unknown',
        'uploader' => $info['uploader'] ?? 'Unknown',
        'duration' => $info['duration'] ?? 0,
        'thumbnail' => $info['thumbnail'] ?? '',
        'formats' => $formats,
        'best_format' => $bestFormat,
        'subtitles' => $subtitles
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// --------------------------------------------------------------------
// STREAM endpoint - proxies video to avoid CORS
// --------------------------------------------------------------------
if (isset($_GET['stream'])) {
    $videoId = extractVideoId($_GET['stream']);
    if (!$videoId) {
        http_response_code(400);
        die('Invalid video ID');
    }
    
    $formatId = isset($_GET['format']) ? $_GET['format'] : 'best';
    
    // Get direct video URL
    $result = runYtdlp(['-g', '-f', $formatId, "https://youtu.be/$videoId"]);
    if (isset($result['error'])) {
        http_response_code(500);
        die($result['error']);
    }
    
    $directUrl = $result['output'];
    if (!filter_var($directUrl, FILTER_VALIDATE_URL)) {
        http_response_code(500);
        die('Invalid stream URL');
    }
    
    // Proxy the video (avoids CORS)
    $ch = curl_init($directUrl);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_BUFFERSIZE, 128 * 1024);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; CORS-Proxy/1.0)');
    curl_exec($ch);
    curl_close($ch);
    exit;
}

http_response_code(400);
echo 'Usage: ?info=VIDEO_ID or ?stream=VIDEO_ID&format=FORMAT_ID';
?>
