<?php
/**
 * Public Letter Submission Page
 * Sistem Manajemen Surat - No login required
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token keamanan tidak valid. Silakan coba lagi.';
        $messageType = 'error';
    } else {
        // Sanitize input
        $templateId = (int)$_POST['template_id'];
        $submitterName = sanitizeInput($_POST['submitter_name']);
        $submitterEmail = sanitizeInput($_POST['submitter_email']);
        $submitterPhone = sanitizeInput($_POST['submitter_phone']);
        
        // Validate required fields
        if (empty($submitterName) || empty($submitterEmail) || empty($templateId)) {
            $message = 'Nama, email, dan jenis surat harus diisi.';
            $messageType = 'error';
        } elseif (!validateEmail($submitterEmail)) {
            $message = 'Format email tidak valid.';
            $messageType = 'error';
        } else {
            // Get template to know required fields
            $template = getTemplateById($templateId);
            if (!$template) {
                $message = 'Template surat tidak ditemukan.';
                $messageType = 'error';
            } else {
                $requiredFields = json_decode($template['fields_required'], true);
                $letterData = [];
                $missingFields = [];
                
                // Collect and validate form data
                foreach ($requiredFields as $field) {
                    $value = sanitizeInput($_POST[$field] ?? '');
                    if (empty($value)) {
                        $missingFields[] = ucfirst(str_replace('_', ' ', $field));
                    } else {
                        $letterData[$field] = $value;
                    }
                }
                
                if (!empty($missingFields)) {
                    $message = 'Field berikut harus diisi: ' . implode(', ', $missingFields);
                    $messageType = 'error';
                } else {
                    try {
                        $db = getDB();
                        $letterNumber = generateLetterNumber();
                        
                        // Insert letter
                        $stmt = $db->prepare("
                            INSERT INTO letters (letter_number, template_id, submitter_name, submitter_email, submitter_phone, letter_data, status) 
                            VALUES (?, ?, ?, ?, ?, ?, 'pending')
                        ");
                        
                        $result = $stmt->execute([
                            $letterNumber,
                            $templateId,
                            $submitterName,
                            $submitterEmail,
                            $submitterPhone,
                            json_encode($letterData)
                        ]);
                        
                        if ($result) {
                            $letterId = $db->lastInsertId();
                            
                            // Send notifications to admin
                            $adminUsers = $db->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
                            foreach ($adminUsers as $admin) {
                                sendNotification(
                                    $admin['id'], 
                                    $letterId, 
                                    "Pengajuan surat baru dari {$submitterName} - {$template['name']}", 
                                    'new_submission'
                                );
                            }
                            
                            $message = "Surat berhasil diajukan dengan nomor: <strong>{$letterNumber}</strong><br>
                                       Anda dapat melacak status surat dengan nomor tersebut atau email Anda di halaman tracking.";
                            $messageType = 'success';
                            
                            // Clear form data
                            $_POST = [];
                        } else {
                            $message = 'Gagal mengajukan surat. Silakan coba lagi.';
                            $messageType = 'error';
                        }
                    } catch (Exception $e) {
                        error_log("Error submitting letter: " . $e->getMessage());
                        $message = 'Terjadi kesalahan sistem. Silakan coba lagi.';
                        $messageType = 'error';
                    }
                }
            }
        }
    }
}

// Get all templates
$templates = getAllTemplates();

// Get template fields via AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] === 'template_fields' && isset($_GET['template_id'])) {
    header('Content-Type: application/json');
    $template = getTemplateById((int)$_GET['template_id']);
    if ($template) {
        echo json_encode([
            'success' => true,
            'fields' => json_decode($template['fields_required'], true)
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Surat - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo"><?php echo APP_NAME; ?></a>
                <nav>
                    <a href="index.php">Pengajuan Surat</a>
                    <a href="track.php">Lacak Surat</a>
                    <a href="../admin/login.php">Login Admin</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="row">
            <div class="col-8">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Pengajuan Surat</h2>
                        <p>Silakan isi form di bawah ini untuk mengajukan surat. Semua field yang bertanda (*) wajib diisi.</p>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?>">
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="index.php" class="letter-form" id="letterForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="form-section">
                            <h3>Informasi Pemohon</h3>
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="submitter_name">Nama Lengkap *</label>
                                        <input type="text" id="submitter_name" name="submitter_name" 
                                               value="<?php echo htmlspecialchars($_POST['submitter_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="submitter_email">Email *</label>
                                        <input type="email" id="submitter_email" name="submitter_email" 
                                               value="<?php echo htmlspecialchars($_POST['submitter_email'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="submitter_phone">Nomor Telepon</label>
                                <input type="tel" id="submitter_phone" name="submitter_phone" 
                                       value="<?php echo htmlspecialchars($_POST['submitter_phone'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Jenis Surat</h3>
                            <div class="form-group">
                                <label for="template_id">Pilih Jenis Surat *</label>
                                <select id="template_id" name="template_id" required onchange="loadTemplateFields()">
                                    <option value="">-- Pilih Jenis Surat --</option>
                                    <?php foreach ($templates as $template): ?>
                                        <option value="<?php echo $template['id']; ?>" 
                                                <?php echo (isset($_POST['template_id']) && $_POST['template_id'] == $template['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($template['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-section" id="dynamicFields" style="display: none;">
                            <h3>Detail Surat</h3>
                            <div id="templateFields">
                                <!-- Dynamic fields will be loaded here -->
                            </div>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn">Ajukan Surat</button>
                            <button type="reset" class="btn btn-outline" onclick="resetForm()">Reset Form</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Informasi</h3>
                    </div>
                    <div class="card-content">
                        <h4>Cara Pengajuan Surat:</h4>
                        <ol>
                            <li>Isi data pemohon dengan lengkap</li>
                            <li>Pilih jenis surat yang diinginkan</li>
                            <li>Lengkapi detail surat sesuai form</li>
                            <li>Klik "Ajukan Surat"</li>
                            <li>Catat nomor surat untuk tracking</li>
                        </ol>

                        <h4>Status Persetujuan:</h4>
                        <ul>
                            <li><span class="badge badge-warning">Pending</span> - Menunggu persetujuan Manager</li>
                            <li><span class="badge badge-info">Manager Approved</span> - Menunggu persetujuan Direktur</li>
                            <li><span class="badge badge-success">Approved</span> - Surat telah disetujui</li>
                            <li><span class="badge badge-danger">Rejected</span> - Surat ditolak</li>
                            <li><span class="badge badge-secondary">Revision</span> - Perlu perbaikan</li>
                        </ul>

                        <div class="mt-3">
                            <a href="track.php" class="btn btn-outline">Lacak Status Surat</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Field labels mapping
        const fieldLabels = {
            'nama': 'Nama',
            'unit': 'Unit/Bagian',
            'tujuan': 'Tujuan',
            'tempat': 'Tempat',
            'tanggal_acara': 'Tanggal Acara',
            'waktu_acara': 'Waktu Acara',
            'nama_kegiatan': 'Nama Kegiatan',
            'isi_permohonan': 'Isi Permohonan',
            'isi_keterangan': 'Isi Keterangan'
        };

        // Field types mapping
        const fieldTypes = {
            'tanggal_acara': 'date',
            'waktu_acara': 'time',
            'isi_permohonan': 'textarea',
            'isi_keterangan': 'textarea'
        };

        function loadTemplateFields() {
            const templateId = document.getElementById('template_id').value;
            const dynamicFields = document.getElementById('dynamicFields');
            const templateFields = document.getElementById('templateFields');

            if (!templateId) {
                dynamicFields.style.display = 'none';
                return;
            }

            // Show loading
            templateFields.innerHTML = '<div class="spinner"></div> Memuat field...';
            dynamicFields.style.display = 'block';

            // Fetch template fields
            fetch(`index.php?ajax=template_fields&template_id=${templateId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let fieldsHtml = '';
                        data.fields.forEach(field => {
                            const label = fieldLabels[field] || field.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
                            const type = fieldTypes[field] || 'text';
                            const savedValue = '<?php echo htmlspecialchars($_POST["' + field + '"] ?? ""); ?>';

                            if (type === 'textarea') {
                                fieldsHtml += `
                                    <div class="form-group">
                                        <label for="${field}">${label} *</label>
                                        <textarea id="${field}" name="${field}" required>${savedValue}</textarea>
                                    </div>
                                `;
                            } else {
                                fieldsHtml += `
                                    <div class="form-group">
                                        <label for="${field}">${label} *</label>
                                        <input type="${type}" id="${field}" name="${field}" value="${savedValue}" required>
                                    </div>
                                `;
                            }
                        });
                        templateFields.innerHTML = fieldsHtml;
                    } else {
                        templateFields.innerHTML = '<div class="alert alert-error">Gagal memuat field template.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    templateFields.innerHTML = '<div class="alert alert-error">Terjadi kesalahan saat memuat field.</div>';
                });
        }

        function resetForm() {
            document.getElementById('letterForm').reset();
            document.getElementById('dynamicFields').style.display = 'none';
        }

        // Load template fields if template is already selected (after form submission with errors)
        document.addEventListener('DOMContentLoaded', function() {
            const templateId = document.getElementById('template_id').value;
            if (templateId) {
                loadTemplateFields();
            }
        });
    </script>
</body>
</html>
