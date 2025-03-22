let questionCounter = 0;

function addQuestion() {
    const container = document.getElementById('questionsContainer');
    const questionDiv = document.createElement('div');
    questionDiv.className = 'question-container';
    questionDiv.innerHTML = `
        <div class="d-flex justify-content-between align-items-start mb-3">
            <h4>Question ${questionCounter + 1}</h4>
            <span class="remove-question" onclick="removeQuestion(this)">×</span>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Question Text*</label>
            <textarea name="questions[${questionCounter}][text]" class="form-control" required></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Question Image</label>
            <input type="file" name="questions[${questionCounter}][image]" class="form-control" accept="image/*">
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Question Type*</label>
                <select name="questions[${questionCounter}][type]" class="form-control" 
                        onchange="handleQuestionType(this, ${questionCounter})" required>
                    <option value="mcq">Multiple Choice</option>
                    <option value="true_false">True/False</option>
                    <option value="open">Open Answer</option>
                </select>
            </div>
            <div class="col-md-6" id="optionsCount_${questionCounter}" style="display:none;">
                <label class="form-label">Number of Options</label>
                <select class="form-control" onchange="updateOptions(this, ${questionCounter})">
                    <option value="2">2 Options</option>
                    <option value="3">3 Options</option>
                    <option value="4" selected>4 Options</option>
                    <option value="5">5 Options</option>
                    <option value="6">6 Options</option>
                </select>
            </div>
        </div>

        <div id="options_${questionCounter}" class="options-container">
            <!-- Options will be added here based on question type -->
        </div>
    `;
    container.appendChild(questionDiv);
    handleQuestionType(questionDiv.querySelector('select'), questionCounter);
    questionCounter++;
}

function removeQuestion(button) {
    if (confirm('Are you sure you want to remove this question?')) {
        const questionBox = button.closest('.question-container');
        questionBox.remove();
        updateQuestionNumbers();
    }
}

function addOption(questionIndex) {
    const optionsList = document.querySelector(`[name="questions[${questionIndex}][options][]"]`)
        .closest('.options-list');
    const optionCount = optionsList.children.length;
    
    const optionRow = document.createElement('div');
    optionRow.className = 'option-row';
    optionRow.innerHTML = `
        <input type="text" name="questions[${questionIndex}][options][]" 
               placeholder="Option ${optionCount + 1}" required>
        <input type="radio" name="questions[${questionIndex}][correct]" value="${optionCount}" required>
        <button type="button" class="btn btn-danger" onclick="removeOption(this)">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    optionsList.appendChild(optionRow);
}

function removeOption(button) {
    const optionRow = button.closest('.option-row');
    const optionsList = optionRow.closest('.options-list');
    
    if (optionsList.children.length > 1) {
        optionRow.remove();
        // Update radio button values
        optionsList.querySelectorAll('.option-row').forEach((row, index) => {
            row.querySelector('input[type="radio"]').value = index;
        });
    } else {
        alert('You must have at least one option');
    }
}

function updateQuestionNumbers() {
    document.querySelectorAll('.question-container').forEach((box, index) => {
        box.querySelector('h4').textContent = `Question ${index + 1}`;
    });
}

// Form validation
document.getElementById('examForm').addEventListener('submit', function(e) {
    const questions = document.querySelectorAll('.question-container');
    
    if (questions.length === 0) {
        e.preventDefault();
        alert('Please add at least one question to the exam');
        return;
    }

    // Validate each question
    questions.forEach((question, index) => {
        const type = question.querySelector('select[name="questions[' + index + '][type]"]').value;
        
        if (type === 'mcq') {
            const options = question.querySelectorAll('.option-row');
            const correct = question.querySelector('input[type="radio"]:checked');
            
            if (options.length < 2) {
                e.preventDefault();
                alert(`Question ${index + 1}: Multiple choice questions must have at least 2 options`);
                return;
            }
            
            if (!correct) {
                e.preventDefault();
                alert(`Question ${index + 1}: Please select a correct answer`);
                return;
            }
        } else if (type === 'true_false') {
            const correct = question.querySelector('input[type="radio"]:checked');
            if (!correct) {
                e.preventDefault();
                alert(`Question ${index + 1}: Please select True or False`);
                return;
            }
        }
    });
});