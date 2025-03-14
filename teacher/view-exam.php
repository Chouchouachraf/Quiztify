<?php
session_start();
require_once '../includes/functions.php';
checkRole('teacher');

$examId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$conn = getDBConnection();

// Get exam details
$stmt = $conn->prepare("
    SELECT 
        e.*,
        COUNT(DISTINCT q.id) as total_questions
    FROM exams e
    LEFT JOIN questions q ON e.id = q.exam_id
    WHERE e.id = ? AND e.created_by = ?
    GROUP BY 
        e.id, e.title, e.description, e.is_published, 
        e.start_date, e.end_date, e.created_by, 
        e.created_at, e.total_points, e.attempts_allowed, 
        e.passing_score, e.has_timer, e.duration_minutes
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

// Fallback for PHP versions < 8.0
$orderBy = '';
switch($sort) {
    case 'score':
        $orderBy = "COALESCE(ea.score, 0) " . ($order === 'asc' ? 'ASC' : 'DESC');
        break;
    case 'date':
        $orderBy = "COALESCE(ea.end_time, '9999-12-31') " . ($order === 'asc' ? 'ASC' : 'DESC');
        break;
    case 'name':
    default:
        $orderBy = "u.full_name " . ($order === 'asc' ? 'ASC' : 'DESC');
}

$stmt = $conn->prepare("
    SELECT 
        u.id as student_id,
        u.full_name,
        c.name as classroom_name,
        ea.id as attempt_id,
        ea.score,
        ea.end_time,
        ea.is_completed,
        COUNT(DISTINCT sa.id) as questions_answered
    FROM exam_classrooms ec
    JOIN classrooms c ON ec.classroom_id = c.id
    JOIN classroom_students cs ON c.id = cs.classroom_id
    JOIN users u ON cs.student_id = u.id
    LEFT JOIN exam_attempts ea ON ec.exam_id = ea.exam_id AND ea.student_id = u.id
    LEFT JOIN student_answers sa ON ea.id = sa.attempt_id
    WHERE ec.exam_id = ?
    GROUP BY 
        u.id,
        u.full_name,
        c.name,
        ea.id, 
        ea.score, 
        ea.end_time,
        ea.is_completed
    ORDER BY $orderBy
");
$stmt->execute([$examId]);
$attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug information
error_log("Exam ID: " . $examId . " - Found " . count($attempts) . " students/attempts");
foreach ($attempts as $index => $attempt) {
    error_log("Student " . ($index + 1) . ": " . $attempt['full_name'] . 
              " - Attempt ID: " . ($attempt['attempt_id'] ?? 'NULL') . 
              " - Is Completed: " . ($attempt['is_completed'] ?? 'NULL'));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Results - <?php echo htmlspecialchars($exam['title']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #9b59b6;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f1c40f;
            --light-color: #ecf0f1;
            --text-dark: #2c3e50;
            --text-light: #95a5a6;
            --background-color: #f5f6fa;
        }

        [data-theme="dark"] {
            --background-color: #1a1a1a;
            --primary-color: #2980b9;
            --secondary-color: #3498db;
            --accent-color: #9b59b6;
            --success-color: #44bb77;
            --danger-color: #ff5555;
            --warning-color: #ffcc00;
            --light-color: #333333;
            --text-dark: #ffffff;
            --text-light: #aaaaaa;
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
            color: var(--text-dark);
            transition: background-color 0.3s, color 0.3s;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }

        .breadcrumb a {
            color: var(--secondary-color);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .breadcrumb i {
            font-size: 0.8em;
            color: var(--text-light);
        }

        .breadcrumb span {
            color: var(--text-dark);
        }

        .header {
            background: var(--light-color);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            color: var(--text-dark);
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
        }

        .header h1 {
            color: var(--text-dark);
            margin-bottom: 15px;
            font-size: 2em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .exam-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--background-color);
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: var(--background-color);
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .info-item i {
            font-size: 1.5rem;
            color: var(--secondary-color);
        }

        .students-list {
            background: var(--light-color);
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .student-item {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid var(--background-color);
            transition: all 0.3s ease;
        }

        .student-item:hover {
            background-color: rgba(0, 0, 0, 0.1);
            transform: translateX(5px);
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
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .submission-date {
            color: var(--text-light);
            font-size: 0.9em;
            margin-right: 20px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .score-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 500;
            margin-right: 20px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .score-badge.high {
            background: var(--success-color);
            color: white;
        }

        .score-badge.medium {
            background: var(--warning-color);
            color: var(--text-dark);
        }

        .score-badge.low {
            background: var(--danger-color);
            color: white;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--accent-color);
            color: white;
        }

        .btn-secondary {
            background: var(--secondary-color);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--light-color);
            border-radius: 15px;
            margin-top: 30px;
            color: var(--text-dark);
        }

        .empty-state i {
            font-size: 64px;
            color: var(--text-light);
            margin-bottom: 20px;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .header {
                padding: 20px;
            }

            .student-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .submission-date {
                margin-right: 0;
            }
            
            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/teacher-nav.php'; ?>

        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a>
            <i class="fas fa-chevron-right"></i>
            <a href="exams.php">Exams</a>
            <i class="fas fa-chevron-right"></i>
            <span><?php echo htmlspecialchars($exam['title']); ?></span>
        </div>

        <div class="header">
            <h1>
                <i class="fas fa-file-alt"></i>
                <?php echo htmlspecialchars($exam['title']); ?>
            </h1>
            <div class="exam-info">
                <div class="info-item">
                    <i class="fas fa-question-circle"></i>
                    <div>
                        <small>Questions</small>
                        <div><?php echo $exam['total_questions']; ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-users"></i>
                    <div>
                        <small>Submissions</small>
                        <div><?php echo count($attempts); ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <small>Duration</small>
                        <div><?php echo $exam['duration_minutes']; ?> minutes</div>
                    </div>
                </div>
            </div>
            <div class="actions">
                <a href="exams.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Exams
                </a>
                <a href="edit-exam.php?id=<?php echo $examId; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Exam
                </a>
            </div>
        </div>

        <?php if (empty($attempts)): ?>
            <div class="empty-state">
                <i class="fas fa-info-circle"></i>
                <h2>No Students Available</h2>
                <p>No students have access to this exam yet.</p>
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
                                <i class="fas fa-book"></i>
                                <?php echo htmlspecialchars($attempt['classroom_name']); ?>
                            </div>
                        </div>
                        
                        <?php if (isset($attempt['attempt_id']) && $attempt['attempt_id']): ?>
                            <?php if (isset($attempt['is_completed']) && $attempt['is_completed']): ?>
                                <div class="submission-date">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo date('M j, Y g:i A', strtotime($attempt['end_time'])); ?>
                                </div>

                                <div class="score-badge <?php 
                                    echo $attempt['score'] >= 80 ? 'high' : 
                                         ($attempt['score'] >= 60 ? 'medium' : 'low'); 
                                ?>">
                                    <i class="fas fa-award"></i>
                                    <?php echo number_format($attempt['score'], 1); ?>%
                                </div>
                                
                                <a href="view-attempt.php?id=<?php echo $attempt['attempt_id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            <?php else: ?>
                                <div class="submission-date">
                                    <i class="fas fa-clock"></i>
                                    <span>In progress</span>
                                </div>
                                
                                <div class="score-badge medium">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    In Progress
                                </div>
                                
                                <a href="view-attempt.php?id=<?php echo $attempt['attempt_id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-eye"></i> View Progress
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="submission-date">
                                <i class="fas fa-clock"></i>
                                <span>Not attempted yet</span>
                            </div>
                            
                            <div class="score-badge medium">
                                <i class="fas fa-hourglass-half"></i>
                                Pending
                            </div>
                            
                            <span class="btn btn-secondary" style="opacity: 0.6; cursor: not-allowed;">
                                <i class="fas fa-eye-slash"></i> No Data
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check for saved theme in localStorage
            const theme = localStorage.getItem('theme') || 'light';
            document.body.dataset.theme = theme;
            
            // This ensures the theme toggle in the navbar will work with this page
            // The actual toggle button is handled in teacher-nav.php
        });
    </script>
</body>
</html>