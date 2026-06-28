<?php
// proxy.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Content-Type: application/vnd.apple.mpegurl');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$id = isset($_GET['id']) ? $_GET['id'] : null;
if (!$id) {
    echo "#EXTM3U\n#EXT-X-ERROR: Missing 'id' parameter";
    exit;
}

$jsonUrl = 'https://raw.githubusercontent.com/doctor-8trange/zyphx8/refs/heads/main/data/fancode.json';
$jsonData = file_get_contents($jsonUrl);
if ($jsonData === false) {
    echo "#EXTM3U\n#EXT-X-ERROR: Failed to fetch data from source";
    exit;
}

$data = json_decode($jsonData, true);
if (!isset($data['matches']) || !is_array($data['matches'])) {
    echo "#EXTM3U\n#EXT-X-ERROR: Invalid data format";
    exit;
}

$found = null;
foreach ($data['matches'] as $match) {
    if (isset($match['match_id']) && (string)$match['match_id'] === (string)$id) {
        $found = $match;
        break;
    }
}

if (!$found) {
    echo "#EXTM3U\n#EXT-X-ERROR: Match not found";
    exit;
}

$autoStreams = isset($found['auto_streams']) ? $found['auto_streams'] : [];
if (!is_array($autoStreams) || count($autoStreams) === 0) {
    echo "#EXTM3U\n#EXT-X-ERROR: No stream available";
    exit;
}

$auto = isset($autoStreams[0]['auto']) ? $autoStreams[0]['auto'] : '';
if (empty($auto)) {
    echo "#EXTM3U\n#EXT-X-ERROR: Stream data empty";
    exit;
}

// Output the exact tokenized M3U8 content
echo $auto;
exit;
