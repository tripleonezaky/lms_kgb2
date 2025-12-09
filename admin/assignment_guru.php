<?php
/**
 * File: admin/assignment_guru.php
 * Fitur: Admin menugaskan guru mengajar (mapel-kelas-tahun ajaran), mendukung lebih dari 1 mapel/kelas/TA
 */
require_once __DIR__ . '/../includes/auth_admin.php';
require_once __DIR__ . '/../config/database.php';

function flash($k){ if(isset($_SESSION[$k])){ $m=$_SESSION[$k]; unset($_SESSION[$k]); return $m;} return null; }

// Tambah Assignment (multi kelas)
if(isset($_POST['tambah_assignment'])){
    $guru_id = (int)($_POST['guru_id'] ?? 0);
    $mapel_id = (int)($_POST['mapel_id'] ?? 0);
    $ta_id = (int)($_POST['tahun_ajaran_id'] ?? 0);
    $kelas_ids = isset($_POST['kelas_ids']) && is_array($_POST['kelas_ids']) ? array_map('intval', $_POST['kelas_ids']) : [];

    if($guru_id<=0 || $mapel_id<=0 || $ta_id<=0 || empty($kelas_ids)){
        $_SESSION['error'] = 'Semua field wajib diisi dan pilih minimal satu kelas!';
    } else {
        $added = 0; $dups = 0;
        foreach(array_unique($kelas_ids) as $kelas_id){
            if($kelas_id<=0) continue;
            $dup = query("SELECT id FROM assignment_guru WHERE guru_id={$guru_id} AND mapel_id={$mapel_id} AND kelas_id={$kelas_id} AND tahun_ajaran_id={$ta_id} LIMIT 1");
            if($dup && fetch_assoc($dup)){
                $dups++;
                continue;
            }
            $ins = query("INSERT INTO assignment_guru (guru_id,mapel_id,kelas_id,tahun_ajaran_id) VALUES ({$guru_id},{$mapel_id},{$kelas_id},{$ta_id})");
            if($ins) $added++;
        }
        if($added>0){
            $_SESSION['success'] = "Assignment berhasil ditambahkan untuk {$added} kelas".($dups>0? " ({$dups} duplikat diabaikan)":"").".";
        } else {
            $_SESSION['error'] = 'Tidak ada assignment baru yang ditambahkan.'.($dups>0? " ({$dups} duplikat)":"");
        }
    }
    header('Location: assignment_guru.php'); exit();
}

// Hapus assignment (normal) atau Hapus Paksa jika diminta
if(isset($_GET['hapus'])){
    $id = (int)$_GET['hapus'];
    $paksa = isset($_GET['paksa']) ? 1 : 0;
    if(!$paksa){
        $del = query("DELETE FROM assignment_guru WHERE id={$id} LIMIT 1");
        if($del){
            $_SESSION['success'] = 'Assignment dihapus.';
            header('Location: assignment_guru.php'); exit();
        } else {
            // Tawarkan opsi hapus paksa
            $_SESSION['error'] = 'Gagal menghapus assignment. Data mungkin masih terhubung. Anda bisa mencoba Hapus Paksa.';
            header('Location: assignment_guru.php?confirm_paksa='.$id); exit();
        }
    } else {
        // Hapus paksa: hapus data terkait secara berantai dalam transaksi
        $ok = true;
        query('START TRANSACTION');
        // Hapus materi terkait assignment ini
        $ok = $ok && query("DELETE FROM materi WHERE assignment_id={$id}");
        // Hapus soal dan turunannya
        // Hapus detail_soal dan jawaban_siswa berbasis soal_id assignment ini
        $rsSoal = query("SELECT id FROM soal WHERE assignment_id={$id}");
        if($rsSoal){
            while($row = fetch_assoc($rsSoal)){
                $sid = (int)$row['id'];
                $ok = $ok && query("DELETE FROM jawaban_siswa WHERE soal_id={$sid}");
                $ok = $ok && query("DELETE FROM detail_soal WHERE soal_id={$sid}");
            }
        }
        $ok = $ok && query("DELETE FROM soal WHERE assignment_id={$id}");
        // Hapus tugas dan pengumpulan_tugas
        $rsTugas = query("SELECT id FROM tugas WHERE assignment_id={$id}");
        if($rsTugas){
            while($row = fetch_assoc($rsTugas)){
                $tid = (int)$row['id'];
                $ok = $ok && query("DELETE FROM pengumpulan_tugas WHERE tugas_id={$tid}");
            }
        }
        $ok = $ok && query("DELETE FROM tugas WHERE assignment_id={$id}");
        // Hapus nilai model komponen jika ada (tbl_nilai menyimpan id_assignment)
        $ok = $ok && query("DELETE FROM tbl_nilai WHERE id_assignment={$id}");
        // Terakhir hapus assignment
        $ok = $ok && query("DELETE FROM assignment_guru WHERE id={$id} LIMIT 1");
        if($ok){
            query('COMMIT');
            $_SESSION['success'] = 'Assignment dan seluruh data terkait berhasil dihapus (paksa).';
        } else {
            query('ROLLBACK');
            $_SESSION['error'] = 'Hapus paksa gagal. Tidak ada perubahan yang disimpan.';
        }
        header('Location: assignment_guru.php'); exit();
    }
}

