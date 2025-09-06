<?php
// Configuration file for multiple Xtream Codes credentials
$xtreamCredentials = [
    [
        'id' => 'server1',
        'name' => 'Server 1 (1)',
        'host' => 'http://royalibox.com:80',
        'username' => 'm7t7u5p96o',
        'password' => 'g4xb6pbb1r'
    ],
    [
        'id' => 'server2',
        'name' => 'Server 2 (New)',
        'host' => 'http://goldenpro.xyz:80', // Replace with actual server URL
        'username' => '51GYZ3Q',             // Replace with actual username
        'password' => 'G0N5T14'              // Replace with actual password
    ],
    
    [
         'id' => 'server3',
         'name' => 'Server 3 (Another TV)',
         'host' => 'http://vocotv.pro',
         'username' => 'n3ketPpxs',
         'password' => '88237138'
     ],
    [
        'id' => 'server4',
        'name' => 'Server 4 (mexamo)',
        'host' => 'http://fprvjetz.mexamo.xyz:80', // Replace with actual server URL
        'username' => 'B35DKUNA',             // Replace with actual username
        'password' => 'HX3WLEH7'              // Replace with actual password
    ],
];

// Function to get credentials by server ID
function getCredentialsById($serverId) {
    global $xtreamCredentials;
    foreach ($xtreamCredentials as $cred) {
        if ($cred['id'] === $serverId) {
            return $cred;
        }
    }
    return null; // Return null if no matching server is found
}
?>
