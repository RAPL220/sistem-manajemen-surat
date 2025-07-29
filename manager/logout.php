<?php
/**
 * Manager Logout
 * Sistem Manajemen Surat
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Logout user
logoutUser();

// Redirect to login page
header('Location: login.php');
exit;
?>
