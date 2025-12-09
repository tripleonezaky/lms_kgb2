<?php
/**
 * File: admin/kelola_siswa.php
 * Fungsi: Kelola Data Siswa (CRUD) sesuai skema database saat ini
 * Skema tabel users yang digunakan:
 *  - id, username (UNIQUE, = NISN), password, nama_lengkap, email, no_whatsapp,
 *    role ('siswa'), nisn (UNIQUE), kelas_id (nullable), is_active, created_at
 * Referensi tampilan:
 *  - kelas (id, nama_kelas, jurusan_id)
 *  - jurusan (id, nama_jurusan, singkatan)
 */

require_once __DIR__ . '/../includes/auth_admin.php';
require_once __DIR__ . '/../config/database.php';

function flash_message($key) {
    if(isset($_SESSION[$key])) { $m=$_SESSION[$key]; unset($_SESSION[$key]); return $m; }
    return null;
}

// =============================================
// PROSES TAMBAH SISWA
// =============================================
if(isset($_POST['tambah_siswa'])) {
    $nisn          = mysqli_real_escape_string($conn, trim($_POST['nisn']));
    $nama_lengkap  = mysqli_real_escape_string($conn, trim($_POST['nama_lengkap']));
    $email         = mysqli_real_escape_string($conn, trim($_POST['email']));
    $no_whatsapp   = mysqli_real_escape_string($conn, trim($_POST['no_whatsapp']));
    $kelas_id      = !empty($_POST['kelas_id']) ? (int)$_POST['kelas_id'] : 'NULL';

    // Username = NISN
    $username = $nisn;

    // Password dari form
    $password  = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $password2 = isset($_POST['password_confirm']) ? (string)$_POST['password_confirm'] : '';

    if ($password === '' || $password2 === '') {
        $_SESSION['error'] = 'Password dan konfirmasi password wajib diisi!';
        header('Location: kelola_siswa.php');
        exit();
    }
    if ($password !== $password2) {
        $_SESSION['error'] = 'Konfirmasi password tidak sama!';
        header('Location: kelola_siswa.php');
        exit();
    }
    if (strlen($password) < 6) {
        $_SESSION['error'] = 'Password minimal 6 karakter!';
        header('Location: kelola_siswa.php');
        exit();
    }

    // Validasi duplikasi
    $dup_nisn = mysqli_query($conn, "SELECT id FROM users WHERE nisn = '$nisn' LIMIT 1");
    $dup_user = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username' LIMIT 1");
    $dup_eml  = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email' LIMIT 1");

    if($dup_nisn && mysqli_num_rows($dup_nisn) > 0) {
        $_SESSION['error'] = 'NISN sudah terdaftar!';
    } elseif($dup_user && mysqli_num_rows($dup_user) > 0) {
        $_SESSION['error'] = 'Username (NISN) sudah digunakan!';
    } elseif($dup_eml && mysqli_num_rows($dup_eml) > 0) {
        $_SESSION['error'] = 'Email sudah terdaftar!';
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $no_wa_val = !empty($no_whatsapp) ? "'".$no_whatsapp."'" : 'NULL';
        $insert = "
            INSERT INTO users (username, password, role, nisn, nama_lengkap, email, no_whatsapp, kelas_id, is_active)
            VALUES ('$username', '$password_hash', 'siswa', '$nisn', '$nama_lengkap', '$email', $no_wa_val, $kelas_id, 1)
        ";
        if(mysqli_query($conn, $insert)) {
            $new_id = mysqli_insert_id($conn);
            // Upload foto jika ada
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $tmp = $_FILES['foto']['tmp_name'];
                $size = (int)$_FILES['foto']['size'];
                $type = function_exists('mime_content_type') ? mime_content_type($tmp) : $_FILES['foto']['type'];
                if (isset($allowed[$type]) && $size <= 2 * 1024 * 1024) {
                    $ext = $allowed[$type];
                    $dirPublic = 'assets/uploads/foto_profil';
                    $base = realpath(__DIR__ . '/../');
                    $dirFs = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dirPublic);
                    if (!is_dir($dirFs)) { @mkdir($dirFs, 0777, true); }
                    $fname = 'user-' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $username) . '-' . time() . '.' . $ext;
                    $destFs = $dirFs . DIRECTORY_SEPARATOR . $fname;
                    if (@move_uploaded_file($tmp, $destFs)) {
                        $pathRel = $dirPublic . '/' . $fname;
                        @mysqli_query($conn, "UPDATE users SET foto = '" . mysqli_real_escape_string($conn, $pathRel) . "' WHERE id = $new_id");
                    }
                }
            }
            $_SESSION['success'] = "Siswa berhasil ditambahkan! Username: <strong>$username</strong> (NISN).";
        } else {
            $_SESSION['error'] = 'Gagal menambahkan siswa: ' . mysqli_error($conn);
        }
    }
    header('Location: kelola_siswa.php');
    exit();
}

