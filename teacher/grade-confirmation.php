<?php
session_start(); // Ensure session is started
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

checkRole('teacher'); // Ensure only teachers can access this page

// Check if grading confirmation data exists in the session
if (!isset($_SESSION['grade_confirmation'])) {
    setFlashMessage('error', 'No grading confirmation data found. Redirecting to exams page.');
    header('Location: exams.php');
    exit;
}

// Retrieve confirmation data from the session
$confirmation = $_SESSION['grade_confirmation'];
unset($_SESSION['grade_confirmation']); // Clear the session data after retrieval

// Fetch the total points for the exam
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT total_points FROM exams WHERE id = ?");
$stmt->execute([$confirmation['exam_id']]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);
$total_points = $exam['total_points'] ?? 100; // Default to 100 if not found

// Calculate the final grade as total points earned / total exam grade
$final_grade = isset($confirmation['score']) ? $confirmation['score'] : 0;
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grading Confirmation - Quiztify</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
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
            flex-wrap: wrap;
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

        .btn-success {
            background: #2ecc71;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .final-grade-display {
            font-size: 1.2em;
            margin: 15px 0;
            color: #2c3e50;
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
                    <?php echo isset($confirmation['exam_title']) ? htmlspecialchars($confirmation['exam_title']) : 'N/A'; ?>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Student:</span>
                    <?php echo isset($confirmation['student_name']) ? htmlspecialchars($confirmation['student_name']) : 'N/A'; ?>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Final Score:</span>
                    <div class="final-grade-display">
                        <?php 
                        if (isset($confirmation['score']) && isset($total_points)) {
                            echo number_format($confirmation['score'], 1) . "/" . $total_points;
                        } else {
                            echo "N/A";
                        }
                        ?>
                    </div>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Status:</span>
                    <span class="status-badge <?php echo isset($confirmation['published']) ? ($confirmation['published'] ? 'status-published' : 'status-draft') : 'status-draft'; ?>">
                        <?php echo isset($confirmation['published']) ? ($confirmation['published'] ? 'Published' : 'Saved as Draft') : 'Saved as Draft'; ?>
                    </span>
                </div>
            </div>
            <div class="action-buttons">
                <a href="grade-exam.php?id=<?php echo isset($confirmation['attempt_id']) ? $confirmation['attempt_id'] : ''; ?>" class="btn btn-primary">
                    <i class='bx bx-edit'></i> Continue Editing
                </a>
                <a href="generate-exam-pdf.php?id=<?php echo isset($confirmation['attempt_id']) ? $confirmation['attempt_id'] : ''; ?>" class="btn btn-success">
                    <i class='bx bx-download'></i> Download as PDF
                </a>
                <a href="view-exam.php?id=<?php echo isset($confirmation['exam_id']) ? $confirmation['exam_id'] : ''; ?>" class="btn btn-secondary">
                    <i class='bx bx-arrow-back'></i> Back to Exam Results
                </a>
            </div>
        </div>
    </div>
</body>
</html>