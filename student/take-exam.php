<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkRole('student');

// Set timezone
date_default_timezone_set('Africa/Casablanca');

try {
    $conn = getDBConnection();
    $examManager = new ExamManager($conn, $_SESSION['user_id']);

    if (!isset($_GET['id'])) {
        setFlashMessage('error', 'No exam specified');
        header('Location: exams.php');
        exit();
    }

    $examId = $_GET['id'];

    // Get exam details
    $stmt = $conn->prepare("
        SELECT e.*, u.full_name as teacher_name
        FROM exams e
        JOIN users u ON e.created_by = u.id
        WHERE e.id = ?
    ");
    $stmt->execute([$examId]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        setFlashMessage('error', 'Exam not found');
        header('Location: exams.php');
        exit();
    }

    // Check if student can take the exam
    if (!$examManager->canStartExam($exam)) {
        setFlashMessage('error', 'You cannot start this exam at this time');
        header('Location: exams.php');
        exit();
    }

    // Start the exam
    if ($examManager->startExam($examId)) {
        // Redirect to the exam interface
        header('Location: exam-interface.php?exam_id=' . $examId);
        exit();
    } else {
        setFlashMessage('error', 'Failed to start exam');
        header('Location: exams.php');
        exit();
    }

} catch (Exception $e) {
    error_log("Error in take-exam.php: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred');
    header('Location: exams.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($exam['title']); ?> - <?php echo SITE_NAME; ?></title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f1c40f;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: #f5f6fa;
            color: var(--dark-color);
            padding-bottom: 60px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .exam-header {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .timer {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: var(--primary-color);
            color: #fff;
            padding: 10px;
            text-align: center;
            z-index: 1000;
        }

        .question-card {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .question-text {
            font-size: 1.1em;
            margin-bottom: 15px;
            color: var(--dark-color);
        }

        .options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .option {
            display: flex;
            align-items: center;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .option:hover {
            background-color: var(--light-color);
        }

        .option input[type="radio"] {
            margin-right: 10px;
        }

        .submit-container {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #fff;
            padding: 15px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .submit-btn {
            background: var(--success-color);
            color: #fff;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1em;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            background: #27ae60;
        }

        .submit-btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }

        .exam-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-item i {
            color: var(--secondary-color);
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .exam-header {
                padding: 15px;
            }

            .question-card {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="timer" id="timer">
        <span>Time remaining: Loading...</span>
    </div>

    <div class="container">
        <div class="exam-header">
            <h1><?php echo htmlspecialchars($exam['title']); ?></h1>
            <p><?php echo htmlspecialchars($exam['description']); ?></p>
            <div class="exam-info">
                <div class="info-item">
                    <i class='bx bx-user'></i>
                    <span>Teacher: <?php echo htmlspecialchars($exam['teacher_name']); ?></span>
                </div>
                <div class="info-item">
                    <i class='bx bx-book'></i>
                    <span>Class: <?php echo htmlspecialchars($exam['classroom_name']); ?></span>
                </div>
                <div class="info-item">
                    <i class='bx bx-time'></i>
                    <span>Duration: <?php echo $exam['duration_minutes']; ?> minutes</span>
                </div>
            </div>
        </div>

        <form id="examForm" method="POST" onsubmit="return confirmSubmit()">
            <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
            <input type="hidden" name="start_time" value="<?php echo date('Y-m-d H:i:s'); ?>">
            
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-card">
                    <h3 class="question-text">
                        Question <?php echo $index + 1; ?>: 
                        <?php echo htmlspecialchars($question['question_text']); ?>
                    </h3>
                    
                    <div class="options">
                        <?php if ($question['question_type'] === 'mcq'): ?>
                            <?php 
                            $options = explode('|||', $question['options']);
                            foreach ($options as $option):
                                list($option_id, $option_text) = explode(':::', $option);
                            ?>
                                <div class="option">
                                    <input type="radio" 
                                           id="option_<?php echo $option_id; ?>"
                                           name="answers[<?php echo $question['id']; ?>]" 
                                           value="<?php echo $option_id; ?>"
                                           required>
                                    <label for="option_<?php echo $option_id; ?>">
                                        <?php echo htmlspecialchars($option_text); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif ($question['question_type'] === 'true_false'): ?>
                            <div class="option">
                                <input type="radio" 
                                       id="true_<?php echo $question['id']; ?>"
                                       name="answers[<?php echo $question['id']; ?>]" 
                                       value="1" 
                                       required>
                                <label for="true_<?php echo $question['id']; ?>">True</label>
                            </div>
                            <div class="option">
                                <input type="radio" 
                                       id="false_<?php echo $question['id']; ?>"
                                       name="answers[<?php echo $question['id']; ?>]" 
                                       value="0" 
                                       required>
                                <label for="false_<?php echo $question['id']; ?>">False</label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="submit-container">
                <button type="submit" class="submit-btn" id="submitBtn">
                    <i class='bx bx-check-circle'></i>
                    Submit Exam
                </button>
            </div>
        </form>
    </div>

    <script>
        // Timer functionality
        let timeLeft = <?php echo $exam['duration_minutes']; ?> * 60;
        const timerDisplay = document.getElementById('timer');
        const examForm = document.getElementById('examForm');
        
        const timer = setInterval(() => {
            timeLeft--;
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerDisplay.querySelector('span').textContent = 
                `Time remaining: ${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 300) { // Last 5 minutes
                timerDisplay.style.background = var(--danger-color);
            }
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                examForm.submit();
            }
        }, 1000);

        function confirmSubmit() {
            return confirm('Are you sure you want to submit your exam? This action cannot be undone.');
        }

        // Prevent form resubmission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Save answers to localStorage
        const form = document.getElementById('examForm');
        const inputs = form.querySelectorAll('input[type="radio"]');

        inputs.forEach(input => {
            input.addEventListener('change', () => {
                const answers = {};
                inputs.forEach(inp => {
                    if (inp.checked) {
                        answers[inp.name] = inp.value;
                    }
                });
                localStorage.setItem('examAnswers', JSON.stringify(answers));
            });
        });

        // Load saved answers
        window.addEventListener('load', () => {
            const savedAnswers = localStorage.getItem('examAnswers');
            if (savedAnswers) {
                const answers = JSON.parse(savedAnswers);
                Object.entries(answers).forEach(([name, value]) => {
                    const input = form.querySelector(`input[name="${name}"][value="${value}"]`);
                    if (input) {
                        input.checked = true;
                    }
                });
            }
        });

        // Clear localStorage on submit
        form.addEventListener('submit', () => {
            localStorage.removeItem('examAnswers');
        });
    </script>
</body>
</html>