<?php
require_once 'session.php';
require_once 'config.php';
require_once 'functions.php';

// Only allow admins to run this script
checkRole('admin');

try {
    $conn = getDBConnection();
    
    // Begin a transaction for safety
    $conn->beginTransaction();
    
    // Create the exam_attempt_logs table if it doesn't exist
    $conn->exec("
        CREATE TABLE IF NOT EXISTS exam_attempt_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            attempt_id INT NOT NULL,
            exam_id INT NOT NULL,
            student_id INT NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            details TEXT,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
            FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (event_type),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create the exam_cheating_snapshots table if it doesn't exist
    $conn->exec("
        CREATE TABLE IF NOT EXISTS exam_cheating_snapshots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            attempt_id INT NOT NULL,
            exam_id INT NOT NULL,
            student_id INT NOT NULL,
            snapshot_path VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
            FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Update the exam_attempts table to add a violations counter if needed
    $conn->exec("
        ALTER TABLE exam_attempts 
        ADD COLUMN IF NOT EXISTS violations INT NOT NULL DEFAULT 0
    ");
    
    // Commit the transaction
    $conn->commit();
    
    echo "Database tables for cheating detection have been successfully updated.";
    
} catch (PDOException $e) {
    // Roll back the transaction if something failed
    if ($conn) {
        $conn->rollBack();
    }
    echo "Error: " . $e->getMessage();
} 