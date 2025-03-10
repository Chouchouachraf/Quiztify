<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkRole('teacher');

$attemptId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$conn = getDBConnection();

// Helper function to calculate points
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
        q.question_type,
        q.points as max_points,
        sa.id as answer_id,
        sa.answer_text,
        sa.selected_option_id,
        sa.points_earned,
        sa.teacher_comment,
        mo.option_text as selected_option,
        mo.is_correct
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
        setFlashMessage('error', 'Failed to save grades: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Exam - <?= htmlspecialchars($attempt['exam_title']) ?></title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --primary-light: #34495e;
            --secondary-color: #3498db;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f1c40f;
            --light-color: #ecf0f1;
            --text-dark: #2c3e50;
            --text-light: #95a5a6;
            --border-color: #ddd;
            --background-color: #f5f7fa;
            --card-background: white;
        }

        [data-theme="dark"] {
            --primary-color: #3498db;
            --primary-light: #2c3e50;
            --secondary-color: #1abc9c;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --light-color: #34495e;
            --text-dark: #ecf0f1;
            --text-light: #bdc3c7;
            --border-color: #2c3e50;
            --background-color: #2c3e50;
            --card-background: #34495e;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background-color: var(--background-color);
            transition: background-color 0.3s, color 0.3s;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: var(--card-background);
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .header h1 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 1.8em;
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 10px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }

        .breadcrumb a {
            color: var(--secondary-color);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .student-info {
            background: var(--card-background);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .info-card {
            border-left: 3px solid var(--secondary-color);
            padding-left: 15px;
        }

        .info-label {
            color: var(--text-light);
            font-size: 0.85em;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            color: var(--text-dark);
            font-size: 1.2em;
            font-weight: 500;
        }

        .percentage {
            font-size: 0.8em;
            color: var(--text-light);
        }

        .grading-form {
            background: var(--card-background);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
        }

        .questions-container {
            margin-bottom: 30px;
        }

        .question-card {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .question-header {
            background: var(--primary-color);
            color: white;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .question-number {
            font-weight: 600;
        }

        .question-points {
            background: rgba(255, 255, 255, 0.2);
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.9em;
        }

        .question-content {
            padding: 20px;
        }

        .question-text {
            font-size: 1.1em;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .answer-section {
            background: var(--light-color);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .answer-section h4 {
            color: var(--primary-color);
            margin-bottom: 10px;
            font-size: 1.1em;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 8px;
        }

        .answer-content {
            white-space: pre-wrap;
            line-height: 1.5;
        }

        .mcq-option {
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            display: flex;
            align-items: center;
        }

        .mcq-option.selected {
            background: rgba(52, 152, 219, 0.1);
            border: 1px solid var(--secondary-color);
        }

        .mcq-option.correct {
            background: rgba(46, 204, 113, 0.1);
            border: 1px solid var(--success-color);
        }

        .grading-section {
            background: var(--light-color);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .points-input {
            width: 80px;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1em;
            background: var(--card-background);
            color: var(--text-dark);
        }

        .feedback-input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            margin-top: 10px;
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
            font-size: 0.95em;
            background: var(--card-background);
            color: var(--text-dark);
        }

        .grading-row {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .final-grade {
            background: var(--card-background);
            padding: 25px;
            border-radius: 10px;
            margin: 25px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .final-grade h3 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-size: 1.3em;
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 10px;
        }

        .publish-controls {
            margin-top: 20px;
            padding: 15px;
            background: var(--light-color);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .publish-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .publish-checkbox input {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-color);
        }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            background: var(--secondary-color);
            color: white;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn-primary {
            background: var(--primary-color);
        }

        .btn-success {
            background: var(--success-color);
        }

        .btn-secondary {
            background: var(--text-light);
        }

        .btn i {
            margin-right: 5px;
        }

        .action-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
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

        .theme-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: background 0.3s;
        }

        .theme-toggle:hover {
            background: var(--primary-light);
        }

        .theme-toggle i {
            font-size: 1.2em;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/teacher-nav.php'; ?>

        <div class="breadcrumb">
            <a href="dashboard.php"><i class='bx bxs-dashboard'></i> Dashboard</a>
            <i class='bx bx-chevron-right'></i>
            <a href="exams.php">Exams</a>
            <i class='bx bx-chevron-right'></i>
            <a href="view-exam.php?id=<?php echo $attempt['exam_id']; ?>"><?php echo htmlspecialchars($attempt['exam_title']); ?></a>
            <i class='bx bx-chevron-right'></i>
            <span>Grading</span>
        </div>

        <?php if ($flash = getFlashMessage()): ?>
            <div class="alert alert-<?= $flash['type'] ?>">
                <?= $flash['message'] ?>
            </div>
        <?php endif; ?>

        <div class="header">
            <h1>Grading: <?= htmlspecialchars($attempt['exam_title']) ?></h1>
            <a href="view-exam.php?id=<?php echo $attempt['exam_id']; ?>" class="btn btn-secondary">
                <i class='bx bx-arrow-back'></i> Back to Exam Results
            </a>
        </div>

        <div class="student-info">
            <div class="info-card">
                <div class="info-label">Student</div>
                <div class="info-value"><?= htmlspecialchars($attempt['student_name']) ?></div>
            </div>
            <div class="info-card">
                <div class="info-label">Class</div>
                <div class="info-value"><?= htmlspecialchars($attempt['classroom_name']) ?></div>
            </div>
            <div class="info-card">
                <div class="info-label">Current Score</div>
                <div class="info-value">
                    <?= number_format(calculatePoints($attempt['score'], $attempt['total_points']), 1) ?>/<?= $attempt['total_points'] ?>
                    <small class="percentage">(<?= number_format($attempt['score'], 1) ?>%)</small>
                </div>
            </div>
            <div class="info-card">
                <div class="info-label">Submitted</div>
                <div class="info-value"><?= date('M j, Y g:i A', strtotime($attempt['end_time'])) ?></div>
            </div>
            <div class="info-card">
                <div class="info-label">Status</div>
                <div class="info-value">
                    <?php if ($attempt['published']): ?>
                        <span style="color: var(--success-color);">
                            <i class='bx bx-check-circle'></i> Published
                        </span>
                    <?php else: ?>
                        <span style="color: var(--warning-color);">
                            <i class='bx bx-time'></i> Draft
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <form method="POST" class="grading-form">
            <div class="questions-container">
                <?php foreach ($answers as $index => $answer): ?>
                    <div class="question-card">
                        <div class="question-header">
                            <div class="question-number">Question <?= $index + 1 ?></div>
                            <div class="question-points"><?= $answer['max_points'] ?> points</div>
                        </div>
                        <div class="question-content">
                            <div class="question-text"><?= htmlspecialchars($answer['question_text']) ?></div>
                            
                            <div class="answer-section">
                                <h4>Student Answer</h4>
                                <?php if ($answer['selected_option']): ?>
                                    <!-- Multiple Choice Answer -->
                                    <div class="mcq-option selected">
                                        <i class='bx bx-radio-circle-marked' style="margin-right: 10px;"></i>
                                        <?= htmlspecialchars($answer['selected_option']) ?>
                                        <?php if ($answer['is_correct']): ?>
                                            <i class='bx bx-check' style="color: var(--success-color); margin-left: 10px;"></i>
                                        <?php else: ?>
                                            <i class='bx bx-x' style="color: var(--danger-color); margin-left: 10px;"></i>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <!-- Text Answer -->
                                    <div class="answer-content">
                                        <?= nl2br(htmlspecialchars($answer['answer_text'] ?? 'No answer provided')) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="grading-section">
                                <div class="grading-row">
                                    <label for="points-<?= $answer['answer_id'] ?>">
                                        <strong>Points:</strong>
                                    </label>
                                    <input type="number" 
                                           id="points-<?= $answer['answer_id'] ?>"
                                           name="points[<?= $answer['answer_id'] ?>]" 
                                           class="points-input"
                                           min="0" 
                                           max="<?= $answer['max_points'] ?>" 
                                           step="0.5"
                                           value="<?= $answer['points_earned'] ?? '' ?>"
                                           required>
                                    <span>out of <?= $answer['max_points'] ?></span>
                                </div>
                                <div>
                                    <label for="comment-<?= $answer['answer_id'] ?>">
                                        <strong>Feedback:</strong>
                                    </label>
                                    <textarea 
                                        id="comment-<?= $answer['answer_id'] ?>"
                                        name="comments[<?= $answer['answer_id'] ?>]" 
                                        class="feedback-input"
                                        placeholder="Provide feedback on this answer"
                                    ><?= $answer['teacher_comment'] ?? '' ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="final-grade">
                <h3>Final Assessment</h3>
                <div class="grading-section">
                    <div class="grading-row">
                        <label for="final_score">
                            <strong>Final Score (%):</strong>
                        </label>
                        <input type="number" 
                               id="final_score"
                               name="final_score" 
                               class="points-input"
                               min="0" 
                               max="100" 
                               step="0.1"
                               value="<?= $attempt['score'] ?? '' ?>"
                               readonly>
                        <small>Score will be calculated automatically based on individual question points</small>
                    </div>
                    <div>
                        <label for="overall_feedback">
                            <strong>Overall Feedback:</strong>
                        </label>
                        <textarea 
                            id="overall_feedback"
                            name="overall_feedback" 
                            class="feedback-input"
                            placeholder="Provide overall feedback for the student"
                        ><?= $attempt['teacher_feedback'] ?? '' ?></textarea>
                    </div>
                </div>

                <div class="publish-controls">
                    <label class="publish-checkbox">
                        <input type="checkbox" 
                               id="publish"
                               name="publish" 
                               value="1" 
                               <?= $attempt['published'] ? 'checked' : '' ?>>
                        Make grades visible to student
                    </label>
                    <p style="margin-top: 10px; color: var(--text-light); font-size: 0.9em;">
                        <i class='bx bx-info-circle'></i> When published, students will be able to see their score, feedback, and graded answers.
                    </p>
                </div>

                <div class="action-buttons">
                    <a href="view-exam.php?id=<?php echo $attempt['exam_id']; ?>" class="btn btn-secondary">
                        <i class='bx bx-x'></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class='bx bx-save'></i> Save Grades
                    </button>
                </div>
            </div>
        </form>
    </div>

    <button class="theme-toggle" id="theme-toggle">
        <i class='bx bx-moon'></i>
    </button>

    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('theme-toggle');
        const body = document.body;

        const currentTheme = localStorage.getItem('theme');
        if (currentTheme) {
            body.setAttribute('data-theme', currentTheme);
            updateThemeIcon(currentTheme);
        }

        themeToggle.addEventListener('click', () => {
            const isDark = body.getAttribute('data-theme') === 'dark';
            body.setAttribute('data-theme', isDark ? 'light' : 'dark');
            localStorage.setItem('theme', isDark ? 'light' : 'dark');
            updateThemeIcon(isDark ? 'light' : 'dark');
        });

        function updateThemeIcon(theme) {
            const icon = themeToggle.querySelector('i');
            icon.className = theme === 'dark' ? 'bx bx-sun' : 'bx bx-moon';
        }

        // Calculate total score automatically
        document.addEventListener('DOMContentLoaded', function() {
            const pointsInputs = document.querySelectorAll('input[name^="points["]');
            const finalScoreInput = document.getElementById('final_score');
            
            function calculateTotalScore() {
                let totalEarned = 0;
                let totalPossible = 0;
                
                pointsInputs.forEach(input => {
                    const earned = parseFloat(input.value) || 0;
                    const maxPoints = parseFloat(input.max) || 0;
                    
                    totalEarned += earned;
                    totalPossible += maxPoints;
                });
                
                const finalScore = totalPossible > 0 ? (totalEarned / totalPossible) * 100 : 0;
                finalScoreInput.value = finalScore.toFixed(1);
            }
            
            // Calculate on page load
            calculateTotalScore();
            
            // Calculate when any points input changes
            pointsInputs.forEach(input => {
                input.addEventListener('input', calculateTotalScore);
            });
        });
    </script>
</body>
</html>