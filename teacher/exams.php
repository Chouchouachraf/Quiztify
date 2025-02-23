<?php
require_once '../includes/functions.php';
checkRole('teacher');

$conn = getDBConnection();

// Handle exam deletion
if (isset($_POST['delete_exam'])) {
    $exam_id = $_POST['exam_id'];
    try {
        $conn->beginTransaction();
        
        // Delete in correct order due to foreign key constraints
        // 1. First delete student answers
        $stmt = $conn->prepare("DELETE FROM student_answers WHERE attempt_id IN (SELECT id FROM exam_attempts WHERE exam_id = ?)");
        $stmt->execute([$exam_id]);

        // 2. Delete exam attempts
        $stmt = $conn->prepare("DELETE FROM exam_attempts WHERE exam_id = ?");
        $stmt->execute([$exam_id]);

        // 3. Delete MCQ options
        $stmt = $conn->prepare("DELETE FROM mcq_options WHERE question_id IN (SELECT id FROM questions WHERE exam_id = ?)");
        $stmt->execute([$exam_id]);

        // 4. Delete questions
        $stmt = $conn->prepare("DELETE FROM questions WHERE exam_id = ?");
        $stmt->execute([$exam_id]);

        // 5. Delete exam classroom associations
        $stmt = $conn->prepare("DELETE FROM exam_classrooms WHERE exam_id = ?");
        $stmt->execute([$exam_id]);

        // 6. Finally delete the exam
        $stmt = $conn->prepare("DELETE FROM exams WHERE id = ? AND created_by = ?");
        $stmt->execute([$exam_id, $_SESSION['user_id']]);
        
        $conn->commit();
        setFlashMessage('success', 'Exam deleted successfully.');
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error deleting exam: " . $e->getMessage());
        setFlashMessage('error', 'Failed to delete exam.');
    }
    header('Location: exams.php');
    exit;
}

// Add the helper function
function calculatePoints($percentage, $total_points) {
    return ($percentage / 100) * $total_points;
}

try {
    // Update the exams query to include total_points and other relevant fields
    $stmt = $conn->prepare("
        SELECT 
            e.*,
            c.name as classroom_name,
            COUNT(DISTINCT q.id) as question_count,
            COUNT(DISTINCT ea.id) as attempt_count,
            AVG(CASE WHEN ea.is_completed = 1 THEN ea.score ELSE NULL END) as average_score,
            COUNT(CASE WHEN ea.is_completed = 1 AND ea.score >= e.passing_score THEN 1 END) as passed_count
        FROM exams e
        LEFT JOIN exam_classrooms ec ON e.id = ec.exam_id
        LEFT JOIN classrooms c ON ec.classroom_id = c.id
        LEFT JOIN questions q ON e.id = q.exam_id
        LEFT JOIN exam_attempts ea ON e.id = ea.exam_id
        WHERE e.created_by = ?
        GROUP BY e.id, e.title, c.name
        ORDER BY e.created_at DESC
    ");
    
    $stmt->execute([$_SESSION['user_id']]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching exams: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading exams.');
    $exams = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Exams - Teacher Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: #f5f6fa;
            color: #2c3e50;
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

        .page-title {
            font-size: 24px;
            color: #2c3e50;
        }

        .btn-create {
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.3s;
        }

        .btn-create:hover {
            background: #2980b9;
        }

        .exams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .exam-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .exam-card:hover {
            transform: translateY(-5px);
        }

        .exam-header {
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .exam-title {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .exam-meta {
            font-size: 14px;
            color: #666;
        }

        .exam-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-published {
            background: #2ecc71;
            color: white;
        }

        .status-draft {
            background: #95a5a6;
            color: white;
        }

        .exam-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin: 15px 0;
        }

        .stat-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .stat-value {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .exam-dates {
            margin: 15px 0;
            font-size: 14px;
            color: #666;
        }

        .exam-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .btn {
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            flex: 1;
            text-align: center;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .btn-view {
            background: #3498db;
            color: white;
        }

        .btn-view:hover {
            background: #2980b9;
        }

        .btn-edit {
            background: #2ecc71;
            color: white;
        }

        .btn-edit:hover {
            background: #27ae60;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }

        .empty-state p {
            color: #666;
            margin-bottom: 15px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .exams-grid {
                grid-template-columns: 1fr;
            }

            .exam-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .percentage {
            color: #666;
            font-size: 0.85em;
            margin-left: 5px;
        }

        .score-display {
            display: flex;
            align-items: baseline;
            gap: 5px;
        }

        .score-value {
            font-size: 1.2em;
            font-weight: 500;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/teacher-nav.php'; ?>

        <div class="page-header">
            <h1 class="page-title">My Exams</h1>
            <a href="create-exam.php" class="btn-create">
                <i class="fas fa-plus"></i> Create New Exam
            </a>
        </div>

        <?php if ($flash = getFlashMessage()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo $flash['message']; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($exams)): ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <p>You haven't created any exams yet.</p>
                <a href="create-exam.php" class="btn-create">Create Your First Exam</a>
            </div>
        <?php else: ?>
            <div class="exams-grid">
                <?php foreach ($exams as $exam): ?>
                    <div class="exam-card">
                        <div class="exam-header">
                            <h3 class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></h3>
                            <div class="exam-meta">
                                Created: <?php echo date('M j, Y', strtotime($exam['created_at'])); ?>
                            </div>
                        </div>

                        <span class="exam-status <?php echo $exam['is_published'] ? 'status-published' : 'status-draft'; ?>">
                            <?php echo $exam['is_published'] ? 'Published' : 'Draft'; ?>
                        </span>

                        <div class="exam-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $exam['question_count']; ?></div>
                                <div class="stat-label">Questions</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $exam['attempt_count']; ?></div>
                                <div class="stat-label">Attempts</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">
                                    <?php if ($exam['average_score']): ?>
                                        <?= number_format(calculatePoints($exam['average_score'], $exam['total_points']), 1) ?>/<?= $exam['total_points'] ?>
                                        <small class="percentage">(<?= number_format($exam['average_score'], 1) ?>%)</small>
                                    <?php else: ?>
                                        No attempts
                                    <?php endif; ?>
                                </div>
                                <div class="stat-label">Avg. Score</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">
                                    <?= number_format(calculatePoints($exam['passing_score'], $exam['total_points']), 1) ?>/<?= $exam['total_points'] ?>
                                    <small class="percentage">(<?= $exam['passing_score'] ?>%)</small>
                                </div>
                                <div class="stat-label">Pass Score</div>
                            </div>
                        </div>

                        <div class="exam-dates">
                            <div class="exam-meta">
                                <i class="fas fa-calendar-alt"></i> 
                                Start: <?php echo date('M j, Y g:i A', strtotime($exam['start_date'])); ?>
                            </div>
                            <div class="exam-meta">
                                <i class="fas fa-calendar-check"></i>
                                End: <?php echo date('M j, Y g:i A', strtotime($exam['end_date'])); ?>
                            </div>
                        </div>

                        <div class="exam-actions">
                            <a href="view-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-view">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="edit-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <form method="POST" style="flex: 1;" onsubmit="return confirm('Are you sure you want to delete this exam?');">
                                <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                <button type="submit" name="delete_exam" class="btn btn-delete" style="width: 100%;">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>