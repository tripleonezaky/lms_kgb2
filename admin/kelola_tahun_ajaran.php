<?php
/**
 * File: admin/kelola_tahun_ajaran.php
 * Fungsi: CRUD Tahun Ajaran + Set Aktif (sesuai skema database)
 * Relasi Database yang digunakan:
 * - tahun_ajaran (utama)
 * - assignment_guru.tahun_ajaran_id → tahun_ajaran.id (FOREIGN KEY - ON DELETE CASCADE)
 * Catatan skema:
 * - Kolom: nama_tahun_ajaran (unik), semester enum('1','2'), tanggal_mulai, tanggal_selesai, is_active (0/1)
 * - Tidak ada kolom tahun_ajaran_id pada tabel kelas. Oleh karena itu, total_kelas tidak ditampilkan di sini.
 */

require_once __DIR__ . '/../includes/auth_admin.php';
require_once '../config/database.php';

// Auto-switch semester dinonaktifkan karena semester tidak lagi melekat pada Tahun Ajaran

// =============================================
// HELPER
// =============================================
// Semester tidak lagi digunakan pada Tahun Ajaran

// =============================================
// PROSES TAMBAH TAHUN AJARAN
// =============================================
if (isset($_POST['tambah_ta'])) {
    $tahun_ajaran = mysqli_real_escape_string($conn, trim($_POST['tahun_ajaran'])); // form field tetap 'tahun_ajaran'
    $tanggal_mulai = mysqli_real_escape_string($conn, trim($_POST['tanggal_mulai']));
    $tanggal_selesai = mysqli_real_escape_string($conn, trim($_POST['tanggal_selesai']));
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validasi format dasar (semester tidak digunakan lagi)

    // Validasi: Cek duplikasi tahun ajaran
    $check_query = "SELECT id FROM tahun_ajaran WHERE nama_tahun_ajaran = '$tahun_ajaran'";
    $check_result = mysqli_query($conn, $check_query);

    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $_SESSION['error'] = "Tahun ajaran $tahun_ajaran sudah ada!";
    } else {
        // Jika set aktif, nonaktifkan tahun ajaran lain
        if ($is_active === 1) {
            mysqli_query($conn, "UPDATE tahun_ajaran SET is_active = 0");
        }

        // Insert data sesuai kolom skema
        $insert_query = "
            INSERT INTO tahun_ajaran (nama_tahun_ajaran, tanggal_mulai, tanggal_selesai, is_active)
            VALUES ('$tahun_ajaran', '$tanggal_mulai', '$tanggal_selesai', $is_active)
        ";

        if (mysqli_query($conn, $insert_query)) {
            $_SESSION['success'] = 'Tahun ajaran berhasil ditambahkan!';
        } else {
            $_SESSION['error'] = 'Gagal menambahkan tahun ajaran: ' . mysqli_error($conn);
        }
    }

    header('Location: kelola_tahun_ajaran.php');
    exit();
}

// =============================================
// PROSES EDIT TAHUN AJARAN
// =============================================
if (isset($_POST['edit_ta'])) {
    $id = (int)$_POST['id'];
    $tahun_ajaran = mysqli_real_escape_string($conn, trim($_POST['tahun_ajaran']));
    $tanggal_mulai = mysqli_real_escape_string($conn, trim($_POST['tanggal_mulai']));
    $tanggal_selesai = mysqli_real_escape_string($conn, trim($_POST['tanggal_selesai']));
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Semester tidak digunakan lagi

    // Validasi: Cek duplikasi (kecuali diri sendiri)
    $check_query = "
        SELECT id FROM tahun_ajaran 
        WHERE nama_tahun_ajaran = '$tahun_ajaran' 
        AND id != $id
    ";
    $check_result = mysqli_query($conn, $check_query);

    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $_SESSION['error'] = "Tahun ajaran $tahun_ajaran sudah ada!";
    } else {
        if ($is_active === 1) {
            mysqli_query($conn, "UPDATE tahun_ajaran SET is_active = 0");
        }

        $update_query = "
            UPDATE tahun_ajaran 
            SET nama_tahun_ajaran = '$tahun_ajaran',
                tanggal_mulai = '$tanggal_mulai',
                tanggal_selesai = '$tanggal_selesai',
                is_active = $is_active
            WHERE id = $id
        ";

        if (mysqli_query($conn, $update_query)) {
            $_SESSION['success'] = 'Tahun ajaran berhasil diupdate!';
        } else {
            $_SESSION['error'] = 'Gagal mengupdate tahun ajaran: ' . mysqli_error($conn);
        }
    }

    header('Location: kelola_tahun_ajaran.php');
    exit();
}

