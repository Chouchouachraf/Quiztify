<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkRole('student');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get and validate the JSON data
        $jsonData = file_get_contents('php://input');
        if (!$jsonData) {
            throw new Exception('No data received');
        }

        $data = json_decode($jsonData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON data');
        }

        // Validate required fields
        if (!isset($data['attempt_id']) || !isset($data['exam_id']) || !isset($data['type'])) {
            throw new Exception('Missing required fields');
        }

        $conn = getDBConnection();
        
        // Verify that this is a valid attempt for this student
        $stmt = $conn->prepare("
            SELECT 1 FROM exam_attempts 
            WHERE id = ? AND exam_id = ? AND student_id = ? AND is_completed = 0
        ");
        $stmt->execute([$data['attempt_id'], $data['exam_id'], $_SESSION['user_id']]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Invalid attempt');
        }

        // Log the cheating attempt
        $stmt = $conn->prepare("
            INSERT INTO exam_attempt_logs 
            (attempt_id, exam_id, student_id, event_type, details, created_at)
            VALUES (?, ?, ?, 'cheating_attempt', ?, NOW())
        ");
        
        $result = $stmt->execute([
            $data['attempt_id'],
            $data['exam_id'],
            $_SESSION['user_id'],
            $data['type']
        ]);

        if (!$result) {
            throw new Exception('Failed to log attempt');
        }
        
        echo json_encode(['status' => 'success']);
        
    } catch (Exception $e) {
        error_log("Error logging cheating attempt: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to log attempt'
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ]);
} 