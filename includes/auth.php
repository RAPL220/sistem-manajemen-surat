<?php
/**
 * Authentication Functions
 * Sistem Manajemen Surat
 */

require_once __DIR__ . '/functions.php';

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Check if user has specific role
 */
function hasRole($role) {
    return isLoggedIn() && $_SESSION['role'] === $role;
}

/**
 * Check if user has any of the specified roles
 */
function hasAnyRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    return in_array($_SESSION['role'], $roles);
}

/**
 * Require login - redirect to login if not authenticated
 */
function requireLogin($role = null) {
    if (!cleanOldSessions()) {
        redirectToLogin();
        return;
    }
    
    if (!isLoggedIn()) {
        redirectToLogin();
        return;
    }
    
    if ($role && !hasRole($role)) {
        http_response_code(403);
        die('Access denied. Insufficient permissions.');
    }
}

/**
 * Redirect to appropriate login page based on role
 */
function redirectToLogin($role = 'admin') {
    $loginPages = [
        'admin' => '/admin/login.php',
        'manager' => '/manager/login.php',
        'director' => '/director/login.php'
    ];
    
    $loginPage = $loginPages[$role] ?? $loginPages['admin'];
    header('Location: ' . BASE_URL . $loginPage);
    exit;
}

/**
 * Authenticate user
 */
function authenticateUser($username, $password) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['last_activity'] = time();
        
        // Log successful login
        logActivity('login', 'User logged in successfully');
        
        return [
            'success' => true,
            'user' => $user
        ];
    }
    
    // Log failed login attempt
    error_log("Failed login attempt for username: $username from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    
    return [
        'success' => false,
        'message' => 'Username atau password salah'
    ];
}

/**
 * Logout user
 */
function logoutUser() {
    if (isLoggedIn()) {
        logActivity('logout', 'User logged out');
    }
    
    // Destroy session
    session_unset();
    session_destroy();
    
    // Start new session for flash messages
    session_start();
    $_SESSION['flash_message'] = 'Anda telah berhasil logout';
}

/**
 * Get current user info
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Update user password
 */
function updateUserPassword($userId, $newPassword) {
    $db = getDB();
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $result = $stmt->execute([$hashedPassword, $userId]);
    
    if ($result) {
        logActivity('password_change', 'Password changed successfully');
    }
    
    return $result;
}

/**
 * Update user signature
 */
function updateUserSignature($userId, $signaturePath) {
    $db = getDB();
    
    $stmt = $db->prepare("UPDATE users SET digital_signature_path = ? WHERE id = ?");
    $result = $stmt->execute([$signaturePath, $userId]);
    
    if ($result) {
        logActivity('signature_update', 'Digital signature updated');
    }
    
    return $result;
}

/**
 * Create new user (admin only)
 */
function createUser($username, $password, $role, $fullName) {
    $db = getDB();
    
    // Check if username already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return [
            'success' => false,
            'message' => 'Username sudah digunakan'
        ];
    }
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("
        INSERT INTO users (username, password_hash, role, full_name) 
        VALUES (?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$username, $hashedPassword, $role, $fullName])) {
        logActivity('user_create', "New user created: $username ($role)");
        return [
            'success' => true,
            'message' => 'User berhasil dibuat'
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Gagal membuat user'
    ];
}

/**
 * Get all users (admin only)
 */
function getAllUsers() {
    $db = getDB();
    $stmt = $db->query("SELECT id, username, role, full_name, created_at FROM users ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

/**
 * Delete user (admin only)
 */
function deleteUser($userId) {
    $db = getDB();
    
    // Don't allow deleting current user
    if ($userId == $_SESSION['user_id']) {
        return [
            'success' => false,
            'message' => 'Tidak dapat menghapus user yang sedang login'
        ];
    }
    
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt->execute([$userId])) {
        logActivity('user_delete', "User deleted: ID $userId");
        return [
            'success' => true,
            'message' => 'User berhasil dihapus'
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Gagal menghapus user'
    ];
}

/**
 * Check password strength
 */
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password harus mengandung huruf besar';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password harus mengandung huruf kecil';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password harus mengandung angka';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Generate secure random password
 */
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle($chars), 0, $length);
}

/**
 * Rate limiting for login attempts
 */
function checkLoginRateLimit($username) {
    $db = getDB();
    $timeWindow = 900; // 15 minutes
    $maxAttempts = 5;
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as attempts 
        FROM login_attempts 
        WHERE username = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmt->execute([$username, $timeWindow]);
    $result = $stmt->fetch();
    
    return $result['attempts'] < $maxAttempts;
}

/**
 * Log login attempt
 */
function logLoginAttempt($username, $success, $ipAddress) {
    $db = getDB();
    
    $stmt = $db->prepare("
        INSERT INTO login_attempts (username, success, ip_address, attempted_at) 
        VALUES (?, ?, ?, NOW())
    ");
    
    return $stmt->execute([$username, $success ? 1 : 0, $ipAddress]);
}

/**
 * Clean old login attempts
 */
function cleanOldLoginAttempts() {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    return $stmt->execute();
}
?>
