<?php
class ExamManager {
    private $conn;
    private $userId;

    public function __construct($conn, $userId) {
        $this->conn = $conn;
        $this->userId = $userId;
    }

    public function test() {
        return "ExamManager is working";
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

            // Create new exam attempt
            $stmt = $this->conn->prepare("
                INSERT INTO exam_attempts (exam_id, student_id, start_time, is_completed)
                VALUES (?, ?, CURRENT_TIMESTAMP, 0)
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
                SELECT q.question_type, q.points, q.correct_answer
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
                    $answerText = null; // Don't store in answer_text
                    
                    // Check if answer is correct
                    $stmt = $this->conn->prepare("
                        SELECT is_correct FROM mcq_options 
                        WHERE id = ? AND question_id = ?
                    ");
                    $stmt->execute([$selectedOptionId, $questionId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $isCorrect = isset($result['is_correct']) ? (int)$result['is_correct'] : 0; // Explicitly cast to integer
                    $pointsEarned = $isCorrect ? $question['points'] : 0;

                    // Insert into student_answers
                    $stmt = $this->conn->prepare("
                        INSERT INTO student_answers 
                        (attempt_id, question_id, student_id, answer_type, answer_text, selected_option_id, is_correct, points_earned)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        answer_text = VALUES(answer_text),
                        selected_option_id = VALUES(selected_option_id),
                        is_correct = VALUES(is_correct),
                        points_earned = VALUES(points_earned)
                    ");
                    
                    return $stmt->execute([
                        $attemptId,
                        $questionId,
                        $this->userId,
                        'mcq',
                        $answerText,
                        $selectedOptionId,
                        $isCorrect,
                        $pointsEarned
                    ]);

                case 'true_false':
                    $answerValue = $answer; // Store the actual true/false answer
                    
                    // Ensure both values are strings and lowercase for comparison
                    $studentAnswer = strtolower(trim($answer));
                    $correctAnswer = strtolower(trim($question['correct_answer']));
                    
                    // Add validation for question's correct answer
                    if (!in_array($correctAnswer, ['true', 'false'])) {
                        error_log("Invalid correct answer for true/false question {$questionId}: " . $correctAnswer);
                        throw new Exception('Question has invalid correct answer');
                    }
                    
                    // Validate student answer
                    if (!in_array($studentAnswer, ['true', 'false'])) {
                        error_log("Invalid student answer for true/false question {$questionId}: " . $studentAnswer);
                        throw new Exception('Invalid true/false answer value');
                    }
                    
                    $isCorrect = ($studentAnswer === $correctAnswer) ? 1 : 0; // Explicitly set as 1 or 0
                    $pointsEarned = $isCorrect ? $question['points'] : 0;

                    // Insert into student_answers
                    $stmt = $this->conn->prepare("
                        INSERT INTO student_answers 
                        (attempt_id, question_id, student_id, answer_type, answer_text, is_correct, points_earned)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        answer_text = VALUES(answer_text),
                        is_correct = VALUES(is_correct),
                        points_earned = VALUES(points_earned)
                    ");
                    
                    // Add debugging output
                    error_log("Storing true/false answer for question {$questionId} with value: " . $answerValue);
                    
                    return $stmt->execute([
                        $attemptId,
                        $questionId,
                        $this->userId,
                        'true_false',
                        $answerValue,
                        $isCorrect,
                        $pointsEarned
                    ]);

                case 'open':
                case 'code':
                    $answerText = $answer;
                    $isCorrect = null; // Will be graded by teacher
                    $pointsEarned = null;

                    // Insert into student_answers (no change needed for open and code questions)
                    $stmt = $this->conn->prepare("
                        INSERT INTO student_answers 
                        (attempt_id, question_id, student_id, answer_type, answer_text, is_correct, points_earned)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        answer_text = VALUES(answer_text),
                        is_correct = VALUES(is_correct),
                        points_earned = VALUES(points_earned)
                    ");
                    
                    // Add debugging for open/code questions
                    error_log("Storing {$question['question_type']} answer for question {$questionId}");
                    
                    return $stmt->execute([
                        $attemptId,
                        $questionId,
                        $this->userId,
                        $question['question_type'],
                        $answerText,
                        $isCorrect, // This is NULL, which is fine for the database
                        $pointsEarned
                    ]);

                default:
                    throw new Exception('Invalid question type.');
            }

        } catch (Exception $e) {
            // Add detailed error logging
            error_log("Error in submitAnswer for question {$questionId}: " . $e->getMessage());
            throw $e;
        }
    }

    public function completeAttempt($attemptId) {
        try {
            $this->conn->beginTransaction();

            // First, calculate the total points earned and total possible points
            $stmt = $this->conn->prepare("
                SELECT 
                    COALESCE(SUM(sa.points_earned), 0) as total_earned,
                    COALESCE(SUM(q.points), 0) as total_possible
                FROM questions q
                LEFT JOIN student_answers sa ON sa.question_id = q.id AND sa.attempt_id = ?
                WHERE q.exam_id = (SELECT exam_id FROM exam_attempts WHERE id = ?)
            ");
            $stmt->execute([$attemptId, $attemptId]);
            $points = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $totalEarned = $points['total_earned'];
            $totalPossible = $points['total_possible'];
            
            // Calculate the score (avoid division by zero)
            $score = $totalPossible > 0 ? $totalEarned : 0;
            
            // Update the exam_attempts table
            $stmt = $this->conn->prepare("
                UPDATE exam_attempts 
                SET is_completed = 1,
                    end_time = CURRENT_TIMESTAMP,
                    score = ?
                WHERE id = ? AND student_id = ?
            ");
            $stmt->execute([$score, $attemptId, $this->userId]);
            
            // Log the completion
            error_log("Attempt {$attemptId} completed by user {$this->userId} with score {$score}");

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error in completeAttempt: " . $e->getMessage());
            throw $e;
        }
    }

    public function getCompletedExams() {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    e.*,
                    u.full_name as teacher_name,
                    c.name as classroom_name,
                    ea.score,
                    ea.end_time as completion_time,
                    ea.id as attempt_id
                FROM exams e
                JOIN users u ON e.created_by = u.id
                JOIN exam_classrooms ec ON e.id = ec.exam_id
                JOIN classrooms c ON ec.classroom_id = c.id
                JOIN exam_attempts ea ON e.id = ea.exam_id
                WHERE ea.student_id = ?
                AND ea.is_completed = 1
                ORDER BY ea.end_time DESC
            ");
            
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error in getCompletedExams: " . $e->getMessage());
            throw new Exception("Failed to fetch completed exams");
        }
    }

    public function getAvailableExams() {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    e.*,
                    u.full_name as teacher_name,
                    c.name as classroom_name,
                    CASE 
                        WHEN NOW() < e.start_date THEN 'upcoming'
                        WHEN NOW() BETWEEN e.start_date AND e.end_date THEN 'active'
                        ELSE 'expired'
                    END as status
                FROM exams e
                JOIN users u ON e.created_by = u.id
                JOIN exam_classrooms ec ON e.id = ec.exam_id
                JOIN classrooms c ON ec.classroom_id = c.id
                JOIN classroom_students cs ON c.id = cs.classroom_id
                LEFT JOIN exam_attempts ea ON e.id = ea.exam_id 
                    AND ea.student_id = ? 
                    AND ea.is_completed = 1
                WHERE cs.student_id = ?
                AND e.is_published = 1
                AND ea.id IS NULL
                ORDER BY e.start_date ASC
            ");
            
            $stmt->execute([$this->userId, $this->userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error in getAvailableExams: " . $e->getMessage());
            throw new Exception("Failed to fetch available exams");
        }
    }
}