// Hapus assignment per grup (Guru-Mapel-TA) - normal atau paksa
if(isset($_GET['hapus_group'])){
    $eg_guru = (int)($_GET['guru_id'] ?? 0);
    $eg_mapel = (int)($_GET['mapel_id'] ?? 0);
    $eg_ta = (int)($_GET['tahun_ajaran_id'] ?? 0);
    $paksa = isset($_GET['paksa']) ? 1 : 0;
    if($eg_guru<=0 || $eg_mapel<=0 || $eg_ta<=0){
        $_SESSION['error'] = 'Parameter hapus tidak lengkap.';
        header('Location: assignment_guru.php'); exit();
    }
    if(!$paksa){
        // Coba hapus langsung semua baris assignment di grup ini
        $del = query("DELETE FROM assignment_guru WHERE guru_id={$eg_guru} AND mapel_id={$eg_mapel} AND tahun_ajaran_id={$eg_ta}");
        if($del){
            $_SESSION['success'] = 'Assignment pada kombinasi ini dihapus.';
        } else {
            $_SESSION['error'] = 'Gagal menghapus assignment. Data mungkin masih terhubung. Anda bisa mencoba Hapus Paksa.';
        }
        header('Location: assignment_guru.php'); exit();
    } else {
        // Hapus Paksa seluruh assignment dalam grup ini & semua data terkait
        $ok = true;
        query('START TRANSACTION');
        $rsAg = query("SELECT id FROM assignment_guru WHERE guru_id={$eg_guru} AND mapel_id={$eg_mapel} AND tahun_ajaran_id={$eg_ta}");
        if($rsAg){
            while($ag = fetch_assoc($rsAg)){
                $id = (int)$ag['id'];
                // Materi
                $ok = $ok && query("DELETE FROM materi WHERE assignment_id={$id}");
                // Soal -> detail_soal & jawaban_siswa
                $rsSoal = query("SELECT id FROM soal WHERE assignment_id={$id}");
                if($rsSoal){
                    while($row = fetch_assoc($rsSoal)){
                        $sid = (int)$row['id'];
                        $ok = $ok && query("DELETE FROM jawaban_siswa WHERE soal_id={$sid}");
                        $ok = $ok && query("DELETE FROM detail_soal WHERE soal_id={$sid}");
                    }
                }
                $ok = $ok && query("DELETE FROM soal WHERE assignment_id={$id}");
                // Tugas -> pengumpulan
                $rsTugas = query("SELECT id FROM tugas WHERE assignment_id={$id}");
                if($rsTugas){
                    while($row = fetch_assoc($rsTugas)){
                        $tid = (int)$row['id'];
                        $ok = $ok && query("DELETE FROM pengumpulan_tugas WHERE tugas_id={$tid}");
                    }
                }
                $ok = $ok && query("DELETE FROM tugas WHERE assignment_id={$id}");
                // Nilai komponen
                $ok = $ok && query("DELETE FROM tbl_nilai WHERE id_assignment={$id}");
                // Hapus assignment
                $ok = $ok && query("DELETE FROM assignment_guru WHERE id={$id} LIMIT 1");
            }
        }
        if($ok){
            query('COMMIT');
            $_SESSION['success'] = 'Assignment dan seluruh data terkait untuk kombinasi ini berhasil dihapus (paksa).';
        } else {
            query('ROLLBACK');
            $_SESSION['error'] = 'Hapus paksa gagal. Tidak ada perubahan yang disimpan.';
        }
        header('Location: assignment_guru.php'); exit();
    }
}

