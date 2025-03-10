<?php
define('ALLOWED_ACCESS', true);
require_once "./includes/session.php";
require_once "./includes/functions.php";

// Redirect logged-in users to their dashboards
if (isLoggedIn()) {
    switch($_SESSION['role']) {
        case 'teacher':
            header('Location: teacher/dashboard.php');
            break;
        case 'student':
            header('Location: student/dashboard.php');
            break;
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
    }
    exit();
}

// Check if page parameter exists
$page = $_GET['page'] ?? '';
if ($page === 'login' || $page === 'register') {
    // Include the appropriate page
    include_once "./{$page}.php";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Quiztify - Online Examination Platform</title>
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: var(--background-color);
            color: var(--text-color);
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Navigation */
        .navbar {
            background: var(--white);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .nav-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary-color);
            color: var(--white);
        }

        .btn-outline {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .btn-primary:hover {
            background: #1557b0;
        }

        .btn-outline:hover {
            background: var(--primary-color);
            color: var(--white);
        }

        /* Hero Section */
        .hero {
            padding: 8rem 0 4rem;
            background: linear-gradient(135deg, var(--sky-blue) 0%, #d0e3ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        .hero-content {
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }

        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            line-height: 1.2;
        }

        .hero p {
            font-size: 1.25rem;
            color: #5f6368;
            margin-bottom: 2.5rem;
        }

        .hero img {
            width: 250px; /* Increased image size */
            height: auto;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        /* Features */
        .features {
            padding: 6rem 0;
            background: var(--white);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2.5rem;
            margin-top: 3rem;
        }

        .feature-card {
            padding: 2.5rem;
            border-radius: 15px;
            background: var(--white);
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            text-align: center;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .feature-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
        }

        .feature-card h3 {
            color: var(--dark-color);
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .feature-card p {
            color: #5f6368;
        }

        /* Footer */
        .footer {
            background: var(--dark-color);
            color: var(--white);
            padding: 4rem 0 2rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
        }

        .footer-section h3 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            font-size: 1.2rem;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 0.8rem;
        }

        .footer-section a {
            color: var(--white);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-section a:hover {
            color: var(--primary-color);
        }

        .footer-bottom {
            text-align: center;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: #9aa0a6;
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }

            .hero-content > div {
                flex-direction: column;
                text-align: center;
            }

            .hero img {
                margin-bottom: 1rem;
            }

            .nav-content {
                padding: 0 1rem;
            }

            .features-grid {
                grid-template-columns: 1fr;
                padding: 0 1rem;
            }

            .hero {
                padding-top: 6rem;
            }
        }
            
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container nav-content">
            <a href="index.php" class="logo">Quiztify</a>
            <div class="nav-links">
                <a href="index.php?page=login" class="btn btn-outline">Login</a>
                <a href="index.php?page=register" class="btn btn-primary">Register</a>
                <button id="theme-toggle" class="btn btn-primary">
                    <i class="fas fa-adjust"></i> Toggle Theme
                </button>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container hero-content">
            <div style="display: flex; align-items: center; justify-content: center; gap: 2rem;">
                <img src="./pictures/App.pnj.png" alt="Quiztify Logo">
                <div>
                    <h1>Welcome to Quiztify</h1>
                    <p>Experience the next generation of online examinations and assessments</p>
                </div>
            </div>
            <div class="nav-links" style="justify-content: center; margin-top: 2rem;">
                <a href="index.php?page=register" class="btn btn-primary">Get Started</a>
                <a href="#features" class="btn btn-outline">Learn More</a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <div class="features-grid">
                <div class="feature-card">
                    <i class="fas fa-edit feature-icon"></i>
                    <h3>Easy Exam Creation</h3>
                    <p>Create and manage exams with multiple question types effortlessly.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-clock feature-icon"></i>
                    <h3>Timed Assessments</h3>
                    <p>Set time limits and monitor student progress in real-time.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-chart-bar feature-icon"></i>
                    <h3>Detailed Analytics</h3>
                    <p>Get comprehensive insights into student performance.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-shield-alt feature-icon"></i>
                    <h3>Secure Platform</h3>
                    <p>Ensure exam integrity with our secure testing environment.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="index.php?page=login">Login</a></li>
                        <li><a href="index.php?page=register">Register</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Contact Us</h3>
                    <p>Email: support@quiztify.com</p>
                    <p>Phone: (123) 456-7890</p>
                </div>
                <div class="footer-section">
                    <h3>Follow Us</h3>
                    <div style="font-size: 1.5rem;">
                        <a href="#" style="margin-right: 1rem;"><i class="fab fa-facebook"></i></a>
                        <a href="#" style="margin-right: 1rem;"><i class="fab fa-twitter"></i></a>
                        <a href="#" style="margin-right: 1rem;"><i class="fab fa-linkedin"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Quiztify. All rights reserved.</p>
            </div>
        </div>
    </footer>

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