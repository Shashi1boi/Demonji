<?php
$stalkerServers = [
    [
        'id'    => 'server1',
        'name'  => 'My Portal',
        'url'   => 'http://alpha-2ott.me/c/',
        'mac'   => '00:1A:79:84:DF:02',
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
