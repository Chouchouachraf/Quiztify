<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

try {
    $conn = getDBConnection();
    
    // Create users table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
        department VARCHAR(100) NULL,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME NULL
    )";
    $conn->exec($sql);

    // Create remember_tokens table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS remember_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // Create classrooms table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS classrooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        teacher_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // Create classroom_students table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS classroom_students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        classroom_id INT NOT NULL,
        student_id INT NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_classroom_student (classroom_id, student_id)
    )";
    $conn->exec($sql);

    // Create exams table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS exams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        duration_minutes INT NOT NULL,
        passing_score DECIMAL(5,2) NOT NULL,
        attempts_allowed INT NOT NULL DEFAULT 1,
        is_published BOOLEAN DEFAULT FALSE,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        start_date DATETIME NOT NULL,
        end_date DATETIME NOT NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // Create questions table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exam_id INT NOT NULL,
        question_text TEXT NOT NULL,
        question_type ENUM('multiple_choice', 'true_false', 'short_answer') NOT NULL,
        points DECIMAL(5,2) NOT NULL DEFAULT 1.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // Create answers table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question_id INT NOT NULL,
        answer_text TEXT NOT NULL,
        is_correct BOOLEAN NOT NULL DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // Create exam_attempts table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS exam_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exam_id INT NOT NULL,
        student_id INT NOT NULL,
        start_time DATETIME NOT NULL,
        end_time DATETIME NULL,
        score DECIMAL(5,2) NULL,
        is_completed BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // Create student_answers table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS student_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        attempt_id INT NOT NULL,
        question_id INT NOT NULL,
        answer_text TEXT NOT NULL,
        is_correct BOOLEAN NULL,
        points_earned DECIMAL(5,2) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // Add default admin user if not exists
    $sql = "INSERT IGNORE INTO users (username, email, password, full_name, role, status) 
            VALUES ('admin', 'admin@quiztify.com', ?, 'System Administrator', 'admin', 'active')";
    $stmt = $conn->prepare($sql);
    $stmt->execute([password_hash('admin123', PASSWORD_DEFAULT)]);

    // Check if column exists
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'classroom_id'");
    if ($stmt->rowCount() == 0) {
        // Add the column and foreign key
        $conn->exec("ALTER TABLE users 
                    ADD COLUMN classroom_id INT NULL,
                    ADD CONSTRAINT fk_user_classroom 
                    FOREIGN KEY (classroom_id) 
                    REFERENCES classrooms(id) 
                    ON DELETE SET NULL");
        
        echo "Database updated successfully";
    } else {
        echo "Column already exists";
    }

    // Check if column exists
    $stmt = $conn->query("SHOW COLUMNS FROM exam_attempts LIKE 'created_at'");
    if ($stmt->rowCount() == 0) {
        // Add the column
        $conn->exec("ALTER TABLE exam_attempts 
                    ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
        
        // Update existing records
        $conn->exec("UPDATE exam_attempts 
                    SET created_at = start_time 
                    WHERE created_at IS NULL");
        
        echo "Database updated successfully";
    } else {
        echo "Column already exists";
    }

    echo "Database updated successfully!<br>";
    echo "Default admin credentials:<br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br>";
    echo "<p>Please change these credentials after first login.</p>";

} catch(PDOException $e) {
    die("Error updating database: " . $e->getMessage());
}
?>

<style>
    /* Add to existing styles */
    .question-points {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
    }
    
    .question-points input {
        font-size: 1.1rem;
        font-weight: 500;
        text-align: center;
    }
    
    .question-points small {
        display: block;
        text-align: center;
        margin-top: 5px;
    }
</style>