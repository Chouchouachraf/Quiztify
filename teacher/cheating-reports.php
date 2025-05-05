<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkRole('teacher');

$conn = getDBConnection();
$filter = $_GET['filter'] ?? 'all';

try {
    // Get all cheating reports for exams created by this teacher
    $query = "
        SELECT 
            eal.id,
            eal.attempt_id,
            eal.event_type,
            eal.details,
            eal.created_at,
            e.id as exam_id,
            e.title as exam_title,
            u.id as student_id,
            u.full_name as student_name,
            u.email as student_email
        FROM exam_attempt_logs eal
        JOIN exam_attempts ea ON eal.attempt_id = ea.id
        JOIN exams e ON ea.exam_id = e.id
        JOIN users u ON eal.student_id = u.id
        WHERE e.created_by = ? 
        AND eal.event_type = 'cheating_attempt'
    ";
    
    // Apply filters if needed
    if ($filter === 'recent') {
        $query .= " AND eal.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    } elseif ($filter === 'high') {
        $query .= " GROUP BY eal.attempt_id, eal.student_id 
                    HAVING COUNT(*) > 5
                    ORDER BY COUNT(*) DESC";
    } else {
        $query .= " ORDER BY eal.created_at DESC";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get summary statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT eal.id) as total_incidents,
            COUNT(DISTINCT eal.attempt_id) as attempts_with_cheating,
            COUNT(DISTINCT eal.student_id) as students_with_cheating,
            COUNT(DISTINCT e.id) as exams_with_cheating
        FROM exam_attempt_logs eal
        JOIN exam_attempts ea ON eal.attempt_id = ea.id
        JOIN exams e ON ea.exam_id = e.id
        WHERE e.created_by = ? 
        AND eal.event_type = 'cheating_attempt'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error in cheating-reports.php: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while retrieving cheating reports.');
    $reports = [];
    $stats = [
        'total_incidents' => 0,
        'attempts_with_cheating' => 0,
        'students_with_cheating' => 0,
        'exams_with_cheating' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cheating Reports - Teacher Dashboard</title>
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #e74c3c;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .filter-tabs {
            display: flex;
            margin-bottom: 20px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .filter-tab {
            padding: 10px 20px;
            text-align: center;
            flex: 1;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            color: #2c3e50;
        }

        .filter-tab.active {
            background-color: #3498db;
            color: white;
        }

        .reports-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .reports-table th,
        .reports-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .reports-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .reports-table tr:hover {
            background-color: #f5f5f5;
        }

        .cheating-type {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            background-color: #ffebee;
            color: #e53935;
        }

        .action-btns {
            display: flex;
            gap: 5px;
        }

        .view-btn, .contact-btn {
            padding: 4px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            text-align: center;
        }

        .view-btn {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .contact-btn {
            background-color: #e8f5e9;
            color: #388e3c;
        }

        .no-reports {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            color: #666;
        }

        .section-title {
            margin-bottom: 20px;
            color: #2c3e50;
            font-size: 20px;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 10px;
            color: #e74c3c;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .reports-table {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/teacher-nav.php'; ?>

        <h1 class="section-title">
            <i class="fas fa-exclamation-triangle"></i>
            Cheating Reports
        </h1>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_incidents']; ?></div>
                <div class="stat-label">Total Incidents</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['attempts_with_cheating']; ?></div>
                <div class="stat-label">Attempts with Cheating</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['students_with_cheating']; ?></div>
                <div class="stat-label">Students with Cheating</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['exams_with_cheating']; ?></div>
                <div class="stat-label">Exams with Cheating</div>
            </div>
        </div>

        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                All Reports
            </a>
            <a href="?filter=recent" class="filter-tab <?php echo $filter === 'recent' ? 'active' : ''; ?>">
                Last 24 Hours
            </a>
            <a href="?filter=high" class="filter-tab <?php echo $filter === 'high' ? 'active' : ''; ?>">
                High Risk Students
            </a>
        </div>

        <?php if (count($reports) > 0): ?>
            <table class="reports-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Student</th>
                        <th>Exam</th>
                        <th>Cheating Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td><?php echo date('M j, Y g:i:s A', strtotime($report['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($report['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($report['exam_title']); ?></td>
                            <td>
                                <span class="cheating-type">
                                    <?php 
                                    $type = $report['details'];
                                    if ($type === 'tab_switch') {
                                        echo 'Left Exam Tab';
                                    } elseif ($type === 'right_click') {
                                        echo 'Right Click Attempt';
                                    } elseif ($type === 'copy_paste') {
                                        echo 'Copy/Paste Attempt';
                                    } elseif ($type === 'navigation_attempt') {
                                        echo 'Navigation Attempt';
                                    } else {
                                        echo htmlspecialchars($type);
                                    }
                                    ?>
                                </span>
                            </td>
                            <td class="action-btns">
                                <a href="view-attempt.php?id=<?php echo $report['attempt_id']; ?>" class="view-btn">
                                    View Attempt
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-reports">
                <i class="fas fa-check-circle" style="font-size: 48px; color: #4caf50; margin-bottom: 15px;"></i>
                <h3>No Cheating Reports Found</h3>
                <p>All your students seem to be following the academic integrity guidelines.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Optional: Add some interactive features with JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight rows when clicked
            const rows = document.querySelectorAll('.reports-table tbody tr');
            rows.forEach(row => {
                row.addEventListener('click', function() {
                    this.classList.toggle('selected');
                });
            });
        });
    </script>
</body>
</html> 