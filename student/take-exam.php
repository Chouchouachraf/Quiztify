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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
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

        .question-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
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

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .exam-header, .question-card {
                padding: 20px;
            }

            .exam-info {
                grid-template-columns: 1fr;
            }

            .option {
                padding: 12px;
            }

            .submit-container {
                padding: 15px;
            }
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
                            <textarea name="answers[<?php echo $question['id']; ?>]" 
                                      class="form-control" rows="3" required
                                      placeholder="Enter your answer"
                                      onchange="updateProgress()"></textarea>
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
        // Get the exam start time and duration from PHP
        const startTime = new Date('<?php echo date('Y-m-d H:i:s'); ?>').getTime();
        const durationMinutes = <?php echo $exam['duration_minutes']; ?>;
        const endTime = startTime + (durationMinutes * 60 * 1000);

        function handleTimeUp() {
            const timerElement = document.getElementById('timer');
            timerElement.innerHTML = `
                <i class='bx bx-time-five'></i>
                <span>Time's up! Submitting exam...</span>
            `;
            timerElement.style.background = 'var(--danger-color)';
            
            // Disable all form inputs and the submit button
            const inputs = document.querySelectorAll('input, textarea, button');
            inputs.forEach(input => input.disabled = true);

            // Save any unsaved answers
            saveAnswers();

            // Show loading overlay
            showSubmissionOverlay();

            // Get the form and create hidden input for auto-submit
            const form = document.getElementById('examForm');
            const autoSubmitInput = document.createElement('input');
            autoSubmitInput.type = 'hidden';
            autoSubmitInput.name = 'auto_submit';
            autoSubmitInput.value = '1';
            form.appendChild(autoSubmitInput);

            // Remove confirmation prompts
            window.onbeforeunload = null;
            form.onsubmit = null;

            // Submit the form
            form.submit();
        }

        function showSubmissionOverlay() {
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
            `;
            overlay.innerHTML = `
                <div style="
                    background: white;
                    padding: 20px;
                    border-radius: 10px;
                    text-align: center;
                ">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2">Submitting your exam...</p>
                </div>
            `;
            document.body.appendChild(overlay);
        }

        function updateTimer() {
            const now = new Date().getTime();
            const timeLeft = endTime - now;

            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                handleTimeUp();
                return;
            }

            const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);

            const timerElement = document.getElementById('timer');
            timerElement.innerHTML = `
                <i class='bx bx-time-five'></i>
                <span>Time remaining: ${minutes}:${seconds.toString().padStart(2, '0')}</span>
            `;

            // Warning states
            if (timeLeft <= 5 * 60 * 1000) {
                timerElement.classList.add('warning');
                if (minutes === 5 && seconds === 0 || 
                    minutes === 1 && seconds === 0 || 
                    minutes === 0 && seconds === 30) {
                    playAlertSound();
                }
            }
        }

        // Initialize timer
        updateTimer();
        const timerInterval = setInterval(updateTimer, 1000);

        // Progress bar functionality
        function updateProgress() {
            const totalQuestions = <?php echo count($questions); ?>;
            const answeredQuestions = document.querySelectorAll('input:checked, textarea:not(:placeholder-shown)').length;
            const progress = (answeredQuestions / totalQuestions) * 100;
            
            document.getElementById('progressBar').style.width = `${progress}%`;
            
            // Enable submit button if all questions are answered
            document.getElementById('submitBtn').disabled = answeredQuestions < totalQuestions;
        }

        // Autosave functionality
        const form = document.getElementById('examForm');
        const inputs = form.querySelectorAll('input[type="radio"], textarea');

        inputs.forEach(input => {
            input.addEventListener('change', () => {
                const answers = {};
                inputs.forEach(inp => {
                    if ((inp.type === 'radio' && inp.checked) || 
                        (inp.type === 'textarea' && inp.value)) {
                        answers[inp.name] = inp.value;
                    }
                });
                localStorage.setItem('examAnswers', JSON.stringify(answers));
                updateProgress();
            });
        });

        // Load saved answers
        window.addEventListener('load', () => {
            const savedAnswers = localStorage.getItem('examAnswers');
            if (savedAnswers) {
                const answers = JSON.parse(savedAnswers);
                Object.entries(answers).forEach(([name, value]) => {
                    const input = form.querySelector(`input[name="${name}"][value="${value}"], textarea[name="${name}"]`);
                    if (input) {
                        if (input.type === 'radio') {
                            input.checked = true;
                        } else {
                            input.value = value;
                        }
                    }
                });
                updateProgress();
            }
        });

        // Confirm before submitting
        function confirmSubmit() {
            return confirm('Are you sure you want to submit your exam? This action cannot be undone.');
        }

        // Clear localStorage on submit
        form.addEventListener('submit', () => {
            if (confirmSubmit()) {
                localStorage.removeItem('examAnswers');
                return true;
            }
            return false;
        });

        // Prevent accidental navigation
        window.onbeforeunload = function() {
            return "Are you sure you want to leave? Your progress will not be saved.";
        };

        // Remove navigation warning when submitting
        form.addEventListener('submit', function() {
            window.onbeforeunload = null;
        });

        // Initialize progress on page load
        updateProgress();

        // Anti-cheating measures with better UX
        let cheatingAttempts = 0;
        const MAX_CHEATING_ATTEMPTS = 3;
        let isFullscreen = false;
        let lastWarningTime = 0;
        const WARNING_COOLDOWN = 30000; // Increased to 30 seconds between warnings
        let hasShownFinalWarning = false; // Track if final warning has been shown

        // Enhanced logging of attempts
        async function logCheatingAttempt(type, message) {
            cheatingAttempts++;
            
            try {
                await fetch('log-cheating-attempt.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        attempt_id: <?php echo $_SESSION['current_attempt']; ?>,
                        exam_id: <?php echo $examId; ?>,
                        type: type
                    })
                });

                const now = Date.now();
                if (now - lastWarningTime > WARNING_COOLDOWN) {
                    if (cheatingAttempts >= MAX_CHEATING_ATTEMPTS && !hasShownFinalWarning) {
                        showFinalWarning();
                        hasShownFinalWarning = true;
                    } else if (cheatingAttempts < MAX_CHEATING_ATTEMPTS) {
                        showFriendlyWarning(message || 'Please focus on your exam.');
                    }
                    lastWarningTime = now;
                }
            } catch (error) {
                console.error('Failed to log attempt:', error);
            }
        }

        // Improved final warning that shows only once
        function showFinalWarning() {
            // Remove any existing warnings first
            const existingWarnings = document.querySelectorAll('.warning-overlay, .final-warning-overlay');
            existingWarnings.forEach(warning => warning.remove());

            const finalWarning = document.createElement('div');
            finalWarning.className = 'final-warning-overlay';
            finalWarning.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #fff3cd;
                padding: 20px;
                border-radius: 10px;
                border-left: 5px solid var(--danger-color);
                box-shadow: 0 0 20px rgba(0,0,0,0.2);
                z-index: 10000;
                max-width: 350px;
                animation: slideIn 0.3s ease-out;
            `;
            finalWarning.innerHTML = `
                <div style="text-align: left;">
                    <h3 style="color: var(--danger-color); margin: 0 0 10px 0; font-size: 1.1rem;">
                        ⚠️ Important Warning
                    </h3>
                    <p style="margin: 0 0 10px 0; font-size: 0.9rem;">
                        Multiple violations detected. Please stay focused on your exam to avoid automatic submission.
                    </p>
                    <button onclick="acknowledgeWarning(this)" style="
                        background: var(--primary-color);
                        color: white;
                        border: none;
                        padding: 8px 16px;
                        border-radius: 5px;
                        cursor: pointer;
                        font-size: 0.9rem;
                        width: 100%;
                    ">I Understand</button>
                </div>
            `;
            document.body.appendChild(finalWarning);

            // Auto-dismiss after 15 seconds
            setTimeout(() => {
                if (document.body.contains(finalWarning)) {
                    finalWarning.style.animation = 'slideOut 0.3s ease-out';
                    setTimeout(() => finalWarning.remove(), 300);
                }
            }, 15000);
        }

        // Function to handle warning acknowledgment
        function acknowledgeWarning(button) {
            const warningOverlay = button.closest('.final-warning-overlay');
            warningOverlay.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => warningOverlay.remove(), 300);
            
            // Reduce attempts count when acknowledged
            cheatingAttempts = Math.max(1, cheatingAttempts - 1);
            hasShownFinalWarning = false; // Allow final warning to show again if needed
            
            showFriendlyWarning('Thank you. You may continue your exam.');
        }

        // Improved friendly warning
        function showFriendlyWarning(message) {
            const now = Date.now();
            if (now - lastWarningTime > WARNING_COOLDOWN) {
                // Remove any existing warning
                const existingWarning = document.querySelector('.warning-overlay');
                if (existingWarning) {
                    existingWarning.remove();
                }

                const warningOverlay = document.createElement('div');
                warningOverlay.className = 'warning-overlay';
                warningOverlay.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: white;
                    padding: 12px 20px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    z-index: 9999;
                    animation: slideIn 0.3s ease-out;
                    max-width: 300px;
                `;
                warningOverlay.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class='bx bx-info-circle' style="color: var(--primary-color); font-size: 1.2rem;"></i>
                        <p style="margin: 0; color: var(--primary-color); font-size: 0.9rem;">${message}</p>
                    </div>
                `;
                document.body.appendChild(warningOverlay);

                // Auto-dismiss after 3 seconds
                setTimeout(() => {
                    if (document.body.contains(warningOverlay)) {
                        warningOverlay.style.animation = 'slideOut 0.3s ease-out';
                        setTimeout(() => warningOverlay.remove(), 300);
                    }
                }, 3000);

                lastWarningTime = now;
            }
        }

        // Function to request fullscreen
        function requestFullscreen() {
            const elem = document.documentElement;
            if (elem.requestFullscreen) {
                elem.requestFullscreen();
            } else if (elem.webkitRequestFullscreen) {
                elem.webkitRequestFullscreen();
            } else if (elem.msRequestFullscreen) {
                elem.msRequestFullscreen();
            }
        }

        // Function to check if browser supports fullscreen
        function isFullscreenAvailable() {
            return document.documentElement.requestFullscreen || 
                   document.documentElement.webkitRequestFullscreen || 
                   document.documentElement.msRequestFullscreen;
        }

        // Start exam in fullscreen if supported
        window.addEventListener('load', function() {
            if (isFullscreenAvailable()) {
                requestFullscreen();
            }
        });

        // Periodically check if still in fullscreen
        setInterval(() => {
            if (!document.hidden && !document.fullscreenElement && isFullscreenAvailable()) {
                logCheatingAttempt('Exited fullscreen mode');
            }
        }, 1000);

        // Add CSS animations if not already present
        if (!document.getElementById('warning-animations')) {
            const style = document.createElement('style');
            style.id = 'warning-animations';
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOut {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }
    </script>
</body>
</html>