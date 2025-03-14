<?php
// Start the session
session_start();

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Get user data from session
$userInitials = isset($_SESSION['full_name']) ? strtoupper(substr($_SESSION['full_name'], 0, 2)) : 'S';
$userName = $_SESSION['full_name'] ?? 'Student';
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tetris Game | QuizTify</title>
    <!-- Include Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Navbar and Theme Styles */
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f1c40f;
            --light-color: #ffffff;
            --dark-color: #1e1e2c;
            --background-color: #f6f8ff;
            --text-color: #2d3748;
            --navbar-bg: #ffffff;
            --game-border: #4361ee;
            --tetris-bg: #f2f5ff;
            --grid-line: rgba(0, 0, 0, 0.1);
            
            /* Game colors */
            --tetris-cyan: #00d2d3;
            --tetris-blue: #4361ee;
            --tetris-orange: #ff9f43;
            --tetris-yellow: #ffd32a;
            --tetris-green: #2ed573;
            --tetris-purple: #a55eea;
            --tetris-red: #ff6b6b;
            
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
            --background-color: #121212;
            --text-color: #f0f0f0;
            --navbar-bg: #1e1e2c;
            --primary-color: #4361ee;
            --secondary-color: #7048e8;
            --success-color: #2ecc71;
            --danger-color: #ff6b6b;
            --warning-color: #ffd32a;
            --light-color: #2c2c3a;
            --dark-color: #f0f0f0;
            --game-border: #7048e8;
            --tetris-bg: #2c2c3a;
            --grid-line: rgba(255, 255, 255, 0.1);
            
            /* Game colors - slightly brighter for dark mode */
            --tetris-cyan: #00ffff;
            --tetris-blue: #5a78ff;
            --tetris-orange: #ffaf60;
            --tetris-yellow: #ffdf5a;
            --tetris-green: #40ff8d;
            --tetris-purple: #c17fff;
            --tetris-red: #ff8787;
            
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            --box-shadow-hover: 0 6px 16px rgba(0, 0, 0, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            transition: all 0.3s ease;
            min-height: 100vh;
            background-image: url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100" fill="none" stroke="%23e74c3c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"%3E%3Cpath d="M12 2L2 7l10 5 10-5z"%3E%3C/path%3E%3Cpath d="M2 17l10 5 10-5M2 12l10 5 10-5M2 7l10 5 10-5M2 12l10 5 10-5"%3E%3C/path%3E%3C/svg%3E');
            background-size: 200px;
            background-opacity: 0.05;
        }

        .navbar {
            background: var(--navbar-bg);
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
            background: var(--primary-color);
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
                background: var(--navbar-bg);
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

        /* Tetris Game Styles */
        .page-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--spacing-lg);
        }

        .game-wrapper {
            display: flex;
            justify-content: center;
            gap: var(--spacing-xl);
            flex-wrap: wrap;
            margin-top: var(--spacing-xl);
        }

        .game-container {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .game-board-wrapper {
            position: relative;
            border: 4px solid var(--game-border);
            border-radius: var(--border-radius-md);
            background-color: var(--tetris-bg);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .game-board {
            display: block;
        }

        .game-grid {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }

        .game-controls {
            margin-top: var(--spacing-lg);
            display: flex;
            gap: var(--spacing-md);
        }

        .control-btn {
            padding: var(--spacing-md);
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
        }

        .control-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: var(--box-shadow-hover);
        }

        .game-info {
            padding: var(--spacing-lg);
            background-color: var(--navbar-bg);
            border-radius: var(--border-radius-md);
            box-shadow: var(--box-shadow);
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
            width: 300px;
        }

        .info-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            color: var(--primary-color);
            margin-bottom: var(--spacing-sm);
        }

        .info-header i {
            font-size: 1.5rem;
        }

        .info-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-md);
        }

        .stat-card {
            background-color: var(--background-color);
            padding: var(--spacing-md);
            border-radius: var(--border-radius-sm);
            text-align: center;
        }

        .stat-card h3 {
            font-size: 0.8rem;
            color: var(--text-color);
            opacity: 0.8;
            margin-bottom: var(--spacing-xs);
        }

        .stat-card p {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .next-piece-preview {
            margin-top: var(--spacing-md);
            background-color: var(--background-color);
            padding: var(--spacing-md);
            border-radius: var(--border-radius-sm);
        }

        .next-piece-preview h3 {
            text-align: center;
            margin-bottom: var(--spacing-sm);
            font-size: 0.9rem;
            color: var(--text-color);
            opacity: 0.8;
        }

        .preview-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 80px;
        }

        .preview-canvas {
            background-color: var(--tetris-bg);
            border: 2px solid var(--game-border);
            border-radius: var(--border-radius-sm);
        }

        .high-scores {
            margin-top: var(--spacing-md);
        }

        .high-scores h3 {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            margin-bottom: var(--spacing-sm);
            font-size: 1rem;
        }

        .score-table {
            width: 100%;
            border-collapse: collapse;
        }

        .score-table th, .score-table td {
            padding: var(--spacing-xs);
            text-align: left;
            border-bottom: 1px solid var(--grid-line);
        }

        .score-table th {
            font-weight: 600;
            color: var(--text-color);
            opacity: 0.8;
            font-size: 0.8rem;
        }

        .score-table tr:last-child td {
            border-bottom: none;
        }

        .score-table .highlight {
            background-color: rgba(67, 97, 238, 0.1);
            font-weight: bold;
        }

        .game-message {
            font-size: 1.2rem;
            color: var(--primary-color);
            margin-bottom: var(--spacing-md);
            max-width: 600px;
            text-align: center;
            line-height: 1.5;
        }

        .play-again-btn {
            padding: var(--spacing-md) var(--spacing-lg);
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
            font-weight: 500;
            margin-top: var(--spacing-md);
        }

        .play-again-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: var(--box-shadow-hover);
        }

        .game-over {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            z-index: 10;
        }

        .game-over h2 {
            font-size: 2.5rem;
            margin-bottom: var(--spacing-md);
            text-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
        }

        .game-over p {
            font-size: 1.2rem;
            margin-bottom: var(--spacing-lg);
        }

        .instructions {
            margin-top: var(--spacing-xl);
            background-color: var(--navbar-bg);
            padding: var(--spacing-lg);
            border-radius: var(--border-radius-md);
            box-shadow: var(--box-shadow);
        }

        .instructions h2 {
            color: var(--primary-color);
            margin-bottom: var(--spacing-md);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .instructions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: var(--spacing-lg);
        }

        .instruction-card {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
        }

        .instruction-card h3 {
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }

        .instruction-card p {
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .key-combo {
            display: inline-flex;
            background-color: var(--background-color);
            padding: 2px 8px;
            border-radius: var(--border-radius-sm);
            font-family: monospace;
            margin: 0 2px;
        }

        /* Educational decorative elements */
        .game-container::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background-image: url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="%23e74c3c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"%3E%3Cpath d="M12 2L2 7l10 5 10-5-10-5z"%3E%3C/path%3E%3Cpath d="M2 17l10 5 10-5M2 12l10 5 10-5M2 7l10 5 10-5M2 12l10 5 10-5"%3E%3C/path%3E%3C/svg%3E');
            background-size: 50px;
            opacity: 0.05;
            pointer-events: none;
        }

        .game-info::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background-image: url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="%234361ee" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"%3E%3Ccircle cx="12" cy="12" r="10"%3E%3C/circle%3E%3C/svg%3E');
            background-size: 40px;
            opacity: 0.05;
            pointer-events: none;
        }

        /* Educational icons */
        .game-message::before {
            content: "";
            background-image: url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="%234361ee" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"%3E%3Crect x="3" y="3" width="18" height="18" rx="2" ry="2"%3E%3C/rect%3E%3Cpath d="M3 9l9-2 9 2"%3E%3C/path%3E%3Cpath d="M9 21V9"%3E%3C/path%3E%3Cpath d="M15 21V9"%3E%3C/path%3E%3C/svg%3E');
            background-repeat: no-repeat;
            background-size: contain;
            width: 48px;
            height: 48px;
            display: inline-block;
            vertical-align: middle;
            margin-right: 10px;
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
                <button id="light-theme-btn" class="theme-toggle" title="Switch to Light Mode">
                    <i class="fas fa-sun"></i>
                </button>
                <button id="dark-theme-btn" class="theme-toggle" title="Switch to Dark Mode">
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

    <div class="page-container">
        <!-- Game Message -->
        <h1 class="game-message">You've finished your exam! Relax and play Tetris while your classmates complete theirs.</h1>

        <!-- Tetris Game -->
        <div class="game-wrapper">
            <div class="game-container">
                <div class="game-board-wrapper">
                    <canvas id="tetris" class="game-board" width="300" height="600"></canvas>
                    <div id="game-over" class="game-over">
                        <h2>Game Over!</h2>
                        <p>Your final score: <span id="final-score">0</span></p>
                        <button class="play-again-btn" id="play-again">Play Again</button>
                    </div>
                </div>
                <div class="game-controls">
                    <button class="control-btn" id="left-btn" title="Move Left">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <button class="control-btn" id="rotate-btn" title="Rotate">
                        <i class="fas fa-redo"></i>
                    </button>
                    <button class="control-btn" id="down-btn" title="Move Down">
                        <i class="fas fa-arrow-down"></i>
                    </button>
                    <button class="control-btn" id="right-btn" title="Move Right">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <div class="game-info">
                <div class="info-header">
                    <i class="fas fa-gamepad"></i>
                    <h2>Tetris Challenge</h2>
                </div>
                <div class="info-stats">
                    <div class="stat-card">
                        <h3>SCORE</h3>
                        <p id="score">0</p>
                    </div>
                    <div class="stat-card">
                        <h3>LEVEL</h3>
                        <p id="level">1</p>
                    </div>
                    <div class="stat-card">
                        <h3>LINES</h3>
                        <p id="lines">0</p>
                    </div>
                    <div class="stat-card">
                        <h3>TIME</h3>
                        <p id="time">00:00</p>
                    </div>
                </div>

                <div class="next-piece-preview">
                    <h3>NEXT PIECE</h3>
                    <div class="preview-container">
                        <canvas id="next-piece" class="preview-canvas" width="120" height="80"></canvas>
                    </div>
                </div>

                <div class="high-scores">
                    <h3><i class="fas fa-trophy"></i> High Scores</h3>
                    <table class="score-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Score</th>
                                <th>Level</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="high-scores-table">
                            <!-- Score rows will be added dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="instructions">
            <h2><i class="fas fa-info-circle"></i> How to Play</h2>
            <div class="instructions-grid">
                <div class="instruction-card">
                    <h3><i class="fas fa-keyboard"></i> Controls</h3>
                    <p>Use <span class="key-combo">←</span> and <span class="key-combo">→</span> arrows to move left and right.</p>
                    <p>Press <span class="key-combo">↑</span> to rotate the piece.</p>
                    <p>Press <span class="key-combo">↓</span> to move down faster.</p>
                    <p>Or use the on-screen buttons below the game board.</p>
                </div>
                <div class="instruction-card">
                    <h3><i class="fas fa-trophy"></i> Scoring</h3>
                    <p>1 line cleared: 100 points</p>
                    <p>2 lines cleared: 300 points</p>
                    <p>3 lines cleared: 500 points</p>
                    <p>4 lines cleared: 800 points</p>
                </div>
                <div class="instruction-card">
                    <h3><i class="fas fa-level-up-alt"></i> Leveling Up</h3>
                    <p>Every 10 lines cleared increases your level.</p>
                    <p>Each level increases the game speed.</p>
                    <p>Higher levels mean higher scores!</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Theme toggle functionality
        const lightThemeBtn = document.getElementById('light-theme-btn');
        const darkThemeBtn = document.getElementById('dark-theme-btn');
        const htmlElement = document.documentElement;

        // Check for saved theme preference or use default theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        htmlElement.setAttribute('data-theme', savedTheme);

        // Set the initial visibility of theme buttons
        if (savedTheme === 'dark') {
            darkThemeBtn.style.display = 'none';
            lightThemeBtn.style.display = 'flex';
        } else {
            lightThemeBtn.style.display = 'none';
            darkThemeBtn.style.display = 'flex';
        }

        // Light theme button click handler
        lightThemeBtn.addEventListener('click', () => {
            htmlElement.setAttribute('data-theme', 'light');
            localStorage.setItem('theme', 'light');
            lightThemeBtn.style.display = 'none';
            darkThemeBtn.style.display = 'flex';
        });

        // Dark theme button click handler
        darkThemeBtn.addEventListener('click', () => {
            htmlElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
            darkThemeBtn.style.display = 'none';
            lightThemeBtn.style.display = 'flex';
        });

        // Mobile menu toggle
        function toggleMobileMenu() {
            const navLinks = document.getElementById('navLinks');
            navLinks.classList.toggle('show');
        }

        // Tetris Game Logic
        const canvas = document.getElementById('tetris');
        const context = canvas.getContext('2d');
        const nextPieceCanvas = document.getElementById('next-piece');
        const nextPieceContext = nextPieceCanvas.getContext('2d');
        const scoreElement = document.getElementById('score');
        const levelElement = document.getElementById('level');
        const linesElement = document.getElementById('lines');
        const timeElement = document.getElementById('time');
        const playAgainButton = document.getElementById('play-again');
        const finalScoreElement = document.getElementById('final-score');
        const gameOverScreen = document.getElementById('game-over');

        // Control buttons
        const leftBtn = document.getElementById('left-btn');
        const rightBtn = document.getElementById('right-btn');
        const downBtn = document.getElementById('down-btn');
        const rotateBtn = document.getElementById('rotate-btn');

        const ROWS = 20;
        const COLS = 10;
        const BLOCK_SIZE = 30;
        const BASE_SPEED = 1000; // Base speed in milliseconds

        context.scale(BLOCK_SIZE, BLOCK_SIZE);

        // Create grid lines
        function drawGrid() {
            context.beginPath();
            context.strokeStyle = getComputedStyle(document.documentElement).getPropertyValue('--grid-line');
            
            // Vertical lines
            for (let i = 1; i < COLS; i++) {
                context.moveTo(i, 0);
                context.lineTo(i, ROWS);
            }
            
            // Horizontal lines
            for (let i = 1; i < ROWS; i++) {
                context.moveTo(0, i);
                context.lineTo(COLS, i);
            }
            
            context.stroke();
        }

        const board = Array.from({ length: ROWS }, () => Array(COLS).fill(0));

        // Define tetromino shapes with a consistent format
        const pieces = [
            {
                shape: [[1, 1], [1, 1]],
                color: 'var(--tetris-yellow)',
                name: 'O'
            },
            {
                shape: [[0, 1, 0], [1, 1, 1]],
                color: 'var(--tetris-purple)',
                name: 'T'
            },
            {
                shape: [[1, 0, 0], [1, 1, 1]],
                color: 'var(--tetris-orange)',
                name: 'L'
            },
            {
                shape: [[0, 0, 1], [1, 1, 1]],
                color: 'var(--tetris-blue)',
                name: 'J'
            },
            {
                shape: [[1, 1, 0], [0, 1, 1]],
                color: 'var(--tetris-green)',
                name: 'S'
            },
            {
                shape: [[0, 1, 1], [1, 1, 0]],
                color: 'var(--tetris-red)',
                name: 'Z'
            },
            {
                shape: [[0, 0, 0, 0], [1, 1, 1, 1]],
                color: 'var(--tetris-cyan)',
                name: 'I'
            }
        ];

        let score = 0;
        let level = 1;
        let lines = 0;
        let dropCounter = 0;
        let lastTime = 0;
        let gameTime = 0;
        let gameActive = true;
        let currentPiece = null;
        let nextPiece = null;

        // Initialize the game
        function initGame() {
            currentPiece = getRandomPiece();
            nextPiece = getRandomPiece();
            drawNextPiece();
            updateGame();
        }

        // Get a random piece from the pieces array
        function getRandomPiece() {
            const randomIndex = Math.floor(Math.random() * pieces.length);
            return { ...pieces[randomIndex], x: Math.floor(COLS / 2) - 1, y: 0 };
        }

        // Draw the next piece preview
        function drawNextPiece() {
            nextPieceContext.clearRect(0, 0, nextPieceCanvas.width, nextPieceCanvas.height);
            nextPieceContext.scale(BLOCK_SIZE / 2, BLOCK_SIZE / 2);
            nextPieceContext.fillStyle = nextPiece.color;
            nextPiece.shape.forEach((row, y) => {
                row.forEach((value, x) => {
                    if (value) {
                        nextPieceContext.fillRect(x, y, 1, 1);
                    }
                });
            });
            nextPieceContext.setTransform(1, 0, 0, 1, 0, 0);
        }

        // Draw the current piece on the board
        function drawPiece(piece) {
            context.fillStyle = piece.color;
            piece.shape.forEach((row, y) => {
                row.forEach((value, x) => {
                    if (value) {
                        context.fillRect(piece.x + x, piece.y + y, 1, 1);
                    }
                });
            });
        }

        // Clear the current piece from the board
        function clearPiece(piece) {
            context.clearRect(piece.x, piece.y, piece.shape[0].length, piece.shape.length);
        }

        // Check if the piece can move to the new position
        function isValidMove(piece, newX, newY) {
            return piece.shape.every((row, y) => {
                return row.every((value, x) => {
                    if (!value) return true;
                    const boardX = newX + x;
                    const boardY = newY + y;
                    return boardX >= 0 && boardX < COLS && boardY < ROWS && !board[boardY][boardX];
                });
            });
        }

        // Move the piece left
        function moveLeft() {
            if (isValidMove(currentPiece, currentPiece.x - 1, currentPiece.y)) {
                clearPiece(currentPiece);
                currentPiece.x--;
                drawPiece(currentPiece);
            }
        }

        // Move the piece right
        function moveRight() {
            if (isValidMove(currentPiece, currentPiece.x + 1, currentPiece.y)) {
                clearPiece(currentPiece);
                currentPiece.x++;
                drawPiece(currentPiece);
            }
        }

        // Move the piece down
        function moveDown() {
            if (isValidMove(currentPiece, currentPiece.x, currentPiece.y + 1)) {
                clearPiece(currentPiece);
                currentPiece.y++;
                drawPiece(currentPiece);
            } else {
                placePiece();
            }
        }

        // Rotate the piece
        function rotatePiece() {
            const rotatedPiece = JSON.parse(JSON.stringify(currentPiece));
            rotatedPiece.shape = rotatedPiece.shape[0].map((val, index) =>
                rotatedPiece.shape.map(row => row[index]).reverse()
            );
            if (isValidMove(rotatedPiece, rotatedPiece.x, rotatedPiece.y)) {
                clearPiece(currentPiece);
                currentPiece.shape = rotatedPiece.shape;
                drawPiece(currentPiece);
            }
        }

        // Place the piece on the board
        function placePiece() {
            currentPiece.shape.forEach((row, y) => {
                row.forEach((value, x) => {
                    if (value) {
                        board[currentPiece.y + y][currentPiece.x + x] = currentPiece.color;
                    }
                });
            });

            // Check for completed lines
            checkLines();

            // Get the next piece
            currentPiece = nextPiece;
            nextPiece = getRandomPiece();
            drawNextPiece();

            // Check if the game is over
            if (!isValidMove(currentPiece, currentPiece.x, currentPiece.y)) {
                gameActive = false;
                gameOverScreen.style.display = 'flex';
                finalScoreElement.textContent = calculateFinalScore();
            }
        }

        // Check for completed lines and update the score
        function checkLines() {
            let linesCleared = 0;
            for (let y = ROWS - 1; y >= 0; y--) {
                if (board[y].every(cell => cell)) {
                    linesCleared++;
                    board.splice(y, 1);
                    board.unshift(Array(COLS).fill(0));
                }
            }

            if (linesCleared > 0) {
                score += [100, 300, 500, 800][linesCleared - 1];
                lines += linesCleared;
                level = Math.floor(lines / 10) + 1;
                updateStats();
            }
        }

        // Update the game stats
        function updateStats() {
            scoreElement.textContent = score;
            levelElement.textContent = level;
            linesElement.textContent = lines;
        }

        // Update the game time
        function updateTime() {
            const minutes = Math.floor(gameTime / 60);
            const seconds = Math.floor(gameTime % 60);
            timeElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }

        // Calculate the final score
        function calculateFinalScore() {
            const timeBonus = Math.floor(gameTime) * 10; // 10 points per second
            const levelBonus = level * 100; // 100 points per level
            const linesBonus = lines * 50; // 50 points per line
            return score + timeBonus + levelBonus + linesBonus;
        }

        // Main game loop
        function updateGame(time = 0) {
            if (!gameActive) return;

            const deltaTime = time - lastTime;
            lastTime = time;
            gameTime += deltaTime / 1000;

            dropCounter += deltaTime;
            if (dropCounter > BASE_SPEED / level) {
                moveDown();
                dropCounter = 0;
            }

            updateTime();
            requestAnimationFrame(updateGame);
        }

        // Event listeners for control buttons
        leftBtn.addEventListener('click', moveLeft);
        rightBtn.addEventListener('click', moveRight);
        downBtn.addEventListener('click', moveDown);
        rotateBtn.addEventListener('click', rotatePiece);

        // Keyboard controls
        document.addEventListener('keydown', event => {
            if (!gameActive) return;

            switch (event.key) {
                case 'ArrowLeft':
                    moveLeft();
                    break;
                case 'ArrowRight':
                    moveRight();
                    break;
                case 'ArrowDown':
                    moveDown();
                    break;
                case 'ArrowUp':
                    rotatePiece();
                    break;
            }
        });

        // Play again button
        playAgainButton.addEventListener('click', () => {
            gameOverScreen.style.display = 'none';
            resetGame();
            initGame();
        });

        // Reset the game state
        function resetGame() {
            board.forEach(row => row.fill(0));
            score = 0;
            level = 1;
            lines = 0;
            gameTime = 0;
            gameActive = true;
            updateStats();
            updateTime();
            context.clearRect(0, 0, canvas.width, canvas.height);
            drawGrid();
        }

        // Start the game
        initGame();
    </script>
</body>
</html>