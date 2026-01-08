<?php
/**
 * Health Check Script
 * 
 * This script verifies that the Apache server and PHP are functioning correctly.
 * It performs basic checks and returns useful debugging information.
 */

// Set content type to JSON
header('Content-Type: application/json');

// Start output collection
ob_start();

// Basic server information
$health_data = [
    'status' => 'OK',
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => [
        'software' => $_SERVER['SERVER_SOFTWARE'],
        'name' => $_SERVER['SERVER_NAME'],
        'address' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
        'port' => $_SERVER['SERVER_PORT']
    ],
    'php' => [
        'version' => phpversion(),
        'interface' => php_sapi_name(),
        'extensions' => get_loaded_extensions(),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize')
    ],
    'database' => [
        'status' => 'Unknown'
    ]
];

// Test database connection
try {
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'doghousemarket';
    
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($conn->connect_error) {
        $health_data['database']['status'] = 'Error';
        $health_data['database']['message'] = 'Connection failed: ' . $conn->connect_error;
    } else {
        $health_data['database']['status'] = 'Connected';
        $health_data['database']['server_info'] = $conn->server_info;
        $health_data['database']['client_info'] = $conn->client_info;
        
        // Test query
        $test_query = "SELECT 1 AS test";
        $result = $conn->query($test_query);
        if ($result) {
            $health_data['database']['query_test'] = 'Success';
        } else {
            $health_data['database']['query_test'] = 'Failed';
            $health_data['database']['query_error'] = $conn->error;
        }
        
        $conn->close();
    }
} catch (Exception $e) {
    $health_data['database']['status'] = 'Exception';
    $health_data['database']['message'] = $e->getMessage();
}

// Test file system access
$health_data['filesystem'] = [
    'document_root' => $_SERVER['DOCUMENT_ROOT'],
    'script_filename' => $_SERVER['SCRIPT_FILENAME'],
    'is_writable' => is_writable(__DIR__) ? 'Yes' : 'No'
];

// Check if important directories are writable
$dirs_to_check = ['images', 'images/dogs'];
$dir_status = [];

foreach ($dirs_to_check as $dir) {
    $full_path = __DIR__ . '/' . $dir;
    
    if (!file_exists($full_path)) {
        // Try to create the directory if it doesn't exist
        if (mkdir($full_path, 0755, true)) {
            $dir_status[$dir] = 'Created and writable';
        } else {
            $dir_status[$dir] = 'Could not create directory';
        }
    } else if (is_writable($full_path)) {
        $dir_status[$dir] = 'Exists and writable';
    } else {
        $dir_status[$dir] = 'Exists but not writable';
    }
}

$health_data['filesystem']['directories'] = $dir_status;

// Check for PHP errors
if (function_exists('error_get_last') && $last_error = error_get_last()) {
    $health_data['errors']['last_php_error'] = $last_error;
}

// Output the result
echo json_encode($health_data, JSON_PRETTY_PRINT);

// End output collection and flush
ob_end_flush();