// =============================================
// PROSES EDIT SISWA
// =============================================
if(isset($_POST['edit_siswa'])) {
    $id            = (int)$_POST['id'];
    $nisn          = mysqli_real_escape_string($conn, trim($_POST['nisn']));
    $nama_lengkap  = mysqli_real_escape_string($conn, trim($_POST['nama_lengkap']));
    $email         = mysqli_real_escape_string($conn, trim($_POST['email']));
    $no_whatsapp   = mysqli_real_escape_string($conn, trim($_POST['no_whatsapp']));
    $kelas_id      = !empty($_POST['kelas_id']) ? (int)$_POST['kelas_id'] : 'NULL';
    $is_active     = isset($_POST['is_active']) ? 1 : 0;

    $username = $nisn; // Username = NISN (sinkron)

    // Validasi duplikasi (kecuali diri sendiri)
    $dup_nisn = mysqli_query($conn, "SELECT id FROM users WHERE nisn = '$nisn' AND id != $id LIMIT 1");
    $dup_user = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username' AND id != $id LIMIT 1");
    $dup_eml  = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email' AND id != $id LIMIT 1");

    if($dup_nisn && mysqli_num_rows($dup_nisn) > 0) {
        $_SESSION['error'] = 'NISN sudah terdaftar!';
    } elseif($dup_user && mysqli_num_rows($dup_user) > 0) {
        $_SESSION['error'] = 'Username (NISN) sudah digunakan!';
    } elseif($dup_eml && mysqli_num_rows($dup_eml) > 0) {
        $_SESSION['error'] = 'Email sudah terdaftar!';
    } else {
        $no_wa_set = !empty($no_whatsapp) ? "no_whatsapp = '".$no_whatsapp."'" : "no_whatsapp = NULL";
        $update = "
            UPDATE users SET
                username = '$username',
                nisn = '$nisn',
                nama_lengkap = '$nama_lengkap',
                email = '$email',
                $no_wa_set,
                kelas_id = $kelas_id,
                is_active = $is_active
            WHERE id = $id AND role = 'siswa'
        ";
        if(mysqli_query($conn, $update)) {
            $_SESSION['success'] = 'Data siswa berhasil diupdate!';
        } else {
            $_SESSION['error'] = 'Gagal mengupdate siswa: ' . mysqli_error($conn);
        }
    }
    header('Location: kelola_siswa.php');
    exit();
}

// =============================================
// PROSES HAPUS SISWA
// =============================================
if(isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    // Cek apakah siswa memiliki nilai
    $cek = mysqli_query($conn, "SELECT id FROM nilai WHERE siswa_id = $id LIMIT 1");
    if($cek && mysqli_num_rows($cek) > 0) {
        $_SESSION['error'] = 'Tidak dapat menghapus siswa karena sudah memiliki data nilai! Hapus nilai terlebih dahulu.';
    } else {
        $del = mysqli_query($conn, "DELETE FROM users WHERE id = $id AND role = 'siswa'");
        if($del) {
            $_SESSION['success'] = 'Siswa berhasil dihapus!';
        } else {
            $_SESSION['error'] = 'Gagal menghapus siswa: ' . mysqli_error($conn);
        }
    }
    header('Location: kelola_siswa.php');
    exit();
}

// =============================================
// PROSES RESET PASSWORD
// =============================================
if(isset($_GET['reset_password'])) {
    $id = (int)$_GET['reset_password'];
    $password_default = 'siswa123';
    $password_hash = password_hash($password_default, PASSWORD_DEFAULT);
    $reset = mysqli_query($conn, "UPDATE users SET password = '$password_hash' WHERE id = $id AND role = 'siswa'");
    if($reset) {
        $_SESSION['success'] = 'Password berhasil direset ke default: <strong>siswa123</strong>';
    } else {
        $_SESSION['error'] = 'Gagal mereset password: ' . mysqli_error($conn);
    }
    header('Location: kelola_siswa.php');
    exit();
}

// =============================================
// LIST DATA SISWA + SEARCH + PAGINATION
// =============================================
$page_size = isset($_GET['page_size']) && in_array((int)$_GET['page_size'], [10,50,100], true) ? (int)$_GET['page_size'] : 10;
$limit  = $page_size;
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

