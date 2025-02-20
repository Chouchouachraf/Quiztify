<?php
require_once '../includes/functions.php';
checkRole('teacher');

$examId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$conn = getDBConnection();

try {
    // Get exam details
    $stmt = $conn->prepare("
        SELECT e.*, 
               COUNT(DISTINCT q.id) as total_questions,
               COUNT(DISTINCT ea.id) as total_attempts,
               AVG(ea.score) as average_score
        FROM exams e
        LEFT JOIN questions q ON e.id = q.exam_id
        LEFT JOIN exam_attempts ea ON e.id = ea.exam_id
        WHERE e.id = ? AND e.created_by = ?
        GROUP BY e.id
    ");
    $stmt->execute([$examId, $_SESSION['user_id']]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        setFlashMessage('error', 'Exam not found or access denied.');
        header('Location: dashboard.php');
        exit;
    }

    // Get all attempts for this exam
    $stmt = $conn->prepare("
        SELECT 
            ea.*,
            u.full_name as student_name,
            u.email as student_email,
            COUNT(DISTINCT sa.id) as questions_answered
        FROM exam_attempts ea
        JOIN users u ON ea.student_id = u.id
        LEFT JOIN student_answers sa ON ea.id = sa.attempt_id
        WHERE ea.exam_id = ?
        GROUP BY ea.id
        ORDER BY ea.end_time DESC
    ");
    $stmt->execute([$examId]);
    $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error in view-exam.php: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading exam data.');
    header('Location: dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Exam Results - <?php echo htmlspecialchars($exam['title']); ?></title>
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
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .exam-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .exam-title {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .exam-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #3498db;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .attempts-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 20px;
            overflow: hidden;
        }

        .attempts-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .attempts-table th,
        .attempts-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .attempts-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .attempts-table tr:hover {
            background: #f8f9fa;
        }

        .score-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: 500;
            font-size: 14px;
        }

        .score-pass {
            background: #d4edda;
            color: #155724;
        }

        .score-fail {
            background: #f8d7da;
            color: #721c24;
        }

        .btn-view {
            display: inline-block;
            padding: 6px 12px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn-view:hover {
            background: #2980b9;
        }

        .no-attempts {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        @media (max-width: 768px) {
            .exam-stats {
                grid-template-columns: 1fr;
            }

            .attempts-table {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/teacher-nav.php'; ?>

        <div class="exam-header">
            <h1 class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></h1>
            <p><?php echo htmlspecialchars($exam['description']); ?></p>
            
            <div class="exam-stats">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $exam['total_attempts']; ?></div>
                    <div class="stat-label">Total Attempts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($exam['average_score'], 1); ?>%</div>
                    <div class="stat-label">Average Score</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $exam['total_questions']; ?></div>
                    <div class="stat-label">Questions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $exam['passing_score']; ?>%</div>
                    <div class="stat-label">Passing Score</div>
                </div>
            </div>
        </div>

        <div class="attempts-table">
            <?php if (empty($attempts)): ?>
                <div class="no-attempts">
                    <i class="fas fa-info-circle fa-2x"></i>
                    <p>No attempts have been made for this exam yet.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Email</th>
                            <th>Completion Time</th>
                            <th>Questions Answered</th>
                            <th>Score</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attempts as $attempt): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($attempt['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($attempt['student_email']); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($attempt['end_time'])); ?></td>
                                <td><?php echo $attempt['questions_answered']; ?>/<?php echo $exam['total_questions']; ?></td>
                                <td><?php echo number_format($attempt['score'], 1); ?>%</td>
                                <td>
                                    <span class="score-badge <?php echo $attempt['score'] >= $exam['passing_score'] ? 'score-pass' : 'score-fail'; ?>">
                                        <?php echo $attempt['score'] >= $exam['passing_score'] ? 'Passed' : 'Failed'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view-attempt.php?id=<?php echo $attempt['id']; ?>" class="btn-view">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>