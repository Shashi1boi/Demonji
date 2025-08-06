<?php
// ======== INITIAL SETTINGS ========
error_reporting(0);
set_time_limit(60);
date_default_timezone_set('Asia/Kolkata');

// ======== CONFIGURATION ========
$portal = isset($_GET['portal']) ? rtrim(trim($_GET['portal']), '/') : exit("Portal missing (?portal=url)");
$mac    = isset($_GET['mac']) ? trim($_GET['mac']) : exit("MAC missing (?mac=xx:xx:xx:xx:xx:xx)");
$channel = isset($_GET['ch']) ? trim($_GET['ch']) : null;
$playlist = isset($_GET['playlist']) ? (int)$_GET['playlist'] : 0;

// Generate credentials
$device_id = strtoupper(hash('sha256', $mac));
$sn = str_replace(':', '', $mac) . '0';

// ======== CURL REQUEST FUNCTION ========
function stalker_request($url, $headers = [], $post = null) {
    static $ch = null;
    if ($ch === null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, "gzip,deflate");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    } else {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    return curl_exec($ch);
}

// ======== HEADER BUILDER ========
function build_headers($token, $portal, $mac) {
    $base_url = $portal . '/';
    return [
        "User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 MAG200 stbapp",
        "Authorization: Bearer $token",
        "Accept: */*",
        "Referer: $base_url",
        "X-User-Agent: Model: MAG250; Link: WiFi",
        "Cookie: mac=$mac; stb_lang=en; timezone=Asia/Kolkata;",
    ];
}

// ======== AUTHENTICATION ========
function authenticate($portal, $mac, $sn, $device_id) {
    // Handshake
    $handshake_url = "$portal/server/load.php?type=stb&action=handshake&JsHttpRequest=1-xml";
    $handshake_data = json_decode(stalker_request($handshake_url, build_headers('', $portal, $mac)), true);
    $token = $handshake_data["js"]["token"] ?? exit("Handshake failed");
    
    // Authorization
    $auth_url = "$portal/server/load.php?type=stb&action=authorize&JsHttpRequest=1-xml";
    $auth_payload = [
        "mac"       => $mac,
        "sn"        => $sn,
        "device_id" => $device_id,
        "device_id2"=> $device_id
    ];
    $auth_data = json_decode(stalker_request($auth_url, build_headers($token, $portal, $mac), $auth_payload), true);
    if (!isset($auth_data["js"]["id"])) exit("Authorization failed");
    
    return $token;
}

// ======== GET CHANNEL LIST ========
function get_channel_list($portal, $token, $mac) {
    $url = "$portal/server/load.php?type=itv&action=get_all_channels&JsHttpRequest=1-xml";
    $data = json_decode(stalker_request($url, build_headers($token, $portal, $mac)), true);
    return $data["js"]["data"] ?? exit("Failed to get channel list");
}

// ======== MAIN PROCESS ========
try {
    $token = authenticate($portal, $mac, $sn, $device_id);
    
    // PLAYLIST MODE
    if ($playlist === 1) {
        $channels = get_channel_list($portal, $token, $mac);
        
        header("Content-Type: application/vnd.apple.mpegurl");
        header("Content-Disposition: inline; filename=\"playlist.m3u\"");
        
        echo "#EXTM3U\n";
        foreach ($channels as $ch) {
            $id = $ch['id'];
            $name = htmlspecialchars($ch['name']);
            $logo = $ch['logo'] ?? '';
            
            // Generate URL for this channel
            $params = http_build_query([
                'portal' => $portal,
                'mac' => $mac,
                'ch' => $id
            ]);
            $channel_url = "//{$_SERVER['HTTP_HOST']}{$_SERVER['SCRIPT_NAME']}?$params";
            
            echo "#EXTINF:-1 tvg-id=\"$id\" tvg-name=\"$name\" tvg-logo=\"$logo\", $name\n";
            echo "$channel_url\n\n";
        }
        exit;
    } 
    // SINGLE CHANNEL MODE
    elseif ($channel) {
        // Get profile (required by some portals)
        $profile_url = "$portal/server/load.php?type=stb&action=get_profile&JsHttpRequest=1-xml";
        $profile_data = json_decode(stalker_request($profile_url, build_headers($token, $portal, $mac)), true);
        
        // Get stream URL
        $stream_url = "$portal/server/load.php?type=itv&action=create_link&cmd=ffmpeg%20http://localhost/ch/$channel&JsHttpRequest=1-xml";
        $stream_data = json_decode(stalker_request($stream_url, build_headers($token, $portal, $mac)), true);
        $cmd = $stream_data["js"]["cmd"] ?? exit("Stream link fetch failed");
        
        header("Location: $cmd");
        exit;
    } else {
        exit("Missing channel ID (?ch=xxx) or playlist request (?playlist=1)");
    }
} catch (Exception $e) {
    exit("Error: " . $e->getMessage());
}
?>
