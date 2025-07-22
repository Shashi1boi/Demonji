<?php
// ======== INITIAL SETTINGS ========
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(120); // Increased for long-running streams
date_default_timezone_set('Asia/Kolkata');

// ======== CONFIGURATION ========
// Dynamically generate restream base URL
$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'https';
$host = $_SERVER['HTTP_HOST'];
$script = $_SERVER['SCRIPT_NAME'];
$restream_base_url = "{$scheme}://{$host}{$script}";

$xtream_config = [
    'panel_url' => 'http://filex.tv:8080',
    'username'  => 'Home329',
    'password'  => 'Sohailhome'
];

$stalker_config = [
    'portal'     => 'http://starshare.fun:8080/c',
    'api_path'   => '',
    'mac'        => '00:1A:79:00:00:00', // Replace with valid MAC
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
    ],
    'token'      => '', // Store token for reuse
    'token_expiry' => 0 // Track token expiry time
];

// ======== PERSISTENT STORAGE FOR ENDPOINT ========
$endpoint_file = __DIR__ . '/stalker_endpoint.txt';
function save_endpoint($endpoint) {
    global $endpoint_file;
    file_put_contents($endpoint_file, $endpoint);
}

function load_endpoint() {
    global $endpoint_file;
    return file_exists($endpoint_file) ? file_get_contents($endpoint_file) : '';
}

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
function make_request($url, $headers = [], $post = null, $retries = 3) {
    for ($attempt = 1; $attempt <= $retries; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 20, // Increased timeout
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false, // Consider enabling in production
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_TCP_KEEPALIVE => 1,
            CURLOPT_TCP_KEEPIDLE => 300, // Extended keep-alive
            CURLOPT_TCP_KEEPINTVL => 60,
            CURLOPT_FAILONERROR => true // Fail on HTTP errors (4xx, 5xx)
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
            sleep(2); // Wait before retry
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
            "Connection: keep-alive",
            "Keep-Alive: timeout=300, max=1000"
        ];
    } else {
        return [
            "User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 MAG200 stbapp",
            "Authorization: Bearer $token",
            "Accept: */*",
            "Referer: {$config['portal']}/",
            "X-User-Agent: Model: MAG250; Link: WiFi",
            "Cookie: mac={$config['mac']}; stb_lang=en; timezone=Asia/Kolkata;",
            "Connection: keep-alive",
            "Keep-Alive: timeout=300, max=1000"
        ];
    }
}

// ======== FIND STALKER ENDPOINT ========
function find_stalker_endpoint($config) {
    $saved_endpoint = load_endpoint();
    if (!empty($saved_endpoint)) {
        $url = "{$config['portal']}{$saved_endpoint}?type=stb&action=handshake&JsHttpRequest=1-xml";
        $response = make_request($url, build_headers('stalker', $config));
        $data = json_decode($response, true);
        if (isset($data['js']['token'])) {
            log_message("Using saved endpoint: $saved_endpoint");
            return $saved_endpoint;
        }
    }

    foreach ($config['possible_endpoints'] as $endpoint) {
        $url = "{$config['portal']}{$endpoint}?type=stb&action=handshake&JsHttpRequest=1-xml";
        $response = make_request($url, build_headers('stalker', $config));
        $data = json_decode($response, true);
        if (isset($data['js']['token'])) {
            save_endpoint($endpoint);
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
    global $stalker_config;
    // Check if token is still valid (within 1 hour)
    if (!empty($stalker_config['token']) && $stalker_config['token_expiry'] > time() + 300) {
        log_message("Using cached token");
        return $stalker_config['token'];
    }

    $handshake_url = "{$config['portal']}{$config['api_path']}?type=stb&action=handshake&JsHttpRequest=1-xml";
    $handshake_data = json_decode(make_request($handshake_url, build_headers('stalker', $config)), true);
    if (!isset($handshake_data["js"]["token"])) {
        $error_msg = "Stalker token refresh failed";
        log_message($error_msg);
        exit($error_msg);
    }
    
    $stalker_config['token'] = $handshake_data["js"]["token"];
    $stalker_config['token_expiry'] = time() + 3600; // Assume 1-hour validity
    log_message("New token obtained: {$stalker_config['token']}");
    return $stalker_config['token'];
}

// ======== STREAM HEALTH CHECK ========
function check_stream_health($stream_url) {
    $ch = curl_init($stream_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => true, // HEAD request to check availability
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code >= 200 && $http_code < 400;
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
    if (!check_stream_health($stream_url)) {
        $error_msg = "Xtream Codes stream health check failed: $stream_url";
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
    // Load or find valid endpoint
    if (empty($config['api_path'])) {
        $config['api_path'] = find_stalker_endpoint($config);
        $stalker_config['api_path'] = $config['api_path'];
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
        // Refresh token and retry
        $token = refresh_stalker_token($config);
        $stream_data = json_decode(make_request($stream_url, build_headers('stalker', $config, $token)), true);
        if (!isset($stream_data["js"]["cmd"])) {
            $error_msg = "Stalker stream link fetch failed";
            log_message($error_msg);
            exit($error_msg);
        }
    }
    
    $cmd = $stream_data["js"]["cmd"];
    if (!check_stream_health($cmd)) {
        $error_msg = "Stalker stream health check failed: $cmd";
        log_message($error_msg);
        exit($error_msg);
    }
    
    log_message("Stalker stream fetched for channel $channel: $cmd");
    return $cmd;
}

function stalker_playlist($config, $restream_base_url) {
    global $stalker_config;
    // Load or find valid endpoint
    if (empty($config['api_path'])) {
        $config['api_path'] = find_stalker_endpoint($config);
        $stalker_config['api_path'] = $config['api_path'];
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
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    header("Location: $stream_url");
}
exit;
?>
