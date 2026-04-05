<?php
$stalkerServers = [
    [
        'id'    => 'server1',
        'name'  => 'My Portal',
        'url'   => 'http://portal.elite4k.co/stalker_portal/c/',
        'mac'   => '00:1A:79:55:89:12',
    ],
    [
        'id'    => 'server2',
        'name'  => 'Another Portal',
        'url'   => 'http://another.com/c/',
        'mac'   => '00:1A:79:11:22:33',
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
