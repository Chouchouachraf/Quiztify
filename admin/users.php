<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

checkRole('admin');

// Initialize variables
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$department_filter = isset($_GET['department']) ? $_GET['department'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$users = [];
$total_pages = 1;
$departments = [];

// Handle actions (delete, update, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = getDBConnection();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                    $stmt->execute([$_POST['user_id']]);
                    setFlashMessage('success', 'User deleted successfully');
                    break;

                case 'update':
                    $updates = [];
                    $params = [$_POST['full_name'], $_POST['email'], $_POST['role']];
                    
                    $sql = "UPDATE users SET full_name = ?, email = ?, role = ?";
                    
                    if (!empty($_POST['new_password'])) {
                        $sql .= ", password = ?";
                        $params[] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                    }
                    
                    if (!empty($_POST['department'])) {
                        $sql .= ", department = ?";
                        $params[] = $_POST['department'];
                    }
                    
                    $sql .= " WHERE id = ? AND role != 'admin'";
                    $params[] = $_POST['user_id'];
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($params);
                    setFlashMessage('success', 'User updated successfully');
                    break;

                case 'add':
                    $stmt = $conn->prepare("
                        INSERT INTO users (username, email, password, full_name, role, department)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['username'],
                        $_POST['email'],
                        password_hash($_POST['password'], PASSWORD_DEFAULT),
                        $_POST['full_name'],
                        $_POST['role'],
                        $_POST['department'] ?? null
                    ]);
                    setFlashMessage('success', 'User added successfully');
                    break;
            }
        }
    } catch (PDOException $e) {
        setFlashMessage('error', 'Error: ' . $e->getMessage());
    }
    
    header('Location: users.php');
    exit();
}

