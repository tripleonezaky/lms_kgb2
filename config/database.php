<?php
/**
 * File: config/database.php
 * Fungsi: Koneksi database (mysqli) + helper query yang digunakan seluruh aplikasi
 */

// Konfigurasi koneksi (sesuaikan dengan environment Anda)
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'lms_kgb2';

// Inisialisasi koneksi
$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

// Set karakter koneksi
mysqli_set_charset($conn, 'utf8mb4');

/**
 * Ambil koneksi global
 */
function db() {
    global $conn;
    return $conn;
}

/**
 * Escape string aman
 */
function escape_string($str) {
    return mysqli_real_escape_string(db(), $str);
}

/**
 * Eksekusi query generik
 */
function query($sql) {
    $result = mysqli_query(db(), $sql);
    if ($result === false) {
        error_log('SQL Error: ' . mysqli_error(db()) . ' | Query: ' . $sql);
    }
    return $result;
}

/**
 * Jumlah baris hasil query
 */
function num_rows($result) {
    return $result ? mysqli_num_rows($result) : 0;
}

/**
 * Ambil satu baris sebagai array asosiatif
 */
function fetch_assoc($result) {
    return $result ? mysqli_fetch_assoc($result) : null;
}

/**
 * Ambil semua baris sebagai array asosiatif
 */
function fetch_all($result) {
    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

/**
 * ID terakhir yang diinsert
 */
function last_insert_id() {
    return mysqli_insert_id(db());
}

/**
 * Siapkan prepared statement
 */
function prepare_stmt($sql) {
    $stmt = mysqli_prepare(db(), $sql);
    if (!$stmt) {
        error_log('Prepare failed: ' . mysqli_error(db()));
    }
    return $stmt;
}

?>
