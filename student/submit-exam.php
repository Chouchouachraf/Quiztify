<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once 'ExamManager.php';
checkRole('student');

$conn = getDBConnection();
$examManager = new ExamManager($conn, $_SESSION['user_id']);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $attemptId = $_POST['attempt_id'] ?? null;
    if (!$attemptId) {
        throw new Exception('No attempt ID provided');
    }

    // Get the attempt details to verify it belongs to the current student
    $stmt = $conn->prepare("
        SELECT ea.*, e.id as exam_id, e.title
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        WHERE ea.id = ? AND ea.student_id = ? AND ea.is_completed = 0
    ");
    $stmt->execute([$attemptId, $_SESSION['user_id']]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt) {
        throw new Exception('Invalid attempt or already completed');
    }

    // Start transaction
    $conn->beginTransaction();

    // Save answers
    foreach ($_POST['answers'] as $questionId => $answer) {
        if (is_array($answer)) {
            // For MCQ or True/False questions
            $selectedOptionId = $answer;
            $answerText = null;
            
            // Check if the answer is correct
            $stmt = $conn->prepare("
                SELECT is_correct 
                FROM mcq_options 
                WHERE id = ? AND question_id = ?
            ");
            $stmt->execute([$selectedOptionId, $questionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $isCorrect = $result ? $result['is_correct'] : 0;
            
            // Get question points
            $stmt = $conn->prepare("SELECT points FROM questions WHERE id = ?");
            $stmt->execute([$questionId]);
            $question = $stmt->fetch(PDO::FETCH_ASSOC);
            $pointsEarned = $isCorrect ? $question['points'] : 0;
            
        } else {
            // For open-ended questions
            $selectedOptionId = null;
            $answerText = $answer;
            $isCorrect = null; // Will be graded by teacher
            $pointsEarned = 0;
        }

        $stmt = $conn->prepare("
            INSERT INTO student_answers 
            (attempt_id, question_id, selected_option_id, answer_text, is_correct, points_earned)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$attemptId, $questionId, $selectedOptionId, $answerText, $isCorrect, $pointsEarned]);
    }

    // Mark attempt as completed
    $stmt = $conn->prepare("
        UPDATE exam_attempts 
        SET is_completed = 1, 
            end_time = CURRENT_TIMESTAMP,
            score = (
                SELECT (COALESCE(SUM(sa.points_earned), 0) / SUM(q.points)) * 100
                FROM questions q
                LEFT JOIN student_answers sa ON sa.question_id = q.id AND sa.attempt_id = ?
                WHERE q.exam_id = ?
            )
        WHERE id = ?
    ");
    $stmt->execute([$attemptId, $attempt['exam_id'], $attemptId]);

    $conn->commit();

    // Clear session data
    unset($_SESSION['current_attempt']);
    
    setFlashMessage('success', 'Exam submitted successfully!');
    header("Location: exam-confirmation.php?exam_id=" . $attempt['exam_id']);
    exit;

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error in submit-exam.php: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while submitting your exam. Please try again.');
    header("Location: take-exam.php?id=" . $attempt['exam_id']);
    exit;
}
?>