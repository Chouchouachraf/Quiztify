<?php
session_start();
require_once 'includes/functions.php';

// Log the logout activity
if (isset($_SESSION['user_id'])) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            INSERT INTO user_activity_logs (
                user_id, 
                activity_type, 
                activity_details
            ) VALUES (?, 'logout', 'User logged out successfully')
        ");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        // Continue with logout even if logging fails
    }
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to logout confirmation page
header('Location: logout-confirmation.php');
exit();