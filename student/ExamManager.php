<?php
class ExamManager {
    private $conn;
    private $userId;

    public function __construct($conn, $userId) {
        $this->conn = $conn;
        $this->userId = $userId;
    }

    public function startExam($examId) {
        try {
            $this->conn->beginTransaction();

            // Check if student has remaining attempts
            $stmt = $this->conn->prepare("
                SELECT attempts_allowed, 
                       (SELECT COUNT(*) FROM exam_attempts 
                        WHERE exam_id = ? AND student_id = ?) as attempts_used
                FROM exams WHERE id = ?
            ");
            $stmt->execute([$examId, $this->userId, $examId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['attempts_used'] >= $result['attempts_allowed']) {
                throw new Exception('No attempts remaining for this exam.');
            }

            // Create new attempt
            $stmt = $this->conn->prepare("
                INSERT INTO exam_attempts (exam_id, student_id, start_time)
                VALUES (?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$examId, $this->userId]);
            $attemptId = $this->conn->lastInsertId();

            $this->conn->commit();
            return $attemptId;

        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function submitAnswer($attemptId, $questionId, $answer) {
        try {
            $stmt = $this->conn->prepare("
                SELECT q.question_type, q.points
                FROM questions q
                JOIN exam_attempts ea ON q.exam_id = ea.exam_id
                WHERE q.id = ? AND ea.id = ? AND ea.student_id = ?
            ");
            $stmt->execute([$questionId, $attemptId, $this->userId]);
            $question = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$question) {
                throw new Exception('Invalid question or attempt.');
            }

            // Handle different question types
            switch ($question['question_type']) {
                case 'mcq':
                    $selectedOptionId = $answer;
                    $answerText = null;
                    
                    // Check if answer is correct
                    $stmt = $this->conn->prepare("
                        SELECT is_correct FROM mcq_options 
                        WHERE id = ? AND question_id = ?
                    ");
                    $stmt->execute([$selectedOptionId, $questionId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $isCorrect = $result['is_correct'] ?? 0;
                    $pointsEarned = $isCorrect ? $question['points'] : 0;
                    break;

                case 'true_false':
                    $selectedOptionId = $answer ? 1 : 0;
                    $answerText = null;
                    $isCorrect = $answer == $question['correct_answer'];
                    $pointsEarned = $isCorrect ? $question['points'] : 0;
                    break;

                case 'open':
                    $selectedOptionId = null;
                    $answerText = $answer;
                    $isCorrect = null; // Will be graded by teacher
                    $pointsEarned = null;
                    break;

                default:
                    throw new Exception('Invalid question type.');
            }

            // Save the answer
            $stmt = $this->conn->prepare("
                INSERT INTO student_answers 
                (attempt_id, question_id, selected_option_id, answer_text, is_correct, points_earned)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                selected_option_id = VALUES(selected_option_id),
                answer_text = VALUES(answer_text),
                is_correct = VALUES(is_correct),
                points_earned = VALUES(points_earned)
            ");
            
            return $stmt->execute([
                $attemptId,
                $questionId,
                $selectedOptionId,
                $answerText,
                $isCorrect,
                $pointsEarned
            ]);

        } catch (Exception $e) {
            error_log("Error in submitAnswer: " . $e->getMessage());
            throw $e;
        }
    }

    public function completeAttempt($attemptId) {
        try {
            $this->conn->beginTransaction();

            // Calculate score for auto-graded questions
            $stmt = $this->conn->prepare("
                UPDATE exam_attempts 
                SET is_completed = 1,
                    end_time = CURRENT_TIMESTAMP,
                    score = (
                        SELECT COALESCE(
                            (SUM(CASE 
                                WHEN q.question_type != 'open' THEN COALESCE(sa.points_earned, 0)
                                ELSE 0 
                            END) / SUM(q.points)) * 100,
                            0
                        )
                        FROM questions q
                        LEFT JOIN student_answers sa ON sa.question_id = q.id 
                            AND sa.attempt_id = ?
                        WHERE q.exam_id = (
                            SELECT exam_id FROM exam_attempts WHERE id = ?
                        )
                    )
                WHERE id = ? AND student_id = ?
            ");
            $stmt->execute([$attemptId, $attemptId, $attemptId, $this->userId]);

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error in completeAttempt: " . $e->getMessage());
            throw $e;
        }
    }
}