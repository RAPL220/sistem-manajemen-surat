<?php
/**
 * Manager Notifications Page
 * Sistem Manajemen Surat
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require manager login
requireLogin('manager');

$db = getDB();
$message = '';
$messageType = '';

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'count') {
        $count = getUnreadNotificationsCount($_SESSION['user_id']);
        echo json_encode(['count' => $count]);
        exit;
    } elseif ($_GET['ajax'] === 'mark_read' && isset($_GET['id'])) {
        $result = markNotificationAsRead((int)$_GET['id']);
        echo json_encode(['success' => $result]);
        exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token keamanan tidak valid.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'mark_all_read') {
            $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
            $result = $stmt->execute([$_SESSION['user_id']]);
            $message = $result ? 'Semua notifikasi ditandai sebagai dibaca.' : 'Gagal menandai notifikasi.';
            $messageType = $result ? 'success' : 'error';
        }
    }
}

// Get notifications
$notifications = getUserNotifications($_SESSION['user_id'], 50);
$unreadCount = getUnreadNotificationsCount($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="dashboard.php" class="logo"><?php echo APP_NAME; ?></a>
                <nav>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="review.php">Review Surat</a>
                    <a href="signature.php">Tanda Tangan Digital</a>
                    <a href="notifications.php" class="active">Notifikasi</a>
                    <a href="logout.php">Logout</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="row">
            <div class="col-12">
                <h1>Notifikasi</h1>
                <p>Pantau semua notifikasi terkait surat yang perlu Anda review.</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-3">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Ringkasan</h3>
                    </div>
                    <div class="card-content">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $unreadCount; ?></div>
                            <div class="stat-label">Belum Dibaca</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo count($notifications); ?></div>
                            <div class="stat-label">Total Notifikasi</div>
                        </div>
                        
                        <?php if ($unreadCount > 0): ?>
                            <form method="POST" action="notifications.php">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="mark_all_read">
                                <button type="submit" class="btn btn-outline">
                                    Tandai Semua Dibaca
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-9">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Daftar Notifikasi</h3>
                    </div>
                    
                    <?php if (empty($notifications)): ?>
                        <div class="text-center p-3">
                            <p>Tidak ada notifikasi.</p>
                        </div>
                    <?php else: ?>
                        <div class="notifications-list">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                                    <div class="notification-message">
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </div>
                                    <div class="notification-meta">
                                        <?php echo formatDate($notification['created_at'], 'd M Y H:i'); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
