<?php
// Configuration file for Stalker Portal credentials
$stalkerCredentials = [
    'host' => 'eagle2024.xyz:80',
    'mac' => '00:1A:79:C1:E3:9A'
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode >= 400) {
        die("Error: Handshake failed. HTTP $httpCode - $error");
    }

    $data = json_decode($response, true);
    $token = $data['js']['token'] ?? '';
    if (empty($token)) {
        die("Error: Failed to generate token.");
    }

    return $token;
}

function generate_token($forceRegenerate = false) {
    global $stalkerCredentials;
    static $cachedToken = null;

    if (!$forceRegenerate && $cachedToken) {
        return $cachedToken;
    }

    $token = handshake($stalkerCredentials['host'], $stalkerCredentials['mac']);
    $cachedToken = $token;
    return $token;
}
?>
