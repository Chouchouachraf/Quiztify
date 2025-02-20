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
        transition: transform 0.3s ease;
    }

    .nav-brand:hover {
        transform: translateY(-2px);
    }

    .nav-brand i {
        color: #3498db;
        font-size: 1.8rem;
    }

    .nav-links {
        display: flex;
        gap: 15px;
        align-items: center;
    }

    .nav-link {
        text-decoration: none;
        color: #666;
        padding: 10px 16px;
        border-radius: 8px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.95rem;
        font-weight: 500;
    }

    .nav-link:hover {
        background: #f8f9fa;
        color: #3498db;
        transform: translateY(-2px);
    }

    .nav-link.active {
        background: #3498db;
        color: white;
    }

    .nav-link i {
        font-size: 1.1rem;
    }

    .profile-dropdown {
        position: relative;
        margin-left: 20px;
    }

    .profile-button {
        background: none;
        border: 2px solid #e1e8ed;
        padding: 8px 16px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 10px;
        color: #2c3e50;
        font-size: 0.95rem;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .profile-button:hover {
        background: #f8f9fa;
        border-color: #3498db;
    }

    .profile-avatar {
        width: 35px;
        height: 35px;
        background: #3498db;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 0.9rem;
        transition: transform 0.3s ease;
    }

    .profile-button:hover .profile-avatar {
        transform: scale(1.1);
    }

    .dropdown-menu {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        padding: 8px 0;
        min-width: 220px;
        display: none;
        z-index: 1000;
        border: 1px solid #e1e8ed;
    }

    .dropdown-menu.show {
        display: block;
        animation: fadeIn 0.3s ease-out;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 20px;
        color: #2c3e50;
        text-decoration: none;
        transition: all 0.3s ease;
        font-size: 0.95rem;
    }

    .dropdown-item:hover {
        background: #f8f9fa;
        color: #3498db;
        padding-left: 24px;
    }

    .dropdown-item i {
        font-size: 1.1rem;
        width: 20px;
        text-align: center;
    }

    .dropdown-divider {
        height: 1px;
        background: #e1e8ed;
        margin: 8px 0;
    }

    .mobile-menu-button {
        display: none;
        background: none;
        border: none;
        padding: 8px;
        cursor: pointer;
        color: #2c3e50;
        font-size: 1.5rem;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (max-width: 768px) {
        .mobile-menu-button {
            display: block;
        }

        .nav-links {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            padding: 15px;
            flex-direction: column;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 0 0 15px 15px;
            z-index: 1000;
        }

        .nav-links.show {
            display: flex;
            animation: fadeIn 0.3s ease-out;
        }

        .nav-link {
            width: 100%;
            padding: 12px 20px;
        }

        .profile-dropdown {
            margin-left: 0;
            width: 100%;
        }

        .profile-button {
            width: 100%;
            justify-content: center;
            margin-top: 10px;
        }

        .dropdown-menu {
            width: 100%;
            position: static;
            box-shadow: none;
            border: 1px solid #e1e8ed;
            margin-top: 10px;
            border-radius: 8px;
        }
    }
</style>

<nav class="navbar">
    <div class="nav-container">
        <a href="dashboard.php" class="nav-brand">
            <i class="fas fa-graduation-cap"></i>
            Quiztify
        </a>

        <button class="mobile-menu-button" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>

        <div class="nav-links" id="navLinks">
            <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="classrooms.php" class="nav-link <?php echo $current_page == 'classrooms.php' ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard"></i> Classrooms
            </a>
            <a href="exams.php" class="nav-link <?php echo $current_page == 'exams.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> Exams
            </a>
            <a href="results.php" class="nav-link <?php echo $current_page == 'results.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Results
            </a>

            <div class="profile-dropdown">
                <button class="profile-button" onclick="toggleDropdown()">
                    <div class="profile-avatar"><?php echo $userInitials; ?></div>
                    <span><?php echo htmlspecialchars($userName); ?></span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="dropdown-menu" id="profileDropdown">
                    <a href="profile.php" class="dropdown-item">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <a href="settings.php" class="dropdown-item">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="../logout.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
    function toggleDropdown() {
        const dropdown = document.getElementById('profileDropdown');
        dropdown.classList.toggle('show');
    }

    function toggleMobileMenu() {
        const navLinks = document.getElementById('navLinks');
        navLinks.classList.toggle('show');
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('profileDropdown');
        const profileButton = event.target.closest('.profile-button');
        
        if (!profileButton && dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
        }
    });

    // Close mobile menu when window is resized
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            document.getElementById('navLinks').classList.remove('show');
        }
    });
</script>