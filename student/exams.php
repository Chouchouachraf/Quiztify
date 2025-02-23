<?php
require_once '../includes/functions.php';
checkRole('student');

// Set timezone - change this to your local timezone
date_default_timezone_set('Africa/Casablanca'); // For Morocco

class ExamManager {
    private $conn;
    private $userId;

    public function __construct($connection, $userId) {
        $this->conn = $connection;
        $this->userId = $userId;
    }

    public function getAvailableExams() {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    e.*,
                    u.full_name as teacher_name,
                    COUNT(DISTINCT q.id) as question_count,
                    c.name as classroom_name,
                    c.department,
                    (
                        SELECT COUNT(*) 
                        FROM exam_attempts 
                        WHERE exam_id = e.id 
                        AND student_id = ? 
                        AND is_completed = 0
                    ) as ongoing_attempts,
                    (
                        SELECT COUNT(*) 
                        FROM exam_attempts 
                        WHERE exam_id = e.id 
                        AND student_id = ? 
                        AND is_completed = 1
                    ) as completed_attempts,
                    SUM(q.points) as total_points
                FROM exams e
                JOIN users u ON e.created_by = u.id
                JOIN exam_classrooms ec ON e.id = ec.exam_id
                JOIN classrooms c ON ec.classroom_id = c.id
                JOIN classroom_students cs ON c.id = cs.classroom_id
                LEFT JOIN questions q ON e.id = q.exam_id
                WHERE cs.student_id = ?
                AND e.is_published = 1
                GROUP BY e.id
                ORDER BY e.start_date ASC
            ");

            $stmt->execute([$this->userId, $this->userId, $this->userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching available exams: " . $e->getMessage());
            return [];
        }
    }

    public function canStartExam($exam) {
        $now = new DateTime('now');
        $endTime = new DateTime($exam['end_date']);

        // Only check if exam has ended
        if ($now > $endTime) {
            return false;
        }

        // Check if student has ongoing attempts
        if ($exam['ongoing_attempts'] > 0) {
            return false;
        }

        return true;
    }

    public function startExam($examId) {
        try {
            $this->conn->beginTransaction();

            // Verify exam is published and student has access
            $stmt = $this->conn->prepare("
                SELECT e.* 
                FROM exams e
                JOIN exam_classrooms ec ON e.id = ec.exam_id
                JOIN classrooms c ON ec.classroom_id = c.id
                JOIN classroom_students cs ON c.id = cs.classroom_id
                WHERE e.id = ?
                AND cs.student_id = ?
                AND e.is_published = 1
            ");
            $stmt->execute([$examId, $this->userId]);
            $exam = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$exam) {
                throw new Exception('Exam not available or access denied.');
            }

            // Check for existing incomplete attempts
            $stmt = $this->conn->prepare("
                SELECT id FROM exam_attempts 
                WHERE exam_id = ? 
                AND student_id = ? 
                AND is_completed = 0
            ");
            $stmt->execute([$examId, $this->userId]);
            if ($stmt->rowCount() > 0) {
                throw new Exception('You have an ongoing attempt for this exam.');
            }

            // Insert new attempt
            $stmt = $this->conn->prepare("
                INSERT INTO exam_attempts (
                    exam_id, student_id, start_time, is_completed
                ) VALUES (?, ?, NOW(), 0)
            ");
            $stmt->execute([$examId, $this->userId]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error starting exam: " . $e->getMessage());
            throw $e;
        }
    }

    public function test() {
        return "ExamManager is working";
    }

    public function getCompletedExams() {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    e.*,
                    u.full_name as teacher_name,
                    c.name as classroom_name,
                    ea.score,
                    ea.end_time as completion_time,
                    ea.id as attempt_id
                FROM exams e
                JOIN users u ON e.created_by = u.id
                JOIN exam_classrooms ec ON e.id = ec.exam_id
                JOIN classrooms c ON ec.classroom_id = c.id
                JOIN exam_attempts ea ON e.id = ea.exam_id
                WHERE ea.student_id = ?
                AND ea.is_completed = 1
                ORDER BY ea.end_time DESC
            ");
            
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error in getCompletedExams: " . $e->getMessage());
            throw new Exception("Failed to fetch completed exams");
        }
    }
}

try {
    $conn = getDBConnection();
    $examManager = new ExamManager($conn, $_SESSION['user_id']);
    
    // Test if ExamManager is loaded correctly
    echo $examManager->test();
    
    // Get exams
    $availableExams = $examManager->getAvailableExams();
    $completedExams = $examManager->getCompletedExams();
    
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo "Error: " . $e->getMessage();
}

// Debug code remains unchanged...
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Exams - <?php echo SITE_NAME; ?></title>
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
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .content {
            margin-top: 20px;
        }

        .exam-card {
            background: #fff;
            border: 1px solid #e1e8ed;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.2s ease;
        }

        .exam-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* ... Copy all styles from dashboard.php ... */
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/student-nav.php'; ?>
        
        <div class="content">
            <div class="exam-list">
                <h2>Available Exams</h2>
                <?php if (empty($availableExams)): ?>
                    <div class="empty-state">
                        <i class='bx bx-book-open'></i>
                        <p>No exams are currently available.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($availableExams as $exam): ?>
                        <?php
                        $now = new DateTime('now', new DateTimeZone('Africa/Casablanca'));
                        $startTime = new DateTime($exam['start_date'], new DateTimeZone('Africa/Casablanca'));
                        $endTime = new DateTime($exam['end_date'], new DateTimeZone('Africa/Casablanca'));
                        ?>
                        <div class="exam-card">
                            <div class="exam-header">
                                <div>
                                    <h3 class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></h3>
                                    <div class="exam-meta">
                                        <span class="teacher-name">By: <?php echo htmlspecialchars($exam['teacher_name']); ?></span>
                                    </div>
                                </div>
                                <?php
                                if ($now < $startTime) {
                                    echo '<span class="exam-status status-upcoming">Upcoming</span>';
                                } elseif ($now <= $endTime) {
                                    echo '<span class="exam-status status-active">Active</span>';
                                } else {
                                    echo '<span class="exam-status status-expired">Expired</span>';
                                }
                                ?>
                            </div>
                            
                            <div class="exam-stats">
                                <div class="exam-stat-item">
                                    <i class='bx bx-question-mark'></i>
                                    <span><?php echo $exam['question_count']; ?> questions</span>
                                </div>
                                <div class="exam-stat-item">
                                    <i class='bx bx-time'></i>
                                    <span><?php echo $exam['duration_minutes']; ?> minutes</span>
                                </div>
                                <div class="exam-stat-item">
                                    <i class='bx bx-revision'></i>
                                    <span>Attempts: <?php echo $exam['completed_attempts']; ?>/<?php echo $exam['attempts_allowed']; ?></span>
                                </div>
                            </div>
                            
                            <div class="exam-dates">
                                <div class="exam-stat-item">
                                    <i class='bx bx-calendar'></i>
                                    <span>Start: <?php echo formatDateTime($exam['start_date']); ?></span>
                                </div>
                                <div class="exam-stat-item">
                                    <i class='bx bx-calendar-check'></i>
                                    <span>End: <?php echo formatDateTime($exam['end_date']); ?></span>
                                </div>
                            </div>
                            
                            <div class="exam-actions">
                                <?php if ($examManager->canStartExam($exam)): ?>
                                    <a href="take-exam.php?id=<?php echo $exam['id']; ?>" class="btn-start">
                                        <i class='bx bx-play'></i> Start Exam
                                    </a>
                                <?php else: ?>
                                    <?php if ($now < $startTime): ?>
                                        <div class="countdown-timer" data-start-time="<?php echo $startTime->getTimestamp() * 1000; ?>">
                                            <button class="btn-start" disabled>
                                                <i class='bx bx-time'></i> Starts in: <span class="countdown"></span>
                                            </button>
                                        </div>
                                    <?php elseif ($exam['ongoing_attempts'] > 0): ?>
                                        <a href="resume-exam.php?id=<?php echo $exam['id']; ?>" class="btn-start">
                                            <i class='bx bx-play-circle'></i> Resume Exam
                                        </a>
                                    <?php elseif ($exam['completed_attempts'] >= $exam['attempts_allowed']): ?>
                                        <button class="btn-start" disabled>
                                            <i class='bx bx-block'></i> No Attempts Remaining
                                        </button>
                                    <?php elseif ($now > $endTime): ?>
                                        <button class="btn-start" disabled>
                                            <i class='bx bx-x-circle'></i> Exam Expired
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($completedExams)): ?>
                <div class="completed-exams">
                    <h2>Completed Exams</h2>
                    <?php foreach ($completedExams as $exam): ?>
                        <div class="exam-card">
                            <div class="exam-header">
                                <div>
                                    <h3 class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></h3>
                                    <div class="exam-meta">
                                        <span class="teacher-name">By: <?php echo htmlspecialchars($exam['teacher_name']); ?></span>
                                    </div>
                                </div>
                                <span class="badge <?php echo ($exam['score'] >= 60) ? 'badge-success' : 'badge-danger'; ?>">
                                    Score: <?php echo $exam['score']; ?>%
                                </span>
                            </div>
                            
                            <div class="exam-stats">
                                <div class="exam-stat-item">
                                    <i class='bx bx-question-mark'></i>
                                    <span><?php echo $exam['question_count']; ?> questions</span>
                                </div>
                                <div class="exam-stat-item">
                                    <i class='bx bx-calendar-check'></i>
                                    <span>Completed: <?php echo formatDateTime($exam['completion_time']); ?></span>
                                </div>
                            </div>
                            
                            <div class="exam-actions">
                                <a href="view-result.php?exam_id=<?php echo $exam['id']; ?>" class="btn-start">
                                    <i class='bx bx-show'></i> View Results
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
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

    // Auto refresh the page periodically
    const refreshInterval = 60000; // 1 minute
    setInterval(() => {
        if (!document.hidden) {
            window.location.reload();
        }
    }, refreshInterval);
    </script>
</body>
</html>