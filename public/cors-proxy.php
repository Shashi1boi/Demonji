<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Expose-Headers: *');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Hardcoded target
$url = 'https://servertvhub.site/jiotv+/mpd.php?id=1373';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) {
    $len = strlen($header);
    $headerParts = explode(':', $header, 2);
    if (count($headerParts) == 2) {
        $name = strtolower(trim($headerParts[0]));
        $safeHeaders = [
            'content-type', 'content-length', 'content-disposition',
            'accept-ranges', 'cache-control', 'etag', 'last-modified',
            'expires', 'date'
        ];
        if (in_array($name, $safeHeaders)) {
            header(trim($headerParts[0]) . ': ' . trim($headerParts[1]), false);
        }
    }
    return $len;
});

if (isset($_SERVER['HTTP_RANGE'])) {
    curl_setopt($ch, CURLOPT_RANGE, $_SERVER['HTTP_RANGE']);
}

curl_exec($ch);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($httpCode != 200 && $httpCode != 206) {
    if ($httpCode == 0 || $error) {
        http_response_code(502);
        echo "Proxy error: " . ($error ?: "No response from target");
    }
}
exit;
