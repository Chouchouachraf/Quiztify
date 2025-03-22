<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

checkRole('admin');

// Initialize variables
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$teacher_filter = isset($_GET['teacher']) ? $_GET['teacher'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$exams = [];
$total_pages = 1;
$teachers = [];

// Handle actions (delete, update, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = getDBConnection();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM exams WHERE id = ?");
                    $stmt->execute([$_POST['exam_id']]);
                    setFlashMessage('success', 'Exam deleted successfully');
                    break;

                case 'update':
                    $stmt = $conn->prepare("
                        UPDATE exams 
                        SET title = ?, description = ?, duration_minutes = ?, 
                            passing_score = ?, attempts_allowed = ?, is_published = ?,
                            start_date = ?, end_date = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['description'],
                        $_POST['duration_minutes'],
                        $_POST['passing_score'],
                        $_POST['attempts_allowed'],
                        isset($_POST['is_published']) ? 1 : 0,
                        $_POST['start_date'],
                        $_POST['end_date'],
                        $_POST['exam_id']
                    ]);
                    setFlashMessage('success', 'Exam updated successfully');
                    break;

                case 'add':
                    $stmt = $conn->prepare("
                        INSERT INTO exams (
                            title, description, duration_minutes, passing_score,
                            attempts_allowed, is_published, created_by, start_date, end_date
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['description'],
                        $_POST['duration_minutes'],
                        $_POST['passing_score'],
                        $_POST['attempts_allowed'],
                        isset($_POST['is_published']) ? 1 : 0,
                        $_POST['teacher_id'],
                        $_POST['start_date'],
                        $_POST['end_date']
                    ]);
                    setFlashMessage('success', 'Exam added successfully');
                    break;
            }
        }
    } catch (PDOException $e) {
        setFlashMessage('error', 'Error: ' . $e->getMessage());
    }
    
    header('Location: exams.php');
    exit();
}

try {
    $conn = getDBConnection();
    
    // Get all teachers for filter
    $teachers = $conn->query("
        SELECT id, full_name, username 
        FROM users 
        WHERE role = 'teacher' 
        ORDER BY full_name
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Build query based on filters
    $where_conditions = ["1=1"];
    $params = [];
    
    if ($status_filter !== 'all') {
        $where_conditions[] = "is_published = ?";
        $params[] = ($status_filter === 'published') ? 1 : 0;
    }
    
    if ($teacher_filter !== 'all') {
        $where_conditions[] = "created_by = ?";
        $params[] = $teacher_filter;
    }
    
    if ($search !== '') {
        $where_conditions[] = "(title LIKE ? OR description LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param]);
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get total filtered count
    $count_sql = "SELECT COUNT(*) FROM exams WHERE $where_clause";
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $total_exams = $stmt->fetchColumn();
    
    // Calculate pagination
    $total_pages = ceil($total_exams / $limit);
    $offset = ($page - 1) * $limit;
    
    // Get exams with additional info
    $sql = "
        SELECT e.*, 
            u.full_name as teacher_name,
            (SELECT COUNT(*) FROM exam_attempts WHERE exam_id = e.id) as attempt_count,
            (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) as question_count
        FROM exams e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE $where_clause 
        ORDER BY e.created_at DESC 
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    setFlashMessage('error', 'Database error: ' . $e->getMessage());
    $exams = [];
    $total_pages = 1;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exams - <?php echo SITE_NAME; ?></title>
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
            --card-bg: #ffffff;
            --border-color: #ddd;
            --table-header-bg: #f8f9fa;
            --sidebar-bg: #ffffff;
            --sidebar-width: 250px;
            --nav-hover-bg: #f0f0f0;
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
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="users.php" class="nav-link">
                    <i class="fas fa-users"></i> Users
                </a>
                <a href="exams.php" class="nav-link active">
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
                <h1 class="page-title">Manage Exams</h1>
                
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

            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="flash-message flash-<?php echo $_SESSION['flash_type']; ?>">
                    <?php echo $_SESSION['flash_message']; ?>
                </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            <?php endif; ?>

            <div class="filters">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label>Status:</label>
                        <select name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Teacher:</label>
                        <select name="teacher">
                            <option value="all">All Teachers</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" 
                                        <?php echo $teacher_filter == $teacher['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Search:</label>
                        <input type="text" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search exams...">
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </div>

            <div class="exams-table">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Teacher</th>
                            <th>Duration</th>
                            <th>Questions</th>
                            <th>Attempts</th>
                            <th>Status</th>
                            <th>Date Range</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($exams)): ?>
                            <?php foreach ($exams as $exam): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                    <td><?php echo htmlspecialchars($exam['teacher_name']); ?></td>
                                    <td><?php echo $exam['duration_minutes']; ?> minutes</td>
                                    <td><?php echo $exam['question_count']; ?> questions</td>
                                    <td><?php echo $exam['attempt_count']; ?> attempts</td>
                                    <td>
                                        <span class="status-badge status-<?php echo $exam['is_published'] ? 'published' : 'draft'; ?>">
                                            <?php echo $exam['is_published'] ? 'Published' : 'Draft'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        echo date('M d, Y', strtotime($exam['start_date'])) . ' - ' . 
                                             date('M d, Y', strtotime($exam['end_date']));
                                        ?>
                                    </td>
                                    <td class="exam-actions">
                                        <button class="btn btn-warning" onclick='showEditModal(<?php echo htmlspecialchars(json_encode($exam)); ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-danger" onclick="confirmDelete(<?php echo $exam['id']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">No exams found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&teacher=<?php echo urlencode($teacher_filter); ?>&search=<?php echo urlencode($search); ?>" 
                           class="page-link <?php echo ($page === $i) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
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

        function confirmDelete(examId) {
            if (confirm('Are you sure you want to delete this exam?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="exam_id" value="${examId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-submit form when filters change
        document.querySelectorAll('.filters select').forEach(element => {
            element.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>
</html>