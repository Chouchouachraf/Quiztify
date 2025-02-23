<?php
require_once '../includes/functions.php';
checkRole('teacher');

$conn = getDBConnection();
$teacher_id = $_SESSION['user_id'];

// Get all exams created by this teacher with student attempts
$stmt = $conn->prepare("
    SELECT 
        e.id as exam_id,
        e.title as exam_title,
        e.passing_score,
        e.total_points,
        c.name as classroom_name,
        COUNT(DISTINCT ea.student_id) as total_students,
        COUNT(CASE WHEN ea.score >= e.passing_score THEN 1 END) as passed_students,
        AVG(ea.score) as average_score
    FROM exams e
    JOIN exam_classrooms ec ON e.id = ec.exam_id
    JOIN classrooms c ON ec.classroom_id = c.id
    LEFT JOIN exam_attempts ea ON e.id = ea.exam_id AND ea.is_completed = 1
    WHERE e.created_by = ?
    GROUP BY e.id, e.title, e.total_points, c.name
    ORDER BY e.created_at DESC
");
$stmt->execute([$teacher_id]);
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function to convert percentage to points
function calculatePoints($percentage, $total_points) {
    return ($percentage / 100) * $total_points;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Grades - Quiztify</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .exam-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .exam-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .exam-title {
            font-size: 1.4em;
            color: #2c3e50;
            margin: 0;
        }

        .exam-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-label {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 1.2em;
            font-weight: 500;
            color: #2c3e50;
        }

        .grades-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .grades-table th,
        .grades-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .grades-table th {
            background: #f8f9fa;
            font-weight: 500;
            color: #2c3e50;
        }

        .grade-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.9em;
        }

        .grade-pass {
            background: #d4edda;
            color: #155724;
        }

        .grade-fail {
            background: #f8d7da;
            color: #721c24;
        }

        .toggle-grades {
            background: none;
            border: none;
            color: #3498db;
            cursor: pointer;
            padding: 5px 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .student-grades {
            display: none;
        }

        .student-grades.show {
            display: table-row-group;
        }

        .percentage {
            color: #666;
            font-size: 0.85em;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/teacher-nav.php'; ?>
        
        <h1>Exam Grades</h1>

        <?php foreach ($exams as $exam): ?>
            <div class="exam-card">
                <div class="exam-header">
                    <h2 class="exam-title"><?= htmlspecialchars($exam['exam_title']) ?></h2>
                    <span class="classroom"><?= htmlspecialchars($exam['classroom_name']) ?></span>
                </div>

                <div class="exam-stats">
                    <div class="stat-item">
                        <div class="stat-label">Total Students</div>
                        <div class="stat-value"><?= $exam['total_students'] ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Passed</div>
                        <div class="stat-value"><?= $exam['passed_students'] ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Average Score</div>
                        <div class="stat-value">
                            <?= number_format(calculatePoints($exam['average_score'], $exam['total_points']), 1) ?>/<?= $exam['total_points'] ?>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Passing Score</div>
                        <div class="stat-value">
                            <?= number_format(calculatePoints($exam['passing_score'], $exam['total_points']), 1) ?>/<?= $exam['total_points'] ?>
                        </div>
                    </div>
                </div>

                <button class="toggle-grades" onclick="toggleGrades(<?= $exam['exam_id'] ?>)">
                    <i class='bx bx-chevron-down'></i> View Student Grades
                </button>

                <table class="grades-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Score</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="grades-<?= $exam['exam_id'] ?>" class="student-grades">
                        <?php
                        $stmt = $conn->prepare("
                            SELECT 
                                u.full_name,
                                ea.score,
                                ea.end_time,
                                ea.id as attempt_id,
                                ea.published
                            FROM exam_attempts ea
                            JOIN users u ON ea.student_id = u.id
                            WHERE ea.exam_id = ? AND ea.is_completed = 1
                            ORDER BY ea.score DESC
                        ");
                        $stmt->execute([$exam['exam_id']]);
                        $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($attempts as $attempt):
                            $passed = $attempt['score'] >= $exam['passing_score'];
                            $points = calculatePoints($attempt['score'], $exam['total_points']);
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($attempt['full_name']) ?></td>
                                <td>
                                    <?= number_format($points, 1) ?>/<?= $exam['total_points'] ?>
                                    <small class="percentage">(<?= number_format($attempt['score'], 1) ?>%)</small>
                                </td>
                                <td>
                                    <span class="grade-badge <?= $passed ? 'grade-pass' : 'grade-fail' ?>">
                                        <?= $passed ? 'Passed' : 'Failed' ?>
                                    </span>
                                </td>
                                <td><?= date('M j, Y g:i A', strtotime($attempt['end_time'])) ?></td>
                                <td>
                                    <a href="view-attempt.php?id=<?= $attempt['attempt_id'] ?>">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        function toggleGrades(examId) {
            const gradesTable = document.getElementById(`grades-${examId}`);
            gradesTable.classList.toggle('show');
            
            const button = gradesTable.previousElementSibling.previousElementSibling;
            const icon = button.querySelector('i');
            if (gradesTable.classList.contains('show')) {
                icon.className = 'bx bx-chevron-up';
            } else {
                icon.className = 'bx bx-chevron-down';
            }
        }
    </script>
</body>
</html> 