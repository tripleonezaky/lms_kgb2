<?php
/**
 * File: admin/kelola_kelas.php
 * Fungsi: CRUD Kelas sesuai skema DB
 */
require_once __DIR__ . '/../includes/auth_admin.php';
require_once __DIR__ . '/../config/database.php';

function flash($k){ if(isset($_SESSION[$k])){ $m=$_SESSION[$k]; unset($_SESSION[$k]); return $m;} return null; }

// Tambah Kelas
if(isset($_POST['tambah_kelas'])){
    $tingkat = mysqli_real_escape_string($conn, trim($_POST['tingkat'])); // X, XI, XII
    $jurusan_id = !empty($_POST['jurusan_id']) ? (int)$_POST['jurusan_id'] : 0;
    $rombel = (int)$_POST['rombel'];
    $nama_kelas = mysqli_real_escape_string($conn, trim($_POST['nama_kelas']));
    $wali_kelas_id = !empty($_POST['wali_kelas_id']) ? (int)$_POST['wali_kelas_id'] : 'NULL';

    if(!in_array($tingkat, ['X','XI','XII'], true)){
        $_SESSION['error'] = 'Tingkat tidak valid!';
    } elseif($jurusan_id <= 0){
        $_SESSION['error'] = 'Jurusan wajib dipilih!';
    } elseif($rombel <= 0){
        $_SESSION['error'] = 'Rombel harus > 0!';
    } else {
        $dup = mysqli_query($conn, "SELECT id FROM kelas WHERE tingkat='$tingkat' AND jurusan_id=$jurusan_id AND rombel=$rombel LIMIT 1");
        if($dup && mysqli_num_rows($dup)>0){
            $_SESSION['error'] = 'Kombinasi Tingkat/Jurusan/Rombel sudah ada!';
        } else {
            $ins = mysqli_query($conn, "INSERT INTO kelas(tingkat,jurusan_id,rombel,nama_kelas,wali_kelas_id) VALUES('$tingkat',$jurusan_id,$rombel,'$nama_kelas',$wali_kelas_id)");
            $_SESSION[$ins? 'success':'error'] = $ins? 'Kelas berhasil ditambahkan!' : ('Gagal menambahkan: '.mysqli_error($conn));
        }
    }
    header('Location: kelola_kelas.php'); exit();
}

// Edit Kelas
if(isset($_POST['edit_kelas'])){
    $id = (int)$_POST['id'];
    $tingkat = mysqli_real_escape_string($conn, trim($_POST['tingkat']));
    $jurusan_id = !empty($_POST['jurusan_id']) ? (int)$_POST['jurusan_id'] : 0;
    $rombel = (int)$_POST['rombel'];
    $nama_kelas = mysqli_real_escape_string($conn, trim($_POST['nama_kelas']));
    $wali_kelas_id = !empty($_POST['wali_kelas_id']) ? (int)$_POST['wali_kelas_id'] : 'NULL';

    if(!in_array($tingkat, ['X','XI','XII'], true)){
        $_SESSION['error'] = 'Tingkat tidak valid!';
    } elseif($jurusan_id <= 0){
        $_SESSION['error'] = 'Jurusan wajib dipilih!';
    } elseif($rombel <= 0){
        $_SESSION['error'] = 'Rombel harus > 0!';
    } else {
        $dup = mysqli_query($conn, "SELECT id FROM kelas WHERE tingkat='$tingkat' AND jurusan_id=$jurusan_id AND rombel=$rombel AND id!=$id LIMIT 1");
        if($dup && mysqli_num_rows($dup)>0){
            $_SESSION['error'] = 'Kombinasi Tingkat/Jurusan/Rombel sudah ada!';
        } else {
            $upd = mysqli_query($conn, "UPDATE kelas SET tingkat='$tingkat', jurusan_id=$jurusan_id, rombel=$rombel, nama_kelas='$nama_kelas', wali_kelas_id=$wali_kelas_id WHERE id=$id");
            $_SESSION[$upd? 'success':'error'] = $upd? 'Kelas berhasil diupdate!' : ('Gagal mengupdate: '.mysqli_error($conn));
        }
    }
    header('Location: kelola_kelas.php'); exit();
}

