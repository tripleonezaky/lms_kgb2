<?php
/**
 * File: login_process.php
 * Fungsi: Memproses login user dengan keamanan ditingkatkan
 */

session_start();

// Include database connection
require_once 'config/database.php';

// Cek apakah form sudah disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi CSRF token
    $csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $_SESSION['error'] = 'Token CSRF tidak valid. Silakan coba lagi.';
        header('Location: index.php');
        exit();
    }

    // Ambil data dari form (tanpa interpolasi SQL langsung)
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    // Validasi input tidak boleh kosong
    if ($username === '' || $password === '') {
        $_SESSION['error'] = 'Username dan password harus diisi!';
        header('Location: index.php');
        exit();
    }

    // Gunakan prepared statements (mysqli)
    // Pastikan $conn tersedia dari config/database.php
    if (!isset($conn)) {
        $_SESSION['error'] = 'Koneksi database tidak tersedia!';
        header('Location: index.php');
        exit();
    }

    $stmt = mysqli_prepare($conn, "SELECT id, username, password, role, nama_lengkap, kode_guru, kelas_id, is_active, foto FROM users WHERE username = ? LIMIT 1");
    if (!$stmt) {
        $_SESSION['error'] = 'Terjadi kesalahan server (prep).';
        header('Location: index.php');
        exit();
    }
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);

        // Cek apakah akun aktif
        if ((int)$user['is_active'] !== 1) {
            $_SESSION['error'] = 'Akun Anda tidak aktif. Hubungi administrator!';
            header('Location: index.php');
            exit();
        }

        $hashed = $user['password'];
        $isValid = false;

        // 1) Verifikasi modern (bcrypt)
        if (preg_match('/^\\$2y\\$/', $hashed)) {
            $isValid = password_verify($password, $hashed);
        } else {
            // 2) Fallback untuk hash lama (MD5) + migrasi setelah sukses
            if (md5($password) === $hashed) {
                $isValid = true;
                $newHash = password_hash($password, PASSWORD_BCRYPT);
                $uid = (int)$user['id'];
                $stmtUpd = mysqli_prepare($conn, 'UPDATE users SET password = ? WHERE id = ? LIMIT 1');
                if ($stmtUpd) {
                    mysqli_stmt_bind_param($stmtUpd, 'si', $newHash, $uid);
                    mysqli_stmt_execute($stmtUpd);
                    mysqli_stmt_close($stmtUpd);
                }
            }
        }

        if ($isValid) {
            // Regenerasi session ID untuk mitigasi session fixation
            session_regenerate_id(true);

            // Set session konsisten
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];

            if ($user['role'] === 'guru') {
                $_SESSION['kode_guru'] = $user['kode_guru'];
            }
            if ($user['role'] === 'siswa') {
                $_SESSION['kelas_id'] = $user['kelas_id'];
            }
            $_SESSION['foto'] = $user['foto'];

            $_SESSION['login_time'] = time();

            // Redirect sesuai role
            switch ($user['role']) {
                case 'admin':
                    header('Location: admin/dashboard.php');
                    break;
                case 'guru':
                    header('Location: guru/dashboard.php');
                    break;
                case 'siswa':
                    header('Location: siswa/dashboard.php');
                    break;
                default:
                    $_SESSION['error'] = 'Role tidak valid!';
                    header('Location: index.php');
            }
            exit();
        }

        // Password salah
        $_SESSION['error'] = 'Password yang Anda masukkan salah!';
        header('Location: index.php');
        exit();
    }

    // Username tidak ditemukan
    $_SESSION['error'] = 'Username tidak ditemukan!';
    header('Location: index.php');
    exit();
} else {
    // Jika akses langsung tanpa POST
    header('Location: index.php');
    exit();
}
?>