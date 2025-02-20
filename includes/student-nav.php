<nav class="navbar">
    <div class="nav-brand">
        <a href="dashboard.php">Quiztify</a>
    </div>
    <ul class="nav-links">
        <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="available-exams.php"><i class="fas fa-book"></i> Available Exams</a></li>
        <li><a href="my-results.php"><i class="fas fa-chart-bar"></i> My Results</a></li>
        <li>
    <a href="../logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to log out?')">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</li>
    </ul>
</nav>

<style>
    .navbar {
        background: #2c3e50;
        padding: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .nav-brand a {
        color: white;
        font-size: 1.5rem;
        text-decoration: none;
        font-weight: bold;
    }

    .nav-links {
        list-style: none;
        display: flex;
        gap: 20px;
        margin: 0;
        padding: 0;
    }

    .nav-links li a {
        color: #ecf0f1;
        text-decoration: none;
        padding: 8px 12px;
        border-radius: 4px;
        transition: background 0.3s;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .nav-links li a:hover {
        background: #34495e;
    }

    .logout-btn {
        background: #e74c3c;
        color: white;
    }

    .logout-btn:hover {
        background: #c0392b !important;
    }

    @media (max-width: 768px) {
        .navbar {
            flex-direction: column;
            gap: 10px;
        }

        .nav-links {
            flex-direction: column;
            width: 100%;
            text-align: center;
        }

        .nav-links li a {
            display: block;
            padding: 10px;
        }
    }
</style>