// Edit group: simpan perubahan kelas untuk kombinasi guru-mapel-TA
if(isset($_POST['simpan_edit_group'])){
    $eg_guru = (int)($_POST['eg_guru_id'] ?? 0);
    $eg_mapel = (int)($_POST['eg_mapel_id'] ?? 0);
    $eg_ta = (int)($_POST['eg_ta_id'] ?? 0);
    $kelas_ids_new = isset($_POST['kelas_ids']) && is_array($_POST['kelas_ids']) ? array_map('intval', $_POST['kelas_ids']) : [];

    if($eg_guru<=0 || $eg_mapel<=0 || $eg_ta<=0){
        $_SESSION['error'] = 'Parameter edit tidak lengkap.';
        header('Location: assignment_guru.php'); exit();
    }
    // Ambil kelas eksisting
    $curr = [];
    $q = query("SELECT kelas_id FROM assignment_guru WHERE guru_id={$eg_guru} AND mapel_id={$eg_mapel} AND tahun_ajaran_id={$eg_ta}");
    if($q){ while($r=fetch_assoc($q)){ $curr[] = (int)$r['kelas_id']; } }
    $curr = array_unique($curr);
    $new = array_unique(array_filter($kelas_ids_new, fn($v)=>$v>0));

    $to_add = array_values(array_diff($new, $curr));
    $to_del = array_values(array_diff($curr, $new));

    $added=0; $deleted=0; $del_failed=0;

    foreach($to_add as $kid){
        $ok = query("INSERT INTO assignment_guru (guru_id,mapel_id,kelas_id,tahun_ajaran_id) VALUES ({$eg_guru},{$eg_mapel},{$kid},{$eg_ta})");
        if($ok) $added++;
    }
    if(!empty($to_del)){
        $in = implode(',', array_map('intval',$to_del));
        $ok = query("DELETE FROM assignment_guru WHERE guru_id={$eg_guru} AND mapel_id={$eg_mapel} AND tahun_ajaran_id={$eg_ta} AND kelas_id IN ({$in})");
        if($ok){
            $deleted = count($to_del);
        } else {
            $del_failed = count($to_del);
        }
    }

    if($added>0 || $deleted>0){
        $_SESSION['success'] = "Perubahan disimpan. Ditambah: {$added}, Dihapus: {$deleted}".($del_failed>0? " (Gagal hapus {$del_failed})":"").".";
    } else {
        $_SESSION['error'] = "Tidak ada perubahan yang disimpan.".($del_failed>0? " (Gagal hapus {$del_failed})":"");
    }
    header('Location: assignment_guru.php'); exit();
}

// Filter
$filter_ta = isset($_GET['tahun_ajaran_id'])? (int)$_GET['tahun_ajaran_id'] : 0;
$filter_guru = isset($_GET['guru_id'])? (int)$_GET['guru_id'] : 0;
$filter_mapel= isset($_GET['mapel_id'])? (int)$_GET['mapel_id'] : 0;
$filter_kelas= isset($_GET['kelas_id'])? (int)$_GET['kelas_id'] : 0;

