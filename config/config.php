<?php
/**
 * Application Configuration
 * Sistem Manajemen Surat
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Application settings
define('APP_NAME', 'Sistem Manajemen Surat');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost:8000');

// File upload settings
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('SIGNATURE_DIR', UPLOAD_DIR . 'signatures/');
define('LETTERS_DIR', UPLOAD_DIR . 'letters/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_SIGNATURE_TYPES', ['image/png', 'image/jpeg']);

// Create upload directories if they don't exist
$directories = [UPLOAD_DIR, SIGNATURE_DIR, LETTERS_DIR];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Security settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 3600); // 1 hour

// Letter number format
define('LETTER_NUMBER_PREFIX', 'SRT');
define('LETTER_NUMBER_FORMAT', 'SRT-%s-%04d'); // SRT-YYYYMMDD-0001

// Notification types
define('NOTIFICATION_TYPES', [
    'new_submission' => 'Pengajuan Surat Baru',
    'approval' => 'Surat Disetujui',
    'rejection' => 'Surat Ditolak',
    'revision' => 'Surat Perlu Revisi'
]);

// Letter status
define('LETTER_STATUS', [
    'pending' => 'Menunggu Persetujuan Manager',
    'manager_approved' => 'Menunggu Persetujuan Direktur',
    'director_approved' => 'Disetujui - Surat Terbit',
    'rejected' => 'Ditolak',
    'revision' => 'Perlu Revisi'
]);

// User roles
define('USER_ROLES', [
    'admin' => 'Administrator',
    'manager' => 'Manager',
    'director' => 'Direktur'
]);

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// Clean old sessions
function cleanOldSessions() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}
?>
