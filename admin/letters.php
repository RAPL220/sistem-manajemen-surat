<?php
/**
 * Letters Monitoring Page
 * Sistem Manajemen Surat - Admin Only
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
requireLogin('admin');

$db = getDB();

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$templateFilter = $_GET['template_id'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build filters array
$filters = [];
if (!empty($statusFilter)) $filters['status'] = $statusFilter;
if (!empty($templateFilter)) $filters['template_id'] = $templateFilter;
if (!empty($dateFrom)) $filters['date_from'] = $dateFrom;
if (!empty($dateTo)) $filters['date_to'] = $dateTo;

// Get letters with filters
$letters = getLetters($filters);

// Get all templates for filter dropdown
$templates = getAllTemplates();

// Get statistics
$stats = [];
$stmt = $db->query("SELECT status, COUNT(*) as count FROM letters GROUP BY status");
while ($row = $stmt->fetch()) {
    $stats[$row['status']] = $row['count'];
}

// Get monthly statistics
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

// Unread notifications count
$unreadCount = getUnreadNotificationsCount($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Surat - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: #fff;
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
        
        .letter-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .export-buttons {
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
                    <a href="letters.php" class="active">Monitoring Surat</a>
                    <a href="templates.php">Template Surat</a>
                    <a href="users.php">Kelola User</a>
                    <a href="notifications.php">
                        Notifikasi 
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge badge-danger"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="logout.php">Logout</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="row">
            <div class="col-12">
                <h1>Monitoring Surat</h1>
                <p>Monitor dan kelola semua surat yang masuk ke sistem.</p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['manager_approved'] ?? 0; ?></div>
                <div class="stat-label">Manager Approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['director_approved'] ?? 0; ?></div>
                <div class="stat-label">Director Approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['rejected'] ?? 0; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['revision'] ?? 0; ?></div>
                <div class="stat-label">Revision</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($letters); ?></div>
                <div class="stat-label">Total (Filtered)</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-form">
            <h3>Filter Surat</h3>
            <form method="GET" action="letters.php">
                <div class="filter-row">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">-- Semua Status --</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>
                                Pending
                            </option>
                            <option value="manager_approved" <?php echo $statusFilter === 'manager_approved' ? 'selected' : ''; ?>>
                                Manager Approved
                            </option>
                            <option value="director_approved" <?php echo $statusFilter === 'director_approved' ? 'selected' : ''; ?>>
                                Director Approved
                            </option>
                            <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>
                                Rejected
                            </option>
                            <option value="revision" <?php echo $statusFilter === 'revision' ? 'selected' : ''; ?>>
                                Revision
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="template_id">Jenis Surat</label>
                        <select id="template_id" name="template_id">
                            <option value="">-- Semua Jenis --</option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?php echo $template['id']; ?>" 
                                        <?php echo $templateFilter == $template['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($template['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_from">Dari Tanggal</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to">Sampai Tanggal</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">Filter</button>
                        <a href="letters.php" class="btn btn-outline">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Export Buttons -->
        <div class="export-buttons">
            <button onclick="exportToCSV()" class="btn btn-outline">Export CSV</button>
            <button onclick="printReport()" class="btn btn-outline">Print Report</button>
        </div>

        <!-- Letters Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Daftar Surat 
                    <?php if (!empty($filters)): ?>
                        (<?php echo count($letters); ?> hasil)
                    <?php endif; ?>
                </h3>
            </div>
            
            <?php if (empty($letters)): ?>
                <div class="text-center p-3">
                    <p>Tidak ada surat yang ditemukan.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="table" id="lettersTable">
                        <thead>
                            <tr>
                                <th>Nomor Surat</th>
                                <th>Pemohon</th>
                                <th>Jenis Surat</th>
                                <th>Status</th>
                                <th>Tanggal Pengajuan</th>
                                <th>Manager</th>
                                <th>Direktur</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($letters as $letter): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($letter['letter_number']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($letter['submitter_name']); ?><br>
                                        <small><?php echo htmlspecialchars($letter['submitter_email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($letter['template_name']); ?></td>
                                    <td><?php echo getStatusBadge($letter['status']); ?></td>
                                    <td><?php echo formatDate($letter['created_at'], 'd M Y H:i'); ?></td>
                                    <td>
                                        <?php if ($letter['manager_action_at']): ?>
                                            <small><?php echo formatDate($letter['manager_action_at'], 'd M Y'); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($letter['director_action_at']): ?>
                                            <small><?php echo formatDate($letter['director_action_at'], 'd M Y'); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="letter-actions">
                                            <a href="letter_detail.php?id=<?php echo $letter['id']; ?>" 
                                               class="btn btn-small">Detail</a>
                                            
                                            <?php if ($letter['status'] === 'director_approved'): ?>
                                                <a href="../public/track.php?letter_id=<?php echo $letter['id']; ?>" 
                                                   class="btn btn-small btn-success" target="_blank">Lihat Surat</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Monthly Chart -->
        <?php if (!empty($monthlyStats)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Statistik Bulanan (6 Bulan Terakhir)</h3>
                </div>
                <div class="chart-container" style="height: 300px; padding: 20px;">
                    <canvas id="monthlyChart" width="800" height="300"></canvas>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Export to CSV
        function exportToCSV() {
            const table = document.getElementById('lettersTable');
            const rows = table.querySelectorAll('tr');
            let csv = [];
            
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const cols = row.querySelectorAll('td, th');
                let csvRow = [];
                
                for (let j = 0; j < cols.length - 1; j++) { // Skip last column (actions)
                    csvRow.push('"' + cols[j].textContent.trim().replace(/"/g, '""') + '"');
                }
                csv.push(csvRow.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'monitoring-surat-' + new Date().toISOString().split('T')[0] + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Print report
        function printReport() {
            window.print();
        }

        // Monthly chart
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

        // Auto-refresh every 30 seconds
        setInterval(function() {
            // Only refresh if no filters are applied
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.toString()) {
                location.reload();
            }
        }, 30000);
    </script>

    <style media="print">
        header, .export-buttons, .letter-actions, .btn {
            display: none !important;
        }
        
        .container {
            max-width: none;
            margin: 0;
            padding: 0;
        }
        
        .card {
            border: none;
            box-shadow: none;
        }
        
        .table {
            font-size: 12px;
        }
        
        .badge {
            border: 1px solid #000;
        }
    </style>
</body>
</html>
