<?php
require_once '../includes/functions.php';
checkRole('teacher');

$attemptId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$conn = getDBConnection();

// Get attempt details
$stmt = $conn->prepare("
    SELECT 
        ea.*,
        e.id as exam_id,
        e.title as exam_title,
        u.full_name as student_name,
        c.name as classroom_name
    FROM exam_attempts ea
    JOIN exams e ON ea.exam_id = e.id
    JOIN users u ON ea.student_id = u.id
    JOIN classroom_students cs ON u.id = cs.student_id
    JOIN classrooms c ON cs.classroom_id = c.id
    WHERE ea.id = ?
");
$stmt->execute([$attemptId]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    setFlashMessage('error', 'Attempt not found');
    header('Location: exams.php');
    exit;
}

// Get questions and answers
$stmt = $conn->prepare("
    SELECT 
        q.id as question_id,
        q.question_text,
        q.question_type,
        q.points as max_points,
        sa.id as answer_id,
        sa.answer_text,
        sa.points_earned,
        sa.teacher_comment,
        mo.option_text as selected_option
    FROM questions q
    LEFT JOIN student_answers sa ON q.id = sa.question_id AND sa.attempt_id = ?
    LEFT JOIN mcq_options mo ON sa.selected_option_id = mo.id
    WHERE q.exam_id = ?
    ORDER BY q.order_num
");
$stmt->execute([$attemptId, $attempt['exam_id']]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        $totalPoints = 0;
        $maxPoints = 0;

        // Update each answer
        foreach ($questions as $question) {
            $answerId = $question['answer_id'];
            if (isset($_POST['points'][$answerId])) {
                $points = min(floatval($_POST['points'][$answerId]), $question['max_points']);
                $comment = $_POST['comments'][$answerId] ?? '';

                $stmt = $conn->prepare("
                    UPDATE student_answers 
                    SET points_earned = ?,
                        teacher_comment = ?,
                        graded_at = CURRENT_TIMESTAMP,
                        graded_by = ?
                    WHERE id = ?
                ");
                $stmt->execute([$points, $comment, $_SESSION['user_id'], $answerId]);

                $totalPoints += $points;
            }
            $maxPoints += $question['max_points'];
        }

        // Calculate final score as percentage
        $finalScore = ($maxPoints > 0) ? ($totalPoints / $maxPoints) * 100 : 0;

        // Update attempt
        $stmt = $conn->prepare("
            UPDATE exam_attempts 
            SET score = ?,
                teacher_feedback = ?,
                published = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            $finalScore,
            $_POST['overall_feedback'],
            isset($_POST['publish']) ? 1 : 0,
            $attemptId
        ]);

        $conn->commit();
        $_SESSION['grade_confirmation'] = [
            'exam_title' => $attempt['exam_title'],
            'student_name' => $attempt['student_name'],
            'score' => $finalScore,
            'published' => isset($_POST['publish']),
            'attempt_id' => $attemptId
        ];
        header("Location: grade-confirmation.php");
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Grading error: " . $e->getMessage());
        setFlashMessage('error', 'Error saving grades');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Exam - <?php echo htmlspecialchars($attempt['exam_title']); ?></title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .container { max-width: 1000px; margin: 20px auto; padding: 20px; }
        .question-card { 
            background: white; 
            padding: 20px; 
            margin: 20px 0; 
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .answer-text { 
            background: #f8f9fa; 
            padding: 15px; 
            border-radius: 4px; 
            margin: 10px 0; 
        }
        .grading-controls {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .points-input {
            width: 100px;
            padding: 5px;
            margin-right: 10px;
        }
        .feedback-input {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
        }
        .submit-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($attempt['exam_title']); ?></h1>
        <p>Student: <?php echo htmlspecialchars($attempt['student_name']); ?></p>
        <p>Class: <?php echo htmlspecialchars($attempt['classroom_name']); ?></p>

        <form method="POST">
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-card">
                    <h3>Question <?php echo $index + 1; ?></h3>
                    <p><?php echo htmlspecialchars($question['question_text']); ?></p>
                    
                    <div class="answer-text">
                        <?php if ($question['question_type'] === 'mcq'): ?>
                            Selected: <?php echo htmlspecialchars($question['selected_option']); ?>
                        <?php else: ?>
                            <?php echo nl2br(htmlspecialchars($question['answer_text'])); ?>
                        <?php endif; ?>
                    </div>

                    <div class="grading-controls">
                        <label>
                            Points (max <?php echo $question['max_points']; ?>):
                            <input type="number" 
                                   name="points[<?php echo $question['answer_id']; ?>]" 
                                   class="points-input"
                                   min="0" 
                                   max="<?php echo $question['max_points']; ?>" 
                                   step="0.5"
                                   value="<?php echo $question['points_earned'] ?? ''; ?>"
                                   required>
                        </label>
                        <textarea 
                            name="comments[<?php echo $question['answer_id']; ?>]" 
                            class="feedback-input"
                            placeholder="Feedback for this answer"
                        ><?php echo $question['teacher_comment'] ?? ''; ?></textarea>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="submit-section">
                <textarea 
                    name="overall_feedback" 
                    class="feedback-input"
                    placeholder="Overall feedback for the student"
                ><?php echo $attempt['teacher_feedback'] ?? ''; ?></textarea>

                <div style="margin: 20px 0;">
                    <label>
                        <input type="checkbox" name="publish" value="1" 
                               <?php echo $attempt['published'] ? 'checked' : ''; ?>>
                        Publish grades to student
                    </label>
                </div>

                <button type="submit">Save Grades</button>
            </div>
        </form>
    </div>
</body>
</html> 