// Options
$tahun_ajaran_opts = fetch_all(query("SELECT id, nama_tahun_ajaran, semester, is_active FROM tahun_ajaran ORDER BY is_active DESC, id DESC"));
$guru_opts = fetch_all(query("SELECT id, nama_lengkap, username AS kode FROM users WHERE role='guru' AND is_active=1 ORDER BY nama_lengkap"));
$mapel_opts = fetch_all(query("SELECT id, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel"));
$kelas_opts = fetch_all(query("SELECT k.id, k.nama_kelas, j.singkatan FROM kelas k JOIN jurusan j ON k.jurusan_id=j.id ORDER BY k.tingkat, k.nama_kelas"));

// List data
$where = [];
if($filter_ta>0) $where[] = 'ag.tahun_ajaran_id='.$filter_ta;
if($filter_guru>0) $where[] = 'ag.guru_id='.$filter_guru;
if($filter_mapel>0) $where[] = 'ag.mapel_id='.$filter_mapel;
if($filter_kelas>0) $where[] = 'ag.kelas_id='.$filter_kelas;
$where_sql = empty($where)? '' : ('WHERE '.implode(' AND ', $where));

$sql = "SELECT ag.guru_id, ag.mapel_id, ag.tahun_ajaran_id,
               u.nama_lengkap AS nama_guru, u.username AS kode_guru,
               mp.nama_mapel,
               ta.nama_tahun_ajaran,
               GROUP_CONCAT(k.nama_kelas ORDER BY k.nama_kelas SEPARATOR ', ') AS kelas_list,
               GROUP_CONCAT(k.id ORDER BY k.nama_kelas) AS kelas_ids
        FROM assignment_guru ag
        JOIN users u ON ag.guru_id=u.id
        JOIN mata_pelajaran mp ON ag.mapel_id=mp.id
        JOIN kelas k ON ag.kelas_id=k.id
        JOIN tahun_ajaran ta ON ag.tahun_ajaran_id=ta.id
        {$where_sql}
        GROUP BY ag.guru_id, ag.mapel_id, ag.tahun_ajaran_id, u.nama_lengkap, u.username, mp.nama_mapel, ta.nama_tahun_ajaran
        ORDER BY ta.id DESC, u.nama_lengkap, mp.nama_mapel";
