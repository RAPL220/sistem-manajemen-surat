# Sistem Manajemen Surat

Sistem manajemen surat berbasis web yang memungkinkan pengajuan, persetujuan, dan pelacakan surat secara digital dengan workflow approval bertingkat.

## 🚀 Fitur Utama

### 1. **Pengajuan Surat Publik (Tanpa Login)**
- Form pengajuan surat online
- Pilihan berbagai jenis template surat
- Auto-generate nomor surat
- Tracking status surat real-time

### 2. **Sistem Approval Bertingkat**
- **Manager**: Review dan persetujuan tahap pertama
- **Direktur**: Persetujuan final
- Opsi: Approve, Reject, atau Request Revision

### 3. **Manajemen Template (Admin)**
- CRUD template surat
- Dynamic form fields
- Preview template
- Placeholder variables

### 4. **Tanda Tangan Digital**
- Upload tanda tangan PNG/JPG
- Otomatis teraplikasi saat approval
- Preview dalam surat

### 5. **Monitoring & Notifikasi**
- Dashboard statistik
- Notifikasi real-time
- Activity logs
- Status tracking

### 6. **Multi-Level Access Control**
- **Admin**: Kelola template, user, monitoring
- **Manager**: Review surat, upload TTD
- **Direktur**: Final approval, upload TTD
- **Public**: Pengajuan dan tracking

## 📋 Persyaratan Sistem

- **Web Server**: Apache/Nginx
- **PHP**: 7.4 atau lebih tinggi
- **Database**: MySQL 5.7+ atau MariaDB 10.3+
- **Extensions**: PDO, GD, FileInfo
- **Storage**: Minimal 100MB untuk uploads

## 🛠️ Instalasi

### 1. Download & Extract
```bash
# Clone atau download project
git clone [repository-url]
cd sistem-manajemen-surat
```

### 2. Setup Database
```sql
-- Buat database baru
CREATE DATABASE letter_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Konfigurasi Web Server
Pastikan document root mengarah ke folder project dan PHP dapat mengakses folder uploads.

### 4. Jalankan Setup
1. Akses `http://your-domain/setup.php`
2. Ikuti wizard setup:
   - Konfigurasi database
   - Buat tabel dan data sample
   - Setup direktori upload
   - Selesaikan instalasi

### 5. Login Default
```
Administrator:
Username: admin
Password: admin123

Manager:
Username: manager  
Password: admin123

Direktur:
Username: director
Password: admin123
```

**⚠️ PENTING: Ubah password default setelah instalasi!**

## 📁 Struktur Project

```
sistem-manajemen-surat/
├── config/
│   ├── database.php          # Konfigurasi database
│   └── config.php           # Konfigurasi aplikasi
├── includes/
│   ├── functions.php        # Fungsi umum
│   ├── auth.php            # Fungsi autentikasi
│   └── notifications.php   # Fungsi notifikasi
├── assets/
│   ├── css/style.css       # Stylesheet utama
│   └── uploads/            # Direktori upload
│       ├── signatures/     # Tanda tangan digital
│       └── letters/        # Surat yang dihasilkan
├── public/
│   ├── index.php          # Form pengajuan surat
│   └── track.php          # Tracking surat
├── admin/
│   ├── login.php          # Login admin
│   ├── dashboard.php      # Dashboard admin
│   ├── templates.php      # Kelola template
│   └── users.php          # Kelola user
├── manager/
│   ├── login.php          # Login manager
│   ├── dashboard.php      # Dashboard manager
│   ├── review.php         # Review surat
│   └── signature.php      # Upload TTD
├── director/
│   ├── login.php          # Login direktur
│   ├── dashboard.php      # Dashboard direktur
│   ├── review.php         # Review surat
│   └── signature.php      # Upload TTD
├── database.sql           # Schema database
├── setup.php             # Wizard instalasi
└── README.md             # Dokumentasi
```

## 🔄 Workflow Sistem

### 1. Pengajuan Surat
```
Pemohon → Form Pengajuan → Pilih Template → Isi Data → Submit
```

### 2. Proses Approval
```
Pengajuan → Manager Review → Direktur Review → Surat Terbit
     ↓           ↓              ↓
   Pending → Approved → Final Approved
     ↓           ↓              ↓
  Rejected ← Revision ← Rejected
```

### 3. Status Surat
- **Pending**: Menunggu review Manager
- **Manager Approved**: Menunggu review Direktur  
- **Director Approved**: Surat disetujui dan terbit
- **Rejected**: Surat ditolak
- **Revision**: Perlu perbaikan

## 🎨 Template Surat

### Format Template
Template menggunakan placeholder variables:
```html
<h2>SURAT IZIN KEGIATAN</h2>
<p>Nomor: {letter_number}</p>

<p>Yang bertanda tangan di bawah ini:</p>
<table>
    <tr><td>Nama</td><td>: {nama}</td></tr>
    <tr><td>Unit</td><td>: {unit}</td></tr>
</table>

<p>Mengajukan permohonan izin untuk:</p>
<table>
    <tr><td>Kegiatan</td><td>: {nama_kegiatan}</td></tr>
    <tr><td>Tempat</td><td>: {tempat}</td></tr>
    <tr><td>Tanggal</td><td>: {tanggal_acara}</td></tr>
</table>
```