try {
    $conn = getDBConnection();
    
    // Get all departments for filter
    $departments = $conn->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
    
    // Build query based on filters
    $where_conditions = ["role != 'admin'"];
    $params = [];
    
    if ($role_filter !== 'all') {
        $where_conditions[] = "role = ?";
        $params[] = $role_filter;
    }
    
    if ($department_filter !== 'all' && $department_filter !== '') {
        $where_conditions[] = "department = ?";
        $params[] = $department_filter;
    }
    
    if ($search !== '') {
        $where_conditions[] = "(full_name LIKE ? OR email LIKE ? OR username LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get total filtered count
    $count_sql = "SELECT COUNT(*) FROM users WHERE $where_clause";
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $total_users = $stmt->fetchColumn();
    
    // Calculate pagination
    $total_pages = ceil($total_users / $limit);
    $offset = ($page - 1) * $limit;
    
    // Get users with additional info
    $sql = "
        SELECT u.*, 
            (SELECT COUNT(*) FROM exam_attempts WHERE student_id = u.id) as exam_count,
            (SELECT GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') 
             FROM classroom_students cs 
             JOIN classrooms c ON cs.classroom_id = c.id 
             WHERE cs.student_id = u.id) as enrolled_classrooms
        FROM users u 
        WHERE $where_clause 
        ORDER BY u.created_at DESC 
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    setFlashMessage('error', 'Database error: ' . $e->getMessage());
    $users = [];
    $total_pages = 1;
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
    <title>Manage Users - <?php echo SITE_NAME; ?></title>
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
            --modal-bg: #ffffff;
            --input-bg: #ffffff;
            --input-border: #ddd;
            --pagination-bg: #ffffff;
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
            --modal-bg: #2a2a2a;
            --input-bg: #333333;
            --input-border: #555;
            --pagination-bg: #2a2a2a;
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
            padding: 15px 20px;
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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

        .filters {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .filter-form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-group label {
            font-weight: 500;
            min-width: 80px;
            color: var(--text-color);
        }

        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 1px solid var(--input-border);
            border-radius: 8px;
            outline: none;
            min-width: 150px;
            background-color: var(--input-bg);
            color: var(--text-color);
            transition: all 0.3s ease;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn-primary {
            background: var(--secondary-color);
            color: white;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-warning {
            background: var(--warning-color);
            color: black;
        }

        .users-table {
            background: var(--card-bg);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background: var(--table-header-bg);
            font-weight: 600;
            color: var(--text-color);
        }

        tr:hover {
            background: var(--nav-hover-bg);
        }

        .role-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .role-teacher {
            background: #1f4287;
            color: white;
        }

        .role-student {
            background: #7b1fa2;
            color: white;
        }

        .user-actions {
            display: flex;
            gap: 8px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            background: var(--modal-bg);
            width: 90%;
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            border-radius: 10px;
            position: relative;
            transition: all 0.3s ease;
            color: var(--text-color);
        }

        .modal h2 {
            margin-bottom: 20px;
            color: var(--text-color);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-color);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--input-border);
            border-radius: 8px;
            outline: none;
            background-color: var(--input-bg);
            color: var(--text-color);
            transition: all 0.3s ease;
        }

        .password-field {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 35px;
            cursor: pointer;
            color: var(--text-color);
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }

        .page-link {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--secondary-color);
            background: var(--pagination-bg);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .page-link:hover {
            background: var(--secondary-color);
            color: white;
        }

        .page-link.active {
            background: var(--secondary-color);
            color: white;
            border-color: var(--secondary-color);
        }

        .flash-message {
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .flash-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .flash-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                padding: 10px;
            }
            
            .filter-form {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
            }
            
            .user-menu {
                width: 100%;
                justify-content: flex-end;
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
                <a href="users.php" class="nav-link active">
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
                <h1 class="page-title">Manage Users</h1>
                
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
                    <?php 
                        echo $_SESSION['flash_message'];
                        unset($_SESSION['flash_message']);
                        unset($_SESSION['flash_type']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="filters">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label>Role:</label>
                        <select name="role">
                            <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                            <option value="teacher" <?php echo $role_filter === 'teacher' ? 'selected' : ''; ?>>Teachers</option>
                            <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Students</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Department:</label>
                        <select name="department">
                            <option value="all">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>" 
                                        <?php echo $department_filter === $dept ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Search:</label>
                        <input type="text" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search users...">
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </div>

            <div class="users-table">
                <table>
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Classes/Exams</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($user['role'] === 'student'): ?>
                                            <?php echo htmlspecialchars($user['enrolled_classrooms'] ?? 'No classes'); ?>
                                        <?php else: ?>
                                            <?php echo $user['exam_count']; ?> exams created
                                        <?php endif; ?>
                                    </td>
                                    <td class="user-actions">
                                        <button class="btn btn-warning" onclick='showEditModal(<?php echo htmlspecialchars(json_encode($user)); ?>)'>
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                        <button class="btn btn-danger" onclick="confirmDelete(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">No users found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&role=<?php echo urlencode($role_filter); ?>&department=<?php echo urlencode($department_filter); ?>&search=<?php echo urlencode($search); ?>" 
                           class="page-link <?php echo ($page === $i) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

            <div id="addModal" class="modal">
                <div class="modal-content">
                    <h2>Add New User</h2>
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" required>
                        </div>
                        <div class="form-group password-field">
                            <label>Password</label>
                            <input type="password" name="password" id="add_password" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('add_password')"></i>
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role" required>
                                <option value="teacher">Teacher</option>
                                <option value="student">Student</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Department</label>
                            <input type="text" name="department">
                        </div>
                        <button type="submit" class="btn btn-primary">Add User</button>
                        <button type="button" class="btn btn-danger" onclick="hideModal('addModal')">Cancel</button>
                    </form>
                </div>
            </div>
            <div id="editModal" class="modal">
                <div class="modal-content">
                    <h2>Edit User</h2>
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" id="edit_full_name" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" id="edit_email" required>
                        </div>
                        <div class="form-group password-field">
                            <label>New Password (leave blank to keep current)</label>
                            <input type="password" name="new_password" id="edit_password">
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('edit_password')"></i>
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role" id="edit_role" required>
                                <option value="teacher">Teacher</option>
                                <option value="student">Student</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Department</label>
                            <input type="text" name="department" id="edit_department">
                        </div>
                        <button type="submit" class="btn btn-primary">Update User</button>
                        <button type="button" class="btn btn-danger" onclick="hideModal('editModal')">Cancel</button>
                    </form>
                </div>
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

        function showAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function showEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_department').value = user.department || '';
            document.getElementById('editModal').style.display = 'block';
        }

        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        function confirmDelete(userId) {
            if (confirm('Are you sure you want to delete this user?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = event.target;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
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