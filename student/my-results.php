<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkRole('student');

try {
    $conn = getDBConnection();
    $student_id = $_SESSION['user_id'];

    // Add helper function
    function calculatePoints($percentage, $total_points) {
        return ($percentage / 100) * $total_points;
    }

    // Fetch all exam attempts with details and answers
    $stmt = $conn->prepare("
        SELECT 
            ea.id as attempt_id,
            ea.score,
            ea.start_time,
            ea.end_time,
            ea.published,
            ea.teacher_feedback,
            e.title as exam_title,
            e.total_points,
            e.passing_score,
            u.full_name as teacher_name,
            c.name as classroom_name,
            c.department,
            GROUP_CONCAT(
                CONCAT(
                    q.question_text, '::',
                    COALESCE(sa.answer_text, ''), '::',
                    COALESCE(sa.points_earned, 0), '::',
                    q.points, '::',
                    COALESCE(sa.teacher_comment, ''), '::',
                    q.question_type, '::',
                    COALESCE(mo.option_text, '')
                )
                SEPARATOR '|||'
            ) as answers
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        JOIN users u ON e.created_by = u.id
        JOIN exam_classrooms ec ON e.id = ec.exam_id
        JOIN classrooms c ON ec.classroom_id = c.id
        LEFT JOIN student_answers sa ON ea.id = sa.attempt_id
        LEFT JOIN questions q ON sa.question_id = q.id
        LEFT JOIN mcq_options mo ON sa.selected_option_id = mo.id
        WHERE ea.student_id = ? AND ea.is_completed = 1
        GROUP BY ea.id
        ORDER BY ea.end_time DESC
    ");
    $stmt->execute([$student_id]);
    $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate statistics
    $total_exams = count($attempts);
    $total_score = 0;
    $highest_score = 0;

    foreach ($attempts as $attempt) {
        $total_score += $attempt['score'];
        $highest_score = max($highest_score, $attempt['score']);
    }

    $average_score = $total_exams > 0 ? $total_score / $total_exams : 0;

} catch (Exception $e) {
    error_log("Error in my-results.php: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading your results.');
    $attempts = [];
    $total_exams = 0;
    $average_score = 0;
    $highest_score = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Results - <?php echo SITE_NAME; ?></title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: #f5f6fa;
            color: #2c3e50;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .result-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .exam-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-color);
        }

        .exam-title {
            font-size: 1.5em;
            color: var(--text-dark);
        }

        .exam-score {
            font-size: 2em;
            font-weight: bold;
            color: var(--primary-color);
        }

        .exam-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 15px;
            background: var(--light-color);
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .meta-item {
            text-align: center;
        }

        .meta-label {
            color: var(--text-light);
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .meta-value {
            font-weight: 500;
            color: var(--text-dark);
        }

        .answers-section {
            margin-top: 20px;
        }

        .answer-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .question-text {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 10px;
        }

        .answer-text {
            background: white;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }

        .points-earned {
            color: var(--success-color);
            font-weight: 500;
            margin: 10px 0;
        }

        .teacher-feedback {
            background: rgba(52, 152, 219, 0.1);
            padding: 10px;
            border-radius: 5px;
            border-left: 3px solid var(--secondary-color);
            margin-top: 10px;
        }

        .toggle-answers {
            background: var(--secondary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }

        .answers-container {
            display: none;
        }

        .answers-container.show {
            display: block;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .no-results i {
            font-size: 48px;
            color: #3498db;
            margin-bottom: 20px;
        }

        .no-results h3 {
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .no-results p {
            color: #666;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .percentage {
            color: #666;
            font-size: 0.85em;
            margin-left: 5px;
        }

        .score-display {
            display: flex;
            align-items: baseline;
            gap: 5px;
        }

        .score-value {
            font-size: 1.2em;
            font-weight: 500;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <?php include '../includes/student-nav.php'; ?>

    <div class="container">
        <h1 class="page-title">My Exam Results</h1>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_exams; ?></div>
                <div class="stat-label">Total Exams</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($average_score, 1); ?>%</div>
                <div class="stat-label">Average Score</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($highest_score, 1); ?>%</div>
                <div class="stat-label">Highest Score</div>
            </div>
        </div>

        <?php if (empty($attempts)): ?>
            <div class="no-results">
                <i class="fas fa-clipboard-list"></i>
                <h3>No Exam Results Yet</h3>
                <p>You haven't taken any exams yet. Check your dashboard for available exams.</p>
            </div>
        <?php else: ?>
            <?php foreach ($attempts as $attempt): ?>
                <div class="result-card">
                    <div class="exam-header">
                        <div class="exam-title"><?php echo htmlspecialchars($attempt['exam_title']); ?></div>
                        <div class="exam-score"><?php echo number_format(calculatePoints($attempt['score'], $attempt['total_points']), 1); ?>/<?php echo $attempt['total_points']; ?></div>
                    </div>

                    <div class="exam-meta">
                        <div class="meta-item">
                            <div class="meta-label">Class</div>
                            <div class="meta-value"><?php echo htmlspecialchars($attempt['classroom_name']); ?></div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Teacher</div>
                            <div class="meta-value"><?php echo htmlspecialchars($attempt['teacher_name']); ?></div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Submitted</div>
                            <div class="meta-value"><?php echo date('M j, Y g:i A', strtotime($attempt['end_time'])); ?></div>
                        </div>
                    </div>

                    <?php if ($attempt['teacher_feedback']): ?>
                        <div class="teacher-feedback">
                            <strong>Teacher Feedback:</strong>
                            <p><?php echo nl2br(htmlspecialchars($attempt['teacher_feedback'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($attempt['published']): ?>
                        <div class="score-section">
                            <div class="score-display">
                                <span class="score-value">
                                    <?php echo number_format(calculatePoints($attempt['score'], $attempt['total_points']), 1); ?>/<?php echo $attempt['total_points']; ?>
                                </span>
                                <small class="percentage">(<?php echo number_format($attempt['score'], 1); ?>%)</small>
                            </div>
                            <div class="pass-status <?php echo $attempt['score'] >= $attempt['passing_score'] ? 'passed' : 'failed'; ?>">
                                <?php echo $attempt['score'] >= $attempt['passing_score'] ? 'Passed' : 'Failed'; ?>
                            </div>
                        </div>

                        <button class="toggle-answers" onclick="toggleAnswers(<?php echo $attempt['attempt_id']; ?>)">
                            <i class='bx bx-chevron-down'></i> View Detailed Results
                        </button>

                        <div id="answers-<?php echo $attempt['attempt_id']; ?>" class="answers-container">
                            <?php
                            $answers = explode('|||', $attempt['answers']);
                            foreach ($answers as $index => $answer):
                                list($question, $student_answer, $points_earned, $max_points, $comment, $type, $selected_option) = explode('::', $answer);
                            ?>
                                <div class="answer-item">
                                    <div class="question-text">
                                        Question <?php echo $index + 1; ?>: <?php echo htmlspecialchars($question); ?>
                                    </div>
                                    
                                    <div class="answer-text">
                                        <strong>Your Answer:</strong><br>
                                        <?php echo $type === 'mcq' ? htmlspecialchars($selected_option) : nl2br(htmlspecialchars($student_answer)); ?>
                                    </div>

                                    <div class="points-earned">
                                        Points: <?php echo $points_earned; ?> / <?php echo $max_points; ?>
                                    </div>

                                    <?php if ($comment): ?>
                                        <div class="teacher-feedback">
                                            <strong>Feedback:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($comment)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="pending-notice">
                            <i class='bx bx-time-five'></i>
                            <p>Results will be available after grading is complete</p>
                            <span class="submission-time">Submitted <?php echo timeAgo($attempt['end_time']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        function toggleAnswers(attemptId) {
            const container = document.getElementById(`answers-${attemptId}`);
            container.classList.toggle('show');
            
            const button = container.previousElementSibling;
            const icon = button.querySelector('i');
            if (container.classList.contains('show')) {
                icon.className = 'bx bx-chevron-up';
            } else {
                icon.className = 'bx bx-chevron-down';
            }
        }
    </script>
</body>
</html>