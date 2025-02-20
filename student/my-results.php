<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkRole('student');

try {
    $conn = getDBConnection();
    $student_id = $_SESSION['user_id'];

    // Fetch all exam attempts with details
    $stmt = $conn->prepare("
        SELECT 
            ea.id as attempt_id,
            ea.score,
            ea.start_time,
            ea.end_time,
            ea.correct_answers,
            ea.total_questions,
            e.title as exam_title,
            e.passing_score,
            e.duration_minutes,
            u.full_name as teacher_name,
            c.name as classroom_name,
            c.department
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        JOIN users u ON e.created_by = u.id
        JOIN exam_classrooms ec ON e.id = ec.exam_id
        JOIN classrooms c ON ec.classroom_id = c.id
        WHERE ea.student_id = ?
        ORDER BY ea.end_time DESC
    ");
    $stmt->execute([$student_id]);
    $attempts = $stmt->fetchAll();

    // Calculate statistics
    $total_exams = count($attempts);
    $total_passed = 0;
    $total_score = 0;
    $highest_score = 0;
    $lowest_score = 100;

    foreach ($attempts as $attempt) {
        $total_score += $attempt['score'];
        $highest_score = max($highest_score, $attempt['score']);
        $lowest_score = min($lowest_score, $attempt['score']);
        if ($attempt['score'] >= $attempt['passing_score']) {
            $total_passed++;
        }
    }

    $average_score = $total_exams > 0 ? $total_score / $total_exams : 0;
    $pass_rate = $total_exams > 0 ? ($total_passed / $total_exams) * 100 : 0;

} catch (Exception $e) {
    error_log("Error in my-results.php: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading your results.');
    $attempts = [];
    $total_exams = 0;
    $average_score = 0;
    $pass_rate = 0;
    $highest_score = 0;
    $lowest_score = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Results - <?php echo SITE_NAME; ?></title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f5f5f5;
        }

        /* Navigation Styles */
        .navbar {
            background: #2c3e50;
            padding: 15px 0;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-brand {
            color: white;
            font-size: 24px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-menu {
            display: flex;
            gap: 20px;
        }

        .nav-link {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .nav-link:hover {
            background: #34495e;
        }

        .nav-link.active {
            background: #3498db;
        }

        /* Content Styles */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-title {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #4a90e2;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .results-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .results-table th,
        .results-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .results-table th {
            background: #4a90e2;
            color: white;
            font-weight: 500;
        }

        .score-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 500;
        }

        .pass {
            background: #e8f5e9;
            color: #4CAF50;
        }

        .fail {
            background: #ffebee;
            color: #f44336;
        }

        .logout-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #c0392b;
        }

        @media (max-width: 768px) {
            .nav-menu {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="nav-brand">
                <i class='bx bx-brain'></i>
                Quiztify
            </a>
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-link">
                    <i class='bx bx-home'></i> Dashboard
                </a>
                <a href="available-exams.php" class="nav-link">
                    <i class='bx bx-book'></i> Available Exams
                </a>
                <a href="my-results.php" class="nav-link active">
                    <i class='bx bx-bar-chart'></i> My Results
                </a>
                <a href="../logout.php" class="nav-link logout-btn">
                    <i class='bx bx-log-out'></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <h1 class="page-title">My Exam Results</h1>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_exams; ?></div>
                <div class="stat-label">Total Exams Taken</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($average_score, 1); ?>%</div>
                <div class="stat-label">Average Score</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($pass_rate, 1); ?>%</div>
                <div class="stat-label">Pass Rate</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($highest_score, 1); ?>%</div>
                <div class="stat-label">Highest Score</div>
            </div>
        </div>

        <?php if (empty($attempts)): ?>
            <div class="no-results">
                <i class='bx bx-notepad'></i>
                <h3>No Exam Results Yet</h3>
                <p>You haven't taken any exams yet. Check your dashboard for available exams.</p>
            </div>
        <?php else: ?>
            <div class="results-table-container">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Exam Details</th>
                            <th>Class</th>
                            <th>Score</th>
                            <th>Correct Answers</th>
                            <th>Duration</th>
                            <th>Completion Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attempts as $attempt): ?>
                            <tr>
                                <td>
                                    <div class="exam-info">
                                        <span class="exam-title">
                                            <?php echo htmlspecialchars($attempt['exam_title']); ?>
                                        </span>
                                        <span class="exam-meta">
                                            Teacher: <?php echo htmlspecialchars($attempt['teacher_name']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($attempt['classroom_name']); ?>
                                    <div class="exam-meta">
                                        <?php echo htmlspecialchars($attempt['department']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="score-badge <?php echo ($attempt['score'] >= $attempt['passing_score']) ? 'pass' : 'fail'; ?>">
                                        <?php echo number_format($attempt['score'], 1); ?>%
                                    </span>
                                </td>
                                <td>
                                    <?php echo $attempt['correct_answers']; ?>/<?php echo $attempt['total_questions']; ?>
                                </td>
                                <td>
                                    <?php 
                                    $start = new DateTime($attempt['start_time']);
                                    $end = new DateTime($attempt['end_time']);
                                    $duration = $end->diff($start);
                                    echo $duration->format('%H:%I:%S');
                                    ?>
                                </td>
                                <td>
                                    <?php echo date('M j, Y, g:i a', strtotime($attempt['end_time'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Add any JavaScript functionality here if needed
    </script>
</body>
</html>