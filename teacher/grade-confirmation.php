<?php
require_once '../includes/functions.php';
checkRole('teacher');

if (!isset($_SESSION['grade_confirmation'])) {
    header('Location: exams.php');
    exit;
}

$confirmation = $_SESSION['grade_confirmation'];
unset($_SESSION['grade_confirmation']); // Clear the session data
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grading Confirmation - Quiztify</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }

        .confirmation-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .success-icon {
            font-size: 64px;
            color: #2ecc71;
            margin-bottom: 20px;
        }

        .confirmation-title {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        .confirmation-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }

        .detail-item {
            margin: 10px 0;
            color: #2c3e50;
        }

        .detail-label {
            font-weight: bold;
            margin-right: 10px;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.9em;
            margin-top: 10px;
        }

        .status-published {
            background: #d4edda;
            color: #155724;
        }

        .status-draft {
            background: #fff3cd;
            color: #856404;
        }

        .action-buttons {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="confirmation-card">
            <i class='bx bx-check-circle success-icon'></i>
            <h1 class="confirmation-title">Grades Saved Successfully!</h1>

            <div class="confirmation-details">
                <div class="detail-item">
                    <span class="detail-label">Exam:</span>
                    <?php echo htmlspecialchars($confirmation['exam_title']); ?>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Student:</span>
                    <?php echo htmlspecialchars($confirmation['student_name']); ?>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Final Score:</span>
                    <?php echo number_format($confirmation['score'], 1); ?>%
                </div>
                <div class="detail-item">
                    <span class="detail-label">Status:</span>
                    <span class="status-badge <?php echo $confirmation['published'] ? 'status-published' : 'status-draft'; ?>">
                        <?php echo $confirmation['published'] ? 'Published' : 'Saved as Draft'; ?>
                    </span>
                </div>
            </div>

            <div class="action-buttons">
                <a href="grade-exam.php?id=<?php echo $confirmation['attempt_id']; ?>" class="btn btn-primary">
                    <i class='bx bx-edit'></i> Continue Editing
                </a>
                <a href="exams.php" class="btn btn-secondary">
                    <i class='bx bx-arrow-back'></i> Back to Exams
                </a>
            </div>
        </div>
    </div>
</body>
</html> 