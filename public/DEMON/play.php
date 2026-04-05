<?php
require_once 'config.php';
require_once 'StalkerLite.php';

$serverId = $_GET['server'] ?? '';
$channelId = $_GET['id'] ?? '';
if (!$serverId || !$channelId) {
    http_response_code(400);
    die('Missing server or channel ID');
}

$server = getStalkerServerById($serverId);
if (!$server) {
    http_response_code(400);
    die('Invalid server');
}

$stalker = new StalkerLite($server['url'], $server['mac'], $server['model'] ?? 'MAG250');

// Try to use existing token? We'll just do a quick handshake if needed.
$connect = $stalker->connect();
if (!$connect['success']) {
    http_response_code(503);
    die('Handshake failed');
}

// Get all channels to find the cmd for this ID
$channels = $stalker->getChannels();
$cmd = null;
foreach ($channels as $ch) {
    if ($ch['id'] == $channelId) {
        $cmd = $ch['cmd'];
        break;
    }
}
if (!$cmd) {
    http_response_code(404);
    die('Channel not found');
}

$streamUrl = $stalker->createLink($cmd);
if (!$streamUrl) {
    http_response_code(502);
    die('Could not resolve stream URL');
}

// Simple redirect – no proxy, saves your bandwidth
header('Location: ' . $streamUrl, true, 302);
exit;
?>