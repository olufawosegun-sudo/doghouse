<?php
/**
 * Database Connection File for Dog House Market
 * 
 * This file establishes a connection to the MySQL database
 * and provides a global connection object to be used throughout the application.
 */

// Database configuration
define('DB_HOST', 'localhost');     // Database host (usually localhost)
define('DB_USERNAME', 'root');      // Database username
define('DB_PASSWORD', '');          // Database password (empty by default for XAMPP)
define('DB_NAME', 'doghousemarket'); // Database name

// Establish database connection
$conn = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set character set
mysqli_set_charset($conn, "utf8mb4");

// Optional: Set timezone
date_default_timezone_set('UTC');

// Return the connection object
return $conn;
?>
// Return the connection object
return $conn;
?>
