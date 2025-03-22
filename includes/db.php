<?php
require_once __DIR__ . '/config.php';

function getDBConnection() {
    static $conn;
    if ($conn === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Test the connection
            $conn->query('SELECT 1');
            return $conn;

        } catch (PDOException $e) {
            // Log the actual error for debugging
            error_log("Database Connection Error: " . $e->getMessage());
            
            // Show a user-friendly message
            throw new Exception("Database connection failed. Please try again later.");
        }
    }
    return $conn;
}

// Test function to verify database connection
function testDatabaseConnection() {
    try {
        $conn = getDBConnection();
        echo "Database connection successful!";
        return true;
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
        return false;
    }
}