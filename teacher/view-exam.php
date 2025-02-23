<?php
require_once '../includes/functions.php';
checkRole('teacher');

$examId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$conn = getDBConnection();

// Get exam details
$stmt = $conn->prepare("
    SELECT e.*, 
           COUNT(DISTINCT q.id) as total_questions
    FROM exams e
    LEFT JOIN questions q ON e.id = q.exam_id
    WHERE e.id = ? AND e.created_by = ?
    GROUP BY e.id
");
$stmt->execute([$examId, $_SESSION['user_id']]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    setFlashMessage('error', 'Exam not found or access denied.');
    header('Location: manage-exams.php');
    exit;
}

// Get all completed attempts with student info
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) ? $_GET['order'] : 'asc';

$orderBy = match($sort) {
    'score' => "ea.score " . ($order === 'asc' ? 'ASC' : 'DESC'),
    'date' => "ea.end_time " . ($order === 'asc' ? 'ASC' : 'DESC'),
    'name' => "u.full_name " . ($order === 'asc' ? 'ASC' : 'DESC'),
    default => "u.full_name ASC"
};

$stmt = $conn->prepare("
    SELECT 
        ea.id as attempt_id,
        ea.score,
        ea.end_time,
        u.full_name,
        c.name as classroom_name,
        COUNT(DISTINCT sa.id) as questions_answered
    FROM exam_attempts ea
    JOIN users u ON ea.student_id = u.id
    JOIN classroom_students cs ON u.id = cs.student_id
    JOIN classrooms c ON cs.classroom_id = c.id
    LEFT JOIN student_answers sa ON ea.id = sa.attempt_id
    WHERE ea.exam_id = ? AND ea.is_completed = 1
    GROUP BY ea.id
    ORDER BY " . $orderBy
);
$stmt->execute([$examId]);
$attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Results - <?php echo htmlspecialchars($exam['title']); ?></title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #9b59b6;
            --light-color: #ecf0f1;
            --text-dark: #2c3e50;
            --text-light: #95a5a6;
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
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: var(--text-dark);
            margin-bottom: 15px;
            font-size: 2em;
        }

        .exam-info {
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .students-list {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .student-item {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid var(--light-color);
            transition: background-color 0.2s ease;
        }

        .student-item:hover {
            background-color: #f8f9fa;
        }

        .student-info {
            flex: 1;
        }

        .student-name {
            color: var(--text-dark);
            font-size: 1.1em;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .student-class {
            color: var(--text-light);
            font-size: 0.9em;
        }

        .submission-date {
            color: var(--text-light);
            font-size: 0.9em;
            margin-right: 20px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: opacity 0.2s ease;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn-primary {
            background: var(--accent-color);
        }

        .btn-secondary {
            background: var(--secondary-color);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            margin-top: 30px;
        }

        .empty-state i {
            font-size: 64px;
            color: var(--text-light);
            margin-bottom: 20px;
        }

        .empty-state h2 {
            color: var(--text-dark);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--text-light);
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/teacher-nav.php'; ?>

        <div class="header">
            <h1><?php echo htmlspecialchars($exam['title']); ?></h1>
            <div class="exam-info">
                <span><i class='bx bx-help-circle'></i> <?php echo $exam['total_questions']; ?> Questions</span>
                <span><i class='bx bx-user'></i> <?php echo count($attempts); ?> Submissions</span>
                <a href="manage-exams.php" class="btn btn-secondary">
                    <i class='bx bx-arrow-back'></i> Back to Exams
                </a>
            </div>
        </div>

        <?php if (empty($attempts)): ?>
            <div class="empty-state">
                <i class='bx bx-info-circle'></i>
                <h2>No Submissions Yet</h2>
                <p>No students have completed this exam yet.</p>
            </div>
        <?php else: ?>
            <div class="students-list">
                <?php foreach ($attempts as $attempt): ?>
                    <div class="student-item">
                        <div class="student-info">
                            <div class="student-name">
                                <?php echo htmlspecialchars($attempt['full_name']); ?>
                            </div>
                            <div class="student-class">
                                <?php echo htmlspecialchars($attempt['classroom_name']); ?>
                            </div>
                        </div>
                        
                        <div class="submission-date">
                            <i class='bx bx-calendar'></i>
                            <?php echo date('M j, Y g:i A', strtotime($attempt['end_time'])); ?>
                        </div>
                        
                        <a href="view-attempt.php?id=<?php echo $attempt['attempt_id']; ?>" class="btn btn-primary">
                            <i class='bx bx-show'></i> View Details
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>