$list = fetch_all(query($sql));

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assignment Guru - Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/_overrides.css">
<link rel="icon" type="image/png" href="../assets/img/logo-kgb2.png">
<style>
.container{max-width:1200px;margin:10px auto;padding:10px}
.card{background:#fff;border:1px solid #ddd;border-radius:8px;margin-bottom:12px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,.04)}
.card-header{padding:12px 16px;background:#f5f5f5;font-weight:600;display:flex;justify-content:space-between;align-items:center}
.card-body{padding:16px}
.row{display:flex;gap:12px;flex-wrap:wrap}
.col{flex:1 1 260px}
label{display:block;margin-bottom:6px;font-weight:600}
select{width:100%;padding:10px;border:1px solid #ced4da;border-radius:6px}
.btn{display:inline-block;padding:10px 14px;border-radius:6px;border:1px solid #2c7be5;background:#2c7be5;color:#fff;text-decoration:none;cursor:pointer}
.btn:hover{filter:brightness(0.95)}
.table{width:100%;border-collapse:collapse;table-layout:fixed}
.table th,.table td{border:1px solid #e5e5e5;padding:10px 12px;vertical-align:middle;box-sizing:border-box}
.table th{background:#fafafa;text-align:left}
/* Batasi lebar kolom agar aksi tetap di satu baris */
.table th:nth-child(1), .table td:nth-child(1){width:16%;}
.table th:nth-child(2), .table td:nth-child(2){width:20%;}
.table th:nth-child(3), .table td:nth-child(3){width:16%;}
/* kolom 4 (Kelas) dibiarkan adaptif namun dibatasi oleh kelas-box */
.table th:nth-child(5), .table td:nth-child(5){width:360px;}
/* Kelas list: bungkus teks multi-baris secara rapi dan batasi tinggi */
.table td:nth-child(4){
  word-wrap: break-word;
  white-space: normal;
  line-height: 1.4;
}
.kelas-box{
  max-height: 40px;
  overflow-y: auto;
  padding-right: 4px;
  font-size: 13px;
}
/* Konsistensi tinggi baris: paksa tinggi baris dan sel aksi */
.table tbody tr{ height: 64px; }
.table td.actions{ padding: 10px 12px; height: 64px; }
/* Aksi: satu baris, jarak konsisten, lebar tombol presisi */
.actions{
  display: flex;
  flex-wrap: nowrap;
  gap: 8px;
  align-items: center;
  justify-content: flex-start;
  white-space: nowrap;
}
.actions .btn{
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: calc((100% - 16px) / 3);
  min-width: 0;
  padding: 0;
  height: 36px;
  font-size: 13px;
  line-height: 1;
  text-align:center;
  white-space: nowrap;
  box-sizing: border-box;
  border-radius: 6px;
  margin: 0;
}
.alert{padding:10px;border-radius:6px;margin-bottom:10px}
.alert-success{background:#e6f4ea;color:#1e7e34}
.alert-error{background:#fdecea;color:#b00020}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-content">
  <div class="top-bar">
    <h1><i class="fas fa-user-tie" aria-hidden="true"></i> Assignment Guru</h1>
    <div class="user-info">
      <span>Admin: <strong><?php echo htmlspecialchars(($_SESSION['nama_lengkap']) ?? ''); ?></strong></span>
      <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
  </div>

  <div class="content-area">
    <?php if($m=flash('success')): ?><div class="alert alert-success"><i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo $m; ?></div><?php endif; ?>
    <?php if($m=flash('error')): ?><div class="alert alert-error"><i class="fas fa-times-circle" aria-hidden="true"></i> <?php echo $m; ?></div><?php endif; ?>

    <div class="card">
      <div class="card-header">Tambah Assignment</div>
      <div class="card-body">
        <form method="post" class="row">
          <input type="hidden" name="tambah_assignment" value="1" />
          <div class="col">
            <label>Tahun Ajaran</label>
            <select name="tahun_ajaran_id" required>
              <option value="">- pilih -</option>
              <?php foreach($tahun_ajaran_opts as $ta): ?>
                <option value="<?php echo (int)$ta['id']; ?>">TA <?php echo htmlspecialchars(($ta['nama_tahun_ajaran']) ?? ''); ?><?php echo ((int)$ta['is_active']===1?' - Aktif':''); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col">
            <label>Guru</label>
            <select name="guru_id" required>
              <option value="">- pilih -</option>
              <?php foreach($guru_opts as $g): ?>
                <option value="<?php echo (int)$g['id']; ?>"><?php echo htmlspecialchars(($g['nama_lengkap'].' ('.$g['kode'].')') ?? ''); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col">
            <label>Mata Pelajaran</label>
            <select name="mapel_id" required>
              <option value="">- pilih -</option>
              <?php foreach($mapel_opts as $m): ?>
                <option value="<?php echo (int)$m['id']; ?>"><?php echo htmlspecialchars(($m['nama_mapel']) ?? ''); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col" style="flex: 1 1 100%;">
            <label>Pilih Kelas (boleh lebih dari satu)</label>
            <div style="max-height:180px; overflow:auto; border:1px solid #ced4da; border-radius:6px; padding:8px; display:grid; grid-template-columns: repeat(auto-fill,minmax(220px,1fr)); gap:6px;">
              <?php foreach($kelas_opts as $k): ?>
                <label style="display:flex; align-items:center; gap:8px; padding:4px 6px; border:1px solid #eee; border-radius:6px;">
                  <input type="checkbox" name="kelas_ids[]" value="<?php echo (int)$k['id']; ?>" />
                  <span><?php echo htmlspecialchars(($k['nama_kelas'].' ('.$k['singkatan'].')') ?? ''); ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col" style="align-self:end"><button class="btn" type="submit">Simpan</button></div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Filter</div>
      <div class="card-body">
        <form class="row" method="get">
          <div class="col"><label>Tahun Ajaran</label><select name="tahun_ajaran_id"><option value="">Semua</option><?php foreach($tahun_ajaran_opts as $ta): ?><option value="<?php echo (int)$ta['id']; ?>" <?php echo $filter_ta==(int)$ta['id']?'selected':''; ?>>TA <?php echo htmlspecialchars(($ta['nama_tahun_ajaran']) ?? ''); ?></option><?php endforeach; ?></select></div>
          <div class="col"><label>Guru</label><select name="guru_id"><option value="">Semua</option><?php foreach($guru_opts as $g): ?><option value="<?php echo (int)$g['id']; ?>" <?php echo $filter_guru==(int)$g['id']?'selected':''; ?>><?php echo htmlspecialchars(($g['nama_lengkap']) ?? ''); ?></option><?php endforeach; ?></select></div>
          <div class="col"><label>Mapel</label><select name="mapel_id"><option value="">Semua</option><?php foreach($mapel_opts as $m): ?><option value="<?php echo (int)$m['id']; ?>" <?php echo $filter_mapel==(int)$m['id']?'selected':''; ?>><?php echo htmlspecialchars(($m['nama_mapel']) ?? ''); ?></option><?php endforeach; ?></select></div>
          <div class="col"><label>Kelas</label><select name="kelas_id"><option value="">Semua</option><?php foreach($kelas_opts as $k): ?><option value="<?php echo (int)$k['id']; ?>" <?php echo $filter_kelas==(int)$k['id']?'selected':''; ?>><?php echo htmlspecialchars(($k['nama_kelas']) ?? ''); ?></option><?php endforeach; ?></select></div>
          <div class="col" style="align-self:end"><button class="btn" type="submit">Terapkan</button></div>
        </form>
      </div>
    </div>

<?php if(isset($_GET['edit_group'])): 
  $eg_guru = (int)($_GET['guru_id'] ?? 0);
  $eg_mapel = (int)($_GET['mapel_id'] ?? 0);
  $eg_ta = (int)($_GET['tahun_ajaran_id'] ?? 0);
  $info = null;
  if($eg_guru>0 && $eg_mapel>0 && $eg_ta>0){
      $q = query("SELECT u.nama_lengkap AS nama_guru, u.username AS kode_guru, mp.nama_mapel, ta.nama_tahun_ajaran, ta.semester FROM assignment_guru ag JOIN users u ON ag.guru_id=u.id JOIN mata_pelajaran mp ON ag.mapel_id=mp.id JOIN tahun_ajaran ta ON ag.tahun_ajaran_id=ta.id WHERE ag.guru_id={$eg_guru} AND ag.mapel_id={$eg_mapel} AND ag.tahun_ajaran_id={$eg_ta} LIMIT 1");
      $info = $q? fetch_assoc($q) : null;
  }
  $kelas_selected = [];
  $qk = query("SELECT kelas_id FROM assignment_guru WHERE guru_id={$eg_guru} AND mapel_id={$eg_mapel} AND tahun_ajaran_id={$eg_ta}");
  if($qk){ while($r=fetch_assoc($qk)){ $kelas_selected[]=(int)$r['kelas_id']; } }
?>
    <div class="card">
      <div class="card-header">Edit Assignment (Kelola Kelas)</div>
      <div class="card-body">
        <?php if(!$info): ?>
          <div class="alert alert-error">Data kombinasi tidak ditemukan.</div>
        <?php else: ?>
          <p><strong>Guru:</strong> <?php echo htmlspecialchars(($info['nama_guru']) ?? '').' ('.htmlspecialchars(($info['kode_guru']) ?? '').')'; ?> | <strong>Mapel:</strong> <?php echo htmlspecialchars(($info['nama_mapel']) ?? ''); ?> | <strong>TA:</strong> <?php echo htmlspecialchars(($info['nama_tahun_ajaran']) ?? ''); ?></p>
          <form method="post">
            <input type="hidden" name="simpan_edit_group" value="1" />
            <input type="hidden" name="eg_guru_id" value="<?php echo $eg_guru; ?>" />
            <input type="hidden" name="eg_mapel_id" value="<?php echo $eg_mapel; ?>" />
            <input type="hidden" name="eg_ta_id" value="<?php echo $eg_ta; ?>" />
            <div style="max-height:220px; overflow:auto; border:1px solid #ced4da; border-radius:6px; padding:8px; display:grid; grid-template-columns: repeat(auto-fill,minmax(220px,1fr)); gap:6px;">
              <?php foreach($kelas_opts as $k): ?>
                <?php $checked = in_array((int)$k['id'], $kelas_selected) ? 'checked' : ''; ?>
                <label style="display:flex; align-items:center; gap:8px; padding:4px 6px; border:1px solid #eee; border-radius:6px;">
                  <input type="checkbox" name="kelas_ids[]" value="<?php echo (int)$k['id']; ?>" <?php echo $checked; ?> />
                  <span><?php echo htmlspecialchars(($k['nama_kelas'].' ('.$k['singkatan'].')') ?? ''); ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <div style="margin-top:10px;">
              <button class="btn" type="submit">Simpan Perubahan</button>
              <a class="btn" style="background:#6c757d;border-color:#6c757d" href="assignment_guru.php">Batal</a>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
<?php endif; ?>

    <div class="card">
      <div class="card-header">Daftar Assignment</div>
      <div class="card-body">
        <table class="table">
          <thead>
            <tr>
              <th>TA</th><th>Guru</th><th>Mapel</th><th>Kelas</th><th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($list)): ?>
              <tr><td colspan="5">Belum ada data assignment.</td></tr>
            <?php else: foreach($list as $it): ?>
              <tr>
                <td><?php echo htmlspecialchars(($it['nama_tahun_ajaran']) ?? ''); ?></td>
                <td><?php echo htmlspecialchars(($it['nama_guru']) ?? '').' ('.htmlspecialchars(($it['kode_guru']) ?? '').')'; ?></td>
                <td><?php echo htmlspecialchars(($it['nama_mapel']) ?? ''); ?></td>
                <td><div class="kelas-box"><?php echo htmlspecialchars($it['kelas_list'] ?? '-'); ?></div></td>
                <td class="actions">
                  <a class="btn" href="?edit_group=1&guru_id=<?php echo (int)$it['guru_id']; ?>&mapel_id=<?php echo (int)$it['mapel_id']; ?>&tahun_ajaran_id=<?php echo (int)$it['tahun_ajaran_id']; ?>">Edit</a>
                  <a class="btn" style="background:#e74c3c;border-color:#e74c3c" href="?hapus_group=1&guru_id=<?php echo (int)$it['guru_id']; ?>&mapel_id=<?php echo (int)$it['mapel_id']; ?>&tahun_ajaran_id=<?php echo (int)$it['tahun_ajaran_id']; ?>" onclick="return confirm('Hapus semua assignment dalam kombinasi ini?')">Hapus</a>
                  <a class="btn btn-danger" href="?hapus_group=1&paksa=1&guru_id=<?php echo (int)$it['guru_id']; ?>&mapel_id=<?php echo (int)$it['mapel_id']; ?>&tahun_ajaran_id=<?php echo (int)$it['tahun_ajaran_id']; ?>" title="Hapus Paksa" aria-label="Hapus Paksa" onclick="return confirm('PERINGATAN: Hapus Paksa akan menghapus seluruh data terkait (materi, soal, detail soal, jawaban siswa, tugas, pengumpulan tugas, nilai komponen) untuk semua kelas pada kombinasi ini. Lanjutkan?')"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></a>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>

