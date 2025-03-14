<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkRole('student');

try {
    $conn = getDBConnection();
    $student_id = $_SESSION['user_id'];

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
        GROUP BY 
            ea.id, 
            ea.score, 
            ea.start_time, 
            ea.end_time, 
            ea.published, 
            ea.teacher_feedback, 
            e.title, 
            e.total_points, 
            e.passing_score, 
            u.full_name, 
            c.name, 
            c.department
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
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f1c40f;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --background-color: #f5f6fa;
            --accent-color: #3498db;
        }

        [data-theme="dark"] {
            --background-color: #1a1a1a;
            --text-color: #ffffff;
            --primary-color: #5588ff;
            --secondary-color: #44bb77;
            --success-color: #44bb77;
            --danger-color: #ff5555;
            --warning-color: #ffcc00;
            --light-color: #333333;
            --dark-color: #ffffff;
            --accent-color: #44bb77;
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
            color: var(--dark-color);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-title {
            font-size: 2em;
            color: var(--primary-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .page-title img {
            width: 32px;
            height: 32px;
            margin-right: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--light-color);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: var(--accent-color);
        }

        .stat-label {
            font-size: 1em;
            color: var(--text-color);
        }

        .result-card {
            background: var(--light-color);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .result-card::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 80px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='80' height='80' viewBox='0 0 24 24' fill='none' stroke='%23e74c3c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 2L2 7l10 5 10-5-10-5z'%3E%3C/path%3E%3Cpath d='M2 17l10 5 10-5M2 12l10 5 10-5M2 7l10 5 10-5M2 12l10 5 10-5'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: center;
            opacity: 0.1;
            filter: var(--background-color);
        }

        .exam-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .exam-title {
            font-size: 1.5em;
            color: var(--primary-color);
        }

        .exam-score {
            font-size: 1.5em;
            font-weight: bold;
            color: var(--accent-color);
        }

        .exam-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .meta-item {
            text-align: center;
        }

        .meta-label {
            font-size: 0.9em;
            color: var(--text-color);
        }

        .meta-value {
            font-size: 1.1em;
            font-weight: 500;
            color: var(--text-color);
        }

        .teacher-feedback {
            background: rgba(52, 152, 219, 0.1);
            padding: 10px;
            border-radius: 5px;
            border-left: 3px solid var(--secondary-color);
            margin-top: 10px;
            color: var(--text-color);
        }

        .toggle-answers {
            background: var(--secondary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toggle-answers i {
            transition: transform 0.3s ease;
        }

        .answers-container {
            display: none;
            margin-top: 20px;
            padding: 15px;
            background: var(--light-color);
            border-radius: 8px;
            border: 1px solid var(--background-color);
        }

        .answers-container.show {
            display: block;
        }

        .answer-item {
            margin-bottom: 15px;
        }

        .question-text {
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 10px;
        }

        .answer-text {
            background: var(--background-color);
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            color: var(--text-color);
        }

        .points-earned {
            color: var(--success-color);
            font-weight: 500;
            margin: 10px 0;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            background: var(--light-color);
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .no-results i {
            font-size: 48px;
            color: var(--accent-color);
            margin-bottom: 20px;
        }

        .no-results h3 {
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .no-results p {
            color: var(--text-color);
        }
    </style>
</head>
<body>
    <?php include '../includes/student-nav.php'; ?>

    <div class="container">
        <h1 class="page-title">
            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='3' width='18' height='18' rx='2' ry='2'%3E%3C/rect%3E%3Cpath d='M3 9l9-2 9 2'%3E%3C/path%3E%3Cpath d='M9 21V9'%3E%3C/path%3E%3Cpath d='M15 21V9'%3E%3C/path%3E%3C/svg%3E" alt="Exam">
            My Exam Results
        </h1>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_exams; ?></div>
                <div class="stat-label">Total Exams</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($average_score, 1); ?></div>
                <div class="stat-label">Average Score</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($highest_score, 1); ?></div>
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
                        <div class="exam-score"><?php echo number_format($attempt['score'], 1); ?></div>
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
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        function toggleAnswers(attemptId) {
            const container = document.getElementById(`answers-${attemptId}`);
            const button = container.previousElementSibling;
            const icon = button.querySelector('i');

            container.classList.toggle('show');
            if (container.classList.contains('show')) {
                icon.className = 'bx bx-chevron-up';
            } else {
                icon.className = 'bx bx-chevron-down';
            }
        }
    </script>
</body>
</html>