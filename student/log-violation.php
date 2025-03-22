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
        header('Location: tetris_game.php');
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.0/codemirror.min.css">
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
            padding-top: 60px;
            padding-bottom: 80px;
        }

        .timer {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 15px;
            text-align: center;
            z-index: 1000;
            font-size: 1.2rem;
            font-weight: 500;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .timer.warning {
            background: linear-gradient(135deg, var(--warning-color), var(--danger-color));
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.8; }
            100% { opacity: 1; }
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .exam-header {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            transition: transform 0.3s ease;
        }

        .exam-header:hover {
            transform: translateY(-5px);
        }

        .exam-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--light-color);
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: var(--light-color);
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .info-item:hover {
            background: #dfe6e9;
            transform: translateX(5px);
        }

        .info-item i {
            font-size: 1.5rem;
            color: var(--secondary-color);
        }

        .question-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .question-number {
            position: absolute;
            top: 0;
            right: 0;
            background: var(--secondary-color);
            color: white;
            padding: 8px 15px;
            border-radius: 0 15px 0 15px;
            font-weight: 500;
        }

        .question-text {
            font-size: 1.1rem;
            margin-bottom: 20px;
            color: var(--primary-color);
            padding-right: 60px;
        }

        .question-image {
            max-width: 100%;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .options {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .option {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .option:hover {
            border-color: var(--secondary-color);
            background: var(--light-color);
            transform: translateX(5px);
        }

        .option input[type="radio"] {
            margin-right: 15px;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .option label {
            flex: 1;
            cursor: pointer;
            font-size: 1.05rem;
        }

        .submit-container {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 20px;
            box-shadow: 0 -4px 10px rgba(0,0,0,0.1);
            text-align: center;
            z-index: 1000;
        }

        .submit-btn {
            background: var(--success-color);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .submit-btn:hover {
            background: #27ae60;
            transform: translateY(-2px);
        }

        .submit-btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
        }

        .progress-bar {
            height: 5px;
            background: var(--light-color);
            border-radius: 5px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .progress {
            height: 100%;
            background: var(--success-color);
            transition: width 0.3s ease;
        }

        .response-format-selector {
            margin-bottom: 20px;
        }

        .code-editor-container {
            display: none;
            margin-bottom: 20px;
        }

        .CodeMirror {
            border: 1px solid #ddd;
            border-radius: 4px;
            height: auto;
        }
    </style>
</head>
<body>
    <div class="timer" id="timer">
        <i class='bx bx-time-five'></i>
        <span>Time remaining: Loading...</span>
    </div>

    <div class="container">
        <div class="exam-header">
            <h1><?php echo htmlspecialchars($exam['title']); ?></h1>
            <p class="lead"><?php echo htmlspecialchars($exam['description']); ?></p>
            
            <div class="exam-info">
                <div class="info-item">
                    <i class='bx bx-user'></i>
                    <div>
                        <small>Teacher</small>
                        <div><?php echo htmlspecialchars($exam['teacher_name']); ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <i class='bx bx-book'></i>
                    <div>
                        <small>Class</small>
                        <div><?php echo htmlspecialchars($exam['classroom_name']); ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <i class='bx bx-time'></i>
                    <div>
                        <small>Duration</small>
                        <div><?php echo $exam['duration_minutes']; ?> minutes</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="progress-bar">
            <div class="progress" id="progressBar" style="width: 0%"></div>
        </div>

        <form id="examForm" method="POST" action="submit-exam.php" onsubmit="return confirmSubmit()">
            <input type="hidden" name="attempt_id" value="<?php echo $_SESSION['current_attempt']; ?>">
            
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-card" id="question_<?php echo $index + 1; ?>">
                    <div class="question-number">Question <?php echo $index + 1; ?></div>
                    
                    <h3 class="question-text">
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
                                           required
                                           onchange="updateProgress()">
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
                                       required
                                       onchange="updateProgress()">
                                <label for="true_<?php echo $question['id']; ?>">True</label>
                            </div>
                            <div class="option">
                                <input type="radio" 
                                       id="false_<?php echo $question['id']; ?>"
                                       name="answers[<?php echo $question['id']; ?>]" 
                                       value="0" 
                                       required
                                       onchange="updateProgress()">
                                <label for="false_<?php echo $question['id']; ?>">False</label>
                            </div>
                        <?php else: ?>
                            <div class="response-format-selector">
                                <label for="response_format_<?php echo $question['id']; ?>">Response Format:</label>
                                <select id="response_format_<?php echo $question['id']; ?>" class="form-select response-format" data-question-id="<?php echo $question['id']; ?>">
                                    <option value="paragraph">Paragraph</option>
                                    <option value="code">Code</option>
                                </select>
                            </div>

                            <div class="code-editor-container" id="code_editor_<?php echo $question['id']; ?>">
                                <textarea id="code_input_<?php echo $question['id']; ?>" name="code_answers[<?php echo $question['id']; ?>]"></textarea>
                            </div>

                            <div class="paragraph-container" id="paragraph_<?php echo $question['id']; ?>">
                                <textarea name="paragraph_answers[<?php echo $question['id']; ?>]" 
                                          class="form-control" rows="3" 
                                          placeholder="Enter your answer"></textarea>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.0/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.0/mode/clike/clike.min.js"></script>
    <script>
        // Initialize CodeMirror instances
        const codeEditors = {};

        document.querySelectorAll('.response-format').forEach(select => {
            const questionId = select.getAttribute('data-question-id');
            const codeEditorContainer = document.getElementById(`code_editor_${questionId}`);
            const paragraphContainer = document.getElementById(`paragraph_${questionId}`);
            const codeTextarea = document.getElementById(`code_input_${questionId}`);

            codeEditors[questionId] = CodeMirror.fromTextArea(codeTextarea, {
                lineNumbers: true,
                mode: 'text/x-csrc',
                indentUnit: 4,
                theme: 'default',
                matchBrackets: true,
                autoCloseBrackets: true,
            });

            select.addEventListener('change', function() {
                if (this.value === 'code') {
                    codeEditorContainer.style.display = 'block';
                    paragraphContainer.style.display = 'none';
                    codeEditors[questionId].refresh();
                } else {
                    codeEditorContainer.style.display = 'none';
                    paragraphContainer.style.display = 'block';
                }
            });

            // Initialize based on default selection
            if (select.value === 'code') {
                codeEditorContainer.style.display = 'block';
                paragraphContainer.style.display = 'none';
                codeEditors[questionId].refresh();
            } else {
                codeEditorContainer.style.display = 'none';
                paragraphContainer.style.display = 'block';
            }
        });

        // Function to log violations
        function logViolation(type) {
            fetch('log-violation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    attempt_id: '<?php echo $_SESSION['current_attempt']; ?>',
                    violation_type: type
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    console.log('Violation logged:', type);
                    alert('Cheating detected! Your actions have been logged.');
                } else {
                    console.error('Failed to log violation:', data.message);
                }
            })
            .catch(error => console.error('Error logging violation:', error));
        }

        // Event listeners for detecting violations
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') {
                logViolation('tab_switch');
            }
        });

        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            logViolation('right_click');
            return false;
        });

        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey && (e.key === 'c' || e.key === 'v')) || e.key === 'PrintScreen') {
                e.preventDefault();
                logViolation('copy_paste');
                return false;
            }
        });

        window.addEventListener('beforeunload', function(e) {
            if (isExamActive) {
                e.preventDefault();
                e.returnValue = '';
                logViolation('navigation_attempt');
                return '';
            }
        });

        window.addEventListener('blur', function() {
            if (isExamActive && !isWarningActive) {
                logViolation('tab_switch');
            }
        });

        // Timer logic
        let timeRemaining = <?php echo $exam['duration_minutes'] * 60; ?>; // Convert minutes to seconds
        const timerElement = document.getElementById('timer').querySelector('span');

        function updateTimer() {
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            timerElement.textContent = `Time remaining: ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

            if (timeRemaining <= 0) {
                clearInterval(timerInterval);
                timerElement.textContent = 'Time is up!';
                // Automatically submit the exam form
                document.getElementById('examForm').submit();
            }

            if (timeRemaining <= 300) { // 5 minutes warning
                document.getElementById('timer').classList.add('warning');
            }

            timeRemaining--;
        }

        const timerInterval = setInterval(updateTimer, 1000);
        updateTimer(); // Initial call to display the timer immediately
    </script>
</body>
</html>