<?php
// ======== INITIAL SETTINGS ========
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(10); // Increased timeout for streaming
date_default_timezone_set('Asia/Kolkata');

// ======== CONFIGURATION ========
// Dynamically generate restream base URL
$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'https';
$host = $_SERVER['HTTP_HOST'];
$script = $_SERVER['SCRIPT_NAME'];
$restream_base_url = "{$scheme}://{$host}{$script}";

$xtream_config = [
    'panel_url' => 'http://starshare.org:8080',
    'username'  => '5477447635355',
    'password'  => '5326643536426'
];

$stalker_config = [
    'portal'     => 'http://filex.me:8080/c/',
    'api_path'   => '',
    'mac'        => '00:1A:79:00:00:00',
    'sn'         => 'A24E172791EEA',
    'device_id'  => 'B4B2D9F2C5DB4998401A39F3FFA46C76F0D352DFBA27D0059359E9B40C9CCF70',
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
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// ======== INPUT CHECK ========
$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : exit("Type missing (?type=xtream or ?type=stalker)");
$channel = isset($_GET['ch']) ? trim($_GET['ch']) : (isset($_GET['playlist']) ? null : exit("Channel ID missing (?ch=xxx)"));
$generate_playlist = isset($_GET['playlist']) && $_GET['playlist'] === 'true';

if (!in_array($type, ['xtream', 'stalker'])) {
    exit("Invalid type. Use ?type=xtream or ?type=stalker");
}

// ======== REQUEST FUNCTION (With cURL) ========
function make_request($url, $headers = [], $post = null, $retries = 2) {
    for ($attempt = 1; $attempt <= $retries; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            // Keep-alive for persistent connections
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_TCP_KEEPALIVE => 1,
            CURLOPT_TCP_KEEPIDLE => 120,
            CURLOPT_TCP_KEEPINTVL => 60
        ]);

        if ($post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $http_code >= 400) {
            log_message("Request attempt $attempt failed: $url - HTTP $http_code - $error");
            if ($attempt === $retries) {
                $error_msg = "Request failed after $retries attempts: $url - HTTP $http_code - $error";
                log_message($error_msg);
                exit($error_msg);
            }
            sleep(1); // Wait before retry
            continue;
        }

        log_message("Request succeeded: $url - HTTP $http_code");
        return $response;
    }
}

// ======== HEADER BUILDER ========
function build_headers($type, $config, $token = '') {
    if ($type === 'xtream') {
        return [
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
            "Accept: */*",
            "Content-Type: application/x-www-form-urlencoded",
            "Connection: keep-alive"
        ];
    } else {
        return [
            "User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 MAG200 stbapp",
            "Authorization: Bearer $token",
            "Accept: */*",
            "Referer: {$config['portal']}/",
            "X-User-Agent: Model: MAG250; Link: WiFi",
            "Cookie: mac={$config['mac']}; stb_lang=en; timezone=Asia/Kolkata;",
            "Connection: keep-alive"
        ];
    }
}

// ======== FIND STALKER ENDPOINT ========
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

// ======== STALKER TOKEN REFRESH ========
function refresh_stalker_token($config) {
    $handshake_url = "{$config['portal']}{$config['api_path']}?type=stb&action=handshake&JsHttpRequest=1-xml";
    $handshake_data = json_decode(make_request($handshake_url, build_headers('stalker', $config)), true);
    if (!isset($handshake_data["js"]["token"])) {
        $error_msg = "Stalker token refresh failed";
        log_message($error_msg);
        exit($error_msg);
    }
    return $handshake_data["js"]["token"];
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
    global $stalker_config;
    // Find valid endpoint if not set
    if (empty($config['api_path'])) {
        $config['api_path'] = find_stalker_endpoint($config);
        $stalker_config['api_path'] = $config['api_path']; // Update global config
    }

    // Step 1: Handshake
    $token = refresh_stalker_token($config);

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

    // Step 4: Fetch Stream Link with retry on token expiration
    $stream_url = "{$config['portal']}{$config['api_path']}?type=itv&action=create_link&cmd=ffmpeg%20http://localhost/ch/{$channel}&JsHttpRequest=1-xml";
    $stream_data = json_decode(make_request($stream_url, build_headers('stalker', $config, $token)), true);
    
    if (!isset($stream_data["js"]["cmd"])) {
        // Try refreshing token and retry once
        $token = refresh_stalker_token($config);
        $stream_data = json_decode(make_request($stream_url, build_headers('stalker', $config, $token)), true);
        if (!isset($stream_data["js"]["cmd"])) {
            $error_msg = "Stalker stream link fetch failed";
            log_message($error_msg);
            exit($error_msg);
        }
    }
    
    $cmd = $stream_data["js"]["cmd"];
    log_message("Stalker stream fetched for channel $channel: $cmd");
    return $cmd;
}

function stalker_playlist($config, $restream_base_url) {
    // Find valid endpoint if not set
    if (empty($config['api_path'])) {
        $config['api_path'] = find_stalker_endpoint($config);
    }

    // Step 1: Handshake
    $token = refresh_stalker_token($config);

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
    // Add cache-control headers to prevent caching issues
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    header("Location: $stream_url");
}
exit;
?>
