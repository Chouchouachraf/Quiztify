<?php
require_once '../includes/functions.php';
checkRole('student');

$attemptId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$conn = getDBConnection();

// Get attempt details with grades
$stmt = $conn->prepare("
    SELECT 
        ea.*,
        e.title as exam_title,
        e.description as exam_description
    FROM exam_attempts ea
    JOIN exams e ON ea.exam_id = e.id
    WHERE ea.id = ? AND ea.student_id = ? AND ea.published = 1
");
$stmt->execute([$attemptId, $_SESSION['user_id']]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    setFlashMessage('error', 'Grade not found or not yet published');
    header('Location: exams.php');
    exit;
}

// Get questions and answers
$stmt = $conn->prepare("
    SELECT 
        q.*,
        sa.answer_text,
        sa.selected_option_id,
        sa.points_earned,
        sa.teacher_comment,
        mo.option_text as selected_option_text
    FROM questions q
    LEFT JOIN student_answers sa ON q.id = sa.question_id AND sa.attempt_id = ?
    LEFT JOIN mcq_options mo ON sa.selected_option_id = mo.id
    WHERE q.exam_id = ?
    ORDER BY q.order_num
");
$stmt->execute([$attemptId, $attempt['exam_id']]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Grade - <?php echo htmlspecialchars($attempt['exam_title']); ?></title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* Add your existing CSS */
        .grade-summary {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .final-score {
            font-size: 2em;
            font-weight: bold;
            color: var(--primary-color);
            text-align: center;
            margin: 20px 0;
        }

        .teacher-feedback {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .question-item {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .points-earned {
            color: var(--success-color);
            font-weight: bold;
            margin-top: 10px;
        }

        .feedback {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/student-nav.php'; ?>

        <div class="grade-summary">
            <h1><?php echo htmlspecialchars($attempt['exam_title']); ?></h1>
            <div class="final-score">
                <?php echo number_format($attempt['score'], 1); ?>%
            </div>
            <?php if ($attempt['teacher_feedback']): ?>
                <div class="teacher-feedback">
                    <h3>Teacher Feedback:</h3>
                    <p><?php echo nl2br(htmlspecialchars($attempt['teacher_feedback'])); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="questions-list">
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-item">
                    <h3>Question <?php echo $index + 1; ?></h3>
                    <p><?php echo htmlspecialchars($question['question_text']); ?></p>
                    
                    <div class="answer">
                        <strong>Your Answer:</strong>
                        <?php if ($question['question_type'] === 'mcq'): ?>
                            <p><?php echo htmlspecialchars($question['selected_option_text']); ?></p>
                        <?php else: ?>
                            <p><?php echo nl2br(htmlspecialchars($question['answer_text'])); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="points-earned">
                        Points: <?php echo $question['points_earned']; ?> / <?php echo $question['points']; ?>
                    </div>

                    <?php if ($question['teacher_comment']): ?>
                        <div class="feedback">
                            <strong>Feedback:</strong>
                            <p><?php echo nl2br(htmlspecialchars($question['teacher_comment'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html> 