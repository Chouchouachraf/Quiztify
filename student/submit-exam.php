<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once 'ExamManager.php';

checkRole('student');

try {
    $conn = getDBConnection();
    $examManager = new ExamManager($conn, $_SESSION['user_id']);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $attemptId = $_POST['attempt_id'] ?? null;
    if (!$attemptId) {
        throw new Exception('No attempt ID provided');
    }

    // Log attempt verification details
    error_log("Verifying attempt ID: " . $attemptId . " for user: " . $_SESSION['user_id']);

    // First check if attempt exists at all
    $checkStmt = $conn->prepare("SELECT id, student_id, is_completed FROM exam_attempts WHERE id = ?");
    $checkStmt->execute([$attemptId]);
    $basicAttemptInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$basicAttemptInfo) {
        throw new Exception('Attempt ID does not exist');
    }

    if ($basicAttemptInfo['student_id'] != $_SESSION['user_id']) {
        throw new Exception('Attempt belongs to different student');
    }

    if ($basicAttemptInfo['is_completed']) {
        throw new Exception('Exam has already been submitted');
    }

    // Verify the attempt and time
    $stmt = $conn->prepare("
        SELECT ea.*, e.duration_minutes, e.end_date
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        WHERE ea.id = ? AND ea.student_id = ? AND ea.is_completed = 0
    ");
    $stmt->execute([$attemptId, $_SESSION['user_id']]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt) {
        throw new Exception('Invalid attempt or exam already submitted');
    }

    // Check if exam has expired
    $now = new DateTime();
    $endDate = new DateTime($attempt['end_date']);
    if ($now > $endDate) {
        throw new Exception('Exam submission period has ended');
    }

    // Check if duration has exceeded (if exam has timer)
    if ($attempt['duration_minutes']) {
        $startTime = new DateTime($attempt['start_time']);
        $timeDiff = $now->diff($startTime);
        $minutesElapsed = ($timeDiff->days * 24 * 60) + ($timeDiff->h * 60) + $timeDiff->i;
        
        if ($minutesElapsed > $attempt['duration_minutes']) {
            throw new Exception('Exam time limit exceeded');
        }
    }

    // Process answers
    $processedQuestions = [];

    if (isset($_POST['answers'])) {
        foreach ($_POST['answers'] as $questionId => $answerValue) {
            if (!in_array($questionId, $processedQuestions)) {
                $examManager->submitAnswer($attemptId, $questionId, $answerValue);
                $processedQuestions[] = $questionId;
            }
        }
    }
    
    if (isset($_POST['paragraph_answers'])) {
        foreach ($_POST['paragraph_answers'] as $questionId => $answerText) {
            if (!in_array($questionId, $processedQuestions)) {
                $examManager->submitAnswer($attemptId, $questionId, $answerText);
                $processedQuestions[] = $questionId;
            }
        }
    }
    
    if (isset($_POST['code_answers'])) {
        foreach ($_POST['code_answers'] as $questionId => $answerText) {
            if (!in_array($questionId, $processedQuestions)) {
                $examManager->submitAnswer($attemptId, $questionId, $answerText);
                $processedQuestions[] = $questionId;
            }
        }
    }

    // Mark the attempt as completed
    $examManager->completeAttempt($attemptId);
    
    // Clear session data
    unset($_SESSION['current_attempt']);
    
    setFlashMessage('success', 'Exam submitted successfully!');
    header('Location: exams.php');
    exit;

} catch (Exception $e) {
    error_log("Error in submit-exam.php: " . $e->getMessage());
    setFlashMessage('error', $e->getMessage());
    header('Location: exams.php');
    exit();
}