// Hapus Kelas
if(isset($_GET['hapus'])){
    $id = (int)$_GET['hapus'];
    $cek1 = mysqli_query($conn, "SELECT id FROM assignment_guru WHERE kelas_id=$id LIMIT 1");
    $cek2 = mysqli_query($conn, "SELECT id FROM users WHERE kelas_id=$id LIMIT 1");
    $cek3 = mysqli_query($conn, "SELECT id FROM nilai WHERE kelas_id=$id LIMIT 1");
    if(($cek1 && mysqli_num_rows($cek1)>0) || ($cek2 && mysqli_num_rows($cek2)>0) || ($cek3 && mysqli_num_rows($cek3)>0)){
        $_SESSION['error'] = 'Tidak dapat menghapus! Masih ada data terkait (assignment/siswa/nilai).';
    } else {
        $del = mysqli_query($conn, "DELETE FROM kelas WHERE id=$id");
        $_SESSION[$del? 'success':'error'] = $del? 'Kelas berhasil dihapus!' : ('Gagal menghapus: '.mysqli_error($conn));
    }
    header('Location: kelola_kelas.php'); exit();
}

// Data Referensi
$rJurusan = mysqli_query($conn, "SELECT id, nama_jurusan, singkatan FROM jurusan ORDER BY nama_jurusan");
$rGuru    = mysqli_query($conn, "SELECT id, nama_lengkap FROM users WHERE role='guru' AND is_active=1 ORDER BY nama_lengkap");

// List & Search & Pagination
$limit=10; $page = isset($_GET['page'])? max(1,(int)$_GET['page']) : 1; $offset = ($page-1)*$limit;
$search = isset($_GET['search'])? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$where = "";
if($search!==''){
    $where = "WHERE k.nama_kelas LIKE '%$search%' OR j.singkatan LIKE '%$search%' OR k.tingkat LIKE '%$search%'";
}
$q = "SELECT k.*, j.singkatan, u.nama_lengkap AS wali_nama FROM kelas k
      LEFT JOIN jurusan j ON k.jurusan_id=j.id
      LEFT JOIN users u ON k.wali_kelas_id=u.id
      $where
      ORDER BY k.tingkat, j.singkatan, k.rombel
      LIMIT $limit OFFSET $offset";
