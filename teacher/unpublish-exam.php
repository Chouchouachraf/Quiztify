<?php
require_once '../includes/functions.php';
checkRole('teacher');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['exam_id'])) {
    try {
        $conn = getDBConnection();
        
        // Verify the exam belongs to this teacher
        $stmt = $conn->prepare("
            SELECT id FROM exams 
            WHERE id = ? AND created_by = ?
        ");
        $stmt->execute([$_POST['exam_id'], $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            // Update exam status to unpublished
            $stmt = $conn->prepare("
                UPDATE exams 
                SET is_published = 0 
                WHERE id = ?
            ");
            $stmt->execute([$_POST['exam_id']]);
            
            setFlashMessage('success', 'Exam unpublished successfully!');
        } else {
            setFlashMessage('error', 'Access denied or exam not found.');
        }
    } catch (Exception $e) {
        setFlashMessage('error', 'Error unpublishing exam: ' . $e->getMessage());
    }
}

header('Location: manage-exams.php');
exit();