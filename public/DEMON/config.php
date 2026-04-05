<?php
// Stalker servers – add as many as you want
$stalkerServers = [
    [
        'id'    => 'server1',
        'name'  => 'My Stalker Portal',
        'url'   => 'http://your-portal.com/stalker_portal/c/',
        'mac'   => '00:1A:79:AA:BB:CC',
        'model' => 'MAG250'
    ],
    [
        'id'    => 'server2',
        'name'  => 'Another Portal',
        'url'   => 'http://another.com/c/',
        'mac'   => '00:1A:79:11:22:33',
        'model' => 'MAG254'
    ],
];

function getStalkerServerById($id) {
    global $stalkerServers;
    foreach ($stalkerServers as $s) {
        if ($s['id'] === $id) return $s;
    }
    return null;
}
?>