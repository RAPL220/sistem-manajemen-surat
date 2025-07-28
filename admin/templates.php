<?php
/**
 * Template Management Page
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token keamanan tidak valid.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $name = sanitizeInput($_POST['name'] ?? '');
            $content = $_POST['content'] ?? '';
            $fields = $_POST['fields'] ?? [];
            
            if (empty($name) || empty($content) || empty($fields)) {
                $message = 'Nama template, konten, dan field harus diisi.';
                $messageType = 'error';
            } else {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO templates (name, template_content, fields_required, created_by) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $result = $stmt->execute([
                        $name,
                        $content,
                        json_encode($fields),
                        $_SESSION['user_id']
                    ]);
                    
                    if ($result) {
                        $message = 'Template berhasil ditambahkan.';
                        $messageType = 'success';
                        logActivity('template_create', "Template created: $name");
                    } else {
                        $message = 'Gagal menambahkan template.';
                        $messageType = 'error';
                    }
                } catch (Exception $e) {
                    error_log("Error adding template: " . $e->getMessage());
                    $message = 'Terjadi kesalahan sistem.';
                    $messageType = 'error';
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $name = sanitizeInput($_POST['name'] ?? '');
            $content = $_POST['content'] ?? '';
            $fields = $_POST['fields'] ?? [];
            
            if (empty($name) || empty($content) || empty($fields)) {
                $message = 'Nama template, konten, dan field harus diisi.';
                $messageType = 'error';
            } else {
                try {
                    $stmt = $db->prepare("
                        UPDATE templates 
                        SET name = ?, template_content = ?, fields_required = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $result = $stmt->execute([
                        $name,
                        $content,
                        json_encode($fields),
                        $id
                    ]);
                    
                    if ($result) {
                        $message = 'Template berhasil diperbarui.';
                        $messageType = 'success';
                        logActivity('template_update', "Template updated: $name (ID: $id)");
                    } else {
                        $message = 'Gagal memperbarui template.';
                        $messageType = 'error';
                    }
                } catch (Exception $e) {
                    error_log("Error updating template: " . $e->getMessage());
                    $message = 'Terjadi kesalahan sistem.';
                    $messageType = 'error';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            
            try {
                // Check if template is being used
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM letters WHERE template_id = ?");
                $stmt->execute([$id]);
                $usage = $stmt->fetch();
                
                if ($usage['count'] > 0) {
                    $message = 'Template tidak dapat dihapus karena masih digunakan oleh ' . $usage['count'] . ' surat.';
                    $messageType = 'error';
                } else {
                    $stmt = $db->prepare("DELETE FROM templates WHERE id = ?");
                    $result = $stmt->execute([$id]);
                    
                    if ($result) {
                        $message = 'Template berhasil dihapus.';
                        $messageType = 'success';
                        logActivity('template_delete', "Template deleted: ID $id");
                    } else {
                        $message = 'Gagal menghapus template.';
                        $messageType = 'error';
                    }
                }
            } catch (Exception $e) {
                error_log("Error deleting template: " . $e->getMessage());
                $message = 'Terjadi kesalahan sistem.';
                $messageType = 'error';
            }
        }
    }
}

// Get all templates
$templates = getAllTemplates();

// Get template for editing
$editTemplate = null;
if (isset($_GET['edit'])) {
    $editTemplate = getTemplateById((int)$_GET['edit']);
}

// Available field types
$fieldTypes = [
    'nama' => 'Nama',
    'unit' => 'Unit/Bagian',
    'tujuan' => 'Tujuan',
    'tempat' => 'Tempat',
    'tanggal_acara' => 'Tanggal Acara',
    'waktu_acara' => 'Waktu Acara',
    'nama_kegiatan' => 'Nama Kegiatan',
    'isi_permohonan' => 'Isi Permohonan',
    'isi_keterangan' => 'Isi Keterangan',
    'nomor_telepon' => 'Nomor Telepon',
    'alamat' => 'Alamat',
    'jabatan' => 'Jabatan',
    'keperluan' => 'Keperluan',
    'keterangan' => 'Keterangan'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Template - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .template-preview {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 1rem;
            font-family: 'Times New Roman', serif;
            font-size: 0.875rem;
            line-height: 1.6;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .field-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .field-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .template-variables {
            background: #e9ecef;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .variable-tag {
            display: inline-block;
            background: #000;
            color: #fff;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.75rem;
            margin: 0.25rem;
            cursor: pointer;
        }
        
        .variable-tag:hover {
            background: #333;
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
                    <a href="templates.php" class="active">Template Surat</a>
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
                <h1>Kelola Template Surat</h1>
                <p>Kelola template surat yang dapat digunakan untuk pengajuan surat.</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Template List -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Daftar Template</h3>
                    </div>
                    
                    <?php if (empty($templates)): ?>
                        <div class="text-center p-3">
                            <p>Belum ada template surat.</p>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nama Template</th>
                                    <th>Dibuat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($templates as $template): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($template['name']); ?></td>
                                        <td><?php echo formatDate($template['created_at'], 'd M Y'); ?></td>
                                        <td>
                                            <a href="templates.php?edit=<?php echo $template['id']; ?>" 
                                               class="btn btn-small">Edit</a>
                                            <button onclick="deleteTemplate(<?php echo $template['id']; ?>, '<?php echo htmlspecialchars($template['name']); ?>')" 
                                                    class="btn btn-small btn-danger">Hapus</button>
                                            <button onclick="previewTemplate(<?php echo $template['id']; ?>)" 
                                                    class="btn btn-small btn-outline">Preview</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Template Form -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <?php echo $editTemplate ? 'Edit Template' : 'Tambah Template Baru'; ?>
                        </h3>
                        <?php if ($editTemplate): ?>
                            <a href="templates.php" class="btn btn-small btn-outline">Batal Edit</a>
                        <?php endif; ?>
                    </div>

                    <form method="POST" action="templates.php" id="templateForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="<?php echo $editTemplate ? 'edit' : 'add'; ?>">
                        <?php if ($editTemplate): ?>
                            <input type="hidden" name="id" value="<?php echo $editTemplate['id']; ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="name">Nama Template *</label>
                            <input type="text" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($editTemplate['name'] ?? ''); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label>Field yang Diperlukan *</label>
                            <div class="template-variables">
                                <p><strong>Klik field di bawah untuk menambahkan ke template:</strong></p>
                                <?php foreach ($fieldTypes as $key => $label): ?>
                                    <span class="variable-tag" onclick="insertVariable('{<?php echo $key; ?>}')">{<?php echo $key; ?>}</span>
                                <?php endforeach; ?>
                                <span class="variable-tag" onclick="insertVariable('{letter_number}')">{letter_number}</span>
                                <span class="variable-tag" onclick="insertVariable('{tanggal_surat}')">{tanggal_surat}</span>
                            </div>
                            
                            <div class="field-selector">
                                <?php 
                                $selectedFields = $editTemplate ? json_decode($editTemplate['fields_required'], true) : [];
                                foreach ($fieldTypes as $key => $label): 
                                ?>
                                    <div class="field-item">
                                        <input type="checkbox" id="field_<?php echo $key; ?>" name="fields[]" 
                                               value="<?php echo $key; ?>"
                                               <?php echo in_array($key, $selectedFields) ? 'checked' : ''; ?>>
                                        <label for="field_<?php echo $key; ?>"><?php echo $label; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="content">Konten Template *</label>
                            <textarea id="content" name="content" rows="15" required><?php echo htmlspecialchars($editTemplate['template_content'] ?? ''); ?></textarea>
                            <small>Gunakan variabel seperti {nama}, {tujuan}, dll. sesuai field yang dipilih di atas.</small>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn">
                                <?php echo $editTemplate ? 'Perbarui Template' : 'Simpan Template'; ?>
                            </button>
                            <?php if ($editTemplate): ?>
                                <a href="templates.php" class="btn btn-outline">Batal</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Preview Modal -->
        <div id="previewModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 8px; max-width: 80%; max-height: 80%; overflow-y: auto;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3>Preview Template</h3>
                    <button onclick="closePreview()" class="btn btn-small">Tutup</button>
                </div>
                <div id="previewContent" class="template-preview"></div>
            </div>
        </div>
    </main>

    <!-- Delete Form -->
    <form id="deleteForm" method="POST" action="templates.php" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <script>
        function insertVariable(variable) {
            const textarea = document.getElementById('content');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            
            textarea.value = text.substring(0, start) + variable + text.substring(end);
            textarea.focus();
            textarea.setSelectionRange(start + variable.length, start + variable.length);
        }

        function deleteTemplate(id, name) {
            if (confirm('Apakah Anda yakin ingin menghapus template "' + name + '"?\n\nTemplate yang sedang digunakan tidak dapat dihapus.')) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        function previewTemplate(id) {
            fetch('template_preview.php?id=' + id)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('previewContent').innerHTML = html;
                    document.getElementById('previewModal').style.display = 'block';
                })
                .catch(error => {
                    alert('Gagal memuat preview template.');
                    console.error('Error:', error);
                });
        }

        function closePreview() {
            document.getElementById('previewModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('previewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePreview();
            }
        });

        // Auto-save draft functionality
        let saveTimeout;
        document.getElementById('content').addEventListener('input', function() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(function() {
                // Could implement auto-save to localStorage here
                console.log('Auto-saving draft...');
            }, 2000);
        });
    </script>
</body>
</html>
