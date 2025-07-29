<?php
/**
 * Letter Detail Page
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

// Get letter ID
$letterId = (int)($_GET['id'] ?? 0);
if (!$letterId) {
    header('Location: letters.php');
    exit;
}

// Get letter details
$letter = getLetterById($letterId);
if (!$letter) {
    header('Location: letters.php');
    exit;
}

// Get letter data
$letterData = json_decode($letter['letter_data'], true);

// Get approval history
$stmt = $db->prepare("
    SELECT 
        'manager' as level,
        manager_action_at as action_at,
        manager_notes as notes,
        u.full_name as actor_name
    FROM letters l
    LEFT JOIN users u ON l.manager_action_by = u.id
    WHERE l.id = ? AND l.manager_action_at IS NOT NULL
    
    UNION ALL
    
    SELECT 
        'director' as level,
        director_action_at as action_at,
        director_notes as notes,
        u.full_name as actor_name
    FROM letters l
    LEFT JOIN users u ON l.director_action_by = u.id
    WHERE l.id = ? AND l.director_action_at IS NOT NULL
    
    ORDER BY action_at ASC
");
$stmt->execute([$letterId, $letterId]);
$approvalHistory = $stmt->fetchAll();

// Get related notifications
$stmt = $db->prepare("
    SELECT n.*, u.full_name as recipient_name
    FROM notifications n
    LEFT JOIN users u ON n.user_id = u.id
    WHERE n.letter_id = ?
    ORDER BY n.created_at DESC
");
$stmt->execute([$letterId]);
$notifications = $stmt->fetchAll();

// Generate letter preview with signatures
function generateLetterPreview($letter, $letterData) {
    $content = processLetterTemplate($letter['template_content'], $letter['letter_data']);
    
    // Add signatures if approved
    if ($letter['status'] === 'director_approved') {
        $content .= '<div class="signature-section">';
        
        if ($letter['manager_action_by']) {
            $db = getDB();
            $stmt = $db->prepare("SELECT full_name, digital_signature_path FROM users WHERE id = ?");
            $stmt->execute([$letter['manager_action_by']]);
            $manager = $stmt->fetch();
            
            if ($manager && $manager['digital_signature_path']) {
                $content .= '<div class="signature-box">';
                $content .= '<p>Mengetahui,<br>Manager</p>';
                $content .= '<img src="../assets/uploads/signatures/' . htmlspecialchars($manager['digital_signature_path']) . '" class="signature-image" alt="Tanda Tangan Manager">';
                $content .= '<p><strong>' . htmlspecialchars($manager['full_name']) . '</strong></p>';
                $content .= '</div>';
            }
        }
        
        if ($letter['director_action_by']) {
            $stmt = $db->prepare("SELECT full_name, digital_signature_path FROM users WHERE id = ?");
            $stmt->execute([$letter['director_action_by']]);
            $director = $stmt->fetch();
            
            if ($director && $director['digital_signature_path']) {
                $content .= '<div class="signature-box">';
                $content .= '<p>Menyetujui,<br>Direktur</p>';
                $content .= '<img src="../assets/uploads/signatures/' . htmlspecialchars($director['digital_signature_path']) . '" class="signature-image" alt="Tanda Tangan Direktur">';
                $content .= '<p><strong>' . htmlspecialchars($director['full_name']) . '</strong></p>';
                $content .= '</div>';
            }
        }
        
        $content .= '</div>';
    }
    
    return $content;
}

$letterPreview = generateLetterPreview($letter, $letterData);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Surat <?php echo htmlspecialchars($letter['letter_number']); ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .letter-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .info-section {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 1.5rem;
        }
        
        .info-section h3 {
            margin-bottom: 1rem;
            color: #000;
            border-bottom: 2px solid #000;
            padding-bottom: 0.5rem;
        }
        
        .info-table {
            width: 100%;
        }
        
        .info-table td {
            padding: 0.5rem 0;
            vertical-align: top;
        }
        
        .info-table td:first-child {
            font-weight: bold;
            width: 40%;
        }
        
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 1rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e0e0e0;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 2rem;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 1rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -2.5rem;
            top: 1rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: #28a745;
            border: 3px solid #fff;
        }
        
        .timeline-item.pending::before {
            background: #ffc107;
        }
        
        .timeline-item.rejected::before {
            background: #dc3545;
        }
        
        .letter-preview {
            background: #fff;
            border: 2px solid #000;
            padding: 2rem;
            margin: 2rem 0;
            font-family: 'Times New Roman', serif;
            line-height: 1.8;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-around;
            margin-top: 3rem;
            flex-wrap: wrap;
            gap: 2rem;
        }
        
        .signature-box {
            text-align: center;
            min-width: 200px;
        }
        
        .signature-image {
            max-width: 150px;
            max-height: 75px;
            margin: 1rem 0;
            border: 1px solid #ddd;
        }
        
        .notification-item {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin: 2rem 0;
            flex-wrap: wrap;
        }
        
        @media print {
            .action-buttons,
            header,
            .info-section,
            .timeline,
            .notification-item {
                display: none !important;
            }
            
            .letter-preview {
                border: none;
                margin: 0;
                padding: 0;
            }
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
                    <a href="notifications.php">Notifikasi</a>
                    <a href="logout.php">Logout</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="row">
            <div class="col-12">
                <h1>Detail Surat: <?php echo htmlspecialchars($letter['letter_number']); ?></h1>
                <p>Informasi lengkap tentang surat yang diajukan.</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="letters.php" class="btn btn-outline">← Kembali ke Monitoring</a>
            <button onclick="window.print()" class="btn btn-outline">Print Surat</button>
            <a href="../public/track.php?letter_id=<?php echo $letter['id']; ?>" 
               class="btn btn-outline" target="_blank">Lihat Tracking Publik</a>
            <?php if ($letter['status'] === 'director_approved'): ?>
                <button onclick="downloadPDF()" class="btn">Download PDF</button>
            <?php endif; ?>
        </div>

        <!-- Letter Information -->
        <div class="letter-info">
            <!-- Basic Information -->
            <div class="info-section">
                <h3>Informasi Dasar</h3>
                <table class="info-table">
                    <tr>
                        <td>Nomor Surat</td>
                        <td><?php echo htmlspecialchars($letter['letter_number']); ?></td>
                    </tr>
                    <tr>
                        <td>Jenis Surat</td>
                        <td><?php echo htmlspecialchars($letter['template_name']); ?></td>
                    </tr>
                    <tr>
                        <td>Status</td>
                        <td><?php echo getStatusBadge($letter['status']); ?></td>
                    </tr>
                    <tr>
                        <td>Tanggal Pengajuan</td>
                        <td><?php echo formatDate($letter['created_at']); ?></td>
                    </tr>
                    <tr>
                        <td>Terakhir Diupdate</td>
                        <td><?php echo formatDate($letter['updated_at']); ?></td>
                    </tr>
                </table>
            </div>

            <!-- Submitter Information -->
            <div class="info-section">
                <h3>Informasi Pemohon</h3>
                <table class="info-table">
                    <tr>
                        <td>Nama</td>
                        <td><?php echo htmlspecialchars($letter['submitter_name']); ?></td>
                    </tr>
                    <tr>
                        <td>Email</td>
                        <td><?php echo htmlspecialchars($letter['submitter_email']); ?></td>
                    </tr>
                    <tr>
                        <td>Telepon</td>
                        <td><?php echo htmlspecialchars($letter['submitter_phone'] ?: '-'); ?></td>
                    </tr>
                </table>
            </div>

            <!-- Letter Data -->
            <div class="info-section">
                <h3>Data Surat</h3>
                <table class="info-table">
                    <?php foreach ($letterData as $key => $value): ?>
                        <tr>
                            <td><?php echo ucfirst(str_replace('_', ' ', $key)); ?></td>
                            <td><?php echo htmlspecialchars($value); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <!-- Approval Timeline -->
        <?php if (!empty($approvalHistory)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Timeline Persetujuan</h3>
                </div>
                <div class="timeline">
                    <div class="timeline-item">
                        <h4>Surat Diajukan</h4>
                        <p>Surat berhasil diajukan ke sistem</p>
                        <small><?php echo formatDate($letter['created_at']); ?></small>
                    </div>
                    
                    <?php foreach ($approvalHistory as $history): ?>
                        <div class="timeline-item <?php echo $letter['status'] === 'rejected' ? 'rejected' : ''; ?>">
                            <h4>
                                <?php echo $history['level'] === 'manager' ? 'Review Manager' : 'Review Direktur'; ?>
                            </h4>
                            <p>
                                <strong><?php echo htmlspecialchars($history['actor_name']); ?></strong>
                                <?php if ($history['notes']): ?>
                                    <br>Catatan: <?php echo htmlspecialchars($history['notes']); ?>
                                <?php endif; ?>
                            </p>
                            <small><?php echo formatDate($history['action_at']); ?></small>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if ($letter['status'] === 'director_approved'): ?>
                        <div class="timeline-item">
                            <h4>Surat Terbit</h4>
                            <p>Surat telah disetujui dan dapat diunduh</p>
                            <small><?php echo formatDate($letter['director_action_at']); ?></small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Letter Preview -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Preview Surat</h3>
            </div>
            <div class="letter-preview">
                <?php echo $letterPreview; ?>
            </div>
        </div>

        <!-- Notifications History -->
        <?php if (!empty($notifications)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Riwayat Notifikasi</h3>
                </div>
                <div class="notifications-list">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item">
                            <div class="notification-header">
                                <span class="notification-type type-<?php echo $notification['type']; ?>">
                                    <?php echo NOTIFICATION_TYPES[$notification['type']] ?? $notification['type']; ?>
                                </span>
                                <small><?php echo formatDate($notification['created_at'], 'd M Y H:i'); ?></small>
                            </div>
                            <div class="notification-message">
                                <strong>Kepada:</strong> <?php echo htmlspecialchars($notification['recipient_name']); ?><br>
                                <strong>Pesan:</strong> <?php echo htmlspecialchars($notification['message']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function downloadPDF() {
            // This would integrate with a PDF generation library
            alert('Fitur download PDF akan diimplementasi dengan library PDF seperti TCPDF atau mPDF');
        }

        // Auto-refresh status every 30 seconds
        setInterval(function() {
            fetch(`letter_detail.php?id=<?php echo $letterId; ?>&ajax=status`)
                .then(response => response.json())
                .then(data => {
                    if (data.status !== '<?php echo $letter['status']; ?>') {
                        location.reload();
                    }
                })
                .catch(error => console.error('Error checking status:', error));
        }, 30000);
    </script>
</body>
</html>
