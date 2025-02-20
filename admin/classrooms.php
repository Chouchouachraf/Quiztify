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
                    $stmt = $conn->prepare("
                        UPDATE classrooms 
                        SET name = ?, description = ?, teacher_id = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['description'],
                        $_POST['teacher_id'],
                        $_POST['classroom_id']
                    ]);
                    setFlashMessage('success', 'Classroom updated successfully');
                    break;

                case 'add':
                    $stmt = $conn->prepare("
                        INSERT INTO classrooms (name, description, teacher_id)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['description'],
                        $_POST['teacher_id']
                    ]);
                    setFlashMessage('success', 'Classroom added successfully');
                    break;

                case 'remove_student':
                    $stmt = $conn->prepare("
                        DELETE FROM classroom_students 
                        WHERE classroom_id = ? AND student_id = ?
                    ");
                    $stmt->execute([$_POST['classroom_id'], $_POST['student_id']]);
                    setFlashMessage('success', 'Student removed from classroom');
                    break;

                case 'add_student':
                    $stmt = $conn->prepare("
                        INSERT INTO classroom_students (classroom_id, student_id)
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$_POST['classroom_id'], $_POST['student_id']]);
                    setFlashMessage('success', 'Student added to classroom');
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
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #357abd;
            --background-color: #f5f5f5;
            --text-color: #333;
            --danger-color: #dc3545;
            --success-color: #28a745;
            --warning-color: #ffc107;
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
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .page-title {
            font-size: 24px;
            color: var(--text-color);
        }

        .filters {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
        }

        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            outline: none;
            min-width: 150px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--primary-color);
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

        .classrooms-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--text-color);
        }

        tr:hover {
            background: #f8f9fa;
        }

        .classroom-actions {
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
            background: white;
            width: 90%;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            border-radius: 8px;
            position: relative;
            max-height: 80vh;
            overflow-y: auto;
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            outline: none;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }

        .page-link {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: var(--primary-color);
            text-decoration: none;
        }

        .page-link.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .student-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            margin-top: 10px;
        }

        .student-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px;
            border-bottom: 1px solid #eee;
        }

        .student-item:last-child {
            border-bottom: none;
        }

        .flash-message {
            padding: 10px;
            border-radius: 4px;
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
                <a href="classrooms.php" class="nav-link active">
                    <i class="bi bi-building"></i> Classrooms
                </a>
                <a href="statistics.php" class="nav-link">
                    <i class="bi bi-graph-up"></i> Statistics
                </a>
                <a href="../logout.php" class="nav-link">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </nav>
        </div>
        <div class="main-content">
            <div class="container">
                <div class="page-header">
                    <h1 class="page-title">Manage Classrooms</h1>
                    <button class="btn btn-primary" onclick="showAddModal()">
                        <i class="bi bi-plus-lg"></i> Add Classroom
                    </button>
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
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </form>
                </div>

                <div class="classrooms-table">
                    <table>
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
                                            <button class="btn btn-primary" onclick="showStudentsModal(<?php echo $classroom['id']; ?>)">
                                                <i class="bi bi-people"></i>
                                            </button>
                                            <button class="btn btn-warning" onclick='showEditModal(<?php echo htmlspecialchars(json_encode($classroom)); ?>)'>
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-danger" onclick="confirmDelete(<?php echo $classroom['id']; ?>)">
                                                <i class="bi bi-trash"></i>
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

            <!-- Add Classroom Modal -->
            <div id="addModal" class="modal">
                <div class="modal-content">
                    <h2>Add New Classroom</h2>
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
                        <button type="submit" class="btn btn-primary">Add Classroom</button>
                        <button type="button" class="btn btn-danger" onclick="hideModal('addModal')">Cancel</button>
                    </form>
                </div>
            </div>

            <!-- Edit Classroom Modal -->
            <div id="editModal" class="modal">
                <div class="modal-content">
                    <h2>Edit Classroom</h2>
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
                        <button type="submit" class="btn btn-primary">Update Classroom</button>
                        <button type="button" class="btn btn-danger" onclick="hideModal('editModal')">Cancel</button>
                    </form>
                </div>
            </div>

            <!-- Students Modal -->
            <div id="studentsModal" class="modal">
                <div class="modal-content">
                    <h2>Manage Students</h2>
                    <div id="studentsList" class="student-list">
                        <!-- Students will be loaded here via AJAX -->
                    </div>
                    <div class="form-group" style="margin-top: 20px;">
                        <label>Add Student</label>
                        <select id="studentToAdd">
                            <!-- Available students will be loaded here via AJAX -->
                        </select>
                        <button onclick="addStudent()" class="btn btn-primary" style="margin-top: 10px;">
                            Add Student
                        </button>
                    </div>
                    <button type="button" class="btn btn-danger" onclick="hideModal('studentsModal')" style="margin-top: 20px;">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentClassroomId = null;

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

        function showStudentsModal(classroomId) {
            currentClassroomId = classroomId;
            loadStudents(classroomId);
            document.getElementById('studentsModal').style.display = 'block';
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

        function loadStudents(classroomId) {
            // This would be implemented with AJAX to load current students
            // and available students for the classroom
        }

        function removeStudent(studentId) {
            if (confirm('Remove this student from the classroom?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="remove_student">
                    <input type="hidden" name="classroom_id" value="${currentClassroomId}">
                    <input type="hidden" name="student_id" value="${studentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function addStudent() {
            const studentId = document.getElementById('studentToAdd').value;
            if (studentId) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="add_student">
                    <input type="hidden" name="classroom_id" value="${currentClassroomId}">
                    <input type="hidden" name="student_id" value="${studentId}">
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