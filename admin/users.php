<?php
/**
 * User Management Page
 * Sistem Manajemen Surat - Admin Only
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
requireLogin('admin');

$db = getDB();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token keamanan tidak valid.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $username = sanitizeInput($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? '';
            $fullName = sanitizeInput($_POST['full_name'] ?? '');
            
            if (empty($username) || empty($password) || empty($role) || empty($fullName)) {
                $message = 'Semua field harus diisi.';
                $messageType = 'error';
            } else {
                // Validate password strength
                $passwordValidation = validatePasswordStrength($password);
                if (!$passwordValidation['valid']) {
                    $message = 'Password tidak memenuhi syarat: ' . implode(', ', $passwordValidation['errors']);
                    $messageType = 'error';
                } else {
                    $result = createUser($username, $password, $role, $fullName);
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'error';
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $username = sanitizeInput($_POST['username'] ?? '');
            $role = $_POST['role'] ?? '';
            $fullName = sanitizeInput($_POST['full_name'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($role) || empty($fullName)) {
                $message = 'Username, role, dan nama lengkap harus diisi.';
                $messageType = 'error';
            } else {
                try {
                    // Check if username already exists (except current user)
                    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                    $stmt->execute([$username, $id]);
                    if ($stmt->fetch()) {
                        $message = 'Username sudah digunakan oleh user lain.';
                        $messageType = 'error';
                    } else {
                        // Update user data
                        if (!empty($password)) {
                            // Update with new password
                            $passwordValidation = validatePasswordStrength($password);
                            if (!$passwordValidation['valid']) {
                                $message = 'Password tidak memenuhi syarat: ' . implode(', ', $passwordValidation['errors']);
                                $messageType = 'error';
                            } else {
                                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                                $stmt = $db->prepare("UPDATE users SET username = ?, password_hash = ?, role = ?, full_name = ? WHERE id = ?");
                                $result = $stmt->execute([$username, $hashedPassword, $role, $fullName, $id]);
                            }
                        } else {
                            // Update without changing password
                            $stmt = $db->prepare("UPDATE users SET username = ?, role = ?, full_name = ? WHERE id = ?");
                            $result = $stmt->execute([$username, $role, $fullName, $id]);
                        }
                        
                        if (isset($result) && $result) {
                            $message = 'User berhasil diperbarui.';
                            $messageType = 'success';
                            logActivity('user_update', "User updated: $username (ID: $id)");
                        } else if (!isset($result)) {
                            // Password validation failed, message already set
                        } else {
                            $message = 'Gagal memperbarui user.';
                            $messageType = 'error';
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error updating user: " . $e->getMessage());
                    $message = 'Terjadi kesalahan sistem.';
                    $messageType = 'error';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $result = deleteUser($id);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
        } elseif ($action === 'reset_password') {
            $id = (int)$_POST['id'];
            $newPassword = generateRandomPassword();
            
            try {
                $result = updateUserPassword($id, $newPassword);
                if ($result) {
                    $message = "Password berhasil direset. Password baru: <strong>$newPassword</strong><br>Harap catat dan berikan kepada user.";
                    $messageType = 'success';
                } else {
                    $message = 'Gagal mereset password.';
                    $messageType = 'error';
                }
            } catch (Exception $e) {
                error_log("Error resetting password: " . $e->getMessage());
                $message = 'Terjadi kesalahan sistem.';
                $messageType = 'error';
            }
        }
    }
}

// Get all users
$users = getAllUsers();

// Get user for editing
$editUser = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editUser = $stmt->fetch();
}

// Get user statistics
$userStats = [];
foreach (['admin', 'manager', 'director'] as $role) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = ?");
    $stmt->execute([$role]);
    $userStats[$role] = $stmt->fetch()['count'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .user-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 1rem;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #000;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.875rem;
            text-transform: uppercase;
        }
        
        .password-strength {
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        
        .password-strength ul {
            margin: 0.5rem 0;
            padding-left: 1.5rem;
        }
        
        .role-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .role-admin { background: #dc3545; color: white; }
        .role-manager { background: #ffc107; color: black; }
        .role-director { background: #28a745; color: white; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="dashboard.php" class="logo"><?php echo APP_NAME; ?></a>
                <nav>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="letters.php">Monitoring Surat</a>
                    <a href="templates.php">Template Surat</a>
                    <a href="users.php" class="active">Kelola User</a>
                    <a href="notifications.php">Notifikasi</a>
                    <a href="logout.php">Logout</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="row">
            <div class="col-12">
                <h1>Kelola User</h1>
                <p>Kelola pengguna sistem dengan berbagai level akses.</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- User Statistics -->
        <div class="user-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $userStats['admin']; ?></div>
                <div class="stat-label">Administrator</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $userStats['manager']; ?></div>
                <div class="stat-label">Manager</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $userStats['director']; ?></div>
                <div class="stat-label">Direktur</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo array_sum($userStats); ?></div>
                <div class="stat-label">Total User</div>
            </div>
        </div>

        <div class="row">
            <!-- User List -->
            <div class="col-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Daftar User</h3>
                    </div>
                    
                    <?php if (empty($users)): ?>
                        <div class="text-center p-3">
                            <p>Belum ada user.</p>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Nama Lengkap</th>
                                    <th>Role</th>
                                    <th>Dibuat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td>
                                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($user['created_at'], 'd M Y'); ?></td>
                                        <td>
                                            <a href="users.php?edit=<?php echo $user['id']; ?>" 
                                               class="btn btn-small">Edit</a>
                                            
                                            <button onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                                    class="btn btn-small btn-warning">Reset Password</button>
                                            
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                                        class="btn btn-small btn-danger">Hapus</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- User Form -->
            <div class="col-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <?php echo $editUser ? 'Edit User' : 'Tambah User Baru'; ?>
                        </h3>
                        <?php if ($editUser): ?>
                            <a href="users.php" class="btn btn-small btn-outline">Batal Edit</a>
                        <?php endif; ?>
                    </div>

                    <form method="POST" action="users.php" id="userForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="<?php echo $editUser ? 'edit' : 'add'; ?>">
                        <?php if ($editUser): ?>
                            <input type="hidden" name="id" value="<?php echo $editUser['id']; ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($editUser['username'] ?? ''); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="full_name">Nama Lengkap *</label>
                            <input type="text" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($editUser['full_name'] ?? ''); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="role">Role *</label>
                            <select id="role" name="role" required>
                                <option value="">-- Pilih Role --</option>
                                <option value="admin" <?php echo (isset($editUser) && $editUser['role'] === 'admin') ? 'selected' : ''; ?>>
                                    Administrator
                                </option>
                                <option value="manager" <?php echo (isset($editUser) && $editUser['role'] === 'manager') ? 'selected' : ''; ?>>
                                    Manager
                                </option>
                                <option value="director" <?php echo (isset($editUser) && $editUser['role'] === 'director') ? 'selected' : ''; ?>>
                                    Direktur
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="password">
                                Password <?php echo $editUser ? '(kosongkan jika tidak diubah)' : '*'; ?>
                            </label>
                            <input type="password" id="password" name="password" 
                                   <?php echo $editUser ? '' : 'required'; ?>>
                            
                            <?php if (!$editUser): ?>
                                <div class="password-strength">
                                    <strong>Syarat Password:</strong>
                                    <ul>
                                        <li>Minimal 8 karakter</li>
                                        <li>Mengandung huruf besar</li>
                                        <li>Mengandung huruf kecil</li>
                                        <li>Mengandung angka</li>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn">
                                <?php echo $editUser ? 'Perbarui User' : 'Tambah User'; ?>
                            </button>
                            <?php if ($editUser): ?>
                                <a href="users.php" class="btn btn-outline">Batal</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Role Information -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Informasi Role</h3>
                    </div>
                    <div class="card-content">
                        <div class="mb-2">
                            <span class="role-badge role-admin">Administrator</span>
                            <p><small>Kelola template, user, monitoring sistem</small></p>
                        </div>
                        <div class="mb-2">
                            <span class="role-badge role-manager">Manager</span>
                            <p><small>Review dan persetujuan surat tahap pertama</small></p>
                        </div>
                        <div class="mb-2">
                            <span class="role-badge role-director">Direktur</span>
                            <p><small>Persetujuan final dan penerbitan surat</small></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Delete Form -->
    <form id="deleteForm" method="POST" action="users.php" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <!-- Reset Password Form -->
    <form id="resetPasswordForm" method="POST" action="users.php" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="id" id="resetPasswordId">
    </form>

    <script>
        function deleteUser(id, username) {
            if (confirm('Apakah Anda yakin ingin menghapus user "' + username + '"?\n\nTindakan ini tidak dapat dibatalkan.')) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        function resetPassword(id, username) {
            if (confirm('Apakah Anda yakin ingin mereset password user "' + username + '"?\n\nPassword baru akan ditampilkan setelah reset.')) {
                document.getElementById('resetPasswordId').value = id;
                document.getElementById('resetPasswordForm').submit();
            }
        }

        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const requirements = [
                { regex: /.{8,}/, text: 'Minimal 8 karakter' },
                { regex: /[A-Z]/, text: 'Huruf besar' },
                { regex: /[a-z]/, text: 'Huruf kecil' },
                { regex: /[0-9]/, text: 'Angka' }
            ];
            
            // This could be enhanced with visual indicators
            console.log('Password strength check for:', password);
        });

        // Form validation
        document.getElementById('userForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const isEdit = <?php echo $editUser ? 'true' : 'false'; ?>;
            
            if (!isEdit && password.length < 8) {
                e.preventDefault();
                alert('Password minimal 8 karakter.');
                return false;
            }
            
            if (password && (!/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password))) {
                e.preventDefault();
                alert('Password harus mengandung huruf besar, huruf kecil, dan angka.');
                return false;
            }
        });
    </script>
</body>
</html>
