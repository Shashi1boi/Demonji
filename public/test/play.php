<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
set_time_limit(0); // Prevent script timeout

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(http_response_code(204));
}

function generateRandomToken($length = 10) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $token = '';
    for ($i = 0; $i < $length; $i++) {
        $token .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $token;
}

function generateRandomDeviceModel() {
    $models = [
        'Samsung' => ['Galaxy S21' => 'Android 11', 'Galaxy S22' => 'Android 12', 'Galaxy S23' => 'Android 13'],
        'Xiaomi' => ['Mi 11' => 'Android 11', 'Mi 12' => 'Android 12', 'Redmi Note 10' => 'Android 11'],
    ];

    $randomBrand = array_rand($models);
    $randomModel = array_rand($models[$randomBrand]);
    $androidVersion = $models[$randomBrand][$randomModel];

    return [
        'brand' => $randomBrand,
        'model' => $randomModel,
        'android_version' => $androidVersion
    ];
}

// Hardcoded Xtream Codes credentials
$host = "http://filex.tv:8080"; // Replace with your Xtream server URL
$username = "Home329"; // Replace with your username
$password = "Sohailhome"; // Replace with your password

if (empty($_GET['id'])) {
    exit("Error: Missing or invalid 'id' parameter.");
}

$id = urlencode($_GET['id']);
$url = "{$host}/live/{$username}/{$password}/{$id}.m3u8";

$uniqueToken = generateRandomToken();
$deviceModel = generateRandomDeviceModel();

$headers = [
    "User-Agent: OTT Navigator/1.6.7.4 (Linux; {$deviceModel['android_version']}; {$deviceModel['brand']} {$deviceModel['model']}) ExoPlayerLib/2.15.1",
    "Host: " . parse_url($host, PHP_URL_HOST),
    "Connection: keep-alive",
    "Accept-Encoding: gzip",
    "X-Unique-Token: " . $uniqueToken,
    "X-Request-ID: " . uniqid(),
    "X-Device-Model: {$deviceModel['brand']} {$deviceModel['model']}"
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
curl_setopt($ch, CURLOPT_TIMEOUT, 0); // No timeout for streaming
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 120);
curl_setopt($ch, CURLOPT_TCP_KEEPINTVL, 60);

$response = curl_exec($ch);
if (!$response) {
    $error = curl_error($ch);
    curl_close($ch);
    exit("Error: cURL request failed. " . $error);
}

$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

if ($status_code != 200) {
    exit("Error: Failed to fetch the m3u8 file. HTTP status code: $status_code");
}

$baseUrl = parse_url($final_url, PHP_URL_SCHEME) . '://' . parse_url($final_url, PHP_URL_HOST);
if ($port = parse_url($final_url, PHP_URL_PORT)) {
    $baseUrl .= ":$port";
}

$processedResponse = implode("\n", array_map(function ($line) use ($baseUrl) {
    if (preg_match('#\.ts($|\?)#', $line) && !filter_var($line, FILTER_VALIDATE_URL)) {
        return $baseUrl . '/' . ltrim($line, '/');
    }
    return $line;
}, explode("\n", $response)));

header('Content-Type: application/vnd.apple.mpegurl');
header('Content-Disposition: attachment; filename="' . $id . '.m3u8"');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
echo trim($processedResponse);
?>
