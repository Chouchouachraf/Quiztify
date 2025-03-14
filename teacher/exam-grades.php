<?php
require_once '../includes/functions.php';
checkRole('teacher');

$conn = getDBConnection();
$teacher_id = $_SESSION['user_id'];

// Get all exams created by this teacher with student attempts
$stmt = $conn->prepare("SELECT 
    e.id as exam_id,
    e.title as exam_title,
    e.total_points,
    c.name as classroom_name,
    COUNT(DISTINCT ea.student_id) as total_students,
    COUNT(CASE WHEN ea.score >= e.passing_score THEN 1 END) as passed_students,
    AVG(ea.score) as average_score
FROM exams e
JOIN exam_classrooms ec ON e.id = ec.exam_id
JOIN classrooms c ON ec.classroom_id = c.id
LEFT JOIN exam_attempts ea ON e.id = ea.exam_id AND ea.is_completed = 1
WHERE e.created_by = ?
GROUP BY e.id, e.title, e.total_points, c.name
ORDER BY e.created_at DESC");
$stmt->execute([$teacher_id]);
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Get user data from session
$userInitials = isset($_SESSION['full_name']) ? strtoupper(substr($_SESSION['full_name'], 0, 2)) : 'T';
$userName = $_SESSION['full_name'] ?? 'Teacher';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Grades - QuizTify</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            --text-color: #2c3e50;
        }

        [data-theme="dark"] {
            --background-color: #1a1a1a;
            --text-color: #ffffff;
            --primary-color: #2980b9;
            --secondary-color: #3498db;
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
            color: var(--text-color);
        }

        .navbar {
            background: var(--background-color);
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
            color: var(--secondary-color);
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
            color: var(--secondary-color);
        }

        .nav-link.active {
            background: var(--secondary-color);
            color: var(--light-color);
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
            background: var(--secondary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--light-color);
            font-weight: bold;
        }

        .logout-btn {
            padding: 8px 16px;
            border: none;
            background: var(--danger-color);
            color: var(--light-color);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .logout-btn:hover {
            background: #c0392b;
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--background-color);
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

        #theme-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            background: var(--secondary-color);
            color: var(--light-color);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        #theme-toggle:hover {
            background: var(--primary-color);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .exam-card {
            background: var(--light-color);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .exam-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--background-color);
        }

        .exam-title {
            font-size: 1.4em;
            color: var(--primary-color);
            margin: 0;
        }

        .exam-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: var(--background-color);
            border-radius: 8px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-label {
            color: var(--text-color);
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 1.2em;
            font-weight: 500;
            color: var(--primary-color);
        }

        .grades-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .grades-table th,
        .grades-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--background-color);
        }

        .grades-table th {
            background: var(--background-color);
            font-weight: 500;
            color: var(--primary-color);
        }

        .toggle-grades {
            background: none;
            border: none;
            color: var(--secondary-color);
            cursor: pointer;
            padding: 5px 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .student-grades {
            display: none;
        }

        .student-grades.show {
            display: table-row-group;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--light-color);
            border-radius: 15px;
            margin-top: 30px;
            color: var(--text-color);
        }

        .empty-state i {
            font-size: 64px;
            color: var(--text-light);
            margin-bottom: 20px;
        }

        .action-link {
            color: var(--secondary-color);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .action-link:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }

        /* Added CSS for the image */
        .exam-image {
            max-width: 100px;
            height: auto;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="nav-brand">
                <i class="fas fa-graduation-cap"></i>
                QuizTify
            </a>

            <div class="nav-links">
                <a href="dashboard.php" class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <a href="exams.php" class="nav-link <?php echo in_array($current_page, ['exams.php', 'view-exam.php', 'view-attempt.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    Exams
                </a>
                <a href="exam-grades.php" class="nav-link <?php echo $current_page === 'exam-grades.php' ? 'active' : ''; ?>">
                    <i class="fas fa-check-square"></i>
                    Grade Exams
                </a>
                <a href="classrooms.php" class="nav-link <?php echo $current_page === 'classrooms.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chalkboard"></i>
                    Classrooms
                </a>
                <a href="results.php" class="nav-link <?php echo $current_page === 'results.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    Results
                </a>
            </div>

            <div class="user-menu">
                <div class="user-avatar">
                    <?php echo $userInitials; ?>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <h1>Exam Grades</h1>
        
        <!-- Added image -->
        <img src="../pictures/exam.png" alt="exam-page" class="exam-image">

        <?php if (empty($exams)): ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <h2>No Exams Found</h2>
                <p>You haven't created any exams yet or no students have attempted your exams.</p>
                <p><a href="create-exam.php" class="action-link"><i class="fas fa-plus"></i> Create an Exam</a></p>
            </div>
        <?php else: ?>
            <?php foreach ($exams as $exam): ?>
                <div class="exam-card">
                    <div class="exam-header">
                        <h2 class="exam-title"><?= htmlspecialchars($exam['exam_title']) ?></h2>
                        <span class="classroom"><?= htmlspecialchars($exam['classroom_name']) ?></span>
                    </div>

                    <div class="exam-stats">
                        <div class="stat-item">
                            <div class="stat-label">Total Students</div>
                            <div class="stat-value"><?= $exam['total_students'] ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Passed</div>
                            <div class="stat-value"><?= $exam['passed_students'] ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Average Score</div>
                            <div class="stat-value">
                                <?= number_format($exam['average_score'], 1) ?>
                            </div>
                        </div>
                    </div>

                    <button class="toggle-grades" onclick="toggleGrades(<?= $exam['exam_id'] ?>)">
                        <i class='bx bx-chevron-down'></i> View Student Grades
                    </button>

                    <table class="grades-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Score</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody id="grades-<?= $exam['exam_id'] ?>" class="student-grades">
                            <?php
                            $stmt = $conn->prepare("SELECT 
                                u.full_name,
                                ea.score,
                                ea.end_time,
                                ea.id as attempt_id
                            FROM exam_attempts ea
                            JOIN users u ON ea.student_id = u.id
                            WHERE ea.exam_id = ? AND ea.is_completed = 1
                            ORDER BY ea.score DESC");
                            $stmt->execute([$exam['exam_id']]);
                            $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (empty($attempts)): ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; padding: 20px;">
                                        No students have completed this exam yet.
                                    </td>
                                </tr>
                            <?php else:
                                foreach ($attempts as $attempt):
                            ?>
                                    <tr>
                                        <td><?= htmlspecialchars($attempt['full_name']) ?></td>
                                        <td>
                                            <?= number_format($attempt['score'], 1) ?>
                                        </td>
                                        <td><?= date('M j, Y g:i A', strtotime($attempt['end_time'])) ?></td>
                                    </tr>
                                <?php endforeach; 
                            endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        function toggleGrades(examId) {
            const gradesTable = document.getElementById(`grades-${examId}`);
            gradesTable.classList.toggle('show');
            
            const button = gradesTable.parentElement.previousElementSibling;
            const icon = button.querySelector('i');
            if (gradesTable.classList.contains('show')) {
                icon.className = 'bx bx-chevron-up';
            } else {
                icon.className = 'bx bx-chevron-down';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const theme = localStorage.getItem('theme') || 'light';
            document.body.dataset.theme = theme;

            // Theme toggle functionality
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    const newTheme = document.body.dataset.theme === 'dark' ? 'light' : 'dark';
                    document.body.dataset.theme = newTheme;
                    localStorage.setItem('theme', newTheme);
                });
            }
        });
    </script>
</body>
</html>