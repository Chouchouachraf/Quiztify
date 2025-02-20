<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

checkRole('admin');

try {
    $conn = getDBConnection();
    
    // Get general statistics
    $stats = [
        'total_users' => $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'total_students' => $conn->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
        'total_teachers' => $conn->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn(),
        'total_exams' => $conn->query("SELECT COUNT(*) FROM exams")->fetchColumn(),
        'total_classrooms' => $conn->query("SELECT COUNT(*) FROM classrooms")->fetchColumn(),
        'total_attempts' => $conn->query("SELECT COUNT(*) FROM exam_attempts")->fetchColumn(),
        'avg_score' => $conn->query("SELECT AVG(score) FROM exam_attempts WHERE is_completed = 1")->fetchColumn()
    ];
    
    // Get exam statistics
    $exam_stats = $conn->query("
        SELECT 
            e.title,
            COUNT(ea.id) as attempt_count,
            AVG(ea.score) as avg_score,
            MIN(ea.score) as min_score,
            MAX(ea.score) as max_score
        FROM exams e
        LEFT JOIN exam_attempts ea ON e.id = ea.exam_id
        WHERE ea.is_completed = 1
        GROUP BY e.id
        ORDER BY attempt_count DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get department statistics
    $dept_stats = $conn->query("
        SELECT 
            department,
            COUNT(*) as student_count,
            (SELECT COUNT(*) FROM exam_attempts ea 
             JOIN users u2 ON ea.student_id = u2.id 
             WHERE u2.department = users.department) as attempt_count
        FROM users
        WHERE department IS NOT NULL
        GROUP BY department
        ORDER BY student_count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get monthly activity
    $monthly_activity = $conn->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as attempt_count,
            AVG(score) as avg_score
        FROM exam_attempts
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics - <?php echo SITE_NAME; ?></title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #357abd;
            --background-color: #f5f5f5;
            --text-color: #333;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: var(--background-color);
            color: var(--text-color);
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: white;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }

        .main-content {
            flex: 1;
            padding: 20px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 10px;
            color: var(--text-color);
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 5px;
        }

        .nav-link:hover {
            background: #f0f0f0;
        }

        .nav-link i {
            margin-right: 10px;
        }

        .nav-link.active {
            background: var(--primary-color);
            color: white;
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
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .table-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <h2 style="margin-bottom: 20px;">Admin Panel</h2>
            <nav>
                <a href="dashboard.php" class="nav-link">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="users.php" class="nav-link">
                    <i class="bi bi-people"></i> Users
                </a>
                <a href="exams.php" class="nav-link">
                    <i class="bi bi-file-text"></i> Exams
                </a>
                <a href="classrooms.php" class="nav-link">
                    <i class="bi bi-building"></i> Classrooms
                </a>
                <a href="statistics.php" class="nav-link active">
                    <i class="bi bi-graph-up"></i> Statistics
                </a>
                <a href="../logout.php" class="nav-link">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </nav>
        </div>

        <div class="main-content">
            <h1 style="margin-bottom: 30px;">System Statistics</h1>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <p class="stat-number"><?php echo $stats['total_users']; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Students</h3>
                    <p class="stat-number"><?php echo $stats['total_students']; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Teachers</h3>
                    <p class="stat-number"><?php echo $stats['total_teachers']; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Average Score</h3>
                    <p class="stat-number"><?php echo number_format($stats['avg_score'], 2); ?>%</p>
                </div>
            </div>

            <div class="chart-container">
                <h2>Monthly Activity</h2>
                <canvas id="monthlyChart"></canvas>
            </div>

            <div class="table-container">
                <h2>Top Exams</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Exam Title</th>
                            <th>Attempts</th>
                            <th>Average Score</th>
                            <th>Min Score</th>
                            <th>Max Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exam_stats as $exam): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                <td><?php echo $exam['attempt_count']; ?></td>
                                <td><?php echo number_format($exam['avg_score'], 2); ?>%</td>
                                <td><?php echo number_format($exam['min_score'], 2); ?>%</td>
                                <td><?php echo number_format($exam['max_score'], 2); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-container">
                <h2>Department Statistics</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Students</th>
                            <th>Total Attempts</th>
                            <th>Attempts per Student</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dept_stats as $dept): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($dept['department']); ?></td>
                                <td><?php echo $dept['student_count']; ?></td>
                                <td><?php echo $dept['attempt_count']; ?></td>
                                <td><?php echo number_format($dept['attempt_count'] / $dept['student_count'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Monthly Activity Chart
        const monthlyData = <?php echo json_encode($monthly_activity); ?>;
        const labels = monthlyData.map(item => item.month);
        const attempts = monthlyData.map(item => item.attempt_count);
        const scores = monthlyData.map(item => parseFloat(item.avg_score));

        new Chart(document.getElementById('monthlyChart'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Attempts',
                    data: attempts,
                    borderColor: '#4a90e2',
                    yAxisID: 'y'
                }, {
                    label: 'Average Score',
                    data: scores,
                    borderColor: '#28a745',
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Number of Attempts'
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Average Score (%)'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