// =============================================
// PROSES SET AKTIF TAHUN AJARAN
// =============================================
if (isset($_GET['set_aktif'])) {
    $id = (int)$_GET['set_aktif'];

    mysqli_query($conn, "UPDATE tahun_ajaran SET is_active = 0");
    $update_query = "UPDATE tahun_ajaran SET is_active = 1 WHERE id = $id";

    if (mysqli_query($conn, $update_query)) {
        $_SESSION['success'] = 'Tahun ajaran berhasil diaktifkan!';
    } else {
        $_SESSION['error'] = 'Gagal mengaktifkan tahun ajaran: ' . mysqli_error($conn);
    }

    header('Location: kelola_tahun_ajaran.php');
    exit();
}

// =============================================
// PROSES HAPUS TAHUN AJARAN
// =============================================
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];

    // Validasi: Cek apakah tahun ajaran masih dipakai di assignment
    $check_assignment = "SELECT COUNT(*) as total FROM assignment_guru WHERE tahun_ajaran_id = $id";
    $result_check_assignment = mysqli_query($conn, $check_assignment);
    $data_check_assignment = $result_check_assignment ? mysqli_fetch_assoc($result_check_assignment) : ['total' => 0];

    if ((int)$data_check_assignment['total'] > 0) {
        $_SESSION['error'] = 'Tidak dapat menghapus tahun ajaran! Masih ada data assignment terkait.';
    } else {
        $delete_query = "DELETE FROM tahun_ajaran WHERE id = $id";
        if (mysqli_query($conn, $delete_query)) {
            $_SESSION['success'] = 'Tahun ajaran berhasil dihapus!';
        } else {
            $_SESSION['error'] = 'Gagal menghapus tahun ajaran: ' . mysqli_error($conn);
        }
    }

    header('Location: kelola_tahun_ajaran.php');
    exit();
}

// =============================================
// AMBIL DATA TAHUN AJARAN + TOTAL ASSIGNMENT
// =============================================
$query = "
    SELECT 
        ta.*,
        COUNT(DISTINCT ag.id) as total_assignment
    FROM tahun_ajaran ta
    LEFT JOIN assignment_guru ag ON ta.id = ag.tahun_ajaran_id
    GROUP BY ta.id
    ORDER BY ta.nama_tahun_ajaran DESC
