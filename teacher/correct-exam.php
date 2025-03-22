<?php
require_once '../includes/functions.php';
checkRole('teacher');

$examId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$conn = getDBConnection();

try {
    // Get exam details
    $stmt = $conn->prepare("
        SELECT e.*, 
               COUNT(DISTINCT q.id) as total_questions,
               COUNT(DISTINCT ea.id) as total_attempts
        FROM exams e
        LEFT JOIN questions q ON e.id = q.exam_id
        LEFT JOIN exam_attempts ea ON e.id = ea.exam_id
        WHERE e.id = ? AND e.created_by = ?
        GROUP BY e.id
    ");
    $stmt->execute([$examId, $_SESSION['user_id']]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        setFlashMessage('error', 'Exam not found or access denied.');
        header('Location: manage-exams.php');
        exit;
    }

    // Get all uncorrected attempts
    $stmt = $conn->prepare("
        SELECT 
            ea.id as attempt_id,
            ea.student_id,
            ea.start_time,
            ea.end_time,
            u.full_name as student_name,
            u.email as student_email,
            q.id as question_id,
            q.question_text,
            q.question_type,
            q.points,
            sa.id as answer_id,
            sa.answer_text,
            sa.points_earned,
            sa.teacher_comment
        FROM exam_attempts ea
        JOIN users u ON ea.student_id = u.id
        JOIN questions q ON q.exam_id = ea.exam_id
        LEFT JOIN student_answers sa ON sa.attempt_id = ea.id AND sa.question_id = q.id
        WHERE ea.exam_id = ? AND ea.is_completed = 1 
        AND (q.question_type = 'open' OR sa.points_earned IS NULL)
        ORDER BY ea.end_time DESC, u.full_name, q.order_num
    ");
    $stmt->execute([$examId]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group answers by attempt
    $attempts = [];
    foreach ($answers as $answer) {
        $attemptId = $answer['attempt_id'];
        if (!isset($attempts[$attemptId])) {
            $attempts[$attemptId] = [
                'student_name' => $answer['student_name'],
                'student_email' => $answer['student_email'],
                'start_time' => $answer['start_time'],
                'end_time' => $answer['end_time'],
                'answers' => []
            ];
        }
        $attempts[$attemptId]['answers'][] = $answer;
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $conn->beginTransaction();

        try {
            foreach ($_POST['grades'] as $answerId => $grade) {
                $points = floatval($grade['points']);
                $comment = $grade['comment'] ?? '';

                $stmt = $conn->prepare("
                    UPDATE student_answers 
                    SET points_earned = ?, 
                        teacher_comment = ?,
                        is_correct = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $points, 
                    $comment,
                    ($points > 0 ? 1 : 0),
                    $answerId
                ]);
            }

            // Update attempt scores
            foreach (array_keys($attempts) as $attemptId) {
                $stmt = $conn->prepare("
                    UPDATE exam_attempts 
                    SET score = (
                        SELECT (SUM(sa.points_earned) / SUM(q.points)) * 100
                        FROM student_answers sa
                        JOIN questions q ON sa.question_id = q.id
                        WHERE sa.attempt_id = ?
                    )
                    WHERE id = ?
                ");
                $stmt->execute([$attemptId, $attemptId]);
            }

            $conn->commit();
            setFlashMessage('success', 'Grades saved successfully.');
            header("Location: correct-exam.php?id=$examId");
            exit;

        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

} catch (Exception $e) {
    error_log("Error in correct-exam.php: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading the exam.');
    header('Location: manage-exams.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Exam - <?php echo htmlspecialchars($exam['title']); ?></title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .attempt-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .student-info {
            border-bottom: 1px solid var(--light-color);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .answer-section {
            background: var(--light-color);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .question-text {
            font-weight: 500;
            margin-bottom: 10px;
        }

        .answer-text {
            background: white;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }

        .grading-controls {
            display: flex;
            gap: 20px;
            align-items: center;
            margin-top: 15px;
        }

        .points-input {
            width: 80px;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .comment-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 10px;
        }

        .submit-btn {
            background: var(--success-color);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }

        .submit-btn:hover {
            background: #27ae60;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/teacher-nav.php'; ?>

        <div class="header">
            <h1><?php echo htmlspecialchars($exam['title']); ?> - Grading</h1>
            <a href="manage-exams.php" class="btn btn-secondary">
                <i class='bx bx-arrow-back'></i> Back to Exams
            </a>
        </div>

        <?php if ($flash = getFlashMessage()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo $flash['message']; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($attempts)): ?>
            <div class="empty-state">
                <i class='bx bx-check-circle'></i>
                <h2>All Done!</h2>
                <p>There are no answers that need grading.</p>
            </div>
        <?php else: ?>
            <form method="POST">
                <?php foreach ($attempts as $attemptId => $attempt): ?>
                    <div class="attempt-card">
                        <div class="student-info">
                            <h3><?php echo htmlspecialchars($attempt['student_name']); ?></h3>
                            <p>Email: <?php echo htmlspecialchars($attempt['student_email']); ?></p>
                            <p>Submitted: <?php echo date('M j, Y g:i A', strtotime($attempt['end_time'])); ?></p>
                        </div>

                        <?php foreach ($attempt['answers'] as $answer): ?>
                            <div class="answer-section">
                                <div class="question-text">
                                    <?php echo htmlspecialchars($answer['question_text']); ?>
                                    <span>(<?php echo $answer['points']; ?> points)</span>
                                </div>

                                <div class="answer-text">
                                    <?php echo nl2br(htmlspecialchars($answer['answer_text'])); ?>
                                </div>

                                <div class="grading-controls">
                                    <div>
                                        <label>Points:</label>
                                        <input type="number" 
                                               name="grades[<?php echo $answer['answer_id']; ?>][points]" 
                                               class="points-input"
                                               min="0" 
                                               max="<?php echo $answer['points']; ?>" 
                                               step="0.5"
                                               value="<?php echo $answer['points_earned'] ?? 0; ?>"
                                               required>
                                        / <?php echo $answer['points']; ?>
                                    </div>
                                    
                                    <textarea name="grades[<?php echo $answer['answer_id']; ?>][comment]" 
                                              class="comment-input"
                                              placeholder="Add feedback for student"><?php echo $answer['teacher_comment'] ?? ''; ?></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="submit-btn">
                    <i class='bx bx-save'></i> Save Grades
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html> 