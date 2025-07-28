<?php
/**
 * Common Functions
 * Sistem Manajemen Surat
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Generate unique letter number
 */
function generateLetterNumber() {
    $db = getDB();
    $date = date('Ymd');
    
    // Get count of letters created today
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM letters WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $result = $stmt->fetch();
    
    $sequence = $result['count'] + 1;
    return sprintf(LETTER_NUMBER_FORMAT, $date, $sequence);
}

/**
 * Upload file with validation
 */
function uploadFile($file, $uploadDir, $allowedTypes = [], $maxSize = MAX_FILE_SIZE) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error occurred'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File size too large'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!empty($allowedTypes) && !in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
    }
    
    return ['success' => false, 'message' => 'Failed to move uploaded file'];
}

/**
 * Send notification to user
 */
function sendNotification($userId, $letterId, $message, $type) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO notifications (user_id, letter_id, message, type) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$userId, $letterId, $message, $type]);
}

/**
 * Get unread notifications count
 */
function getUnreadNotificationsCount($userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result['count'];
}

/**
 * Get notifications for user
 */
function getUserNotifications($userId, $limit = 10) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

/**
 * Mark notification as read
 */
function markNotificationAsRead($notificationId) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ?");
    return $stmt->execute([$notificationId]);
}

/**
 * Get letter by ID
 */
function getLetterById($letterId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT l.*, t.name as template_name, t.template_content,
               m.full_name as manager_name, d.full_name as director_name
        FROM letters l 
        LEFT JOIN templates t ON l.template_id = t.id
        LEFT JOIN users m ON l.manager_action_by = m.id
        LEFT JOIN users d ON l.director_action_by = d.id
        WHERE l.id = ?
    ");
    $stmt->execute([$letterId]);
    return $stmt->fetch();
}

/**
 * Get letter by letter number
 */
function getLetterByNumber($letterNumber) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT l.*, t.name as template_name, t.template_content,
               m.full_name as manager_name, d.full_name as director_name
        FROM letters l 
        LEFT JOIN templates t ON l.template_id = t.id
        LEFT JOIN users m ON l.manager_action_by = m.id
        LEFT JOIN users d ON l.director_action_by = d.id
        WHERE l.letter_number = ?
    ");
    $stmt->execute([$letterNumber]);
    return $stmt->fetch();
}

/**
 * Get all templates
 */
function getAllTemplates() {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM templates ORDER BY name ASC");
    return $stmt->fetchAll();
}

/**
 * Get template by ID
 */
function getTemplateById($templateId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM templates WHERE id = ?");
    $stmt->execute([$templateId]);
    return $stmt->fetch();
}

/**
 * Process letter template with data
 */
function processLetterTemplate($templateContent, $letterData) {
    $data = json_decode($letterData, true);
    $processed = $templateContent;
    
    foreach ($data as $key => $value) {
        $processed = str_replace('{' . $key . '}', $value, $processed);
    }
    
    // Add current date if not provided
    $processed = str_replace('{tanggal_surat}', date('d F Y'), $processed);
    
    return $processed;
}

/**
 * Get letters with filters
 */
function getLetters($filters = []) {
    $db = getDB();
    $where = [];
    $params = [];
    
    if (!empty($filters['status'])) {
        $where[] = "l.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['template_id'])) {
        $where[] = "l.template_id = ?";
        $params[] = $filters['template_id'];
    }
    
    if (!empty($filters['date_from'])) {
        $where[] = "DATE(l.created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where[] = "DATE(l.created_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    $sql = "
        SELECT l.*, t.name as template_name
        FROM letters l 
        LEFT JOIN templates t ON l.template_id = t.id
        $whereClause
        ORDER BY l.created_at DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Update letter status
 */
function updateLetterStatus($letterId, $status, $userId, $notes = '') {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        if ($status === 'manager_approved') {
            $stmt = $db->prepare("
                UPDATE letters 
                SET status = ?, manager_action_by = ?, manager_action_at = NOW(), manager_notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, $userId, $notes, $letterId]);
        } elseif ($status === 'director_approved') {
            $stmt = $db->prepare("
                UPDATE letters 
                SET status = ?, director_action_by = ?, director_action_at = NOW(), director_notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, $userId, $notes, $letterId]);
        } else {
            $stmt = $db->prepare("UPDATE letters SET status = ? WHERE id = ?");
            $stmt->execute([$status, $letterId]);
        }
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollback();
        error_log("Error updating letter status: " . $e->getMessage());
        return false;
    }
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'd F Y H:i') {
    $months = [
        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
        'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
        'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
        'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
    ];
    
    $formatted = date($format, strtotime($date));
    return str_replace(array_keys($months), array_values($months), $formatted);
}

/**
 * Get status badge HTML
 */
function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge badge-warning">Menunggu Manager</span>',
        'manager_approved' => '<span class="badge badge-info">Menunggu Direktur</span>',
        'director_approved' => '<span class="badge badge-success">Disetujui</span>',
        'rejected' => '<span class="badge badge-danger">Ditolak</span>',
        'revision' => '<span class="badge badge-secondary">Perlu Revisi</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge badge-light">Unknown</span>';
}

/**
 * Log activity
 */
function logActivity($action, $details = '') {
    $db = getDB();
    $userId = $_SESSION['user_id'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, action, details, user_agent, ip_address) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([$userId, $action, $details, $userAgent, $ipAddress]);
}
?>
