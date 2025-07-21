<?php
// ======== INITIAL SETTINGS ========
error_reporting(E_ALL); // Enable error reporting for debugging
ini_set('display_errors', 1);
set_time_limit(30);
date_default_timezone_set('Asia/Kolkata');

// ======== CONFIGURATION ========
// Dynamically generate restream base URL
$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'https';
$host = $_SERVER['HTTP_HOST'];
$script = $_SERVER['SCRIPT_NAME'];
$restream_base_url = "{$scheme}://{$host}{$script}";

$xtream_config = [
    'panel_url' => 'http://filex.me:8080', // Replace with your Xtream Codes panel URL
    'username'  => 'Home329', // Replace with your Xtream Codes username
    'password'  => 'Sohailhome'  // Replace with your Xtream Codes password
];

$stalker_config = [
    'portal'     => 'http://starshare.fun:8080/c', // Base portal URL
    'api_path'   => '', // Will be set dynamically after testing endpoints
    'mac'        => '00:1A:79:00:00:00', // Replace with your valid MAC address
    'sn'         => 'YOUR_SERIAL_NUMBER', // Replace with your valid serial number
    'device_id'  => 'YOUR_DEVICE_ID', // Replace with your valid device ID
    'possible_endpoints' => [ // Expanded list of possible Stalker API endpoints
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
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// ======== INPUT CHECK ========
$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : exit("Type missing (?type=xtream or ?type=stalker)");
$channel = isset($_GET['ch']) ? trim($_GET['ch']) : (isset($_GET['playlist']) ? null : exit("Channel ID missing (?ch=xxx)"));
$generate_playlist = isset($_GET['playlist']) && $_GET['playlist'] === 'true';

if (!in_array($type, ['xtream', 'stalker'])) {
    exit("Invalid type. Use ?type=xtream or ?type=stalker");
}

// ======== REQUEST FUNCTION (Without cURL) ========
function make_request($url, $headers = [], $post = null) {
    $options = [
        'http' => [
            'method'  => $post ? 'POST' : 'GET',
            'header'  => implode("\r\n", $headers),
            'content' => $post ? http_build_query($post) : '',
            'follow_location' => 1,
            'max_redirects'  => 5,
            'timeout' => 10
        ]
    ];
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        $error = error_get_last();
        $error_msg = "Request failed: file_get_contents($url): " . ($error['message'] ?? 'Unknown error');
        log_message($error_msg);
        exit($error_msg);
    }
    log_message("Request succeeded: $url");
    return $response;
}

// ======== HEADER BUILDER ========
function build_headers($type, $config, $token = '') {
    if ($type === 'xtream') {
        return [
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
            "Accept: */*",
            "Content-Type: application/x-www-form-urlencoded"
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

// ======== FIND STALKER ENDPOINT ========
function find_stalker_endpoint($config) {
    foreach ($config['possible_endpoints'] as $endpoint) {
        $url = "{$config['portal']}{$endpoint}?type=stb&action=handshake&JsHttpRequest=1-xml";
        $headers = build_headers('stalker', $config);
        $response = @file_get_contents($url, false, stream_context_create(['http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'follow_location' => 1,
            'max_redirects' => 5,
            'timeout' => 10
        ]]));
        if ($response !== false) {
            $data = json_decode($response, true);
            if (isset($data['js']['token'])) {
                log_message("Valid endpoint found: $endpoint");
                return $endpoint;
            }
        }
        log_message("Endpoint failed: $endpoint");
    }
    $error_msg = "No valid Stalker API endpoint found. Tried: " . implode(', ', $config['possible_endpoints']);
    log_message($error_msg);
    exit($error_msg);
}

// ======== XTREAM CODES LOGIC ========
function xtream_stream($config, $channel) {
    $auth_url = "{$config['panel_url']}/player_api.php?username={$config['username']}&password={$config['password']}&action=user_info";
    $auth_data = json_decode(make_request($auth_url, build_headers('xtream', $config)), true);
    if (!isset($auth_data['user_info']['auth']) || $auth_data['user_info']['auth'] != 1) {
        $error_msg = "Xtream Codes authentication failed";
        log_message($error_msg);
        exit($error_msg);
    }

    $stream_url = "{$config['panel_url']}/live/{$config['username']}/{$config['password']}/{$channel}.m3u8";
    $headers = build_headers('xtream', $config);
    $stream_response = make_request($stream_url, $headers);
    if (strpos($stream_response, '#EXTM3U') === false) {
        $error_msg = "Xtream Codes stream link fetch failed";
        log_message($error_msg);
        exit($error_msg);
    }

    return $stream_url;
}

function xtream_playlist($config, $restream_base_url) {
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
        $stream_url = "{$restream_base_url}?type=xtream&ch={$channel_id}";
        $m3u .= "#EXTINF:-1,{$channel_name}\n{$stream_url}\n";
    }
    log_message("Xtream Codes playlist generated with " . count($channels_data) . " channels");
    return $m3u;
}

// ======== STALKER PORTAL LOGIC ========
function stalker_stream($config, $channel) {
    // Find valid endpoint if not set
    if (empty($config['api_path'])) {
        $config['api_path'] = find_stalker_endpoint($config);
    }

    // Step 1: Handshake
    $handshake_url = "{$config['portal']}{$config['api_path']}?type=stb&action=handshake&JsHttpRequest=1-xml";
    $handshake_data = json_decode(make_request($handshake_url, build_headers('stalker', $config)), true);
    $token = isset($handshake_data["js"]["token"]) ? $handshake_data["js"]["token"] : exit("Stalker handshake failed");

    // Step 2: Authorize
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

    // Step 3: Get Profile
    $profile_url = "{$config['portal']}{$config['api_path']}?type=stb&action=get_profile&JsHttpRequest=1-xml";
    $profile_data = json_decode(make_request($profile_url, build_headers('stalker', $config, $token)), true);
    if (!isset($profile_data["js"]["id"])) {
        $error_msg = "Stalker profile retrieval failed";
        log_message($error_msg);
        exit($error_msg);
    }

    // Step 4: Fetch Stream Link
    $stream_url = "{$config['portal']}{$config['api_path']}?type=itv&action=create_link&cmd=ffmpeg%20http://localhost/ch/{$channel}&JsHttpRequest=1-xml";
    $stream_data = json_decode(make_request($stream_url, build_headers('stalker', $config, $token)), true);
    $cmd = isset($stream_data["js"]["cmd"]) ? $stream_data["js"]["cmd"] : exit("Stalker stream link fetch failed");
    log_message("Stalker stream fetched for channel $channel: $cmd");

    return $cmd;
}

function stalker_playlist($config, $restream_base_url) {
    // Find valid endpoint if not set
    if (empty($config['api_path'])) {
        $config['api_path'] = find_stalker_endpoint($config);
    }

    // Step 1: Handshake
    $handshake_url = "{$config['portal']}{$config['api_path']}?type=stb&action=handshake&JsHttpRequest=1-xml";
    $handshake_data = json_decode(make_request($handshake_url, build_headers('stalker', $config)), true);
    $token = isset($handshake_data["js"]["token"]) ? $handshake_data["js"]["token"] : exit("Stalker handshake failed");

    // Step 2: Authorize
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

    // Step 3: Fetch Channels
    $channels_url = "{$config['portal']}{$config['api_path']}?type=itv&action=get_all_channels&JsHttpRequest=1-xml";
    $channels_data = json_decode(make_request($channels_url, build_headers('stalker', $config, $token)), true);
    if (!isset($channels_data['js']['data'])) {
        $error_msg = "Failed to fetch Stalker channel list";
        log_message($error_msg);
        exit($error_msg);
    }

    // Generate M3U playlist with restream URLs
    $m3u = "#EXTM3U\n";
    foreach ($channels_data['js']['data'] as $channel) {
        $channel_id = $channel['id'];
        $channel_name = $channel['name'] ?? 'Unknown';
        $stream_url = "{$restream_base_url}?type=stalker&ch={$channel_id}";
        $m3u .= "#EXTINF:-1,{$channel_name}\n{$stream_url}\n";
    }
    log_message("Stalker playlist generated with " . count($channels_data['js']['data']) . " channels");
    return $m3u;
}

// ======== MAIN LOGIC ========
if ($generate_playlist) {
    header('Content-Type: audio/mpegurl');
    header('Content-Disposition: attachment; filename="playlist.m3u"');
    if ($type === 'xtream') {
        echo xtream_playlist($xtream_config, $restream_base_url);
    } else {
        echo stalker_playlist($stalker_config, $restream_base_url);
    }
} else {
    if ($type === 'xtream') {
        $stream_url = xtream_stream($xtream_config, $channel);
    } else {
        $stream_url = stalker_stream($stalker_config, $channel);
    }
    log_message("Redirecting to stream URL: $stream_url");
    header("Location: $stream_url");
}
exit;
?>
