<?php
session_start();
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a student
checkRole('student');

try {
    $conn = getDBConnection();
    $student_id = $_SESSION['user_id'];
    
    // Get current datetime for comparison
    $current_datetime = date('Y-m-d H:i:s');

    // Get available exams for the student's classrooms
    $query = "
        SELECT DISTINCT
            e.id,
            e.title,
            e.description,
            e.duration_minutes,
            e.passing_score,
            e.start_date,
            e.end_date,
            e.attempts_allowed,
            u.full_name as teacher_name,
            c.name as classroom_name,
            c.department
        FROM exams e
        JOIN users u ON e.created_by = u.id
        JOIN exam_classrooms ec ON e.id = ec.exam_id
        JOIN classrooms c ON ec.classroom_id = c.id
        JOIN classroom_students cs ON c.id = cs.classroom_id
        WHERE cs.student_id = ?
        AND e.is_published = 1
        ORDER BY e.start_date DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute([$student_id]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error fetching available exams: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while fetching exams.');
    $exams = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Exams - <?php echo SITE_NAME; ?></title>
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
        }

        [data-theme="dark"] {
            --background-color: #1a1a1a;
            --text-color: #ffffff;
            --primary-color: #5588ff;
            --secondary-color:rgb(68, 108, 187);
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
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--background-color);
            color: var(--dark-color);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-title {
            font-size: 24px;
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-color);
        }

        .exam-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .exam-card {
            background: var(--light-color);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.2s;
        }

        .exam-card:hover {
            transform: translateY(-5px);
        }

        .exam-header {
            background: var(--secondary-color);
            color: var(--light-color);
            padding: 15px;
        }

        .exam-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .exam-teacher {
            font-size: 14px;
            opacity: 0.9;
        }

        .exam-body {
            padding: 15px;
        }

        .exam-info {
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--text-color);
        }

        .exam-info i {
            width: 20px;
            color: var(--secondary-color);
            margin-right: 5px;
        }

        .classroom-info {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--light-color);
            font-size: 14px;
            color: var(--text-color);
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            background: var(--light-color);
            border-left: 4px solid;
        }

        .alert-error {
            border-color: var(--danger-color);
            color: var(--danger-color);
            background-color: var(--light-color);
        }

        .alert-success {
            border-color: var(--success-color);
            color: var(--success-color);
            background-color: var(--light-color);
        }

        .no-exams {
            text-align: center;
            padding: 40px;
            background: var(--light-color);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .no-exams i {
            font-size: 48px;
            color: var(--secondary-color);
            margin-bottom: 15px;
        }

        .no-exams h3 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .no-exams p {
            color: var(--text-color);
        }
    </style>
</head>
<body>
<?php include '../includes/student-nav.php'; ?>
    <div class="container">
        <h1 class="page-title">Available Exams</h1>

        <?php if (isset($_SESSION['flash_message'])): 
            $message = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
        ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($exams)): ?>
            <div class="no-exams">
                <i class='bx bx-calendar-x'></i>
                <h3>No Exams Available</h3>
                <p>There are currently no exams available for your classrooms.</p>
            </div>
        <?php else: ?>
            <div class="exam-grid">
                <?php foreach ($exams as $exam): ?>
                    <div class="exam-card">
                        <div class="exam-header">
                            <div class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></div>
                            <div class="exam-teacher">by <?php echo htmlspecialchars($exam['teacher_name']); ?></div>
                        </div>
                        <div class="exam-body">
                            <?php if (!empty($exam['description'])): ?>
                                <div class="exam-info">
                                    <i class='bx bx-info-circle'></i>
                                    <?php echo htmlspecialchars($exam['description']); ?>
                                </div>
                            <?php endif; ?>

                            <div class="exam-info">
                                <i class='bx bx-time'></i>
                                Duration: <?php echo $exam['duration_minutes']; ?> minutes
                            </div>

                            <div class="exam-info">
                                <i class='bx bx-calendar'></i>
                                Start: <?php echo date('M d, Y H:i', strtotime($exam['start_date'])); ?>
                            </div>

                            <div class="exam-info">
                                <i class='bx bx-calendar-x'></i>
                                End: <?php echo date('M d, Y H:i', strtotime($exam['end_date'])); ?>
                            </div>

                            <div class="exam-info">
                                <i class='bx bx-trophy'></i>
                                Passing Score: <?php echo $exam['passing_score']; ?>%
                            </div>

                            <div class="classroom-info">
                                <i class='bx bx-building-house'></i>
                                <?php echo htmlspecialchars($exam['classroom_name']); ?> 
                                (<?php echo htmlspecialchars($exam['department']); ?>)
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>