-- Database Schema for Sistem Manajemen Surat
-- Run this SQL to create the required database structure

CREATE DATABASE IF NOT EXISTS letter_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE letter_management;

-- Users table (Admin, Manager, Director)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'director') NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    digital_signature_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Templates table
CREATE TABLE templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    template_content TEXT NOT NULL,
    fields_required JSON NOT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Letters table
CREATE TABLE letters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    letter_number VARCHAR(50) UNIQUE NOT NULL,
    template_id INT NOT NULL,
    submitter_name VARCHAR(255) NOT NULL,
    submitter_email VARCHAR(255),
    submitter_phone VARCHAR(20),
    letter_data JSON NOT NULL,
    status ENUM('pending', 'manager_approved', 'director_approved', 'rejected', 'revision') DEFAULT 'pending',
    manager_action_by INT NULL,
    manager_action_at TIMESTAMP NULL,
    manager_notes TEXT NULL,
    director_action_by INT NULL,
    director_action_at TIMESTAMP NULL,
    director_notes TEXT NULL,
    final_letter_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_action_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (director_action_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Notifications table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    letter_id INT NULL,
    message TEXT NOT NULL,
    type ENUM('new_submission', 'approval', 'rejection', 'revision') NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (letter_id) REFERENCES letters(id) ON DELETE CASCADE
);

-- Activity logs table
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT NULL,
    user_agent TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Login attempts table (for rate limiting)
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    success BOOLEAN NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin user
-- Password: admin123 (change this in production!)
INSERT INTO users (username, password_hash, role, full_name) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administrator'),
('manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 'Manager'),
('director', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'director', 'Direktur');

-- Insert sample templates
INSERT INTO templates (name, template_content, fields_required, created_by) VALUES 
(
    'Surat Izin Kegiatan',
    '<div class="letter-header">
        <h2>SURAT IZIN KEGIATAN</h2>
        <p>Nomor: {letter_number}</p>
    </div>
    <div class="letter-content">
        <p>Yang bertanda tangan di bawah ini:</p>
        <table>
            <tr><td>Nama</td><td>: {nama}</td></tr>
            <tr><td>Unit</td><td>: {unit}</td></tr>
        </table>
        <p>Dengan ini mengajukan permohonan izin untuk melaksanakan kegiatan:</p>
        <table>
            <tr><td>Nama Kegiatan</td><td>: {nama_kegiatan}</td></tr>
            <tr><td>Tempat</td><td>: {tempat}</td></tr>
            <tr><td>Tanggal</td><td>: {tanggal_acara}</td></tr>
            <tr><td>Waktu</td><td>: {waktu_acara}</td></tr>
            <tr><td>Tujuan</td><td>: {tujuan}</td></tr>
        </table>
        <p>Demikian permohonan ini kami sampaikan, atas perhatian dan persetujuannya kami ucapkan terima kasih.</p>
    </div>',
    '["nama", "unit", "nama_kegiatan", "tempat", "tanggal_acara", "waktu_acara", "tujuan"]',
    1
),
(
    'Surat Permohonan',
    '<div class="letter-header">
        <h2>SURAT PERMOHONAN</h2>
        <p>Nomor: {letter_number}</p>
    </div>
    <div class="letter-content">
        <p>Kepada Yth.<br>
        {tujuan}</p>
        <p>Yang bertanda tangan di bawah ini:</p>
        <table>
            <tr><td>Nama</td><td>: {nama}</td></tr>
            <tr><td>Unit</td><td>: {unit}</td></tr>
        </table>
        <p>Dengan hormat mengajukan permohonan:</p>
        <p>{isi_permohonan}</p>
        <p>Demikian permohonan ini kami sampaikan, atas perhatian dan persetujuannya kami ucapkan terima kasih.</p>
        <p>Tempat, {tanggal_surat}</p>
        <p>Hormat kami,</p>
        <br><br>
        <p>{nama}</p>
    </div>',
    '["nama", "unit", "tujuan", "isi_permohonan"]',
    1
),
(
    'Surat Keterangan',
    '<div class="letter-header">
        <h2>SURAT KETERANGAN</h2>
        <p>Nomor: {letter_number}</p>
    </div>
    <div class="letter-content">
        <p>Yang bertanda tangan di bawah ini menerangkan bahwa:</p>
        <table>
            <tr><td>Nama</td><td>: {nama}</td></tr>
            <tr><td>Unit</td><td>: {unit}</td></tr>
        </table>
        <p>{isi_keterangan}</p>
        <p>Demikian surat keterangan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>
        <p>Tempat, {tanggal_surat}</p>
    </div>',
    '["nama", "unit", "isi_keterangan"]',
    1
);

-- Create indexes for better performance
CREATE INDEX idx_letters_status ON letters(status);
CREATE INDEX idx_letters_created_at ON letters(created_at);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read);
CREATE INDEX idx_activity_logs_user_created ON activity_logs(user_id, created_at);
CREATE INDEX idx_login_attempts_username_time ON login_attempts(username, attempted_at);

-- Create views for common queries
CREATE VIEW letter_summary AS
SELECT 
    l.id,
    l.letter_number,
    l.submitter_name,
    l.status,
    l.created_at,
    t.name as template_name,
    m.full_name as manager_name,
    d.full_name as director_name
FROM letters l
LEFT JOIN templates t ON l.template_id = t.id
LEFT JOIN users m ON l.manager_action_by = m.id
LEFT JOIN users d ON l.director_action_by = d.id;

-- Create stored procedures for common operations
DELIMITER //

CREATE PROCEDURE GetLetterStats()
BEGIN
    SELECT 
        COUNT(*) as total_letters,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_letters,
        SUM(CASE WHEN status = 'manager_approved' THEN 1 ELSE 0 END) as manager_approved,
        SUM(CASE WHEN status = 'director_approved' THEN 1 ELSE 0 END) as director_approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_letters,
        SUM(CASE WHEN status = 'revision' THEN 1 ELSE 0 END) as revision_letters
    FROM letters;
END //

CREATE PROCEDURE GetMonthlyStats(IN target_year INT, IN target_month INT)
BEGIN
    SELECT 
        DAY(created_at) as day,
        COUNT(*) as letter_count
    FROM letters 
    WHERE YEAR(created_at) = target_year 
    AND MONTH(created_at) = target_month
    GROUP BY DAY(created_at)
    ORDER BY day;
END //

DELIMITER ;

-- Grant permissions (adjust as needed for your setup)
-- GRANT ALL PRIVILEGES ON letter_management.* TO 'your_user'@'localhost';
-- FLUSH PRIVILEGES;
