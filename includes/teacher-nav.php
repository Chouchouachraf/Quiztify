<?php
// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Get user data from session
$userInitials = isset($_SESSION['full_name']) ? strtoupper(substr($_SESSION['full_name'], 0, 2)) : 'T';
$userName = $_SESSION['full_name'] ?? 'Teacher';
?>

<style>
    .navbar {
        background: white;
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
        color: #2c3e50;
        font-size: 1.5rem;
        font-weight: bold;
    }

    .nav-brand i {
        color: #3498db;
    }

    .nav-links {
        display: flex;
        gap: 20px;
        align-items: center;
    }

    .nav-link {
        text-decoration: none;
        color: #666;
        padding: 8px 16px;
        border-radius: 8px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .nav-link:hover {
        background: #f8f9fa;
        color: #3498db;
    }

    .nav-link.active {
        background: #3498db;
        color: white;
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
        background: #3498db;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
    }

    .logout-btn {
        padding: 8px 16px;
        border: none;
        background: #e74c3c;
        color: white;
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
            background: white;
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
            <a href="exams.php" class="nav-link <?php echo $current_page === 'exams.php' ? 'active' : ''; ?>">
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
        </div>

        <div class="user-menu">
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
    // Add mobile menu toggle functionality if needed
    function toggleMobileMenu() {
        const navLinks = document.querySelector('.nav-links');
        navLinks.classList.toggle('show');
    }
</script>