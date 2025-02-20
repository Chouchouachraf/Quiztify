<?php
require_once '../includes/functions.php';

// Ensure user is logged in and is a teacher
checkRole('teacher');

if (!isset($_GET['exam_id'])) {
    setFlashMessage('error', 'No exam specified');
    header('Location: dashboard.php');
    exit();
}

try {
    $conn = getDBConnection();
    
    // Get exam details
    $stmt = $conn->prepare("
        SELECT * FROM exams 
        WHERE id = ? AND created_by = ?
    ");
    $stmt->execute([$_GET['exam_id'], $_SESSION['user_id']]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        setFlashMessage('error', 'Exam not found or unauthorized');
        header('Location: dashboard.php');
        exit();
    }

    // Get all attempts for this exam
    $stmt = $conn->prepare("
        SELECT 
            ea.*,
            u.full_name,
            u.username
        FROM exam_attempts ea
        JOIN users u ON ea.student_id = u.id
        WHERE ea.exam_id = ?
        ORDER BY ea.start_time DESC
    ");
    $stmt->execute([$_GET['exam_id']]);
    $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    setFlashMessage('error', 'Error fetching results: ' . $e->getMessage());
    header('Location: dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Exam Results - Quiztify</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="results-container">
        <header>
            <h1>Results: <?php echo htmlspecialchars($exam['title']); ?></h1>
            <a href="dashboard.php" class="btn">Back to Dashboard</a>
        </header>

        <?php if ($flash = getFlashMessage()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo $flash['message']; ?>
            </div>
        <?php endif; ?>

        <section class="exam-summary">
            <h2>Exam Summary</h2>
            <div class="summary-stats">
                <div class="stat">
                    <label>Total Attempts:</label>
                    <span><?php echo count($attempts); ?></span>
                </div>
                <div class="stat">
                    <label>Average Score:</label>
                    <span>
                        <?php 
                        $scores = array_column($attempts, 'score');
                        echo !empty($scores) ? number_format(array_sum($scores) / count($scores), 2) : 'N/A';
                        ?>
                    </span>
                </div>
                <div class="stat">
                    <label>Highest Score:</label>
                    <span><?php echo !empty($scores) ? max($scores) : 'N/A'; ?></span>
                </div>
                <div class="stat">
                    <label>Lowest Score:</label>
                    <span><?php echo !empty($scores) ? min($scores) : 'N/A'; ?></span>
                </div>
            </div>
        </section>

        <section class="attempts-list">
            <h2>Student Attempts</h2>
            <?php if (empty($attempts)): ?>
                <p>No attempts yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Score</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attempts as $attempt): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($attempt['full_name']); ?></td>
                                <td><?php echo formatDateTime($attempt['start_time']); ?></td>
                                <td><?php echo $attempt['end_time'] ? formatDateTime($attempt['end_time']) : 'In Progress'; ?></td>
                                <td><?php echo $attempt['score'] !== null ? $attempt['score'] : 'Not graded'; ?></td>
                                <td><?php echo $attempt['is_completed'] ? 'Completed' : 'In Progress'; ?></td>
                                <td>
                                    <a href="grade-attempt.php?attempt_id=<?php echo $attempt['id']; ?>" 
                                       class="btn btn-small">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>