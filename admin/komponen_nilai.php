<?php
/**
 * File: admin/komponen_nilai.php
 * Fungsi: CRUD Komponen Nilai (tbl_komponen_nilai)
 */
require_once __DIR__ . '/../includes/auth_admin.php';
require_once __DIR__ . '/../config/database.php';

function flash($k){ if(isset($_SESSION[$k])){ $m=$_SESSION[$k]; unset($_SESSION[$k]); return $m;} return null; }

// Tambah Komponen
if(isset($_POST['tambah_komponen'])){
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama_komponen']));
    $bobot = (int)$_POST['bobot'];
    $ket = mysqli_real_escape_string($conn, trim($_POST['keterangan']));

    if($bobot < 0 || $bobot > 100){
        $_SESSION['error'] = 'Bobot harus 0-100!';
    } else {
        $ket_val = $ket!==''? "'".$ket."'" : 'NULL';
        $ins = mysqli_query($conn, "INSERT INTO tbl_komponen_nilai(nama_komponen,bobot,keterangan) VALUES('$nama',$bobot,$ket_val)");
        $_SESSION[$ins? 'success':'error'] = $ins? 'Komponen nilai berhasil ditambahkan!' : ('Gagal menambahkan: '.mysqli_error($conn));
    }
    header('Location: komponen_nilai.php'); exit();
}

// Edit Komponen
if(isset($_POST['edit_komponen'])){
    $id = (int)$_POST['id'];
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama_komponen']));
    $bobot = (int)$_POST['bobot'];
    $ket = mysqli_real_escape_string($conn, trim($_POST['keterangan']));

    if($bobot < 0 || $bobot > 100){
        $_SESSION['error'] = 'Bobot harus 0-100!';
    } else {
        $ket_set = $ket!==''? "keterangan='".$ket."'" : "keterangan=NULL";
        $upd = mysqli_query($conn, "UPDATE tbl_komponen_nilai SET nama_komponen='$nama', bobot=$bobot, $ket_set WHERE id_komponen=$id");
        $_SESSION[$upd? 'success':'error'] = $upd? 'Komponen nilai berhasil diupdate!' : ('Gagal mengupdate: '.mysqli_error($conn));
    }
    header('Location: komponen_nilai.php'); exit();
}

// Hapus Komponen
if(isset($_GET['hapus'])){
    $id = (int)$_GET['hapus'];
    $cek = mysqli_query($conn, "SELECT id_nilai FROM tbl_nilai WHERE id_komponen=$id LIMIT 1");
    if($cek && mysqli_num_rows($cek)>0){
        $_SESSION['error'] = 'Tidak dapat menghapus! Masih ada nilai terkait komponen ini.';
    } else {
        $del = mysqli_query($conn, "DELETE FROM tbl_komponen_nilai WHERE id_komponen=$id");
        $_SESSION[$del? 'success':'error'] = $del? 'Komponen nilai berhasil dihapus!' : ('Gagal menghapus: '.mysqli_error($conn));
    }
    header('Location: komponen_nilai.php'); exit();
}

