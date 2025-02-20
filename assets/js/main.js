// Main JavaScript file for Quiztify

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(tooltip => {
        new Tooltip(tooltip);
    });

    // Handle mobile menu toggle
    const menuToggle = document.querySelector('.menu-toggle');
    const mobileMenu = document.querySelector('.mobile-menu');
    
    if (menuToggle && mobileMenu) {
        menuToggle.addEventListener('click', () => {
            mobileMenu.classList.toggle('active');
        });
    }

    // Flash message auto-dismiss
    const flashMessages = document.querySelectorAll('.alert');
    flashMessages.forEach(message => {
        setTimeout(() => {
            message.style.opacity = '0';
            setTimeout(() => message.remove(), 300);
        }, 5000);
    });

    // Form validation
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', validateForm);
    });
});

// Form validation function
function validateForm(e) {
    const form = e.target;
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            showError(field, 'This field is required');
        } else {
            clearError(field);
        }

        // Email validation
        if (field.type === 'email' && field.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(field.value)) {
                isValid = false;
                showError(field, 'Please enter a valid email address');
            }
        }

        // Password validation
        if (field.type === 'password' && field.dataset.minLength) {
            if (field.value.length < parseInt(field.dataset.minLength)) {
                isValid = false;
                showError(field, `Password must be at least ${field.dataset.minLength} characters`);
            }
        }
    });

    if (!isValid) {
        e.preventDefault();
    }
}

// Show error message
function showError(field, message) {
    clearError(field);
    field.classList.add('error');
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.textContent = message;
    field.parentNode.appendChild(errorDiv);
}

// Clear error message
function clearError(field) {
    field.classList.remove('error');
    const errorDiv = field.parentNode.querySelector('.error-message');
    if (errorDiv) {
        errorDiv.remove();
    }
}

// Tooltip class
class Tooltip {
    constructor(element) {
        this.element = element;
        this.message = element.dataset.tooltip;
        this.tooltip = null;
        
        this.init();
    }

    init() {
        this.element.addEventListener('mouseenter', () => this.show());
        this.element.addEventListener('mouseleave', () => this.hide());
    }

    show() {
        this.tooltip = document.createElement('div');
        this.tooltip.className = 'tooltip';
        this.tooltip.textContent = this.message;
        document.body.appendChild(this.tooltip);

        const elementRect = this.element.getBoundingClientRect();
        const tooltipRect = this.tooltip.getBoundingClientRect();

        this.tooltip.style.top = `${elementRect.top - tooltipRect.height - 10}px`;
        this.tooltip.style.left = `${elementRect.left + (elementRect.width/2) - (tooltipRect.width/2)}px`;
    }

    hide() {
        if (this.tooltip) {
            this.tooltip.remove();
            this.tooltip = null;
        }
    }
}

// Exam timer functionality
class ExamTimer {
    constructor(duration, displayElement, onComplete) {
        this.duration = duration * 60; // Convert to seconds
        this.display = displayElement;
        this.onComplete = onComplete;
        this.remaining = this.duration;
        this.interval = null;
    }

    start() {
        this.interval = setInterval(() => {
            this.remaining--;
            this.updateDisplay();

            if (this.remaining <= 0) {
                this.stop();
                if (this.onComplete) this.onComplete();
            }
        }, 1000);
    }

    stop() {
        if (this.interval) {
            clearInterval(this.interval);
        }
    }

    updateDisplay() {
        const minutes = Math.floor(this.remaining / 60);
        const seconds = this.remaining % 60;
        this.display.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
    }
}

// Initialize exam timer if on exam page
const timerElement = document.getElementById('exam-timer');
if (timerElement) {
    const duration = parseInt(timerElement.dataset.duration || 0);
    const timer = new ExamTimer(duration, timerElement, () => {
        document.getElementById('exam-form').submit();
    });
    timer.start();
}