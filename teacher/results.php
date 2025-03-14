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
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f1c40f;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --background-color: #f5f6fa;
            --text-color: #2c3e50;
            --border-color: #ddd;
            --table-header-bg: #f8f9fa;
            --table-hover-bg: #f1f2f6;
            --card-bg: #ffffff;
        }

        [data-theme="dark"] {
            --background-color: #1a1a1a;
            --text-color: #ffffff;
            --primary-color: #2980b9;
            --secondary-color: #3498db;
            --success-color: #44bb77;
            --danger-color: #ff5555;
            --warning-color: #ffcc00;
            --light-color: #333333;
            --dark-color: #ffffff;
            --border-color: #444;
            --table-header-bg: #2c3e50;
            --table-hover-bg: #2c2c2c;
            --card-bg: #242424;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: var(--background-color);
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s;
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
            color: var(--primary-color);
        }

        .results-container {
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: background-color 0.3s;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
        }

        .results-table th,
        .results-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            transition: border-color 0.3s;
        }

        .results-table th {
            background: var(--table-header-bg);
            font-weight: 600;
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s;
        }

        .results-table tr:hover {
            background: var(--table-hover-bg);
            transition: background-color 0.3s;
        }

        .score-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: 500;
            font-size: 14px;
        }

        .score-pass {
            background: var(--success-color);
            color: white;
        }

        .score-fail {
            background: var(--danger-color);
            color: white;
        }

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .filter-input {
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            background-color: var(--card-bg);
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s, border-color 0.3s;
        }

        .filter-input::placeholder {
            color: var(--text-color);
            opacity: 0.7;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-color);
        }

        .empty-state i {
            font-size: 48px;
            color: var(--light-color);
            margin-bottom: 15px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .alert-success {
            background: var(--success-color);
            color: white;
            border: 1px solid rgba(0,0,0,0.1);
        }

        .alert-error {
            background: var(--danger-color);
            color: white;
            border: 1px solid rgba(0,0,0,0.1);
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .filters {
                width: 100%;
                flex-direction: column;
            }
            
            .results-table {
                display: block;
                overflow-x: auto;
            }
        }
                /* Light mode table text color */
table th, table td {
    color: var(--text-color);
}

/* Dark mode table text color */
[data-theme="dark"] table th, [data-theme="dark"] table td {
    color: var(--text-color);
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

        <?php if ($flash = getFlashMessage()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo $flash['message']; ?>
            </div>
        <?php endif; ?>

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
                            <th>Completion Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($result['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($result['exam_title']); ?></td>
                                <td><?php echo number_format($result['score'], 1); ?>%</td>
                                <td><?php echo $result['questions_answered']; ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($result['end_time'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set theme from localStorage
            const theme = localStorage.getItem('theme') || 'light';
            document.body.dataset.theme = theme;

            // Theme toggle functionality is handled in teacher-nav.php
            
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

            if (studentFilter && examFilter) {
                studentFilter.addEventListener('input', filterResults);
                examFilter.addEventListener('input', filterResults);
            }
        });
    </script>
</body>
</html>