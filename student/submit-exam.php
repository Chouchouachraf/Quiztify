<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
checkRole('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: available-exams.php');
    exit;
}

$conn = getDBConnection();
$examId = filter_input(INPUT_POST, 'exam_id', FILTER_VALIDATE_INT);
$answers = $_POST['answers'] ?? [];
$studentId = $_SESSION['user_id'];

try {
    // Start transaction
    $conn->beginTransaction();

    // Get exam details
    $stmt = $conn->prepare("
        SELECT e.*, u.email as teacher_email, u.full_name as teacher_name 
        FROM exams e 
        JOIN users u ON e.created_by = u.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$examId]);
    $exam = $stmt->fetch();

    if (!$exam) {
        throw new Exception('Exam not found');
    }

    // Check if student is allowed to take this exam
    $stmt = $conn->prepare("
        SELECT COUNT(*) as attempt_count 
        FROM exam_attempts 
        WHERE exam_id = ? AND student_id = ?
    ");
    $stmt->execute([$examId, $studentId]);
    $attemptCount = $stmt->fetch()['attempt_count'];

    if ($attemptCount >= $exam['attempts_allowed']) {
        throw new Exception('Maximum attempts reached for this exam');
    }

    // Create exam attempt
    $stmt = $conn->prepare("
        INSERT INTO exam_attempts (
            exam_id, 
            student_id, 
            start_time,
            end_time,
            is_completed
        ) VALUES (?, ?, NOW(), NOW(), 1)
    ");
    $stmt->execute([$examId, $studentId]);
    $attemptId = $conn->lastInsertId();

    // Save student answers
    $totalQuestions = 0;
    $correctAnswers = 0;

    foreach ($answers as $questionId => $selectedOptionId) {
        // Save the answer
        $stmt = $conn->prepare("
            INSERT INTO student_answers (
                attempt_id,
                question_id,
                selected_option_id,
                submitted_at
            ) VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$attemptId, $questionId, $selectedOptionId]);

        // Check if answer is correct
        $stmt = $conn->prepare("
            SELECT is_correct, points 
            FROM mcq_options 
            WHERE id = ? AND question_id = ?
        ");
        $stmt->execute([$selectedOptionId, $questionId]);
        $option = $stmt->fetch();

        $totalQuestions++;
        if ($option && $option['is_correct']) {
            $correctAnswers++;
        }
    }

    // Calculate score
    $score = ($totalQuestions > 0) ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0;

    // Update attempt with score
    $stmt = $conn->prepare("
        UPDATE exam_attempts 
        SET 
            score = ?,
            correct_answers = ?,
            total_questions = ?,
            completion_time = TIMEDIFF(end_time, start_time)
        WHERE id = ?
    ");
    $stmt->execute([$score, $correctAnswers, $totalQuestions, $attemptId]);

    // Log the activity
    $stmt = $conn->prepare("
        INSERT INTO user_activity_logs (
            user_id,
            activity_type,
            description,
            ip_address,
            user_agent
        ) VALUES (?, 'exam_submission', ?, ?, ?)
    ");
    $stmt->execute([
        $studentId,
        "Submitted exam: {$exam['title']} with score: {$score}%",
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);

    // Get student details
    $stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    // Commit transaction
    $conn->commit();

    // Set success message with detailed information
    $message = "Exam submitted successfully!\n";
    $message .= "Score: {$score}%\n";
    $message .= "Correct Answers: {$correctAnswers}/{$totalQuestions}\n";
    
    if ($score >= $exam['passing_score']) {
        $message .= "Congratulations! You passed the exam.";
    } else {
        $message .= "You did not meet the passing score of {$exam['passing_score']}%.";
    }

    setFlashMessage('success', $message);
    header('Location: exam-results.php?attempt_id=' . $attemptId);
    exit;

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    error_log("Error in submit-exam.php: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while submitting your exam. Please try again.');
    header('Location: available-exams.php');
    exit;
}
?>