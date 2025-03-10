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

    // Verify the attempt and time
    $stmt = $conn->prepare("
        SELECT ea.*, e.duration_minutes
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        WHERE ea.id = ? AND ea.student_id = ? AND ea.is_completed = 0
    ");
    $stmt->execute([$attemptId, $_SESSION['user_id']]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt) {
        throw new Exception('Invalid attempt or exam already submitted');
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
?>