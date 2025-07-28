# 📦 Panduan Instalasi Sistem Manajemen Surat

## 🚀 Quick Start

### 1. Download & Extract
```bash
# Extract file sistem-manajemen-surat.zip ke web server directory
unzip sistem-manajemen-surat.zip
cd sistem-manajemen-surat
```

### 2. Setup Database
```sql
-- Buat database MySQL/MariaDB
CREATE DATABASE letter_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Konfigurasi Web Server
- Pastikan PHP 7.4+ dengan extensions: PDO, GD, FileInfo
- Set document root ke folder project
- Pastikan folder `assets/uploads/` dapat ditulis

### 4. Jalankan Instalasi
1. Buka browser: `http://your-domain/setup.php`
2. Ikuti wizard setup (4 langkah)
3. Hapus file `setup.php` setelah selesai

### 5. Login Default
```
Admin: admin / admin123
Manager: manager / admin123
Direktur: director / admin123
```

## 📁 Struktur File

```
sistem-manajemen-surat/
├── config/           # Konfigurasi database & aplikasi
├── includes/         # Fungsi umum & autentikasi
├── assets/css/       # Stylesheet
├── public/           # Form publik & tracking
├── admin/            # Panel administrator
├── manager/          # Dashboard manager
├── director/         # Dashboard direktur
├── database.sql      # Schema database
├── setup.php         # Wizard instalasi
├── README.md         # Dokumentasi lengkap
└── PLAN.md          # Rencana pengembangan
```

## ✅ Fitur Utama

### 🌐 Akses Publik (Tanpa Login)
- **Form Pengajuan**: `public/index.php`
- **Tracking Surat**: `public/track.php`

### 👨‍💼 Panel Admin
- **Login**: `admin/login.php`
- **Dashboard**: Monitoring & statistik
- **Template**: Kelola template surat
- **User**: Kelola pengguna sistem

### 👔 Panel Manager
- **Login**: `manager/login.php`
- **Review**: Setujui/tolak surat tahap 1
- **TTD Digital**: Upload tanda tangan

### 🎩 Panel Direktur
- **Login**: `director/login.php`
- **Final Review**: Persetujuan final
- **TTD Digital**: Upload tanda tangan

## 🔧 Konfigurasi

### Database (config/database.php)
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'letter_management');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### Upload Settings (config/config.php)
```php
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_SIGNATURE_TYPES', ['image/png', 'image/jpeg']);
```

## 🔒 Keamanan

### Setelah Instalasi:
1. ✅ Ubah semua password default
2. ✅ Hapus file `setup.php`
3. ✅ Set permission folder uploads (755)
4. ✅ Aktifkan HTTPS di production
5. ✅ Backup database secara berkala

### File Permissions:
```bash
chmod 755 assets/uploads/
chmod 755 assets/uploads/signatures/
chmod 755 assets/uploads/letters/
```

## 🎯 Workflow Sistem

```
1. Pemohon mengisi form → 2. Manager review → 3. Direktur approve → 4. Surat terbit
                     ↓              ↓                ↓
                  Pending → Manager Approved → Director Approved
                     ↓              ↓                ↓
                 Rejected ←    Revision    ←    Rejected
```

## 📞 Support

### Troubleshooting:
- **Database Error**: Cek konfigurasi di `config/database.php`
- **Upload Error**: Cek permission folder `assets/uploads/`
- **Login Error**: Cek tabel `users` di database

### Log Files:
- PHP Error: `/var/log/php/error.log`
- Activity: Tabel `activity_logs` di database

## 🔄 Update System

### Manual Update:
1. Backup database dan files
2. Replace files (kecuali config/)
3. Run database migration jika ada
4. Test functionality

### Database Backup:
```bash
mysqldump -u username -p letter_management > backup.sql
```

## 📋 Checklist Post-Installation

- [ ] Database connection berhasil
- [ ] Upload directories dapat ditulis
- [ ] Form publik dapat diakses
- [ ] Login admin/manager/director berhasil
- [ ] Upload tanda tangan berhasil
- [ ] Template surat dapat dibuat
- [ ] Workflow approval berjalan
- [ ] Tracking surat berfungsi
- [ ] Password default sudah diubah
- [ ] File setup.php sudah dihapus

## 🎉 Selamat!

Sistem Manajemen Surat siap digunakan!

**URL Akses:**
- Form Publik: `http://your-domain/public/`
- Admin Panel: `http://your-domain/admin/`
- Manager Panel: `http://your-domain/manager/`
- Direktur Panel: `http://your-domain/director/`

---
*Sistem Manajemen Surat v1.0 - Digitalisasi Proses Administrasi*
