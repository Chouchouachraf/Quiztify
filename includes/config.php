<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'quiztifydatabase');  // Make sure this matches your database name
define('DB_USER', 'root');           
define('DB_PASS', '');                

// Site configuration
define('SITE_NAME', 'Quiztify');
define('BASE_URL', 'http://localhost/quiztify');

// Time zone
date_default_timezone_set('UTC');