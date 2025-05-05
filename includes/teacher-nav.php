<?php
// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Get user data from session
$userInitials = isset($_SESSION['full_name']) ? strtoupper(substr($_SESSION['full_name'], 0, 2)) : 'T';
$userName = $_SESSION['full_name'] ?? 'Teacher';
?>

<style>
    :root {
        --primary-color: #2c3e50;
        --secondary-color: #3498db;
        --success-color: #2ecc71;
        --danger-color: #e74c3c;
        --warning-color: #f1c40f;
        --light-color: #ecf0f1;
        --dark-color: #2c3e50;
        --background-color: #f5f6fa;
        --text-color: #2c3e50;
    }

    [data-theme="dark"] {
        --background-color: #1a1a1a;
        --text-color: #ffffff;
        --primary-color: #2980b9; /* Dark blue primary color */
        --secondary-color: #3498db; /* Blue secondary color */
        --success-color: #44bb77;
        --danger-color: #ff5555;
        --warning-color: #ffcc00;
        --light-color: #333333;
        --dark-color: #ffffff;
    }

    .navbar {
        background: var(--background-color);
        padding: 15px 0;
        margin-bottom: 30px;
        border-radius: 15px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .nav-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 20px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .nav-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        color: var(--primary-color);
        font-size: 1.5rem;
        font-weight: bold;
    }

    .nav-brand i {
        color: var(--secondary-color);
    }

    .nav-links {
        display: flex;
        gap: 20px;
        align-items: center;
    }

    .nav-link {
        text-decoration: none;
        color: var(--text-color);
        padding: 8px 16px;
        border-radius: 8px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .nav-link:hover {
        background: var(--light-color);
        color: var(--secondary-color);
    }

    .nav-link.active {
        background: var(--secondary-color);
        color: var(--light-color);
    }

    .nav-link i {
        font-size: 1.1rem;
    }

    .user-menu {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .user-avatar {
        width: 35px;
        height: 35px;
        background: var(--secondary-color);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--light-color);
        font-weight: bold;
    }

    .logout-btn {
        padding: 8px 16px;
        border: none;
        background: var(--danger-color);
        color: var(--light-color);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .logout-btn:hover {
        background: #c0392b;
    }

    @media (max-width: 768px) {
        .nav-links {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--background-color);
            flex-direction: column;
            padding: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .nav-links.show {
            display: flex;
        }

        .mobile-menu {
            display: block;
        }
    }

    /* Updated Dark Mode Button Styling */
    #theme-toggle {
        padding: 8px;
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

    #theme-toggle:hover {
        background: rgba(0, 0, 0, 0.05);
        transform: scale(1.05);
    }

    #theme-toggle i {
        font-size: 1.2rem;
    }

    [data-theme="light"] #theme-toggle i {
        content: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="none" d="M0 0h24v24H0z"/><path fill="currentColor" d="M12 17a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0 2a7 7 0 1 0 0-14 7 7 0 0 0 0 14zm0-1a6 6 0 1 1 0-12 6 6 0 0 1 0 12z"/></svg>');
    }

    [data-theme="dark"] #theme-toggle i {
        content: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="none" d="M0 0h24v24H0z"/><path fill="currentColor" d="M12 17a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0 2a7 7 0 1 0 0-14 7 7 0 0 0 0 14zm0-1a6 6 0 1 1 0-12 6 6 0 0 1 0 12z"/><path fill="currentColor" d="M12 2a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0V3a1 1 0 0 1 1-1zm0 20a1 1 0 0 1 1-1v-1a1 1 0 1 1-2 0v1a1 1 0 0 1 1 1z"/></svg>');
    }
</style>

<nav class="navbar">
    <div class="nav-container">
        <a href="dashboard.php" class="nav-brand">
            <i class="fas fa-graduation-cap"></i>
            QuizTify
        </a>

        <div class="nav-links">
            <a href="dashboard.php" class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
            <a href="exams.php" class="nav-link <?php echo in_array($current_page, ['exams.php', 'view-exam.php', 'view-attempt.php']) ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i>
                Exams
            </a>
            <a href="exam-grades.php" class="nav-link <?php echo $current_page === 'exam-grades.php' ? 'active' : ''; ?>">
                <i class="fas fa-check-square"></i>
                Grade Exams
            </a>
            <a href="classrooms.php" class="nav-link <?php echo $current_page === 'classrooms.php' ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard"></i>
                Classrooms
            </a>
            <a href="results.php" class="nav-link <?php echo $current_page === 'results.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                Results
            </a>
            <a href="cheating-reports.php" class="nav-link <?php echo $current_page === 'cheating-reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-exclamation-triangle"></i>
                Cheating Reports
            </a>
        </div>

        <div class="user-menu">
            <button id="theme-toggle" class="theme-toggle" title="Toggle Dark Mode">
                <i class="fas fa-moon"></i>
            </button>
            <div class="user-avatar">
                <?php echo $userInitials; ?>
            </div>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>
</nav>

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