$r = mysqli_query($conn,$q);
$qt = mysqli_query($conn, "SELECT COUNT(*) total FROM kelas k LEFT JOIN jurusan j ON k.jurusan_id=j.id $where");
$total = $qt? (int)mysqli_fetch_assoc($qt)['total'] : 0; $pages = max(1,(int)ceil($total/$limit));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Kelas - LMS KGB2</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/_overrides.css">
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-content">
    <div class="top-bar">
      <h1><i class="fas fa-school" aria-hidden="true"></i> Kelola Kelas</h1>
        <div class="user-info">
            <span>Admin: <strong><?php echo htmlspecialchars(($_SESSION['nama_lengkap']) ?? ''); ?></strong></span>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
    <div class="content-area">
        <?php if($m=flash('success')): ?><div class="alert alert-success"><i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo $m; ?></div><?php endif; ?>
        <?php if($m=flash('error')): ?><div class="alert alert-danger"><i class="fas fa-times-circle" aria-hidden="true"></i> <?php echo $m; ?></div><?php endif; ?>

        <div class="card">
            <div class="card-header"><h3><i class="fas fa-list" aria-hidden="true"></i> Daftar Kelas</h3></div>
            <div class="card-body">
                <div class="card-toolbar" style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom:12px;">
                    <form method="GET" action="" style="display:flex; gap:10px;">
                        <input type="text" name="search" placeholder="🔍 Cari Kelas / Jurusan / Tingkat..." value="<?php echo htmlspecialchars(($search) ?? ''); ?>" style="padding:8px 15px; border:1px solid #ddd; border-radius:5px; width:320px;">
                        <button type="submit" class="btn btn-secondary">Cari</button>
                        <?php if($search!==''): ?><a href="kelola_kelas.php" class="btn btn-secondary">Reset</a><?php endif; ?>
                    </form>
                    <button class="btn btn-primary" onclick="openModal('modalTambah')"><i class="fas fa-plus" aria-hidden="true"></i> Tambah Kelas</button>
                </div>
                <div style="margin-bottom:12px;"><strong>Total:</strong> <span class="badge badge-primary"><?php echo number_format($total); ?></span></div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tingkat</th>
                                <th>Jurusan</th>
                                <th>Rombel</th>
                                <th>Nama Kelas</th>
                                <th>Wali Kelas</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($r && mysqli_num_rows($r)>0): $no=$offset+1; while($row=mysqli_fetch_assoc($r)): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><?php echo htmlspecialchars(($row['tingkat']) ?? ''); ?></td>
                                <td><span class="badge badge-success"><?php echo htmlspecialchars(($row['singkatan']) ?? ''); ?></span></td>
                                <td><?php echo htmlspecialchars(($row['rombel']) ?? ''); ?></td>
                                <td><strong><?php echo htmlspecialchars(($row['nama_kelas']) ?? ''); ?></strong></td>
                                <td><?php echo $row['wali_nama']? htmlspecialchars(($row['wali_nama']) ?? '') : '<em style="color:#999;">-</em>'; ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-action btn-warning" title="Edit" onclick='openEdit(<?php echo json_encode($row, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'><i class="fas fa-pen" aria-hidden="true"></i></button>
                                        <a href="?hapus=<?php echo $row['id']; ?>" class="btn-action btn-danger" onclick="return confirm('Hapus kelas ini?')" title="Hapus"><i class="fas fa-trash-alt" aria-hidden="true"></i></a>
                                        <a href="delete_force.php?entity=kelas&id=<?php echo $row['id']; ?>&redirect=<?php echo urlencode('kelola_kelas.php'); ?>" class="btn-action btn-danger" onclick="return confirm('Hapus Paksa: Kelas dan seluruh data terkait akan dihapus. Lanjutkan?')" title="Hapus Paksa"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="7" style="text-align:center; padding:30px; color:#999;">Tidak ada data</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php 
                // Hitung rentang tampil
                $start = $total > 0 ? ($offset + 1) : 0;
                $end = min($offset + $limit, $total);
                ?>
                <div class="pagination-bar">
                    <div class="pagination-info">Jumlah: <?php echo $start; ?>–<?php echo $end; ?> / <?php echo number_format($total); ?></div>
                    <?php if($pages>1): ?>
                    <div class="pagination-box">
                        <?php if($page>1): ?>
                            <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $search!==''? '&search='.urlencode($search):''; ?>">« Prev</a>
                        <?php else: ?>
                            <span class="page-link disabled">« Prev</span>
                        <?php endif; ?>

                        <?php for($i=1;$i<=$pages;$i++): ?>
                            <a class="page-link <?php echo $i==$page? 'active':''; ?>" href="?page=<?php echo $i; ?><?php echo $search!==''? '&search='.urlencode($search):''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if($page<$pages): ?>
                            <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $search!==''? '&search='.urlencode($search):''; ?>">Next »</a>
                        <?php else: ?>
                            <span class="page-link disabled">Next »</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div id="modalTambah" class="modal">
  <div class="modal-content">
    <div class="modal-header"><h3>➕ Tambah Kelas</h3><button class="modal-close" onclick="closeModal('modalTambah')">&times;</button></div>
    <form method="POST" action="">
      <div class="modal-body">
        <div class="form-row" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:16px;">
          <div class="form-group"><label>Tingkat *</label>
            <select name="tingkat" required>
              <option value="">-- Pilih --</option>
              <option value="X">X</option>
              <option value="XI">XI</option>
              <option value="XII">XII</option>
            </select>
          </div>
          <div class="form-group"><label>Jurusan *</label>
            <select name="jurusan_id" required>
              <option value="">-- Pilih Jurusan --</option>
              <?php if($rJurusan){ mysqli_data_seek($rJurusan,0); while($j=mysqli_fetch_assoc($rJurusan)): ?>
              <option value="<?php echo $j['id']; ?>"><?php echo htmlspecialchars(($j['singkatan'].' - '.$j['nama_jurusan']) ?? ''); ?></option>
              <?php endwhile; } ?>
            </select>
          </div>
          <div class="form-group"><label>Rombel *</label><input type="number" name="rombel" required min="1" max="99"></div>
          <div class="form-group"><label>Nama Kelas *</label><input type="text" name="nama_kelas" required maxlength="50" placeholder="Contoh: X RPL 1"></div>
          <div class="form-group"><label>Wali Kelas</label>
            <select name="wali_kelas_id">
              <option value="">-- Tidak ada --</option>
              <?php if($rGuru){ mysqli_data_seek($rGuru,0); while($g=mysqli_fetch_assoc($rGuru)): ?>
              <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars(($g['nama_lengkap']) ?? ''); ?></option>
              <?php endwhile; } ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modalTambah')">Batal</button><button type="submit" name="tambah_kelas" class="btn btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Simpan</button></div>
    </form>
  </div>
