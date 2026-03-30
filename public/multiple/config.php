<?php
// Configuration file for multiple Xtream Codes credentials
$xtreamCredentials = [
    [
        'id' => 'server1',
        'name' => 'Server 1 (new)',
        'host' => 'http://31.43.191.125:8080',
        'username' => 'VIP014391751994706511',
        'password' => 'b425720d22ff'
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
         'host' => 'http://filex.me:8080',
         'username' => 'Ghosia104',
         'password' => 'pathan104'
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
