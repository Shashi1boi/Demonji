<?php
// ======== INITIAL SETTINGS ========
error_reporting(E_ALL); // Enable error reporting for debugging (disable in production: error_reporting(0))
ini_set('display_errors', 1);
set_time_limit(60); // Adjusted for Render's free tier
date_default_timezone_set('Asia/Kolkata');

// ======== CONFIGURATION ========
// Dynamically generate restream base URL (used for logging)
$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'https';
$host = $_SERVER['HTTP_HOST'];
$script = $_SERVER['SCRIPT_NAME'];
$restream_base_url = "{$scheme}://{$host}{$script}";

$xtream_config = [
    'panel_url' => 'http://filex.tv:8080',
    'username'  => 'Home329',
    'password'  => 'Sohailhome',
    'token'     => null,
    'token_expiry' => 0
];

$stalker_config = [
    'portal'     => 'http://starshare.fun:8080/c',
    'api_path'   => '',
    'mac'        => '00:1A:79:00:00:00', // Replace with valid MAC address
    'sn'         => 'YOUR_SERIAL_NUMBER', // Replace with valid serial number
    'device_id'  => 'YOUR_DEVICE_ID', // Replace with valid device ID
    'possible_endpoints' => [
        '/server/load.php',
        '/stalker_portal/server/load.php',
        '/api/load.php',
        '/c/server/load.php',
        '/stalker_portal/api/load.php',
        '/portal/server/load.php',
        '/stalker/server/load.php',
        '/server/api.php'
    ]
];

// ======== LOGGING FUNCTION ========
function log_message($message) {
    $log_file = __DIR__ . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

// ======== INPUT CHECK ========
$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : exit("Type missing (?type=xtream or ?type=stalker)");
$channel = isset($_GET['ch']) ? trim($_GET['ch']) : (isset($_GET['playlist']) ? null : exit("Channel ID missing (?ch=xxx)"));
$generate_playlist = isset($_GET['playlist']) && $_GET['playlist'] === 'true';

// Add CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, User-Agent');

if (!in_array($type, ['xtream', 'stalker'])) {
    $error_msg = "Invalid type. Use ?type=xtream or ?type=stalker";
    log_message($error_msg);
    exit($error_msg);
}

// ======== cURL REQUEST FUNCTION ========
function make_request($url, $headers = [], $post = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, [
        'Connection: keep-alive',
        'Keep-Alive: timeout=10, max=50'
    ]));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');

    if ($post) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) {
        $error = curl_error($ch);
        $error_msg = "cURL request failed: $url (HTTP $http_code): $error";
        log_message($error_msg);
        curl_close($ch);
        exit($error_msg);
    }

    log_message("cURL request succeeded: $url (HTTP $http_code)");
    curl_close($ch);
    return $response;
}

// ======== STREAM PROXY FUNCTION ========
function proxy_stream($url, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, [
        'Connection: keep-alive',
        'Keep-Alive: timeout=10, max=50'
    ]));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
        echo $data;
        flush();
        return strlen($data);
    });

    header('Content-Type: application/x-mpegURL');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('Access-Control-Allow-Origin: *');

    $result = curl_exec($ch);
    if ($result === false) {
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error_msg = "Stream failed: cURL($url): HTTP $http_code: $error";
        log_message($error_msg);
        curl_close($ch);
        exit($error_msg);
    }

    log_message("Stream proxied successfully: $url");
    curl_close($ch);
}

// ======== HEADER BUILDER ========
function build_headers($type, $config, $token = '') {
    if ($type === 'xtream') {
        return [
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
            "Accept: application/x-mpegURL",
            "Content-Type: application/x-mpegURL"
        ];
    } else {
        return [
            "User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 MAG200 stbapp",
            "Authorization: Bearer $token",
            "Accept: */*",
            "Referer: {$config['portal']}/",
            "X-User-Agent: Model: MAG250; Link: WiFi",
            "Cookie: mac={$config['mac']}; stb_lang=en; timezone=Asia/Kolkata;"
        ];
    }
}

