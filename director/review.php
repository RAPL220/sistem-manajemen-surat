<?php
/**
 * Director Letter Review Page
 * Sistem Manajemen Surat
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require director login
requireLogin('director');

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
            if (!$letter || $letter['status'] !== 'manager_approved') {
                $message = 'Surat tidak ditemukan atau belum disetujui Manager.';
                $messageType = 'error';
            } else {
                try {
                    $db->beginTransaction();
                    
                    if ($action === 'approve') {
                        // Check if director has digital signature
                        $currentUser = getCurrentUser();
                        if (empty($currentUser['digital_signature_path'])) {
                            $message = 'Anda harus mengupload tanda tangan digital terlebih dahulu.';
                            $messageType = 'error';
                        } else {
                            // Update letter status to director_approved
                            $result = updateLetterStatus($letterId, 'director_approved', $_SESSION['user_id'], $notes);
                            
                            if ($result) {
                                // Generate final letter with signatures
                                $finalLetterPath = generateFinalLetter($letter);
                                
                                if ($finalLetterPath) {
                                    // Update letter with final path
                                    $stmt = $db->prepare("UPDATE letters SET final_letter_path = ? WHERE id = ?");
                                    $stmt->execute([$finalLetterPath, $letterId]);
                                }
                                
                                // Send notification to admin
                                $admins = $db->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
                                foreach ($admins as $admin) {
                                    sendNotification(
                                        $admin['id'], 
                                        $letterId, 
                                        "Surat {$letter['letter_number']} telah disetujui Direktur dan siap terbit", 
                                        'approval'
                                    );
                                }
                                
                                $message = 'Surat berhasil disetujui dan telah terbit.';
                                $messageType = 'success';
                                logActivity('letter_approve_director', "Letter approved: {$letter['letter_number']}");
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
                                // Send notification to admin and manager
                                $admins = $db->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
                                foreach ($admins as $admin) {
                                    sendNotification(
                                        $admin['id'], 
                                        $letterId, 
                                        "Surat {$letter['letter_number']} ditolak oleh Direktur {$_SESSION['full_name']}", 
                                        'rejection'
                                    );
                                }
                                
                                if ($letter['manager_action_by']) {
                                    sendNotification(
                                        $letter['manager_action_by'], 
                                        $letterId, 
                                        "Surat {$letter['letter_number']} yang Anda setujui ditolak oleh Direktur", 
                                        'rejection'
                                    );
                                }
                                
                                $message = 'Surat berhasil ditolak.';
                                $messageType = 'success';
                                logActivity('letter_reject_director', "Letter rejected: {$letter['letter_number']}");
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
                                // Send notification to admin and manager
                                $admins = $db->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
                                foreach ($admins as $admin) {
                                    sendNotification(
                                        $admin['id'], 
                                        $letterId, 
                                        "Surat {$letter['letter_number']} perlu revisi menurut Direktur {$_SESSION['full_name']}", 
                                        'revision'
                                    );
                                }
                                
                                if ($letter['manager_action_by']) {
                                    sendNotification(
                                        $letter['manager_action_by'], 
                                        $letterId, 
                                        "Surat {$letter['letter_number']} yang Anda setujui perlu revisi menurut Direktur", 
                                        'revision'
                                    );
                                }
                                
                                $message = 'Surat berhasil diminta untuk revisi.';
                                $messageType = 'success';
                                logActivity('letter_revision_director', "Letter revision requested: {$letter['letter_number']}");
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
    if (!$reviewLetter || $reviewLetter['status'] !== 'manager_approved') {
        $reviewLetter = null;
        $message = 'Surat tidak ditemukan atau belum disetujui Manager.';
        $messageType = 'error';
    }
}

// Get all letters waiting for director approval
$pendingLetters = getLetters(['status' => 'manager_approved']);

// Check if director has digital signature
$currentUser = getCurrentUser();
$hasSignature = !empty($currentUser['digital_signature_path']) && file_exists(SIGNATURE_DIR . $currentUser['digital_signature_path']);

// Function to generate final letter (placeholder)
function generateFinalLetter($letter) {
    // This would generate a PDF or HTML file with signatures
    // For now, return a placeholder path
    return 'letters/' . $letter['letter_number'] . '.pdf';
}
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
        
        .approval-chain {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .approval-step {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.5rem 0;
        }
        
        .approval-step.completed {
            color: #28a745;
        }
        
        .approval-step.current {
            color: #007bff;
            font-weight: bold;
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
                <h1>Review Surat Final</h1>
                <p>Review dan berikan persetujuan final untuk surat yang telah disetujui Manager.</p>
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
                        <h3 class="card-title">Surat Menunggu Persetujuan Final (<?php echo count($pendingLetters); ?>)</h3>
                    </div>
                    
                    <?php if (empty($pendingLetters)): ?>
                        <div class="text-center p-3">
                            <p>Tidak ada surat yang menunggu persetujuan final.</p>
                        </div>
                    <?php else: ?>
                        <div class="letter-list">
                            <?php foreach ($pendingLetters as $letter): ?>
                                <div class="card mb-2 <?php echo ($reviewLetter && $reviewLetter['id'] == $letter['id']) ? 'border-primary' : ''; ?>">
                                    <div class="p-2">
                                        <h5><?php echo htmlspecialchars($letter['letter_number']); ?></h5>
                                        <p class="mb-1"><strong><?php echo htmlspecialchars($letter['submitter_name']); ?></strong></p>
                                        <p class="mb-1"><?php echo htmlspecialchars($letter['template_name']); ?></p>
                                        <p class="mb-2"><small>Disetujui Manager: <?php echo formatDate($letter['manager_action_at'], 'd M Y'); ?></small></p>
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
                        
                        <!-- Approval Chain -->
                        <div class="approval-chain">
                            <h4>Alur Persetujuan</h4>
                            <div class="approval-step completed">
                                ✓ Pengajuan oleh <?php echo htmlspecialchars($reviewLetter['submitter_name']); ?>
                                <small>(<?php echo formatDate($reviewLetter['created_at'], 'd M Y'); ?>)</small>
                            </div>
                            <div class="approval-step completed">
                                ✓ Disetujui Manager <?php echo htmlspecialchars($reviewLetter['manager_name'] ?? 'Unknown'); ?>
                                <small>(<?php echo formatDate($reviewLetter['manager_action_at'], 'd M Y'); ?>)</small>
                            </div>
                            <div class="approval-step current">
                                → Menunggu Persetujuan Direktur
                            </div>
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
                            <tr>
                                <td><strong>Catatan Manager</strong></td>
                                <td><?php echo htmlspecialchars($reviewLetter['manager_notes'] ?: 'Tidak ada catatan'); ?></td>
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
                            <h3 class="card-title">Preview Surat Final</h3>
                        </div>
                        <div class="letter-preview">
                            <?php echo processLetterTemplate($reviewLetter['template_content'], $reviewLetter['letter_data']); ?>
                            
                            <div style="margin-top: 3rem; display: flex; justify-content: space-around;">
                                <!-- Manager Signature -->
                                <?php if ($reviewLetter['manager_action_by']): ?>
                                    <?php
                                    $stmt = $db->prepare("SELECT full_name, digital_signature_path FROM users WHERE id = ?");
                                    $stmt->execute([$reviewLetter['manager_action_by']]);
                                    $manager = $stmt->fetch();
                                    ?>
                                    <div style="text-align: center;">
                                        <p>Mengetahui,<br>Manager</p>
                                        <?php if ($manager && $manager['digital_signature_path']): ?>
                                            <img src="../assets/uploads/signatures/<?php echo htmlspecialchars($manager['digital_signature_path']); ?>" 
                                                 alt="Tanda Tangan Manager" class="signature-preview">
                                        <?php else: ?>
                                            <div style="height: 75px; border: 1px dashed #ccc; display: flex; align-items: center; justify-content: center; width: 150px;">
                                                <small>Tanda Tangan Manager</small>
                                            </div>
                                        <?php endif; ?>
                                        <p><strong><?php echo htmlspecialchars($manager['full_name'] ?? 'Manager'); ?></strong></p>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Director Signature -->
                                <div style="text-align: center;">
                                    <p>Menyetujui,<br>Direktur</p>
                                    <?php if ($hasSignature): ?>
                                        <img src="../assets/uploads/signatures/<?php echo htmlspecialchars($currentUser['digital_signature_path']); ?>" 
                                             alt="Tanda Tangan Direktur" class="signature-preview">
                                    <?php else: ?>
                                        <div style="height: 75px; border: 1px dashed #ccc; display: flex; align-items: center; justify-content: center; width: 150px;">
                                            <small>Upload TTD</small>
                                        </div>
                                    <?php endif; ?>
                                    <p><strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong><br>Direktur</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Pilih Surat untuk Direview</h3>
                        </div>
                        <div class="text-center p-3">
                            <p>Pilih surat dari daftar di sebelah kiri untuk mulai review final.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Action Panel -->
            <div class="col-3">
                <?php if ($reviewLetter): ?>
                    <div class="letter-actions">
                        <h4>Aksi Review Final</h4>
                        
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
                                        ✓ Setujui & Terbitkan
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
                        <h4>Panduan Review Final</h4>
                        <ul>
                            <li><strong>Setujui:</strong> Surat akan terbit dan dapat diunduh</li>
                            <li><strong>Revisi:</strong> Surat dikembalikan untuk perbaikan</li>
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
                        
                        <div class="mt-3">
                            <p><strong>Catatan:</strong></p>
                            <small>Sebagai Direktur, Anda memberikan persetujuan final. Surat yang disetujui akan langsung terbit dan dapat diakses oleh pemohon.</small>
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
                    confirmMessage = 'Apakah Anda yakin ingin menyetujui dan menerbitkan surat ini?\n\nSurat akan langsung dapat diakses oleh pemohon.';
                    break;
                case 'reject':
                    confirmMessage = 'Apakah Anda yakin ingin menolak surat ini?\n\nSurat yang ditolak tidak dapat diproses lebih lanjut.';
                    break;
                case 'revision':
                    confirmMessage = 'Apakah Anda yakin ingin meminta revisi untuk surat ini?\n\nSurat akan dikembalikan untuk perbaikan.';
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
            const storageKey = 'director_review_notes_' + letterId;
            
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
