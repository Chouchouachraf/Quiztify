<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkRole('student');

try {
    $conn = getDBConnection();
    
    // Get attempt details
    $stmt = $conn->prepare("
        SELECT ea.*, e.duration_minutes, e.end_date
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        WHERE ea.id = ? AND ea.student_id = ? AND ea.is_completed = 0
    ");
    $stmt->execute([$_POST['attempt_id'], $_SESSION['user_id']]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt) {
        throw new Exception('Invalid attempt');
    }

    // Calculate if time is up
    $startTime = strtotime($attempt['start_time']);
    $timeLimit = $attempt['duration_minutes'] * 60;
    $currentTime = time();
    $timeElapsed = $currentTime - $startTime;
    
    $response = [
        'timeRemaining' => max(0, $timeLimit - $timeElapsed),
        'shouldSubmit' => ($timeElapsed >= $timeLimit)
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 