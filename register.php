<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];
    
    $errors = [];
    
    // Validate input
    if (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (!in_array($role, ['student', 'teacher'])) {
        $errors[] = "Invalid role selected";
    }
    
    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            
            // Check if username or email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->rowCount() > 0) {
                $errors[] = "Username or email already exists";
            } else {
                // Insert new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    INSERT INTO users (username, email, password, full_name, role)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$username, $email, $hashed_password, $full_name, $role]);
                
                $_SESSION['success'] = "Registration successful! Please login.";
                header('Location: login.php');
                exit();
            }
        } catch(PDOException $e) {
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
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
            width: 400px; /* Smaller width */
            background: var(--light-color);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            margin-left: 20px; /* Slightly to the left */
        }

        .wrapper h1 {
            font-size: 32px; /* Smaller font size */
            text-align: center;
            margin-bottom: 20px;
            color: var(--primary-color);
        }

        .input-box {
            position: relative;
            width: 100%;
            height: 45px; /* Smaller height */
            margin: 20px 0;
        }

        .input-box input {
            width: 100%;
            height: 100%;
            background: transparent;
            border: 2px solid var(--light-color);
            border-radius: 6px;
            outline: none;
            padding: 15px 45px 15px 15px;
            font-size: 14px; /* Smaller font size */
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
            font-size: 18px; /* Smaller icon size */
            color: var(--light-color);
        }

        .role-select {
            margin: 20px 0;
            position: relative;
        }

        .role-select select {
            width: 100%;
            height: 45px; /* Smaller height */
            background: transparent;
            border: 2px solid var(--light-color);
            border-radius: 6px;
            outline: none;
            padding: 0 15px;
            font-size: 14px; /* Smaller font size */
            transition: all 0.3s ease;
            appearance: none;
            cursor: pointer;
        }

        .role-select select:focus {
            border-color: var(--primary-color);
        }

        .role-select::after {
            content: '\25BC';
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--light-color);
            pointer-events: none;
            font-size: 14px; /* Smaller font size */
        }

        .btn {
            width: 100%;
            height: 40px; /* Smaller height */
            background: var(--primary-color);
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px; /* Smaller font size */
            color: var(--light-color);
            font-weight: 500;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .btn:hover {
            background: #357abd;
        }

        .register-link {
            text-align: center;
            margin-top: 15px; /* Smaller margin */
        }

        .register-link p a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }

        .register-link p a:hover {
            text-decoration: underline;
        }

        .errors {
            background: var(--danger-color);
            color: var(--light-color);
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px; /* Smaller margin */
            border-left: 4px solid var(--danger-color);
        }

        .errors ul {
            margin: 0;
            padding-left: 20px;
        }

        .errors li {
            margin: 5px 0;
        }

        .success {
            background: var(--success-color);
            color: var(--light-color);
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px; /* Smaller margin */
            border-left: 4px solid var(--success-color);
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
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <button id="theme-toggle">
        <i class="bx bx-moon"></i> Toggle Theme
    </button>
    <div class="wrapper">
        <h1>Register</h1>
        <?php if (!empty($errors)): ?>
            <div class="errors">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form action="" method="POST">
            <div class="input-box">
                <input type="text" name="username" placeholder="Username" required 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                <i class='bx bxs-user'></i>
            </div>
            
            <div class="input-box">
                <input type="email" name="email" placeholder="Email" required
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                <i class='bx bxs-envelope'></i>
            </div>
            
            <div class="input-box">
                <input type="text" name="full_name" placeholder="Full Name" required
                       value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                <i class='bx bxs-user-detail'></i>
            </div>
            
            <div class="input-box">
                <input type="password" name="password" placeholder="Password" required>
                <i class='bx bxs-lock-alt'></i>
            </div>
            
            <div class="input-box">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                <i class='bx bxs-lock-alt'></i>
            </div>
            
            <div class="role-select">
                <select name="role" required>
                    <option value="">Select Role</option>
                    <option value="student" <?php echo (isset($_POST['role']) && $_POST['role'] === 'student') ? 'selected' : ''; ?>>Student</option>
                    <option value="teacher" <?php echo (isset($_POST['role']) && $_POST['role'] === 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                </select>
            </div>

            <button type="submit" class="btn">Register</button>

            <div class="register-link">
                <p>Already have an account? <a href="login.php">Login</a></p>
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