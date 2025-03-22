<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Authentication Functions
 */
function checkRole($required_role) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        setFlashMessage('error', 'Please login to continue.');
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }

    if ($_SESSION['role'] !== $required_role) {
        setFlashMessage('error', 'You do not have permission to access this page.');
        redirectBasedOnRole($_SESSION['role']);
    }
}

function redirectBasedOnRole($role) {
    switch ($role) {
        case 'admin':
            header('Location: /Quiztify/admin/dashboard.php');
            break;
        case 'teacher':
            header('Location: /Quiztify/teacher/dashboard.php');
            break;
        case 'student':
            header('Location: /Quiztify/student/dashboard.php');
            break;
        default:
            header('Location: /Quiztify/login.php');
    }
    exit;
}


function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            SELECT id, username, full_name, email, role, department, 
                   DATE_FORMAT(created_at, '%M %Y') as join_date
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching user: " . $e->getMessage());
        return null;
    }
}

/**
 * Flash Messages
 */
function setFlashMessage($type, $message) {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = array();
    }
    $_SESSION['flash_messages'][$type] = $message;
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Validation Functions
 */
function validateInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
    // Minimum 8 characters, at least one letter and one number
    return strlen($password) >= 8 && 
           preg_match('/[A-Za-z]/', $password) && 
           preg_match('/\d/', $password);
}

/**
 * File Upload Functions
 */
function handleFileUpload($file, $allowed_types = ['jpg', 'jpeg', 'png'], $max_size = 5242880) {
    try {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed');
        }

        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_types)) {
            throw new Exception('Invalid file type');
        }

        if ($file['size'] > $max_size) {
            throw new Exception('File too large');
        }

        $upload_dir = '../uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $new_filename = uniqid() . '.' . $file_extension;
        $destination = $upload_dir . $new_filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception('Failed to move uploaded file');
        }

        return $new_filename;
    } catch (Exception $e) {
        error_log("File upload error: " . $e->getMessage());
        return false;
    }
}

/**
 * Date and Time Functions
 */
function formatDate($date, $format = 'M j, Y') {
    return date($format, strtotime($date));
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}

/**
 * Activity Logging
 */
function logActivity($user_id, $activity_type, $details) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            INSERT INTO user_activity_logs (user_id, activity_type, activity_details)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user_id, $activity_type, $details]);
        return true;
    } catch (PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Notification Functions
 */
function createNotification($user_id, $type, $message) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, message)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user_id, $type, $message]);
        return true;
    } catch (PDOException $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

function getUnreadNotifications($user_id) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND is_read = 0 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Security Functions
 */
function generateToken() {
    return bin2hex(random_bytes(32));
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function requirePost() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die('Method not allowed');
    }
}

/**
 * Error Handling
 */
function handleError($errno, $errstr, $errfile, $errline) {
    $error_message = "Error [$errno]: $errstr in $errfile on line $errline";
    error_log($error_message);

    if (in_array($errno, [E_ERROR, E_USER_ERROR])) {
        setFlashMessage('error', 'A critical error occurred. Please try again later.');
        header('Location: /Quiztify/error.php');
        exit;
    }
}

set_error_handler('handleError');

/**
 * Pagination Helper
 */
function getPaginationData($total_items, $items_per_page, $current_page) {
    $total_pages = ceil($total_items / $items_per_page);
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $items_per_page;

    return [
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'offset' => $offset,
        'items_per_page' => $items_per_page
    ];
}

/**
 * Remember Me Functionality
 */
function handleRememberMe() {
    if (isset($_COOKIE['remember_token'])) {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("
                SELECT u.* 
                FROM users u
                JOIN remember_tokens rt ON u.id = rt.user_id
                WHERE rt.token = ? AND rt.expires_at > NOW()
            ");
            $stmt->execute([$_COOKIE['remember_token']]);
            $user = $stmt->fetch();

            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['department'] = $user['department'];
                
                // Refresh remember token
                $new_token = generateToken();
                $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                $stmt = $conn->prepare("
                    UPDATE remember_tokens 
                    SET token = ?, expires_at = ? 
                    WHERE user_id = ?
                ");
                $stmt->execute([$new_token, $expires, $user['id']]);
                
                setcookie('remember_token', $new_token, strtotime('+30 days'), '/', '', true, true);
            }
        } catch (PDOException $e) {
            error_log("Remember me error: " . $e->getMessage());
        }
    }
}

/**
 * Display flash messages
 */
function displayFlashMessages() {
    if (isset($_SESSION['flash_messages']) && !empty($_SESSION['flash_messages'])) {
        foreach ($_SESSION['flash_messages'] as $type => $message) {
            $backgroundColor = '';
            $textColor = '#fff';
            
            switch ($type) {
                case 'success':
                    $backgroundColor = '#28a745';
                    break;
                case 'error':
                    $backgroundColor = '#dc3545';
                    break;
                case 'warning':
                    $backgroundColor = '#ffc107';
                    $textColor = '#000';
                    break;
                case 'info':
                    $backgroundColor = '#17a2b8';
                    break;
            }
            
            echo "<div class='flash-message' style='
                    padding: 15px;
                    margin-bottom: 20px;
                    border-radius: 4px;
                    background-color: {$backgroundColor};
                    color: {$textColor};
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    animation: slideIn 0.5s ease-out;
                '>
                {$message}
            </div>";
        }
        // Clear the flash messages after displaying them
        unset($_SESSION['flash_messages']);
    }
}

function formatDateTime($dateTime) {
    try {
        $date = new DateTime($dateTime);
        return $date->format('M d, Y h:i A'); // Example: Jan 01, 2024 02:30 PM
    } catch (Exception $e) {
        error_log("Error formatting date: " . $e->getMessage());
        return 'Invalid date';
    }
}