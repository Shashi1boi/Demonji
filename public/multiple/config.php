<?php
// Configuration file for multiple Xtream Codes credentials
$xtreamCredentials = [
    [
        'id' => 'server1',
        'name' => 'Server 1 (Filex TV)',
        'host' => 'http://filex.tv:8080',
        'username' => 'Home329',
        'password' => 'Sohailhome'
    ],
    // Add more servers as needed
    // [
    //     'id' => 'server2',
    //     'name' => 'Server 2 (Example TV)',
    //     'host' => 'http://example.tv:8080',
    //     'username' => 'user2',
    //     'password' => 'pass2'
    // ]
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
