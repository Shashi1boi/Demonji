<?php
// Enhanced CORS Proxy with File Download Support
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$targetUrl = $_GET['url'] ?? '';
if (empty($targetUrl)) {
    http_response_code(400);
    die(json_encode(['error' => 'URL parameter is required']));
}

// Initialize cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $targetUrl,
    CURLOPT_RETURNTRANSFER => false, // Stream directly to output
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HEADERFUNCTION => function($curl, $header) {
        // Forward specific headers only
        $forwardHeaders = [
            'content-type',
            'content-length',
            'accept-ranges',
            'content-disposition'
        ];
        
        $headerParts = explode(':', $header, 2);
        if (count($headerParts) === 2) {
            $headerName = strtolower(trim($headerParts[0]));
            if (in_array($headerName, $forwardHeaders)) {
                header($header);
            }
        }
        return strlen($header);
    },
    CURLOPT_WRITEFUNCTION => function($curl, $data) {
        echo $data;
        return strlen($data);
    },
    CURLOPT_BUFFERSIZE => 8192, // Stream in 8KB chunks
    CURLOPT_NOPROGRESS => false,
    CURLOPT_PROGRESSFUNCTION => function(
        $resource, $download_size, $downloaded, $upload_size, $uploaded
    ) {
        // Abort if client disconnects
        return connection_aborted() ? 1 : 0;
    }
]);

// Set request method
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
} else {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
}

// Execute and close
curl_exec($ch);
$error = curl_error($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!empty($error)) {
    http_response_code(500);
    die(json_encode(['error' => $error]));
}

// If we got here, the transfer completed
http_response_code($status);
?>
