<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a teacher
checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Fetch teacher's classrooms
try {
    $stmt = $conn->prepare("
        SELECT id, name, department 
        FROM classrooms 
        WHERE teacher_id = ? 
        ORDER BY name
    ");
    $stmt->execute([$teacher_id]);
    $classrooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching classrooms: " . $e->getMessage());
    $classrooms = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $conn->beginTransaction();

        // Basic exam information
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $duration = (int)$_POST['duration'];
        $passing_score = (float)$_POST['passing_score'];
        $attempts_allowed = (int)$_POST['attempts_allowed'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $selected_classrooms = $_POST['classrooms'] ?? [];
        $is_published = isset($_POST['is_published']) ? 1 : 0;

        // Validate input
        if (empty($title) || empty($duration) || empty($start_date) || empty($end_date)) {
            throw new Exception('Please fill in all required fields.');
        }

        if (empty($selected_classrooms)) {
            throw new Exception('Please select at least one classroom.');
        }

        // Insert exam
        $stmt = $conn->prepare("
            INSERT INTO exams (
                title, description, duration_minutes, passing_score,
                attempts_allowed, start_date, end_date, is_published, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $title, $description, $duration, $passing_score,
            $attempts_allowed, $start_date, $end_date, $is_published, $teacher_id
        ]);
        
        $exam_id = $conn->lastInsertId();

        // Create exam_classrooms entries
        $stmt = $conn->prepare("
            INSERT INTO exam_classrooms (exam_id, classroom_id) 
            VALUES (?, ?)
        ");

        foreach ($selected_classrooms as $classroom_id) {
            $stmt->execute([$exam_id, $classroom_id]);
        }

        // Process questions
        $questions = $_POST['questions'];
        $stmt = $conn->prepare("
            INSERT INTO questions (
                exam_id, question_text, question_type, points, order_num
            ) VALUES (?, ?, ?, ?, ?)
        ");

        $option_stmt = $conn->prepare("
            INSERT INTO mcq_options (
                question_id, option_text, is_correct
            ) VALUES (?, ?, ?)
        ");

        foreach ($questions as $index => $question) {
            // Insert question
            $stmt->execute([
                $exam_id,
                $question['text'],
                $question['type'],
                $question['points'],
                $index + 1
            ]);

            $question_id = $conn->lastInsertId();

            // Insert options for MCQ and True/False questions
            if (in_array($question['type'], ['mcq', 'true_false'])) {
                foreach ($question['options'] as $opt_index => $option) {
                    $is_correct = ($opt_index == $question['correct_option']) ? 1 : 0;
                    $option_stmt->execute([
                        $question_id,
                        $option,
                        $is_correct
                    ]);
                }
            }
        }

        // Commit transaction
        $conn->commit();
        setFlashMessage('success', 'Exam created successfully!');
        header('Location: exams.php');
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        setFlashMessage('error', $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Exam - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .question-container {
            border: 1px solid #dee2e6;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .option-container {
            margin-top: 10px;
        }
        .remove-question {
            color: #dc3545;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Create New Exam</h1>

        <?php if ($flash = getFlashMessage()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo $flash['message']; ?>
            </div>
        <?php endif; ?>

        <form id="examForm" method="POST" action="">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Title*</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Duration (minutes)*</label>
                    <input type="number" name="duration" class="form-control" required min="1">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Start Date and Time*</label>
                    <input type="datetime-local" name="start_date" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">End Date and Time*</label>
                    <input type="datetime-local" name="end_date" class="form-control" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Passing Score (%)</label>
                    <input type="number" name="passing_score" class="form-control" value="60" min="0" max="100">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Attempts Allowed</label>
                    <input type="number" name="attempts_allowed" class="form-control" value="1" min="1">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Published</label>
                    <div class="form-check mt-2">
                        <input type="checkbox" name="is_published" class="form-check-input" id="isPublished">
                        <label class="form-check-label" for="isPublished">Make exam available</label>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Select Classrooms*</label>
                <?php if (empty($classrooms)): ?>
                    <p class="text-danger">You need to create a classroom first.</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($classrooms as $classroom): ?>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" name="classrooms[]" 
                                           class="form-check-input" 
                                           value="<?php echo $classroom['id']; ?>" 
                                           id="classroom_<?php echo $classroom['id']; ?>">
                                    <label class="form-check-label" for="classroom_<?php echo $classroom['id']; ?>">
                                        <?php echo htmlspecialchars($classroom['name']); ?> 
                                        (<?php echo htmlspecialchars($classroom['department']); ?>)
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div id="questionsContainer">
                <!-- Questions will be added here dynamically -->
            </div>

            <button type="button" class="btn btn-secondary mb-3" onclick="addQuestion()">Add Question</button>
            <button type="submit" class="btn btn-primary mb-3">Create Exam</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize datetime pickers
        flatpickr("input[type=datetime-local]", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
        });

        let questionCount = 0;

        function addQuestion() {
            const container = document.getElementById('questionsContainer');
            const questionDiv = document.createElement('div');
            questionDiv.className = 'question-container';
            questionDiv.innerHTML = `
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h4>Question ${questionCount + 1}</h4>
                    <span class="remove-question" onclick="removeQuestion(this)">×</span>
                </div>
                <input type="hidden" name="questions[${questionCount}][order]" value="${questionCount + 1}">
                
                <div class="mb-3">
                    <label class="form-label">Question Text*</label>
                    <textarea name="questions[${questionCount}][text]" class="form-control" required></textarea>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Question Type*</label>
                        <select name="questions[${questionCount}][type]" class="form-control" 
                                onchange="handleQuestionType(this, ${questionCount})" required>
                            <option value="mcq">Multiple Choice</option>
                            <option value="true_false">True/False</option>
                            <option value="open">Open Answer</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Points</label>
                        <input type="number" name="questions[${questionCount}][points]" 
                               class="form-control" value="1" min="0" step="0.5">
                    </div>
                </div>

                <div id="options_${questionCount}" class="options-container">
                    <!-- Options will be added here based on question type -->
                </div>
            `;
            container.appendChild(questionDiv);
            handleQuestionType(questionDiv.querySelector('select'), questionCount);
            questionCount++;
        }

        function handleQuestionType(select, qIndex) {
            const optionsContainer = document.getElementById(`options_${qIndex}`);
            const type = select.value;

            if (type === 'mcq') {
                optionsContainer.innerHTML = `
                    <div class="mb-3">
                        <label class="form-label">Options*</label>
                        <div class="option-container">
                            <div class="mb-2">
                                <input type="text" name="questions[${qIndex}][options][]" 
                                       class="form-control mb-2" placeholder="Option 1" required>
                                <input type="text" name="questions[${qIndex}][options][]" 
                                       class="form-control mb-2" placeholder="Option 2" required>
                                <input type="text" name="questions[${qIndex}][options][]" 
                                       class="form-control mb-2" placeholder="Option 3" required>
                                <input type="text" name="questions[${qIndex}][options][]" 
                                       class="form-control" placeholder="Option 4" required>
                            </div>
                        </div>
                        <label class="form-label mt-3">Correct Option*</label>
                        <select name="questions[${qIndex}][correct_option]" class="form-control" required>
                            <option value="0">Option 1</option>
                            <option value="1">Option 2</option>
                            <option value="2">Option 3</option>
                            <option value="3">Option 4</option>
                        </select>
                    </div>
                `;
            } else if (type === 'true_false') {
                optionsContainer.innerHTML = `
                    <div class="mb-3">
                        <label class="form-label">Correct Answer*</label>
                        <select name="questions[${qIndex}][correct_option]" class="form-control" required>
                            <option value="0">True</option>
                            <option value="1">False</option>
                        </select>
                        <input type="hidden" name="questions[${qIndex}][options][]" value="True">
                        <input type="hidden" name="questions[${qIndex}][options][]" value="False">
                    </div>
                `;
            } else {
                optionsContainer.innerHTML = ''; // No options for open questions
            }
        }

        function removeQuestion(button) {
            button.closest('.question-container').remove();
        }

        // Add first question automatically
        addQuestion();
    </script>
</body>
</html>