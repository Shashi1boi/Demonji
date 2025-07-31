<?php
// Configuration file for Stalker Portal credentials
$stalkerCredentials = [
    'host' => 'eagle2024.xyz:80/c',
    'mac' => '00:1A:79:C1:E3:9A',
    'serial_number' => '',
    'device_id_1' => '',
    'device_id_2' => '',
    'signature' => '',
    'api_version' => '263'
];

function handshake($host, $mac) {
    $url = "http://{$host}/stalker_portal/server/load.php?type=stb&action=handshake&token=&JsHttpRequest=1-xml";
    $headers = [
        'User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG200 stbapp ver: 2 rev: 250 Safari/533.3',
        'Connection: Keep-Alive',
        'Accept-Encoding: gzip',
        'X-User-Agent: Model: MAG250; Link: WiFi',
        "Referer: http://{$host}/stalker_portal/c/",
        "Host: {$host}",
        "Connection: Keep-Alive"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_COOKIE, "mac={$mac}; stb_lang=en; timezone=GMT");
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['js']['token'] ?? '';
}

function get_profile($host, $mac, $serial_number, $device_id_1, $device_id_2, $signature, $api_version, $token) {
    $timestamp = time();
    $url = "http://{$host}/stalker_portal/server/load.php?type=stb&action=get_profile&hd=1&ver=ImageDescription%3A+0.2.18-r14-pub-250%3B+ImageDate%3A+Fri+Jan+15+15%3A20%3A44+EET+2016%3B+PORTAL+version%3A+5.1.0%3B+API+Version%3A+JS+API+version%3A+328%3B+STB+API+version%3A+134%3B+Player+Engine+version%3A+0x566&num_banks=2&sn={$serial_number}&stb_type=MAG250&image_version=218&video_out=hdmi&device_id={$device_id_1}&device_id2={$device_id_2}&signature={$signature}&auth_second_step=1&hw_version=1.7-BD-00&not_valid_token=0&client_type=STB&hw_version_2=08e10744513ba2b4847402b6718c0eae&timestamp={$timestamp}&api_signature={$api_version}&metrics=%7B%22mac%22%3A%22{$mac}%22%2C%22sn%22%3A%22{$serial_number}%22%2C%22model%22%3A%22MAG250%22%2C%22type%22%3A%22STB%22%2C%22uid%22%3A%22%22%2C%22random%22%3A%22%22%7D&JsHttpRequest=1-xml";
    $headers = [
        'User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG200 stbapp ver: 2 rev: 250 Safari/533.3',
        'Connection: Keep-Alive',
        'Accept-Encoding: gzip',
        'X-User-Agent: Model: MAG250; Link: WiFi',
        "Referer: http://{$host}/stalker_portal/c/",
        "Authorization: Bearer {$token}",
        "Host: {$host}",
        "Connection: Keep-Alive"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_COOKIE, "mac={$mac}; stb_lang=en; timezone=GMT");
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    $response = curl_exec($ch);
    curl_close($ch);
}

function generate_token($forceRegenerate = false) {
    global $stalkerCredentials;
    static $cachedToken = null;

    if (!$forceRegenerate && $cachedToken) {
        return $cachedToken;
    }

    $token = handshake($stalkerCredentials['host'], $stalkerCredentials['mac']);
    if (empty($token)) {
        die("Error: Failed to generate token.");
    }

    get_profile(
        $stalkerCredentials['host'],
        $stalkerCredentials['mac'],
        $stalkerCredentials['serial_number'],
        $stalkerCredentials['device_id_1'],
        $stalkerCredentials['device_id_2'],
        $stalkerCredentials['signature'],
        $stalkerCredentials['api_version'],
        $token
    );

    $cachedToken = $token;
    return $token;
}
?>
