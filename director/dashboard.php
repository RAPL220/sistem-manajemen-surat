<?php
/**
 * Director Dashboard
 * Sistem Manajemen Surat
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require director login
requireLogin('director');

$db = getDB();

// Get statistics
$stats = [];

// Letters waiting for director approval
$stmt = $db->query("SELECT COUNT(*) as count FROM letters WHERE status = 'manager_approved'");
$stats['pending_letters'] = $stmt->fetch()['count'];

// Letters approved by director
$stmt = $db->prepare("SELECT COUNT(*) as count FROM letters WHERE status = 'director_approved' AND director_action_by = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats['approved_by_me'] = $stmt->fetch()['count'];

// Total letters processed by this director
$stmt = $db->prepare("SELECT COUNT(*) as count FROM letters WHERE director_action_by = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats['total_processed'] = $stmt->fetch()['count'];

// Letters rejected by director
$stmt = $db->prepare("SELECT COUNT(*) as count FROM letters WHERE status = 'rejected' AND director_action_by = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats['rejected_by_me'] = $stmt->fetch()['count'];

// Recent pending letters (approved by manager, waiting for director)
$stmt = $db->query("
    SELECT l.*, t.name as template_name, u.full_name as manager_name
    FROM letters l 
    LEFT JOIN templates t ON l.template_id = t.id 
    LEFT JOIN users u ON l.manager_action_by = u.id
    WHERE l.status = 'manager_approved'
    ORDER BY l.manager_action_at ASC 
    LIMIT 10
");
$pendingLetters = $stmt->fetchAll();

// Recent notifications
$notifications = getUserNotifications($_SESSION['user_id'], 5);

// Unread notifications count
$unreadCount = getUnreadNotificationsCount($_SESSION['user_id']);

// Check if director has digital signature
$currentUser = getCurrentUser();
$hasSignature = !empty($currentUser['digital_signature_path']) && file_exists(SIGNATURE_DIR . $currentUser['digital_signature_path']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Direktur - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="dashboard.php" class="logo"><?php echo APP_NAME; ?></a>
                <nav>
                    <a href="dashboard.php" class="active">Dashboard</a>
                    <a href="review.php">Review Surat</a>
                    <a href="signature.php">Tanda Tangan Digital</a>
                    <a href="notifications.php">
                        Notifikasi 
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge badge-danger"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['full_name']); ?>)</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="row">
            <div class="col-12">
                <h1>Dashboard Direktur</h1>
                <p>Selamat datang, <?php echo htmlspecialchars($_SESSION['full_name']); ?>. Berikut adalah ringkasan surat yang perlu persetujuan final.</p>
            </div>
        </div>

        <?php if (!$hasSignature): ?>
            <div class="alert alert-warning">
                <h4>Tanda Tangan Digital Belum Diatur</h4>
                <p>Anda belum mengupload tanda tangan digital. Silakan upload tanda tangan untuk dapat menyetujui surat.</p>
                <a href="signature.php" class="btn btn-warning">Upload Tanda Tangan</a>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending_letters']; ?></div>
                <div class="stat-label">Menunggu Persetujuan</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['approved_by_me']; ?></div>
                <div class="stat-label">Disetujui Saya</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_processed']; ?></div>
                <div class="stat-label">Total Diproses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['rejected_by_me']; ?></div>
                <div class="stat-label">Ditolak Saya</div>
            </div>
        </div>

        <div class="row">
            <!-- Pending Letters -->
            <div class="col-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Surat Menunggu Persetujuan Final</h3>
                        <a href="review.php" class="btn btn-small">Lihat Semua</a>
                    </div>
                    
                    <?php if (empty($pendingLetters)): ?>
                        <div class="text-center p-3">
                            <p>Tidak ada surat yang menunggu persetujuan.</p>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nomor Surat</th>
                                    <th>Pemohon</th>
                                    <th>Jenis Surat</th>
                                    <th>Disetujui Manager</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingLetters as $letter): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($letter['letter_number']); ?></td>
                                        <td><?php echo htmlspecialchars($letter['submitter_name']); ?></td>
                                        <td><?php echo htmlspecialchars($letter['template_name']); ?></td>
                                        <td><?php echo htmlspecialchars($letter['manager_name']); ?></td>
                                        <td><?php echo formatDate($letter['manager_action_at'], 'd M Y'); ?></td>
                                        <td>
                                            <a href="review.php?id=<?php echo $letter['id']; ?>" 
                                               class="btn btn-small btn-success">Review</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-4">
                <!-- Recent Notifications -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Notifikasi Terbaru</h3>
                        <a href="notifications.php" class="btn btn-small">Lihat Semua</a>
                    </div>
                    
                    <?php if (empty($notifications)): ?>
                        <div class="text-center p-3">
                            <p>Tidak ada notifikasi.</p>
                        </div>
                    <?php else: ?>
                        <div class="notification-list">
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

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Aksi Cepat</h3>
                    </div>
                    <div class="card-content">
                        <div class="mb-2">
                            <a href="review.php" class="btn btn-outline">
                                Review Surat (<?php echo $stats['pending_letters']; ?>)
                            </a>
                        </div>
                        <div class="mb-2">
                            <a href="signature.php" class="btn btn-outline">
                                <?php echo $hasSignature ? 'Update' : 'Upload'; ?> Tanda Tangan
                            </a>
                        </div>
                        <div class="mb-2">
                            <a href="../public/track.php" class="btn btn-outline" target="_blank">
                                Lihat Tracking Publik
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Signature Status -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Status Tanda Tangan</h3>
                    </div>
                    <div class="card-content">
                        <?php if ($hasSignature): ?>
                            <div class="alert alert-success">
                                <p><strong>Tanda tangan digital aktif</strong></p>
                                <img src="../assets/uploads/signatures/<?php echo htmlspecialchars($currentUser['digital_signature_path']); ?>" 
                                     alt="Tanda Tangan" style="max-width: 150px; max-height: 75px; border: 1px solid #ddd;">
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <p><strong>Tanda tangan belum diatur</strong></p>
                                <p>Upload tanda tangan digital untuk dapat menyetujui surat.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Workflow Info -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Alur Persetujuan</h3>
                    </div>
                    <div class="card-content">
                        <div class="workflow-steps">
                            <div class="step">
                                <span class="step-number">1</span>
                                <span class="step-text">Pengajuan oleh Pemohon</span>
                            </div>
                            <div class="step">
                                <span class="step-number">2</span>
                                <span class="step-text">Review & Persetujuan Manager</span>
                            </div>
                            <div class="step current">
                                <span class="step-number">3</span>
                                <span class="step-text">Review & Persetujuan Direktur</span>
                            </div>
                            <div class="step">
                                <span class="step-number">4</span>
                                <span class="step-text">Surat Terbit</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Aktivitas Terbaru</h3>
                    </div>
                    
                    <?php
                    // Get recent letters processed by this director
                    $stmt = $db->prepare("
                        SELECT l.*, t.name as template_name, u.full_name as manager_name
                        FROM letters l 
                        LEFT JOIN templates t ON l.template_id = t.id 
                        LEFT JOIN users u ON l.manager_action_by = u.id
                        WHERE l.director_action_by = ?
                        ORDER BY l.director_action_at DESC 
                        LIMIT 5
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    $recentActivity = $stmt->fetchAll();
                    ?>
                    
                    <?php if (empty($recentActivity)): ?>
                        <div class="text-center p-3">
                            <p>Belum ada aktivitas.</p>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nomor Surat</th>
                                    <th>Pemohon</th>
                                    <th>Jenis Surat</th>
                                    <th>Manager</th>
                                    <th>Status</th>
                                    <th>Tanggal Aksi</th>
                                    <th>Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentActivity as $activity): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($activity['letter_number']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['submitter_name']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['template_name']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['manager_name']); ?></td>
                                        <td><?php echo getStatusBadge($activity['status']); ?></td>
                                        <td><?php echo formatDate($activity['director_action_at'], 'd M Y H:i'); ?></td>
                                        <td><?php echo htmlspecialchars($activity['director_notes'] ?: '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <style>
        .workflow-steps {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border-radius: 4px;
        }
        
        .step.current {
            background: #f8f9fa;
            border: 1px solid #000;
        }
        
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            background: #e0e0e0;
            border-radius: 50%;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .step.current .step-number {
            background: #000;
            color: #fff;
        }
        
        .step-text {
            font-size: 0.875rem;
        }
    </style>

    <script>
        // Auto-refresh notifications every 30 seconds
        setInterval(function() {
            fetch('notifications.php?ajax=count')
                .then(response => response.json())
                .then(data => {
                    const badge = document.querySelector('nav a[href="notifications.php"] .badge');
                    if (data.count > 0) {
                        if (badge) {
                            badge.textContent = data.count;
                        } else {
                            const link = document.querySelector('nav a[href="notifications.php"]');
                            link.innerHTML += ' <span class="badge badge-danger">' + data.count + '</span>';
                        }
                    } else if (badge) {
                        badge.remove();
                    }
                })
                .catch(error => console.error('Error fetching notifications:', error));
        }, 30000);

        // Auto-refresh pending letters count
        setInterval(function() {
            fetch('dashboard.php?ajax=pending_count')
                .then(response => response.json())
                .then(data => {
                    const statNumber = document.querySelector('.dashboard-stats .stat-card:first-child .stat-number');
                    if (statNumber) {
                        statNumber.textContent = data.count;
                    }
                })
                .catch(error => console.error('Error fetching pending count:', error));
        }, 60000);
    </script>
</body>
</html>
