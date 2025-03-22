<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once 'ExamManager.php';

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

    // Check if exam is still available
    $now = new DateTime();
    $startDate = new DateTime($exam['start_date']);
    $endDate = new DateTime($exam['end_date']);

    if ($now < $startDate) {
        throw new Exception('This exam has not started yet.');
    }

    if ($now > $endDate) {
        throw new Exception('This exam has ended.');
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

    // Check for existing incomplete attempt
    $stmt = $conn->prepare("
        SELECT id, start_time 
        FROM exam_attempts 
        WHERE exam_id = ? AND student_id = ? AND is_completed = 0
        ORDER BY start_time DESC 
        LIMIT 1
    ");
    $stmt->execute([$examId, $_SESSION['user_id']]);
    $existingAttempt = $stmt->fetch(PDO::FETCH_ASSOC);

    // Start new attempt if no incomplete attempt exists
    if (!$existingAttempt) {
        if (isset($_SESSION['current_attempt'])) {
            // Clear any stale attempt from session
            unset($_SESSION['current_attempt']);
        }
        
        $examManager->startExam($examId);
        
        // Get the new attempt ID
        $stmt = $conn->prepare("
            SELECT id, start_time FROM exam_attempts 
            WHERE exam_id = ? AND student_id = ? 
            AND is_completed = 0 
            ORDER BY start_time DESC LIMIT 1
        ");
        $stmt->execute([$examId, $_SESSION['user_id']]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$attempt) {
            throw new Exception('Failed to create exam attempt.');
        }
        
        $_SESSION['current_attempt'] = $attempt['id'];
        $_SESSION['exam_start_time'] = $attempt['start_time'];
    } else {
        // Use existing incomplete attempt
        $_SESSION['current_attempt'] = $existingAttempt['id'];
        $_SESSION['exam_start_time'] = $existingAttempt['start_time'];
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
    <link href="https://unpkg.com/boxicons@2.1.4/css/box-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --light-color: #ecf0f1;
            --dark-color: #34495e;
            --background-color: #f5f7fa;
            --card-background: white;
            --text-color: #333;
        }

        body.dark-mode {
            --background-color: #1a1a1a;
            --card-background: #2d2d2d;
            --text-color: #f0f0f0;
            --primary-color: #61dafb;
            --secondary-color: #4fc3f7;
            --success-color: #4caf50;
            --danger-color: #ff5252;
            --warning-color: #ff9800;
            --light-color: #444;
            --dark-color: #fff;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: var(--background-color);
            color: var(--text-color);
            padding-top: 80px;
            padding-bottom: 80px;
            transition: background-color 0.3s ease;
        }

        .exam-container {
            max-width: 1000px;
            margin: 0 auto;
            background: var(--card-background);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 30px;
            transition: background-color 0.3s ease;
        }

        .exam-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .exam-header h1 {
            color: var(--secondary-color);
            margin-bottom: 10px;
        }

        .exam-header p {
            color: var(--dark-color);
            font-size: 1.1rem;
        }

        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .toggle-switch {
            appearance: none;
            width: 60px;
            height: 30px;
            border-radius: 30px;
            background: var(--light-color);
            position: relative;
            cursor: pointer;
            outline: none;
            transition: all 0.3s ease;
        }

        .toggle-switch:checked {
            background: var(--secondary-color);
        }

        .toggle-switch:checked::after {
            right: 2px;
            background: white;
        }

        .toggle-switch::after {
            content: '';
            position: absolute;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--card-background);
            top: 2px;
            left: 2px;
            transition: all 0.3s ease;
        }

        .integrity-message {
            text-align: center;
            margin-bottom: 30px;
            color: var(--primary-color);
        }

        .integrity-message p {
            margin: 10px 0;
        }

        .clock-timer {
            position: fixed;
            top: 20px;
            left: 20px;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: var(--card-background);
            border: 4px solid var(--secondary-color);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            overflow: hidden;
        }

        .time-display {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 10px;
            text-align: center;
            width: 100%;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            padding: 5px 10px;
            border-radius: 5px;
        }

        .clock-face {
            position: relative;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: var(--card-background);
            box-shadow: 
                inset 0 0 15px rgba(0, 0, 0, 0.1),
                0 0 10px rgba(0, 0, 0, 0.1);
        }

        .clock-markers {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
        }

        .clock-marker {
            position: absolute;
            width: 2px;
            height: 12px;
            background: var(--primary-color);
            top: 69px;
            transform-origin: bottom center;
            transition: all 0.3s ease;
        }

        .clock-marker:nth-child(5n) {
            height: 18px;
            width: 3px;
            background: var(--secondary-color);
        }

        .clock-hands {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
        }

        .clock-hand {
            position: absolute;
            bottom: 75px;
            left: 74px;
            transform-origin: bottom center;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .clock-hour-hand {
            width: 4px;
            height: 50px;
            background: var(--primary-color);
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
        }

        .clock-minute-hand {
            width: 3px;
            height: 70px;
            background: var(--secondary-color);
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
        }

        .clock-second-hand {
            width: 2px;
            height: 80px;
            background: var(--danger-color);
            box-shadow: 0 0 5px rgba(255, 0, 0, 0.3);
            animation: secondHand 60s linear infinite;
        }

        @keyframes secondHand {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .clock-center {
            position: absolute;
            width: 14px;
            height: 14px;
            background: var(--primary-color);
            border-radius: 50%;
            top: 73px;
            left: 73px;
            z-index: 1;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
        }

        .question-container {
            margin-bottom: 40px;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .question-number {
            background: var(--secondary-color);
            color: var(--card-background);
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
        }

        .question-points {
            background: var(--light-color);
            color: var(--primary-color);
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
        }

        .question-content {
            background: var(--card-background);
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .question-text {
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: var(--dark-color);
        }

        .question-image {
            max-width: 100%;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .options-container {
            margin-top: 20px;
        }

        .option {
            background: var(--light-color);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .option:hover {
            background: var(--secondary-color);
            color: var(--card-background);
        }

        .option input[type="radio"] {
            margin-right: 15px;
        }

        .option-label {
            flex: 1;
            transition: color 0.3s ease;
        }

        .option::after {
            content: '';
            position: absolute;
            width: 0;
            height: 0;
            background: var(--success-color);
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .option.selected::after {
            width: 20px;
            height: 20px;
            right: 15px;
        }

        .submit-container {
            text-align: center;
            margin-top: 40px;
        }

        .submit-btn {
            background: var(--success-color);
            color: var(--card-background);
            border: none;
            padding: 15px 40px;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .submit-btn:hover {
            background: #27ae60;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .submit-btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .progress-container {
            margin-top: 20px;
            margin-bottom: 30px;
        }

        .progress-bar {
            height: 10px;
            background: var(--light-color);
            border-radius: 5px;
            overflow: hidden;
        }

        .progress {
            height: 100%;
            background: var(--secondary-color);
            width: 0%;
            transition: width 0.3s ease;
        }

        .question-type-header {
            margin-bottom: 15px;
            font-weight: bold;
        }

        .cheat-warning {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--danger-color);
            color: var(--card-background);
            padding: 15px 30px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 999;
            display: none;
        }

        .show-warning {
            opacity: 1;
            display: block;
        }

        .exam-info {
            background: var(--light-color);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-label {
            font-weight: bold;
            color: var(--primary-color);
        }

        .info-value {
            color: var(--dark-color);
        }
    </style>
</head>
<body>
    <div class="theme-toggle">
        <label class="switch">
            <input type="checkbox" class="toggle-switch" onchange="toggleTheme()">
            <span class="slider round"></span>
        </label>
    </div>

    <div class="exam-container">
        <div class="exam-header">
            <h1><?php echo htmlspecialchars($exam['title']); ?></h1>
            <p><?php echo htmlspecialchars($exam['description']); ?></p>
        </div>

        <div class="exam-info">
            <div class="info-item">
                <span class="info-label">Teacher:</span>
                <span class="info-value"><?php echo htmlspecialchars($exam['teacher_name']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Class:</span>
                <span class="info-value"><?php echo htmlspecialchars($exam['classroom_name']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Duration:</span>
                <span class="info-value"><?php echo $exam['duration_minutes']; ?> minutes</span>
            </div>
            <div class="info-item">
                <span class="info-label">Points Available:</span>
                <span class="info-value"><?php echo array_sum(array_column($questions, 'points')); ?> total points</span>
            </div>
        </div>

        <div class="integrity-message">
            <p><strong>Academic Integrity Notice:</strong> This exam is designed to assess your knowledge. Please adhere to the honor code.</p>
            <p>Cheating in any form will result in disciplinary action. All activities are monitored and recorded.</p>
        </div>

        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress" id="progressBar"></div>
            </div>
        </div>

        <form id="examForm" method="POST" action="submit-exam.php">
            <input type="hidden" name="attempt_id" value="<?php echo $_SESSION['current_attempt']; ?>">
            
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-container">
                    <div class="question-header">
                        <div class="question-number">Question <?php echo $index + 1; ?> of <?php echo count($questions); ?></div>
                        <div class="question-points"><?php echo $question['points']; ?> points</div>
                    </div>
                    
                    <div class="question-content">
                        <h3 class="question-text">
                            <?php echo htmlspecialchars($question['question_text']); ?>
                        </h3>
                        
                        <?php if ($question['question_image']): ?>
                            <img src="../uploads/questions/<?php echo htmlspecialchars($question['question_image']); ?>" 
                                 alt="Question image" class="question-image">
                        <?php endif; ?>

                        <div class="options-container">
                            <?php if ($question['question_type'] === 'mcq'): ?>
                                <?php 
                                $options = explode('||', $question['options']);
                                foreach ($options as $option):
                                    list($optionId, $optionText) = explode(':', $option);
                                ?>
                                    <div class="option" onclick="selectOption(this)">
                                        <input type="radio" 
                                               id="option_<?php echo $optionId; ?>"
                                               name="answers[<?php echo $question['id']; ?>]" 
                                               value="<?php echo $optionId; ?>"
                                               required>
                                        <label class="option-label" for="option_<?php echo $optionId; ?>">
                                            <?php echo htmlspecialchars($optionText); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php elseif ($question['question_type'] === 'true_false'): ?>
                                <div class="option" onclick="selectOption(this)">
                                    <input type="radio" 
                                           id="true_<?php echo $question['id']; ?>"
                                           name="answers[<?php echo $question['id']; ?>]" 
                                           value="true" 
                                           required>
                                    <label class="option-label" for="true_<?php echo $question['id']; ?>">True</label>
                                </div>
                                <div class="option" onclick="selectOption(this)">
                                    <input type="radio" 
                                           id="false_<?php echo $question['id']; ?>"
                                           name="answers[<?php echo $question['id']; ?>]" 
                                           value="false" 
                                           required>
                                    <label class="option-label" for="false_<?php echo $question['id']; ?>">False</label>
                                </div>
                            <?php else: ?>
                                <div class="question-type-header">
                                    <?php echo ucfirst($question['question_type']); ?> Response:
                                </div>
                                <textarea name="paragraph_answers[<?php echo $question['id']; ?>]" 
                                          class="form-control" rows="4" 
                                          placeholder="Enter your answer"
                                          required
                                          oninput="updateProgress()"></textarea>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="submit-container">
                <button type="submit" class="submit-btn" id="submitBtn" onclick="return confirmSubmit()">
                    Submit Exam
                </button>
            </div>
        </form>
    </div>

    <div class="cheat-warning" id="cheatWarning">
        Cheating detected! Your actions have been logged and will be reviewed.
    </div>

    <div class="clock-timer">
        <div class="time-display" id="timeDisplay">00:00</div>
        <div class="clock-face">
            <div class="clock-markers">
                <?php for ($i = 0; $i < 60; $i++): ?>
                    <div class="clock-marker" style="transform: rotate(<?php echo $i * 6; ?>deg)"></div>
                <?php endfor; ?>
            </div>
            <div class="clock-hands">
                <div class="clock-hand clock-hour-hand" id="hourHand"></div>
                <div class="clock-hand clock-minute-hand" id="minuteHand"></div>
                <div class="clock-hand clock-second-hand" id="secondHand"></div>
                <div class="clock-center"></div>
            </div>
        </div>
    </div>

    <script>
        // Function to log cheating attempts
        function logCheating(type) {
            fetch('log-cheating.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    attempt_id: '<?php echo $_SESSION['current_attempt']; ?>',
                    type: type
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    console.log('Cheating logged:', type);
                    document.getElementById('cheatWarning').classList.add('show-warning');
                    setTimeout(() => {
                        document.getElementById('cheatWarning').classList.remove('show-warning');
                    }, 5000);
                } else {
                    console.error('Failed to log cheating:', data.message);
                }
            })
            .catch(error => console.error('Error logging cheating:', error));
        }

        // Event listeners for detecting cheating
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') {
                logCheating('tab_switch');
            }
        });

        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            logCheating('right_click');
            return false;
        });

        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey && (e.key === 'c' || e.key === 'v')) || e.key === 'PrintScreen') {
                e.preventDefault();
                logCheating('copy_paste');
                return false;
            }
        });

        window.addEventListener('beforeunload', function(e) {
            e.preventDefault();
            e.returnValue = '';
            logCheating('navigation_attempt');
            return '';
        });

        window.addEventListener('blur', function() {
            logCheating('tab_switch');
        });

        // Timer logic
        let timeRemaining = <?php echo $exam['duration_minutes'] * 60; ?>; // Convert minutes to seconds
        const totalSeconds = timeRemaining;

        function updateTimer() {
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;

            // Update time display
            document.getElementById('timeDisplay').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

            // Update clock hands
            const now = new Date();
            const hour = now.getHours() % 12;
            const minute = now.getMinutes();
            const second = now.getSeconds();

            const hourDeg = (hour * 30) + (minute * 0.5);
            const minuteDeg = minute * 6;
            const secondDeg = second * 6;

            document.getElementById('hourHand').style.transform = `rotate(${hourDeg}deg)`;
            document.getElementById('minuteHand').style.transform = `rotate(${minuteDeg}deg)`;
            document.getElementById('secondHand').style.transform = `rotate(${secondDeg}deg)`;

            // Update progress bar
            const progress = ((totalSeconds - timeRemaining) / totalSeconds) * 100;
            document.getElementById('progressBar').style.width = `${progress}%`;

            // Add warning when time is running low
            if (timeRemaining <= 300) { // 5 minutes warning
                document.body.classList.add('time-warning');
            } else {
                document.body.classList.remove('time-warning');
            }

            if (timeRemaining <= 0) {
                clearInterval(timerInterval);
                document.getElementById('examForm').submit();
            }

            timeRemaining--;
        }

        const timerInterval = setInterval(updateTimer, 1000);
        updateTimer(); // Initial call to display the timer immediately

        // Function to confirm submission
        function confirmSubmit() {
            return confirm('Are you sure you want to submit your exam? This action cannot be undone.');
        }

        // Function to update progress
        function updateProgress() {
            const progress = ((totalSeconds - timeRemaining) / totalSeconds) * 100;
            document.getElementById('progressBar').style.width = `${progress}%`;
        }

        // Function to toggle dark/light mode
        function toggleTheme() {
            document.body.classList.toggle('dark-mode');
        }

        // Function to handle option selection
        function selectOption(option) {
            const radioInput = option.querySelector('input[type="radio"]');
            if (radioInput) {
                radioInput.checked = true;
                option.classList.add('selected');
                option.addEventListener('transitionend', () => {
                    option.classList.remove('selected');
                }, { once: true });
                updateProgress();
            }
        }
    </script>
</body>
</html>