<?php
/**
 * Notifications Page
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
    } elseif ($_GET['ajax'] === 'mark_all_read') {
        $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
        $result = $stmt->execute([$_SESSION['user_id']]);
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
        
        if ($action === 'mark_read') {
            $id = (int)$_POST['id'];
            $result = markNotificationAsRead($id);
            $message = $result ? 'Notifikasi ditandai sebagai dibaca.' : 'Gagal menandai notifikasi.';
            $messageType = $result ? 'success' : 'error';
        } elseif ($action === 'mark_all_read') {
            $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
            $result = $stmt->execute([$_SESSION['user_id']]);
            $message = $result ? 'Semua notifikasi ditandai sebagai dibaca.' : 'Gagal menandai notifikasi.';
            $messageType = $result ? 'success' : 'error';
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            $result = $stmt->execute([$id, $_SESSION['user_id']]);
            $message = $result ? 'Notifikasi berhasil dihapus.' : 'Gagal menghapus notifikasi.';
            $messageType = $result ? 'success' : 'error';
        } elseif ($action === 'delete_all_read') {
            $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = TRUE");
            $result = $stmt->execute([$_SESSION['user_id']]);
            $message = $result ? 'Semua notifikasi yang sudah dibaca berhasil dihapus.' : 'Gagal menghapus notifikasi.';
            $messageType = $result ? 'success' : 'error';
        }
    }
}

// Get filter parameters
$typeFilter = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Build query conditions
$conditions = ["user_id = ?"];
$params = [$_SESSION['user_id']];

if (!empty($typeFilter)) {
    $conditions[] = "type = ?";
    $params[] = $typeFilter;
}

if ($statusFilter === 'read') {
    $conditions[] = "is_read = TRUE";
} elseif ($statusFilter === 'unread') {
    $conditions[] = "is_read = FALSE";
}

$whereClause = implode(" AND ", $conditions);

// Get notifications with pagination
$page = (int)($_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT n.*, l.letter_number, l.submitter_name 
    FROM notifications n
    LEFT JOIN letters l ON n.letter_id = l.id
    WHERE $whereClause
    ORDER BY n.created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $perPage;
$params[] = $offset;
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Get total count for pagination
$countParams = array_slice($params, 0, -2); // Remove LIMIT and OFFSET params
$stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE $whereClause");
$stmt->execute($countParams);
$totalNotifications = $stmt->fetch()['total'];
$totalPages = ceil($totalNotifications / $perPage);

// Get notification statistics
$stats = [];
$stmt = $db->prepare("SELECT type, COUNT(*) as count FROM notifications WHERE user_id = ? GROUP BY type");
$stmt->execute([$_SESSION['user_id']]);
while ($row = $stmt->fetch()) {
    $stats[$row['type']] = $row['count'];
}

$stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
$stmt->execute([$_SESSION['user_id']]);
$unreadCount = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = TRUE");
$stmt->execute([$_SESSION['user_id']]);
$readCount = $stmt->fetch()['count'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .notification-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
            font-size: 1.5rem;
            font-weight: bold;
            color: #000;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #666;
            text-transform: uppercase;
        }
        
        .notification-item {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1rem;
            position: relative;
        }
        
        .notification-item.unread {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }
        
        .notification-type {
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .type-new_submission { background: #d4edda; color: #155724; }
        .type-approval { background: #d1ecf1; color: #0c5460; }
        .type-rejection { background: #f8d7da; color: #721c24; }
        .type-revision { background: #fff3cd; color: #856404; }
        
        .notification-message {
            margin-bottom: 0.5rem;
            line-height: 1.5;
        }
        
        .notification-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
            color: #666;
        }
        
        .notification-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .filter-form {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .pagination a,
        .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid #e0e0e0;
            text-decoration: none;
            color: #333;
        }
        
        .pagination .current {
            background: #000;
            color: #fff;
            border-color: #000;
        }
        
        .bulk-actions {
            background: #e9ecef;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
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
                    <a href="users.php">Kelola User</a>
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
                <p>Kelola dan pantau semua notifikasi sistem.</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="notification-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $unreadCount; ?></div>
                <div class="stat-label">Belum Dibaca</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $readCount; ?></div>
                <div class="stat-label">Sudah Dibaca</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['new_submission'] ?? 0; ?></div>
                <div class="stat-label">Pengajuan Baru</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['approval'] ?? 0; ?></div>
                <div class="stat-label">Persetujuan</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['rejection'] ?? 0; ?></div>
                <div class="stat-label">Penolakan</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['revision'] ?? 0; ?></div>
                <div class="stat-label">Revisi</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-form">
            <h3>Filter Notifikasi</h3>
            <form method="GET" action="notifications.php">
                <div class="filter-row">
                    <div class="form-group">
                        <label for="type">Jenis Notifikasi</label>
                        <select id="type" name="type">
                            <option value="">-- Semua Jenis --</option>
                            <option value="new_submission" <?php echo $typeFilter === 'new_submission' ? 'selected' : ''; ?>>
                                Pengajuan Baru
                            </option>
                            <option value="approval" <?php echo $typeFilter === 'approval' ? 'selected' : ''; ?>>
                                Persetujuan
                            </option>
                            <option value="rejection" <?php echo $typeFilter === 'rejection' ? 'selected' : ''; ?>>
                                Penolakan
                            </option>
                            <option value="revision" <?php echo $typeFilter === 'revision' ? 'selected' : ''; ?>>
                                Revisi
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status Baca</label>
                        <select id="status" name="status">
                            <option value="">-- Semua Status --</option>
                            <option value="unread" <?php echo $statusFilter === 'unread' ? 'selected' : ''; ?>>
                                Belum Dibaca
                            </option>
                            <option value="read" <?php echo $statusFilter === 'read' ? 'selected' : ''; ?>>
                                Sudah Dibaca
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">Filter</button>
                        <a href="notifications.php" class="btn btn-outline">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Bulk Actions -->
        <?php if (!empty($notifications)): ?>
            <div class="bulk-actions">
                <h4>Aksi Massal</h4>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <button onclick="markAllAsRead()" class="btn btn-outline">
                        Tandai Semua Sebagai Dibaca
                    </button>
                    <button onclick="deleteAllRead()" class="btn btn-outline btn-danger">
                        Hapus Semua yang Sudah Dibaca
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Notifications List -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Daftar Notifikasi 
                    (<?php echo $totalNotifications; ?> total, halaman <?php echo $page; ?> dari <?php echo $totalPages; ?>)
                </h3>
            </div>
            
            <?php if (empty($notifications)): ?>
                <div class="text-center p-3">
                    <p>Tidak ada notifikasi yang ditemukan.</p>
                </div>
            <?php else: ?>
                <div class="notifications-list">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                            <div class="notification-header">
                                <span class="notification-type type-<?php echo $notification['type']; ?>">
                                    <?php echo NOTIFICATION_TYPES[$notification['type']] ?? $notification['type']; ?>
                                </span>
                                <div class="notification-actions">
                                    <?php if (!$notification['is_read']): ?>
                                        <button onclick="markAsRead(<?php echo $notification['id']; ?>)" 
                                                class="btn btn-small">Tandai Dibaca</button>
                                    <?php endif; ?>
                                    <button onclick="deleteNotification(<?php echo $notification['id']; ?>)" 
                                            class="btn btn-small btn-danger">Hapus</button>
                                </div>
                            </div>
                            
                            <div class="notification-message">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </div>
                            
                            <div class="notification-meta">
                                <div>
                                    <?php if ($notification['letter_number']): ?>
                                        <strong>Surat:</strong> <?php echo htmlspecialchars($notification['letter_number']); ?>
                                        <?php if ($notification['submitter_name']): ?>
                                            - <?php echo htmlspecialchars($notification['submitter_name']); ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php echo formatDate($notification['created_at'], 'd M Y H:i'); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter(['type' => $typeFilter, 'status' => $statusFilter])); ?>">
                        ← Sebelumnya
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter(['type' => $typeFilter, 'status' => $statusFilter])); ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter(['type' => $typeFilter, 'status' => $statusFilter])); ?>">
                        Selanjutnya →
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Hidden Forms -->
    <form id="markReadForm" method="POST" action="notifications.php" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="mark_read">
        <input type="hidden" name="id" id="markReadId">
    </form>

    <form id="deleteForm" method="POST" action="notifications.php" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <form id="markAllReadForm" method="POST" action="notifications.php" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="mark_all_read">
    </form>

    <form id="deleteAllReadForm" method="POST" action="notifications.php" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="delete_all_read">
    </form>

    <script>
        function markAsRead(id) {
            document.getElementById('markReadId').value = id;
            document.getElementById('markReadForm').submit();
        }

        function deleteNotification(id) {
            if (confirm('Apakah Anda yakin ingin menghapus notifikasi ini?')) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        function markAllAsRead() {
            if (confirm('Apakah Anda yakin ingin menandai semua notifikasi sebagai dibaca?')) {
                document.getElementById('markAllReadForm').submit();
            }
        }

        function deleteAllRead() {
            if (confirm('Apakah Anda yakin ingin menghapus semua notifikasi yang sudah dibaca?\n\nTindakan ini tidak dapat dibatalkan.')) {
                document.getElementById('deleteAllReadForm').submit();
            }
        }

        // Auto-refresh unread count every 30 seconds
        setInterval(function() {
            fetch('notifications.php?ajax=count')
                .then(response => response.json())
                .then(data => {
                    // Update any notification badges in navigation
                    const badges = document.querySelectorAll('.badge');
                    badges.forEach(badge => {
                        if (data.count > 0) {
                            badge.textContent = data.count;
                            badge.style.display = 'inline';
                        } else {
                            badge.style.display = 'none';
                        }
                    });
                })
                .catch(error => console.error('Error fetching notification count:', error));
        }, 30000);

        // Mark notification as read when clicked
        document.querySelectorAll('.notification-item.unread').forEach(item => {
            item.addEventListener('click', function(e) {
                if (e.target.tagName !== 'BUTTON') {
                    const id = this.dataset.id;
                    if (id) {
                        fetch(`notifications.php?ajax=mark_read&id=${id}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    this.classList.remove('unread');
                                }
                            });
                    }
                }
            });
        });
    </script>
</body>
</html>
