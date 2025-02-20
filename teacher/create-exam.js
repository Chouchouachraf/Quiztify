let questionCounter = 0;

function addQuestion(type) {
    const container = document.getElementById('questions-container');
    const questionBox = document.createElement('div');
    questionBox.className = 'question-box';
    questionBox.dataset.questionIndex = questionCounter;

    let template = `
        <div class="question-header">
            <h4>Question ${questionCounter + 1}</h4>
            <button type="button" class="btn btn-danger" onclick="removeQuestion(this)">
                <i class="fas fa-trash"></i>
            </button>
        </div>

        <input type="hidden" name="questions[${questionCounter}][type]" value="${type}">
        
        <div class="form-group">
            <label>Question Text</label>
            <textarea name="questions[${questionCounter}][text]" required rows="3"></textarea>
        </div>

        <div class="form-group">
            <label>Points</label>
            <input type="number" name="questions[${questionCounter}][points]" value="1" min="0.5" step="0.5" required>
        </div>`;

    // Add type-specific fields
    if (type === 'mcq') {
        template += `
            <div class="mcq-options">
                <div class="options-list">
                    <div class="option-row">
                        <input type="text" name="questions[${questionCounter}][options][]" placeholder="Option 1" required>
                        <input type="radio" name="questions[${questionCounter}][correct]" value="0" required>
                        <button type="button" class="btn btn-danger" onclick="removeOption(this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary" onclick="addOption(${questionCounter})">
                    Add Option
                </button>
            </div>`;
    } else if (type === 'true_false') {
        template += `
            <div class="true-false-options">
                <label>Correct Answer</label>
                <div class="radio-group">
                    <label>
                        <input type="radio" name="questions[${questionCounter}][correct]" value="true" required> True
                    </label>
                    <label>
                        <input type="radio" name="questions[${questionCounter}][correct]" value="false" required> False
                    </label>
                </div>
            </div>`;
    }

    questionBox.innerHTML = template;
    container.appendChild(questionBox);
    questionCounter++;
}

function removeQuestion(button) {
    if (confirm('Are you sure you want to remove this question?')) {
        const questionBox = button.closest('.question-box');
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
    document.querySelectorAll('.question-box').forEach((box, index) => {
        box.querySelector('h4').textContent = `Question ${index + 1}`;
    });
}

// Form validation
document.getElementById('examForm').addEventListener('submit', function(e) {
    const questions = document.querySelectorAll('.question-box');
    
    if (questions.length === 0) {
        e.preventDefault();
        alert('Please add at least one question to the exam');
        return;
    }

    // Validate each question
    questions.forEach((question, index) => {
        const type = question.querySelector('input[name^="questions"][name$="[type]"]').value;
        
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