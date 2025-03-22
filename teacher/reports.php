<?php
require_once '../includes/functions.php';
checkRole('teacher');

$conn = getDBConnection();

try {
    // Get overall statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT e.id) as total_exams,
            COUNT(DISTINCT ea.id) as total_attempts,
            COUNT(DISTINCT ea.student_id) as total_students,
            AVG(ea.score) as average_score,
            (
                SELECT COUNT(DISTINCT ea2.id) 
                FROM exam_attempts ea2 
                JOIN exams e2 ON ea2.exam_id = e2.id 
                WHERE e2.created_by = ? 
                AND ea2.score >= e2.passing_score
            ) as passed_attempts
        FROM exams e
        LEFT JOIN exam_attempts ea ON e.id = ea.exam_id
        WHERE e.created_by = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $overall_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get exam-wise statistics
    $stmt = $conn->prepare("
        SELECT 
            e.id,
            e.title,
            e.passing_score,
            COUNT(DISTINCT ea.id) as attempt_count,
            COUNT(DISTINCT ea.student_id) as student_count,
            AVG(ea.score) as avg_score,
            MIN(ea.score) as min_score,
            MAX(ea.score) as max_score,
            COUNT(CASE WHEN ea.score >= e.passing_score THEN 1 END) as passed_count
        FROM exams e
        LEFT JOIN exam_attempts ea ON e.id = ea.exam_id
        WHERE e.created_by = ?
        GROUP BY e.id
        ORDER BY e.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $exam_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent attempts
    $stmt = $conn->prepare("
        SELECT 
            ea.id,
            ea.score,
            ea.end_time,
            e.title as exam_title,
            u.full_name as student_name,
            e.passing_score
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        JOIN users u ON ea.student_id = u.id
        WHERE e.created_by = ?
        ORDER BY ea.end_time DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error in reports.php: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while generating reports.');
    $overall_stats = [];
    $exam_stats = [];
    $recent_attempts = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Teacher Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            color: #3498db;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .recent-attempts {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .attempts-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .attempts-table th,
        .attempts-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .attempts-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .score-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .score-pass {
            background: #d4edda;
            color: #155724;
        }

        .score-fail {
            background: #f8d7da;
            color: #721c24;
        }

        .section-title {
            margin-bottom: 20px;
            color: #2c3e50;
            font-size: 20px;
        }

        @media (max-width: 768px) {
            .charts-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/teacher-nav.php'; ?>

        <h1 class="section-title">Reports & Analytics</h1>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $overall_stats['total_exams']; ?></div>
                <div class="stat-label">Total Exams</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $overall_stats['total_attempts']; ?></div>
                <div class="stat-label">Total Attempts</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $overall_stats['total_students']; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($overall_stats['average_score'], 1); ?>%</div>
                <div class="stat-label">Average Score</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                    $pass_rate = $overall_stats['total_attempts'] ? 
                        ($overall_stats['passed_attempts'] / $overall_stats['total_attempts'] * 100) : 0;
                    echo number_format($pass_rate, 1);
                    ?>%
                </div>
                <div class="stat-label">Pass Rate</div>
            </div>
        </div>

        <div class="charts-container">
            <div class="chart-card">
                <h2 class="section-title">Exam Performance</h2>
                <canvas id="examChart"></canvas>
            </div>
            <div class="chart-card">
                <h2 class="section-title">Score Distribution</h2>
                <canvas id="scoreChart"></canvas>
            </div>
        </div>

        <div class="recent-attempts">
            <h2 class="section-title">Recent Attempts</h2>
            <table class="attempts-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Exam</th>
                        <th>Score</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_attempts as $attempt): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($attempt['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($attempt['exam_title']); ?></td>
                            <td><?php echo number_format($attempt['score'], 1); ?>%</td>
                            <td>
                                <span class="score-badge <?php echo $attempt['score'] >= $attempt['passing_score'] ? 'score-pass' : 'score-fail'; ?>">
                                    <?php echo $attempt['score'] >= $attempt['passing_score'] ? 'Passed' : 'Failed'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y g:i A', strtotime($attempt['end_time'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Exam Performance Chart
        const examData = <?php echo json_encode($exam_stats); ?>;
        const examCtx = document.getElementById('examChart').getContext('2d');
        new Chart(examCtx, {
            type: 'bar',
            data: {
                labels: examData.map(exam => exam.title),
                datasets: [{
                    label: 'Average Score',
                    data: examData.map(exam => exam.avg_score),
                    backgroundColor: '#3498db'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });

        // Score Distribution Chart
        const scoreCtx = document.getElementById('scoreChart').getContext('2d');
        new Chart(scoreCtx, {
            type: 'pie',
            data: {
                labels: ['90-100%', '80-89%', '70-79%', '60-69%', 'Below 60%'],
                datasets: [{
                    data: [
                        examData.reduce((count, exam) => count + (exam.avg_score >= 90 ? 1 : 0), 0),
                        examData.reduce((count, exam) => count + (exam.avg_score >= 80 && exam.avg_score < 90 ? 1 : 0), 0),
                        examData.reduce((count, exam) => count + (exam.avg_score >= 70 && exam.avg_score < 80 ? 1 : 0), 0),
                        examData.reduce((count, exam) => count + (exam.avg_score >= 60 && exam.avg_score < 70 ? 1 : 0), 0),
                        examData.reduce((count, exam) => count + (exam.avg_score < 60 ? 1 : 0), 0)
                    ],
                    backgroundColor: [
                        '#2ecc71',
                        '#3498db',
                        '#f1c40f',
                        '#e67e22',
                        '#e74c3c'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>