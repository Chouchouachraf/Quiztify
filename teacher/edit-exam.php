<?php
require_once '../includes/functions.php';
checkRole('teacher');

$examId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$conn = getDBConnection();

try {
    // Get exam details
    $stmt = $conn->prepare("
        SELECT e.*, COUNT(DISTINCT ea.id) as attempt_count
        FROM exams e
        LEFT JOIN exam_attempts ea ON e.id = ea.exam_id
        WHERE e.id = ? AND e.created_by = ?
        GROUP BY e.id
    ");
    $stmt->execute([$examId, $_SESSION['user_id']]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        setFlashMessage('error', 'Exam not found or access denied.');
        header('Location: exams.php');
        exit;
    }

    // Get exam questions
    $stmt = $conn->prepare("
        SELECT q.*
        FROM questions q
        WHERE q.exam_id = ?
        ORDER BY q.id
    ");
    $stmt->execute([$examId]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error in edit-exam.php: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading the exam.');
    header('Location: exams.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        // Update exam details
        $stmt = $conn->prepare("
            UPDATE exams SET
                title = ?,
                description = ?,
                start_date = ?,
                end_date = ?,
                duration_minutes = ?,
                is_published = ?
            WHERE id = ? AND created_by = ?
        ");

        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['start_date'],
            $_POST['end_date'],
            $_POST['duration_minutes'],
            isset($_POST['is_published']) ? 1 : 0,
            $examId,
            $_SESSION['user_id']
        ]);

        // Update question texts
        if (isset($_POST['question_texts']) && is_array($_POST['question_texts'])) {
            foreach ($_POST['question_texts'] as $questionId => $questionText) {
                $stmt = $conn->prepare("
                    UPDATE questions SET
                        question_text = ?
                    WHERE id = ? AND exam_id = ?
                ");
                $stmt->execute([$questionText, $questionId, $examId]);
            }
        }

        $conn->commit();
        setFlashMessage('success', 'Exam updated successfully.');
        header('Location: exams.php');
        exit;

    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Error updating exam: " . $e->getMessage());
        setFlashMessage('error', 'An error occurred while updating the exam.');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Exam - <?php echo htmlspecialchars($exam['title']); ?></title>
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
            --shadow-color: rgba(0, 0, 0, 0.1);
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
            --border-color: #444444;
            --shadow-color: rgba(0, 0, 0, 0.3);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--background-color);
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        h1 {
            margin: 0;
            color: var(--primary-color);
        }

        .theme-toggle {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--text-color);
        }

        .form-section {
            background: var(--background-color);
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px var(--shadow-color);
        }

        .form-section h2 {
            color: var(--primary-color);
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            background-color: var(--background-color);
            color: var(--text-color);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--secondary-color);
        }

        .questions-container {
            margin-top: 20px;
        }

        .question-card {
            background: var(--light-color);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
        }

        .btn-container {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 5px;
            border: none;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: var(--secondary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .warning-message {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .warning-message i {
            font-size: 20px;
        }
    </style>
</head>
<body class="light-mode">
    <div class="container">
        <header>
            <h1>Edit Exam: <?php echo htmlspecialchars($exam['title']); ?></h1>
            <button id="theme-toggle" class="theme-toggle" title="Toggle Dark Mode">
                <i class="fas fa-moon"></i>
            </button>
        </header>

        <form method="POST" id="editExamForm">
            <div class="form-section">
                <h2>Basic Details</h2>
                <div class="form-group">
                    <label for="title">Exam Title</label>
                    <input type="text" id="title" name="title" class="form-control" 
                           value="<?php echo htmlspecialchars($exam['title']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="4"
                            required><?php echo htmlspecialchars($exam['description']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="datetime-local" id="start_date" name="start_date" class="form-control"
                           value="<?php echo date('Y-m-d\TH:i', strtotime($exam['start_date'])); ?>" required>
                </div>

                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input type="datetime-local" id="end_date" name="end_date" class="form-control"
                           value="<?php echo date('Y-m-d\TH:i', strtotime($exam['end_date'])); ?>" required>
                </div>

                <div class="form-group">
                    <label for="duration_minutes">Duration (minutes)</label>
                    <input type="number" id="duration_minutes" name="duration_minutes" class="form-control"
                           value="<?php echo $exam['duration_minutes']; ?>" required min="1">
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_published" value="1" 
                               <?php echo $exam['is_published'] ? 'checked' : ''; ?>>
                        Published
                    </label>
                </div>
            </div>

            <div class="form-section">
                <h2>Questions</h2>
                <div class="questions-container">
                    <?php foreach ($questions as $question): ?>
                        <div class="question-card">
                            <div class="form-group">
                                <label>Question <?php echo $question['id']; ?></label>
                                <input type="text" name="question_texts[<?php echo $question['id']; ?>]" 
                                       class="form-control" value="<?php echo htmlspecialchars($question['question_text']); ?>" required>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="btn-container">
                <button type="button" class="btn btn-danger" onclick="history.back()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    <script>
        // Form validation
        document.getElementById('editExamForm').addEventListener('submit', function(e) {
            const startDate = new Date(document.getElementById('start_date').value);
            const endDate = new Date(document.getElementById('end_date').value);
            
            if (endDate <= startDate) {
                e.preventDefault();
                alert('End date must be after start date');
            }
        });

        // Dark/Light mode toggle
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.body.dataset.theme = savedTheme;

            document.getElementById('theme-toggle').addEventListener('click', function() {
                const newTheme = document.body.dataset.theme === 'dark' ? 'light' : 'dark';
                document.body.dataset.theme = newTheme;
                localStorage.setItem('theme', newTheme);
                
                // Update icon based on theme
                if (newTheme === 'dark') {
                    this.innerHTML = '<i class="fas fa-sun"></i>';
                } else {
                    this.innerHTML = '<i class="fas fa-moon"></i>';
                }
            });
        });
    </script>
</body>
</html>