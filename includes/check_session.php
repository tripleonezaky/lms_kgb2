<?php
/**
 * File: includes/check_session.php
 * Fungsi: Memastikan user sudah login dan menerapkan timeout sesi.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hitung base URL aplikasi (segment pertama setelah root), contoh: /lms_kgb2
$script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/';
$segments = explode('/', trim($script, '/'));
$base = '/' . ($segments[0] ?? '');
$login_url = rtrim($base, '/') . '/index.php';

// Tambahkan header anti-cache untuk halaman terproteksi
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Pastikan user sudah login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    $_SESSION['error'] = 'Anda harus login terlebih dahulu!';
    header('Location: ' . $login_url);
    exit();
}

// Timeout session (30 menit)
$timeout = 1800; // detik
if (isset($_SESSION['login_time'])) {
    $elapsed = time() - (int)$_SESSION['login_time'];
    if ($elapsed > $timeout) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['error'] = 'Session Anda telah berakhir. Silakan login kembali.';
        header('Location: ' . $login_url);
        exit();
    }
}

// Update time stamp
$_SESSION['login_time'] = time();

// Inject global JS for UI behaviors (hamburger toggle, etc.) on HTML requests only
if (!defined('LMS_JS_INCLUDED')) {
    define('LMS_JS_INCLUDED', true);
    $accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
    if (strpos($accept, 'text/html') !== false) {
        echo '<script src="' . htmlspecialchars($base, ENT_QUOTES, 'UTF-8') . '/assets/js/script.js"></script>';
    }
}
