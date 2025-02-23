<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkRole('student');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $exam_id = $data['exam_id'] ?? null;
        $attempt_id = $data['attempt_id'] ?? null;

        if (!$exam_id || !$attempt_id) {
            throw new Exception('Invalid request');
        }

        $conn = getDBConnection();
        
        // Update attempt start time
        $stmt = $conn->prepare("
            UPDATE exam_attempts 
            SET start_time = NOW() 
            WHERE id = ? AND student_id = ? AND is_completed = 0
        ");
        $stmt->execute([$attempt_id, $_SESSION['user_id']]);

        // Mark exam as started in session
        $_SESSION['exam_started'] = true;
        
        $response['success'] = true;
    } catch (Exception $e) {
        error_log("Error starting exam: " . $e->getMessage());
        $response['error'] = $e->getMessage();
    }
}

header('Content-Type: application/json');
echo json_encode($response); 