<?php
// Configuration file for multiple Xtream Codes credentials
$xtreamCredentials = [
    [
        'id' => 'server1',
        'name' => 'Server 1 (1)',
        'host' => 'http://agh2019.xyz:80',
        'username' => 'SOFIANBENAISSA',
        'password' => 'X7KJL94'
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
         'host' => 'http://newton68769.cdngold.me:80',
         'username' => '84e409ccbe',
         'password' => 'a2d7b7b506'
     ],
    [
        'id' => 'server4',
        'name' => 'Server 4 (sawai)',
        'host' => 'http://sawaiptv.vip', // Replace with actual server URL
        'username' => 'drahme1101',             // Replace with actual username
        'password' => 'edweeryqokh65429'              // Replace with actual password
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
