<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get statistics
try {
    $conn = getDBConnection();
    
    // Get total counts
    $stats = [
        'total_users' => $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'total_students' => $conn->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
        'total_teachers' => $conn->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn(),
        'total_exams' => $conn->query("SELECT COUNT(*) FROM exams")->fetchColumn(),
        'total_classrooms' => $conn->query("SELECT COUNT(*) FROM classrooms")->fetchColumn(),
        'total_attempts' => $conn->query("SELECT COUNT(*) FROM exam_attempts")->fetchColumn()
    ];
    
    // Get recent users
    $recent_users = $conn->query("
        SELECT id, username, email, role, full_name, created_at
        FROM users 
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent exam attempts
    $recent_attempts = $conn->query("
        SELECT 
            ea.*, 
            u.username, 
            u.full_name, 
            e.title as exam_title,
            e.total_points
        FROM exam_attempts ea
        JOIN users u ON ea.student_id = u.id
        JOIN exams e ON ea.exam_id = e.id
        ORDER BY ea.start_time DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Add helper function
function calculatePoints($percentage, $total_points) {
    return ($percentage / 100) * $total_points;
}

// Get user data from session for the navbar
$userInitials = isset($_SESSION['full_name']) ? strtoupper(substr($_SESSION['full_name'], 0, 2)) : 'A';
$userName = $_SESSION['full_name'] ?? 'Admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f1c40f;
            --light-color: #ecf0f1;
            --dark-color: rgb(0, 0, 0);
            --background-color: #f5f6fa;
            --text-color: rgb(0, 0, 0); /* Black text color for light mode */
            --card-bg: #ffffff;
            --border-color: #ddd;
            --table-header-bg: #f8f9fa;
            --sidebar-bg: #ffffff;
            --sidebar-width: 250px;
            --nav-hover-bg: #f0f0f0;
        }

        [data-theme="dark"] {
            --background-color: #1a1a1a;
            --text-color: rgb(255, 252, 252); /* White text color for dark mode */
            --primary-color: #2980b9;
            --secondary-color: #3498db;
            --success-color: #44bb77;
            --danger-color: #ff5555;
            --warning-color: #ffcc00;
            --light-color: #333333;
            --dark-color: #ffffff;
            --card-bg: #2a2a2a;
            --border-color: #444;
            --table-header-bg: #333;
            --sidebar-bg: #222222;
            --nav-hover-bg: #333333;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--background-color);
            color: var(--text-color);
            transition: all 0.3s ease;
            line-height: 1.6;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
        }

        .logo i {
            color: var(--secondary-color);
            font-size: 24px;
        }

        .logo h2 {
            color: var(--text-color);
            font-size: 20px;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 24px;
            color: var(--text-color);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--secondary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .stat-card h3 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-color);
        }

        .recent-activity {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .table th {
            background-color: var(--table-header-bg);
            font-weight: 600;
            color: var(--text-color);
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: var(--text-color);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            background: var(--nav-hover-bg);
        }

        .nav-link i {
            margin-right: 12px;
            font-size: 18px;
        }

        .nav-link.active {
            background: var(--secondary-color);
            color: white;
        }

        .theme-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            background: var(--secondary-color);
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .theme-toggle:hover {
            background: var(--primary-color);
        }

        .logout-btn {
            background: var(--danger-color);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .logout-btn:hover {
            background: #c0392b;
        }

        .percentage {
            color: var(--text-color);
            font-size: 0.85em;
            margin-left: 5px;
            opacity: 0.8;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                padding: 10px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        /* Light mode table text color */
table th, table td {
    color: var(--text-color);
}

/* Dark mode table text color */
[data-theme="dark"] table th, [data-theme="dark"] table td {
    color: var(--text-color);
}
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="logo">
                <i class="fas fa-graduation-cap"></i>
                <h2>QuizTify Admin</h2>
            </div>
            
            <nav>
                <a href="dashboard.php" class="nav-link active">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="users.php" class="nav-link">
                    <i class="fas fa-users"></i> Users
                </a>
                <a href="exams.php" class="nav-link">
                    <i class="fas fa-file-alt"></i> Exams
                </a>
                <a href="classrooms.php" class="nav-link">
                    <i class="fas fa-chalkboard"></i> Classrooms
                </a>
                <a href="statistics.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i> Statistics
                </a>
            </nav>
        </div>

        <div class="main-content">
            <div class="header">
                <h1 class="page-title">Dashboard Overview</h1>
                
                <div class="user-menu">
                    <button id="theme-toggle" class="theme-toggle">
                        <i class="fas fa-moon"></i> Theme
                    </button>
                    <div class="user-avatar">
                        <?php echo $userInitials; ?>
                    </div>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

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
                    <h3>Total Exams</h3>
                    <p class="stat-number"><?php echo $stats['total_exams']; ?></p>
                </div>
            </div>

            <div class="recent-activity">
                <h2>Recent Users</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($user['role'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="recent-activity">
                <h2>Recent Exam Attempts</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Exam</th>
                            <th>Start Time</th>
                            <th>Status</th>
                            <th>Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_attempts as $attempt): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($attempt['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($attempt['exam_title']); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($attempt['start_time'])); ?></td>
                            <td>
                                <?php if ($attempt['is_completed']): ?>
                                    <span style="color: var(--success-color);">Completed</span>
                                <?php else: ?>
                                    <span style="color: var(--warning-color);">In Progress</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($attempt['is_completed'] && isset($attempt['score'])): ?>
                                    <?php 
                                    $points = calculatePoints($attempt['score'], $attempt['total_points']);
                                    echo number_format($points, 1) . '/' . $attempt['total_points'];
                                    ?>
                                    <small class="percentage">(<?php echo number_format($attempt['score'], 1); ?>%)</small>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get theme from localStorage or default to light
            const theme = localStorage.getItem('theme') || 'light';
            document.body.dataset.theme = theme;
            
            // Theme toggle button functionality
            document.getElementById('theme-toggle').addEventListener('click', function() {
                const newTheme = document.body.dataset.theme === 'dark' ? 'light' : 'dark';
                document.body.dataset.theme = newTheme;
                localStorage.setItem('theme', newTheme);
                
                // Update icon based on theme
                const themeIcon = this.querySelector('i');
                if (newTheme === 'dark') {
                    themeIcon.classList.remove('fa-sun');
                    themeIcon.classList.add('fa-moon');
                } else {
                    themeIcon.classList.remove('fa-moon');
                    themeIcon.classList.add('fa-sun');
                }
            });
            
            // Set correct icon on page load
            const themeIcon = document.querySelector('#theme-toggle i');
            if (theme === 'dark') {
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
            } else {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
            }
        });
        
    </script>
</body>
</html>