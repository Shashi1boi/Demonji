<?php
// Simple CORS Proxy Script in PHP

// Allow from any origin
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Get the target URL from the query parameter
$targetUrl = isset($_GET['url']) ? $_GET['url'] : '';

if (empty($targetUrl)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing URL parameter']);
    exit;
}

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// Forward headers (optional)
$headers = [];
foreach (getallheaders() as $name => $value) {
    if (strtolower($name) === 'host') continue;
    $headers[] = "$name: $value";
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Forward the request method
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);

// Forward POST data if present
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    $postData = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
}

// Execute the request
$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for errors
if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(['error' => 'Proxy error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

// Forward the status code
http_response_code($statusCode);

// Forward the content type
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
if ($contentType) {
    header("Content-Type: $contentType");
}

// Output the response
echo $response;

// Close cURL
curl_close($ch);
?>