$where = "WHERE u.role = 'siswa'";
if(!empty($search)) {
    $where .= " AND (u.username LIKE '%$search%' OR u.nisn LIKE '%$search%' OR u.nama_lengkap LIKE '%$search%' OR u.email LIKE '%$search%' OR u.no_whatsapp LIKE '%$search%' OR k.nama_kelas LIKE '%$search%' OR j.singkatan LIKE '%$search%')";
}

$qData = "
    SELECT u.*, k.nama_kelas, j.singkatan
    FROM users u
    LEFT JOIN kelas k ON u.kelas_id = k.id
    LEFT JOIN jurusan j ON k.jurusan_id = j.id
    $where
    ORDER BY u.created_at DESC
    LIMIT $limit OFFSET $offset
";
$rData = mysqli_query($conn, $qData);

$qTotal = "SELECT COUNT(*) AS total FROM users u LEFT JOIN kelas k ON u.kelas_id = k.id LEFT JOIN jurusan j ON k.jurusan_id = j.id $where";
$rTotal = mysqli_query($conn, $qTotal);
$total_data = $rTotal ? (int)mysqli_fetch_assoc($rTotal)['total'] : 0;
$total_pages = max(1, (int)ceil($total_data / $limit));

// Dropdown kelas
$qKelas = "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas";
$rKelas = mysqli_query($conn, $qKelas);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Siswa - LMS KGB2</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/_overrides.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h1><i class="fas fa-user-graduate" aria-hidden="true"></i> Kelola Data Siswa</h1>
        <div class="user-info">
            <span>Admin: <strong><?php echo htmlspecialchars(($_SESSION['nama_lengkap']) ?? ''); ?></strong></span>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="content-area">
        <?php if($msg = flash_message('success')): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo $msg; ?></div>
        <?php endif; ?>
        <?php if($msg = flash_message('error')): ?>
            <div class="alert alert-danger"><i class="fas fa-times-circle" aria-hidden="true"></i> <?php echo $msg; ?></div>
        <?php endif; ?>

        <div class="alert alert-info">
            ℹ️ <strong>Info:</strong> Username siswa = <code>NISN</code> (10 digit) dan password wajib diinput manual saat tambah.
        </div>

        <div class="card">
            <div class="card-header">
                <h3>📋 Daftar Siswa</h3>
            </div>
            <div class="card-body">
                <div class="card-toolbar" style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom:12px;">
                    <form method="GET" action="" style="display:flex; gap:10px;">
                        <input type="text" name="search" placeholder="🔍 Cari NISN, Nama, Email, Username, Kelas, Jurusan..." value="<?php echo htmlspecialchars(($search) ?? ''); ?>" style="padding:8px 15px; border:1px solid #ddd; border-radius:5px; width:320px;">
                        <button type="submit" class="btn btn-secondary">Cari</button>
                        <?php if(!empty($search)): ?>
                        <a href="kelola_siswa.php" class="btn btn-secondary">Reset</a>
                        <?php endif; ?>
                    </form>
                    <button class="btn btn-primary" onclick="openModal('modalTambah')">➕ Tambah Siswa</button>
                </div>
                <div style="margin-bottom:12px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <div><strong>Total Siswa:</strong> <span class="badge badge-primary"><?php echo number_format($total_data); ?></span></div>
                    <div>
                        <label for="page_size_select" style="margin-right:6px; color:#334155;">Tampilkan</label>
                        <select id="page_size_select" onchange="changePageSize(this.value)" style="padding:6px 10px; border:1px solid #e2e8f0; border-radius:8px;">
                            <option value="10" <?php echo $page_size===10?'selected':''; ?>>10 data</option>
                            <option value="50" <?php echo $page_size===50?'selected':''; ?>>50 data</option>
                            <option value="100" <?php echo $page_size===100?'selected':''; ?>>100 data</option>
                        </select>
                    </div>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Username (NISN)</th>
                                <th>Nama Lengkap</th>
                                <th>Kelas</th>
                                <th>Jurusan</th>
                                <th>Email</th>
                                <th>No. WhatsApp</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($rData && mysqli_num_rows($rData) > 0): $no = $offset + 1; ?>
                                <?php while($row = mysqli_fetch_assoc($rData)): ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><span class="badge badge-info"><?php echo htmlspecialchars(($row['username']) ?? ''); ?></span></td>
                                    <td><strong><?php echo htmlspecialchars(($row['nama_lengkap']) ?? ''); ?></strong></td>
                                    <td><?php echo $row['nama_kelas'] ? htmlspecialchars(($row['nama_kelas']) ?? '') : '<em style="color:#999;">-</em>'; ?></td>
                                    <td><?php echo $row['singkatan'] ? '<span class="badge badge-success">'.htmlspecialchars(($row['singkatan']) ?? '').'</span>' : '<em style="color:#999;">-</em>'; ?></td>
                                    <td><?php echo isset($row['email']) && $row['email'] !== null && $row['email'] !== '' ? htmlspecialchars(($row['email']) ?? '') : '<em style="color:#999;">-</em>'; ?></td>
                                    <td><?php echo $row['no_whatsapp'] ? htmlspecialchars(($row['no_whatsapp']) ?? '') : '<em style="color:#999;">-</em>'; ?></td>
                                    <td>
                                        <?php if((int)$row['is_active'] === 1): ?>
                                            <span class="badge badge-success"><i class="fas fa-check-circle" aria-hidden="true"></i> Aktif</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger"><i class="fas fa-times-circle" aria-hidden="true"></i> Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="#" class="btn icon-btn" data-icon="edit" title="Edit" onclick='openModalEdit(<?php echo json_encode($row, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>); return false;'>Edit</a>
                                            <a href="?reset_password=<?php echo $row['id']; ?>" class="btn icon-btn" data-icon="reset" onclick="return confirm('Reset password siswa ini ke default (siswa123)?')" title="Reset Password">Reset</a>
                                            <a href="?hapus=<?php echo $row['id']; ?>" class="btn icon-btn" data-icon="delete" onclick="return confirm('Yakin ingin menghapus siswa ini?')" title="Hapus">Hapus</a>
                                            <a href="delete_force.php?entity=siswa&id=<?php echo $row['id']; ?>&redirect=<?php echo urlencode('kelola_siswa.php'); ?>" class="btn icon-btn" data-icon="force-delete" onclick="return confirm('Hapus Paksa: Siswa dan seluruh data terkait (nilai, pengumpulan, jawaban, log) akan dihapus. Lanjutkan?')" title="Hapus Paksa">Force</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align:center; padding:30px; color:#999;">Tidak ada data siswa<?php echo !empty($search) ? ' dengan pencarian &quot;' . htmlspecialchars(($search) ?? '') . '&quot;' : ''; ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php 
                    $start = $total_data > 0 ? ($offset + 1) : 0;
                    $end = min($offset + $limit, $total_data);
                ?>
                <div class="pagination-bar">
                    <div class="pagination-info">Jumlah: <?php echo $start; ?>–<?php echo $end; ?> / <?php echo number_format($total_data); ?></div>
                    <?php if($total_pages > 1): ?>
                    <div class="pagination-box">
                        <?php 
                        $qs = [];
                        if(!empty($search)) $qs['search']=$search; 
                        $qs['page_size']=$page_size;
                        $build = function($p) use ($qs){ $qs['page']=$p; return '?'.http_build_query($qs); };
                        $total_pages = max(1, $total_pages);
                        $window = 10;
                        $startWin = max(1, min($page - intval($window/2), $total_pages - $window + 1));
                        $endWin = min($total_pages, $startWin + $window - 1);
                        ?>
                        <a class="page-link <?php echo $page==1?'disabled':''; ?>" href="<?php echo $page==1?'#':$build(1); ?>">« First</a>
                        <a class="page-link <?php echo $page==1?'disabled':''; ?>" href="<?php echo $page==1?'#':$build(max(1,$page-1)); ?>">‹ Prev</a>
                        <?php for($i=$startWin;$i<=$endWin;$i++): ?>
                            <a class="page-link <?php echo $i==$page?'active':''; ?>" href="<?php echo $build($i); ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <a class="page-link <?php echo $page==$total_pages?'disabled':''; ?>" href="<?php echo $page==$total_pages?'#':$build(min($total_pages,$page+1)); ?>">Next ›</a>
                        <a class="page-link <?php echo $page==$total_pages?'disabled':''; ?>" href="<?php echo $page==$total_pages?'#':$build($total_pages); ?>">Last »</a>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </div>
