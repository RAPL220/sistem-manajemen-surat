<?php
/**
 * Admin Login Page
 * Sistem Manajemen Surat
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn() && hasRole('admin')) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$messageType = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token keamanan tidak valid. Silakan coba lagi.';
        $messageType = 'error';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        if (empty($username) || empty($password)) {
            $message = 'Username dan password harus diisi.';
            $messageType = 'error';
        } elseif (!checkLoginRateLimit($username)) {
            $message = 'Terlalu banyak percobaan login. Silakan coba lagi dalam 15 menit.';
            $messageType = 'error';
        } else {
            $result = authenticateUser($username, $password);
            
            // Log login attempt
            logLoginAttempt($username, $result['success'], $ipAddress);
            
            if ($result['success']) {
                // Check if user is admin
                if ($result['user']['role'] !== 'admin') {
                    $message = 'Akses ditolak. Halaman ini khusus untuk Administrator.';
                    $messageType = 'error';
                    logoutUser();
                } else {
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                $message = $result['message'];
                $messageType = 'error';
            }
        }
    }
}

// Clean old login attempts
cleanOldLoginAttempts();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrator - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1><?php echo APP_NAME; ?></h1>
                <p>Login Administrator</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-info">
                    <?php echo $_SESSION['flash_message']; unset($_SESSION['flash_message']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                           required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn">Login</button>
                </div>
            </form>

            <div class="text-center mt-3">
                <p><a href="../public/index.php">← Kembali ke Pengajuan Surat</a></p>
                <p>
                    <a href="../manager/login.php">Login Manager</a> | 
                    <a href="../director/login.php">Login Direktur</a>
                </p>
            </div>

            <div class="mt-3">
                <small class="text-center d-block">
                    <strong>Default Login:</strong><br>
                    Username: admin<br>
                    Password: admin123
                </small>
            </div>
        </div>
    </div>

    <script>
        // Auto-focus on username field
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.getElementById('username');
            if (usernameField && !usernameField.value) {
                usernameField.focus();
            }
        });

        // Clear form on page load if there was an error
        <?php if ($messageType === 'error'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('password').value = '';
        });
        <?php endif; ?>
    </script>
</body>
</html>
