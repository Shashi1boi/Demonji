<?php
/**
 * Pure PHP YouTube Proxy – No shell/yt‑dlp required.
 * 
 * Usage:
 *   ?info=VIDEO_ID         -> returns JSON with video metadata & formats
 *   ?stream=VIDEO_ID&format=FORMAT_ID  -> streams the video (proxied, no CORS)
 */

// CORS headers (allow any origin)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// --------------------------------------------------------------------
// Helper: fetch a URL with custom headers and return the content
// --------------------------------------------------------------------
function fetchUrl($url, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) {
        return false;
    }
    return $response;
}

// --------------------------------------------------------------------
// Extract video ID from various input formats
// --------------------------------------------------------------------
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

// --------------------------------------------------------------------
// Parse the YouTube watch page and extract player response JSON
// --------------------------------------------------------------------
function getPlayerResponse($videoId) {
    $url = "https://www.youtube.com/watch?v=$videoId";
    $html = fetchUrl($url);
    if (!$html) {
        return ['error' => 'Failed to fetch YouTube page'];
    }

    // Find the ytInitialPlayerResponse JSON
    if (preg_match('/ytInitialPlayerResponse\s*=\s*({.+?})\s*;\s*(?:var|<\/script)/s', $html, $match)) {
        $json = $match[1];
        $data = json_decode($json, true);
        if ($data) {
            return $data;
        }
    }

    // Fallback: look for another pattern
    if (preg_match('/var ytInitialPlayerResponse = ({.+?});/', $html, $match)) {
        $json = $match[1];
        $data = json_decode($json, true);
        if ($data) {
            return $data;
        }
    }

    return ['error' => 'Could not extract player response'];
}

// --------------------------------------------------------------------
// Convert YouTube's streaming data into a nice formats list
// --------------------------------------------------------------------
function extractFormats($playerResponse) {
    $formats = [];

    // Video+audio formats (called "formats")
    if (!empty($playerResponse['streamingData']['formats'])) {
        foreach ($playerResponse['streamingData']['formats'] as $fmt) {
            if (empty($fmt['url'])) continue;
            $formats[] = [
                'format_id'   => $fmt['itag'],
                'ext'         => $fmt['mimeType'] ? explode(';', explode('/', $fmt['mimeType'])[1])[0] : 'mp4',
                'height'      => $fmt['height'] ?? null,
                'width'       => $fmt['width'] ?? null,
                'fps'         => $fmt['fps'] ?? null,
                'vcodec'      => $fmt['mimeType'] ?? '',
                'acodec'      => 'audio present',
                'filesize'    => $fmt['contentLength'] ?? null,
                'url'         => $fmt['url'],
                'quality'     => ($fmt['qualityLabel'] ?? $fmt['quality']) . ($fmt['fps'] ? " ({$fmt['fps']}fps)" : ''),
                'hasVideo'    => true,
                'hasAudio'    => true,
            ];
        }
    }

    // Adaptive formats (video only or audio only)
    if (!empty($playerResponse['streamingData']['adaptiveFormats'])) {
        foreach ($playerResponse['streamingData']['adaptiveFormats'] as $fmt) {
            if (empty($fmt['url'])) continue;
            $hasVideo = strpos($fmt['mimeType'], 'video/') !== false;
            $hasAudio = strpos($fmt['mimeType'], 'audio/') !== false;
            $formats[] = [
                'format_id'   => $fmt['itag'],
                'ext'         => $fmt['mimeType'] ? explode(';', explode('/', $fmt['mimeType'])[1])[0] : ($hasVideo ? 'mp4' : 'm4a'),
                'height'      => $fmt['height'] ?? null,
                'width'       => $fmt['width'] ?? null,
                'fps'         => $fmt['fps'] ?? null,
                'vcodec'      => $hasVideo ? ($fmt['mimeType'] ?? '') : 'none',
                'acodec'      => $hasAudio ? ($fmt['mimeType'] ?? '') : 'none',
                'filesize'    => $fmt['contentLength'] ?? null,
                'url'         => $fmt['url'],
                'quality'     => $hasVideo ? ($fmt['qualityLabel'] ?? $fmt['quality']) : "Audio " . ($fmt['bitrate'] ? floor($fmt['bitrate']/1000)."kbps" : ''),
                'hasVideo'    => $hasVideo,
                'hasAudio'    => $hasAudio,
            ];
        }
    }

    // Sort by quality (height descending)
    usort($formats, function($a, $b) {
        return ($b['height'] ?? 0) - ($a['height'] ?? 0);
    });

    return $formats;
}

