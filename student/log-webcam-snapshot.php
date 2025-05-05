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
        if (!isset($data['attempt_id']) || !isset($data['exam_id']) || !isset($data['image_data'])) {
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

        // Extract the base64 encoded image data (remove the header part)
        $imageData = $data['image_data'];
        list($type, $imageData) = explode(';', $imageData);
        list(, $imageData) = explode(',', $imageData);
        $imageData = base64_decode($imageData);
        
        // Create a unique filename
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "webcam_{$data['attempt_id']}_{$timestamp}.jpg";
        $uploadDir = '../uploads/snapshots/';
        
        // Ensure directory exists
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Save the image
        $filePath = $uploadDir . $filename;
        file_put_contents($filePath, $imageData);
        
        // Record the snapshot in the database
        $stmt = $conn->prepare("
            INSERT INTO exam_cheating_snapshots 
            (attempt_id, exam_id, student_id, snapshot_path, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $data['attempt_id'],
            $data['exam_id'],
            $_SESSION['user_id'],
            $filename
        ]);

        if (!$result) {
            throw new Exception('Failed to log snapshot');
        }
        
        echo json_encode(['status' => 'success']);
        
    } catch (Exception $e) {
        error_log("Error logging webcam snapshot: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to log snapshot'
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ]);
} 