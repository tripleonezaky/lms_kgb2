<?php
/**
 * File: guru/tugas/detail.php
 * Halaman Detail Tugas untuk Guru
 * - Menampilkan info tugas (judul, kelas, mapel, deadline, isi/deskripsi)
 * - Menampilkan daftar pengumpulan untuk tugas ini dengan filter status
 * - Aksi per pengumpulan: Nilai, Minta Revisi, Komentar
 */
session_start();
require_once '../../includes/check_session.php';
require_once '../../includes/check_role.php';
check_role(['guru']);
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$guru_id = (int)$_SESSION['user_id'];
$tugas_id = isset($_GET['tugas_id']) ? (int)$_GET['tugas_id'] : 0;

if ($tugas_id <= 0) {
    set_flash('error', 'Tugas tidak valid.');
    redirect('index.php');
}

// Pastikan tugas milik guru ini
$sql_tugas = "
  SELECT t.*, k.nama_kelas, mp.nama_mapel, ag.guru_id
  FROM tugas t
  JOIN assignment_guru ag ON t.assignment_id = ag.id
  JOIN kelas k ON ag.kelas_id = k.id
  JOIN mata_pelajaran mp ON ag.mapel_id = mp.id
  WHERE t.id = {$tugas_id} AND ag.guru_id = {$guru_id}
  LIMIT 1
";
$res_tugas = query($sql_tugas);
$tugas = $res_tugas ? fetch_assoc($res_tugas) : null;
if (!$tugas) {
    set_flash('error', 'Tugas tidak ditemukan atau bukan milik Anda.');
    redirect('index.php');
}

// Normalisasi status pengumpulan (idempotent)
@query("ALTER TABLE pengumpulan_tugas MODIFY COLUMN status ENUM('submitted','late','needs_revision','graded') NOT NULL DEFAULT 'submitted'");

// Aksi: Penilaian
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nilai_id'])) {
    $pengumpulan_id = (int)$_POST['nilai_id'];
    $nilai = isset($_POST['nilai']) ? (float)$_POST['nilai'] : null;
    $feedback = isset($_POST['feedback']) ? escape_string($_POST['feedback']) : '';

    // Validasi pengumpulan untuk tugas ini dan milik guru
    $sql_check = "
        SELECT pt.id
        FROM pengumpulan_tugas pt
        JOIN tugas t ON pt.tugas_id = t.id
        JOIN assignment_guru ag ON t.assignment_id = ag.id
        WHERE pt.id = {$pengumpulan_id} AND t.id = {$tugas_id} AND ag.guru_id = {$guru_id}
        LIMIT 1
    ";
    $res_check = query($sql_check);
    if ($res_check && fetch_assoc($res_check)) {
        $nilai_val = $nilai !== null ? number_format($nilai, 2, '.', '') : 'NULL';
        $sql_upd = "UPDATE pengumpulan_tugas SET nilai = {$nilai_val}, feedback = '{$feedback}', status = 'graded' WHERE id = {$pengumpulan_id}";
        if (query($sql_upd)) {
            // Upsert ke tabel nilai sebagai Nilai Harian (konsisten dengan guru/nilai/tugas.php)
            $sql_detail = "SELECT t.id AS tugas_id, ag.mapel_id, ag.kelas_id, ag.tahun_ajaran_id, u.id AS siswa_id, ta.semester
            FROM pengumpulan_tugas pt
            JOIN tugas t ON pt.tugas_id = t.id
            JOIN assignment_guru ag ON t.assignment_id = ag.id
            JOIN users u ON pt.siswa_id = u.id
            JOIN tahun_ajaran ta ON ag.tahun_ajaran_id = ta.id
            WHERE pt.id = {$pengumpulan_id} LIMIT 1";
            $rd = query($sql_detail); $d = $rd ? fetch_assoc($rd) : null;
            if ($d && $nilai !== null) {
                $mapel_id = (int)$d['mapel_id'];
                $kelas_id = (int)$d['kelas_id'];
                $tahun_ajaran_id = (int)$d['tahun_ajaran_id'];
                $siswa_id_n = (int)$d['siswa_id'];
                $semester = ($d['semester']=='2') ? 'Genap' : 'Ganjil';
                $jenis = 'Harian';
                $cek = query("SELECT id FROM nilai WHERE siswa_id={$siswa_id_n} AND mapel_id={$mapel_id} AND kelas_id={$kelas_id} AND tahun_ajaran_id={$tahun_ajaran_id} AND semester='".escape_string($semester)."' AND jenis_penilaian='".escape_string($jenis)."' LIMIT 1");
                $rowC = $cek ? fetch_assoc($cek) : null;
                if ($rowC) {
                    query("UPDATE nilai SET nilai=".(float)$nilai_val.", updated_at='".date('Y-m-d H:i:s')."' WHERE id=".(int)$rowC['id']);
                } else {
                    query("INSERT INTO nilai (siswa_id,mapel_id,kelas_id,tahun_ajaran_id,semester,jenis_penilaian,nilai,created_at,updated_at) VALUES ("
                    ."{$siswa_id_n},{$mapel_id},{$kelas_id},{$tahun_ajaran_id},'".escape_string($semester)."','".escape_string($jenis)."',".(float)$nilai_val.",'".date('Y-m-d H:i:s')."','".date('Y-m-d H:i:s')."')");
                }
            }
            set_flash('success', 'Penilaian disimpan.');
        } else {
            set_flash('error', 'Gagal menyimpan penilaian.');
        }
    } else {
        set_flash('error', 'Data tidak valid.');
    }
    redirect('detail.php?tugas_id='.$tugas_id);
}