// ======== XTREAM CODES LOGIC ========
function xtream_authenticate(&$config) {
    $auth_url = "{$config['panel_url']}/player_api.php?username={$config['username']}&password={$config['password']}&action=user_info";
    $headers = build_headers('xtream', $config);
    $auth_data = json_decode(make_request($auth_url, $headers), true);
    if (!isset($auth_data['user_info']['auth']) || $auth_data['user_info']['auth'] != 1) {
        $error_msg = "Xtream Codes authentication failed";
        log_message($error_msg);
        exit($error_msg);
    }
    $config['token_expiry'] = time() + 180; // Assume 3-minute validity
    log_message("Xtream Codes authentication successful");
}

function xtream_stream($config, $channel) {
    if (time() >= $config['token_expiry']) {
        xtream_authenticate($config);
    }

    $stream_url = "{$config['panel_url']}/live/{$config['username']}/{$config['password']}/{$channel}.m3u8";
    $headers = build_headers('xtream', $config);
    $stream_response = make_request($stream_url, $headers);
    if (strpos($stream_response, '#EXTM3U') === false) {
        $error_msg = "Xtream Codes stream link fetch failed for channel $channel";
        log_message($error_msg);
        exit($error_msg);
    }

    return $stream_url;
}

function xtream_playlist($config, $restream_base_url) {
    xtream_authenticate($config);

    $channels_url = "{$config['panel_url']}/player_api.php?username={$config['username']}&password={$config['password']}&action=get_live_streams";
    $channels_data = json_decode(make_request($channels_url, build_headers('xtream', $config)), true);
    if (!$channels_data) {
        $error_msg = "Failed to fetch Xtream Codes channel list";
        log_message($error_msg);
        exit($error_msg);
    }

    $m3u = "#EXTM3U\n";
    foreach ($channels_data as $channel) {
        $channel_id = $channel['stream_id'];
        $channel_name = $channel['name'] ?? 'Unknown';
        $stream_url = "{$config['panel_url']}/live/{$config['username']}/{$config['password']}/{$channel_id}.m3u8";
        $m3u .= "#EXTINF:-1,{$channel_name}\n{$stream_url}\n";
    }
    log_message("Xtream Codes playlist generated with " . count($channels_data) . " channels");
    return $m3u;
}

// ======== STALKER PORTAL LOGIC ========
function find_stalker_endpoint($config) {
    foreach ($config['possible_endpoints'] as $endpoint) {
        $url = "{$config['portal']}{$endpoint}?type=stb&action=handshake&JsHttpRequest=1-xml";
        $response = make_request($url, build_headers('stalker', $config));
        $data = json_decode($response, true);
        if (isset($data['js']['token'])) {
            log_message("Valid endpoint found: $endpoint");
            return $endpoint;
        }
        log_message("Endpoint failed: $endpoint");
    }
    $error_msg = "No valid Stalker API endpoint found. Tried: " . implode(', ', $config['possible_endpoints']);
    log_message($error_msg);
    exit($error_msg);
}

