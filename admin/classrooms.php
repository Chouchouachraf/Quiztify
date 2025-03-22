<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

checkRole('admin');

// Initialize variables
$teacher_filter = isset($_GET['teacher']) ? $_GET['teacher'] : 'all';
$department_filter = isset($_GET['department']) ? $_GET['department'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$classrooms = [];
$total_pages = 1;
$teachers = [];

// Handle actions (delete, update, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = getDBConnection();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM classrooms WHERE id = ?");
                    $stmt->execute([$_POST['classroom_id']]);
                    setFlashMessage('success', 'Classroom deleted successfully');
                    break;

                case 'update':
                    // Get the current teacher's department
                    $currentTeacherId = $_POST['teacher_id'];
                    $teacherStmt = $conn->prepare("SELECT department FROM users WHERE id = ?");
                    $teacherStmt->execute([$currentTeacherId]);
                    $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
                    $department = $teacher['department'] ?? '';
                    
                    $stmt = $conn->prepare("
                        UPDATE classrooms 
                        SET name = ?, description = ?, teacher_id = ?, department = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['description'],
                        $_POST['teacher_id'],
                        $department,
                        $_POST['classroom_id']
                    ]);
                    setFlashMessage('success', 'Classroom updated successfully');
                    break;

                case 'add':
                    // Get the selected teacher's department
                    $teacherId = $_POST['teacher_id'];
                    $teacherStmt = $conn->prepare("SELECT department FROM users WHERE id = ?");
                    $teacherStmt->execute([$teacherId]);
                    $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
                    $department = $teacher['department'] ?? '';
                    
                    $stmt = $conn->prepare("
                        INSERT INTO classrooms (name, description, teacher_id, department)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['description'],
                        $_POST['teacher_id'],
                        $department
                    ]);
                    setFlashMessage('success', 'Classroom added successfully');
                    break;
            }
        }
    } catch (PDOException $e) {
        setFlashMessage('error', 'Error: ' . $e->getMessage());
    }
    
    header('Location: classrooms.php');
    exit();
}