// Aksi: Minta Revisi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_revision'])) {
    $pengumpulan_id = (int)$_POST['mark_revision'];
    $sql_check = "
        SELECT pt.id
        FROM pengumpulan_tugas pt
        JOIN tugas t ON pt.tugas_id = t.id
        JOIN assignment_guru ag ON t.assignment_id = ag.id
        WHERE pt.id = {$pengumpulan_id} AND t.id = {$tugas_id} AND ag.guru_id = {$guru_id}
        LIMIT 1
    ";
    $res_check = query($sql_check);
    if ($res_check && fetch_assoc($res_check)) {
        $ok = query("UPDATE pengumpulan_tugas SET status = 'needs_revision' WHERE id = {$pengumpulan_id}");
        set_flash($ok? 'success':'error', $ok? 'Status diubah ke Perlu Revisi.':'Gagal mengubah status.');
    } else {
        set_flash('error', 'Data tidak valid.');
    }
    redirect('detail.php?tugas_id='.$tugas_id);
}

// Filter pengumpulan
$allowed_filters = ['all','submitted','needs_revision','late','graded'];
$filter = isset($_GET['filter']) && in_array($_GET['filter'], $allowed_filters, true) ? $_GET['filter'] : 'all';

$where_status = '';
if ($filter !== 'all') {
    $where_status = " AND pt.status='".escape_string($filter)."'";
}

// Hitung counter per status
$counts = [
  'submitted' => 0,
  'needs_revision' => 0,
  'late' => 0,
  'graded' => 0
];
$sql_counts = "
  SELECT pt.status, COUNT(*) AS cnt
  FROM pengumpulan_tugas pt
  JOIN tugas t ON pt.tugas_id = t.id
  JOIN assignment_guru ag ON t.assignment_id = ag.id
  WHERE t.id={$tugas_id} AND ag.guru_id={$guru_id}
  GROUP BY pt.status
";
$res_counts = query($sql_counts);
if ($res_counts) {
  while($r = fetch_assoc($res_counts)) {
    if (isset($counts[$r['status']])) $counts[$r['status']] = (int)$r['cnt'];
  }
}

