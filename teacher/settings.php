<?php
require_once '../includes/functions.php';
checkRole('teacher');

$conn = getDBConnection();
$userId = $_SESSION['user_id'];

// Fetch current user data and preferences
try {
    $stmt = $conn->prepare("
        SELECT u.*, up.email_notifications 
        FROM users u 
        LEFT JOIN user_preferences up ON u.id = up.user_id 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Create default preferences if they don't exist
    if ($user['email_notifications'] === null) {
        $stmt = $conn->prepare("
            INSERT INTO user_preferences (user_id, email_notifications)
            VALUES (?, 1)
        ");
        $stmt->execute([$userId]);
        $user['email_notifications'] = 1;
    }
} catch (PDOException $e) {
    error_log("Error fetching user data: " . $e->getMessage());
    setFlashMessage('error', 'Failed to load user data.');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        // Update profile information
        if (isset($_POST['update_profile'])) {
            $stmt = $conn->prepare("
                UPDATE users 
                SET full_name = ?, 
                    email = ?,
                    phone = ?,
                    department = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $_POST['full_name'],
                $_POST['email'],
                $_POST['phone'],
                $_POST['department'],
                $userId
            ]);

            setFlashMessage('success', 'Profile updated successfully.');
        }

        // Change password
        if (isset($_POST['change_password'])) {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];

            // Verify current password
            if (!password_verify($currentPassword, $user['password'])) {
                throw new Exception('Current password is incorrect.');
            }

            // Validate new password
            if ($newPassword !== $confirmPassword) {
                throw new Exception('New passwords do not match.');
            }

            if (strlen($newPassword) < 6) {
                throw new Exception('Password must be at least 6 characters long.');
            }

            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);

            setFlashMessage('success', 'Password changed successfully.');
        }

        // Update notification preferences
        if (isset($_POST['update_notifications'])) {
            $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
            
            // Check if preferences exist
            $stmt = $conn->prepare("SELECT id FROM user_preferences WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            if ($stmt->fetch()) {
                // Update existing preferences
                $stmt = $conn->prepare("
                    UPDATE user_preferences 
                    SET email_notifications = ?
                    WHERE user_id = ?
                ");
            } else {
                // Insert new preferences
                $stmt = $conn->prepare("
                    INSERT INTO user_preferences (user_id, email_notifications)
                    VALUES (?, ?)
                ");
            }
            
            $stmt->execute([$emailNotifications, $userId]);
            setFlashMessage('success', 'Notification preferences updated.');
        }

        $conn->commit();
        header('Location: settings.php');
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        setFlashMessage('error', $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Teacher Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: #f5f6fa;
            color: #2c3e50;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .settings-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .card-header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .card-header h2 {
            color: #2c3e50;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #34495e;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }

        .alert {
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #2196F3;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .notification-option {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .notification-label {
            font-weight: 500;
            color: #2c3e50;
        }

        .notification-description {
            color: #666;
            font-size: 0.9rem;
            margin-left: 75px;
            margin-top: 5px;
        }

        .settings-header {
            margin-bottom: 30px;
        }

        .settings-header h1 {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .settings-header p {
            color: #666;
            font-size: 0.9rem;
        }

        @keyframes slideIn {
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
            .settings-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 10px;
            }

            .settings-card {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/teacher-nav.php'; ?>

        <div class="settings-header">
            <h1><i class="fas fa-cog"></i> Settings</h1>
            <p>Manage your account settings and preferences</p>
        </div>

        <?php if ($flash = getFlashMessage()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo $flash['message']; ?>
            </div>
        <?php endif; ?>

        <div class="settings-grid">
            <!-- Profile Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <h2><i class="fas fa-user"></i> Profile Settings</h2>
                </div>
                <form method="POST">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" 
                               value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="department">Department</label>
                        <input type="text" id="department" name="department" class="form-control" 
                               value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        Update Profile
                    </button>
                </form>
            </div>

            <!-- Password Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <h2><i class="fas fa-lock"></i> Change Password</h2>
                </div>
                <form method="POST">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" 
                               class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" 
                               class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               class="form-control" required>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary">
                        Change Password
                    </button>
                </form>
            </div>

            <!-- Notification Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <h2><i class="fas fa-bell"></i> Notification Settings</h2>
                </div>
                <form method="POST">
                    <div class="form-group">
                        <div class="notification-option">
                            <label class="switch">
                                <input type="checkbox" name="email_notifications" 
                                       <?php echo ($user['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                            <span class="notification-label">Email Notifications</span>
                        </div>
                        <p class="notification-description">
                            Receive email notifications about exam results and student activity.
                        </p>
                    </div>
                    <button type="submit" name="update_notifications" class="btn btn-primary">
                        Save Preferences
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Password validation
        document.querySelector('form[name="change_password"]').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
            }

            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
            }
        });
    </script>
</body>
</html>