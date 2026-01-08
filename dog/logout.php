<?php
/**
 * Dog House Market - Logout Script
 * Handles user session destruction and logout
 */

// Start session
session_start();

// Clear all session variables
$_SESSION = [];

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Remove any remember me cookies
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 3600, '/');
}

// Redirect to the home page
header("Location: index.php");
exit;

// This file doesn't require logo modification as it's only a processing script without UI
