<?php
/**
 * File: logout.php
 * Fungsi: Logout Handler untuk semua role (admin, guru, siswa)
 * Features:
 * - Destroy session
 * - Clear session cookies
 * - Redirect ke login page
 * - Security: Regenerate session ID
 */

session_start();

// Simpan informasi untuk tracking (opsional - bisa diabaikan jika tidak butuh logging)
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$nama = isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : null;

// Unset semua session variables
$_SESSION = array();

// Jika ada session cookie, hapus juga
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy session
session_destroy();

// Regenerate session ID untuk security
session_start();
session_regenerate_id(true);

// Set flash message
$_SESSION['logout_success'] = "Anda telah berhasil logout!";

// Redirect ke halaman login
header("Location: index.php");
exit();
?>