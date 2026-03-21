<?php
/**
 * Improved PHP YouTube Proxy – Works even when YouTube changes the page.
 */

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function fetchUrl($url, $headers = [], $cookie = '') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
    if ($cookie) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
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

function getPlayerResponse($videoId) {
    // First, try with a simple consent cookie to avoid age‑gate
    $cookie = 'CONSENT=YES+cb.20231201-18-p0.en+FX+123;';
    $url = "https://www.youtube.com/watch?v=$videoId";
    $html = fetchUrl($url, [], $cookie);
    if (!$html) {
        return ['error' => 'Failed to fetch YouTube page'];
    }

    // Look for ytInitialPlayerResponse
    if (preg_match('/ytInitialPlayerResponse\s*=\s*({.+?})\s*;\s*(?:var|<\/script)/s', $html, $match)) {
        $json = $match[1];
        $data = json_decode($json, true);
        if ($data) {
            return $data;
        }
    }

    // Try another pattern
    if (preg_match('/var ytInitialPlayerResponse = ({.+?});/', $html, $match)) {
        $json = $match[1];
        $data = json_decode($json, true);
        if ($data) {
            return $data;
        }
    }

    // Fallback: maybe the page contains a different variable
    if (preg_match('/ytInitialData\s*=\s*({.+?})\s*;\s*(?:var|<\/script)/s', $html, $match)) {
        // Sometimes the video details are inside ytInitialData – we could parse it,
        // but it's more complex. For now, we return an error.
        return ['error' => 'Found ytInitialData but not ytInitialPlayerResponse – YouTube may have changed the structure'];
    }

    return ['error' => 'Could not extract player response from page'];
}

function extractFormats($playerResponse) {
    $formats = [];

    if (!empty($playerResponse['streamingData']['formats'])) {
        foreach ($playerResponse['streamingData']['formats'] as $fmt) {
            if (empty($fmt['url'])) continue;
            $formats[] = [
                'format_id' => $fmt['itag'],
                'ext' => explode(';', explode('/', $fmt['mimeType'])[1])[0],
                'height' => $fmt['height'] ?? null,
                'width' => $fmt['width'] ?? null,
                'fps' => $fmt['fps'] ?? null,
                'vcodec' => $fmt['mimeType'] ?? '',
                'acodec' => 'audio present',
                'filesize' => $fmt['contentLength'] ?? null,
                'url' => $fmt['url'],
                'quality' => ($fmt['qualityLabel'] ?? $fmt['quality']) . ($fmt['fps'] ? " ({$fmt['fps']}fps)" : ''),
                'hasVideo' => true,
                'hasAudio' => true,
            ];
        }
    }

    if (!empty($playerResponse['streamingData']['adaptiveFormats'])) {
        foreach ($playerResponse['streamingData']['adaptiveFormats'] as $fmt) {
            if (empty($fmt['url'])) continue;
            $hasVideo = strpos($fmt['mimeType'], 'video/') !== false;
            $hasAudio = strpos($fmt['mimeType'], 'audio/') !== false;
            $formats[] = [
                'format_id' => $fmt['itag'],
                'ext' => explode(';', explode('/', $fmt['mimeType'])[1])[0],
                'height' => $fmt['height'] ?? null,
                'width' => $fmt['width'] ?? null,
                'fps' => $fmt['fps'] ?? null,
                'vcodec' => $hasVideo ? ($fmt['mimeType'] ?? '') : 'none',
                'acodec' => $hasAudio ? ($fmt['mimeType'] ?? '') : 'none',
                'filesize' => $fmt['contentLength'] ?? null,
                'url' => $fmt['url'],
                'quality' => $hasVideo ? ($fmt['qualityLabel'] ?? $fmt['quality']) : "Audio " . ($fmt['bitrate'] ? floor($fmt['bitrate']/1000)."kbps" : ''),
                'hasVideo' => $hasVideo,
                'hasAudio' => $hasAudio,
            ];
        }
    }

    usort($formats, function($a, $b) {
        return ($b['height'] ?? 0) - ($a['height'] ?? 0);
    });

    return $formats;
}

function extractSubtitles($playerResponse) {
    $subtitles = [];
    if (!empty($playerResponse['captions']['playerCaptionsTracklistRenderer']['captionTracks'])) {
        foreach ($playerResponse['captions']['playerCaptionsTracklistRenderer']['captionTracks'] as $track) {
            $subtitles[] = [
                'language' => $track['languageCode'],
                'name' => $track['name']['simpleText'] ?? $track['languageCode'],
                'url' => $track['baseUrl'],
                'format' => 'vtt'
            ];
        }
    }
    return $subtitles;
}

if (isset($_GET['info'])) {
    $videoId = extractVideoId($_GET['info']);
    if (!$videoId) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid video ID']));
    }

    $playerResponse = getPlayerResponse($videoId);
    if (isset($playerResponse['error'])) {
        http_response_code(500);
        die(json_encode(['error' => $playerResponse['error']]));
    }

    $videoDetails = $playerResponse['videoDetails'] ?? [];

    $formats = extractFormats($playerResponse);
    $subtitles = extractSubtitles($playerResponse);

    // Determine best format (prefer video+audio, highest quality)
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
        'title' => $videoDetails['title'] ?? 'Unknown',
        'uploader' => $videoDetails['author'] ?? 'Unknown',
        'duration' => $videoDetails['lengthSeconds'] ?? 0,
        'thumbnail' => $videoDetails['thumbnail']['thumbnails'][0]['url'] ?? '',
        'formats' => $formats,
        'best_format' => $bestFormat,
        'subtitles' => $subtitles,
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if (isset($_GET['stream'])) {
    $videoId = extractVideoId($_GET['stream']);
    if (!$videoId) {
        http_response_code(400);
        die('Invalid video ID');
    }

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

    // Proxy the video
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

http_response_code(400);
echo 'Usage: ?info=VIDEO_ID or ?stream=VIDEO_ID&format=FORMAT_ID';
?>
