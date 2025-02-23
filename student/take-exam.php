<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once 'ExamManager.php'; // Create this file to store the ExamManager class

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

    // Get exam details with questions
    $stmt = $conn->prepare("
        SELECT 
            e.*,
            u.full_name as teacher_name,
            c.name as classroom_name,
            c.department
        FROM exams e
        JOIN users u ON e.created_by = u.id
        JOIN exam_classrooms ec ON e.id = ec.exam_id
        JOIN classrooms c ON ec.classroom_id = c.id
        JOIN classroom_students cs ON c.id = cs.classroom_id
        WHERE e.id = ? 
        AND cs.student_id = ?
        AND e.is_published = 1
    ");
    $stmt->execute([$examId, $_SESSION['user_id']]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        throw new Exception('Exam not found or access denied.');
    }

    // Get questions
    $stmt = $conn->prepare("
        SELECT 
            q.*,
            GROUP_CONCAT(
                CONCAT(mo.id, ':', mo.option_text)
                ORDER BY mo.id ASC
                SEPARATOR '||'
            ) as options
        FROM questions q
        LEFT JOIN mcq_options mo ON q.id = mo.question_id
        WHERE q.exam_id = ?
        GROUP BY q.id
        ORDER BY q.order_num ASC
    ");
    $stmt->execute([$examId]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Start new attempt if not already started
    if (!isset($_SESSION['current_attempt'])) {
        $examManager->startExam($examId);
        
        // Get the new attempt ID
        $stmt = $conn->prepare("
            SELECT id FROM exam_attempts 
            WHERE exam_id = ? AND student_id = ? 
            AND is_completed = 0 
            ORDER BY start_time DESC LIMIT 1
        ");
        $stmt->execute([$examId, $_SESSION['user_id']]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['current_attempt'] = $attempt['id'];
    }

} catch (Exception $e) {
    error_log("Error in take-exam.php: " . $e->getMessage());
    setFlashMessage('error', $e->getMessage());
    header('Location: dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Exam - <?php echo htmlspecialchars($exam['title']); ?></title>
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

        <form id="examForm" method="POST" action="submit-exam.php" onsubmit="return confirmSubmit()">
            <input type="hidden" name="attempt_id" value="<?php echo $_SESSION['current_attempt']; ?>">
            <input type="hidden" name="start_time" value="<?php echo date('Y-m-d H:i:s'); ?>">
            
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-card">
                    <h3 class="question-text">
                        Question <?php echo $index + 1; ?>: 
                        <?php echo htmlspecialchars($question['question_text']); ?>
                    </h3>
                    
                    <?php if ($question['question_image']): ?>
                        <img src="../uploads/questions/<?php echo htmlspecialchars($question['question_image']); ?>" 
                             alt="Question image" class="question-image">
                    <?php endif; ?>

                    <div class="options">
                        <?php if ($question['question_type'] === 'mcq'): ?>
                            <?php 
                            $options = explode('||', $question['options']);
                            foreach ($options as $option):
                                list($optionId, $optionText) = explode(':', $option);
                            ?>
                                <div class="option">
                                    <input type="radio" 
                                           id="option_<?php echo $optionId; ?>"
                                           name="answers[<?php echo $question['id']; ?>]" 
                                           value="<?php echo $optionId; ?>"
                                           required>
                                    <label for="option_<?php echo $optionId; ?>">
                                        <?php echo htmlspecialchars($optionText); ?>
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
                        <?php else: ?>
                            <textarea name="answers[<?php echo $question['id']; ?>]" 
                                      class="form-control" rows="3" required
                                      placeholder="Enter your answer"></textarea>
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

        // Add confirmation before leaving page
        window.onbeforeunload = function() {
            return "Are you sure you want to leave? Your progress will not be saved.";
        };

        // Remove warning when submitting form
        document.getElementById('examForm').onsubmit = function() {
            window.onbeforeunload = null;
        };
    </script>
</body>
</html>