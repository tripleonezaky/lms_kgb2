<?php
/**
 * File: guru/materi/index.php
 * Fitur: daftar materi milik guru dan form tambah materi dengan upload file (pdf/docx/ppt/xls)
 */
session_start();
require_once '../../includes/check_session.php';
require_once '../../includes/check_role.php';
check_role(['guru']);
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$guru_id = (int)$_SESSION['user_id'];

// Pivot untuk distribusi materi ke banyak kelas
@query("CREATE TABLE IF NOT EXISTS materi_kelas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  materi_id INT NOT NULL,
  kelas_id INT NOT NULL,
  created_at DATETIME NULL,
  UNIQUE KEY uniq_mk (materi_id, kelas_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ambil assignment guru untuk dropdown
$sql_assign = "
    SELECT ag.id AS assignment_id, k.nama_kelas, mp.nama_mapel, ta.nama_tahun_ajaran
    FROM assignment_guru ag
    JOIN kelas k ON ag.kelas_id = k.id
    JOIN mata_pelajaran mp ON ag.mapel_id = mp.id
    JOIN tahun_ajaran ta ON ag.tahun_ajaran_id = ta.id
    WHERE ag.guru_id = {$guru_id}
    ORDER BY ta.id DESC, k.nama_kelas, mp.nama_mapel
";
$assignments = fetch_all(query($sql_assign));

// Handle create + upload file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    $assignment_id = (int)($_POST['assignment_id'] ?? 0);
    $judul = escape_string($_POST['judul_materi'] ?? '');
    $deskripsi = escape_string($_POST['deskripsi'] ?? '');
    $stored_path = null;

    // Upload file jika ada
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['pdf','doc','docx','ppt','pptx','xls','xlsx'];
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $dir = __DIR__ . '/../../assets/uploads/materi/';
            if (!is_dir($dir)) { mkdir($dir, 0777, true); }
            $newName = 'materi-'.($assignment_id ?: 'X').'-'.time().'-'.bin2hex(random_bytes(3)).'.'.$ext;
            $dest = $dir.$newName;
            if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
                $stored_path = 'materi/'.$newName; // relative from assets/uploads
            }
        }
    }

    if ($assignment_id > 0 && $judul !== '') {
        // Validasi assignment milik guru
        $check = query("SELECT id FROM assignment_guru WHERE id = {$assignment_id} AND guru_id = {$guru_id} LIMIT 1");
        if ($check && fetch_assoc($check)) {
            $file_sql = $stored_path ? "'".escape_string($stored_path)."'" : "NULL";
            $sql_ins = "INSERT INTO materi (assignment_id, judul_materi, deskripsi, file_path) VALUES ({$assignment_id}, '{$judul}', '{$deskripsi}', {$file_sql})";
            if (query($sql_ins)) set_flash('success', 'Materi berhasil ditambahkan.'); else set_flash('error', 'Gagal menambah materi.');
        } else {
            set_flash('error', 'Assignment tidak valid.');
        }
    } else {
        set_flash('error', 'Lengkapi data materi.');
    }
    redirect('index.php');
}

// Handle delete
if (isset($_GET['action']) && $_GET['action']==='delete' && isset($_GET['id'])){
    $id = (int)$_GET['id'];
    // Pastikan materi milik guru
    $cek = query("SELECT m.id FROM materi m JOIN assignment_guru ag ON m.assignment_id=ag.id WHERE m.id={$id} AND ag.guru_id={$guru_id} LIMIT 1");
    if ($cek && fetch_assoc($cek)) {
        query("DELETE FROM materi_kelas WHERE materi_id={$id}");
        query("DELETE FROM materi WHERE id={$id} LIMIT 1");
        set_flash('success','Materi dihapus');
    } else { set_flash('error','Akses ditolak'); }
    redirect('index.php');
}