function stalker_stream($config, $channel) {
    if (empty($config['api_path'])) {
        $config['api_path'] = find_stalker_endpoint($config);
    }

    $handshake_url = "{$config['portal']}{$config['api_path']}?type=stb&action=handshake&JsHttpRequest=1-xml";
    $handshake_data = json_decode(make_request($handshake_url, build_headers('stalker', $config)), true);
    $token = isset($handshake_data["js"]["token"]) ? $handshake_data["js"]["token"] : exit("Stalker handshake failed");

    $auth_url = "{$config['portal']}{$config['api_path']}?type=stb&action=authorize&JsHttpRequest=1-xml";
    $auth_payload = [
        "mac"       => $config['mac'],
        "sn"        => $config['sn'],
        "device_id" => $config['device_id'],
        "device_id2"=> $config['device_id']
    ];
    $auth_data = json_decode(make_request($auth_url, build_headers('stalker', $config, $token), $auth_payload), true);
    if (!isset($auth_data["js"]["id"])) {
        $error_msg = "Stalker authorization failed";
        log_message($error_msg);
        exit($error_msg);
    }

    $profile_url = "{$config['portal']}{$config['api_path']}?type=stb&action=get_profile&JsHttpRequest=1-xml";
    $profile_data = json_decode(make_request($profile_url, build_headers('stalker', $config, $token)), true);
    if (!isset($profile_data["js"]["id"])) {
        $error_msg = "Stalker profile retrieval failed";
        log_message($error_msg);
        exit($error_msg);
    }

    $stream_url = "{$config['portal']}{$config['api_path']}?type=itv&action=create_link&cmd=ffmpeg%20http://localhost/ch/{$channel}&JsHttpRequest=1-xml";
    $stream_data = json_decode(make_request($stream_url, build_headers('stalker', $config, $token)), true);
    $cmd = isset($stream_data["js"]["cmd"]) ? $stream_data["js"]["cmd"] : exit("Stalker stream link fetch failed");
    log_message("Stalker stream fetched for channel $channel: $cmd");

    return $cmd;
}

function stalker_playlist($config, $restream_base_url) {
    if (empty($config['api_path'])) {
        $config['api_path'] = find_stalker_endpoint($config);
    }

    $handshake_url = "{$config['portal']}{$config['api_path']}?type=stb&action=handshake&JsHttpRequest=1-xml";
    $handshake_data = json_decode(make_request($handshake_url, build_headers('stalker', $config)), true);
    $token = isset($handshake_data["js"]["token"]) ? $handshake_data["js"]["token"] : exit("Stalker handshake failed");

    $auth_url = "{$config['portal']}{$config['api_path']}?type=stb&action=authorize&JsHttpRequest=1-xml";
    $auth_payload = [
        "mac"       => $config['mac'],
        "sn"        => $config['sn'],
        "device_id" => $config['device_id'],
        "device_id2"=> $config['device_id']
    ];
    $auth_data = json_decode(make_request($auth_url, build_headers('stalker', $config, $token), $auth_payload), true);
    if (!isset($auth_data["js"]["id"])) {
        $error_msg = "Stalker authorization failed";
        log_message($error_msg);
        exit($error_msg);
    }

    $channels_url = "{$config['portal']}{$config['api_path']}?type=itv&action=get_all_channels&JsHttpRequest=1-xml";
    $channels_data = json_decode(make_request($channels_url, build_headers('stalker', $config, $token)), true);
    if (!isset($channels_data['js']['data'])) {
        $error_msg = "Failed to fetch Stalker channel list";
        log_message($error_msg);
        exit($error_msg);
    }

    $m3u = "#EXTM3U\n";
    foreach ($channels_data['js']['data'] as $channel) {
        $channel_id = $channel['id'];
        $channel_name = $channel['name'] ?? 'Unknown';
        $stream_url = stalker_stream($config, $channel_id);
        $m3u .= "#EXTINF:-1,{$channel_name}\n{$stream_url}\n";
    }
    log_message("Stalker playlist generated with " . count($channels_data['js']['data']) . " channels");
    return $m3u;
}

// ======== MAIN LOGIC ========
if ($generate_playlist) {
    header('Content-Type: audio/mpegurl');
    header('Content-Disposition: attachment; filename="playlist.m3u"');
    header('Cache-Control: no-cache');
    header('Access-Control-Allow-Origin: *');
    if ($type === 'xtream') {
        echo xtream_playlist($xtream_config, $restream_base_url);
    } else {
        echo stalker_playlist($stalker_config, $restream_base_url);
    }
} else {
    if ($type === 'xtream') {
        $stream_url = xtream_stream($xtream_config, $channel);
        proxy_stream($stream_url, build_headers('xtream', $xtream_config));
    } else {
        $stream_url = stalker_stream($stalker_config, $channel);
        proxy_stream($stream_url, build_headers('stalker', $stalker_config));
    }
}
exit;
?>
