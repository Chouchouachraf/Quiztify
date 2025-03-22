<?php
require_once '../includes/functions.php';
require_once 'ExamManager.php';
checkRole('student');

try {
    $conn = getDBConnection();
    $examManager = new ExamManager($conn, $_SESSION['user_id']);

    if (!isset($_GET['attempt_id'])) {
        throw new Exception('No attempt specified');
    }

    $result = $examManager->getExamResult($_GET['attempt_id']);
    if (!$result) {
        throw new Exception('Result not found or access denied');
    }

} catch (Exception $e) {
    setFlashMessage('error', $e->getMessage());
    header('Location: dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Result - <?php echo htmlspecialchars($result['exam_title']); ?></title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .result-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .score-display {
            text-align: center;
            padding: 20px;
            margin: 20px 0;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .score {
            font-size: 48px;
            color: #2c3e50;
            margin: 10px 0;
        }

        .result-info {
            margin: 20px 0;
            padding: 15px;
            border-radius: 8px;
            background: #e8f4fd;
        }

        .info-item {
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="result-container">
        <h1><?php echo htmlspecialchars($result['exam_title']); ?> - Results</h1>
        
        <div class="score-display">
            <div class="score">
                <?php echo number_format($result['score'], 1); ?> / <?php echo $result['total_points']; ?>
            </div>
            <div class="percentage">
                <?php 
                $percentage = ($result['score'] / $result['total_points']) * 100;
                echo number_format($percentage, 1) . '%';
                ?>
            </div>
        </div>

        <div class="result-info">
            <div class="info-item">
                <i class='bx bx-book'></i>
                <span>Class: <?php echo htmlspecialchars($result['classroom_name']); ?></span>
            </div>
            <div class="info-item">
                <i class='bx bx-user'></i>
                <span>Teacher: <?php echo htmlspecialchars($result['teacher_name']); ?></span>
            </div>
            <div class="info-item">
                <i class='bx bx-time'></i>
                <span>Completed: <?php echo date('M j, Y g:i A', strtotime($result['end_time'])); ?></span>
            </div>
        </div>

        <a href="dashboard.php" class="back-btn">
            <i class='bx bx-arrow-back'></i>
            Back to Dashboard
        </a>
    </div>
</body>
</html> 