<?php
// Configuration file for multiple Xtream Codes credentials
$xtreamCredentials = [
    [
        'id' => 'server1',
        'name' => 'Server 1 (Filex tv)',
        'host' => 'http://filex.me:8080',
        'username' => '000003',
        'password' => '000003'
    ],
    [
        'id' => 'server2',
        'name' => 'Server 2 (New)',
        'host' => 'http://rfye55.xyz', // Replace with actual server URL
        'username' => '98:06:3c:98:da:fa',             // Replace with actual username
        'password' => '881A863F2BD7'              // Replace with actual password
    ],
    
    [
         'id' => 'server3',
         'name' => 'Server 3 (Another TV)',
         'host' => 'http://newton68769.cdngold.me:80',
         'username' => '84e409ccbe',
         'password' => 'a2d7b7b506'
     ],
    [
        'id' => 'server4',
        'name' => 'Server 4 (Zamlb)',
        'host' => 'http://zamlb.co.uk', // Replace with actual server URL
        'username' => 'Fraser123',             // Replace with actual username
        'password' => 'Fraser123'              // Replace with actual password
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
