document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('registerForm');
    const loginForm = document.getElementById('loginForm');
    
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
        
        // Validación en tiempo real de contraseñas
        const password = registerForm.querySelector('#password');
        const confirmPassword = registerForm.querySelector('#confirm_password');
        
        if (password && confirmPassword) {
            confirmPassword.addEventListener('input', function() {
                if (this.value && password.value !== this.value) {
                    this.setCustomValidity('Las contraseñas no coinciden');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    }
    
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
});
async function handleRegister(e) {
    e.preventDefault();
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Procesando...';
    clearErrors();
    const formData = new FormData(e.target);
    const firstName = formData.get('first_name').trim();
    const lastName = formData.get('last_name').trim();
    const email = formData.get('email').trim();
    const password = formData.get('password');
    const confirmPassword = formData.get('confirm_password');
    const department = formData.get('department');
    const acceptTerms = formData.get('accept_terms');
    if (!firstName || !lastName || !email || !password || !confirmPassword || !department) {
        showError('Todos los campos son obligatorios');
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        return;
    }
    if (!acceptTerms) {
        showError('Debe aceptar los términos y condiciones');
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        return;
    }
    if (!isValidEmail(email)) {
        showError('Formato de correo electrónico inválido');
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        return;
    }
    if (!isWhirlpoolEmail(email)) {
        showError('Debe utilizar un correo corporativo de Whirlpool (@whirlpool.com, @whirlpool.com.mx, @whirlpool.ca)');
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        return;
    }
    if (password !== confirmPassword) {
        showError('Las contraseñas no coinciden');
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        return;
    }
    const passwordValidation = validatePassword(password);
    if (!passwordValidation.valid) {
        showError(passwordValidation.message);
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        return;
    }
    
    try {
        const response = await fetch('process/register_process.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message);
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 1500);
        } else {
            showError(data.message);
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    } catch (error) {
        console.error('Error:', error);
        showError('Error de conexión. Por favor intente nuevamente');
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}
async function handleLogin(e) {
    e.preventDefault();
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Ingresando...';
    clearErrors();
    const formData = new FormData(e.target);
    
    const email = formData.get('email').trim();
    const password = formData.get('password');
    if (!email || !password) {
        showError('Correo electrónico y contraseña son obligatorios');
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        return;
    }
    if (!isValidEmail(email)) {
        showError('Formato de correo electrónico inválido');
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        return;
    }
    
    try {
        const response = await fetch('process/login_process.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message);
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 1000);
        } else {
            showError(data.message);
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    } catch (error) {
        console.error('Error:', error);
        showError('Error de conexión. Por favor intente nuevamente');
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function isWhirlpoolEmail(email) {
    const allowedDomains = ['whirlpool.com', 'whirlpool.com.mx', 'whirlpool.ca'];
    const domain = email.split('@')[1]?.toLowerCase();
    return allowedDomains.includes(domain);
}

function validatePassword(password) {
    if (password.length < 8) {
        return { valid: false, message: 'La contraseña debe tener al menos 8 caracteres' };
    }
    
    if (!/[A-Z]/.test(password)) {
        return { valid: false, message: 'La contraseña debe contener al menos una mayúscula' };
    }
    
    if (!/[a-z]/.test(password)) {
        return { valid: false, message: 'La contraseña debe contener al menos una minúscula' };
    }
    
    if (!/[0-9]/.test(password)) {
        return { valid: false, message: 'La contraseña debe contener al menos un número' };
    }
    
    return { valid: true };
}
function showError(message) {
    clearErrors();
    
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-error';
    alertDiv.innerHTML = `
        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
        </svg>
        <span>${message}</span>
    `;
    
    const form = document.querySelector('.auth-form');
    form.insertBefore(alertDiv, form.firstChild);
    alertDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    setTimeout(() => alertDiv.remove(), 5000);
}

function showSuccess(message) {
    clearErrors();
    
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success';
    alertDiv.innerHTML = `
        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
        </svg>
        <span>${message}</span>
    `;
    
    const form = document.querySelector('.auth-form');
    form.insertBefore(alertDiv, form.firstChild);
    alertDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function clearErrors() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => alert.remove());
}