";
$result = mysqli_query($conn, $query);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Tahun Ajaran - LMS KGB2</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/_overrides.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h1><i class="fas fa-calendar" aria-hidden="true"></i> Kelola Tahun Ajaran</h1>
        <div class="user-info">
            <span>Admin: <strong><?php echo htmlspecialchars(($_SESSION['nama_lengkap']) ?? ''); ?></strong></span>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="content-area">
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><i class="fas fa-times-circle" aria-hidden="true"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle" aria-hidden="true"></i> <strong>Penting:</strong> Hanya boleh ada 1 tahun ajaran aktif. Jika mengaktifkan tahun ajaran baru, tahun ajaran lain akan otomatis non-aktif.
        </div>

        <div class="card">
            <div class="card-header">
                <h3>📋 Daftar Tahun Ajaran</h3>
            </div>
            <div class="card-body">
                <div class="card-toolbar" style="display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap; margin-bottom:12px;">
                    <button class="btn btn-primary" onclick="openModal('modalTambah')">➕ Tambah Tahun Ajaran</button>
                </div>
                <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tahun Ajaran</th>
                                                        <th>Tanggal Mulai</th>
                            <th>Tanggal Selesai</th>
                            <th>Status</th>
                            <th>Total Assignment</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $no = 1; 
                    if ($result && mysqli_num_rows($result) > 0):
                        while ($row = mysqli_fetch_assoc($result)):
                    ?>
                        <tr style="<?php echo $row['is_active'] ? 'background: #e8f5e9;' : ''; ?>">
                            <td><?php echo $no++; ?></td>
                            <td><strong><?php echo htmlspecialchars(($row['nama_tahun_ajaran']) ?? ''); ?></strong></td>
                                                        <td><?php echo date('d/m/Y', strtotime($row['tanggal_mulai'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['tanggal_selesai'])); ?></td>
                            <td>
                                <?php if($row['is_active']): ?>
                                    <span class="badge badge-success">✓ Aktif</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">✗ Tidak Aktif</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format((int)$row['total_assignment']); ?> Assignment</td>
                            <td>
                                <?php if(!$row['is_active']): ?>
                                <a href="?set_aktif=<?php echo $row['id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Aktifkan tahun ajaran ini?')">✓ Aktifkan</a>
                                <?php endif; ?>

                                <button class="btn btn-warning btn-sm" onclick='editTA(<?php echo json_encode($row, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'><i class="fas fa-pen" aria-hidden="true"></i> Edit</button>

                                <a href="?hapus=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete('Hapus tahun ajaran <?php echo htmlspecialchars(($row['nama_tahun_ajaran']) ?? ''); ?>?')">🗑️ Hapus</a>
                                <a href="delete_force.php?entity=tahun_ajaran&id=<?php echo $row['id']; ?>&redirect=<?php echo urlencode('kelola_tahun_ajaran.php'); ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus Paksa: Tahun ajaran dan seluruh assignment/nilai terkait akan dihapus. Lanjutkan?')"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> Hapus Paksa</a>
                            </td>
                        </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: #999;">Belum ada data tahun ajaran</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            </div>
        </div>

    </div>
</div>

<!-- MODAL TAMBAH TAHUN AJARAN -->
<div id="modalTambah" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>➕ Tambah Tahun Ajaran</h3>
            <button class="modal-close" onclick="closeModal('modalTambah')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Tahun Ajaran *</label>
                        <input type="text" name="tahun_ajaran" required maxlength="20" placeholder="Contoh: 2025/2026">
                    </div>
                                    </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Tanggal Mulai *</label>
                        <input type="date" name="tanggal_mulai" required>
                    </div>
                    <div class="form-group">
                        <label>Tanggal Selesai *</label>
                        <input type="date" name="tanggal_selesai" required>
                    </div>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="is_active" value="1">
                        <span>Set sebagai Tahun Ajaran Aktif</span>
                    </label>
                    <small style="color: #999;">*Jika dicentang, tahun ajaran lain akan otomatis non-aktif</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="closeModal('modalTambah')">Batal</button>
                <button type="submit" name="tambah_ta" class="btn btn-success"><i class="fas fa-save" aria-hidden="true"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT TAHUN AJARAN -->
<div id="modalEdit" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-pen" aria-hidden="true"></i> Edit Tahun Ajaran</h3>
            <button class="modal-close" onclick="closeModal('modalEdit')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Tahun Ajaran *</label>
                        <input type="text" name="tahun_ajaran" id="edit_tahun_ajaran" required maxlength="20">
                    </div>
                                    </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Tanggal Mulai *</label>
                        <input type="date" name="tanggal_mulai" id="edit_tanggal_mulai" required>
                    </div>
                    <div class="form-group">
                        <label>Tanggal Selesai *</label>
                        <input type="date" name="tanggal_selesai" id="edit_tanggal_selesai" required>
                    </div>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                        <span>Set sebagai Tahun Ajaran Aktif</span>
                    </label>
                    <small style="color: #999;">*Jika dicentang, tahun ajaran lain akan otomatis non-aktif</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="closeModal('modalEdit')">Batal</button>
                <button type="submit" name="edit_ta" class="btn btn-success"><i class="fas fa-save" aria-hidden="true"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/script.js"></script>
<script>
function editTA(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_tahun_ajaran').value = data.nama_tahun_ajaran;
        document.getElementById('edit_tanggal_mulai').value = data.tanggal_mulai;
    document.getElementById('edit_tanggal_selesai').value = data.tanggal_selesai;
    document.getElementById('edit_is_active').checked = parseInt(data.is_active, 10) === 1;
    openModal('modalEdit');
}
</script>
</body>
</html>

