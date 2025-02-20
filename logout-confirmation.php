<!DOCTYPE html>
<html>
<head>
    <title>Logged Out - Quiztify</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .logout-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }

        .icon-circle {
            background: #4CAF50;
            color: white;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 20px;
        }

        h1 {
            color: #2c3e50;
            margin: 0 0 20px;
            font-size: 24px;
        }

        p {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #4CAF50;
            color: white;
            padding: 12px 24px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #45a049;
        }

        .timer {
            margin-top: 20px;
            color: #666;
            font-size: 14px;
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
    </script>
</body>
</html>