try {
    $conn = getDBConnection();
    
    // Get all teachers for filter
    $teachers = $conn->query("
        SELECT id, full_name, department 
        FROM users 
        WHERE role = 'teacher' 
        ORDER BY full_name
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all departments for filter
    $departments = $conn->query("
        SELECT DISTINCT department 
        FROM users 
        WHERE role = 'teacher' AND department IS NOT NULL 
        ORDER BY department
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    // Build query based on filters
    $where_conditions = ["1=1"];
    $params = [];
    
    if ($teacher_filter !== 'all') {
        $where_conditions[] = "c.teacher_id = ?";
        $params[] = $teacher_filter;
    }
    
    if ($department_filter !== 'all') {
        $where_conditions[] = "u.department = ?";
        $params[] = $department_filter;
    }
    
    if ($search !== '') {
        $where_conditions[] = "(c.name LIKE ? OR c.description LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param]);
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get total filtered count
    $count_sql = "
        SELECT COUNT(*) 
        FROM classrooms c
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE $where_clause
    ";
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $total_classrooms = $stmt->fetchColumn();
    
    // Calculate pagination
    $total_pages = ceil($total_classrooms / $limit);
    $offset = ($page - 1) * $limit;
    
    // Get classrooms with additional info
    $sql = "
        SELECT c.*, 
            u.full_name as teacher_name,
            u.department as teacher_department,
            (SELECT COUNT(*) FROM classroom_students WHERE classroom_id = c.id) as student_count,
            (SELECT COUNT(*) FROM exams WHERE classroom_id = c.id) as exam_count
        FROM classrooms c
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE $where_clause 
        ORDER BY c.created_at DESC 
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $classrooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    setFlashMessage('error', 'Database error: ' . $e->getMessage());
    $classrooms = [];
    $total_pages = 1;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classrooms - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
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
            display: flex;
            flex-direction: column;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            width: 100%;
            max-width: 1200px;
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
            width: 100%;
            max-width: 1200px;
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
            width: 100%;
            max-width: 1200px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            max-width: 1200px;
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
            
            .main-content {
                padding: 15px;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            .table {
                min-width: 700px;
            }
            
            .add-classroom-btn {
                width: 100%;
            }
            
            .modal-content {
                width: 95%;
                max-width: 500px;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
            
            .btn {
                padding: 10px 20px;
                font-size: 14px;
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

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    animation: fadeIn 0.3s;
}

.modal-content {
    background-color: var(--card-bg);
    margin: 10% auto;
    padding: 25px;
    border-radius: 10px;
    width: 80%;
    max-width: 600px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    animation: slideDown 0.3s;
    overflow-y: auto;
    max-height: 80vh;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideDown {
    from { transform: translateY(-100px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--secondary-color);
    font-weight: 600;
}

.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    font-size: 16px;
    transition: border 0.3s ease;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-block;
    text-align: center;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background-color: #2980b9;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.btn-success {
    background-color: var(--success-color);
    color: white;
}

.btn-success:hover {
    background-color: #27ae60;
}

.btn-danger {
    background-color: var(--danger-color);
    color: white;
}

.btn-danger:hover {
    background-color: #c0392b;
}

.btn-warning {
    background-color: var(--warning-color);
    color: white;
}

.btn-warning:hover {
    background-color: #e67e22;
}

.classroom-actions {
    display: flex;
    gap: 10px;
}

.classroom-actions button {
    margin: 0;
}

/* Filters styling */
.filters {
    background-color: var(--card-bg);
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    width: 100%;
    max-width: 1200px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.filter-group {
    margin-bottom: 15px;
    display: flex;
    flex-direction: column;
}

.filter-group label {
    margin-bottom: 5px;
    color: var(--secondary-color);
    font-weight: 600;
}

.filter-group select,
.filter-group input {
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    font-size: 16px;
}

.filter-group select:focus,
.filter-group input:focus {
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}

.btn-filter {
    background-color: var(--primary-color);
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.btn-filter:hover {
    background-color: #2980b9;
}

/* Classroom table styling */
.classrooms-table {
    width: 100%;
    max-width: 1200px;
    margin-bottom: 30px;
}

.table-container {
    overflow-x: auto;
}

.table {
    min-width: 800px;
}

.table th,
.table td {
    padding: 15px;
    text-align: left;
}

.table th {
    background-color: var(--table-header-bg);
    position: sticky;
    top: 0;
}

.table tr:hover {
    background-color: rgba(52, 152, 219, 0.05);
}

.classroom-actions {
    display: flex;
    gap: 10px;
}

.classroom-actions button {
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s;
}

.classroom-actions .btn-edit {
    background-color: var(--warning-color);
    color: white;
}

.classroom-actions .btn-delete {
    background-color: var(--danger-color);
    color: white;
}

.classroom-actions .btn-edit:hover {
    background-color: #e67e22;
    transform: translateY(-2px);
}

.classroom-actions .btn-delete:hover {
    background-color: #c0392b;
    transform: translateY(-2px);
}

/* Pagination styling */
.pagination {
    display: flex;
    justify-content: center;
    margin-top: 20px;
    width: 100%;
    max-width: 1200px;
}

.page-link {
    padding: 10px 15px;
    margin: 0 5px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background-color: var(--card-bg);
    color: var(--text-color);
    text-decoration: none;
    transition: all 0.3s;
}

.page-link.active {
    background-color: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.page-link:hover:not(.active) {
    background-color: var(--light-color);
}

/* Add classroom button */
.add-classroom-btn {
    background-color: var(--success-color);
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: block;
    margin: 20px auto 0;
    width: fit-content;
}

.add-classroom-btn:hover {
    background-color: #27ae60;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Modal header */
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-color);
}

.modal-title {
    font-size: 22px;
    color: var(--secondary-color);
}

.close-modal-btn {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: var(--text-color);
    opacity: 0.7;
}

.close-modal-btn:hover {
    opacity: 1;
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
                <a href="exams.php" class="nav-link">
                    <i class="fas fa-file-alt"></i> Exams
                </a>
                <a href="classrooms.php" class="nav-link active">
                    <i class="fas fa-chalkboard"></i> Classrooms
                </a>
                <a href="statistics.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i> Statistics
                </a>
            </nav>
        </div>

        <div class="main-content">
            <div class="header">
                <h1 class="page-title">Manage Classrooms</h1>
                
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

            <?php displayFlashMessages(); ?>

            <div class="filters">
                <form method="GET" class="filter-form">
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
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search classrooms...">
                    </div>
                    <button type="submit" class="btn-filter">Filter</button>
                </form>
            </div>

            <button class="add-classroom-btn" onclick="showAddModal()">Add New Classroom</button>

            <div class="classrooms-table">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Teacher</th>
                                <th>Department</th>
                                <th>Students</th>
                                <th>Exams</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($classrooms)): ?>
                                <?php foreach ($classrooms as $classroom): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($classroom['name']); ?></td>
                                        <td><?php echo htmlspecialchars($classroom['teacher_name']); ?></td>
                                        <td><?php echo htmlspecialchars($classroom['teacher_department'] ?? 'N/A'); ?></td>
                                        <td><?php echo $classroom['student_count']; ?> students</td>
                                        <td><?php echo $classroom['exam_count']; ?> exams</td>
                                        <td><?php echo date('M d, Y', strtotime($classroom['created_at'])); ?></td>
                                        <td class="classroom-actions">
                                            <button class="btn-edit" onclick='showEditModal(<?php echo htmlspecialchars(json_encode($classroom)); ?>)'>
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn-delete" onclick="confirmDelete(<?php echo $classroom['id']; ?>)">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center;">No classrooms found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&teacher=<?php echo urlencode($teacher_filter); ?>&department=<?php echo urlencode($department_filter); ?>&search=<?php echo urlencode($search); ?>" 
                           class="page-link <?php echo ($page === $i) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Classroom Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New Classroom</h2>
                <button class="close-modal-btn" onclick="hideModal('addModal')">&times;</button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" required></textarea>
                </div>
                <div class="form-group">
                    <label>Teacher</label>
                    <select name="teacher_id" required>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>">
                                <?php echo htmlspecialchars($teacher['full_name']); ?>
                                <?php echo $teacher['department'] ? ' (' . htmlspecialchars($teacher['department']) . ')' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary">Add Classroom</button>
                    <button type="button" class="btn btn-danger" onclick="hideModal('addModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Classroom Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit Classroom</h2>
                <button class="close-modal-btn" onclick="hideModal('editModal')">&times;</button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="classroom_id" id="edit_classroom_id">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description" required></textarea>
                </div>
                <div class="form-group">
                    <label>Teacher</label>
                    <select name="teacher_id" id="edit_teacher_id" required>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>">
                                <?php echo htmlspecialchars($teacher['full_name']); ?>
                                <?php echo $teacher['department'] ? ' (' . htmlspecialchars($teacher['department']) . ')' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary">Update Classroom</button>
                    <button type="button" class="btn btn-danger" onclick="hideModal('editModal')">Cancel</button>
                </div>
            </form>
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

        function showEditModal(classroom) {
            document.getElementById('edit_classroom_id').value = classroom.id;
            document.getElementById('edit_name').value = classroom.name;
            document.getElementById('edit_description').value = classroom.description;
            document.getElementById('edit_teacher_id').value = classroom.teacher_id;
            document.getElementById('editModal').style.display = 'block';
        }

        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function confirmDelete(classroomId) {
            if (confirm('Are you sure you want to delete this classroom?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="classroom_id" value="${classroomId}">
                `;
                document.body.appendChild(form);
                form.submit();
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