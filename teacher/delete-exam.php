<?php
require_once '../includes/functions.php';
checkRole('teacher');

if (isset($_GET['id'])) {
    try {
        $conn = getDBConnection();
        
        // Verify the exam belongs to this teacher
        $stmt = $conn->prepare("
            SELECT id FROM exams 
            WHERE id = ? AND created_by = ?
        ");
        $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            // Start transaction
            $conn->beginTransaction();
            
            // First, get all questions for this exam
            $stmt = $conn->prepare("SELECT id FROM questions WHERE exam_id = ?");
            $stmt->execute([$_GET['id']]);
            $questions = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Delete student answers
            if (!empty($questions)) {
                $questionMarks = str_repeat('?,', count($questions) - 1) . '?';
                $stmt = $conn->prepare("DELETE FROM student_answers WHERE question_id IN ($questionMarks)");
                $stmt->execute($questions);
            }
            
            // Delete MCQ options
            if (!empty($questions)) {
                $questionMarks = str_repeat('?,', count($questions) - 1) . '?';
                $stmt = $conn->prepare("DELETE FROM mcq_options WHERE question_id IN ($questionMarks)");
                $stmt->execute($questions);
            }
            
            // Delete exam attempts
            $stmt = $conn->prepare("DELETE FROM exam_attempts WHERE exam_id = ?");
            $stmt->execute([$_GET['id']]);
            
            // Delete questions
            $stmt = $conn->prepare("DELETE FROM questions WHERE exam_id = ?");
            $stmt->execute([$_GET['id']]);
            
            // Finally, delete the exam
            $stmt = $conn->prepare("DELETE FROM exams WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            
            $conn->commit();
            setFlashMessage('success', 'Exam deleted successfully!');
        } else {
            setFlashMessage('error', 'Access denied or exam not found.');
        }
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollBack();
        }
        setFlashMessage('error', 'Error deleting exam: ' . $e->getMessage());
    }
}

header('Location: manage-exams.php');
exit();