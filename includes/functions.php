<?php
/**
 * File: includes/functions.php
 * Fungsi: Helper functions untuk seluruh aplikasi
 */

// Start session jika belum
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Fungsi untuk mengecek apakah user sudah login
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Fungsi untuk redirect
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Fungsi untuk set flash message
 */
function set_flash($type, $message) {
    $_SESSION['flash_type'] = $type;    // success, error, warning, info
    $_SESSION['flash_message'] = $message;
}

/**
 * Fungsi untuk get dan hapus flash message
 */
function get_flash() {
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_type'];
        $message = $_SESSION['flash_message'];
        
        unset($_SESSION['flash_type']);
        unset($_SESSION['flash_message']);
        
        return ['type' => $type, 'message' => $message];
    }
    return null;
}

/**
 * Fungsi untuk format tanggal Indonesia
 */
function format_tanggal($date, $format = 'd F Y') {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $timestamp = strtotime($date);
    $d = date('d', $timestamp);
    $m = date('n', $timestamp);
    $y = date('Y', $timestamp);
    
    return $d . ' ' . $bulan[$m] . ' ' . $y;
}

/**
 * Fungsi untuk upload file
 */
function upload_file($file, $folder = 'temp', $allowed_types = ['jpg', 'jpeg', 'png', 'pdf']) {
    $upload_dir = "../assets/uploads/" . $folder . "/";
    
    // Cek folder, buat jika belum ada
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_error = $file['error'];
    
    // Cek error
    if ($file_error !== 0) {
        return ['success' => false, 'message' => 'Error uploading file'];
    }
    
    // Cek ukuran (max 5MB)
    if ($file_size > 5242880) {
        return ['success' => false, 'message' => 'File terlalu besar (max 5MB)'];
    }
    
    // Cek ekstensi
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_types)) {
        return ['success' => false, 'message' => 'Tipe file tidak diizinkan'];
    }
    
    // Rename file dengan timestamp
    $new_file_name = uniqid() . '_' . time() . '.' . $file_ext;
    $destination = $upload_dir . $new_file_name;
    
    // Upload
    if (move_uploaded_file($file_tmp, $destination)) {
        return [
            'success' => true, 
            'file_name' => $new_file_name,
            'file_path' => $folder . '/' . $new_file_name
        ];
    }
    
    return ['success' => false, 'message' => 'Gagal mengupload file'];
}

/**
 * Fungsi untuk delete file
 */
function delete_file($file_path) {
    $full_path = "../assets/uploads/" . $file_path;
    if (file_exists($full_path)) {
        return unlink($full_path);
    }
    return false;
}

/**
 * Fungsi untuk sanitize input
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars(($data) ?? '');
    return $data;
}

/**
 * Fungsi untuk generate password random
 */
function generate_password($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

/**
 * Fungsi untuk hitung grade
 */
function hitung_grade($nilai) {
    if ($nilai >= 90) return 'A';
    if ($nilai >= 80) return 'B';
    if ($nilai >= 70) return 'C';
    if ($nilai >= 60) return 'D';
    return 'E';
}
/**
 * Ambil Tahun Ajaran Aktif
 * @return array|null { id, nama_tahun_ajaran, semester } atau null jika belum di-set aktif
 */
function get_tahun_ajaran_aktif() {
    $sql = "SELECT id, nama_tahun_ajaran, semester FROM tahun_ajaran WHERE is_active = 1 LIMIT 1";
    // Gunakan helper query() bila tersedia, jika tidak fallback ke mysqli_query
    if (function_exists('query')) {
        $res = query($sql);
        if ($res) {
            return function_exists('fetch_assoc') ? (fetch_assoc($res) ?: null) : null;
        }
        return null;
    }
    if (isset($GLOBALS['conn'])) {
        $res = mysqli_query($GLOBALS['conn'], $sql);
        return $res ? (mysqli_fetch_assoc($res) ?: null) : null;
    }
    return null;
}

/**
 * Ambil ID Tahun Ajaran Aktif
 * @return int|null
 */
function get_tahun_ajaran_aktif_id() {
    $ta = get_tahun_ajaran_aktif();
    return $ta ? (int)$ta['id'] : null;
}

?>