### Variables Tersedia
- `{nama}` - Nama pemohon
- `{unit}` - Unit/bagian
- `{tujuan}` - Tujuan surat
- `{tempat}` - Tempat kegiatan
- `{tanggal_acara}` - Tanggal kegiatan
- `{waktu_acara}` - Waktu kegiatan
- `{letter_number}` - Nomor surat (auto)
- `{tanggal_surat}` - Tanggal surat (auto)

## 🔐 Keamanan

### Fitur Keamanan
- **CSRF Protection**: Token keamanan pada form
- **SQL Injection Prevention**: Prepared statements
- **File Upload Validation**: Type dan size validation
- **Session Management**: Timeout dan regeneration
- **Rate Limiting**: Login attempt limiting
- **Input Sanitization**: XSS prevention

### Best Practices
1. Ubah password default
2. Gunakan HTTPS di production
3. Set proper file permissions
4. Regular backup database
5. Update PHP dan dependencies
6. Monitor activity logs

## 📊 Database Schema

### Tabel Utama
- **users**: Data pengguna (admin, manager, direktur)
- **templates**: Template surat
- **letters**: Data pengajuan surat
- **notifications**: Notifikasi sistem
- **activity_logs**: Log aktivitas
- **login_attempts**: Log percobaan login

### Relasi
```
users (1) → (n) letters (manager_action_by)
users (1) → (n) letters (director_action_by)
templates (1) → (n) letters
users (1) → (n) notifications
letters (1) → (n) notifications
```

## 🚀 Deployment

### Production Setup
1. **Web Server Configuration**
   ```apache
   # .htaccess
   RewriteEngine On
   RewriteCond %{REQUEST_FILENAME} !-f
   RewriteCond %{REQUEST_FILENAME} !-d
   RewriteRule ^(.*)$ index.php [QSA,L]
   ```

2. **PHP Configuration**
   ```ini
   upload_max_filesize = 10M
   post_max_size = 10M
   max_execution_time = 300
   memory_limit = 256M
   ```

3. **Database Optimization**
   ```sql
   -- Add indexes for performance
   CREATE INDEX idx_letters_status ON letters(status);
   CREATE INDEX idx_letters_created ON letters(created_at);
   ```

4. **Security Headers**
   ```apache
   Header always set X-Content-Type-Options nosniff
   Header always set X-Frame-Options DENY
   Header always set X-XSS-Protection "1; mode=block"
   ```

## 🔧 Kustomisasi

### Menambah Template Baru
1. Login sebagai Admin
2. Masuk ke "Template Surat"
3. Klik "Tambah Template Baru"
4. Isi nama dan konten template
5. Pilih field yang diperlukan
6. Simpan template

### Menambah Field Baru
Edit file `admin/templates.php` dan tambahkan field di array `$fieldTypes`:
```php
$fieldTypes = [
    'nama' => 'Nama',
    'unit' => 'Unit/Bagian',
    'field_baru' => 'Label Field Baru', // Tambahkan ini
    // ...
];
```

### Mengubah Tampilan
Edit file `assets/css/style.css` untuk menyesuaikan tampilan sesuai brand/kebutuhan.

## 🐛 Troubleshooting

### Masalah Umum

**1. Database Connection Error**
- Periksa konfigurasi di `config/database.php`
- Pastikan MySQL service berjalan
- Cek username/password database

**2. File Upload Error**
- Periksa permission folder `assets/uploads/`
- Cek setting `upload_max_filesize` di PHP
- Pastikan folder dapat ditulis web server

**3. Session Error**
- Periksa permission folder session PHP
- Cek setting `session.save_path`
- Pastikan cookies enabled di browser

**4. Template Not Loading**
- Periksa data di tabel `templates`
- Cek format JSON di field `fields_required`
- Pastikan template memiliki placeholder yang benar

### Log Files
- **PHP Error Log**: `/var/log/php/error.log`
- **Apache Error Log**: `/var/log/apache2/error.log`
- **Application Log**: Cek tabel `activity_logs`

## 📞 Support

### Dokumentasi
- Baca file README.md ini
- Cek komentar di source code
- Lihat database schema di `database.sql`

### Development
- Framework: Pure PHP (no framework)
- Database: MySQL dengan PDO
- Frontend: HTML5, CSS3, Vanilla JavaScript
- Architecture: MVC-like structure

## 📝 Changelog

### Version 1.0.0
- ✅ Sistem pengajuan surat publik
- ✅ Workflow approval Manager → Direktur
- ✅ Template management system
- ✅ Digital signature upload
- ✅ Real-time notifications
- ✅ Letter tracking system
- ✅ Multi-level access control
- ✅ Responsive design
- ✅ Security features

### Planned Features
- 📧 Email notifications
- 📱 WhatsApp integration
- 📊 Advanced reporting
- 🔄 Bulk operations
- 📱 Mobile app
- 🌐 Multi-language support

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 👥 Contributors

- **Developer**: [Your Name]
- **Version**: 1.0.0
- **Last Updated**: 2024

---

**🎯 Sistem Manajemen Surat - Digitalisasi Proses Administrasi Surat**

Untuk pertanyaan atau dukungan teknis, silakan hubungi administrator sistem.
