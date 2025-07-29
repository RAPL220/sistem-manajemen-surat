<?php
/**
 * Director Digital Signature Upload
 * Sistem Manajemen Surat
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require director login
requireLogin('director');

$message = '';
$messageType = '';

// Handle signature upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token keamanan tidak valid.';
        $messageType = 'error';
    } else {
        if (!isset($_FILES['signature']) || $_FILES['signature']['error'] !== UPLOAD_ERR_OK) {
            $message = 'Silakan pilih file tanda tangan.';
            $messageType = 'error';
        } else {
            $uploadResult = uploadFile(
                $_FILES['signature'], 
                SIGNATURE_DIR, 
                ALLOWED_SIGNATURE_TYPES, 
                MAX_FILE_SIZE
            );
            
            if ($uploadResult['success']) {
                // Delete old signature if exists
                $currentUser = getCurrentUser();
                if (!empty($currentUser['digital_signature_path'])) {
                    $oldSignaturePath = SIGNATURE_DIR . $currentUser['digital_signature_path'];
                    if (file_exists($oldSignaturePath)) {
                        unlink($oldSignaturePath);
                    }
                }
                
                // Update user signature path
                $result = updateUserSignature($_SESSION['user_id'], $uploadResult['filename']);
                
                if ($result) {
                    $message = 'Tanda tangan digital berhasil diupload.';
                    $messageType = 'success';
                } else {
                    // Delete uploaded file if database update failed
                    unlink($uploadResult['filepath']);
                    $message = 'Gagal menyimpan tanda tangan ke database.';
                    $messageType = 'error';
                }
            } else {
                $message = $uploadResult['message'];
                $messageType = 'error';
            }
        }
    }
}

// Get current user info
$currentUser = getCurrentUser();
$hasSignature = !empty($currentUser['digital_signature_path']) && file_exists(SIGNATURE_DIR . $currentUser['digital_signature_path']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tanda Tangan Digital - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .signature-preview {
            max-width: 300px;
            max-height: 150px;
            border: 2px solid #000;
            border-radius: 4px;
            padding: 1rem;
            background: #fff;
        }
        
        .signature-upload-area {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: border-color 0.3s ease;
            cursor: pointer;
        }
        
        .signature-upload-area:hover {
            border-color: #000;
        }
        
        .signature-upload-area.dragover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        
        .file-info {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .signature-guidelines {
            background: #e9ecef;
            border-radius: 4px;
            padding: 1.5rem;
            margin-bottom: 2rem;
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
                    <a href="review.php">Review Surat</a>
                    <a href="signature.php" class="active">Tanda Tangan Digital</a>
                    <a href="notifications.php">Notifikasi</a>
                    <a href="logout.php">Logout</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="row">
            <div class="col-12">
                <h1>Tanda Tangan Digital</h1>
                <p>Upload dan kelola tanda tangan digital Anda untuk memberikan persetujuan final surat.</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Current Signature -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Tanda Tangan Saat Ini</h3>
                    </div>
                    
                    <?php if ($hasSignature): ?>
                        <div class="text-center p-3">
                            <img src="../assets/uploads/signatures/<?php echo htmlspecialchars($currentUser['digital_signature_path']); ?>" 
                                 alt="Tanda Tangan Digital" class="signature-preview">
                            <div class="mt-2">
                                <p><strong><?php echo htmlspecialchars($currentUser['full_name']); ?></strong></p>
                                <p>Direktur</p>
                                <small class="text-muted">
                                    Diupload: <?php echo formatDate($currentUser['updated_at'] ?? $currentUser['created_at']); ?>
                                </small>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-3">
                            <div class="signature-preview" style="display: flex; align-items: center; justify-content: center; color: #666;">
                                <p>Belum ada tanda tangan</p>
                            </div>
                            <div class="mt-2">
                                <p><strong><?php echo htmlspecialchars($currentUser['full_name']); ?></strong></p>
                                <p>Direktur</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Guidelines -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Panduan Tanda Tangan Digital</h3>
                    </div>
                    <div class="signature-guidelines">
                        <h4>Persyaratan File:</h4>
                        <ul>
                            <li>Format: PNG atau JPG</li>
                            <li>Ukuran maksimal: 5MB</li>
                            <li>Resolusi yang disarankan: 300x150 pixel</li>
                            <li>Background transparan (PNG) lebih baik</li>
                        </ul>
                        
                        <h4>Tips Tanda Tangan yang Baik:</h4>
                        <ul>
                            <li>Gunakan tinta hitam pada kertas putih</li>
                            <li>Scan dengan resolusi tinggi</li>
                            <li>Pastikan tanda tangan jelas dan tidak buram</li>
                            <li>Crop gambar agar hanya tanda tangan yang terlihat</li>
                        </ul>
                        
                        <h4>Keamanan:</h4>
                        <ul>
                            <li>Tanda tangan digital memiliki kekuatan hukum</li>
                            <li>Hanya upload tanda tangan asli Anda</li>
                            <li>Jaga kerahasiaan akun Anda</li>
                            <li>Sebagai Direktur, tanda tangan Anda adalah persetujuan final</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Upload Form -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <?php echo $hasSignature ? 'Update Tanda Tangan' : 'Upload Tanda Tangan'; ?>
                        </h3>
                    </div>

                    <form method="POST" action="signature.php" enctype="multipart/form-data" id="signatureForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="form-group">
                            <label for="signature">File Tanda Tangan *</label>
                            <div class="signature-upload-area" onclick="document.getElementById('signature').click()">
                                <input type="file" id="signature" name="signature" 
                                       accept="image/png,image/jpeg" required style="display: none;">
                                <div id="uploadText">
                                    <p><strong>Klik untuk memilih file</strong></p>
                                    <p>atau drag & drop file di sini</p>
                                    <small>PNG atau JPG, maksimal 5MB</small>
                                </div>
                                <div id="filePreview" style="display: none;">
                                    <img id="previewImage" style="max-width: 200px; max-height: 100px;">
                                    <p id="fileName"></p>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn" id="submitBtn">
                                <?php echo $hasSignature ? 'Update Tanda Tangan' : 'Upload Tanda Tangan'; ?>
                            </button>
                            <a href="dashboard.php" class="btn btn-outline">Kembali</a>
                        </div>
                    </form>
                </div>

                <!-- Preview in Letter -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Preview dalam Surat</h3>
                    </div>
                    <div class="letter-preview" style="font-family: 'Times New Roman', serif; padding: 1.5rem; background: #f8f9fa;">
                        <p>...isi surat...</p>
                        <div style="margin-top: 3rem; display: flex; justify-content: space-around;">
                            <div style="text-align: center;">
                                <p>Mengetahui,<br>Manager</p>
                                <div style="height: 75px; border: 1px dashed #ccc; display: flex; align-items: center; justify-content: center; width: 150px;">
                                    <small>Tanda Tangan Manager</small>
                                </div>
                                <p><strong>Manager Name</strong></p>
                            </div>
                            <div style="text-align: center;">
                                <p>Menyetujui,<br>Direktur</p>
                                <?php if ($hasSignature): ?>
                                    <img src="../assets/uploads/signatures/<?php echo htmlspecialchars($currentUser['digital_signature_path']); ?>" 
                                         alt="Tanda Tangan" style="max-width: 150px; max-height: 75px;">
                                <?php else: ?>
                                    <div style="height: 75px; border: 1px dashed #ccc; display: flex; align-items: center; justify-content: center; width: 150px;">
                                        <small>Tanda Tangan</small>
                                    </div>
                                <?php endif; ?>
                                <p><strong><?php echo htmlspecialchars($currentUser['full_name']); ?></strong><br>
                                Direktur</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Director Authority Info -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Kewenangan Direktur</h3>
                    </div>
                    <div class="card-content">
                        <div class="alert alert-info">
                            <h4>Persetujuan Final</h4>
                            <p>Sebagai Direktur, tanda tangan digital Anda merupakan persetujuan final untuk penerbitan surat. Surat yang telah Anda setujui akan:</p>
                            <ul>
                                <li>Langsung dapat diakses oleh pemohon</li>
                                <li>Memiliki kekuatan hukum yang sah</li>
                                <li>Tidak dapat diubah atau dibatalkan</li>
                                <li>Tercatat dalam sistem sebagai surat resmi</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const fileInput = document.getElementById('signature');
        const uploadArea = document.querySelector('.signature-upload-area');
        const uploadText = document.getElementById('uploadText');
        const filePreview = document.getElementById('filePreview');
        const previewImage = document.getElementById('previewImage');
        const fileName = document.getElementById('fileName');
        const submitBtn = document.getElementById('submitBtn');

        // File input change handler
        fileInput.addEventListener('change', handleFileSelect);

        // Drag and drop handlers
        uploadArea.addEventListener('dragover', handleDragOver);
        uploadArea.addEventListener('dragleave', handleDragLeave);
        uploadArea.addEventListener('drop', handleDrop);

        function handleFileSelect(e) {
            const file = e.target.files[0];
            if (file) {
                validateAndPreviewFile(file);
            }
        }

        function handleDragOver(e) {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        }

        function handleDragLeave(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
        }

        function handleDrop(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const file = files[0];
                fileInput.files = files;
                validateAndPreviewFile(file);
            }
        }

        function validateAndPreviewFile(file) {
            // Validate file type
            const allowedTypes = ['image/png', 'image/jpeg'];
            if (!allowedTypes.includes(file.type)) {
                alert('File harus berformat PNG atau JPG.');
                fileInput.value = '';
                return;
            }

            // Validate file size (5MB)
            const maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                alert('Ukuran file maksimal 5MB.');
                fileInput.value = '';
                return;
            }

            // Show preview
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                fileName.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
                uploadText.style.display = 'none';
                filePreview.style.display = 'block';
                submitBtn.disabled = false;
            };
            reader.readAsDataURL(file);
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Form submission handler
        document.getElementById('signatureForm').addEventListener('submit', function(e) {
            if (!fileInput.files.length) {
                e.preventDefault();
                alert('Silakan pilih file tanda tangan.');
                return;
            }

            if (confirm('Apakah Anda yakin ingin ' + (<?php echo $hasSignature ? 'true' : 'false'; ?> ? 'mengupdate' : 'mengupload') + ' tanda tangan digital?\n\nTanda tangan ini akan digunakan untuk persetujuan final surat.')) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner"></span> Mengupload...';
            } else {
                e.preventDefault();
            }
        });

        // Reset preview when clicking upload area again
        uploadArea.addEventListener('click', function() {
            if (filePreview.style.display === 'block') {
                fileInput.value = '';
                uploadText.style.display = 'block';
                filePreview.style.display = 'none';
                submitBtn.disabled = true;
            }
        });
    </script>
</body>
</html>
