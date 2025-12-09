<?php
/**
 * File: includes/auth_admin.php
 * Tujuan: Proteksi halaman Admin dengan satu include.
 * Penggunaan (di baris paling awal setiap file admin/*.php):
 *   <?php require_once __DIR__ . '/../includes/auth_admin.php'; ?>
 */

// Pastikan error reporting wajar saat pengembangan
if (!defined('APP_ENV')) {
    define('APP_ENV', 'development'); // ganti ke 'production' saat live
}
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
    ini_set('display_errors', 0);
}

// Muat proteksi sesi dan role
require_once __DIR__ . '/check_session.php';
require_once __DIR__ . '/check_role.php';

// Batasi hanya role admin
check_role(['admin']);

// Opsional: header keamanan dasar
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer-when-downgrade');
// Catatan: Untuk CSP sebaiknya dirancang sesuai aset yang digunakan aplikasi.
