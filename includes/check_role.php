<?php
/**
 * File: includes/check_role.php
 * Fungsi: Cek role user, jika tidak sesuai redirect ke dashboard sesuai role
 *
 * Catatan: Sertakan terlebih dahulu check_session.php pada halaman terproteksi.
 * include '../includes/check_session.php';
 * include '../includes/check_role.php';
 * check_role(['admin', 'guru']);
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function check_role($allowed_roles = []) {
    if (!isset($_SESSION['role'])) {
        $_SESSION['error'] = 'Anda harus login terlebih dahulu!';
        header('Location: ../index.php');
        exit();
    }

    if (!in_array($_SESSION['role'], $allowed_roles)) {
        $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman ini!';
        switch ($_SESSION['role']) {
            case 'admin':
                header('Location: ../admin/dashboard.php');
                break;
            case 'guru':
                header('Location: ../guru/dashboard.php');
                break;
            case 'siswa':
                header('Location: ../siswa/dashboard.php');
                break;
            default:
                header('Location: ../index.php');
        }
        exit();
    }
}
?>