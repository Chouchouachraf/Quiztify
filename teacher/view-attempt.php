<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkRole('teacher');

$attempt_id = $_GET['id'] ?? null;
$format = $_GET['format'] ?? 'html';

if (!$attempt_id) {
    setFlashMessage('error', 'Invalid attempt ID');
    header('Location: dashboard.php');
    exit;
}

try {
    $conn = getDBConnection();
    
    // Fetch attempt details with student and exam info
    $stmt = $conn->prepare("
        SELECT 
            ea.*,
            e.title as exam_title,
            e.passing_score,
            e.duration_minutes,
            u.full_name as student_name,
            u.email as student_email,
            c.name as classroom_name,
            c.department
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        JOIN users u ON ea.student_id = u.id
        JOIN exam_classrooms ec ON e.id = ec.exam_id
        JOIN classrooms c ON ec.classroom_id = c.id
        WHERE ea.id = ? AND e.created_by = ?
    ");
    $stmt->execute([$attempt_id, $_SESSION['user_id']]);
    $attempt = $stmt->fetch();

    if (!$attempt) {
        throw new Exception('Attempt not found or unauthorized access.');
    }

    // Fetch questions and student answers
    $stmt = $conn->prepare("
        SELECT 
            q.*,
            sa.answer_text,
            sa.selected_option_id,
            sa.is_correct,
            sa.points_earned,
            GROUP_CONCAT(
                CONCAT(mo.id, ':::', mo.option_text, ':::', mo.is_correct)
                SEPARATOR '|||'
            ) as options
        FROM questions q
        LEFT JOIN student_answers sa ON q.id = sa.question_id AND sa.attempt_id = ?
        LEFT JOIN mcq_options mo ON q.id = mo.question_id
        WHERE q.exam_id = ?
        GROUP BY q.id
        ORDER BY q.question_order, q.order_num
    ");
    $stmt->execute([$attempt_id, $attempt['exam_id']]);
    $questions = $stmt->fetchAll();

} catch (Exception $e) {
    setFlashMessage('error', $e->getMessage());
    header('Location: dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Attempt - <?php echo SITE_NAME; ?></title>
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
            --border-radius: 8px;
            --box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
            padding-bottom: 40px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .back-btn, .pdf-btn, .print-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            color: white;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .back-btn {
            background-color: var(--secondary-color);
        }

        .pdf-btn {
            background-color: var(--danger-color);
        }

        .print-btn {
            background-color: var(--success-color);
        }

        .back-btn:hover, .pdf-btn:hover, .print-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .attempt-header {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: var(--box-shadow);
        }

        .attempt-header h2 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-size: 1.8rem;
        }

        .attempt-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: var(--light-color);
            border-radius: var(--border-radius);
        }

        .meta-item i {
            font-size: 1.5rem;
            color: var(--secondary-color);
        }

        .score-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-left: 10px;
        }

        .passed {
            background-color: var(--success-color);
            color: white;
        }

        .failed {
            background-color: var(--danger-color);
            color: white;
        }

        .questions-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .question-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-color);
        }

        .question-header h3 {
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .question-points {
            background: var(--light-color);
            padding: 5px 10px;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .options-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 15px;
        }

        .option-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border-radius: var(--border-radius);
            background: var(--light-color);
            transition: all 0.3s ease;
        }

        .option-item.correct {
            background-color: rgba(46, 204, 113, 0.1);
            border: 1px solid var(--success-color);
        }

        .option-item.incorrect {
            background-color: rgba(231, 76, 60, 0.1);
            border: 1px solid var(--danger-color);
        }

        .option-item i {
            font-size: 1.2rem;
        }

        @media print {
            .action-buttons {
                display: none;
            }

            body {
                background: white;
            }

            .container {
                padding: 0;
            }

            .attempt-header, .question-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }

            .question-card {
                page-break-inside: avoid;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .attempt-meta {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .question-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="action-buttons">
            <a href="view-exam.php?id=<?php echo $attempt['exam_id']; ?>" class="back-btn">
                <i class='bx bx-arrow-back'></i> Back to Exam
            </a>
            <a href="?id=<?php echo $attempt_id; ?>&format=pdf" class="pdf-btn">
                <i class='bx bxs-file-pdf'></i> Download PDF
            </a>
            <button onclick="window.print()" class="print-btn">
                <i class='bx bx-printer'></i> Print Report
            </button>
        </div>

        <div class="attempt-header">
            <h2><?php echo htmlspecialchars($attempt['exam_title']); ?></h2>
            <div class="attempt-meta">
                <div class="meta-item">
                    <i class='bx bx-user'></i>
                    <span>Student: <?php echo htmlspecialchars($attempt['student_name']); ?></span>
                </div>
                <div class="meta-item">
                    <i class='bx bx-book'></i>
                    <span>Class: <?php echo htmlspecialchars($attempt['classroom_name']); ?></span>
                </div>
                <div class="meta-item">
                    <i class='bx bx-time'></i>
                    <span>Started: <?php echo date('M j, Y g:i A', strtotime($attempt['start_time'])); ?></span>
                </div>
                <div class="meta-item">
                    <i class='bx bx-check-circle'></i>
                    <span>Completed: <?php echo date('M j, Y g:i A', strtotime($attempt['end_time'])); ?></span>
                </div>
                <div class="meta-item">
                    <i class='bx bx-trophy'></i>
                    <span>Score: <?php echo number_format($attempt['score'], 1); ?>%</span>
                    <span class="score-badge <?php echo ($attempt['score'] >= $attempt['passing_score']) ? 'passed' : 'failed'; ?>">
                        <?php echo ($attempt['score'] >= $attempt['passing_score']) ? 'PASSED' : 'FAILED'; ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="questions-container">
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-card">
                    <div class="question-header">
                        <h3>Question <?php echo $index + 1; ?></h3>
                        <span class="question-points">
                            <?php echo $question['points_earned'] ?? 0; ?>/<?php echo $question['points']; ?> points
                        </span>
                    </div>
                    
                    <p><?php echo htmlspecialchars($question['question_text']); ?></p>

                    <div class="options-list">
                        <?php if ($question['question_type'] === 'mcq'): ?>
                            <?php 
                            $options = explode('|||', $question['options']);
                            foreach ($options as $option):
                                list($option_id, $option_text, $is_correct) = explode(':::', $option);
                                $is_selected = $question['selected_option_id'] == $option_id;
                                $class = 'option-item';
                                if ($is_selected) {
                                    $class .= $is_correct ? ' correct' : ' incorrect';
                                } elseif ($is_correct) {
                                    $class .= ' correct';
                                }
                            ?>
                                <div class="<?php echo $class; ?>">
                                    <i class='bx <?php echo $is_selected ? 'bx-radio-circle-marked' : 'bx-radio-circle'; ?>'></i>
                                    <?php echo htmlspecialchars($option_text); ?>
                                    <?php if ($is_correct): ?>
                                        <i class='bx bx-check' style="color: var(--success-color);"></i>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif ($question['question_type'] === 'true_false'): ?>
                            <?php
                            $selected_answer = $question['answer_text'];
                            $correct_answer = $question['correct_answer'];
                            ?>
                            <div class="option-item <?php echo $selected_answer === 'true' ? ($question['is_correct'] ? 'correct' : 'incorrect') : ''; ?>">
                                <i class='bx <?php echo $selected_answer === 'true' ? 'bx-radio-circle-marked' : 'bx-radio-circle'; ?>'></i>
                                True
                                <?php if ($correct_answer === 'true'): ?>
                                    <i class='bx bx-check' style="color: var(--success-color);"></i>
                                <?php endif; ?>
                            </div>
                            <div class="option-item <?php echo $selected_answer === 'false' ? ($question['is_correct'] ? 'correct' : 'incorrect') : ''; ?>">
                                <i class='bx <?php echo $selected_answer === 'false' ? 'bx-radio-circle-marked' : 'bx-radio-circle'; ?>'></i>
                                False
                                <?php if ($correct_answer === 'false'): ?>
                                    <i class='bx bx-check' style="color: var(--success-color);"></i>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>