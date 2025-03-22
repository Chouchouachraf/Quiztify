<?php
// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Get user data from session
$userInitials = isset($_SESSION['full_name']) ? strtoupper(substr($_SESSION['full_name'], 0, 2)) : 'S';
$userName = $_SESSION['full_name'] ?? 'Student';

// Simulate exam submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process exam submission (e.g., save answers, calculate score, etc.)
    // For simplicity, we'll just redirect to the Tetris game
    header('Location: tetris_game.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Submission</title>
    <!-- Include Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    /* Tetris-themed navbar styles */
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
        --background-color: #121212; /* Dark background */
        --text-color: #f0f0f0; /* Light text */
        --primary-color: #5a78ff; /* Brighter Tetris blue */
        --secondary-color: #7048e8; /* Brighter Tetris purple */
        --success-color: #40ff8d; /* Brighter Tetris green */
        --danger-color: #ff8787; /* Brighter Tetris red */
        --warning-color: #ffdf5a; /* Brighter Tetris yellow */
        --light-color: #2c2c3a; /* Darker Tetris background */
        --dark-color: #f0f0f0; /* Light text */
        --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        --box-shadow-hover: 0 6px 16px rgba(0, 0, 0, 0.3);
    }

    body {
        margin: 0;
        padding: 0;
        font-family: 'Poppins', sans-serif; /* Tetris font */
        background-color: var(--background-color);
        color: var(--text-color);
        transition: all 0.3s ease;
    }

    .navbar {
        background: var(--light-color);
        padding: var(--spacing-md) 0;
        margin: var(--spacing-md) var(--spacing-lg);
        margin-bottom: var(--spacing-xl);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--box-shadow);
        transition: all 0.3s ease;
    }

    .nav-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 var(--spacing-lg);
        max-width: 1200px;
        margin: 0 auto;
        height: 60px;
    }

    .nav-brand {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        text-decoration: none;
        color: var(--primary-color);
        font-size: 1.5rem;
        font-weight: bold;
        transition: all 0.3s ease;
    }

    .nav-brand:hover {
        transform: scale(1.02);
    }

    .nav-brand i {
        color: var(--secondary-color);
    }

    .nav-links {
        display: flex;
        gap: var(--spacing-md);
        align-items: center;
    }

    .nav-link {
        text-decoration: none;
        color: var(--text-color);
        padding: var(--spacing-sm) var(--spacing-md);
        border-radius: var(--border-radius-sm);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: var(--spacing-xs);
        font-weight: 500;
    }

    .nav-link:hover {
        background: var(--primary-color);
        color: white;
        transform: translateY(-2px);
        box-shadow: var(--box-shadow-hover);
    }

    .nav-link.active {
        background: var(--secondary-color);
        color: white;
        box-shadow: var(--box-shadow);
    }

    .nav-link i {
        font-size: 1.1rem;
    }

    .user-menu {
        display: flex;
        align-items: center;
        gap: var(--spacing-md);
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        background: var(--primary-color);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        box-shadow: var(--box-shadow);
        transition: all 0.3s ease;
    }

    .user-avatar:hover {
        transform: scale(1.05);
        box-shadow: var(--box-shadow-hover);
    }

    .theme-toggle {
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
        width: 36px;
        height: 36px;
    }

    .theme-toggle:hover {
        background: rgba(0, 0, 0, 0.05);
        transform: scale(1.05);
    }

    .theme-toggle i {
        font-size: 1.2rem;
    }

    .logout-btn {
        padding: var(--spacing-sm) var(--spacing-md);
        border: none;
        background: var(--danger-color);
        color: white;
        border-radius: var(--border-radius-sm);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: var(--spacing-xs);
        font-weight: 500;
    }

    .logout-btn:hover {
        background: #c0392b;
        transform: translateY(-2px);
        box-shadow: var(--box-shadow-hover);
    }

    /* Mobile menu styles */
    .mobile-menu-btn {
        display: none;
        background: transparent;
        border: none;
        color: var(--text-color);
        font-size: 1.5rem;
        cursor: pointer;
    }

    @media (max-width: 768px) {
        .nav-links {
            display: none;
            position: absolute;
            top: 80px;
            left: var(--spacing-lg);
            right: var(--spacing-lg);
            background: var(--light-color);
            flex-direction: column;
            padding: var(--spacing-md);
            border-radius: var(--border-radius-md);
            box-shadow: var(--box-shadow);
            z-index: 100;
        }

        .nav-links.show {
            display: flex;
        }

        .mobile-menu-btn {
            display: block;
        }

        .navbar {
            margin: var(--spacing-sm);
        }

        .nav-container {
            padding: 0 var(--spacing-md);
        }

        .user-menu {
            gap: var(--spacing-sm);
        }

        .logout-btn span {
            display: none;
        }
    }
</style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="nav-brand">
                <i class="fas fa-graduation-cap"></i>
                QuizTify
            </a>

            <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </button>

            <div class="nav-links" id="navLinks">
                <a href="dashboard.php" class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <a href="available-exams.php" class="nav-link <?php echo $current_page === 'available-exams.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    Available Exams
                </a>
                <a href="my-results.php" class="nav-link <?php echo $current_page === 'my-results.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    My Results
                </a>
                <!-- Game Icon -->
                <a href="tetris_game.php" class="nav-link <?php echo $current_page === 'tetris_game.php' ? 'active' : ''; ?>">
                    <i class="fas fa-gamepad"></i>
                    Play Tetris
                </a>
                <button id="light-theme" class="theme-toggle" title="Switch to Light Mode">
                    <i class="fas fa-sun"></i>
                </button>
                <button id="dark-theme" class="theme-toggle" title="Switch to Dark Mode">
                    <i class="fas fa-moon"></i>
                </button>
            </div>

            <div class="user-menu">
                <div class="user-avatar" title="<?php echo $userName; ?>">
                    <?php echo $userInitials; ?>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </nav>

    </form>

    <script>
        // Theme toggle functionality
        const lightThemeButton = document.getElementById('light-theme');
        const darkThemeButton = document.getElementById('dark-theme');

        function updateThemeButtonsVisibility() {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            if (currentTheme === 'light') {
                lightThemeButton.style.display = 'none';
                darkThemeButton.style.display = 'flex';
            } else {
                lightThemeButton.style.display = 'flex';
                darkThemeButton.style.display = 'none';
            }
        }

        darkThemeButton.addEventListener('click', () => {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
            updateThemeButtonsVisibility();
        });

        lightThemeButton.addEventListener('click', () => {
            document.documentElement.setAttribute('data-theme', 'light');
            localStorage.setItem('theme', 'light');
            updateThemeButtonsVisibility();
        });

        // Set theme based on local storage or system preference
        const savedTheme = localStorage.getItem('theme');
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;

        if (savedTheme) {
            document.documentElement.setAttribute('data-theme', savedTheme);
        } else if (prefersDark) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }

        updateThemeButtonsVisibility();

        // Mobile menu toggle functionality
        function toggleMobileMenu() {
            const navLinks = document.getElementById('navLinks');
            navLinks.classList.toggle('show');
        }
    </script>
</body>
</html>