// Handle deliver (distribusi ke kelas lain)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['deliver'])){
    $materi_id = (int)($_POST['materi_id'] ?? 0);
    $kelas_ids = isset($_POST['kelas_ids']) ? (array)$_POST['kelas_ids'] : [];
    // Validasi materi milik guru
    $cek = query("SELECT m.id FROM materi m JOIN assignment_guru ag ON m.assignment_id=ag.id WHERE m.id={$materi_id} AND ag.guru_id={$guru_id} LIMIT 1");
    if (!$cek || !fetch_assoc($cek)) { set_flash('error','Akses ditolak'); redirect('index.php'); }
    // Ambil kelas yang relevan untuk guru ini berdasarkan assignmentnya
    $resK = query("SELECT DISTINCT k.id FROM assignment_guru ag JOIN kelas k ON k.id=ag.kelas_id WHERE ag.guru_id={$guru_id}");
    $allowedKelas = [];
    while($row = fetch_assoc($resK)){ $allowedKelas[(int)$row['id']] = true; }
    foreach ($kelas_ids as $kid){
        $kid = (int)$kid; if (!isset($allowedKelas[$kid])) continue;
        query("INSERT INTO materi_kelas (materi_id, kelas_id, created_at) VALUES ({$materi_id}, {$kid}, '".date('Y-m-d H:i:s')."') ON DUPLICATE KEY UPDATE kelas_id=VALUES(kelas_id)");
    }
    set_flash('success','Materi didistribusikan ke kelas terpilih.');
    redirect('index.php');
}

// Ambil list materi milik guru
$sql_list = "
    SELECT m.id, m.judul_materi, m.tanggal_upload, m.file_path,
           k.nama_kelas, k.id AS kelas_id, mp.nama_mapel
    FROM materi m
    JOIN assignment_guru ag ON m.assignment_id = ag.id
    JOIN kelas k ON ag.kelas_id = k.id
    JOIN mata_pelajaran mp ON ag.mapel_id = mp.id
    WHERE ag.guru_id = {$guru_id}
    ORDER BY m.tanggal_upload DESC
