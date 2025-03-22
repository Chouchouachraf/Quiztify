<?php
require_once '../includes/functions.php';
checkRole('teacher');

$conn = getDBConnection();
$userId = $_SESSION['user_id'];

try {
    // Fetch teacher profile data with statistics
    $stmt = $conn->prepare("
        SELECT 
            u.*,
            COUNT(DISTINCT e.id) as total_exams,
            COUNT(DISTINCT ea.id) as total_attempts,
            (
                SELECT COUNT(DISTINCT student_id) 
                FROM exam_attempts ea2 
                JOIN exams e2 ON ea2.exam_id = e2.id 
                WHERE e2.created_by = u.id
            ) as total_students,
            ROUND(AVG(ea.score), 1) as average_score
        FROM users u
        LEFT JOIN exams e ON u.id = e.created_by
        LEFT JOIN exam_attempts ea ON e.id = ea.exam_id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent activity
    $stmt = $conn->prepare("
        SELECT 
            'exam_created' as type,
            e.title as title,
            e.created_at as date,
            NULL as student_name,
            NULL as score
        FROM exams e
        WHERE e.created_by = ?
        
        UNION ALL
        
        SELECT 
            'exam_attempt' as type,
            e.title as title,
            ea.end_time as date,
            u.full_name as student_name,
            ea.score
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        JOIN users u ON ea.student_id = u.id
        WHERE e.created_by = ?
        
        ORDER BY date DESC
        LIMIT 10
    ");
    
    $stmt->execute([$userId, $userId]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error in profile.php: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading profile data.');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Profile - Quiztify</title>
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

        .profile-header {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 30px;
            align-items: center;
        }

        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            text-transform: uppercase;
        }

        .profile-info h1 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .profile-meta {
            display: flex;
            gap: 20px;
            color: #666;
            font-size: 0.9rem;
        }

        .profile-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .activity-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .section-header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f0f3f6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3498db;
        }

        .activity-details {
            flex: 1;
        }

        .activity-title {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .activity-meta {
            color: #666;
            font-size: 0.9rem;
        }

        .score-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .score-pass {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .score-fail {
            background: #fde8e8;
            color: #c62828;
        }

        @media (max-width: 768px) {
            .profile-header {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .profile-avatar {
                margin: 0 auto;
            }

            .profile-meta {
                justify-content: center;
                flex-wrap: wrap;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/teacher-nav.php'; ?>

        <?php if ($flash = getFlashMessage()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo $flash['message']; ?>
            </div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($profile['full_name'], 0, 2)); ?>
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($profile['full_name']); ?></h1>
                <div class="profile-meta">
                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($profile['email']); ?></span>
                    <?php if (!empty($profile['phone'])): ?>
                        <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($profile['phone']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($profile['department'])): ?>
                        <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($profile['department']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $profile['total_exams']; ?></div>
                <div class="stat-label">Total Exams</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $profile['total_students']; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $profile['total_attempts']; ?></div>
                <div class="stat-label">Total Attempts</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $profile['average_score'] ?? '0'; ?>%</div>
                <div class="stat-label">Average Score</div>
            </div>
        </div>

        <div class="activity-section">
            <div class="section-header">
                <h2><i class="fas fa-history"></i> Recent Activity</h2>
            </div>
            <?php if (empty($activities)): ?>
                <p class="text-center">No recent activity</p>
            <?php else: ?>
                <ul class="activity-list">
                    <?php foreach ($activities as $activity): ?>
                        <li class="activity-item">
                            <div class="activity-icon">
                                <i class="fas <?php echo $activity['type'] === 'exam_created' ? 'fa-file-alt' : 'fa-pen'; ?>"></i>
                            </div>
                            <div class="activity-details">
                                <div class="activity-title">
                                    <?php if ($activity['type'] === 'exam_created'): ?>
                                        Created exam: <?php echo htmlspecialchars($activity['title']); ?>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($activity['student_name']); ?> attempted 
                                        <?php echo htmlspecialchars($activity['title']); ?>
                                        <span class="score-badge <?php echo $activity['score'] >= 60 ? 'score-pass' : 'score-fail'; ?>">
                                            <?php echo $activity['score']; ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-meta">
                                    <i class="far fa-clock"></i> 
                                    <?php echo date('M j, Y g:i A', strtotime($activity['date'])); ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>