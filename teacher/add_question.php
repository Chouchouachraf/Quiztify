<?php
require_once '../includes/functions.php';
checkRole('teacher');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn = getDBConnection();
        
        // Insert question into question bank
        $stmt = $conn->prepare("
            INSERT INTO question_bank (
                teacher_id, 
                question_text, 
                question_type
            ) VALUES (?, ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $_POST['question_text'],
            $_POST['question_type']
        ]);
        
        $questionId = $conn->lastInsertId();
        
        // Handle different question types
        if ($_POST['question_type'] == 'mcq') {
            foreach ($_POST['options'] as $index => $optionText) {
                $stmt = $conn->prepare("
                    INSERT INTO question_options (
                        question_id,
                        option_text,
                        is_correct
                    ) VALUES (?, ?, ?)
                ");
                
                $stmt->execute([
                    $questionId,
                    $optionText,
                    isset($_POST['correct_option']) && $_POST['correct_option'] == $index
                ]);
            }
        } elseif ($_POST['question_type'] == 'true_false') {
            $stmt = $conn->prepare("
                INSERT INTO question_options (
                    question_id,
                    option_text,
                    is_correct
                ) VALUES 
                (?, 'True', ?),
                (?, 'False', ?)
            ");
            
            $stmt->execute([
                $questionId,
                $_POST['correct_answer'] === 'true',
                $questionId,
                $_POST['correct_answer'] === 'false'
            ]);
        }
        
        setFlashMessage('success', 'Question added successfully!');
        header('Location: question_bank.php');
        exit();
        
    } catch (Exception $e) {
        setFlashMessage('error', 'Error adding question: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Question - Quiztify</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include '../includes/teacher-nav.php'; ?>
        
        <div class="content">
            <h2>Add New Question</h2>
            
            <?php if ($flash = getFlashMessage()): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo $flash['message']; ?>
                </div>
            <?php endif; ?>
            
            <form id="questionForm" method="POST" class="question-form">
                <div class="form-group">
                    <label for="question_type">Question Type</label>
                    <select id="question_type" name="question_type" required onchange="toggleQuestionOptions()">
                        <option value="mcq">Multiple Choice</option>
                        <option value="open">Open Question</option>
                        <option value="true_false">True/False</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="question_text">Question Text</label>
                    <textarea id="question_text" name="question_text" required rows="3"></textarea>
                </div>
                
                <!-- MCQ Options -->
                <div id="mcq_options" class="form-group">
                    <div id="options_container">
                        <div class="option-row">
                            <input type="text" name="options[]" placeholder="Option 1" required>
                            <input type="radio" name="correct_option" value="0" required>
                            <button type="button" class="btn btn-danger" onclick="removeOption(this)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="addOption()">
                        Add Option
                    </button>
                </div>
                
                <!-- True/False Options -->
                <div id="true_false_options" class="form-group" style="display: none;">
                    <label>Correct Answer</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="correct_answer" value="true" required> True
                        </label>
                        <label>
                            <input type="radio" name="correct_answer" value="false" required> False
                        </label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Question</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/add-question.js"></script>
</body>
</html>