<?php
// Configuration file for multiple Xtream Codes credentials
$xtreamCredentials = [
    [
        'id' => 'server1',
        'name' => 'Server 1 (Filex TV)',
        'host' => 'http://starshare.org:8080',
        'username' => 'Premium51',
        'password' => 'Sad41'
    ],
    [
        'id' => 'server2',
        'name' => 'Server 2 (Example TV)',
        'host' => 'http://example.tv:8080', // Replace with actual server URL
        'username' => 'user2',             // Replace with actual username
        'password' => 'pass2'              // Replace with actual password
    ],
    // Add more servers as needed
    // [
    //     'id' => 'server3',
    //     'name' => 'Server 3 (Another TV)',
    //     'host' => 'http://another.tv:8080',
    //     'username' => 'user3',
    //     'password' => 'pass3'
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
