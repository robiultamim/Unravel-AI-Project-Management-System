// auth.js - Authentication page functionality

document.addEventListener('DOMContentLoaded', function() {
    // Password strength indicator
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        passwordInput.addEventListener('input', checkPasswordStrength);
    }
    
    // Role selection
    const roleOptions = document.querySelectorAll('.role-option');
    roleOptions.forEach(option => {
        option.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                roleOptions.forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
            }
        });
    });
    
    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                    showFieldError(field, 'This field is required');
                } else {
                    field.classList.remove('error');
                    removeFieldError(field);
                }
            });
            
            // Check password match
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            if (password && confirmPassword && password.value !== confirmPassword.value) {
                isValid = false;
                showFieldError(confirmPassword, 'Passwords do not match');
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    });
});

function checkPasswordStrength(password) {
    const password = password.target.value;
    const strengthIndicator = document.getElementById('password-strength');
    
    if (!strengthIndicator) {
        return;
    }
    
    let strength = 0;
    let tips = "";
    
    // Check password length
    if (password.length >= 8) {
        strength += 1;
    } else {
        tips += "Make the password at least 8 characters long. ";
    }
    
    // Check for mixed case
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) {
        strength += 1;
    } else {
        tips += "Include both lowercase and uppercase letters. ";
    }
    
    // Check for numbers
    if (/\d/.test(password)) {
        strength += 1;
    } else {
        tips += "Include at least one number. ";
    }
    
    // Check for special characters
    if (/[^a-zA-Z\d]/.test(password)) {
        strength += 1;
    } else {
        tips += "Include at least one special character. ";
    }
    
    // Update strength indicator
    const strengthClasses = ['very-weak', 'weak', 'medium', 'strong', 'very-strong'];
    strengthIndicator.className = 'password-strength ' + strengthClasses[strength];
    
    // Update tips
    const tipsElement = document.getElementById('password-tips');
    if (tipsElement) {
        if (tips) {
            tipsElement.textContent = tips;
            tipsElement.style.display = 'block';
        } else {
            tipsElement.style.display = 'none';
        }
    }
}

function showFieldError(field, message) {
    // Remove existing error
    removeFieldError(field);
    
    // Create error element
    const error = document.createElement('div');
    error.className = 'field-error';
    error.textContent = message;
    error.style.color = 'var(--danger)';
    error.style.fontSize = '0.8rem';
    error.style.marginTop = '5px';
    
    // Insert after field
    field.parentNode.insertBefore(error, field.nextSibling);
}

function removeFieldError(field) {
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

// Auto-hide alerts after 5 seconds
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);