// Ambil daftar pengumpulan untuk tugas ini
$sql_list = "
  SELECT pt.id, pt.tanggal_submit, pt.status, pt.file_path, pt.isi_jawaban, pt.nilai, pt.feedback,
         u.nama_lengkap AS nama_siswa, u.nisn, k.nama_kelas
  FROM pengumpulan_tugas pt
  JOIN tugas t ON pt.tugas_id = t.id
  JOIN assignment_guru ag ON t.assignment_id = ag.id
  JOIN users u ON pt.siswa_id = u.id
  JOIN kelas k ON u.kelas_id = k.id
  WHERE t.id={$tugas_id} AND ag.guru_id={$guru_id} {$where_status}
  ORDER BY (pt.status = 'needs_revision') DESC, (pt.status = 'submitted') DESC, (pt.status = 'late') DESC, pt.tanggal_submit ASC
";
$res_list = query($sql_list);
$submissions = fetch_all($res_list);

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detail Tugas - Guru</title>
  <link rel="stylesheet" href="../../assets/css/style.css">
  <style>
    body { font-family: 'Poppins', sans-serif; background:#f5f7fa; }
    .container { max-width: 1100px; margin: 20px auto; }
    .header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
    .card { background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.08); margin-bottom:14px; }
    .card-header { padding:16px 20px; border-bottom:1px solid #eee; font-weight:600; }
    .card-body { padding:20px; }
    .btn { cursor:pointer; padding:8px 12px; border-radius:6px; border:none; }
    .btn-primary { background:#1e5ba8; color:#fff; }
    .btn-secondary { background:#2c7be5; color:#fff; border:1px solid #2c7be5; text-decoration:none; }
    .btn-danger { background:#dc2626; color:#fff; }
    .badge { padding:2px 8px; border-radius:12px; font-size:12px; }
    .badge-warn { background:#fff7ed; color:#9a3412; }
    .badge-ok { background:#ecfdf5; color:#065f46; }
    .muted { color:#64748b; font-size:12px; }
    table { width:100%; border-collapse: collapse; }
    th, td { border-bottom:1px solid #eee; padding:10px; text-align:left; }
    th { background:#f8fafc; }
    .filter { display:flex; gap:8px; flex-wrap:wrap; }
    .filter a { text-decoration:none; padding:6px 10px; border-radius:6px; border:1px solid #cbd5e1; color:#0f172a; background:#fff; }
    .filter a.active { background:#1e5ba8; color:#fff; border-color:#1e5ba8; }
    details > summary { cursor:pointer; }
    .comment-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:8px;margin-top:6px}
    .comment-item{border-bottom:1px dashed #e2e8f0;padding:6px 0}
    .comment-item:last-child{border-bottom:0}
    form.inline { display:flex; gap:8px; align-items:center; }
    input[type=number] { width:90px; padding:6px 8px; border:1px solid #cbd5e1; border-radius:6px; }
    input[type=text] { width:200px; padding:6px 8px; border:1px solid #cbd5e1; border-radius:6px; }
  </style>
</head>
<body>
<div class="container">
      <div class="header">
    <h2><i class="fas fa-thumbtack" aria-hidden="true"></i> Detail Tugas</h2>
    <div>
      <a href="index.php" class="btn btn-secondary back-btn" onclick="var href=this.getAttribute('href'); document.body.style.transition='opacity .22s'; document.body.style.opacity=0; setTimeout(function(){ if (history.length>1) { history.back(); } else { window.location=href; } },220); return false;"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali ke List</a>
      <a href="index.php?edit_id=<?php echo (int)$tugas['id']; ?>" class="btn btn-primary"><i class="fas fa-pen" aria-hidden="true"></i> Edit Tugas</a>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="card" style="border:1px solid #a7f3d0; background:#ecfdf5; color:#065f46;">
      <div class="card-body"><?php echo htmlspecialchars(($flash['message']) ?? ''); ?></div>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">Informasi Tugas</div>
    <div class="card-body">
      <div><strong>Judul:</strong> <?php echo htmlspecialchars(($tugas['judul_tugas']) ?? ''); ?></div>
      <div><strong>Kelas:</strong> <?php echo htmlspecialchars(($tugas['nama_kelas']) ?? ''); ?> &nbsp; <strong>Mapel:</strong> <?php echo htmlspecialchars(($tugas['nama_mapel']) ?? ''); ?></div>
      <div><strong>Deadline:</strong> <?php echo htmlspecialchars(($tugas['deadline']) ?? ''); ?></div>
      <?php if (!empty($tugas['deskripsi'])): ?>
        <div style="margin-top:6px"><strong>Deskripsi:</strong><br><div class="muted" style="white-space:pre-wrap; border:1px solid #e5e7eb; padding:8px; border-radius:6px;"><?php echo nl2br(htmlspecialchars(($tugas['deskripsi']) ?? '')); ?></div></div>
      <?php endif; ?>
      <?php if (!empty($tugas['isi_tugas'])): ?>
        <div style="margin-top:6px"><strong>Isi Tugas:</strong><br><div style="white-space:pre-wrap; border:1px solid #e5e7eb; padding:8px; border-radius:6px;"><?php echo nl2br(htmlspecialchars(($tugas['isi_tugas']) ?? '')); ?></div></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Pengumpulan Siswa</div>
    <div class="card-body">
      <div class="filter" style="margin-bottom:10px">
        <?php 
          $tabs = [
            'all' => 'Semua',
            'submitted' => 'Menunggu ('.$counts['submitted'].')',
            'needs_revision' => 'Perlu Revisi ('.$counts['needs_revision'].')',
            'late' => 'Terlambat ('.$counts['late'].')',
            'graded' => 'Sudah Dinilai ('.$counts['graded'].')'
          ];
          foreach ($tabs as $key => $label):
            $active = $filter===$key ? 'active' : '';
        ?>
          <a class="<?php echo $active; ?>" href="detail.php?tugas_id=<?php echo (int)$tugas_id; ?>&filter=<?php echo urlencode($key); ?>"><?php echo htmlspecialchars(($label) ?? ''); ?></a>
        <?php endforeach; ?>
      </div>

      <table>
        <thead>
          <tr>
            <th>No</th>
            <th>Siswa</th>
            <th>Kelas</th>
            <th>Submit</th>
            <th>Status</th>
            <th>Nilai</th>
            <th>File</th>
            <th>Jawaban</th>
            <th>Aksi</th>
            <th>Komentar</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($submissions)): ?>
          <tr><td colspan="10" style="text-align:center; color:#64748b; padding:16px;">Belum ada pengumpulan untuk filter ini.</td></tr>
        <?php else: $no=1; foreach ($submissions as $s): ?>
          <tr>
            <td><?php echo $no++; ?></td>
            <td><?php echo htmlspecialchars(($s['nama_siswa']) ?? ''); ?> (<?php echo htmlspecialchars(($s['nisn']) ?? ''); ?>)</td>
            <td><?php echo htmlspecialchars(($s['nama_kelas']) ?? ''); ?></td>
            <td><?php echo htmlspecialchars(($s['tanggal_submit']) ?? ''); ?></td>
            <td>
              <?php if ($s['status']==='submitted'): ?>
                <span class="badge badge-warn">Menunggu</span>
              <?php elseif ($s['status']==='needs_revision'): ?>
                <span class="badge badge-warn">Perlu Revisi</span>
              <?php elseif ($s['status']==='late'): ?>
                <span class="badge badge-warn">Terlambat</span>
              <?php elseif ($s['status']==='graded'): ?>
                <span class="badge badge-ok">Sudah Dinilai</span>
              <?php else: ?>
                <span class="badge"><?php echo htmlspecialchars(($s['status']) ?? ''); ?></span>
              <?php endif; ?>
            </td>
            <td><?php echo isset($s['nilai'])? htmlspecialchars(($s['nilai']) ?? '') : '-'; ?></td>
            <td><?php echo $s['file_path'] ? '<a href="../../assets/uploads/'.htmlspecialchars(($s['file_path']) ?? '').'" target="_blank">Lihat</a>' : '-'; ?></td>
            <td>
              <?php if (!empty($s['isi_jawaban'])): ?>
                <details>
                  <summary>Lihat</summary>
                  <div style="white-space:pre-wrap; border:1px solid #e5e7eb; padding:8px; border-radius:6px; margin-top:6px; max-height:200px; overflow:auto;">
                    <?php echo nl2br(htmlspecialchars(($s['isi_jawaban']) ?? '')); ?>
                  </div>
                </details>
              <?php else: ?>-
              <?php endif; ?>
            </td>
            <td>
              <form method="post" class="inline" style="margin-bottom:6px">
                <input type="hidden" name="nilai_id" value="<?php echo (int)$s['id']; ?>">
                <input type="number" name="nilai" min="0" max="100" step="0.01" placeholder="0-100" required>
                <button type="submit" class="btn btn-primary">Simpan</button>
              </form>
              <form method="post" onsubmit="return confirm('Minta revisi untuk pengumpulan ini?');">
                <input type="hidden" name="mark_revision" value="<?php echo (int)$s['id']; ?>">
                <button class="btn btn-danger" type="submit">Minta Revisi</button>
              </form>
            </td>
            <td>
              <div class="comment-box" id="cbox-<?php echo (int)$s['id']; ?>" style="display:block"></div>
              <div style="margin-top:6px; display:flex; gap:6px;">
                <input type="text" id="cinput-<?php echo (int)$s['id']; ?>" placeholder="Tulis komentar..." style="flex:1; padding:6px; border:1px solid #cbd5e1; border-radius:6px;">
                <button class="btn btn-primary" onclick="sendComment(<?php echo (int)$s['id']; ?>)">Kirim</button>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
async function loadComments(pengumpulanId){
  const box = document.getElementById('cbox-'+pengumpulanId);
  if(!box) return; box.style.display='block';
  box.innerHTML = '<div class="muted">Memuat komentar...</div>';
  const res = await fetch(`../../includes/comments_api.php?action=list_comments&pengumpulan_id=${pengumpulanId}`);
  const js = await res.json();
  if(!js.success){ box.innerHTML = '<div class="muted">Gagal memuat</div>'; return; }
  const arr = js.data.comments||[];
  if(arr.length===0){ box.innerHTML = '<div class="muted">Belum ada komentar</div>'; return; }
  box.innerHTML = arr.map(c=> `
    <div class=\"comment-item\">
      <div style=\"display:flex;justify-content:space-between;align-items:center\">
        <div><strong>${c.guru_nama||'Guru'}</strong> <span class=\"muted\">${c.created_at}</span>${c.is_edited==1?' <span class=\"muted\">(edited)</span>':''}</div>
      </div>
      <div>${(c.comment||'').replaceAll('\\n','<br>')}</div>
    </div>`).join('');
}
async function sendComment(pengumpulanId){
  const input = document.getElementById('cinput-'+pengumpulanId);
  const val = (input.value||'').trim(); if(!val) return;
  const fd = new FormData(); fd.append('pengumpulan_id', pengumpulanId); fd.append('comment', val);
  const res = await fetch('../../includes/comments_api.php?action=add_comment', { method:'POST', body: fd });
  const js = await res.json(); if(js.success){ input.value=''; loadComments(pengumpulanId); } else { alert(js.message||'Gagal'); }
}
async function editComment(pengumpulanId, commentId, oldVal){
  const val = prompt('Edit komentar:', oldVal||'');
  if(val===null) return;
  const fd = new FormData(); fd.append('comment_id', commentId); fd.append('comment', val);
  const res = await fetch('../../includes/comments_api.php?action=edit_comment', { method:'POST', body: fd });
  const js = await res.json(); if(js.success){ loadComments(pengumpulanId); } else { alert(js.message||'Gagal mengedit'); }
}
async function deleteComment(pengumpulanId, commentId){
  if(!confirm('Hapus komentar ini?')) return;
  const fd = new FormData(); fd.append('comment_id', commentId);
  const res = await fetch('../../includes/comments_api.php?action=delete_comment', { method:'POST', body: fd });
  const js = await res.json(); if(js.success){ loadComments(pengumpulanId); } else { alert(js.message||'Gagal menghapus'); }
}
</script>
<script>
window.addEventListener('DOMContentLoaded', ()=>{
  document.querySelectorAll('[id^="cbox-"]').forEach(el=>{
    const id = parseInt(el.id.replace('cbox-','')); if(id) loadComments(id);
  });
});
</script>
</body>
</html>