</div>

<!-- Modal Tambah Siswa -->
<div id="modalTambah" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>➕ Tambah Siswa Baru</h3>
            <button class="modal-close" onclick="closeModal('modalTambah')">&times;</button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="modal-body">
                <div class="form-group">
                    <label>NISN (10 digit) *</label>
                    <input type="text" name="nisn" required maxlength="10" placeholder="Contoh: 0051234567">
                    <small style="color:#999;">NISN akan menjadi username login</small>
                </div>
                <div class="form-group">
                    <label>Nama Lengkap *</label>
                    <input type="text" name="nama_lengkap" required maxlength="100" placeholder="Contoh: Ahmad Rizki Maulana">
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required maxlength="100" placeholder="Contoh: ahmad.rizki@student.kgb2.sch.id">
                </div>
                <div class="form-group">
                    <label>No. WhatsApp</label>
                    <input type="text" name="no_whatsapp" maxlength="20" placeholder="Contoh: 081234567890">
                </div>
                <div class="form-group">
                    <label>Kelas</label>
                    <select name="kelas_id">
                        <option value="">-- Pilih Kelas (Opsional) --</option>
                        <?php if($rKelas): mysqli_data_seek($rKelas, 0); while($k = mysqli_fetch_assoc($rKelas)): ?>
                            <option value="<?php echo $k['id']; ?>"><?php echo htmlspecialchars(($k['nama_kelas']) ?? ''); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" required minlength="6" placeholder="Minimal 6 karakter">
                </div>
                <div class="form-group">
                    <label>Konfirmasi Password *</label>
                    <input type="password" name="password_confirm" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Foto (opsional)</label>
                    <input type="file" name="foto" accept="image/*">
                    <small style="color:#999;">Format: JPG/PNG/WebP, maks 2MB</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalTambah')">Batal</button>
                <button type="submit" name="tambah_siswa" class="btn btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Siswa -->
