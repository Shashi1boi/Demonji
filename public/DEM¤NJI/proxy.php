<?php
// proxy.php - m3u8 proxy that preserves Akamai token for all segments

// Hardcoded target (change or pass via ?url=)
$target = $_GET['url'] ?? 'https://in-mc-flive.fancode.com/mumbai/143399_english_hls_b76266f66a74436_1ta-di_h264/1080p.m3u8?hdntl=Expires=1782731105~_GO=Generated~acl=/mumbai/143399_english_hls_b76266f66a74436_1ta-di_h264/*~Signature=ARZJ1WIXxPRrwRMWjCPiKVQW0N17EVSgSFeDK30KVu9zLQgrQ7bJFec0Wk3KvENLLvH7Dcg_4P3EAAZKWpGkUeyIB3wL';

// Parse target to get base path and query string
$parts = parse_url($target);
$basePath = dirname($parts['path']) . '/';
$query = isset($parts['query']) ? '?' . $parts['query'] : '';

// Fetch the original m3u8
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $target,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30
]);
$content = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200 || empty($content)) {
    http_response_code(404);
    exit('Playlist not available');
}

// Rewrite segment URIs
$lines = explode("\n", $content);
$newLines = [];
foreach ($lines as $line) {
    $trimmed = trim($line);
    if (empty($trimmed) || $trimmed[0] === '#') {
        $newLines[] = $line; // keep comments and tags unchanged
    } else {
        // Construct absolute URL with token query
        if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
            // Already absolute, just append the token query
            $segmentUrl = $trimmed . (parse_url($trimmed, PHP_URL_QUERY) ? '&' : '?') . ltrim($query, '?');
        } else {
            // Relative path: combine with base path and append token
            $segmentUrl = (isset($parts['scheme']) ? $parts['scheme'] . '://' : '') .
                          (isset($parts['host']) ? $parts['host'] : '') .
                          $basePath . $trimmed . $query;
        }
        // Encode and proxy through this script
        $newLines[] = 'proxy.php?url=' . urlencode($segmentUrl);
    }
}
$newContent = implode("\n", $newLines);

// Output as m3u8
header('Content-Type: application/vnd.apple.mpegurl');
header('Content-Disposition: inline; filename="playlist.m3u8"');
echo $newContent;
?>
