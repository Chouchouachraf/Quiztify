<?php
session_start();
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkRole('student');

try {
    $conn = getDBConnection();
    $student_id = $_SESSION['user_id'];

    // Get available exams for the student's classrooms
    $stmt = $conn->prepare("
        SELECT 
            e.id,
            e.title,
            e.description,
            e.start_date,
            e.end_date,
            e.total_points,
            e.attempts_allowed,
            c.name as classroom_name,
            c.department,
            u.full_name as teacher_name,
            (SELECT COUNT(*) FROM exam_attempts ea WHERE ea.exam_id = e.id AND ea.student_id = ?) as attempts_taken,
            (SELECT COUNT(*) FROM questions eq WHERE eq.exam_id = e.id) as question_count
        FROM exams e
        JOIN exam_classrooms ec ON e.id = ec.exam_id
        JOIN classrooms c ON ec.classroom_id = c.id
        JOIN users u ON e.created_by = u.id
        JOIN classroom_students cs ON c.id = cs.classroom_id
        WHERE cs.student_id = ?
        AND e.is_published = 1
        AND e.end_date >= NOW()
        ORDER BY e.start_date ASC
    ");
    $stmt->execute([$student_id, $student_id]);
    $availableExams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent exam attempts
    $stmt = $conn->prepare("
        SELECT 
            ea.id as attempt_id,
            ea.start_time,
            ea.end_time,
            ea.score,
            e.title,
            e.total_points,
            c.name as classroom_name
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        JOIN exam_classrooms ec ON e.id = ec.exam_id
        JOIN classrooms c ON ec.classroom_id = c.id
        WHERE ea.student_id = ?
        AND ea.is_completed = 1
        ORDER BY ea.end_time DESC
        LIMIT 5
    ");
    $stmt->execute([$student_id]);
    $recentAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error in dashboard.php: " . $e->getMessage());
    $availableExams = [];
    $recentAttempts = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo SITE_NAME; ?></title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
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
            --accent-color: #3498db; /* Blue accent color */
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

        .content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        .welcome-section {
            grid-column: 1 / -1;
            background: var(--light-color);
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
        }

        .welcome-section::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 100px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 24 24' fill='none' stroke='%23e74c3c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 2L2 7l10 5 10-5-10-5z'%3E%3C/path%3E%3Cpath d='M2 17l10 5 10-5M2 12l10 5 10-5M2 7l10 5 10-5M2 12l10 5 10-5'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: center;
            opacity: 0.1;
            filter: var(--background-color);
        }

        .exam-list, .recent-attempts {
            background: var(--light-color);
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .exam-card {
            background: var(--light-color);
            border: 1px solid var(--light-color);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.2s ease;
        }

        .exam-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .exam-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .exam-title {
            font-size: 1.2em;
            color: var(--accent-color);
            margin-bottom: 5px;
        }

        .exam-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 500;
        }

        .status-active {
            background-color: var(--success-color);
            color: white;
        }

        .status-upcoming {
            background-color: var(--warning-color);
            color: var(--dark-color);
        }

        .status-expired {
            background-color: var(--danger-color);
            color: white;
        }

        .exam-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }

        .exam-stat-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
            color: var(--text-color);
        }

        .exam-actions {
            margin-top: 15px;
            text-align: right;
        }

        .btn-start {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 16px;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .btn-start:hover {
            background-color: #2980b9;
        }

        .btn-start:disabled {
            background-color: #bdc3c7;
            cursor: not-allowed;
        }

        .attempt-card {
            background: var(--light-color);
            border: 1px solid var(--light-color);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }

        .badge-success {
            background-color: var(--success-color);
            color: white;
        }

        .badge-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-color);
        }

        .empty-state i {
            font-size: 3em;
            color: var(--light-color);
            margin-bottom: 10px;
        }

        .navbar {
            background: var(--light-color);
            padding: 15px 0;
            margin-bottom: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: bold;
        }

        .nav-brand i {
            color: var(--accent-color);
        }

        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .nav-link {
            text-decoration: none;
            color: var(--text-color);
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-link:hover {
            background: var(--light-color);
            color: var(--accent-color);
        }

        .nav-link.active {
            background: var(--accent-color);
            color: white;
        }

        .nav-link i {
            font-size: 1.1rem;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .logout-btn {
            padding: 8px 16px;
            border: none;
            background: var(--danger-color);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .logout-btn:hover {
            background: #c0392b;
        }

        #theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px;
            border: none;
            border-radius: 5px;
            background: var(--accent-color);
            color: var(--light-color);
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--light-color);
                flex-direction: column;
                padding: 10px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }

            .nav-links.show {
                display: flex;
            }

            .mobile-menu {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/student-nav.php'; ?>
        
        <div class="content">
            <div class="welcome-section">
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'%3E%3C/path%3E%3Cpolyline points='14 2 14 8 20 8'%3E%3C/polyline%3E%3Cpolyline points='16 18 20 18 20 12'%3E%3C/polyline%3E%3Cline x1='16' y1='18' x2='16' y2='22'%3E%3C/line%3E%3Cline x1='8' y1='22' x2='8' y2='18'%3E%3C/line%3E%3C/svg%3E" alt="Exam Document">
                <div>
                    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
                    <p>Here are your available exams:</p>
                </div>
            </div>

            <?php if ($flash = getFlashMessage()): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo $flash['message']; ?>
                </div>
            <?php endif; ?>

            <div class="exam-list">
                <h3>Available Exams</h3>
                <?php if (empty($availableExams)): ?>
                    <div class="empty-state">
                        <i class='bx bx-book-open'></i>
                        <p>No exams are currently available.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($availableExams as $exam): ?>
                        <div class="exam-card">
                            <div class="exam-header">
                                <div>
                                    <h3 class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></h3>
                                    <div class="exam-meta">
                                        <span class="teacher-name">By: <?php echo htmlspecialchars($exam['teacher_name']); ?></span>
                                        <span class="classroom-name"><?php echo htmlspecialchars($exam['classroom_name']); ?> 
                                            (<?php echo htmlspecialchars($exam['department']); ?>)</span>
                                    </div>
                                </div>
                                <?php
                                $now = new DateTime();
                                $startTime = new DateTime($exam['start_date']);
                                $endTime = new DateTime($exam['end_date']);
                                
                                if ($now < $startTime) {
                                    echo '<span class="exam-status status-upcoming">Upcoming</span>';
                                } elseif ($now <= $endTime) {
                                    echo '<span class="exam-status status-active">Active</span>';
                                } else {
                                    echo '<span class="exam-status status-expired">Expired</span>';
                                }
                                ?>
                            </div>
                            
                            <p><?php echo htmlspecialchars($exam['description']); ?></p>
                            
                            <div class="exam-stats">
                                <div class="exam-stat-item">
                                    <i class='bx bx-question-mark'></i>
                                    <span><?php echo $exam['question_count']; ?> questions</span>
                                </div>
                                <div class="exam-stat-item">
                                    <i class='bx bx-trophy'></i>
                                    <span>Total Points: <?php echo $exam['total_points']; ?></span>
                                </div>
                                <div class="exam-stat-item">
                                    <i class='bx bx-time'></i>
                                    <span>Duration: <?php 
                                        $duration = round((strtotime($exam['end_date']) - strtotime($exam['start_date'])) / 3600, 1);
                                        echo $duration . ' hours';
                                    ?></span>
                                </div>
                            </div>
                            
                            <div class="exam-dates">
                                <div class="exam-stat-item">
                                    <i class='bx bx-calendar'></i>
                                    <span>Start: <?php echo $startTime->format('M j, Y g:i A'); ?></span>
                                </div>
                                <div class="exam-stat-item">
                                    <i class='bx bx-calendar-check'></i>
                                    <span>End: <?php echo $endTime->format('M j, Y g:i A'); ?></span>
                                </div>
                            </div>
                            
                            <div class="exam-actions">
                                <?php if ($now > $endTime): ?>
                                    <button class="btn-start" disabled>
                                        <i class='bx bx-x-circle'></i> Exam Expired
                                    </button>
                                <?php elseif ($exam['attempts_taken'] >= $exam['attempts_allowed']): ?>
                                    <button class="btn-start" disabled>
                                        <i class='bx bx-x-circle'></i> No Attempts Left
                                    </button>
                                <?php else: ?>
                                    <a href="take-exam.php?id=<?php echo $exam['id']; ?>" class="btn-start">
                                        <i class='bx bx-play'></i> Start Exam
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="recent-attempts">
                <h3>Recent Attempts</h3>
                <?php if (!empty($recentAttempts)): ?>
                    <?php foreach ($recentAttempts as $attempt): ?>
                        <div class="attempt-card">
                            <h4><?php echo htmlspecialchars($attempt['title']); ?></h4>
                            <p class="classroom-name"><?php echo htmlspecialchars($attempt['classroom_name']); ?></p>
                            <p>
                                Score: 
                                <?= number_format(($attempt['score'] / 100) * $attempt['total_points'], 1) ?>/<?= $attempt['total_points'] ?>
                                <small class="percentage">(<?= number_format($attempt['score'], 1) ?>%)</small>
                            </p>
                            <p>Completed: <?php echo date('M j, Y g:i A', strtotime($attempt['end_time'])); ?></p>
                            <span class="badge <?php echo ($attempt['score'] >= ($attempt['total_points'] * 0.5)) ? 'badge-success' : 'badge-danger'; ?>">
                                <?php echo ($attempt['score'] >= ($attempt['total_points'] * 0.5)) ? 'Passed' : 'Failed'; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class='bx bx-history'></i>
                        <p>No recent exam attempts.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
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