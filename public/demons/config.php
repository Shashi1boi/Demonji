<?php
// Configuration file for Stalker Portal credentials
$stalkerCredentials = [
    'host' => 'http://jiotv.be',
    'mac' => '00:1A:79:F3:B2:FF'
];

function handshake($host, $mac) {
    $url = "http://{$host}/stalker_portal/server/load.php?type=stb&action=handshake&token=&JsHttpRequest=1-xml";
    $headers = [
        "Cookie: mac={$mac}; stb_lang=en; timezone=GMT",
        "User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG200 stbapp ver: 2 rev: 250 Safari/533.3",
        "Accept: */*",
        "Referer: http://{$host}/stalker_portal/c/",
        "Connection: Keep-Alive",
        "Accept-Encoding: gzip"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    curl_setopt($ch, CURLOPT_HEADER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headersReceived = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode >= 400) {
        die("Error: Handshake failed. HTTP $httpCode\nHeaders: $headersReceived\nBody: $body\nError: $error");
    }

    $data = json_decode($body, true);
    $token = $data['js']['token'] ?? '';
    if (empty($token)) {
        die("Error: Failed to generate token. Response: $body");
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
