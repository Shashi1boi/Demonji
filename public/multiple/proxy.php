<?php
// Universal CORS Proxy for All URLs
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, Range, X-Requested-With");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Max-Age: 86400");
    header("Content-Length: 0");
    exit(0);
}

// Get the full encoded URL from the query string
$raw_url = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
$raw_url = preg_replace('/^url=/', '', $raw_url);
$target_url = urldecode($raw_url);

if (empty($target_url)) {
    http_response_code(400);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'URL parameter is required']));
}

// Validate URL format
if (!filter_var($target_url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Invalid URL format']));
}

// Initialize cURL
$ch = curl_init();
$headers = [];

// Set basic cURL options
curl_setopt_array($ch, [
    CURLOPT_URL => $target_url,
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HEADER => false,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_BUFFERSIZE => 131072, // 128KB chunks
    CURLOPT_NOPROGRESS => false,
    CURLOPT_FAILONERROR => false,
]);

// Forward headers (excluding some sensitive ones)
foreach (getallheaders() as $name => $value) {
    $lower_name = strtolower($name);
    if (!in_array($lower_name, ['host', 'connection', 'expect', 'content-length'])) {
        $headers[] = "$name: $value";
    }
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Handle different request methods
switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
        break;
    case 'PUT':
    case 'DELETE':
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
        break;
    case 'GET':
        // Handle byte range requests for media files
        if (isset($_SERVER['HTTP_RANGE'])) {
            curl_setopt($ch, CURLOPT_RANGE, $_SERVER['HTTP_RANGE']);
        }
        break;
}

// Handle response headers
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header_line) {
    $forward_headers = [
        'content-type',
        'content-length',
        'accept-ranges',
        'content-range',
        'content-disposition',
        'cache-control',
        'last-modified',
        'etag'
    ];
    
    $header_parts = explode(':', $header_line, 2);
    if (count($header_parts) === 2) {
        $header_name = strtolower(trim($header_parts[0]));
        if (in_array($header_name, $forward_headers)) {
            header($header_line);
        }
    }
    return strlen($header_line);
});

// Stream the response directly to output
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) {
    echo $data;
    return strlen($data);
});

// Handle connection abort
curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($resource, $dl_size, $dl, $ul_size, $ul) {
    return connection_aborted() ? 1 : 0;
});

// Execute the request
curl_exec($ch);

// Handle errors
if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    $error_no = curl_errno($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 502;
    
    http_response_code($http_code);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Proxy request failed',
        'curl_error' => $error_msg,
        'curl_errno' => $error_no,
        'http_code' => $http_code,
        'target_url' => $target_url
    ]);
}

curl_close($ch);
?>
