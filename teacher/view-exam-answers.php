<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkRole('teacher');

$exam_id = $_GET['id'] ?? null;
$teacher_id = $_SESSION['user_id'];

if (!$exam_id) {
    header('Location: exams.php');
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get exam details and verify ownership
    $stmt = $conn->prepare("
        SELECT e.*, COUNT(DISTINCT ea.id) as total_attempts,
               AVG(ea.score) as average_score
        FROM exams e
        LEFT JOIN exam_attempts ea ON e.id = ea.id
        WHERE e.id = ? AND e.created_by = ?
        GROUP BY e.id
    ");
    $stmt->execute([$exam_id, $teacher_id]);
    $exam = $stmt->fetch();

    if (!$exam) {
        setFlashMessage('error', 'Exam not found or access denied.');
        header('Location: exams.php');
        exit;
    }

    // Get all attempts with student details
    $stmt = $conn->prepare("
        SELECT 
            ea.*,
            u.full_name as student_name,
            u.email as student_email,
            COUNT(sa.id) as questions_answered,
            SUM(CASE WHEN mo.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers
        FROM exam_attempts ea
        JOIN users u ON ea.student_id = u.id
        LEFT JOIN student_answers sa ON ea.id = sa.attempt_id
        LEFT JOIN mcq_options mo ON sa.selected_option_id = mo.id
        WHERE ea.exam_id = ?
        GROUP BY ea.id
        ORDER BY ea.end_time DESC
    ");
    $stmt->execute([$exam_id]);
    $attempts = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Error in view-exam-answers.php: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading exam answers.');
    header('Location: exams.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Exam Answers - <?php echo SITE_NAME; ?></title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Add your custom styles here */
    </style>
</head>
<body>
    <div class="container py-4">
        <h1 class="mb-4">Exam Answers: <?php echo htmlspecialchars($exam['title']); ?></h1>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Attempts</h5>
                        <p class="card-text h2"><?php echo $exam['total_attempts']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Average Score</h5>
                        <p class="card-text h2"><?php echo number_format($exam['average_score'], 1); ?>%</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Score</th>
                        <th>Questions</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attempts as $attempt): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($attempt['student_name']); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($attempt['student_email']); ?></small>
                            </td>
                            <td><?php echo number_format($attempt['score'], 1); ?>%</td>
                            <td>
                                <?php echo $attempt['correct_answers']; ?>/<?php echo $attempt['questions_answered']; ?>
                            </td>
                            <td><?php echo date('M j, Y, g:i a', strtotime($attempt['start_time'])); ?></td>
                            <td><?php echo date('M j, Y, g:i a', strtotime($attempt['end_time'])); ?></td>
                            <td>
                                <?php if ($attempt['score'] >= $exam['passing_score']): ?>
                                    <span class="badge bg-success">Passed</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Failed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="view-attempt-details.php?attempt_id=<?php echo $attempt['id']; ?>" 
                                   class="btn btn-sm btn-primary">
                                    View Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>