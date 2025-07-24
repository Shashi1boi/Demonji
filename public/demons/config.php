<?php
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

// Get credentials from query parameters
$sanitize = function ($input) {
    return htmlspecialchars(trim($input));
};

$url = $sanitize($_GET['url'] ?? '');
$mac = $sanitize($_GET['mac'] ?? '');
$sn = $sanitize($_GET['sn'] ?? '');
$device_id_1 = $sanitize($_GET['device_id_1'] ?? '');
$device_id_2 = $sanitize($_GET['device_id_2'] ?? '');
$sig = $sanitize($_GET['sig'] ?? '');

$api = "263";
$host = parse_url($url)["host"];

// Handshake
function handshake($host) { 
    $Xurl = "http://$host/stalker_portal/server/load.php?type=stb&action=handshake&token=&JsHttpRequest=1-xml";
    $HED = [
        'User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG200 stbapp ver: 2 rev: 250 Safari/533.3',
        'Connection: Keep-Alive',
        'Accept-Encoding: gzip',
        'X-User-Agent: Model: MAG250; Link: WiFi',
        "Referer: http://$host/stalker_portal/c/",
        "Host: $host",
        "Connection: Keep-Alive",
    ];
    $Info_Data = Info($Xurl, $HED, '');
    $Info_Status = $Info_Data["Info_arr"]["info"];
    $Info_Data = $Info_Data["Info_arr"]["data"];
    $Info_Data_Json = json_decode($Info_Data, true);
    $Info_Encode = array(
        "Info_arr" => array(
            "token" => $Info_Data_Json["js"]["token"] ?? '',
            "random" => $Info_Data_Json["js"]["random"] ?? '',
            "Status Code" => $Info_Status
        )
    );
    return $Info_Encode;
}

// Generate Token
function generate_token($host, $mac, $sn, $device_id_1, $device_id_2, $sig) {
    $Info_Decode = handshake($host);
    $Bearer_token = $Info_Decode["Info_arr"]["token"];
    if (empty($Bearer_token)) {
        die("Error: Failed to generate token.");
    }
    $Bearer_token = re_generate_token($Bearer_token, $host);
    $Bearer_token = $Bearer_token["Info_arr"]["token"];
    get_profile($Bearer_token, $host, $mac, $sn, $device_id_1, $device_id_2, $sig);
    return $Bearer_token;
}

// Re Generate Token
function re_generate_token($Bearer_token, $host) {
    $Xurl = "http://$host/stalker_portal/server/load.php?type=stb&action=handshake&token=$Bearer_token&JsHttpRequest=1-xml";
    $HED = [
        'User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG200 stbapp ver: 2 rev: 250 Safari/533.3',
        'Connection: Keep-Alive',
        'Accept-Encoding: gzip',
        'X-User-Agent: Model: MAG250; Link: WiFi',
        "Referer: http://$host/stalker_portal/c/",
        "Host: $host",
        "Connection: Keep-Alive",
    ];
    $Info_Data = Info($Xurl, $HED, '');
    $Info_Data = $Info_Data["Info_arr"]["data"];
    $Info_Data_Json = json_decode($Info_Data, true);
    $Info_Encode = array(
        "Info_arr" => array(
            "token" => $Info_Data_Json["js"]["token"] ?? '',
            "random" => $Info_Data_Json["js"]["random"] ?? ''
        )
    );
    return $Info_Encode;
}

// Get Profile
function get_profile($Bearer_token, $host, $mac, $sn, $device_id_1, $device_id_2, $sig) {
    global $api;
    $timestamp = time();
    $Info_Decode = handshake($host);
    $Info_Decode_Random = $Info_Decode["Info_arr"]["random"];
    $Xurl = "http://$host/stalker_portal/server/load.php?type=stb&action=get_profile&hd=1&ver=ImageDescription%3A+0.2.18-r14-pub-250%3B+ImageDate%3A+Fri+Jan+15+15%3A20%3A44+EET+2016%3B+PORTAL+version%3A+5.1.0%3B+API+Version%3A+JS+API+version%3A+328%3B+STB+API+version%3A+134%3B+Player+Engine+version%3A+0x566&num_banks=2&sn=$sn&stb_type=MAG250&image_version=218&video_out=hdmi&device_id=$device_id_1&device_id2=$device_id_2&signature=$sig&auth_second_step=1&hw_version=1.7-BD-00¬_valid_token=0&client_type=STB&hw_version_2=08e10744513ba2b4847402b6718c0eae×tamp=$timestamp&api_signature=$api&metrics=%7B%22mac%22%3A%22$mac%22%2C%22sn%22%3A%22$sn%22%2C%22model%22%3A%22MAG250%22%2C%22type%22%3A%22STB%22%2C%22uid%22%3A%22%22%2C%22random%22%3A%22$Info_Decode_Random%22%7D&JsHttpRequest=1-xml";
    $HED = [
        'User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG200 stbapp ver: 2 rev: 250 Safari/533.3',
        'Connection: Keep-Alive',
        'Accept-Encoding: gzip',
        'X-User-Agent: Model: MAG250; Link: WiFi',
        "Referer: http://$host/stalker_portal/c/",
        "Authorization: Bearer $Bearer_token",
        "Host: $host",
        "Connection: Keep-Alive",
    ];
    Info($Xurl, $HED, $mac);
}

// Info
function Info($Xurl, $HED, $mac) {
    $cURL_Info = curl_init();
    curl_setopt_array($cURL_Info, [
        CURLOPT_URL => $Xurl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => 'gzip',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_COOKIE => "mac=$mac; stb_lang=en; timezone=GMT",
        CURLOPT_HTTPHEADER => $HED,
    ]);
    $Info_Data = curl_exec($cURL_Info);
    $Info_Status = curl_getinfo($cURL_Info);
    curl_close($cURL_Info);
    $Info_Encode = array(
        "Info_arr" => array(
            "data" => $Info_Data,
            "info" => $Info_Status,
        )
    );
    return $Info_Encode;
}

// Get Groups
function group_title($host, $mac, $sn, $device_id_1, $device_id_2, $sig, $all = false) {
    $group_title_url = "http://$host/stalker_portal/server/load.php?type=itv&action=get_genres&JsHttpRequest=1-xml";
    $headers = [
        "User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG200 stbapp ver: 2 rev: 250 Safari/533.3",
        "Authorization: Bearer " . generate_token($host, $mac, $sn, $device_id_1, $device_id_2, $sig),
        "X-User-Agent: Model: MAG250; Link: WiFi",
        "Referer: http://$host/stalker_portal/c/",
        "Accept: */*",
        "Host: $host",
        "Connection: Keep-Alive",
        "Accept-Encoding: gzip",
    ];

    $response = Info($group_title_url, $headers, $mac);
    $json_api_data = json_decode($response["Info_arr"]["data"], true);
    
    if (!isset($json_api_data["js"]) || !is_array($json_api_data["js"])) {
        return [];
    }

    $filtered_data = [];
    foreach ($json_api_data["js"] as $genre) {
        if ($genre['id'] === "*") {
            continue;
        }
        $filtered_data[$genre['id']] = $genre['title'];
    }

    return $filtered_data;
}

// AJAX endpoint for groups
if (isset($_GET['action']) && $_GET['action'] === 'get_groups') {
    if (empty($url) || empty($mac) || empty($sn) || empty($device_id_1) || empty($device_id_2)) {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(group_title($host, $mac, $sn, $device_id_1, $device_id_2, $sig, true));
    exit;
}
?>
