<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// If user is already logged in, redirect based on role
if (isset($_SESSION['user_id'])) {
    redirectBasedOnRole($_SESSION['role']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];

            // Handle remember me functionality
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/');
                
                // Store token in database
                $stmt = $conn->prepare("
                    INSERT INTO remember_tokens (user_id, token, expires_at) 
                    VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
                ");
                $stmt->execute([$user['id'], $token]);
            }

            // Redirect based on role
            redirectBasedOnRole($user['role']);
        } else {
            $error = "Invalid username or password";
        }
    } catch(PDOException $e) {
        $error = "Login failed: Database error";
        error_log($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Poppins", sans-serif;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: var(--background-color);
            color: var(--text-color);
        }

        .wrapper {
            width: 420px;
            background: var(--light-color);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .wrapper h1 {
            font-size: 36px;
            text-align: center;
            margin-bottom: 20px;
            color: var(--primary-color);
        }

        .input-box {
            position: relative;
            width: 100%;
            height: 50px;
            margin: 30px 0;
        }

        .input-box input {
            width: 100%;
            height: 100%;
            background: transparent;
            border: 2px solid var(--light-color);
            border-radius: 6px;
            outline: none;
            padding: 15px 45px 15px 15px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .input-box input:focus {
            border-color: var(--primary-color);
        }

        .input-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
            color: var(--light-color);
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            margin: -15px 0 15px;
        }

        .remember-forgot label {
            color: var(--text-color);
            cursor: pointer;
        }

        .remember-forgot a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .btn {
            width: 100%;
            height: 45px;
            background: var(--primary-color);
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            color: var(--light-color);
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: #357abd;
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
        }

        .register-link p a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }

        .error {
            background: var(--danger-color);
            color: var(--light-color);
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
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
            .wrapper {
                width: 100%;
                margin: 20px;
                padding: 20px;
            }

            .wrapper h1 {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <button id="theme-toggle">
        <i class="bx bx-moon"></i> Toggle Theme
    </button>
    <div class="wrapper">
        <h1>Login</h1>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form action="" method="POST">
            <div class="input-box">
                <input type="text" name="username" placeholder="Username or Email" required>
                <i class='bx bxs-user'></i>
            </div>
            <div class="input-box">
                <input type="password" name="password" placeholder="Password" required>
                <i class='bx bxs-lock-alt'></i>
            </div>

            <div class="remember-forgot">
                <label><input type="checkbox" name="remember"> Remember me</label>
            </div>

            <button type="submit" class="btn">Login</button>

            <div class="register-link">
                <p>Don't have an account? <a href="register.php">Register</a></p>
            </div>
        </form>
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
        });
    </script>
</body>
</html>