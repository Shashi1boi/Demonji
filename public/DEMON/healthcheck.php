<?php
// Fast response to minimize Render's resource usage
header('Cache-Control: no-cache, must-revalidate');
header('Content-Type: text/plain');

// Database/Redis check (optional - remove if not needed)
$db_healthy = true; // Replace with actual checks if using databases

if ($db_healthy) {
    http_response_code(200);
    echo "OK " . date('Y-m-d H:i:s'); // Timestamp for debugging
} else {
    http_response_code(503);
    echo "SERVICE_UNAVAILABLE";
}
?>
