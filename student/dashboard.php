<?php
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
            e.*,
            u.full_name as teacher_name,
            COUNT(DISTINCT q.id) as question_count,
            c.name as classroom_name,
            c.department,
            (
                SELECT COUNT(*) 
                FROM exam_attempts 
                WHERE exam_id = e.id AND student_id = ? AND is_completed = 1
            ) as attempts_taken,
            SUM(q.points) as total_points
        FROM exams e
        JOIN users u ON e.created_by = u.id
        JOIN exam_classrooms ec ON e.id = ec.exam_id
        JOIN classrooms c ON ec.classroom_id = c.id
        JOIN classroom_students cs ON c.id = cs.classroom_id
        LEFT JOIN questions q ON e.id = q.exam_id
        WHERE e.is_published = 1
        AND cs.student_id = ?
        AND e.end_date >= NOW()
        GROUP BY e.id
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: #f5f6fa;
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
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .exam-list, .recent-attempts {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .exam-card {
            background: #fff;
            border: 1px solid #e1e8ed;
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
            color: var(--primary-color);
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
            color: #666;
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
            background-color: var(--secondary-color);
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
            background: #fff;
            border: 1px solid #e1e8ed;
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
            color: #666;
        }

        .empty-state i {
            font-size: 3em;
            color: #bdc3c7;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .content {
                grid-template-columns: 1fr;
            }

            .exam-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/student-nav.php'; ?>
        
        <div class="content">
            <div class="welcome-section">
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
                <p>Here are your available exams:</p>
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
                                <?php elseif ($exam['ongoing_attempts'] > 0): ?>
                                    <a href="resume-exam.php?id=<?php echo $exam['id']; ?>" class="btn-start">
                                        <i class='bx bx-play-circle'></i> Resume Exam
                                    </a>
                                <?php else: ?>
                                    <a href="take-exam.php?id=<?php echo $exam['id']; ?>" class="btn-start">
                                        <i class='bx bx-play'></i> Start Exam
                                        <?php if ($now > $startTime): ?>
                                            <small>(Late start)</small>
                                        <?php endif; ?>
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
</body>
</html>