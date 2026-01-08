<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Database connection
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'doghousemarket';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get order ID from URL
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id) {
    // Verify the order belongs to this user and is pending
    $checkQuery = "SELECT * FROM orders WHERE id = $order_id AND user_id = $user_id AND status = 'Pending'";
    $checkResult = $conn->query($checkQuery);
    
    if ($checkResult && $checkResult->num_rows > 0) {
        // Update order status to Cancelled
        $updateQuery = "UPDATE orders SET status = 'Cancelled' WHERE id = $order_id";
        
        if ($conn->query($updateQuery)) {
            $_SESSION['message'] = 'Order cancelled successfully.';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error cancelling order.';
            $_SESSION['message_type'] = 'danger';
        }
    } else {
        $_SESSION['message'] = 'Order not found or cannot be cancelled.';
        $_SESSION['message_type'] = 'warning';
    }
}

header("Location: my_orders.php");
exit;