// --------------------------------------------------------------------
// Extract subtitles (captions) from player response
// --------------------------------------------------------------------
function extractSubtitles($playerResponse) {
    $subtitles = [];
    if (!empty($playerResponse['captions']['playerCaptionsTracklistRenderer']['captionTracks'])) {
        foreach ($playerResponse['captions']['playerCaptionsTracklistRenderer']['captionTracks'] as $track) {
            $subtitles[] = [
                'language' => $track['languageCode'],
                'name'     => $track['name']['simpleText'] ?? $track['languageCode'],
                'url'      => $track['baseUrl'],
                'format'   => 'vtt'
            ];
        }
    }
    return $subtitles;
}

// --------------------------------------------------------------------
// INFO endpoint
// --------------------------------------------------------------------
if (isset($_GET['info'])) {
    $videoId = extractVideoId($_GET['info']);
    if (!$videoId) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid video ID']));
    }

    $playerResponse = getPlayerResponse($videoId);
    if (isset($playerResponse['error'])) {
        http_response_code(500);
        die(json_encode($playerResponse));
    }

    $videoDetails = $playerResponse['videoDetails'] ?? [];

    $formats = extractFormats($playerResponse);
    $subtitles = extractSubtitles($playerResponse);

    // Determine best format (prefer video+audio combined, highest quality)
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

    $response = [
        'title'       => $videoDetails['title'] ?? 'Unknown',
        'uploader'    => $videoDetails['author'] ?? 'Unknown',
        'duration'    => $videoDetails['lengthSeconds'] ?? 0,
        'thumbnail'   => $videoDetails['thumbnail']['thumbnails'][0]['url'] ?? '',
        'formats'     => $formats,
        'best_format' => $bestFormat,
        'subtitles'   => $subtitles,
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// --------------------------------------------------------------------
// STREAM endpoint (proxies the video to avoid CORS)
// --------------------------------------------------------------------
if (isset($_GET['stream'])) {
    $videoId = extractVideoId($_GET['stream']);
    if (!$videoId) {
        http_response_code(400);
        die('Invalid video ID');
    }

    // If format ID is provided, we need to fetch the player response and get that specific format's URL.
    $formatId = isset($_GET['format']) ? $_GET['format'] : null;

    $playerResponse = getPlayerResponse($videoId);
    if (isset($playerResponse['error'])) {
        http_response_code(500);
        die($playerResponse['error']);
    }

    $formats = extractFormats($playerResponse);
    $targetUrl = null;

    if ($formatId) {
        foreach ($formats as $f) {
            if ($f['format_id'] == $formatId) {
                $targetUrl = $f['url'];
                break;
            }
        }
        if (!$targetUrl) {
            http_response_code(404);
            die('Format not found');
        }
    } else {
        // No format specified: use best video+audio format
        foreach ($formats as $f) {
            if ($f['hasVideo'] && $f['hasAudio']) {
                $targetUrl = $f['url'];
                break;
            }
        }
        if (!$targetUrl && !empty($formats)) {
            $targetUrl = $formats[0]['url'];
        }
        if (!$targetUrl) {
            http_response_code(404);
            die('No stream URL found');
        }
    }

    // Proxy the video through this script (avoid CORS)
    $ch = curl_init($targetUrl);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_BUFFERSIZE, 128 * 1024);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; CORS-Proxy/1.0)');
    curl_exec($ch);
    curl_close($ch);
    exit;
}

// --------------------------------------------------------------------
// Default: show help
// --------------------------------------------------------------------
http_response_code(400);
echo 'Usage: ?info=VIDEO_ID or ?stream=VIDEO_ID&format=FORMAT_ID';
?>
