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

        .exams-table {
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

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-published {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-draft {
            background: #fff3e0;
            color: #ef6c00;
        }

        .exam-actions {
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

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-group input {
            width: auto;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
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

        .flash-message {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .flash-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .flash-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
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
                <a href="exams.php" class="nav-link active">
                    <i class="bi bi-file-text"></i> Exams
                </a>
                <a href="classrooms.php" class="nav-link">
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
                    <h1 class="page-title">Manage Exams</h1>
                    <button class="btn btn-primary" onclick="showAddModal()">
                        <i class="bi bi-plus"></i> Add New Exam
                    </button>
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
                    <table>
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

            <!-- Add Exam Modal -->
            <div id="addModal" class="modal">
                <div class="modal-content">
                    <h2>Add New Exam</h2>
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" required>
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
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Duration (minutes)</label>
                            <input type="number" name="duration_minutes" required min="1">
                        </div>
                        <div class="form-group">
                            <label>Passing Score (%)</label>
                            <input type="number" name="passing_score" required min="0" max="100">
                        </div>
                        <div class="form-group">
                            <label>Attempts Allowed</label>
                            <input type="number" name="attempts_allowed" required min="1">
                        </div>
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="datetime-local" name="start_date" required>
                        </div>
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="datetime-local" name="end_date" required>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_published" id="add_is_published">
                            <label for="add_is_published">Publish Exam</label>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Exam</button>
                        <button type="button" class="btn btn-danger" onclick="hideModal('addModal')">Cancel</button>
                    </form>
                </div>
            </div>

            <!-- Edit Exam Modal -->
            <div id="editModal" class="modal">
                <div class="modal-content">
                    <h2>Edit Exam</h2>
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="exam_id" id="edit_exam_id">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" id="edit_title" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" id="edit_description" required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Duration (minutes)</label>
                            <input type="number" name="duration_minutes" id="edit_duration_minutes" required min="1">
                        </div>
                        <div class="form-group">
                            <label>Passing Score (%)</label>
                            <input type="number" name="passing_score" id="edit_passing_score" required min="0" max="100">
                        </div>
                        <div class="form-group">
                            <label>Attempts Allowed</label>
                            <input type="number" name="attempts_allowed" id="edit_attempts_allowed" required min="1">
                        </div>
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="datetime-local" name="start_date" id="edit_start_date" required>
                        </div>
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="datetime-local" name="end_date" id="edit_end_date" required>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_published" id="edit_is_published">
                            <label for="edit_is_published">Publish Exam</label>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Exam</button>
                        <button type="button" class="btn btn-danger" onclick="hideModal('editModal')">Cancel</button>
                    </form>
                </div>
            </div>

            <script>
                function showAddModal() {
                    document.getElementById('addModal').style.display = 'block';
                }

                function showEditModal(exam) {
                    document.getElementById('edit_exam_id').value = exam.id;
                    document.getElementById('edit_title').value = exam.title;
                    document.getElementById('edit_description').value = exam.description;
                    document.getElementById('edit_duration_minutes').value = exam.duration_minutes;
                    document.getElementById('edit_passing_score').value = exam.passing_score;
                    document.getElementById('edit_attempts_allowed').value = exam.attempts_allowed;
                    document.getElementById('edit_is_published').checked = exam.is_published == 1;
                    
                    // Format datetime for input
                    document.getElementById('edit_start_date').value = formatDateTime(exam.start_date);
                    document.getElementById('edit_end_date').value = formatDateTime(exam.end_date);
                    
                    document.getElementById('editModal').style.display = 'block';
                }

                function hideModal(modalId) {
                    document.getElementById(modalId).style.display = 'none';
                }

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

                function formatDateTime(dateString) {
                    const date = new Date(dateString);
                    return date.toISOString().slice(0, 16);
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
        </div>
    </div>
</body>
</html>