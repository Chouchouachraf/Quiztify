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
        $has_timer = isset($_POST['has_timer']) ? 1 : 0;
        $duration_minutes = $has_timer ? (int)$_POST['duration_minutes'] : null;

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
                is_published, created_by, total_points,
                has_timer, duration_minutes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $title, $description, $start_date, $end_date,
            $is_published, $teacher_id, $total_points,
            $has_timer, $duration_minutes
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
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f1c40f;
            --light-color: #ecf0f1;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: #f5f6fa;
            color: var(--primary-color);
            padding-bottom: 2rem;
        }

        .main-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .card {
            background: white;
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }

        .card-header h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .form-label {
            font-weight: 500;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .question-card {
            background: #fff;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e1e8ed;
            position: relative;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
        }

        .remove-question {
            position: absolute;
            top: 1rem;
            right: 1rem;
            color: var(--danger-color);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .remove-question:hover {
            transform: scale(1.2);
        }

        .options-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .option-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.5rem;
            background: var(--light-color);
            border-radius: 8px;
        }

        .preview-image {
            max-width: 200px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--secondary-color);
            border: none;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--primary-color);
            border: none;
        }

        .btn-secondary:hover {
            background: #234567;
            transform: translateY(-2px);
        }

        .alert {
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        /* Custom checkbox and radio styles */
        .form-check-input {
            width: 1.2em;
            height: 1.2em;
            margin-top: 0.25em;
            cursor: pointer;
        }

        .form-check-label {
            cursor: pointer;
            user-select: none;
        }

        /* Animation for adding questions */
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .question-card {
            animation: slideDown 0.3s ease-out;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .main-container {
                margin: 1rem auto;
            }

            .card-header {
                padding: 1rem;
            }

            .question-card {
                padding: 1rem;
            }

            .option-row {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/teacher-nav.php'; ?>
    
    <div class="main-container">
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-edit me-2"></i>Create New Exam</h2>
            </div>
            <div class="card-body">
                <?php if ($flash = getFlashMessage()): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                        <?php echo $flash['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form id="examForm" method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <!-- Basic Info Section -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="mb-3">Basic Information</h4>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Title*</label>
                                    <input type="text" name="title" class="form-control" required
                                           placeholder="Enter exam title">
                                    <div class="invalid-feedback">Please provide an exam title.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Total Points*</label>
                                    <select name="total_points" class="form-control" required>
                                        <option value="">Select total points</option>
                                        <option value="10">10 Points</option>
                                        <option value="20">20 Points</option>
                                        <option value="40">40 Points</option>
                                        <option value="100">100 Points</option>
                                    </select>
                                    <div class="invalid-feedback">Please select total points.</div>
                                </div>
                            </div>

                            <div class="mb-3 mt-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3"
                                          placeholder="Enter exam description"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Timing Section -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="mb-3">Exam Timing</h4>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Start Date and Time*</label>
                                    <input type="datetime-local" name="start_date" class="form-control" required>
                                    <div class="invalid-feedback">Please set a start date and time.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">End Date and Time*</label>
                                    <input type="datetime-local" name="end_date" class="form-control" required>
                                    <div class="invalid-feedback">Please set an end date and time.</div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" name="has_timer" class="form-check-input" id="hasTimer">
                                        <label class="form-check-label" for="hasTimer">Enable Timer</label>
                                    </div>
                                </div>
                                <div class="col-md-6" id="durationField" style="display: none;">
                                    <label class="form-label">Duration (minutes)*</label>
                                    <input type="number" name="duration_minutes" class="form-control" min="1" value="60">
                                    <div class="invalid-feedback">Please set a valid duration.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Classrooms Section -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="mb-3">Select Classrooms*</h4>
                            <?php if (empty($classrooms)): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    You need to create a classroom first.
                                </div>
                            <?php else: ?>
                                <div class="row g-3">
                                    <?php foreach ($classrooms as $classroom): ?>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input type="checkbox" name="classrooms[]" 
                                                       class="form-check-input" 
                                                       value="<?php echo $classroom['id']; ?>" 
                                                       id="classroom_<?php echo $classroom['id']; ?>">
                                                <label class="form-check-label" for="classroom_<?php echo $classroom['id']; ?>">
                                                    <?php echo htmlspecialchars($classroom['name']); ?> 
                                                    <small class="text-muted">(<?php echo htmlspecialchars($classroom['department']); ?>)</small>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Questions Section -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="mb-3">Questions</h4>
                            <div id="questionsContainer"></div>
                            <button type="button" class="btn btn-secondary" onclick="addQuestion()">
                                <i class="fas fa-plus me-2"></i>Add Question
                            </button>
                        </div>
                    </div>

                    <!-- Submit Section -->
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="is_published" class="form-check-input" id="isPublished">
                            <label class="form-check-label" for="isPublished">Publish Exam</label>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create Exam
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize datetime pickers with better configuration
        flatpickr("input[type=datetime-local]", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            minDate: "today",
            time_24hr: true
        });

        let questionCount = 0;

        function addQuestion() {
            const container = document.getElementById('questionsContainer');
            const questionCard = document.createElement('div');
            questionCard.className = 'question-card';
            questionCard.innerHTML = `
                <div class="question-header">
                    <h5>Question ${questionCount + 1}</h5>
                    <button type="button" class="btn btn-link text-danger remove-question" 
                            onclick="removeQuestion(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Question Text*</label>
                        <textarea name="questions[${questionCount}][text]" 
                                  class="form-control" required rows="3"
                                  placeholder="Enter your question"></textarea>
                        <div class="invalid-feedback">Please enter the question text.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Points*</label>
                        <input type="number" name="questions[${questionCount}][points]" 
                               class="form-control" required min="0.5" step="0.5" value="1"
                               onchange="validateTotalPoints()">
                        <div class="invalid-feedback">Points must be greater than 0.</div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Question Image</label>
                    <input type="file" name="questions[${questionCount}][image]" 
                           class="form-control" accept="image/*"
                           onchange="previewImage(this, 'preview_${questionCount}')">
                    <img id="preview_${questionCount}" class="preview-image mt-2" 
                         style="display:none;" alt="Question image preview">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Question Type*</label>
                    <select name="questions[${questionCount}][type]" 
                            class="form-control" required
                            onchange="handleQuestionType(this, ${questionCount})">
                        <option value="mcq">Multiple Choice</option>
                        <option value="true_false">True/False</option>
                        <option value="open">Open Answer</option>
                    </select>
                </div>
                
                <div id="options_${questionCount}" class="options-container"></div>
            `;
            
            container.appendChild(questionCard);
            handleQuestionType(questionCard.querySelector('select'), questionCount);
            questionCount++;
            validateTotalPoints();
        }

        function handleQuestionType(select, questionIndex) {
            const container = document.getElementById(`options_${questionIndex}`);
            const type = select.value;
            
            switch(type) {
                case 'mcq':
                    container.innerHTML = `
                        <div class="mb-3">
                            <label class="form-label">Options</label>
                            <select class="form-control" 
                                    onchange="updateMCQOptions(this, ${questionIndex})">
                                <option value="4" selected>4 Options</option>
                                <option value="2">2 Options</option>
                                <option value="3">3 Options</option>
                                <option value="5">5 Options</option>
                                <option value="6">6 Options</option>
                            </select>
                        </div>
                        <div id="mcq_options_${questionIndex}" class="mt-3"></div>
                    `;
                    updateMCQOptions(container.querySelector('select'), questionIndex);
                    break;
                    
                case 'true_false':
                    container.innerHTML = `
                        <div class="options-list">
                            <div class="form-check">
                                <input type="radio" name="questions[${questionIndex}][correct_option]" 
                                       value="true" class="form-check-input" required>
                                <label class="form-check-label">True</label>
                            </div>
                            <div class="form-check mt-2">
                                <input type="radio" name="questions[${questionIndex}][correct_option]" 
                                       value="false" class="form-check-input" required>
                                <label class="form-check-label">False</label>
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
                            <div class="invalid-feedback">Please provide the correct answer.</div>
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
                    <div class="option-row mb-2">
                        <div class="input-group">
                            <input type="text" name="questions[${questionIndex}][options][]" 
                                   class="form-control" placeholder="Option ${i + 1}" required>
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <input type="radio" name="questions[${questionIndex}][correct_option]" 
                                           value="${i}" required>
                                    <label class="ms-2 mb-0">Correct</label>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            html += '</div>';
            container.innerHTML = html;
        }

        function removeQuestion(button) {
            button.closest('.question-card').remove();
            validateTotalPoints();
            // Renumber remaining questions
            document.querySelectorAll('.question-card').forEach((card, index) => {
                card.querySelector('h5').textContent = `Question ${index + 1}`;
            });
        }

        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            const file = input.files[0];
            
            if (file) {
                // Validate file size (max 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('Image size should not exceed 2MB');
                    input.value = '';
                    preview.style.display = 'none';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        }

        function validateTotalPoints() {
            const totalExamPoints = parseFloat(document.querySelector('select[name="total_points"]').value) || 0;
            let sumQuestionPoints = 0;
            
            document.querySelectorAll('input[name$="[points]"]').forEach(input => {
                sumQuestionPoints += parseFloat(input.value) || 0;
            });
            
            const submitBtn = document.querySelector('button[type="submit"]');
            const pointsWarning = document.getElementById('pointsWarning');
            
            if (sumQuestionPoints > totalExamPoints) {
                if (!pointsWarning) {
                    const warning = document.createElement('div');
                    warning.id = 'pointsWarning';
                    warning.className = 'alert alert-warning mt-3';
                    warning.innerHTML = `Total question points (${sumQuestionPoints}) exceed exam total points (${totalExamPoints})`;
                    document.querySelector('.card-body').insertBefore(warning, document.querySelector('#questionsContainer'));
                }
                submitBtn.disabled = true;
            } else {
                if (pointsWarning) {
                    pointsWarning.remove();
                }
                submitBtn.disabled = false;
            }
        }

        // Form validation
        (() => {
            'use strict'
            const forms = document.querySelectorAll('.needs-validation');
            
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();

        // Timer toggle
        document.getElementById('hasTimer').addEventListener('change', function() {
            document.getElementById('durationField').style.display = 
                this.checked ? 'block' : 'none';
        });

        // Add first question automatically
        addQuestion();

        // Prevent accidental navigation
        window.onbeforeunload = function() {
            if (document.getElementById('questionsContainer').children.length > 0) {
                return "You have unsaved changes. Are you sure you want to leave?";
            }
        };

        // Remove navigation warning when submitting
        document.getElementById('examForm').addEventListener('submit', function() {
            window.onbeforeunload = null;
        });
    </script>
</body>
</html>