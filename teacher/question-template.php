<div class="question-box" data-question-index="<?php echo $index ?? 'NEW_QUESTION'; ?>">
    <div class="question-header">
        <h4>Question <?php echo ($index ?? 0) + 1; ?></h4>
        <button type="button" class="btn btn-danger btn-sm" onclick="removeQuestion(this)">
            <i class="fas fa-trash"></i>
        </button>
    </div>

    <input type="hidden" name="questions[<?php echo $index ?? 'NEW_QUESTION'; ?>][id]" 
           value="<?php echo $question['id'] ?? ''; ?>">

    <div class="form-group">
        <label>Question Type</label>
        <select name="questions[<?php echo $index ?? 'NEW_QUESTION'; ?>][type]" 
                class="question-type" onchange="handleQuestionTypeChange(this)">
            <option value="mcq" <?php echo ($question['question_type'] ?? '') === 'mcq' ? 'selected' : ''; ?>>
                Multiple Choice
            </option>
            <option value="open" <?php echo ($question['question_type'] ?? '') === 'open' ? 'selected' : ''; ?>>
                Open Question
            </option>
            <option value="true_false" <?php echo ($question['question_type'] ?? '') === 'true_false' ? 'selected' : ''; ?>>
                True/False
            </option>
        </select>
    </div>

    <div class="form-group">
        <label>Question Text</label>
        <textarea name="questions[<?php echo $index ?? 'NEW_QUESTION'; ?>][text]" required rows="3"
                  class="question-text"><?php echo htmlspecialchars($question['question_text'] ?? ''); ?></textarea>
    </div>

    <div class="form-group">
        <label>Points</label>
        <input type="number" name="questions[<?php echo $index ?? 'NEW_QUESTION'; ?>][points]" 
               value="<?php echo $question['points'] ?? '1'; ?>" required min="0" step="0.5">
    </div>

    <div class="options-container" style="display: <?php echo ($question['question_type'] ?? 'mcq') === 'open' ? 'none' : 'block'; ?>">
        <?php if (($question['question_type'] ?? 'mcq') === 'true_false'): ?>
            <div class="true-false-options">
                <label>
                    <input type="radio" name="questions[<?php echo $index ?? 'NEW_QUESTION'; ?>][correct]" value="true"
                           <?php echo ($question['correct_answer'] ?? '') === 'true' ? 'checked' : ''; ?>> True
                </label>
                <label>
                    <input type="radio" name="questions[<?php echo $index ?? 'NEW_QUESTION'; ?>][correct]" value="false"
                           <?php echo ($question['correct_answer'] ?? '') === 'false' ? 'checked' : ''; ?>> False
                </label>
            </div>
        <?php else: ?>
            <div class="mcq-options">
                <?php
                if (isset($question['options'])) {
                    $options = explode('||', $question['options']);
                    foreach ($options as $optionIndex => $option) {
                        list($optionId, $optionText, $isCorrect) = explode(':', $option);
                        ?>
                        <div class="option-row">
                            <input type="text" 
                                   name="questions[<?php echo $index; ?>][options][<?php echo $optionIndex; ?>][text]"
                                   value="<?php echo htmlspecialchars($optionText); ?>" required>
                            <label>
                                <input type="radio" 
                                       name="questions[<?php echo $index; ?>][correct]"
                                       value="<?php echo $optionIndex; ?>"
                                       <?php echo $isCorrect ? 'checked' : ''; ?>>
                                Correct
                            </label>
                            <button type="button" class="btn btn-danger btn-sm" onclick="removeOption(this)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="addOption(this)">
                Add Option
            </button>
        <?php endif; ?>
    </div>
</div>