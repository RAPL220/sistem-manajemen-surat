<?php
/**
 * Setup Script for Sistem Manajemen Surat
 * Run this file once to initialize the system
 */

// Check if setup has already been run
if (file_exists('setup_complete.txt')) {
    die('Setup has already been completed. Delete setup_complete.txt to run setup again.');
}

$message = '';
$messageType = '';
$step = $_GET['step'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'test_db') {
        // Test database connection
        $host = $_POST['db_host'] ?? 'localhost';
        $dbname = $_POST['db_name'] ?? 'letter_management';
        $username = $_POST['db_user'] ?? 'root';
        $password = $_POST['db_pass'] ?? '';
        
        try {
            $dsn = "mysql:host=$host;charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password);
            
            // Try to create database if it doesn't exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbname`");
            
            $message = 'Database connection successful!';
            $messageType = 'success';
            
            // Update config file
            $configContent = file_get_contents('config/database.php');
            $configContent = str_replace("define('DB_HOST', 'localhost');", "define('DB_HOST', '$host');", $configContent);
            $configContent = str_replace("define('DB_NAME', 'letter_management');", "define('DB_NAME', '$dbname');", $configContent);
            $configContent = str_replace("define('DB_USER', 'root');", "define('DB_USER', '$username');", $configContent);
            $configContent = str_replace("define('DB_PASS', '');", "define('DB_PASS', '$password');", $configContent);
            file_put_contents('config/database.php', $configContent);
            
            $step = 2;
        } catch (PDOException $e) {
            $message = 'Database connection failed: ' . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($action === 'create_tables') {
        // Create database tables
        try {
            require_once 'config/database.php';
            $db = getDB();
            
            // Read and execute SQL file
            $sql = file_get_contents('database.sql');
            
            // Split SQL into individual statements
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            foreach ($statements as $statement) {
                if (!empty($statement) && !preg_match('/^(--|\/\*|\*)/', $statement)) {
                    $db->exec($statement);
                }
            }
            
            $message = 'Database tables created successfully!';
            $messageType = 'success';
            $step = 3;
        } catch (Exception $e) {
            $message = 'Error creating tables: ' . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($action === 'create_directories') {
        // Create necessary directories
        $directories = [
            'assets/uploads',
            'assets/uploads/signatures',
            'assets/uploads/letters'
        ];
        
        $created = [];
        $errors = [];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (mkdir($dir, 0755, true)) {
                    $created[] = $dir;
                } else {
                    $errors[] = $dir;
                }
            }
        }
        
        if (empty($errors)) {
            $message = 'Directories created successfully: ' . implode(', ', $created);
            $messageType = 'success';
            $step = 4;
        } else {
            $message = 'Error creating directories: ' . implode(', ', $errors);
            $messageType = 'error';
        }
    } elseif ($action === 'complete_setup') {
        // Mark setup as complete
        file_put_contents('setup_complete.txt', date('Y-m-d H:i:s'));
        $message = 'Setup completed successfully!';
        $messageType = 'success';
        $step = 5;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Sistem Manajemen Surat</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .setup-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .setup-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .setup-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
        }
        
        .setup-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 15px;
            right: -50%;
            width: 100%;
            height: 2px;
            background: #e0e0e0;
            z-index: 1;
        }
        
        .setup-step.active::after {
            background: #000;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }
        
        .setup-step.active .step-number {
            background: #000;
            color: #fff;
        }
        
        .setup-step.completed .step-number {
            background: #28a745;
            color: #fff;
        }
        
        .step-title {
            font-size: 0.875rem;
            text-align: center;
        }
        
        .code-block {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 1rem;
            font-family: monospace;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="text-center mb-3">
            <h1>Setup Sistem Manajemen Surat</h1>
            <p>Ikuti langkah-langkah berikut untuk mengatur sistem</p>
        </div>

        <!-- Progress Steps -->
        <div class="setup-steps">
            <div class="setup-step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                <div class="step-number">1</div>
                <div class="step-title">Database</div>
            </div>
            <div class="setup-step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                <div class="step-number">2</div>
                <div class="step-title">Tables</div>
            </div>
            <div class="setup-step <?php echo $step >= 3 ? 'active' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?>">
                <div class="step-number">3</div>
                <div class="step-title">Directories</div>
            </div>
            <div class="setup-step <?php echo $step >= 4 ? 'active' : ''; ?> <?php echo $step > 4 ? 'completed' : ''; ?>">
                <div class="step-number">4</div>
                <div class="step-title">Complete</div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Step 1: Database Configuration -->
        <?php if ($step == 1): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Step 1: Database Configuration</h3>
                </div>
                <div class="card-content">
                    <p>Configure your database connection settings:</p>
                    
                    <form method="POST" action="setup.php">
                        <input type="hidden" name="action" value="test_db">
                        
                        <div class="form-group">
                            <label for="db_host">Database Host</label>
                            <input type="text" id="db_host" name="db_host" value="localhost" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_name">Database Name</label>
                            <input type="text" id="db_name" name="db_name" value="letter_management" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_user">Database Username</label>
                            <input type="text" id="db_user" name="db_user" value="root" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_pass">Database Password</label>
                            <input type="password" id="db_pass" name="db_pass" value="">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn">Test Connection & Continue</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Step 2: Create Tables -->
        <?php if ($step == 2): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Step 2: Create Database Tables</h3>
                </div>
                <div class="card-content">
                    <p>Create the required database tables and insert sample data:</p>
                    
                    <div class="alert alert-info">
                        <h4>What will be created:</h4>
                        <ul>
                            <li>Users table (with default admin, manager, director accounts)</li>
                            <li>Templates table (with sample letter templates)</li>
                            <li>Letters table (for storing letter submissions)</li>
                            <li>Notifications table (for system notifications)</li>
                            <li>Activity logs and login attempts tables</li>
                        </ul>
                    </div>
                    
                    <form method="POST" action="setup.php">
                        <input type="hidden" name="action" value="create_tables">
                        <button type="submit" class="btn">Create Tables</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Step 3: Create Directories -->
        <?php if ($step == 3): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Step 3: Create Upload Directories</h3>
                </div>
                <div class="card-content">
                    <p>Create necessary directories for file uploads:</p>
                    
                    <div class="alert alert-info">
                        <h4>Directories to be created:</h4>
                        <ul>
                            <li><code>assets/uploads/</code> - Main upload directory</li>
                            <li><code>assets/uploads/signatures/</code> - Digital signatures</li>
                            <li><code>assets/uploads/letters/</code> - Generated letters</li>
                        </ul>
                    </div>
                    
                    <form method="POST" action="setup.php">
                        <input type="hidden" name="action" value="create_directories">
                        <button type="submit" class="btn">Create Directories</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Step 4: Complete Setup -->
        <?php if ($step == 4): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Step 4: Complete Setup</h3>
                </div>
                <div class="card-content">
                    <div class="alert alert-success">
                        <h4>Setup Almost Complete!</h4>
                        <p>All components have been configured successfully.</p>
                    </div>
                    
                    <h4>Default Login Credentials:</h4>
                    <div class="code-block">
                        <strong>Administrator:</strong><br>
                        Username: admin<br>
                        Password: admin123<br><br>
                        
                        <strong>Manager:</strong><br>
                        Username: manager<br>
                        Password: admin123<br><br>
                        
                        <strong>Director:</strong><br>
                        Username: director<br>
                        Password: admin123
                    </div>
                    
                    <div class="alert alert-warning">
                        <h4>Important Security Notes:</h4>
                        <ul>
                            <li>Change all default passwords immediately after setup</li>
                            <li>Delete this setup.php file after completion</li>
                            <li>Set proper file permissions on upload directories</li>
                            <li>Configure your web server security settings</li>
                        </ul>
                    </div>
                    
                    <form method="POST" action="setup.php">
                        <input type="hidden" name="action" value="complete_setup">
                        <button type="submit" class="btn">Complete Setup</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Step 5: Setup Complete -->
        <?php if ($step == 5): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Setup Complete!</h3>
                </div>
                <div class="card-content">
                    <div class="alert alert-success">
                        <h4>🎉 Sistem Manajemen Surat berhasil disetup!</h4>
                        <p>Sistem siap digunakan. Silakan akses halaman-halaman berikut:</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <h4>Public Access:</h4>
                            <ul>
                                <li><a href="public/index.php" target="_blank">Form Pengajuan Surat</a></li>
                                <li><a href="public/track.php" target="_blank">Lacak Status Surat</a></li>
                            </ul>
                        </div>
                        <div class="col-6">
                            <h4>Admin Access:</h4>
                            <ul>
                                <li><a href="admin/login.php" target="_blank">Login Administrator</a></li>
                                <li><a href="manager/login.php" target="_blank">Login Manager</a></li>
                                <li><a href="director/login.php" target="_blank">Login Direktur</a></li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h4>Next Steps:</h4>
                        <ol>
                            <li>Login sebagai admin dan ubah password default</li>
                            <li>Upload tanda tangan digital untuk Manager dan Direktur</li>
                            <li>Customize template surat sesuai kebutuhan</li>
                            <li>Test workflow dengan mengajukan surat sample</li>
                            <li><strong>Hapus file setup.php ini untuk keamanan</strong></li>
                        </ol>
                    </div>
                    
                    <div class="text-center">
                        <a href="public/index.php" class="btn">Mulai Menggunakan Sistem</a>
                        <a href="admin/login.php" class="btn btn-outline">Login Admin</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
