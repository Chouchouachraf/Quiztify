<?php
require_once '../includes/functions.php';
checkRole('teacher');

$conn = getDBConnection();

// Get all exams created by this teacher
$stmt = $conn->prepare("
    SELECT 
        e.id,
        e.title,
        e.description,
        e.start_date,
        e.end_date,
        e.duration_minutes,
        e.attempts_allowed,
        e.is_published,
        COUNT(DISTINCT q.id) as question_count,
        COUNT(DISTINCT ea.id) as attempt_count
    FROM exams e
    LEFT JOIN questions q ON e.id = q.exam_id
    LEFT JOIN exam_attempts ea ON e.id = ea.exam_id
    WHERE e.created_by = ?
    GROUP BY e.id, e.title, e.description, e.start_date, e.end_date, 
             e.duration_minutes, e.attempts_allowed, e.is_published
    ORDER BY e.created_at DESC
");

$stmt->execute([$_SESSION['user_id']]);
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Exams - Quiztify</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .exam-list {
            margin: 20px 0;
        }
        .exam-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .exam-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .exam-stats {
            display: flex;
            gap: 20px;
            margin: 10px 0;
            color: #666;
        }
        .exam-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
        }
        .btn-primary { background: #4CAF50; color: white; }
        .btn-secondary { background: #2196F3; color: white; }
        .btn-danger { background: #f44336; color: white; }
        .btn-success { background: #45a049; color: white; }
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            margin-left: 10px;
        }
        .status-published {
            background: #4CAF50;
            color: white;
        }
        .status-draft {
            background: #757575;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/teacher-nav.php'; ?>
        
        <div class="content">
            <div class="header-actions">
                <h2>Manage Exams</h2>
                <a href="create-exam.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create New Exam
                </a>
            </div>

            <?php if ($flash = getFlashMessage()): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo $flash['message']; ?>
                </div>
            <?php endif; ?>

            <div class="exam-list">
                <?php if (empty($exams)): ?>
                    <div class="empty-state">
                        <p>You haven't created any exams yet.</p>
                        <a href="create-exam.php" class="btn btn-primary">Create Your First Exam</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($exams as $exam): ?>
                        <div class="exam-card">
                            <div class="exam-header">
                                <h3>
                                    <?php echo htmlspecialchars($exam['title']); ?>
                                    <span class="status-badge <?php echo $exam['is_published'] ? 'status-published' : 'status-draft'; ?>">
                                        <?php echo $exam['is_published'] ? 'Published' : 'Draft'; ?>
                                    </span>
                                </h3>
                            </div>
                            
                            <p><?php echo htmlspecialchars($exam['description']); ?></p>
                            
                            <div class="exam-stats">
                                <span><i class="fas fa-question-circle"></i> <?php echo $exam['question_count']; ?> questions</span>
                                <span><i class="fas fa-clock"></i> <?php echo $exam['duration_minutes']; ?> minutes</span>
                                <span><i class="fas fa-users"></i> <?php echo $exam['attempt_count']; ?> attempts</span>
                            </div>
                            
                            <div class="exam-dates">
                                <span><strong>Start:</strong> <?php echo date('M j, Y g:i A', strtotime($exam['start_date'])); ?></span>
                                <br>
                                <span><strong>End:</strong> <?php echo date('M j, Y g:i A', strtotime($exam['end_date'])); ?></span>
                            </div>
                            
                            <div class="exam-actions">
                                <?php if (!$exam['is_published']): ?>
                                    <form method="POST" action="publish-exam.php" style="display: inline;">
                                        <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-check"></i> Publish
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="unpublish-exam.php" style="display: inline;">
                                        <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                        <button type="submit" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Unpublish
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <a href="edit-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-secondary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="view-results.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-chart-bar"></i> View Results
                                </a>
                                <button onclick="deleteExam(<?php echo $exam['id']; ?>)" class="btn btn-danger">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function deleteExam(examId) {
            if (confirm('Are you sure you want to delete this exam? This action cannot be undone.')) {
                window.location.href = `delete-exam.php?id=${examId}`;
            }
        }
    </script>
</body>
</html>