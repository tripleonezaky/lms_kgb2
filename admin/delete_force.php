<?php
/**
 * File: admin/delete_force.php
 * Fitur: Hapus Paksa terpusat untuk Admin pada berbagai master data
 * Entity didukung:
 *  - guru -> users (role='guru')
 *  - siswa -> users (role='siswa')
 *  - kelas -> kelas
 *  - mapel -> mata_pelajaran
 *  - tahun_ajaran -> tahun_ajaran
 *  - komponen_nilai -> tbl_komponen_nilai (+ cleanup manual tbl_nilai)
 *  - assignment -> assignment_guru
 * Catatan: Mayoritas relasi telah ON DELETE CASCADE sesuai skema DB.
 *          Pembersihan manual diperlukan untuk entity tanpa FK (mis. tbl_nilai vs tbl_komponen_nilai).
 */

require_once __DIR__ . '/../includes/auth_admin.php';
require_once __DIR__ . '/../config/database.php';

function redirect_back($fallback = 'dashboard.php'){
    $to = isset($_GET['redirect']) ? $_GET['redirect'] : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $fallback);
    header('Location: ' . $to);
    exit();
}

$entity = isset($_GET['entity']) ? trim($_GET['entity']) : '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($entity === '' || $id <= 0) {
    $_SESSION['error'] = 'Parameter penghapusan tidak valid.';
    redirect_back();
}

mysqli_begin_transaction($conn);
try {
    $affected_info = [];

    switch ($entity) {
        case 'guru': {
            // Pastikan benar-benar guru
            $cek = mysqli_query($conn, "SELECT id FROM users WHERE id=$id AND role='guru' LIMIT 1");
            if (!$cek || mysqli_num_rows($cek) === 0) throw new Exception('Data guru tidak ditemukan.');
            // Hapus user guru -> cascade ke assignment_guru, soal, materi, tugas, dll.
            $ok = mysqli_query($conn, "DELETE FROM users WHERE id=$id AND role='guru'");
            if (!$ok) throw new Exception('Gagal menghapus guru: ' . mysqli_error($conn));
            $affected_info[] = 'Guru & assignment terkait (cascade) terhapus.';
            break;
        }
        case 'siswa': {
            $cek = mysqli_query($conn, "SELECT id FROM users WHERE id=$id AND role='siswa' LIMIT 1");
            if (!$cek || mysqli_num_rows($cek) === 0) throw new Exception('Data siswa tidak ditemukan.');
            $ok = mysqli_query($conn, "DELETE FROM users WHERE id=$id AND role='siswa'");
            if (!$ok) throw new Exception('Gagal menghapus siswa: ' . mysqli_error($conn));
            $affected_info[] = 'Siswa & data nilai/pengumpulan/jawaban/log (cascade) terhapus.';
            break;
        }
        case 'kelas': {
            // Menghapus kelas -> cascade ke assignment_guru, nilai, nilai_deskripsi; users.kelas_id SET NULL
            $ok = mysqli_query($conn, "DELETE FROM kelas WHERE id=$id");
            if (!$ok) throw new Exception('Gagal menghapus kelas: ' . mysqli_error($conn));
            $affected_info[] = 'Kelas & assignment/nilai terkait (cascade) terhapus. Siswa di kelas ini di-set NULL.';
            break;
        }
        case 'mapel': {
            $ok = mysqli_query($conn, "DELETE FROM mata_pelajaran WHERE id=$id");
            if (!$ok) throw new Exception('Gagal menghapus mata pelajaran: ' . mysqli_error($conn));
            $affected_info[] = 'Mata pelajaran & assignment/nilai terkait (cascade) terhapus.';
            break;
        }
        case 'tahun_ajaran': {
            $ok = mysqli_query($conn, "DELETE FROM tahun_ajaran WHERE id=$id");
            if (!$ok) throw new Exception('Gagal menghapus tahun ajaran: ' . mysqli_error($conn));
            $affected_info[] = 'Tahun ajaran & assignment/nilai terkait (cascade) terhapus.';
            break;
        }
        case 'komponen_nilai': {
            // Tidak ada FK dari tbl_nilai ke tbl_komponen_nilai -> bersihkan manual
            $delNilai = mysqli_query($conn, "DELETE FROM tbl_nilai WHERE id_komponen=$id");
            if ($delNilai === false) throw new Exception('Gagal membersihkan nilai terkait komponen: ' . mysqli_error($conn));
            $ok = mysqli_query($conn, "DELETE FROM tbl_komponen_nilai WHERE id_komponen=$id");
            if (!$ok) throw new Exception('Gagal menghapus komponen nilai: ' . mysqli_error($conn));
            $affected_info[] = 'Komponen nilai & nilai terkait (clean) terhapus.';
            break;
        }
        case 'assignment': {
            // Hapus assignment -> cascade ke materi, soal, tugas, dll.
            $ok = mysqli_query($conn, "DELETE FROM assignment_guru WHERE id=$id");
            if (!$ok) throw new Exception('Gagal menghapus assignment: ' . mysqli_error($conn));
            $affected_info[] = 'Assignment & materi/soal/tugas terkait (cascade) terhapus.';
            break;
        }
        default:
            throw new Exception('Entity tidak dikenali untuk hapus paksa.');
    }

    mysqli_commit($conn);
    $_SESSION['success'] = 'Hapus paksa berhasil. ' . (!empty($affected_info) ? implode(' ', $affected_info) : '');
    redirect_back();
} catch (Exception $ex) {
    mysqli_rollback($conn);
    $_SESSION['error'] = 'Hapus paksa gagal: ' . $ex->getMessage();
    redirect_back();
}
