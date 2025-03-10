<?php
require_once '../includes/functions.php';
checkRole('teacher');

$conn = getDBConnection();
$userId = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        if (isset($_POST['create_classroom'])) {
            // Create new classroom
            $stmt = $conn->prepare("
                INSERT INTO classrooms (teacher_id, name, department, description)
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $_POST['name'],
                $_POST['department'],
                $_POST['description']
            ]);

            setFlashMessage('success', 'Classroom created successfully.');
        }

        if (isset($_POST['add_student'])) {
            // Add student to classroom
            $stmt = $conn->prepare("
                INSERT INTO classroom_students (classroom_id, student_id)
                VALUES (?, ?)
            ");
            
            $stmt->execute([
                $_POST['classroom_id'],
                $_POST['student_id']
            ]);

            setFlashMessage('success', 'Student added to classroom.');
        }

        if (isset($_POST['remove_student'])) {
            // Remove student from classroom
            $stmt = $conn->prepare("
                DELETE FROM classroom_students 
                WHERE classroom_id = ? AND student_id = ?
            ");
            
            $stmt->execute([
                $_POST['classroom_id'],
                $_POST['student_id']
            ]);

            setFlashMessage('success', 'Student removed from classroom.');
        }

        $conn->commit();
        header('Location: classrooms.php');
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        setFlashMessage('error', $e->getMessage());
    }
}

// Fetch classrooms
try {
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            COUNT(DISTINCT cs.student_id) as student_count
        FROM classrooms c
        LEFT JOIN classroom_students cs ON c.id = cs.classroom_id
        WHERE c.teacher_id = ?
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    
    $stmt->execute([$userId]);
    $classrooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch available students (role = 'student')
    $stmt = $conn->prepare("
        SELECT id, full_name, email 
        FROM users 
        WHERE role = 'student'
        ORDER BY full_name
    ");
    
    $stmt->execute();
    $available_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error in classrooms.php: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading classroom data.');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classrooms - Teacher Dashboard</title>
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
        }

        [data-theme="dark"] {
            --background-color: #1a1a1a;
            --text-color: #ffffff;
            --primary-color: #2980b9; /* Dark blue primary color */
            --secondary-color: #3498db; /* Blue secondary color */
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 24px;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .classroom-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .classroom-card {
            background: var(--light-color);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .classroom-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .classroom-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .classroom-department {
            color: var(--text-color);
            font-size: 0.9rem;
        }

        .classroom-stats {
            display: flex;
            gap: 15px;
            margin: 15px 0;
            padding: 10px 0;
            border-top: 1px solid var(--light-color);
            border-bottom: 1px solid var(--light-color);
        }

        .stat {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--text-color);
            font-size: 0.9rem;
        }

        .student-list {
            max-height: 200px;
            overflow-y: auto;
            margin: 15px 0;
        }

        .student-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--light-color);
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .student-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--secondary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            text-transform: uppercase;
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
            background: var(--light-color);
            border-radius: 10px;
            padding: 20px;
            width: 90%;
            max-width: 500px;
            margin: 50px auto;
        }

        .close {
            float: right;
            cursor: pointer;
            font-size: 1.5rem;
        }

        @media (max-width: 768px) {
            .classroom-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/teacher-nav.php'; ?>

        <div class="page-header">
            <h1><i class="fas fa-chalkboard"></i> Classrooms</h1>
        </div>

        <?php if ($flash = getFlashMessage()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo $flash['message']; ?>
            </div>
        <?php endif; ?>

        <div class="classroom-grid">
            <?php foreach ($classrooms as $classroom): ?>
                <div class="classroom-card">
                    <div class="classroom-header">
                        <div>
                            <div class="classroom-title"><?php echo htmlspecialchars($classroom['name']); ?></div>
                            <div class="classroom-department"><?php echo htmlspecialchars($classroom['department']); ?></div>
                        </div>
                        <button class="btn btn-small" onclick="showModal('addStudentModal<?php echo $classroom['id']; ?>')">
                            <i class="fas fa-user-plus"></i>
                        </button>
                    </div>

                    <?php if (!empty($classroom['description'])): ?>
                        <p class="classroom-description"><?php echo htmlspecialchars($classroom['description']); ?></p>
                    <?php endif; ?>

                    <div class="classroom-stats">
                        <div class="stat">
                            <i class="fas fa-users"></i>
                            <?php echo $classroom['student_count']; ?> Students
                        </div>
                    </div>

                    <div class="student-list">
                        <?php
                        $stmt = $conn->prepare("
                            SELECT u.id, u.full_name, u.email
                            FROM classroom_students cs
                            JOIN users u ON cs.student_id = u.id
                            WHERE cs.classroom_id = ?
                            ORDER BY u.full_name
                        ");
                        $stmt->execute([$classroom['id']]);
                        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>

                        <?php foreach ($students as $student): ?>
                            <div class="student-item">
                                <div class="student-info">
                                    <div class="student-avatar">
                                        <?php echo strtoupper(substr($student['full_name'], 0, 2)); ?>
                                    </div>
                                    <div>
                                        <div><?php echo htmlspecialchars($student['full_name']); ?></div>
                                        <div class="student-email"><?php echo htmlspecialchars($student['email']); ?></div>
                                    </div>
                                </div>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="classroom_id" value="<?php echo $classroom['id']; ?>">
                                    <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                    <button type="submit" name="remove_student" class="btn btn-small btn-danger">
                                        <i class="fas fa-user-minus"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Add Student Modal -->
                <div id="addStudentModal<?php echo $classroom['id']; ?>" class="modal">
                    <div class="modal-content">
                        <span class="close" onclick="hideModal('addStudentModal<?php echo $classroom['id']; ?>')">&times;</span>
                        <h2>Add Student to <?php echo htmlspecialchars($classroom['name']); ?></h2>
                        <form method="POST">
                            <input type="hidden" name="classroom_id" value="<?php echo $classroom['id']; ?>">
                            <div class="form-group">
                                <label for="student_id">Select Student</label>
                                <select name="student_id" class="form-control" required>
                                    <option value="">Select a student...</option>
                                    <?php foreach ($available_students as $student): ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php echo htmlspecialchars($student['full_name']); ?> 
                                            (<?php echo htmlspecialchars($student['email']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="add_student" class="btn btn-primary">Add Student</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Create Classroom Modal -->
        <div id="createClassroomModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="hideModal('createClassroomModal')">&times;</span>
                <h2>Create New Classroom</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="name">Classroom Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="department">Department</label>
                        <input type="text" name="department" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="submit" name="create_classroom" class="btn btn-primary">Create Classroom</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>