<div id="modalEdit" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-pen" aria-hidden="true"></i> Edit Data Siswa</h3>
            <button class="modal-close" onclick="closeModal('modalEdit')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>NISN (Username) *</label>
                    <input type="text" name="nisn" id="edit_nisn" required maxlength="10">
                    <small style="color:#999;">Username akan otomatis berubah sesuai NISN</small>
                </div>
                <div class="form-group">
                    <label>Nama Lengkap *</label>
                    <input type="text" name="nama_lengkap" id="edit_nama_lengkap" required maxlength="100">
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" id="edit_email" required maxlength="100">
                </div>
                <div class="form-group">
                    <label>No. WhatsApp</label>
                    <input type="text" name="no_whatsapp" id="edit_no_whatsapp" maxlength="20">
                </div>
                <div class="form-group">
                    <label>Kelas</label>
                    <select name="kelas_id" id="edit_kelas_id">
                        <option value="">-- Pilih Kelas (Opsional) --</option>
                        <?php if($rKelas): mysqli_data_seek($rKelas, 0); while($k = mysqli_fetch_assoc($rKelas)): ?>
                            <option value="<?php echo $k['id']; ?>"><?php echo htmlspecialchars(($k['nama_kelas']) ?? ''); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                        <span>Aktif</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEdit')">Batal</button>
                <button type="submit" name="edit_siswa" class="btn btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<?php
// Sembunyikan deprecated warning saat diakses via IP publik (tanpa host sekolah)
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
if (preg_match('~^\d{1,3}(?:\.\d{1,3}){3}(?::\d+)?$~', $host)) {
    @error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
}
?>
<script src="../assets/js/script.js"></script>
<script>
function changePageSize(ps){
    const url = new URL(window.location.href);
    url.searchParams.set('page_size', ps);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}
function openModalEdit(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_nisn').value = data.nisn;
    document.getElementById('edit_nama_lengkap').value = data.nama_lengkap;
    document.getElementById('edit_email').value = data.email;
    document.getElementById('edit_no_whatsapp').value = data.no_whatsapp || '';
    document.getElementById('edit_kelas_id').value = data.kelas_id || '';
    document.getElementById('edit_is_active').checked = parseInt(data.is_active, 10) === 1;
    openModal('modalEdit');
}
</script>
<style>
.pagination-bar{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-top:15px}
.pagination-info{padding:6px 10px;border:1px solid #e2e8f0;border-radius:8px;color:#334155;background:#fff}
.pagination-box{display:flex;gap:6px;flex-wrap:wrap}
.page-link{display:inline-block;padding:6px 10px;border:1px solid #e2e8f0;border-radius:8px;color:#334155;background:#fff;text-decoration:none}
.page-link:hover{border-color:#1e5ba8;color:#1e5ba8}
.page-link.active{background:#1e5ba8;color:#fff;border-color:#1e5ba8}
.page-link.disabled{opacity:.5;pointer-events:none}
</style>
</body>
</html>

