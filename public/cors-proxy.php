<?php
/**
 * YouTube Proxy using Invidious/Piped APIs (reliable, no scraping)
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// --------------------------------------------------------------------
// Configuration: list of API sources (priority order)
// --------------------------------------------------------------------
$API_SOURCES = [
    // Invidious instances (use /api/v1/videos/{id})
    ['type' => 'invidious', 'name' => 'Invidious (vern.cc)', 'url' => 'https://inv.vern.cc/api/v1/videos/'],
    ['type' => 'invidious', 'name' => 'Invidious (yewtu.be)', 'url' => 'https://yewtu.be/api/v1/videos/'],
    ['type' => 'invidious', 'name' => 'Invidious (tube.cthd.icu)', 'url' => 'https://tube.cthd.icu/api/v1/videos/'],
    ['type' => 'invidious', 'name' => 'Invidious (inv.odyssey346.dev)', 'url' => 'https://inv.odyssey346.dev/api/v1/videos/'],
    ['type' => 'invidious', 'name' => 'Invidious (iv.melmac.space)', 'url' => 'https://iv.melmac.space/api/v1/videos/'],
    ['type' => 'invidious', 'name' => 'Invidious (inv.riverside.rocks)', 'url' => 'https://inv.riverside.rocks/api/v1/videos/'],
    // Piped instances (use /streams/{id})
    ['type' => 'piped', 'name' => 'Piped (piped.video)', 'url' => 'https://pipedapi.piped.video/streams/'],
    ['type' => 'piped', 'name' => 'Piped (kavin.rocks)', 'url' => 'https://pipedapi.kavin.rocks/streams/'],
    ['type' => 'piped', 'name' => 'Piped (smnz.de)', 'url' => 'https://pipedapi.smnz.de/streams/'],
    ['type' => 'piped', 'name' => 'Piped (adminforge.de)', 'url' => 'https://pipedapi.adminforge.de/streams/'],
    ['type' => 'piped', 'name' => 'Piped (moomoo.me)', 'url' => 'https://pipedapi.moomoo.me/streams/'],
    ['type' => 'piped', 'name' => 'Piped (privacydev.net)', 'url' => 'https://pipedapi.privacydev.net/streams/'],
];

// --------------------------------------------------------------------
// Helper: fetch a URL with cURL
// --------------------------------------------------------------------
function fetchUrl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; PHP-Proxy/1.0)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) {
        return false;
    }
    return $response;
}

// --------------------------------------------------------------------
// Convert Invidious API response to our standard format
// --------------------------------------------------------------------
function convertInvidious($data) {
    $formats = [];

    // Video+audio formats
    if (isset($data['formatStreams'])) {
        foreach ($data['formatStreams'] as $f) {
            $formats[] = [
                'format_id' => $f['itag'],
                'ext' => $f['container'],
                'height' => $f['qualityLabel'] ? (int) filter_var($f['qualityLabel'], FILTER_SANITIZE_NUMBER_INT) : null,
                'width' => null,
                'fps' => null,
                'vcodec' => $f['type'],
                'acodec' => 'audio present',
                'filesize' => $f['size'] ?? null,
                'url' => $f['url'],
                'quality' => $f['qualityLabel'] ?? $f['quality'],
                'hasVideo' => true,
                'hasAudio' => true,
            ];
        }
    }

    // Adaptive formats
    if (isset($data['adaptiveFormats'])) {
        foreach ($data['adaptiveFormats'] as $f) {
            $hasVideo = strpos($f['type'], 'video/') !== false;
            $hasAudio = strpos($f['type'], 'audio/') !== false;
            $formats[] = [
                'format_id' => $f['itag'],
                'ext' => $f['container'],
                'height' => $hasVideo ? (int) filter_var($f['qualityLabel'], FILTER_SANITIZE_NUMBER_INT) : null,
                'width' => null,
                'fps' => null,
                'vcodec' => $hasVideo ? $f['type'] : 'none',
                'acodec' => $hasAudio ? $f['type'] : 'none',
                'filesize' => $f['size'] ?? null,
                'url' => $f['url'],
                'quality' => $hasVideo ? ($f['qualityLabel'] ?? $f['quality']) : "Audio " . ($f['bitrate'] ? floor($f['bitrate']/1000)."kbps" : ''),
                'hasVideo' => $hasVideo,
                'hasAudio' => $hasAudio,
            ];
        }
    }

    // Sort by height descending
    usort($formats, function($a, $b) {
        return ($b['height'] ?? 0) - ($a['height'] ?? 0);
    });

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

    // Subtitles
    $subtitles = [];
    if (isset($data['captions'])) {
        foreach ($data['captions'] as $cap) {
            $subtitles[] = [
                'language' => $cap['languageCode'],
                'name' => $cap['name'] ?? $cap['languageCode'],
                'url' => $cap['url'],
                'format' => 'vtt'
            ];
        }
    }

    return [
        'title' => $data['title'] ?? 'Unknown',
        'uploader' => $data['author'] ?? 'Unknown',
        'duration' => $data['lengthSeconds'] ?? 0,
        'thumbnail' => $data['videoThumbnails'][0]['url'] ?? '',
        'formats' => $formats,
        'best_format' => $bestFormat,
        'subtitles' => $subtitles,
    ];
}

// --------------------------------------------------------------------
// Convert Piped API response to our standard format
// --------------------------------------------------------------------
function convertPiped($data) {
    $formats = [];

    if (isset($data['videoStreams'])) {
        foreach ($data['videoStreams'] as $f) {
            $formats[] = [
                'format_id' => $f['itag'],
                'ext' => $f['format'],
                'height' => $f['quality'] === 'audio' ? null : (int) filter_var($f['quality'], FILTER_SANITIZE_NUMBER_INT),
                'width' => null,
                'fps' => null,
                'vcodec' => $f['codec'],
                'acodec' => $f['codec'],
                'filesize' => null,
                'url' => $f['url'],
                'quality' => $f['quality'],
                'hasVideo' => $f['quality'] !== 'audio',
                'hasAudio' => true,
            ];
        }
    }

    if (isset($data['audioStreams'])) {
        foreach ($data['audioStreams'] as $f) {
            $formats[] = [
                'format_id' => $f['itag'],
                'ext' => $f['format'],
                'height' => null,
                'width' => null,
                'fps' => null,
                'vcodec' => 'none',
                'acodec' => $f['codec'],
                'filesize' => null,
                'url' => $f['url'],
                'quality' => "Audio " . ($f['bitrate'] ? floor($f['bitrate']/1000)."kbps" : ''),
                'hasVideo' => false,
                'hasAudio' => true,
            ];
        }
    }

    usort($formats, function($a, $b) {
        return ($b['height'] ?? 0) - ($a['height'] ?? 0);
    });

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

    $subtitles = [];
    if (isset($data['subtitles'])) {
        foreach ($data['subtitles'] as $sub) {
            $subtitles[] = [
                'language' => $sub['code'],
                'name' => $sub['name'],
                'url' => $sub['url'],
                'format' => 'vtt'
            ];
        }
    }

    return [
        'title' => $data['title'] ?? 'Unknown',
        'uploader' => $data['uploader'] ?? 'Unknown',
        'duration' => $data['duration'] ?? 0,
        'thumbnail' => $data['thumbnailUrl'] ?? '',
        'formats' => $formats,
        'best_format' => $bestFormat,
        'subtitles' => $subtitles,
    ];
}

// --------------------------------------------------------------------
// Extract video ID from input (same as before)
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
// INFO endpoint: try each API source until one works
// --------------------------------------------------------------------
if (isset($_GET['info'])) {
    $videoId = extractVideoId($_GET['info']);
    if (!$videoId) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid video ID']));
    }

    $convertedData = null;
    $lastError = null;

    global $API_SOURCES;
    foreach ($API_SOURCES as $source) {
        $url = $source['url'] . $videoId;
        $response = fetchUrl($url);
        if ($response === false) {
            $lastError = "Failed to fetch from " . $source['name'];
            continue;
        }
        $data = json_decode($response, true);
        if (!$data) {
            $lastError = "Invalid JSON from " . $source['name'];
            continue;
        }
        if (isset($data['error'])) {
            $lastError = "API error from " . $source['name'] . ": " . $data['error'];
            continue;
        }

        // Convert to our standard format
        if ($source['type'] === 'invidious') {
            $convertedData = convertInvidious($data);
        } else {
            $convertedData = convertPiped($data);
        }

        // If we got at least one format, stop trying
        if (!empty($convertedData['formats'])) {
            break;
        } else {
            $lastError = "No formats found from " . $source['name'];
            $convertedData = null;
        }
    }

    if (!$convertedData) {
        http_response_code(500);
        die(json_encode(['error' => 'All API sources failed: ' . $lastError]));
    }

    header('Content-Type: application/json');
    echo json_encode($convertedData);
    exit;
}

// --------------------------------------------------------------------
// STREAM endpoint: proxy the video URL (no CORS)
// --------------------------------------------------------------------
if (isset($_GET['stream'])) {
    $videoId = extractVideoId($_GET['stream']);
    if (!$videoId) {
        http_response_code(400);
        die('Invalid video ID');
    }

    $formatId = isset($_GET['format']) ? $_GET['format'] : null;
    $targetUrl = null;

    // First find the format in the same way as INFO
    foreach ($API_SOURCES as $source) {
        $url = $source['url'] . $videoId;
        $response = fetchUrl($url);
        if ($response === false) continue;
        $data = json_decode($response, true);
        if (!$data) continue;

        // Convert to our format
        if ($source['type'] === 'invidious') {
            $formats = convertInvidious($data)['formats'];
        } else {
            $formats = convertPiped($data)['formats'];
        }

        if ($formatId) {
            foreach ($formats as $f) {
                if ($f['format_id'] == $formatId) {
                    $targetUrl = $f['url'];
                    break 2;
                }
            }
        } else {
            // No format specified: use best video+audio
            foreach ($formats as $f) {
                if ($f['hasVideo'] && $f['hasAudio']) {
                    $targetUrl = $f['url'];
                    break 2;
                }
            }
            // fallback to first format
            if (!$targetUrl && !empty($formats)) {
                $targetUrl = $formats[0]['url'];
                break;
            }
        }
    }

    if (!$targetUrl) {
        http_response_code(404);
        die('Stream URL not found');
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
