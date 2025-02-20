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
        SELECT q.*, GROUP_CONCAT(
            CONCAT(mo.id, ':::', mo.option_text, ':::', mo.is_correct)
            ORDER BY mo.id SEPARATOR '|||'
        ) as options
        FROM questions q
        LEFT JOIN mcq_options mo ON q.id = mo.question_id
        WHERE q.exam_id = ?
        GROUP BY q.id
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
                passing_score = ?,
                attempts_allowed = ?,
                is_published = ?
            WHERE id = ? AND created_by = ?
        ");

        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['start_date'],
            $_POST['end_date'],
            $_POST['duration_minutes'],
            $_POST['passing_score'],
            $_POST['attempts_allowed'],
            isset($_POST['is_published']) ? 1 : 0,
            $examId,
            $_SESSION['user_id']
        ]);

        // Handle questions update if no attempts have been made
        if ($exam['attempt_count'] == 0) {
            // Delete existing questions and options
            $stmt = $conn->prepare("DELETE FROM mcq_options WHERE question_id IN (SELECT id FROM questions WHERE exam_id = ?)");
            $stmt->execute([$examId]);
            
            $stmt = $conn->prepare("DELETE FROM questions WHERE exam_id = ?");
            $stmt->execute([$examId]);

            // Add new questions and options
            if (isset($_POST['questions']) && is_array($_POST['questions'])) {
                foreach ($_POST['questions'] as $q) {
                    $stmt = $conn->prepare("
                        INSERT INTO questions (exam_id, question_text)
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$examId, $q['text']]);
                    $questionId = $conn->lastInsertId();

                    foreach ($q['options'] as $index => $option) {
                        $stmt = $conn->prepare("
                            INSERT INTO mcq_options (question_id, option_text, is_correct)
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([
                            $questionId,
                            $option,
                            $index == $q['correct_option'] ? 1 : 0
                        ]);
                    }
                }
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
        /* Add your CSS styles here */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .form-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .questions-container {
            margin-top: 20px;
        }

        .question-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .options-list {
            margin-top: 10px;
        }

        .option-item {
            margin-bottom: 5px;
        }

        .btn-container {
            margin-top: 20px;
            text-align: right;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            border: none;
            font-size: 14px;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .warning-message {
            background: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/teacher-nav.php'; ?>

        <h1>Edit Exam: <?php echo htmlspecialchars($exam['title']); ?></h1>

        <?php if ($exam['attempt_count'] > 0): ?>
            <div class="warning-message">
                <i class="fas fa-exclamation-triangle"></i>
                This exam has been attempted by students. You can only modify basic details, not questions.
            </div>
        <?php endif; ?>

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
                    <textarea id="description" name="description" class="form-control" rows="3"
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
                    <label for="passing_score">Passing Score (%)</label>
                    <input type="number" id="passing_score" name="passing_score" class="form-control"
                           value="<?php echo $exam['passing_score']; ?>" required min="0" max="100">
                </div>

                <div class="form-group">
                    <label for="attempts_allowed">Attempts Allowed</label>
                    <input type="number" id="attempts_allowed" name="attempts_allowed" class="form-control"
                           value="<?php echo $exam['attempts_allowed']; ?>" required min="1">
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_published" value="1" 
                               <?php echo $exam['is_published'] ? 'checked' : ''; ?>>
                        Published
                    </label>
                </div>
            </div>

            <?php if ($exam['attempt_count'] == 0): ?>
                <div class="form-section">
                    <h2>Questions</h2>
                    <div id="questionsContainer">
                        <?php foreach ($questions as $index => $question): ?>
                            <div class="question-card">
                                <div class="form-group">
                                    <label>Question <?php echo $index + 1; ?></label>
                                    <input type="text" name="questions[<?php echo $index; ?>][text]" 
                                           class="form-control" value="<?php echo htmlspecialchars($question['question_text']); ?>" required>
                                </div>

                                <div class="options-list">
                                    <?php 
                                    $options = explode('|||', $question['options']);
                                    foreach ($options as $optionIndex => $option):
                                        list($optionId, $optionText, $isCorrect) = explode(':::', $option);
                                    ?>
                                        <div class="option-item">
                                            <input type="text" name="questions[<?php echo $index; ?>][options][]" 
                                                   class="form-control" value="<?php echo htmlspecialchars($optionText); ?>" required>
                                            <input type="radio" name="questions[<?php echo $index; ?>][correct_option]" 
                                                   value="<?php echo $optionIndex; ?>" <?php echo $isCorrect ? 'checked' : ''; ?> required>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="addQuestion()">Add Question</button>
                </div>
            <?php endif; ?>

            <div class="btn-container">
                <button type="button" class="btn btn-danger" onclick="history.back()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    <script>
        function addQuestion() {
            const container = document.getElementById('questionsContainer');
            const questionIndex = container.children.length;
            
            const questionCard = document.createElement('div');
            questionCard.className = 'question-card';
            questionCard.innerHTML = `
                <div class="form-group">
                    <label>Question ${questionIndex + 1}</label>
                    <input type="text" name="questions[${questionIndex}][text]" class="form-control" required>
                </div>
                <div class="options-list">
                    ${Array(4).fill().map((_, i) => `
                        <div class="option-item">
                            <input type="text" name="questions[${questionIndex}][options][]" class="form-control" 
                                   placeholder="Option ${i + 1}" required>
                            <input type="radio" name="questions[${questionIndex}][correct_option]" 
                                   value="${i}" required>
                        </div>
                    `).join('')}
                </div>
            `;
            
            container.appendChild(questionCard);
        }

        // Form validation
        document.getElementById('editExamForm').addEventListener('submit', function(e) {
            const startDate = new Date(document.getElementById('start_date').value);
            const endDate = new Date(document.getElementById('end_date').value);
            
            if (endDate <= startDate) {
                e.preventDefault();
                alert('End date must be after start date');
            }
        });
    </script>
</body>
</html>