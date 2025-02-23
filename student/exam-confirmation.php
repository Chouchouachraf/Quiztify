<?php
require_once '../includes/functions.php';
checkRole('student');

$examId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$conn = getDBConnection();

try {
    $stmt = $conn->prepare("
        SELECT e.title
        FROM exams e
        JOIN exam_attempts ea ON e.id = ea.exam_id
        WHERE e.id = ? AND ea.student_id = ?
        ORDER BY ea.end_time DESC
        LIMIT 1
    ");
    $stmt->execute([$examId, $_SESSION['user_id']]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        header('Location: dashboard.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Error in exam-confirmation.php: " . $e->getMessage());
    header('Location: dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Submitted - <?php echo htmlspecialchars($exam['title']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            text-align: center;
        }

        .success-icon {
            color: #4CAF50;
            font-size: 64px;
            margin-bottom: 20px;
        }

        .message {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
        }

        .sub-message {
            color: #666;
            margin-bottom: 30px;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <i class="fas fa-check-circle success-icon"></i>
        <h1 class="message">Exam Submitted Successfully!</h1>
        <p class="sub-message">
            Thank you for completing <?php echo htmlspecialchars($exam['title']); ?>.
            Your responses have been recorded.
        </p>
        <a href="dashboard.php" class="btn">
            <i class="fas fa-home"></i> Return to Dashboard
        </a>
    </div>
</body>
</html> 