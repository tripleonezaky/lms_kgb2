<?php
/**
 * File: logout.php
 * Fungsi: Logout user dan destroy session
 */

session_start();

// Hapus semua session variables
session_unset();

// Destroy session
session_destroy();

// Redirect ke halaman login dengan pesan
session_start();
$_SESSION['success'] = "Anda berhasil logout. Silakan login kembali.";

header("Location: index.php");
exit();
?>