<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debugging: Log the received data
    error_log("Received POST data: " . print_r($_POST, true));

    // Validate input
    if (!isset($_POST['attempt_id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
        exit();
    }

    $attemptId = $_POST['attempt_id'];
    $studentId = $_SESSION['user_id'];

    try {
        $conn = getDBConnection();

        // Begin transaction
        $conn->beginTransaction();

        // Process answers
        if (isset($_POST['answers'])) {
            foreach ($_POST['answers'] as $questionId => $answer) {
                // Debugging: Log each answer
                error_log("Processing answer for question $questionId: $answer");

                // Determine the question type
                $stmt = $conn->prepare("
                    SELECT question_type FROM questions WHERE id = ?
                ");
                $stmt->execute([$questionId]);
                $question = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$question) {
                    throw new Exception("Invalid question ID: $questionId");
                }

                // Handle different question types
                switch ($question['question_type']) {
                    case 'mcq':
                        $selectedOptionId = $answer;
                        $answerText = null;
                        break;

                    case 'true_false':
                        $selectedOptionId = $answer ? 1 : 0;
                        $answerText = null;
                        break;

                    case 'open':
                        $selectedOptionId = null;
                        $answerText = $answer;
                        break;

                    default:
                        throw new Exception("Invalid question type: {$question['question_type']}");
                }

                // Save or update the answer
                $stmt = $conn->prepare("
                    INSERT INTO student_answers 
                    (attempt_id, question_id, selected_option_id, answer_text)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    selected_option_id = VALUES(selected_option_id),
                    answer_text = VALUES(answer_text)
                ");
                $stmt->execute([$attemptId, $questionId, $selectedOptionId, $answerText]);
            }
        }

        // Commit transaction
        $conn->commit();

        // Debugging: Log success
        error_log("Progress auto-saved successfully");

        // Return success response
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        $conn->rollBack();
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Failed to save progress']);
        error_log("Error in auto-save.php: " . $e->getMessage());
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>