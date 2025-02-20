<?php
require_once '../includes/functions.php';
checkRole('teacher');

$conn = getDBConnection();

try {
    // Get all exam results with student details
    $stmt = $conn->prepare("
        SELECT 
            ea.id as attempt_id,
            ea.score,
            ea.start_time,
            ea.end_time,
            e.id as exam_id,
            e.title as exam_title,
            e.passing_score,
            u.full_name as student_name,
            u.email as student_email,
            (
                SELECT COUNT(*)
                FROM student_answers sa
                WHERE sa.attempt_id = ea.id
            ) as questions_answered
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        JOIN users u ON ea.student_id = u.id
        WHERE e.created_by = ?
        ORDER BY ea.end_time DESC
    ");
    
    $stmt->execute([$_SESSION['user_id']]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error in results.php: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading results.');
    $results = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Results - Teacher Dashboard</title>
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

        .results-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
        }

        .results-table th,
        .results-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .results-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .results-table tr:hover {
            background: #f8f9fa;
        }

        .score-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: 500;
            font-size: 14px;
        }

        .score-pass {
            background: #d4edda;
            color: #155724;
        }

        .score-fail {
            background: #f8d7da;
            color: #721c24;
        }

        .btn-view {
            display: inline-block;
            padding: 6px 12px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            transition: background 0.3s;
        }

        .btn-view:hover {
            background: #2980b9;
        }

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .filter-input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .results-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/teacher-nav.php'; ?>

        <div class="page-header">
            <h1 class="page-title">Exam Results</h1>
            <div class="filters">
                <input type="text" id="studentFilter" class="filter-input" placeholder="Search by student name...">
                <input type="text" id="examFilter" class="filter-input" placeholder="Search by exam title...">
            </div>
        </div>

        <div class="results-container">
            <?php if (empty($results)): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <p>No exam results available yet.</p>
                </div>
            <?php else: ?>
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Exam Title</th>
                            <th>Score</th>
                            <th>Questions</th>
                            <th>Status</th>
                            <th>Completion Time</th>
                            <th>Duration</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($result['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($result['exam_title']); ?></td>
                                <td><?php echo number_format($result['score'], 1); ?>%</td>
                                <td><?php echo $result['questions_answered']; ?></td>
                                <td>
                                    <span class="score-badge <?php echo $result['score'] >= $result['passing_score'] ? 'score-pass' : 'score-fail'; ?>">
                                        <?php echo $result['score'] >= $result['passing_score'] ? 'Passed' : 'Failed'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($result['end_time'])); ?></td>
                                <td>
                                    <?php 
                                    $duration = strtotime($result['end_time']) - strtotime($result['start_time']);
                                    echo floor($duration / 60) . ' mins';
                                    ?>
                                </td>
                                <td>
                                    <a href="view-attempt.php?id=<?php echo $result['attempt_id']; ?>" class="btn-view">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Search functionality
        const studentFilter = document.getElementById('studentFilter');
        const examFilter = document.getElementById('examFilter');
        const tableRows = document.querySelectorAll('.results-table tbody tr');

        function filterResults() {
            const studentSearch = studentFilter.value.toLowerCase();
            const examSearch = examFilter.value.toLowerCase();

            tableRows.forEach(row => {
                const studentName = row.cells[0].textContent.toLowerCase();
                const examTitle = row.cells[1].textContent.toLowerCase();
                
                const matchesStudent = studentName.includes(studentSearch);
                const matchesExam = examTitle.includes(examSearch);

                row.style.display = (matchesStudent && matchesExam) ? '' : 'none';
            });
        }

        studentFilter.addEventListener('input', filterResults);
        examFilter.addEventListener('input', filterResults);
    </script>
</body>
</html>