<?php
/**
 * Letter Tracking Page
 * Sistem Manajemen Surat - No login required
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$letter = null;
$message = '';
$messageType = '';

// Handle tracking request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $searchType = $_POST['search_type'] ?? 'letter_number';
    $searchValue = sanitizeInput($_POST['search_value'] ?? '');
    
    if (empty($searchValue)) {
        $message = 'Silakan masukkan nomor surat atau email untuk melacak.';
        $messageType = 'error';
    } else {
        try {
            $db = getDB();
            
            if ($searchType === 'letter_number') {
                $letter = getLetterByNumber($searchValue);
            } else {
                // Search by email
                $stmt = $db->prepare("
                    SELECT l.*, t.name as template_name, t.template_content,
                           m.full_name as manager_name, d.full_name as director_name
                    FROM letters l 
                    LEFT JOIN templates t ON l.template_id = t.id
                    LEFT JOIN users m ON l.manager_action_by = m.id
                    LEFT JOIN users d ON l.director_action_by = d.id
                    WHERE l.submitter_email = ?
                    ORDER BY l.created_at DESC
                ");
                $stmt->execute([$searchValue]);
                $letters = $stmt->fetchAll();
                
                if (count($letters) === 1) {
                    $letter = $letters[0];
                } elseif (count($letters) > 1) {
                    // Multiple letters found, show list
                    $multipleLetters = $letters;
                } else {
                    $message = 'Tidak ditemukan surat dengan email tersebut.';
                    $messageType = 'error';
                }
            }
            
            if (!$letter && !isset($multipleLetters)) {
                $message = 'Surat tidak ditemukan. Periksa kembali nomor surat atau email Anda.';
                $messageType = 'error';
            }
        } catch (Exception $e) {
            error_log("Error tracking letter: " . $e->getMessage());
            $message = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            $messageType = 'error';
        }
    }
}

// Handle letter selection from multiple results
if (isset($_GET['letter_id'])) {
    $letter = getLetterById((int)$_GET['letter_id']);
    if (!$letter) {
        $message = 'Surat tidak ditemukan.';
        $messageType = 'error';
    }
}

function getStatusTimeline($letter) {
    $timeline = [];
    
    // Submission
    $timeline[] = [
        'status' => 'submitted',
        'title' => 'Surat Diajukan',
        'date' => $letter['created_at'],
        'description' => 'Surat berhasil diajukan ke sistem',
        'completed' => true
    ];
    
    // Manager approval
    if ($letter['status'] === 'pending') {
        $timeline[] = [
            'status' => 'manager_review',
            'title' => 'Menunggu Persetujuan Manager',
            'date' => null,
            'description' => 'Surat sedang menunggu review dari Manager',
            'completed' => false,
            'current' => true
        ];
    } else {
        $timeline[] = [
            'status' => 'manager_review',
            'title' => 'Review Manager',
            'date' => $letter['manager_action_at'],
            'description' => $letter['manager_notes'] ?: 'Disetujui oleh Manager',
            'completed' => true
        ];
    }
    
    // Director approval
    if ($letter['status'] === 'manager_approved') {
        $timeline[] = [
            'status' => 'director_review',
            'title' => 'Menunggu Persetujuan Direktur',
            'date' => null,
            'description' => 'Surat sedang menunggu review dari Direktur',
            'completed' => false,
            'current' => true
        ];
    } elseif (in_array($letter['status'], ['director_approved', 'rejected'])) {
        $timeline[] = [
            'status' => 'director_review',
            'title' => 'Review Direktur',
            'date' => $letter['director_action_at'],
            'description' => $letter['director_notes'] ?: ($letter['status'] === 'director_approved' ? 'Disetujui oleh Direktur' : 'Ditolak oleh Direktur'),
            'completed' => true
        ];
    }
    
    // Final status
    if ($letter['status'] === 'director_approved') {
        $timeline[] = [
            'status' => 'completed',
            'title' => 'Surat Selesai',
            'date' => $letter['director_action_at'],
            'description' => 'Surat telah disetujui dan dapat diunduh',
            'completed' => true
        ];
    } elseif ($letter['status'] === 'rejected') {
        $timeline[] = [
            'status' => 'rejected',
            'title' => 'Surat Ditolak',
            'date' => $letter['manager_action_at'] ?: $letter['director_action_at'],
            'description' => 'Surat ditolak. Silakan ajukan surat baru dengan perbaikan.',
            'completed' => true,
            'rejected' => true
        ];
    } elseif ($letter['status'] === 'revision') {
        $timeline[] = [
            'status' => 'revision',
            'title' => 'Perlu Revisi',
            'date' => $letter['manager_action_at'] ?: $letter['director_action_at'],
            'description' => 'Surat perlu diperbaiki. Silakan ajukan surat baru dengan perbaikan.',
            'completed' => true,
            'revision' => true
        ];
    }
    
    return $timeline;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lacak Surat - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
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
            border-radius: 8px;
            padding: 1.5rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -2.5rem;
            top: 1.5rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: #e0e0e0;
            border: 3px solid #fff;
        }
        
        .timeline-item.completed::before {
            background: #28a745;
        }
        
        .timeline-item.current::before {
            background: #ffc107;
            animation: pulse 2s infinite;
        }
        
        .timeline-item.rejected::before {
            background: #dc3545;
        }
        
        .timeline-item.revision::before {
            background: #6c757d;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .timeline-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #000;
        }
        
        .timeline-date {
            font-size: 0.875rem;
            color: #666;
            margin-bottom: 0.5rem;
        }
        
        .timeline-description {
            color: #333;
        }
        
        .letter-preview-mini {
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
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo"><?php echo APP_NAME; ?></a>
                <nav>
                    <a href="index.php">Pengajuan Surat</a>
                    <a href="track.php" class="active">Lacak Surat</a>
                    <a href="../admin/login.php">Login Admin</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="row">
            <div class="col-4">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Lacak Status Surat</h2>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?>">
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="track.php">
                        <div class="form-group">
                            <label for="search_type">Lacak berdasarkan:</label>
                            <select id="search_type" name="search_type">
                                <option value="letter_number" <?php echo (($_POST['search_type'] ?? '') === 'letter_number') ? 'selected' : ''; ?>>
                                    Nomor Surat
                                </option>
                                <option value="email" <?php echo (($_POST['search_type'] ?? '') === 'email') ? 'selected' : ''; ?>>
                                    Email Pemohon
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="search_value">Masukkan nomor surat atau email:</label>
                            <input type="text" id="search_value" name="search_value" 
                                   value="<?php echo htmlspecialchars($_POST['search_value'] ?? ''); ?>" 
                                   placeholder="Contoh: SRT-20231201-0001 atau email@domain.com" required>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn">Lacak Surat</button>
                        </div>
                    </form>

                    <div class="mt-3">
                        <h4>Tips Pelacakan:</h4>
                        <ul>
                            <li>Gunakan nomor surat lengkap (contoh: SRT-20231201-0001)</li>
                            <li>Atau gunakan email yang digunakan saat pengajuan</li>
                            <li>Jika menggunakan email dan memiliki beberapa surat, pilih surat yang ingin dilacak</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-8">
                <?php if (isset($multipleLetters)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Pilih Surat yang Ingin Dilacak</h3>
                            <p>Ditemukan <?php echo count($multipleLetters); ?> surat dengan email tersebut:</p>
                        </div>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nomor Surat</th>
                                    <th>Jenis Surat</th>
                                    <th>Tanggal Pengajuan</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($multipleLetters as $letterItem): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($letterItem['letter_number']); ?></td>
                                        <td><?php echo htmlspecialchars($letterItem['template_name']); ?></td>
                                        <td><?php echo formatDate($letterItem['created_at'], 'd M Y'); ?></td>
                                        <td><?php echo getStatusBadge($letterItem['status']); ?></td>
                                        <td>
                                            <a href="track.php?letter_id=<?php echo $letterItem['id']; ?>" class="btn btn-small">
                                                Lihat Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($letter): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Detail Surat</h3>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <table class="table">
                                    <tr>
                                        <td><strong>Nomor Surat</strong></td>
                                        <td><?php echo htmlspecialchars($letter['letter_number']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Jenis Surat</strong></td>
                                        <td><?php echo htmlspecialchars($letter['template_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Pemohon</strong></td>
                                        <td><?php echo htmlspecialchars($letter['submitter_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email</strong></td>
                                        <td><?php echo htmlspecialchars($letter['submitter_email']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Tanggal Pengajuan</strong></td>
                                        <td><?php echo formatDate($letter['created_at']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status Saat Ini</strong></td>
                                        <td><?php echo getStatusBadge($letter['status']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-6">
                                <?php if ($letter['status'] === 'director_approved' && $letter['final_letter_path']): ?>
                                    <div class="alert alert-success">
                                        <h4>Surat Telah Disetujui!</h4>
                                        <p>Surat Anda telah disetujui dan dapat diunduh.</p>
                                        <a href="../<?php echo htmlspecialchars($letter['final_letter_path']); ?>" 
                                           class="btn btn-success" target="_blank">
                                            Unduh Surat
                                        </a>
                                    </div>
                                <?php elseif ($letter['status'] === 'rejected'): ?>
                                    <div class="alert alert-danger">
                                        <h4>Surat Ditolak</h4>
                                        <p>Surat Anda ditolak. Silakan ajukan surat baru dengan perbaikan yang diperlukan.</p>
                                    </div>
                                <?php elseif ($letter['status'] === 'revision'): ?>
                                    <div class="alert alert-warning">
                                        <h4>Perlu Revisi</h4>
                                        <p>Surat Anda perlu diperbaiki. Silakan ajukan surat baru dengan revisi yang diminta.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <h4>Sedang Diproses</h4>
                                        <p>Surat Anda sedang dalam proses persetujuan. Silakan cek kembali secara berkala.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Timeline Persetujuan</h3>
                        </div>
                        <div class="timeline">
                            <?php foreach (getStatusTimeline($letter) as $item): ?>
                                <div class="timeline-item <?php 
                                    echo $item['completed'] ? 'completed' : '';
                                    echo isset($item['current']) ? ' current' : '';
                                    echo isset($item['rejected']) ? ' rejected' : '';
                                    echo isset($item['revision']) ? ' revision' : '';
                                ?>">
                                    <div class="timeline-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                    <?php if ($item['date']): ?>
                                        <div class="timeline-date"><?php echo formatDate($item['date']); ?></div>
                                    <?php endif; ?>
                                    <div class="timeline-description"><?php echo htmlspecialchars($item['description']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ($letter['template_content']): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Preview Surat</h3>
                            </div>
                            <div class="letter-preview-mini">
                                <?php echo processLetterTemplate($letter['template_content'], $letter['letter_data']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Lacak Status Surat Anda</h3>
                        </div>
                        <div class="text-center p-3">
                            <p>Masukkan nomor surat atau email di form sebelah kiri untuk melacak status surat Anda.</p>
                            <p>Sistem akan menampilkan informasi lengkap tentang progress persetujuan surat.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
