<?php
/**
 * Manager Letter Review Page
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

// Handle letter action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token keamanan tidak valid.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        $letterId = (int)$_POST['letter_id'];
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        if (empty($letterId)) {
            $message = 'ID surat tidak valid.';
            $messageType = 'error';
        } else {
            // Get letter details
            $letter = getLetterById($letterId);
            if (!$letter || $letter['status'] !== 'pending') {
                $message = 'Surat tidak ditemukan atau sudah diproses.';
                $messageType = 'error';
            } else {
                try {
                    $db->beginTransaction();
                    
                    if ($action === 'approve') {
                        // Check if manager has digital signature
                        $currentUser = getCurrentUser();
                        if (empty($currentUser['digital_signature_path'])) {
                            $message = 'Anda harus mengupload tanda tangan digital terlebih dahulu.';
                            $messageType = 'error';
                        } else {
                            // Update letter status to manager_approved
                            $result = updateLetterStatus($letterId, 'manager_approved', $_SESSION['user_id'], $notes);
                            
                            if ($result) {
                                // Send notification to directors
                                $directors = $db->query("SELECT id FROM users WHERE role = 'director'")->fetchAll();
                                foreach ($directors as $director) {
                                    sendNotification(
                                        $director['id'], 
                                        $letterId, 
                                        "Surat {$letter['letter_number']} telah disetujui Manager dan menunggu persetujuan Direktur", 
                                        'approval'
                                    );
                                }
                                
                                // Send notification to admin
                                $admins = $db->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
                                foreach ($admins as $admin) {
                                    sendNotification(
                                        $admin['id'], 
                                        $letterId, 
                                        "Surat {$letter['letter_number']} disetujui oleh Manager {$_SESSION['full_name']}", 
                                        'approval'
                                    );
                                }
                                
                                $message = 'Surat berhasil disetujui dan diteruskan ke Direktur.';
                                $messageType = 'success';
                                logActivity('letter_approve_manager', "Letter approved: {$letter['letter_number']}");
                            } else {
                                $message = 'Gagal menyetujui surat.';
                                $messageType = 'error';
                            }
                        }
                    } elseif ($action === 'reject') {
                        if (empty($notes)) {
                            $message = 'Alasan penolakan harus diisi.';
                            $messageType = 'error';
                        } else {
                            // Update letter status to rejected
                            $result = updateLetterStatus($letterId, 'rejected', $_SESSION['user_id'], $notes);
                            
                            if ($result) {
                                // Send notification to admin
                                $admins = $db->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
                                foreach ($admins as $admin) {
                                    sendNotification(
                                        $admin['id'], 
                                        $letterId, 
                                        "Surat {$letter['letter_number']} ditolak oleh Manager {$_SESSION['full_name']}", 
                                        'rejection'
                                    );
                                }
                                
                                $message = 'Surat berhasil ditolak.';
                                $messageType = 'success';
                                logActivity('letter_reject_manager', "Letter rejected: {$letter['letter_number']}");
                            } else {
                                $message = 'Gagal menolak surat.';
                                $messageType = 'error';
                            }
                        }
                    } elseif ($action === 'revision') {
                        if (empty($notes)) {
                            $message = 'Catatan revisi harus diisi.';
                            $messageType = 'error';
                        } else {
                            // Update letter status to revision
                            $result = updateLetterStatus($letterId, 'revision', $_SESSION['user_id'], $notes);
                            
                            if ($result) {
                                // Send notification to admin
                                $admins = $db->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
                                foreach ($admins as $admin) {
                                    sendNotification(
                                        $admin['id'], 
                                        $letterId, 
                                        "Surat {$letter['letter_number']} perlu revisi menurut Manager {$_SESSION['full_name']}", 
                                        'revision'
                                    );
                                }
                                
                                $message = 'Surat berhasil diminta untuk revisi.';
                                $messageType = 'success';
                                logActivity('letter_revision_manager', "Letter revision requested: {$letter['letter_number']}");
                            } else {
                                $message = 'Gagal meminta revisi surat.';
                                $messageType = 'error';
                            }
                        }
                    }
                    
                    $db->commit();
                } catch (Exception $e) {
                    $db->rollback();
                    error_log("Error processing letter action: " . $e->getMessage());
                    $message = 'Terjadi kesalahan sistem.';
                    $messageType = 'error';
                }
            }
        }
    }
}

// Get letter for review if ID is provided
$reviewLetter = null;
if (isset($_GET['id'])) {
    $reviewLetter = getLetterById((int)$_GET['id']);
    if (!$reviewLetter || $reviewLetter['status'] !== 'pending') {
        $reviewLetter = null;
        $message = 'Surat tidak ditemukan atau sudah diproses.';
        $messageType = 'error';
    }
}

// Get all pending letters
$pendingLetters = getLetters(['status' => 'pending']);

// Check if manager has digital signature
$currentUser = getCurrentUser();
$hasSignature = !empty($currentUser['digital_signature_path']) && file_exists(SIGNATURE_DIR . $currentUser['digital_signature_path']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Surat - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .letter-actions {
            position: sticky;
            top: 20px;
            background: #fff;
            border: 2px solid #000;
            border-radius: 8px;
            padding: 1.5rem;
        }
        
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .letter-preview {
            background: #fff;
            border: 2px solid #000;
            padding: 2rem;
            margin: 1rem 0;
            font-family: 'Times New Roman', serif;
            line-height: 1.8;
        }
        
        .signature-preview {
            max-width: 150px;
            max-height: 75px;
            border: 1px solid #ddd;
            margin: 0.5rem 0;
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
                    <a href="review.php" class="active">Review Surat</a>
                    <a href="signature.php">Tanda Tangan Digital</a>
                    <a href="notifications.php">Notifikasi</a>
                    <a href="logout.php">Logout</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="row">
            <div class="col-12">
                <h1>Review Surat</h1>
                <p>Review dan setujui surat yang diajukan oleh pemohon.</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if (!$hasSignature): ?>
            <div class="alert alert-warning">
                <h4>Tanda Tangan Digital Belum Diatur</h4>
                <p>Anda harus mengupload tanda tangan digital untuk dapat menyetujui surat.</p>
                <a href="signature.php" class="btn btn-warning">Upload Tanda Tangan</a>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Letter List -->
            <div class="col-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Surat Menunggu Review (<?php echo count($pendingLetters); ?>)</h3>
                    </div>
                    
                    <?php if (empty($pendingLetters)): ?>
                        <div class="text-center p-3">
                            <p>Tidak ada surat yang menunggu review.</p>
                        </div>
                    <?php else: ?>
                        <div class="letter-list">
                            <?php foreach ($pendingLetters as $letter): ?>
                                <div class="card mb-2 <?php echo ($reviewLetter && $reviewLetter['id'] == $letter['id']) ? 'border-primary' : ''; ?>">
                                    <div class="p-2">
                                        <h5><?php echo htmlspecialchars($letter['letter_number']); ?></h5>
                                        <p class="mb-1"><strong><?php echo htmlspecialchars($letter['submitter_name']); ?></strong></p>
                                        <p class="mb-1"><?php echo htmlspecialchars($letter['template_name']); ?></p>
                                        <p class="mb-2"><small><?php echo formatDate($letter['created_at'], 'd M Y H:i'); ?></small></p>
                                        <a href="review.php?id=<?php echo $letter['id']; ?>" 
                                           class="btn btn-small <?php echo ($reviewLetter && $reviewLetter['id'] == $letter['id']) ? 'btn-primary' : ''; ?>">
                                            <?php echo ($reviewLetter && $reviewLetter['id'] == $letter['id']) ? 'Sedang Direview' : 'Review'; ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Letter Review -->
            <div class="col-5">
                <?php if ($reviewLetter): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Detail Surat</h3>
                        </div>
                        
                        <table class="table">
                            <tr>
                                <td><strong>Nomor Surat</strong></td>
                                <td><?php echo htmlspecialchars($reviewLetter['letter_number']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Jenis Surat</strong></td>
                                <td><?php echo htmlspecialchars($reviewLetter['template_name']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Pemohon</strong></td>
                                <td><?php echo htmlspecialchars($reviewLetter['submitter_name']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Email</strong></td>
                                <td><?php echo htmlspecialchars($reviewLetter['submitter_email']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Telepon</strong></td>
                                <td><?php echo htmlspecialchars($reviewLetter['submitter_phone'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Tanggal Pengajuan</strong></td>
                                <td><?php echo formatDate($reviewLetter['created_at']); ?></td>
                            </tr>
                        </table>
                        
                        <h4>Data Surat:</h4>
                        <?php 
                        $letterData = json_decode($reviewLetter['letter_data'], true);
                        ?>
                        <table class="table">
                            <?php foreach ($letterData as $key => $value): ?>
                                <tr>
                                    <td><strong><?php echo ucfirst(str_replace('_', ' ', $key)); ?></strong></td>
                                    <td><?php echo htmlspecialchars($value); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Preview Surat</h3>
                        </div>
                        <div class="letter-preview">
                            <?php echo processLetterTemplate($reviewLetter['template_content'], $reviewLetter['letter_data']); ?>
                            
                            <?php if ($hasSignature): ?>
                                <div style="margin-top: 3rem;">
                                    <p><strong>Tanda Tangan Manager:</strong></p>
                                    <img src="../assets/uploads/signatures/<?php echo htmlspecialchars($currentUser['digital_signature_path']); ?>" 
                                         alt="Tanda Tangan Manager" class="signature-preview">
                                    <p><?php echo htmlspecialchars($_SESSION['full_name']); ?><br>Manager</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Pilih Surat untuk Direview</h3>
                        </div>
                        <div class="text-center p-3">
                            <p>Pilih surat dari daftar di sebelah kiri untuk mulai review.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Action Panel -->
            <div class="col-3">
                <?php if ($reviewLetter): ?>
                    <div class="letter-actions">
                        <h4>Aksi Review</h4>
                        
                        <form method="POST" action="review.php" id="reviewForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="letter_id" value="<?php echo $reviewLetter['id']; ?>">
                            <input type="hidden" name="action" id="actionType">
                            
                            <div class="form-group">
                                <label for="notes">Catatan:</label>
                                <textarea id="notes" name="notes" rows="4" 
                                          placeholder="Tambahkan catatan (opsional untuk approve, wajib untuk reject/revision)"></textarea>
                            </div>
                            
                            <div class="action-buttons">
                                <?php if ($hasSignature): ?>
                                    <button type="button" onclick="submitAction('approve')" class="btn btn-success">
                                        ✓ Setujui
                                    </button>
                                <?php else: ?>
                                    <button type="button" disabled class="btn btn-success" title="Upload tanda tangan terlebih dahulu">
                                        ✓ Setujui (Perlu TTD)
                                    </button>
                                <?php endif; ?>
                                
                                <button type="button" onclick="submitAction('revision')" class="btn btn-warning">
                                    ↻ Minta Revisi
                                </button>
                                
                                <button type="button" onclick="submitAction('reject')" class="btn btn-danger">
                                    ✗ Tolak
                                </button>
                            </div>
                        </form>
                        
                        <div class="mt-3">
                            <a href="review.php" class="btn btn-outline btn-small">← Kembali ke Daftar</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="letter-actions">
                        <h4>Panduan Review</h4>
                        <ul>
                            <li><strong>Setujui:</strong> Surat akan diteruskan ke Direktur</li>
                            <li><strong>Revisi:</strong> Pemohon diminta memperbaiki surat</li>
                            <li><strong>Tolak:</strong> Surat ditolak dan tidak diproses</li>
                        </ul>
                        
                        <div class="mt-3">
                            <p><strong>Status Tanda Tangan:</strong></p>
                            <?php if ($hasSignature): ?>
                                <div class="alert alert-success">
                                    <small>✓ Tanda tangan digital aktif</small>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <small>⚠ Tanda tangan belum diatur</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function submitAction(action) {
            const notes = document.getElementById('notes').value.trim();
            
            if (action === 'reject' && !notes) {
                alert('Alasan penolakan harus diisi.');
                document.getElementById('notes').focus();
                return;
            }
            
            if (action === 'revision' && !notes) {
                alert('Catatan revisi harus diisi.');
                document.getElementById('notes').focus();
                return;
            }
            
            let confirmMessage = '';
            switch(action) {
                case 'approve':
                    confirmMessage = 'Apakah Anda yakin ingin menyetujui surat ini?\n\nSurat akan diteruskan ke Direktur untuk persetujuan final.';
                    break;
                case 'reject':
                    confirmMessage = 'Apakah Anda yakin ingin menolak surat ini?\n\nSurat yang ditolak tidak dapat diproses lebih lanjut.';
                    break;
                case 'revision':
                    confirmMessage = 'Apakah Anda yakin ingin meminta revisi untuk surat ini?\n\nPemohon akan diminta untuk memperbaiki surat.';
                    break;
            }
            
            if (confirm(confirmMessage)) {
                document.getElementById('actionType').value = action;
                document.getElementById('reviewForm').submit();
            }
        }

        // Auto-save notes to localStorage
        const notesField = document.getElementById('notes');
        if (notesField) {
            const letterId = <?php echo $reviewLetter ? $reviewLetter['id'] : 'null'; ?>;
            const storageKey = 'review_notes_' + letterId;
            
            // Load saved notes
            const savedNotes = localStorage.getItem(storageKey);
            if (savedNotes) {
                notesField.value = savedNotes;
            }
            
            // Save notes on input
            notesField.addEventListener('input', function() {
                localStorage.setItem(storageKey, this.value);
            });
            
            // Clear saved notes on form submit
            document.getElementById('reviewForm').addEventListener('submit', function() {
                localStorage.removeItem(storageKey);
            });
        }
    </script>
</body>
</html>
