<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

checkRole('student');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exams - QuizTify</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* Tetris-themed styles */
        :root {
            --primary-color: #4361ee; /* Tetris blue */
            --secondary-color: #3a0ca3; /* Tetris purple */
            --success-color: #2ed573; /* Tetris green */
            --danger-color: #ff6b6b; /* Tetris red */
            --warning-color: #ffd32a; /* Tetris yellow */
            --light-color: #f2f5ff; /* Tetris background */
            --dark-color: #1e1e2c; /* Tetris dark */
            --background-color: #f6f8ff; /* Tetris light background */
            --text-color: #2d3748; /* Tetris text color */
            
            /* Spacing variables */
            --spacing-xs: 8px;
            --spacing-sm: 12px;
            --spacing-md: 16px;
            --spacing-lg: 24px;
            --spacing-xl: 32px;
            
            /* Border radius */
            --border-radius-sm: 6px;
            --border-radius-md: 10px;
            --border-radius-lg: 15px;
            
            /* Box shadow */
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --box-shadow-hover: 0 6px 16px rgba(0, 0, 0, 0.12);
        }

        [data-theme="dark"] {
            --primary-color: #5a78ff; /* Brighter Tetris blue */
            --secondary-color: #7048e8; /* Brighter Tetris purple */
            --success-color: #40ff8d; /* Brighter Tetris green */
            --danger-color: #ff8787; /* Brighter Tetris red */
            --warning-color: #ffdf5a; /* Brighter Tetris yellow */
            --light-color: #2c2c3a; /* Darker Tetris background */
            --dark-color: #f0f0f0; /* Light text */
            --background-color: #121212; /* Dark background */
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            --box-shadow-hover: 0 6px 16px rgba(0, 0, 0, 0.3);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            transition: all 0.3s ease;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: var(--spacing-xl);
        }

        h1 {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: var(--spacing-md);
        }

        p {
            font-size: 1.2rem;
            color: var(--text-color);
            margin-bottom: var(--spacing-xl);
        }

        .play-btn {
            padding: var(--spacing-md) var(--spacing-lg);
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius-md);
            font-size: 1.2rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            text-decoration: none;
        }

        .play-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: var(--box-shadow-hover);
        }

        .play-btn i {
            font-size: 1.5rem;
        }

        .theme-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: var(--spacing-sm);
            border: none;
            background: transparent;
            color: var(--text-color);
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
        }

        .theme-toggle:hover {
            background: rgba(0, 0, 0, 0.05);
            transform: scale(1.05);
        }

        .theme-toggle i {
            font-size: 1.2rem;
        }

        /* Student Image Styles */
        .student-image {
            width: 200px;
            height: auto;
            border-radius: var(--border-radius-md);
            margin-bottom: var(--spacing-lg);
            box-shadow: var(--box-shadow);
            transition: transform 0.3s ease;
        }

        .student-image:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Happy Student Image -->
        <img src="../pictures/student1.png" alt="Happy Student" class="student-image">        <h1>Good Luck on Your Exams!</h1>
        <p>We wish you the best of luck and hope you achieve great grades. Take a break and relax with a game of Tetris!</p>
        <a href="http://localhost/Quiztify/student/tetris_game.php" class="play-btn">
            <i class='bx bx-game'></i>
            Play Tetris
        </a>
    </div>

    <!-- Theme Toggle Button -->
    <button id="theme-toggle" class="theme-toggle" title="Toggle Theme">
        <i class='bx bx-moon'></i>
    </button>

    <script>
        // Theme Toggle Functionality
        const themeToggle = document.getElementById('theme-toggle');
        const htmlElement = document.documentElement;

        const savedTheme = localStorage.getItem('theme') || 'light';
        htmlElement.setAttribute('data-theme', savedTheme);

        if (savedTheme === 'dark') {
            themeToggle.innerHTML = `<i class='bx bx-sun'></i>`;
        } else {
            themeToggle.innerHTML = `<i class='bx bx-moon'></i>`;
        }

        themeToggle.addEventListener('click', () => {
            const currentTheme = htmlElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            htmlElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);

            if (newTheme === 'dark') {
                themeToggle.innerHTML = `<i class='bx bx-sun'></i>`;
            } else {
                themeToggle.innerHTML = `<i class='bx bx-moon'></i>`;
            }
        });
    </script>
</body>
</html>