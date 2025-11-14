<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login & Register - Loan System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #210a1a 0%, #41081a 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-header {
            background: linear-gradient(135deg, #210a1a 0%, #41081a 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }

        .logo-container {
            margin-bottom: 20px;
        }

        .logo-container img {
            max-width: 150px;
            max-height: 150px;
            width: auto;
            height: auto;
            object-fit: contain;
        }

        .form-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .form-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .tabs {
            display: flex;
            background: #f5f5f5;
        }

        .tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            background: transparent;
            border: none;
            font-size: 16px;
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
            position: relative;
        }

        .tab.active {
            color: #210a1a;
            background: white;
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #210a1a 0%, #41081a 100%);
        }

        .form-container {
            padding: 40px;
        }

        .form {
            display: none;
        }

        .form.active {
            display: block;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateX(10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-group input:focus {
            border-color: #210a1a;
            box-shadow: 0 0 0 3px rgba(33, 10, 26, 0.1);
        }

        .form-group input::placeholder {
            color: #999;
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            font-size: 18px;
            user-select: none;
        }

        .password-toggle-icon:hover {
            color: #210a1a;
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .remember-me input[type="checkbox"] {
            width: auto;
            cursor: pointer;
        }

        .forgot-password {
            color: #210a1a;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .forgot-password:hover {
            color: #41081a;
            text-decoration: underline;
        }

        .submit-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #210a1a 0%, #41081a 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(33, 10, 26, 0.4);
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(33, 10, 26, 0.5);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }

        .error-message.show {
            display: block;
            animation: shake 0.4s ease-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .success-message {
            background: #efe;
            color: #3c3;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }

        .success-message.show {
            display: block;
        }

        @media (max-width: 480px) {
            .container {
                border-radius: 0;
                max-width: 100%;
            }

            .form-container {
                padding: 30px 20px;
            }

            .form-header {
                padding: 25px 20px;
            }

            .logo-container img {
                max-width: 120px;
                max-height: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-header">
            <div class="logo-container">
                <img src="img/bancorlogo.png" alt="Bancor Logo" onerror="this.style.display='none'">
            </div>
            <h1></h1>
            <p>Welcome! Please login or create an account</p>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="switchForm('login')">Login</button>
            <button class="tab" onclick="switchForm('register')">Register</button>
        </div>

        <div class="form-container">
            <?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
                <div class="success-message" style="display: block; margin-bottom: 20px;">
                    You have been logged out successfully!
                </div>
            <?php endif; ?>
            <!-- Login Form -->
            <form id="loginForm" class="form active" onsubmit="handleLogin(event)">
                <div class="error-message" id="loginError"></div>
                <div class="success-message" id="loginSuccess"></div>

                <div class="form-group">
                    <label for="loginEmail">Email Address</label>
                    <input type="email" id="loginEmail" name="email" placeholder="Enter your email" required>
                </div>

                <div class="form-group">
                    <label for="loginPassword">Password</label>
                    <div class="password-toggle">
                        <input type="password" id="loginPassword" name="password" placeholder="Enter your password" required>
                        <span class="password-toggle-icon" onclick="togglePassword('loginPassword', this)">👁️</span>
                    </div>
                </div>

                <div class="remember-forgot">
                    <label class="remember-me">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="forgot-password" onclick="alert('Forgot password feature coming soon!'); return false;">Forgot Password?</a>
                </div>

                <button type="submit" class="submit-btn">Login</button>
            </form>

            <!-- Register Form -->
            <form id="registerForm" class="form" onsubmit="handleRegister(event)">
                <div class="error-message" id="registerError"></div>
                <div class="success-message" id="registerSuccess"></div>

                <div class="form-group">
                    <label for="registerName">Full Name</label>
                    <input type="text" id="registerName" name="name" placeholder="Enter your full name" required>
                </div>

                <div class="form-group">
                    <label for="registerEmail">Email Address</label>
                    <input type="email" id="registerEmail" name="email" placeholder="Enter your email" required>
                </div>

                <div class="form-group">
                    <label for="registerPassword">Password</label>
                    <div class="password-toggle">
                        <input type="password" id="registerPassword" name="password" placeholder="Create a password" required minlength="6">
                        <span class="password-toggle-icon" onclick="togglePassword('registerPassword', this)">👁️</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="registerConfirmPassword">Confirm Password</label>
                    <div class="password-toggle">
                        <input type="password" id="registerConfirmPassword" name="confirmPassword" placeholder="Confirm your password" required>
                        <span class="password-toggle-icon" onclick="togglePassword('registerConfirmPassword', this)">👁️</span>
                    </div>
                </div>

                <button type="submit" class="submit-btn">Create Account</button>
            </form>
        </div>
    </div>

    <script>
        function switchForm(formType) {
            // Update tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');

            // Update forms
            const forms = document.querySelectorAll('.form');
            forms.forEach(form => form.classList.remove('active'));

            if (formType === 'login') {
                document.getElementById('loginForm').classList.add('active');
            } else {
                document.getElementById('registerForm').classList.add('active');
            }

            // Clear messages
            clearMessages();
        }

        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = '🙈';
            } else {
                input.type = 'password';
                icon.textContent = '👁️';
            }
        }

        function showError(formType, message) {
            const errorDiv = document.getElementById(formType + 'Error');
            errorDiv.textContent = message;
            errorDiv.classList.add('show');
            setTimeout(() => {
                errorDiv.classList.remove('show');
            }, 5000);
        }

        function showSuccess(formType, message) {
            const successDiv = document.getElementById(formType + 'Success');
            successDiv.textContent = message;
            successDiv.classList.add('show');
            setTimeout(() => {
                successDiv.classList.remove('show');
            }, 3000);
        }

        function clearMessages() {
            document.querySelectorAll('.error-message, .success-message').forEach(msg => {
                msg.classList.remove('show');
                msg.textContent = '';
            });
        }

        function handleLogin(event) {
            event.preventDefault();
            clearMessages();

            const email = document.getElementById('loginEmail').value;
            const password = document.getElementById('loginPassword').value;
            const remember = document.querySelector('#loginForm input[name="remember"]').checked;

            // Basic validation
            if (!email || !password) {
                showError('login', 'Please fill in all fields');
                return;
            }

            // Disable submit button
            const submitBtn = event.target.querySelector('.submit-btn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Logging in...';

            // Make API call
            fetch('api/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ email, password, remember })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('login', 'Login successful! Redirecting...');
                    
                    // Store user info in localStorage
                    localStorage.setItem('user', JSON.stringify(data.user));
                    
                    // Redirect to home page
                    setTimeout(() => {
                        window.location.href = 'home.php';
                    }, 1500);
                } else {
                    showError('login', data.message || 'Login failed. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Login';
                }
            })
            .catch(error => {
                console.error('Login error:', error);
                showError('login', 'Network error. Please check your connection and try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Login';
            });
        }

        function handleRegister(event) {
            event.preventDefault();
            clearMessages();

            const name = document.getElementById('registerName').value;
            const email = document.getElementById('registerEmail').value;
            const password = document.getElementById('registerPassword').value;
            const confirmPassword = document.getElementById('registerConfirmPassword').value;

            // Validation
            if (!name || !email || !password || !confirmPassword) {
                showError('register', 'Please fill in all fields');
                return;
            }

            if (password.length < 6) {
                showError('register', 'Password must be at least 6 characters');
                return;
            }

            if (password !== confirmPassword) {
                showError('register', 'Passwords do not match');
                return;
            }

            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showError('register', 'Please enter a valid email address');
                return;
            }

            // Disable submit button
            const submitBtn = event.target.querySelector('.submit-btn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating account...';

            // Make API call
            fetch('api/register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ name, email, password })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('register', 'Account created successfully! You can now login.');
                    
                    // Switch to login form after successful registration
                    setTimeout(() => {
                        switchForm('login');
                        document.querySelectorAll('.tab')[0].classList.add('active');
                        document.querySelectorAll('.tab')[1].classList.remove('active');
                        document.getElementById('loginEmail').value = email;
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Create Account';
                    }, 2000);
                } else  {
                    showError('register', data.message || 'Registration failed. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Create Account';
                }
            })
            .catch(error => {
                console.error('Registration error:', error);
                showError('register', 'Network error. Please check your connection and try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Account';
            });
        }
    </script>
</body>
</html>
