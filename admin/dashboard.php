<?php
/**
 * Admin Dashboard
 * Sistem Manajemen Surat
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
requireLogin('admin');

$db = getDB();

// Get statistics
$stats = [];

// Total letters
$stmt = $db->query("SELECT COUNT(*) as count FROM letters");
$stats['total_letters'] = $stmt->fetch()['count'];

// Pending letters (waiting for manager)
$stmt = $db->query("SELECT COUNT(*) as count FROM letters WHERE status = 'pending'");
$stats['pending_letters'] = $stmt->fetch()['count'];

// Manager approved (waiting for director)
$stmt = $db->query("SELECT COUNT(*) as count FROM letters WHERE status = 'manager_approved'");
$stats['manager_approved'] = $stmt->fetch()['count'];

// Approved letters
$stmt = $db->query("SELECT COUNT(*) as count FROM letters WHERE status = 'director_approved'");
$stats['approved_letters'] = $stmt->fetch()['count'];

// Rejected letters
$stmt = $db->query("SELECT COUNT(*) as count FROM letters WHERE status = 'rejected'");
$stats['rejected_letters'] = $stmt->fetch()['count'];

// Revision letters
$stmt = $db->query("SELECT COUNT(*) as count FROM letters WHERE status = 'revision'");
$stats['revision_letters'] = $stmt->fetch()['count'];

// Recent letters
$stmt = $db->query("
    SELECT l.*, t.name as template_name 
    FROM letters l 
    LEFT JOIN templates t ON l.template_id = t.id 
    ORDER BY l.created_at DESC 
    LIMIT 10
");
$recentLetters = $stmt->fetchAll();

// Recent notifications
$notifications = getUserNotifications($_SESSION['user_id'], 5);

// Unread notifications count
$unreadCount = getUnreadNotificationsCount($_SESSION['user_id']);

// Monthly statistics for chart
$stmt = $db->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as count
    FROM letters 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");
$monthlyStats = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrator - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="dashboard.php" class="logo"><?php echo APP_NAME; ?></a>
                <nav>
                    <a href="dashboard.php" class="active">Dashboard</a>
                    <a href="letters.php">Monitoring Surat</a>
                    <a href="templates.php">Template Surat</a>
                    <a href="users.php">Kelola User</a>
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
                <h1>Dashboard Administrator</h1>
                <p>Selamat datang, <?php echo htmlspecialchars($_SESSION['full_name']); ?>. Berikut adalah ringkasan sistem manajemen surat.</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_letters']; ?></div>
                <div class="stat-label">Total Surat</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending_letters']; ?></div>
                <div class="stat-label">Menunggu Manager</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['manager_approved']; ?></div>
                <div class="stat-label">Menunggu Direktur</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['approved_letters']; ?></div>
                <div class="stat-label">Disetujui</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['rejected_letters']; ?></div>
                <div class="stat-label">Ditolak</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['revision_letters']; ?></div>
                <div class="stat-label">Perlu Revisi</div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Letters -->
            <div class="col-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Surat Terbaru</h3>
                        <a href="letters.php" class="btn btn-small">Lihat Semua</a>
                    </div>
                    
                    <?php if (empty($recentLetters)): ?>
                        <div class="text-center p-3">
                            <p>Belum ada surat yang diajukan.</p>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nomor Surat</th>
                                    <th>Pemohon</th>
                                    <th>Jenis Surat</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentLetters as $letter): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($letter['letter_number']); ?></td>
                                        <td><?php echo htmlspecialchars($letter['submitter_name']); ?></td>
                                        <td><?php echo htmlspecialchars($letter['template_name']); ?></td>
                                        <td><?php echo getStatusBadge($letter['status']); ?></td>
                                        <td><?php echo formatDate($letter['created_at'], 'd M Y'); ?></td>
                                        <td>
                                            <a href="letter_detail.php?id=<?php echo $letter['id']; ?>" 
                                               class="btn btn-small">Detail</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Notifications -->
            <div class="col-4">
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
                            <a href="templates.php" class="btn btn-outline">Kelola Template</a>
                        </div>
                        <div class="mb-2">
                            <a href="users.php" class="btn btn-outline">Kelola User</a>
                        </div>
                        <div class="mb-2">
                            <a href="letters.php?status=pending" class="btn btn-outline">
                                Surat Pending (<?php echo $stats['pending_letters']; ?>)
                            </a>
                        </div>
                        <div class="mb-2">
                            <a href="../public/index.php" class="btn btn-outline" target="_blank">
                                Lihat Form Publik
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Chart -->
        <?php if (!empty($monthlyStats)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Statistik Bulanan (6 Bulan Terakhir)</h3>
                        </div>
                        <div class="chart-container" style="height: 300px; padding: 20px;">
                            <canvas id="monthlyChart" width="800" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Simple chart implementation
        <?php if (!empty($monthlyStats)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const canvas = document.getElementById('monthlyChart');
            const ctx = canvas.getContext('2d');
            
            const data = <?php echo json_encode($monthlyStats); ?>;
            const maxValue = Math.max(...data.map(d => d.count));
            const padding = 40;
            const chartWidth = canvas.width - (padding * 2);
            const chartHeight = canvas.height - (padding * 2);
            
            // Clear canvas
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            // Draw axes
            ctx.strokeStyle = '#000';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(padding, padding);
            ctx.lineTo(padding, canvas.height - padding);
            ctx.lineTo(canvas.width - padding, canvas.height - padding);
            ctx.stroke();
            
            // Draw bars
            const barWidth = chartWidth / data.length * 0.8;
            const barSpacing = chartWidth / data.length * 0.2;
            
            data.forEach((item, index) => {
                const barHeight = (item.count / maxValue) * chartHeight;
                const x = padding + (index * (barWidth + barSpacing)) + (barSpacing / 2);
                const y = canvas.height - padding - barHeight;
                
                // Draw bar
                ctx.fillStyle = '#000';
                ctx.fillRect(x, y, barWidth, barHeight);
                
                // Draw label
                ctx.fillStyle = '#666';
                ctx.font = '12px Arial';
                ctx.textAlign = 'center';
                ctx.fillText(item.month, x + (barWidth / 2), canvas.height - padding + 20);
                
                // Draw value
                ctx.fillStyle = '#000';
                ctx.fillText(item.count, x + (barWidth / 2), y - 5);
            });
        });
        <?php endif; ?>

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
    </script>
</body>
</html>
