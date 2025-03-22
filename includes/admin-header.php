<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Admin Dashboard' ?> - <?= SITE_NAME ?></title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <div class="nav-brand">
                <img src="../assets/images/logo.png" alt="Quiztify Logo">
                <h1>Admin Panel</h1>
            </div>
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="users.php" class="nav-link <?= $current_page === 'users' ? 'active' : '' ?>">
                    <i class="bi bi-people"></i> Users
                </a>
                <a href="exams.php" class="nav-link <?= $current_page === 'exams' ? 'active' : '' ?>">
                    <i class="bi bi-file-text"></i> Exams
                </a>
                <a href="classrooms.php" class="nav-link <?= $current_page === 'classrooms' ? 'active' : '' ?>">
                    <i class="bi bi-building"></i> Classrooms
                </a>
                <a href="statistics.php" class="nav-link <?= $current_page === 'statistics' ? 'active' : '' ?>">
                    <i class="bi bi-graph-up"></i> Statistics
                </a>
                <a href="../logout.php" class="nav-link">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </nav>
        </aside>
        <main class="main-content"> 