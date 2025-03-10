<!DOCTYPE html>
<html>
<head>
    <title>Logged Out - Quiztify</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --background-color: #f5f6fa;
            --text-color: #2c3e50;
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --warning-color: #f1c40f;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }

        [data-theme="dark"] {
            --background-color: #1a1a1a;
            --text-color: #ffffff;
            --primary-color: #5588ff;
            --secondary-color: #44bb77;
            --success-color: #44bb77;
            --danger-color: #ff5555;
            --warning-color: #ffcc00;
            --light-color: #333333;
            --dark-color: #ffffff;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: var(--background-color);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .logout-container {
            background: var(--light-color);
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }

        .icon-circle {
            background: var(--success-color);
            color: var(--light-color);
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 20px;
        }

        h1 {
            color: var(--primary-color);
            margin: 0 0 20px;
            font-size: 24px;
        }

        p {
            color: var(--text-color);
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary-color);
            color: var(--light-color);
            padding: 12px 24px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #357abd;
        }

        .timer {
            margin-top: 20px;
            color: var(--text-color);
            font-size: 14px;
        }

        #theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px;
            border: none;
            border-radius: 5px;
            background: var(--primary-color);
            color: var(--light-color);
            cursor: pointer;
        }

        @media (max-width: 480px) {
            .logout-container {
                padding: 30px;
            }

            h1 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <button id="theme-toggle">
        <i class="bx bx-moon"></i> Toggle Theme
    </button>
    <div class="logout-container">
        <div class="icon-circle">
            <i class="fas fa-check fa-2x"></i>
        </div>
        <h1>Successfully Logged Out</h1>
        <p>Thank you for using Quiztify. You have been safely logged out of your account.</p>
        <a href="login.php" class="btn">
            <i class="fas fa-sign-in-alt"></i>
            Log In Again
        </a>
        <div class="timer">
            Redirecting to login page in <span id="countdown">5</span> seconds...
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const theme = localStorage.getItem('theme') || 'light';
            document.body.dataset.theme = theme;

            document.getElementById('theme-toggle').addEventListener('click', function() {
                const newTheme = document.body.dataset.theme === 'dark' ? 'light' : 'dark';
                document.body.dataset.theme = newTheme;
                localStorage.setItem('theme', newTheme);
            });

            // Countdown timer
            let timeLeft = 5;
            const countdownElement = document.getElementById('countdown');
            
            const timer = setInterval(() => {
                timeLeft--;
                countdownElement.textContent = timeLeft;
                
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    window.location.href = 'login.php';
                }
            }, 1000);
        });
    </script>
</body>
</html>