<?php
require_once '../includes/functions.php';
checkRole('teacher');

$conn = getDBConnection();
$userId = $_SESSION['user_id'];

try {
    // Fetch dashboard statistics
    $stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM exams WHERE created_by = ?) as total_exams,
            (SELECT COUNT(*) FROM classrooms WHERE teacher_id = ?) as total_classrooms,
            (SELECT COUNT(DISTINCT student_id) 
             FROM classroom_students cs 
             JOIN classrooms c ON cs.classroom_id = c.id 
             WHERE c.teacher_id = ?) as total_students,
            (SELECT COUNT(*) 
             FROM exam_attempts ea 
             JOIN exams e ON ea.exam_id = e.id 
             WHERE e.created_by = ?) as total_attempts
    ");
    
    $stmt->execute([$userId, $userId, $userId, $userId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch recent exams
    $stmt = $conn->prepare("
        SELECT 
            e.*,
            COUNT(DISTINCT ea.id) as attempt_count,
            AVG(ea.score) as average_score
        FROM exams e
        LEFT JOIN exam_attempts ea ON e.id = ea.exam_id
        WHERE e.created_by = ?
        GROUP BY e.id
        ORDER BY e.created_at DESC
        LIMIT 5
    ");
    
    $stmt->execute([$userId]);
    $recent_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch recent classroom activities
    $stmt = $conn->prepare("
        SELECT 
            c.name as classroom_name,
            c.department,
            u.full_name as student_name,
            cs.joined_at
        FROM classroom_students cs
        JOIN classrooms c ON cs.classroom_id = c.id
        JOIN users u ON cs.student_id = u.id
        WHERE c.teacher_id = ?
        ORDER BY cs.joined_at DESC
        LIMIT 5
    ");
    
    $stmt->execute([$userId]);
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch classrooms with student count
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            COUNT(DISTINCT cs.student_id) as student_count
        FROM classrooms c
        LEFT JOIN classroom_students cs ON c.id = cs.classroom_id
        WHERE c.teacher_id = ?
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT 4
    ");
    
    $stmt->execute([$userId]);
    $classrooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error in dashboard.php: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading dashboard data.');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Quiztify</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .dashboard-header {
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            font-size: 24px;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--light-color);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--text-color);
            font-size: 0.9rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .dashboard-card {
            background: var(--light-color);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--light-color);
        }

        .card-header h2 {
            font-size: 18px;
            color: var(--primary-color);
        }

        .view-all {
            color: var(--secondary-color);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .exam-list, .activity-list {
            list-style: none;
        }

        .exam-item, .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid var(--light-color);
        }

        .exam-title {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .exam-meta, .activity-meta {
            color: var(--text-color);
            font-size: 0.9rem;
            display: flex;
            gap: 15px;
        }

        .classroom-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .classroom-card {
            background: var(--light-color);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .classroom-header {
            margin-bottom: 15px;
        }

        .classroom-name {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .classroom-department {
            color: var(--text-color);
            font-size: 0.9rem;
        }

        .classroom-stats {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            color: var(--text-color);
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Added CSS for the image */
        .dashboard-image {
            max-width: 100px;
            height: auto;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/teacher-nav.php'; ?>

        <div class="dashboard-header">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Teacher'); ?>!</h1>
            <img src="../pictures/prof1.png" alt="Exam Dashboard" class="dashboard-image">
        </div>

        <?php if ($flash = getFlashMessage()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo $flash['message']; ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_classrooms']; ?></div>
                <div class="stat-label">Classrooms</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_students']; ?></div>
                <div class="stat-label">Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_exams']; ?></div>
                <div class="stat-label">Exams</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_attempts']; ?></div>
                <div class="stat-label">Exam Attempts</div>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Recent Exams -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2><i class="fas fa-file-alt"></i> Recent Exams</h2>
                    <a href="exams.php" class="view-all">View All</a>
                </div>
                <?php if (empty($recent_exams)): ?>
                    <p>No exams created yet.</p>
                <?php else: ?>
                    <ul class="exam-list">
                        <?php foreach ($recent_exams as $exam): ?>
                            <li class="exam-item">
                                <div class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></div>
                                <div class="exam-meta">
                                    <span><i class="fas fa-users"></i> <?php echo $exam['attempt_count']; ?> attempts</span>
                                    <span><i class="fas fa-chart-line"></i> 
                                        <?php echo $exam['average_score'] ? round($exam['average_score'], 1) . '' : 'No attempts'; ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Recent Activities -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Recent Activities</h2>
                </div>
                <?php if (empty($recent_activities)): ?>
                    <p>No recent activities.</p>
                <?php else: ?>
                    <ul class="activity-list">
                        <?php foreach ($recent_activities as $activity): ?>
                            <li class="activity-item">
                                <div class="activity-title">
                                    <?php echo htmlspecialchars($activity['student_name']); ?> 
                                    joined <?php echo htmlspecialchars($activity['classroom_name']); ?>
                                </div>
                                <div class="activity-meta">
                                    <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($activity['department']); ?></span>
                                    <span><i class="far fa-clock"></i> 
                                        <?php echo date('M j, Y', strtotime($activity['joined_at'])); ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <!-- Classrooms Section -->
        <div class="dashboard-card" style="margin-top: 20px;">
            <div class="card-header">
                <h2><i class="fas fa-chalkboard"></i> Your Classrooms</h2>
                <a href="classrooms.php" class="view-all">View All</a>
            </div>
            <?php if (empty($classrooms)): ?>
                <p>No classrooms created yet.</p>
            <?php else: ?>
                <div class="classroom-grid">
                    <?php foreach ($classrooms as $classroom): ?>
                        <div class="classroom-card">
                            <div class="classroom-header">
                                <div class="classroom-name"><?php echo htmlspecialchars($classroom['name']); ?></div>
                                <div class="classroom-department"><?php echo htmlspecialchars($classroom['department']); ?></div>
                            </div>
                            <div class="classroom-stats">
                                <span><i class="fas fa-users"></i> <?php echo $classroom['student_count']; ?> students</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>