<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../includes/session.php';
require_once '../includes/config.php';
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

// Get questions and their answers ordered by creation order
$questions = [];
$stmt = $conn->prepare("
    SELECT 
        q.*,
        q.question_type,
        q.order_num
    FROM questions q
    WHERE q.exam_id = ?
    ORDER BY q.order_num
");
$stmt->execute([$attempt['exam_id']]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch answers for each question type
$allAnswers = [];

// Fetch all student answers
$stmt = $conn->prepare("
    SELECT 
        sa.*,
        q.points as max_points,
        mo.option_text as selected_option_text
    FROM student_answers sa
    JOIN questions q ON sa.question_id = q.id
    LEFT JOIN mcq_options mo ON sa.selected_option_id = mo.id
    WHERE sa.attempt_id = ?
");
$stmt->execute([$attemptId]);
$studentAnswers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Combine all answers into a single array indexed by question_id
foreach ($questions as $question) {
    $questionId = $question['id'];
    $allAnswers[$questionId] = [
        'question_text' => $question['question_text'],
        'question_type' => $question['question_type'],
        'max_points' => $question['points'],
        'answer' => null,
        'points_earned' => null,
        'teacher_comment' => null,
        'selected_option_text' => null,
        'is_correct' => null,
        'order_num' => $question['order_num']
    ];
}

// Process all answers
foreach ($studentAnswers as $answer) {
    $questionId = $answer['question_id'];
    if (isset($allAnswers[$questionId])) {
        $allAnswers[$questionId]['answer'] = $answer['answer_type'] === 'mcq' ? 
                                            $answer['selected_option_id'] : 
                                            $answer['answer_text'];
        $allAnswers[$questionId]['points_earned'] = $answer['points_earned'];
        $allAnswers[$questionId]['teacher_comment'] = $answer['teacher_comment'];
        $allAnswers[$questionId]['selected_option_text'] = $answer['selected_option_text'];
        $allAnswers[$questionId]['is_correct'] = $answer['is_correct'];
        $allAnswers[$questionId]['manual_grade'] = $answer['manual_grade'];
    }
}

// No need to fetch from exam_final_grades, use the attempt data directly
$finalGrade = [
    'final_score' => $attempt['score'],
    'total_points' => $attempt['total_points'],
    'overall_feedback' => $attempt['teacher_feedback']
];

// Get cheating incidents for this attempt
$stmt = $conn->prepare("
    SELECT 
        eal.*,
        DATE_FORMAT(eal.created_at, '%Y-%m-%d %H:%i:%s') as formatted_time,
        ecs.snapshot_path
    FROM exam_attempt_logs eal
    LEFT JOIN exam_cheating_snapshots ecs ON eal.attempt_id = ecs.attempt_id 
        AND eal.created_at = ecs.created_at
    WHERE eal.attempt_id = ? 
    AND eal.event_type = 'cheating_attempt'
    ORDER BY eal.created_at DESC
");
$stmt->execute([$attemptId]);
$cheatingIncidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                
                // Update student_answers table for all question types
                $stmt = $conn->prepare("
                    UPDATE student_answers 
                    SET points_earned = ?,
                        teacher_comment = ?,
                        graded_by = ?,
                        graded_at = NOW()
                    WHERE attempt_id = ? AND question_id = ?
                ");
                $stmt->execute([$points, $comment, $_SESSION['user_id'], $attemptId, $questionId]);
                
                // Additional updates for specific question types
                if ($answer['question_type'] === 'mcq') {
                    // Update mcq_student_answers table
                    $stmt = $conn->prepare("
                        UPDATE mcq_student_answers 
                        SET points_earned = ?,
                            teacher_comment = ?,
                            graded_by = ?,
                            graded_at = NOW()
                        WHERE attempt_id = ? AND question_id = ?
                    ");
                    $stmt->execute([$points, $comment, $_SESSION['user_id'], $attemptId, $questionId]);
                } elseif ($answer['question_type'] === 'true_false') {
                    // Update true_false_student_answers table
                    $stmt = $conn->prepare("
                        UPDATE true_false_student_answers 
                        SET points_earned = ?,
                            teacher_comment = ?,
                            graded_by = ?,
                            graded_at = NOW()
                        WHERE attempt_id = ? AND question_id = ?
                    ");
                    $stmt->execute([$points, $comment, $_SESSION['user_id'], $attemptId, $questionId]);
                }
                
                $totalPoints += $points;
                $maxPoints += $answer['max_points'];
            }
        }

        // Use the final_score directly from the form
        $finalScore = isset($_POST['final_score']) ? floatval($_POST['final_score']) : $totalPoints;

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
        
        // Refresh the attempt data after saving
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
                    <?php if ($attempt['score'] !== null): ?>
                        <?= number_format($attempt['score'], 1) ?>
                    <?php else: ?>
                        Not graded yet
                    <?php endif; ?>
                </div>
            </div>
            <div class="info-card">
                <div class="info-label">Submitted</div>
                <div class="info-value">
                    <?php if ($attempt['end_time']): ?>
                        <?= date('M j, Y g:i A', strtotime($attempt['end_time'])) ?>
                    <?php else: ?>
                        Not submitted yet
                    <?php endif; ?>
                </div>
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
                <?php 
                // Sort questions by order_num to display in creation order
                usort($allAnswers, function($a, $b) {
                    return $a['order_num'] <=> $b['order_num'];
                });
                
                foreach ($allAnswers as $questionId => $answer): ?>
                    <div class="question-card">
                        <div class="question-header">
                            <div class="question-number">Question <?= $answer['order_num'] ?></div>
                            <div class="question-points"><?= $answer['max_points'] ?> points</div>
                        </div>
                        <div class="question-content">
                            <div class="question-text"><?= htmlspecialchars($answer['question_text']) ?></div>
                            
                            <div class="answer-section">
                                <h4>Student Answer</h4>
                                <?php if ($answer['question_type'] === 'mcq'): ?>
                                    <!-- Multiple Choice Answer -->
                                    <div class="mcq-option selected">
                                        <i class='bx bx-radio-circle-marked' style="margin-right: 10px;"></i>
                                        <?= htmlspecialchars($answer['selected_option_text'] ?? 'Option not found') ?>
                                        <?php if ($answer['is_correct']): ?>
                                            <i class='bx bx-check' style="color: var(--success-color); margin-left: 10px;"></i>
                                        <?php else: ?>
                                            <i class='bx bx-x' style="color: var(--danger-color); margin-left: 10px;"></i>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($answer['question_type'] === 'true_false'): ?>
                                    <!-- True/False Answer -->
                                    <div class="mcq-option selected">
                                        <i class='bx bx-radio-circle-marked' style="margin-right: 10px;"></i>
                                        <?= htmlspecialchars($answer['answer'] ?? 'No answer') ?>
                                        <?php if ($answer['is_correct']): ?>
                                            <i class='bx bx-check' style="color: var(--success-color); margin-left: 10px;"></i>
                                        <?php else: ?>
                                            <i class='bx bx-x' style="color: var(--danger-color); margin-left: 10px;"></i>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <!-- Text Answer -->
                                    <div class="answer-content">
                                        <?= nl2br(htmlspecialchars($answer['answer'] ?? 'No answer provided')) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="grading-section">
                                <div class="grading-row">
                                    <label for="points-<?= $questionId ?>">
                                        <strong>Points:</strong>
                                    </label>
                                    <input type="number" 
                                           id="points-<?= $questionId ?>"
                                           name="points[<?= $questionId ?>]" 
                                           class="points-input"
                                           min="0" 
                                           max="<?= $answer['max_points'] ?>" 
                                           step="0.5"
                                           value="<?= $answer['points_earned'] ?? '' ?>"
                                           required>
                                    <span class="max-points" data-max="<?= $answer['max_points'] ?>">out of <?= $answer['max_points'] ?></span>
                                </div>
                                <div>
                                    <label for="comment-<?= $questionId ?>">
                                        <strong>Feedback:</strong>
                                    </label>
                                    <textarea 
                                        id="comment-<?= $questionId ?>"
                                        name="comments[<?= $questionId ?>]" 
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
                            <strong>Final Score:</strong>
                        </label>
                        <input type="number" 
                               id="final_score"
                               name="final_score" 
                               class="points-input"
                               min="0" 
                               max="100" 
                               step="0.1"
                               value="<?= $attempt['score'] ?? $totalPoints ?? 0 ?>"
                               required>
                        <small>Enter the final score</small>
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

        <!-- Add this after the student answers section -->
        <div class="question-card" style="margin-top: 30px;">
            <div class="question-header" style="background: var(--danger-color); display: flex; justify-content: space-between; align-items: center;">
                <h5 style="margin: 0; font-size: 1.1em; font-weight: 600;">
                    <i class='bx bx-error-circle' style="margin-right: 8px;"></i>
                    Exam Integrity Report
                </h5>
                <span style="background: rgba(255, 255, 255, 0.2); padding: 3px 10px; border-radius: 15px; font-size: 0.9em;">
                    <?php echo count($cheatingIncidents) > 0 ? count($cheatingIncidents) . ' Incidents' : 'No Incidents'; ?>
                </span>
            </div>
            <div class="question-content">
                <?php if (count($cheatingIncidents) > 0): ?>
                    <div style="padding: 15px; background-color: #fff3cd; color: #856404; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ffeeba;">
                        <strong>Notice:</strong> This attempt has suspicious activities that may indicate academic integrity violations.
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                            <thead>
                                <tr style="background-color: var(--light-color); border-bottom: 1px solid var(--border-color);">
                                    <th style="padding: 12px 15px; text-align: left; font-weight: 600;">Time</th>
                                    <th style="padding: 12px 15px; text-align: left; font-weight: 600;">Type</th>
                                    <th style="padding: 12px 15px; text-align: left; font-weight: 600;">Details</th>
                                    <th style="padding: 12px 15px; text-align: left; font-weight: 600;">Evidence</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cheatingIncidents as $incident): ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 12px 15px;"><?php echo $incident['formatted_time']; ?></td>
                                        <td style="padding: 12px 15px;">
                                            <span style="display: inline-block; padding: 4px 8px; border-radius: 4px; background-color: #f8d7da; color: #721c24; font-size: 0.9em; font-weight: 500;">
                                                <?php 
                                                $type = $incident['details'];
                                                if ($type === 'tab_switch') {
                                                    echo 'Left Exam Tab';
                                                } elseif ($type === 'right_click') {
                                                    echo 'Right Click Attempt';
                                                } elseif ($type === 'copy_paste' || $type === 'copy_text' || $type === 'paste_text' || $type === 'cut_text') {
                                                    echo 'Copy/Paste Attempt';
                                                } elseif ($type === 'navigation_attempt') {
                                                    echo 'Navigation Attempt';
                                                } elseif ($type === 'focus_loss') {
                                                    echo 'Window Focus Loss';
                                                } elseif ($type === 'keyboard_shortcut') {
                                                    echo 'Keyboard Shortcut';
                                                } elseif ($type === 'dev_tools') {
                                                    echo 'Dev Tools Access';
                                                } elseif ($type === 'window_resize') {
                                                    echo 'Window Resize';
                                                } elseif ($type === 'multiple_displays') {
                                                    echo 'Multiple Displays';
                                                } elseif ($type === 'fullscreen_enter' || $type === 'fullscreen_exit') {
                                                    echo 'Fullscreen Toggle';
                                                } else {
                                                    echo htmlspecialchars($type);
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px 15px;"><?php echo htmlspecialchars($incident['details']); ?></td>
                                        <td style="padding: 12px 15px;">
                                            <?php if ($incident['snapshot_path']): ?>
                                                <a href="../uploads/snapshots/<?php echo $incident['snapshot_path']; ?>" 
                                                   target="_blank" 
                                                   style="display: inline-block; padding: 5px 10px; background-color: var(--secondary-color); color: white; text-decoration: none; border-radius: 4px; font-size: 0.9em;">
                                                    <i class='bx bx-camera' style="margin-right: 5px;"></i> View Snapshot
                                                </a>
                                            <?php else: ?>
                                                <span style="color: var(--text-light);">No snapshot</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="padding: 15px; background-color: #d4edda; color: #155724; border-radius: 8px; border: 1px solid #c3e6cb;">
                        <i class='bx bx-check-circle' style="margin-right: 5px;"></i>
                        No suspicious activities were detected during this exam attempt.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Calculate total score automatically
        document.addEventListener('DOMContentLoaded', function() {
            const pointsInputs = document.querySelectorAll('input[name^="points["]');
            const finalScoreInput = document.getElementById('final_score');
            
            function calculateTotalScore() {
                let totalEarned = 0;
                
                pointsInputs.forEach(input => {
                    const earned = parseFloat(input.value) || 0;
                    totalEarned += earned;
                });
                
                // Update the final score field with the total points earned
                // Only update if the user hasn't manually changed it
                if (!finalScoreInput.dataset.manuallyEdited) {
                    finalScoreInput.value = totalEarned.toFixed(1);
                }
            }
            
            // Calculate on page load
            calculateTotalScore();
            
            // Calculate when any points input changes
            pointsInputs.forEach(input => {
                input.addEventListener('input', calculateTotalScore);
            });
            
            // Mark the final score as manually edited when the user changes it
            finalScoreInput.addEventListener('input', function() {
                finalScoreInput.dataset.manuallyEdited = 'true';
            });
            
            // Add a button to recalculate the total
            const recalculateButton = document.createElement('button');
            recalculateButton.type = 'button';
            recalculateButton.className = 'btn btn-secondary';
            recalculateButton.style.marginLeft = '10px';
            recalculateButton.innerHTML = '<i class="bx bx-refresh"></i> Recalculate';
            recalculateButton.addEventListener('click', function() {
                finalScoreInput.dataset.manuallyEdited = '';
                calculateTotalScore();
            });
            
            finalScoreInput.parentNode.appendChild(recalculateButton);
        });
    </script>
</body>
</html>