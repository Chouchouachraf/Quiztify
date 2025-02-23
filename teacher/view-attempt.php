<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkRole('teacher');

$attemptId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$conn = getDBConnection();

// Add this helper function at the top of the file
function calculatePoints($percentage, $total_points) {
    return ($percentage / 100) * $total_points;
}

// Get attempt details
$stmt = $conn->prepare("
    SELECT 
        ea.*,
        e.id as exam_id,
        e.title as exam_title,
        e.description as exam_description,
        e.total_points,
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

// Get student answers
$stmt = $conn->prepare("
    SELECT 
        q.id as question_id,
        q.question_text,
        q.points as max_points,
        sa.id as answer_id,
        sa.answer_text,
        sa.points_earned,
        sa.teacher_comment,
        sa.selected_option_id,
        mo.option_text as selected_option
    FROM questions q
    LEFT JOIN student_answers sa ON q.id = sa.question_id AND sa.attempt_id = ?
    LEFT JOIN mcq_options mo ON sa.selected_option_id = mo.id
    WHERE q.exam_id = ?
    ORDER BY q.order_num
");
$stmt->execute([$attemptId, $attempt['exam_id']]);
$answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        $totalPoints = 0;
        $maxPoints = 0;

        // Save grades for each answer
        foreach ($answers as $answer) {
            if (isset($_POST['points'][$answer['answer_id']])) {
                $points = min(floatval($_POST['points'][$answer['answer_id']]), $answer['max_points']);
                $comment = $_POST['comments'][$answer['answer_id']] ?? '';
                
                $stmt = $conn->prepare("
                    UPDATE student_answers 
                    SET points_earned = ?,
                        teacher_comment = ?,
                        graded_at = NOW(),
                        graded_by = ?
                    WHERE id = ?
                ");
                $stmt->execute([$points, $comment, $_SESSION['user_id'], $answer['answer_id']]);
                
                $totalPoints += $points;
                $maxPoints += $answer['max_points'];
            }
        }

        // Calculate final score
        $finalScore = ($maxPoints > 0) ? ($totalPoints / $maxPoints) * 100 : 0;

        // Update attempt
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
        
        $_SESSION['grade_confirmation'] = [
            'exam_title' => $attempt['exam_title'],
            'student_name' => $attempt['student_name'],
            'score' => $finalScore,
            'published' => isset($_POST['publish']),
            'attempt_id' => $attemptId
        ];
        
        header("Location: grade-confirmation.php");
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        setFlashMessage('error', 'Failed to save grades');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Exam - <?= htmlspecialchars($attempt['exam_title']) ?></title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f1c40f;
            --light-color: #ecf0f1;
            --text-dark: #2c3e50;
            --text-light: #95a5a6;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: var(--text-dark);
            margin-bottom: 20px;
            font-size: 2em;
        }

        .student-info {
            background: var(--light-color);
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .info-item {
            text-align: center;
        }

        .info-label {
            color: var(--text-light);
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .info-value {
            color: var(--text-dark);
            font-size: 1.2em;
            font-weight: 500;
        }

        .question {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .question-text {
            font-size: 1.2em;
            color: var(--text-dark);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-color);
        }

        .answer {
            background: #f8f9fa;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }

        .option {
            padding: 12px;
            border-radius: 8px;
            margin: 8px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .correct-option {
            background: rgba(46, 204, 113, 0.1);
            border: 1px solid var(--success-color);
        }

        .selected-option {
            background: rgba(52, 152, 219, 0.1);
            border: 1px solid var(--secondary-color);
        }

        .points {
            font-weight: 500;
            color: var(--primary-color);
        }

        .feedback {
            margin-top: 15px;
            padding: 10px;
            border-left: 3px solid var(--secondary-color);
            background: rgba(52, 152, 219, 0.1);
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            transition: opacity 0.2s ease;
        }

        .grading {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--light-color);
        }

        .points-input {
            width: 100px;
            padding: 8px;
            border: 1px solid var(--light-color);
            border-radius: 4px;
        }

        .feedback-input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--light-color);
            border-radius: 4px;
            margin-top: 10px;
            resize: vertical;
        }

        .final-grade {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .btn-submit {
            background: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 20px;
        }

        .publish-controls {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .publish-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .publish-checkbox input {
            width: 18px;
            height: 18px;
        }

        .grade-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            margin-top: 10px;
        }

        .grade-status.published {
            background: var(--success-color);
            color: white;
        }

        .grade-status.draft {
            background: var(--warning-color);
            color: var(--text-dark);
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }

        .alert-error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/teacher-nav.php'; ?>

        <div class="header">
            <h1><?= htmlspecialchars($attempt['exam_title']) ?></h1>
            <div class="student-info">
                <div class="info-item">
                    <div class="info-label">Student</div>
                    <div class="info-value"><?= htmlspecialchars($attempt['student_name']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Class</div>
                    <div class="info-value"><?= htmlspecialchars($attempt['classroom_name']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Score</div>
                    <div class="info-value">
                        <?= number_format(calculatePoints($attempt['score'], $attempt['total_points']), 1) ?>/<?= $attempt['total_points'] ?>
                        <small class="percentage">(<?= number_format($attempt['score'], 1) ?>%)</small>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Submitted</div>
                    <div class="info-value"><?= date('M j, Y g:i A', strtotime($attempt['end_time'])) ?></div>
                </div>
            </div>
            <a href="view-exam.php?id=<?= $attempt['exam_id'] ?>" class="btn">
                <i class='bx bx-arrow-back'></i> Back to Results
            </a>
        </div>

        <?php if ($flash = getFlashMessage()): ?>
            <div class="alert alert-<?= $flash['type'] ?>">
                <?= $flash['message'] ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?php foreach ($answers as $index => $answer): ?>
                <div class="question">
                    <h3>Question <?= $index + 1 ?></h3>
                    <p><?= htmlspecialchars($answer['question_text']) ?></p>
                    
                    <div class="answer">
                        <?php if ($answer['selected_option']): ?>
                            Selected: <?= htmlspecialchars($answer['selected_option']) ?>
                        <?php else: ?>
                            <?= nl2br(htmlspecialchars($answer['answer_text'])) ?>
                        <?php endif; ?>
                    </div>

                    <div class="grading">
                        <label>
                            Points (max <?= $answer['max_points'] ?>):
                            <input type="number" 
                                   name="points[<?= $answer['answer_id'] ?>]" 
                                   min="0" 
                                   max="<?= $answer['max_points'] ?>" 
                                   step="0.5"
                                   value="<?= $answer['points_earned'] ?? '' ?>"
                                   required>
                        </label>
                        <textarea 
                            name="comments[<?= $answer['answer_id'] ?>]" 
                            placeholder="Feedback for this answer"
                        ><?= $answer['teacher_comment'] ?? '' ?></textarea>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="final-grade">
                <h3>Final Grade</h3>
                <div>
                    <label>
                        Final Score (%):
                        <input type="number" 
                               name="final_score" 
                               class="points-input"
                               min="0" 
                               max="100" 
                               step="0.1"
                               value="<?= $attempt['score'] ?? '' ?>"
                               required>
                    </label>
                </div>
                <div>
                    <label>
                        Overall Feedback:
                        <textarea name="overall_feedback" 
                                  class="feedback-input"
                                  placeholder="Add overall feedback for the student"><?= $attempt['teacher_feedback'] ?? '' ?></textarea>
                    </label>
                </div>
            </div>

            <div class="publish-controls">
                <label class="publish-checkbox">
                    <input type="checkbox" name="publish" value="1" 
                           <?= $attempt['published'] ? 'checked' : '' ?>>
                    Make grade visible to student
                </label>
            </div>

            <button type="submit" class="btn-submit">
                <i class='bx bx-save'></i> Save Grades
            </button>
        </form>
    </div>
</body>
</html>