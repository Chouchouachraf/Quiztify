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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        $totalPoints = 0;
        $maxPoints = 0;

        // Save grades for each answer
        foreach ($allAnswers as $questionId => $answer) {
            if (isset($_POST['points'][$questionId])) {
                $points = min(floatval($_POST['points'][$questionId]), $answer['max_points']);
                $comment = $_POST['comments'][$questionId] ?? '';
                
                // Update different tables based on question type
                switch ($answer['question_type']) {
                    case 'mcq':
                        $stmt = $conn->prepare("
                            UPDATE mcq_student_answers 
                            SET points_earned = ?,
                                graded_by = ?,
                                graded_at = NOW()
                            WHERE attempt_id = ? AND question_id = ?
                        ");
                        $stmt->execute([$points, $_SESSION['user_id'], $attemptId, $questionId]);
                        break;
                        
                    case 'true_false':
                        $stmt = $conn->prepare("
                            UPDATE true_false_student_answers 
                            SET points_earned = ?,
                                graded_by = ?,
                                graded_at = NOW()
                            WHERE attempt_id = ? AND question_id = ?
                        ");
                        $stmt->execute([$points, $_SESSION['user_id'], $attemptId, $questionId]);
                        break;
                        
                    case 'open':
                    case 'code':
                        $stmt = $conn->prepare("
                            UPDATE student_answers 
                            SET points_earned = ?,
                                teacher_comment = ?,
                                graded_by = ?,
                                graded_at = NOW()
                            WHERE attempt_id = ? AND question_id = ?
                        ");
                        $stmt->execute([$points, $comment, $_SESSION['user_id'], $attemptId, $questionId]);
                        break;
                }
                
                $totalPoints += $points;
                $maxPoints += $answer['max_points'];
            }
        }

        // Calculate final score as total points earned
        $finalScore = $totalPoints;

        // Update the exam_attempts table with the final grade
        $stmt = $conn->prepare("
            UPDATE exam_attempts 
            SET score = ?,
                teacher_feedback = ?,
                published = ?,
                graded_at = NOW(),
                graded_by = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $finalScore,
            $_POST['overall_feedback'],
            isset($_POST['publish']) ? 1 : 0,
            $_SESSION['user_id'],
            $attemptId
        ]);

        $conn->commit();
        
        // Store confirmation data in the session
        $_SESSION['grade_confirmation'] = [
            'exam_title' => $attempt['exam_title'],
            'student_name' => $attempt['student_name'],
            'score' => $finalScore,
            'published' => isset($_POST['publish']),
            'attempt_id' => $attemptId,
            'exam_id' => $attempt['exam_id']
        ];
        
        // Redirect to the confirmation page
        header("Location: grade-confirmation.php");
        exit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        setFlashMessage('error', 'Failed to save grades: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Exam - <?php echo htmlspecialchars($attempt['exam_title']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f1c40f;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --background-color: #f5f6fa;
            --text-color: #2c3e50;
        }

        [data-theme="dark"] {
            --background-color: #1a1a1a;
            --text-color: #ffffff;
            --primary-color: #2980b9;
            --secondary-color: #3498db;
            --success-color: #44bb77;
            --danger-color: #ff5555;
            --warning-color: #ffcc00;
            --light-color: #333333;
            --dark-color: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: var(--background-color);
            color: var(--text-color);
        }

        .container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
        }

        h1 {
            color: var(--primary-color);
        }

        .question-card {
            background: var(--light-color);
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .answer-text {
            background: var(--background-color);
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }

        .grading-controls {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--light-color);
        }

        .points-input {
            width: 100px;
            padding: 5px;
            margin-right: 10px;
            background: var(--background-color);
            color: var(--text-color);
            border: 1px solid var(--light-color);
            border-radius: 4px;
        }

        .feedback-input {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            background: var(--background-color);
            color: var(--text-color);
            border: 1px solid var(--light-color);
            border-radius: 4px;
        }

        .submit-section {
            background: var(--light-color);
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        button {
            background: var(--secondary-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }

        button:hover {
            background: var(--primary-color);
        }

        label {
            color: var(--text-color);
        }

        input[type="checkbox"] {
            margin-right: 10px;
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const theme = localStorage.getItem('theme') || 'light';
            document.body.dataset.theme = theme;

            document.getElementById('theme-toggle').addEventListener('click', function() {
                const newTheme = document.body.dataset.theme === 'dark' ? 'light' : 'dark';
                document.body.dataset.theme = newTheme;
                localStorage.setItem('theme', newTheme);
            });
        });
    </script>
</body>
</html>