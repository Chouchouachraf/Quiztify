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
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $selected_classrooms = $_POST['classrooms'] ?? [];
        $is_published = isset($_POST['is_published']) ? 1 : 0;
        $total_points = $_POST['total_points'];

        // Validate input
        if (empty($title) || empty($start_date) || empty($end_date)) {
            throw new Exception('Please fill in all required fields.');
        }

        if (empty($selected_classrooms)) {
            throw new Exception('Please select at least one classroom.');
        }

        // Insert exam
        $stmt = $conn->prepare("
            INSERT INTO exams (
                title, description, start_date, end_date, 
                is_published, created_by, total_points
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $title, $description, $start_date, $end_date,
            $is_published, $teacher_id, $total_points
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
                exam_id, question_text, question_type, points, 
                order_num, question_image, correct_answer
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $option_stmt = $conn->prepare("
            INSERT INTO mcq_options (
                question_id, option_text, is_correct
            ) VALUES (?, ?, ?)
        ");

        foreach ($questions as $index => $q) {
            $question_image = null;
            
            // Handle image upload
            if (isset($_FILES['questions']['name'][$index]['image']) && 
                $_FILES['questions']['error'][$index]['image'] === UPLOAD_ERR_OK) {
                
                $image = $_FILES['questions']['name'][$index]['image'];
                $image_temp = $_FILES['questions']['tmp_name'][$index]['image'];
                $image_ext = strtolower(pathinfo($image, PATHINFO_EXTENSION));
                
                // Validate image type
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($image_ext, $allowed_types)) {
                    throw new Exception('Invalid image type. Allowed types: ' . implode(', ', $allowed_types));
                }
                
                // Generate unique filename
                $new_filename = uniqid() . '.' . $image_ext;
                $upload_path = '../uploads/questions/' . $new_filename;
                
                // Create directory if it doesn't exist
                if (!is_dir('../uploads/questions')) {
                    mkdir('../uploads/questions', 0777, true);
                }
                
                // Move uploaded file
                if (move_uploaded_file($image_temp, $upload_path)) {
                    $question_image = $new_filename;
                }
            }

            // Validate question points
            $points = floatval($q['points']);
            if ($points <= 0) {
                throw new Exception('Question points must be greater than 0');
            }

            // Insert question
            $correct_answer = null;
            if ($q['type'] === 'open') {
                $correct_answer = $q['correct_answer'];
            } elseif ($q['type'] === 'true_false') {
                $correct_answer = $q['correct_option']; // 'true' or 'false'
            }

            $stmt->execute([
                $exam_id,
                $q['text'],
                $q['type'],
                $points,
                $index + 1,
                $question_image,
                $correct_answer
            ]);

            $question_id = $conn->lastInsertId();

            // Insert options for MCQ questions
            if ($q['type'] === 'mcq') {
                $option_stmt = $conn->prepare("
                    INSERT INTO mcq_options (
                        question_id, option_text, is_correct
                    ) VALUES (?, ?, ?)
                ");

                foreach ($q['options'] as $opt_index => $option) {
                    $is_correct = ($opt_index == $q['correct_option']) ? 1 : 0;
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: #f5f6fa;
            color: #2c3e50;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .card-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            background: #f8f9fa;
            border-radius: 15px 15px 0 0;
        }

        .card-header h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.5rem;
        }

        .card-body {
            padding: 20px;
        }

        .form-label {
            font-weight: 500;
            color: #2c3e50;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #3498db;
            border: none;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .question-card {
            border: 1px solid #e1e8ed;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            background: #fff;
            transition: all 0.3s ease;
        }

        .question-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .preview-image {
            border-radius: 8px;
            border: 1px solid #e1e8ed;
        }

        .exam-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status-upcoming {
            background-color: #f1c40f;
            color: #000;
        }

        .status-active {
            background-color: #2ecc71;
            color: #fff;
        }

        .status-expired {
            background-color: #e74c3c;
            color: #fff;
        }

        .btn-start {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-start:hover:not(:disabled) {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        .btn-start:disabled {
            background-color: #bdc3c7;
            cursor: not-allowed;
        }

        .countdown {
            font-family: monospace;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include '../includes/teacher-nav.php'; ?>
    
    <div class="main-container">
        <div class="card">
            <div class="card-header">
                <h2 class="mb-0">Create New Exam</h2>
            </div>
            <div class="card-body">
                <?php if ($flash = getFlashMessage()): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?>">
                        <?php echo $flash['message']; ?>
                    </div>
                <?php endif; ?>

                <form id="examForm" method="POST" action="" enctype="multipart/form-data">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Title*</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total Points*</label>
                            <select name="total_points" class="form-control" required>
                                <option value="10">10 Points</option>
                                <option value="20">20 Points</option>
                                <option value="40">40 Points</option>
                                <option value="100">100 Points</option>
                            </select>
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
                        <div class="col-md-6">
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
        </div>
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
            const questionCard = document.createElement('div');
            questionCard.className = 'question-card';
            questionCard.innerHTML = `
                <div class="question-header">
                    <h4>Question ${questionCount + 1}</h4>
                    <span class="remove-question" onclick="removeQuestion(this)">
                        <i class="fas fa-times"></i>
                    </span>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Question Text*</label>
                        <textarea name="questions[${questionCount}][text]" class="form-control" required rows="3"></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Question Points*</label>
                        <input type="number" name="questions[${questionCount}][points]" 
                               class="form-control" required min="0.5" step="0.5" value="1">
                        <small class="text-muted">Points for this question</small>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Question Image</label>
                    <input type="file" name="questions[${questionCount}][image]" 
                           class="form-control" accept="image/*"
                           onchange="previewImage(this, 'preview_${questionCount}')">
                    <img id="preview_${questionCount}" class="preview-image" alt="Preview" style="display:none; max-width:200px; margin-top:10px;">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Question Type*</label>
                    <select name="questions[${questionCount}][type]" class="form-control" 
                            onchange="handleQuestionType(this, ${questionCount})" required>
                        <option value="mcq">Multiple Choice</option>
                        <option value="true_false">True/False</option>
                        <option value="open">Open Answer</option>
                    </select>
                </div>
                
                <div id="options_${questionCount}" class="options-container">
                    <!-- Options will be added here -->
                </div>
            `;
            
            container.appendChild(questionCard);
            handleQuestionType(questionCard.querySelector('select'), questionCount);
            questionCount++;
        }

        function handleQuestionType(select, questionIndex) {
            const container = document.getElementById(`options_${questionIndex}`);
            const type = select.value;
            
            switch(type) {
                case 'mcq':
                    container.innerHTML = `
                        <div class="mb-3">
                            <label class="form-label">Number of Options</label>
                            <select class="form-control" onchange="updateMCQOptions(this, ${questionIndex})">
                                <option value="2">2 Options</option>
                                <option value="3">3 Options</option>
                                <option value="4" selected>4 Options</option>
                                <option value="5">5 Options</option>
                                <option value="6">6 Options</option>
                            </select>
                        </div>
                        <div id="mcq_options_${questionIndex}"></div>
                    `;
                    updateMCQOptions(container.querySelector('select'), questionIndex);
                    break;
                    
                case 'true_false':
                    container.innerHTML = `
                        <div class="options-list">
                            <div class="option-row">
                                <div class="form-check">
                                    <input type="radio" name="questions[${questionIndex}][correct_option]" 
                                           value="true" class="form-check-input" required>
                                    <label class="form-check-label">True</label>
                                </div>
                            </div>
                            <div class="option-row">
                                <div class="form-check">
                                    <input type="radio" name="questions[${questionIndex}][correct_option]" 
                                           value="false" class="form-check-input" required>
                                    <label class="form-check-label">False</label>
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'open':
                    container.innerHTML = `
                        <div class="mb-3">
                            <label class="form-label">Correct Answer*</label>
                            <textarea name="questions[${questionIndex}][correct_answer]" 
                                      class="form-control" rows="2" required
                                      placeholder="Enter the correct answer"></textarea>
                        </div>
                    `;
                    break;
            }
        }

        function updateMCQOptions(select, questionIndex) {
            const container = document.getElementById(`mcq_options_${questionIndex}`);
            const count = parseInt(select.value);
            
            let html = '<div class="options-list">';
            for (let i = 0; i < count; i++) {
                html += `
                    <div class="option-row">
                        <input type="text" name="questions[${questionIndex}][options][]" 
                               class="form-control" placeholder="Option ${i + 1}" required>
                        <div class="form-check">
                            <input type="radio" name="questions[${questionIndex}][correct_option]" 
                                   value="${i}" class="form-check-input" required>
                            <label class="form-check-label">Correct</label>
                        </div>
                    </div>
                `;
            }
            html += '</div>';
            container.innerHTML = html;
        }

        function removeQuestion(button) {
            button.closest('.question-card').remove();
        }

        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Add first question automatically
        addQuestion();

        // Add form validation to check total points
        document.getElementById('examForm').addEventListener('submit', function(e) {
            const totalExamPoints = parseFloat(document.querySelector('select[name="total_points"]').value);
            let sumQuestionPoints = 0;
            
            // Calculate sum of question points
            const questionPoints = document.querySelectorAll('input[name$="[points]"]');
            questionPoints.forEach(input => {
                sumQuestionPoints += parseFloat(input.value);
            });
            
            if (sumQuestionPoints !== totalExamPoints) {
                e.preventDefault();
                alert(`The sum of question points (${sumQuestionPoints}) must equal the total exam points (${totalExamPoints})`);
                return;
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Update all countdown timers
            const timers = document.querySelectorAll('.countdown-timer');
            
            timers.forEach(timer => {
                const countdownSpan = timer.querySelector('.countdown');
                const startTime = parseInt(timer.dataset.startTime);
                
                function updateCountdown() {
                    const now = new Date().getTime();
                    const distance = startTime - now;
                    
                    if (distance <= 0) {
                        window.location.reload();
                        return;
                    }
                    
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                    
                    countdownSpan.textContent = `${hours}h ${minutes}m ${seconds}s`;
                }
                
                updateCountdown();
                setInterval(updateCountdown, 1000);
            });
        });

        // Auto refresh the page periodically to check for new available exams
        const refreshInterval = 60000; // 1 minute
        setInterval(() => {
            if (!document.hidden) {
                window.location.reload();
            }
        }, refreshInterval);
    </script>
</body>
</html>