";
$materi_list = fetch_all(query($sql_list));
$flash = get_flash();
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Materi - Guru</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .container{max-width:1100px;margin:16px auto;padding:12px}
        .card{background:#fff;border:1px solid #ddd;border-radius:10px;margin-bottom:16px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,.05)}
        .card-header{padding:14px 18px;background:#f5f5f5;font-weight:600}
        .card-body{padding:18px}
        .row{display:flex;gap:14px;flex-wrap:wrap}
        .row .col{flex:1 1 280px}
        label{display:block;margin-bottom:6px;font-weight:600}
        input[type=text],input[type=datetime-local],select,textarea,input[type=file]{width:100%;padding:12px;border:1px solid #ced4da;border-radius:8px;background:#fff;box-sizing:border-box}
        textarea{min-height:120px}
        input:focus,select:focus,textarea:focus{outline:none;border-color:#2c7be5;box-shadow:0 0 0 3px rgba(44,123,229,.15)}
        .btn{display:inline-block;padding:10px 14px;border-radius:8px;border:1px solid #2c7be5;background:#2c7be5;color:#fff;cursor:pointer;text-decoration:none}
        .btn:hover{filter:brightness(0.95)}
        .table{width:100%;border-collapse:collapse}
        .table th,.table td{border:1px solid #e5e5e5;padding:12px}
        .table th{background:#fafafa;text-align:left}
        .alert{padding:12px;border-radius:8px;margin-bottom:12px}
        .alert-error{background:#fdecea;color:#b00020}
        .alert-success{background:#e6f4ea;color:#1e7e34}
        .muted{color:#64748b;font-size:12px}
        .actions{display:flex;gap:10px;align-items:center}
        .icon-btn{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;border:1px solid #d0d7de;background:#fff;color:#2c7be5;text-decoration:none}
        .icon-btn:hover{background:#f3f6ff}
    </style>
</head>
<body>
<div class="container">
    <div class="card">
      <div class="card-header">Tambah Materi</div>
      <div class="card-body">
        <div style="margin-bottom:10px">
          <a href="../dashboard.php" class="btn back-btn" onclick="var href=this.getAttribute('href'); document.body.style.transition='opacity .22s'; document.body.style.opacity=0; setTimeout(function(){ if (history.length>1) { history.back(); } else { window.location=href; } },220); return false;"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali</a>
        </div>
        <?php if ($flash): ?>
          <div class="alert alert-<?php echo htmlspecialchars(($flash['type']) ?? ''); ?>"><?php echo htmlspecialchars(($flash['message']) ?? ''); ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="create" value="1">
          <div class="row">
            <div class="col">
              <label>Assignment (Kelas & Mapel)</label>
              <select name="assignment_id" required>
                <option value="">- pilih -</option>
                <?php foreach ($assignments as $a): ?>
                  <option value="<?php echo (int)$a['assignment_id']; ?>">TA <?php echo htmlspecialchars(($a['nama_tahun_ajaran']) ?? ''); ?> - <?php echo htmlspecialchars(($a['nama_kelas']) ?? ''); ?> - <?php echo htmlspecialchars(($a['nama_mapel']) ?? ''); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col">
              <label>Judul Materi</label>
              <input type="text" name="judul_materi" required />
            </div>
          </div>
          <div>
            <label>Deskripsi</label>
            <textarea name="deskripsi" rows="3"></textarea>
          </div>
          <div class="row">
            <div class="col">
              <label>File (opsional)</label>
              <input type="file" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx" />
              <small class="muted">Tipe diizinkan: pdf, doc, docx, ppt, pptx, xls, xlsx</small>
            </div>
          </div>
          <div style="margin-top:10px">
            <button class="btn" type="submit">Simpan</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Daftar Materi</div>
      <div class="card-body">
        <table class="table">
          <thead>
            <tr>
              <th>Judul</th>
              <th>Kelas</th>
              <th>Mapel</th>
              <th>Upload</th>
              <th>File</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($materi_list)): ?>
            <tr><td colspan="6">Belum ada materi.</td></tr>
          <?php else: foreach ($materi_list as $m): ?>
            <tr>
              <td><?php echo htmlspecialchars(($m['judul_materi']) ?? ''); ?></td>
              <td><?php echo htmlspecialchars(($m['nama_kelas']) ?? ''); ?></td>
              <td><?php echo htmlspecialchars(($m['nama_mapel']) ?? ''); ?></td>
              <td><?php echo htmlspecialchars(($m['tanggal_upload']) ?? ''); ?></td>
              <td><?php echo $m['file_path'] ? '<a href="../../assets/uploads/' . htmlspecialchars(($m['file_path']) ?? '') . '" target="_blank">Lihat</a>' : '-'; ?></td>
              <td class="actions">
                <a class="icon-btn" href="edit.php?id=<?php echo (int)$m['id']; ?>" title="Edit" aria-label="Edit">✎</a>
                <a class="icon-btn" href="index.php?action=delete&id=<?php echo (int)$m['id']; ?>" onclick="return confirm('Hapus materi ini?')" title="Hapus" aria-label="Hapus">🗑</a>
                <a class="icon-btn" href="#" title="Deliver ke Kelas" aria-label="Deliver" onclick="openDeliver(<?php echo (int)$m['id']; ?>, '<?php echo htmlspecialchars((addslashes($m['judul_materi']) ?? '')); ?>')">⇪</a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
<!-- Modal Deliver -->
    <div id="deliver-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:12px;z-index:9999">
      <div style="background:#fff;max-width:520px;width:100%;border-radius:10px;overflow:hidden">
        <div style="padding:14px 18px;background:#f5f5f5;font-weight:600">Deliver Materi ke Kelas</div>
        <div style="padding:18px">
          <form method="post">
            <input type="hidden" name="deliver" value="1" />
            <input type="hidden" name="materi_id" id="deliver-materi-id" />
            <div style="margin-bottom:10px">
              <div style="font-weight:600" id="deliver-title">Materi</div>
              <div class="muted" style="margin-top:4px">Pilih kelas tujuan (bisa lebih dari satu)</div>
            </div>
            <div style="max-height:280px;overflow:auto;border:1px solid #e5e5e5;border-radius:8px;padding:10px">
              <?php
                $kelasRes = query("SELECT DISTINCT k.id, k.nama_kelas FROM assignment_guru ag JOIN kelas k ON k.id=ag.kelas_id WHERE ag.guru_id={$guru_id} ORDER BY k.nama_kelas");
                if ($kelasRes) {
                  while ($row = fetch_assoc($kelasRes)) {
                    echo '<label style="display:block;margin-bottom:6px"><input type="checkbox" name="kelas_ids[]" value="'.(int)$row['id'].'"> '.htmlspecialchars(($row['nama_kelas']) ?? '').'</label>';
                  }
                }
              ?>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px">
              <button type="button" class="btn" onclick="closeDeliver()">Batal</button>
              <button type="submit" class="btn">Simpan</button>
            </div>
          </form>
        </div>
      </div>
    </div>
</div>
<script>
  function openDeliver(id, title){
    document.getElementById('deliver-materi-id').value = id;
    document.getElementById('deliver-title').textContent = title;
    document.getElementById('deliver-modal').style.display = 'flex';
  }
  function closeDeliver(){
    document.getElementById('deliver-modal').style.display = 'none';
  }
</script>
</body>
</html>
