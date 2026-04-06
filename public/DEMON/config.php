<?php
$stalkerServers = [
    [
        'id'    => 'server1',
        'name'  => 'My Portal',
        'url'   => 'http://new.jiotv.be/stalker_portal/c/',
        'mac'   => '00:1A:79:C4:A4:F8',
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