// List & Search & Pagination
$limit=10; $page = isset($_GET['page'])? max(1,(int)$_GET['page']) : 1; $offset = ($page-1)*$limit;
$search = isset($_GET['search'])? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$where = '';
if($search!==''){
    $where = "WHERE nama_komponen LIKE '%$search%'";
}
$q = "SELECT * FROM tbl_komponen_nilai $where ORDER BY id_komponen ASC LIMIT $limit OFFSET $offset";
$r = mysqli_query($conn,$q);
$qt = mysqli_query($conn, "SELECT COUNT(*) total FROM tbl_komponen_nilai $where");
$total = $qt? (int)mysqli_fetch_assoc($qt)['total'] : 0; $pages = max(1,(int)ceil($total/$limit));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Komponen Nilai - LMS KGB2</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/_overrides.css">
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-content">
    <div class="top-bar">
        <h1>⚙️ Komponen Nilai</h1>
        <div class="user-info">
            <span>Admin: <strong><?php echo htmlspecialchars(($_SESSION['nama_lengkap']) ?? ''); ?></strong></span>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
    <div class="content-area">
        <?php if($m=flash('success')): ?><div class="alert alert-success"><i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo $m; ?></div><?php endif; ?>
        <?php if($m=flash('error')): ?><div class="alert alert-danger"><i class="fas fa-times-circle" aria-hidden="true"></i> <?php echo $m; ?></div><?php endif; ?>

        <div class="card">
            <div class="card-header"><h3>📋 Daftar Komponen Nilai</h3></div>
            <div class="card-body">
                <div class="card-toolbar" style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom:12px;">
                    <form method="GET" action="" style="display:flex; gap:10px;">
                        <input type="text" name="search" placeholder="🔍 Cari Nama Komponen..." value="<?php echo htmlspecialchars(($search) ?? ''); ?>" style="padding:8px 15px; border:1px solid #ddd; border-radius:5px; width:320px;">
                        <button type="submit" class="btn btn-secondary">Cari</button>
                        <?php if($search!==''): ?><a href="komponen_nilai.php" class="btn btn-secondary">Reset</a><?php endif; ?>
                    </form>
                    <button class="btn btn-primary" onclick="openModal('modalTambah')">➕ Tambah Komponen</button>
                </div>
                <div style="margin-bottom:12px;"><strong>Total:</strong> <span class="badge badge-primary"><?php echo number_format($total); ?></span></div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Komponen</th>
                                <th>Bobot</th>
                                <th>Keterangan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($r && mysqli_num_rows($r)>0): $no=$offset+1; while($row=mysqli_fetch_assoc($r)): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><strong><?php echo htmlspecialchars(($row['nama_komponen']) ?? ''); ?></strong></td>
                                <td><span class="badge badge-info"><?php echo (int)$row['bobot']; ?>%</span></td>
                                <td><?php echo $row['keterangan']? htmlspecialchars(($row['keterangan']) ?? '') : '<em style="color:#999;">-</em>'; ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-action btn-warning" title="Edit" onclick='openEdit(<?php echo json_encode($row, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'><i class="fas fa-pen" aria-hidden="true"></i></button>
                                        <a href="?hapus=<?php echo $row['id_komponen']; ?>" class="btn-action btn-danger" onclick="return confirm('Hapus komponen ini?')" title="Hapus">🗑️</a>
                                        <a href="delete_force.php?entity=komponen_nilai&id=<?php echo $row['id_komponen']; ?>&redirect=<?php echo urlencode('komponen_nilai.php'); ?>" class="btn-action btn-danger" onclick="return confirm('Hapus Paksa: Komponen nilai dan seluruh nilai terkait akan dihapus. Lanjutkan?')" title="Hapus Paksa"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="5" style="text-align:center; padding:30px; color:#999;">Tidak ada data</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if($pages>1): ?>
                <div class="pagination" style="margin-top:15px; display:flex; gap:6px; flex-wrap:wrap;">
                    <?php if($page>1): ?><a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $search!==''? '&search='.urlencode($search):''; ?>">« Prev</a><?php endif; ?>
                    <?php for($i=1;$i<=$pages;$i++): ?>
                        <a class="page-link <?php echo $i==$page? 'active':''; ?>" href="?page=<?php echo $i; ?><?php echo $search!==''? '&search='.urlencode($search):''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if($page<$pages): ?><a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $search!==''? '&search='.urlencode($search):''; ?>">Next »</a><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div id="modalTambah" class="modal">
  <div class="modal-content">
    <div class="modal-header"><h3>➕ Tambah Komponen Nilai</h3><button class="modal-close" onclick="closeModal('modalTambah')">&times;</button></div>
    <form method="POST" action="">
      <div class="modal-body">
        <div class="form-row" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:16px;">
          <div class="form-group"><label>Nama Komponen *</label><input type="text" name="nama_komponen" required maxlength="50" placeholder="Contoh: Tugas"></div>
          <div class="form-group"><label>Bobot (%) *</label><input type="number" name="bobot" required min="0" max="100"></div>
          <div class="form-group" style="grid-column: 1/-1;"><label>Keterangan</label><textarea name="keterangan" rows="3" placeholder="Keterangan tambahan"></textarea></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modalTambah')">Batal</button><button type="submit" name="tambah_komponen" class="btn btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Simpan</button></div>
    </form>
  </div>
</div>

<!-- Modal Edit -->
<div id="modalEdit" class="modal">
  <div class="modal-content">
    <div class="modal-header"><h3><i class="fas fa-pen" aria-hidden="true"></i> Edit Komponen Nilai</h3><button class="modal-close" onclick="closeModal('modalEdit')">&times;</button></div>
    <form method="POST" action="">
      <input type="hidden" name="id" id="edit_id">
      <div class="modal-body">
        <div class="form-row" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:16px;">
          <div class="form-group"><label>Nama Komponen *</label><input type="text" name="nama_komponen" id="edit_nama" required maxlength="50"></div>
          <div class="form-group"><label>Bobot (%) *</label><input type="number" name="bobot" id="edit_bobot" required min="0" max="100"></div>
          <div class="form-group" style="grid-column: 1/-1;"><label>Keterangan</label><textarea name="keterangan" id="edit_keterangan" rows="3"></textarea></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modalEdit')">Batal</button><button type="submit" name="edit_komponen" class="btn btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Update</button></div>
    </form>
  </div>
</div>

<script src="../assets/js/script.js"></script>
<script>
function openEdit(d){
  document.getElementById('edit_id').value = d.id_komponen;
  document.getElementById('edit_nama').value = d.nama_komponen;
  document.getElementById('edit_bobot').value = d.bobot;
  document.getElementById('edit_keterangan').value = d.keterangan||'';
  openModal('modalEdit');
}
</script>
</body>
</html>

