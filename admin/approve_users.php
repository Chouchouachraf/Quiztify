<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

checkRole('admin');

try {
    $conn = getDBConnection();
    
    // Get pending users
    $pending_users = $conn->query("
        SELECT id, username, email, full_name, role 
        FROM users 
        WHERE is_approved = FALSE
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Handle approval action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
        $userId = $_POST['user_id'];
        $stmt = $conn->prepare("UPDATE users SET is_approved = TRUE WHERE id = ?");
        $stmt->execute([$userId]);
        setFlashMessage('success', 'User approved successfully');
        header('Location: approve_users.php');
        exit();
    }
} catch(PDOException $e) {
    setFlashMessage('error', 'Error: ' . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Users - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Include your existing styles here */
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <!-- Include your sidebar content here -->
        </div>
        <div class="main-content">
            <div class="header">
                <h1 class="page-title">Approve Users</h1>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['role']); ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-primary">Approve</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>