</div>

<!-- Modal Edit -->
<div id="modalEdit" class="modal">
  <div class="modal-content">
    <div class="modal-header"><h3><i class="fas fa-pen" aria-hidden="true"></i> Edit Kelas</h3><button class="modal-close" onclick="closeModal('modalEdit')">&times;</button></div>
    <form method="POST" action="">
      <input type="hidden" name="id" id="edit_id">
      <div class="modal-body">
        <div class="form-row" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:16px;">
          <div class="form-group"><label>Tingkat *</label>
            <select name="tingkat" id="edit_tingkat" required>
              <option value="X">X</option>
              <option value="XI">XI</option>
              <option value="XII">XII</option>
            </select>
          </div>
          <div class="form-group"><label>Jurusan *</label>
            <select name="jurusan_id" id="edit_jurusan_id" required>
              <option value="">-- Pilih Jurusan --</option>
              <?php if($rJurusan){ mysqli_data_seek($rJurusan,0); while($j=mysqli_fetch_assoc($rJurusan)): ?>
              <option value="<?php echo $j['id']; ?>"><?php echo htmlspecialchars(($j['singkatan'].' - '.$j['nama_jurusan']) ?? ''); ?></option>
              <?php endwhile; } ?>
            </select>
          </div>
          <div class="form-group"><label>Rombel *</label><input type="number" name="rombel" id="edit_rombel" required min="1" max="99"></div>
          <div class="form-group"><label>Nama Kelas *</label><input type="text" name="nama_kelas" id="edit_nama_kelas" required maxlength="50"></div>
          <div class="form-group"><label>Wali Kelas</label>
            <select name="wali_kelas_id" id="edit_wali_kelas_id">
              <option value="">-- Tidak ada --</option>
              <?php if($rGuru){ mysqli_data_seek($rGuru,0); while($g=mysqli_fetch_assoc($rGuru)): ?>
              <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars(($g['nama_lengkap']) ?? ''); ?></option>
              <?php endwhile; } ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modalEdit')">Batal</button><button type="submit" name="edit_kelas" class="btn btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Update</button></div>
    </form>
  </div>
</div>

<script src="../assets/js/script.js"></script>
<script>
function openEdit(d){
  document.getElementById('edit_id').value = d.id;
  document.getElementById('edit_tingkat').value = d.tingkat;
  document.getElementById('edit_jurusan_id').value = d.jurusan_id;
  document.getElementById('edit_rombel').value = d.rombel;
  document.getElementById('edit_nama_kelas').value = d.nama_kelas;
  document.getElementById('edit_wali_kelas_id').value = d.wali_kelas_id || '';
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

