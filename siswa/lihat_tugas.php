<?php
session_start();
require_once '../includes/check_session.php';
require_once '../includes/check_role.php';
check_role(['siswa']);
require_once '../config/database.php';
require_once '../includes/functions.php';

$siswa_id = (int)$_SESSION['user_id'];
$kelas_id = isset($_SESSION['kelas_id']) ? (int)$_SESSION['kelas_id'] : 0;
$kelas_nama = '-';
// Fallback: jika kelas_id belum ada di session, ambil dari DB agar daftar tugas tetap muncul
if ($kelas_id === 0) {
    $resK = query("SELECT kelas_id FROM users WHERE id = {$siswa_id} LIMIT 1");
    $rowK = $resK ? fetch_assoc($resK) : null;
    if ($rowK && (int)$rowK['kelas_id'] > 0) {
        $kelas_id = (int)$rowK['kelas_id'];
        $_SESSION['kelas_id'] = $kelas_id; // set ke session untuk akses berikutnya
    }
}
// Ambil nama kelas untuk tampilan
if ($kelas_id > 0) {
    $rk = query("SELECT nama_kelas FROM kelas WHERE id={$kelas_id} LIMIT 1");
    $rkrow = $rk ? fetch_assoc($rk) : null;
    if ($rkrow && !empty($rkrow['nama_kelas'])) { $kelas_nama = $rkrow['nama_kelas']; }
}
// Optional: paksa refresh kelas dari DB jika diminta
if (isset($_GET['force_kelas']) && (int)$_GET['force_kelas'] === 1) {
    $resK = query("SELECT kelas_id FROM users WHERE id = {$siswa_id} LIMIT 1");
    $rowK = $resK ? fetch_assoc($resK) : null;
    if ($rowK && (int)$rowK['kelas_id'] > 0) {
        $kelas_id = (int)$rowK['kelas_id'];
        $_SESSION['kelas_id'] = $kelas_id;
        // refresh nama kelas
        $rk = query("SELECT nama_kelas FROM kelas WHERE id={$kelas_id} LIMIT 1");
        $rkrow = $rk ? fetch_assoc($rk) : null;
        if ($rkrow && !empty($rkrow['nama_kelas'])) { $kelas_nama = $rkrow['nama_kelas']; }
    }
}

// Handle resubmit pengumpulan tugas (update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resubmit']) && isset($_POST['pengumpulan_id'])) {
    $pengumpulan_id = (int)$_POST['pengumpulan_id'];
    $isi_jawaban = trim($_POST['isi_jawaban'] ?? '');

    // Validasi pengumpulan milik siswa & boleh diubah
    $sqlp = "SELECT pt.id, pt.siswa_id, pt.tugas_id, pt.status, pt.file_path
             FROM pengumpulan_tugas pt
             JOIN tugas t ON pt.tugas_id=t.id
             JOIN assignment_guru ag ON t.assignment_id=ag.id
             WHERE pt.id={$pengumpulan_id} AND pt.siswa_id={$siswa_id} AND ag.kelas_id={$kelas_id} LIMIT 1";
    $resp = query($sqlp); $rp = $resp? fetch_assoc($resp): null;
    if (!$rp) { set_flash('error','Pengumpulan tidak valid.'); redirect('lihat_tugas.php'); }
    if ($rp['status']==='graded') { set_flash('error','Pengumpulan sudah dinilai dan tidak dapat diubah.'); redirect('lihat_tugas.php'); }

    // Upload file (opsional)
    $stored_path = $rp['file_path']; $file_replaced = false;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['pdf','doc','docx','ppt','pptx','xls','xlsx','zip'];
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $dir = __DIR__ . '/../assets/uploads/pengumpulan_tugas/';
            if (!is_dir($dir)) { mkdir($dir, 0777, true); }
            $newName = 'tugas-'.$rp['tugas_id'].'-s'.$siswa_id.'-'.time().'-'.bin2hex(random_bytes(3)).'.'.$ext;
            $dest = $dir.$newName;
            if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
                // hapus file lama bila ada dan berbeda
                if (!empty($stored_path)) {
                    $old = __DIR__ . '/../assets/uploads/' . $stored_path;
                    if (is_file($old)) { @unlink($old); }
                }
                $stored_path = 'pengumpulan_tugas/'.$newName;
                $file_replaced = true;
            }
        }
    }

    // Minimal harus ada salah satu (isi atau file) jika sebelumnya keduanya kosong
    if ($stored_path === '' && $isi_jawaban === '') {
        set_flash('error','Isi jawaban atau unggah file wajib salah satu.');
        redirect('lihat_tugas.php');
    }

    $upd = "UPDATE pengumpulan_tugas SET isi_jawaban='".escape_string($isi_jawaban)."', file_path='".escape_string($stored_path)."', status='submitted', edited_at='".date('Y-m-d H:i:s')."' WHERE id={$pengumpulan_id}";
    $ok = query($upd);
    set_flash($ok? 'success':'error', $ok? 'Pengumpulan diperbarui.':'Gagal memperbarui.');
    redirect('lihat_tugas.php');
}

// Handle upload pengumpulan tugas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tugas_id'])) {
    $tugas_id = (int)$_POST['tugas_id'];
    $isi_jawaban = trim($_POST['isi_jawaban'] ?? '');

    // Validasi tugas milik kelas siswa
    $sql_check = "SELECT t.id, t.deadline FROM tugas t JOIN assignment_guru ag ON t.assignment_id = ag.id WHERE t.id={$tugas_id} AND ag.kelas_id={$kelas_id} LIMIT 1";
    $res_check = query($sql_check);
    $row_check = $res_check ? fetch_assoc($res_check) : null;
    if (!$row_check) {
        set_flash('error','Tugas tidak valid.');
        redirect('lihat_tugas.php');
    }

    // Cek sudah submit? (unique constraint tugas_id+siswa_id)
    $res_exist = query("SELECT id FROM pengumpulan_tugas WHERE tugas_id={$tugas_id} AND siswa_id={$siswa_id} LIMIT 1");
    if ($res_exist && fetch_assoc($res_exist)) {
        set_flash('error','Anda sudah mengumpulkan tugas ini.');
        redirect('lihat_tugas.php');
    }

    // Upload file
    $file_ok = false; $stored_path = '';
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['pdf','doc','docx','ppt','pptx','xls','xlsx','zip'];
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $dir = __DIR__ . '/../assets/uploads/pengumpulan_tugas/';
            if (!is_dir($dir)) { mkdir($dir, 0777, true); }
            $newName = 'tugas-'.$tugas_id.'-s'.$siswa_id.'-'.time().'-'.bin2hex(random_bytes(3)).'.'.$ext;
            $dest = $dir.$newName;
            if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
                $stored_path = 'pengumpulan_tugas/'.$newName; // relative path from assets/uploads
                $file_ok = true;
            }
        }
    }

        if (!$file_ok && $isi_jawaban === '') {
        set_flash('error','Isi jawaban atau unggah file wajib salah satu.');
        redirect('lihat_tugas.php');
    }

    // Tentukan status: submitted/late
    $now = date('Y-m-d H:i:s');
    $status = (strtotime($now) > strtotime($row_check['deadline'])) ? 'late' : 'submitted';

    $sql_ins = "INSERT INTO pengumpulan_tugas (tugas_id, siswa_id, file_path, isi_jawaban, status) VALUES ({$tugas_id}, {$siswa_id}, '".escape_string($stored_path)."', '".escape_string($isi_jawaban)."', '{$status}')";
    if (query($sql_ins)) set_flash('success','Tugas berhasil dikumpulkan.'); else set_flash('error','Gagal menyimpan pengumpulan.');
    redirect('lihat_tugas.php');
}

// Ambil daftar tugas + status pengumpulan siswa
$sql_tugas = "
    SELECT t.id, t.judul_tugas, t.deskripsi, t.isi_tugas, t.deadline, mp.nama_mapel, t.file_path AS lampiran_tugas,
           pt.id AS pengumpulan_id, pt.status AS status_pengumpulan, pt.nilai, pt.feedback, pt.isi_jawaban
    FROM tugas t
    JOIN assignment_guru ag ON t.assignment_id = ag.id
    JOIN mata_pelajaran mp ON ag.mapel_id = mp.id
    LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id = t.id AND pt.siswa_id = {$siswa_id}
    WHERE ag.kelas_id = {$kelas_id}
    ORDER BY t.deadline ASC
";
$tugas_rows = fetch_all(query($sql_tugas));
// Jika kosong, coba re-sync kelas_id dari DB lalu query ulang sekali lagi
if (empty($tugas_rows)) {
    $resK2 = query("SELECT kelas_id FROM users WHERE id = {$siswa_id} LIMIT 1");
    $rowK2 = $resK2 ? fetch_assoc($resK2) : null;
    if ($rowK2 && (int)$rowK2['kelas_id'] > 0 && (int)$rowK2['kelas_id'] !== (int)$kelas_id) {
        $kelas_id = (int)$rowK2['kelas_id'];
        $_SESSION['kelas_id'] = $kelas_id;
        $sql_tugas = "
            SELECT t.id, t.judul_tugas, t.deskripsi, t.isi_tugas, t.deadline, mp.nama_mapel, t.file_path AS lampiran_tugas,
                   pt.id AS pengumpulan_id, pt.status AS status_pengumpulan, pt.nilai, pt.feedback, pt.isi_jawaban
            FROM tugas t
            JOIN assignment_guru ag ON t.assignment_id = ag.id
            JOIN mata_pelajaran mp ON ag.mapel_id = mp.id
            LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id = t.id AND pt.siswa_id = {$siswa_id}
            WHERE ag.kelas_id = {$kelas_id}
            ORDER BY t.deadline ASC
        ";
        $tugas_rows = fetch_all(query($sql_tugas));
    }
}
$flash = get_flash();
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Tugas Saya - Siswa</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .container{max-width:1100px;margin:10px auto;padding:10px}
    .card{background:#fff;border:1px solid #ddd;border-radius:8px;margin-bottom:12px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,.04)}
    .card-header{padding:12px 16px;background:#f5f5f5;font-weight:600;display:flex;justify-content:space-between;align-items:center}
    .card-body{padding:16px}
    .btn{display:inline-block;padding:10px 14px;border-radius:6px;border:1px solid #2c7be5;background:#2c7be5;color:#fff;text-decoration:none;cursor:pointer}
    .btn:hover{filter:brightness(0.95)}
    .table{width:100%;border-collapse:collapse}
    .table th,.table td{border:1px solid #e5e5e5;padding:10px;vertical-align:top}
    .table th{background:#fafafa;text-align:left}
    .badge{padding:4px 10px;border-radius:12px;font-size:12px}
    .b-warn{background:#fff7ed;color:#9a3412}
    .b-ok{background:#ecfdf5;color:#065f46}
    .b-red{background:#fee2e2;color:#991b1b}
    .btn-sm{padding:6px 10px;font-size:12px}
    .muted{color:#64748b;font-size:12px}
    .comment-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:10px;margin-top:8px}
    .comment-item{border-bottom:1px dashed #e2e8f0;padding:6px 0}
    .comment-item:last-child{border-bottom:0}
    .answer-input{display:grid;grid-template-columns:1fr;gap:8px}
    .answer-input label{font-weight:600}
    .answer-input textarea{width:100%;min-height:150px;padding:10px;border:1px solid #cbd5e1;border-radius:6px}
    .answer-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .answer-help{font-size:12px;color:#64748b}
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header">
        <div>Tugas Saya</div>
        <div class="muted">Kelas: <?php echo htmlspecialchars(($kelas_nama) ?? ''); ?></div>
        <div>
          <a href="dashboard.php" class="btn back-btn" onclick="var href=this.getAttribute('href'); document.body.style.transition='opacity .22s'; document.body.style.opacity=0; setTimeout(function(){ if (history.length>1) { history.back(); } else { window.location=href; } },220); return false;"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali</a>
        </div>
      </div>
      <div class="card-body">
        <?php if ($flash): ?>
          <div class="alert alert-<?php echo htmlspecialchars(($flash['type']) ?? ''); ?>" style="margin-bottom:10px"><?php echo htmlspecialchars(($flash['message']) ?? ''); ?></div>
        <?php endif; ?>
        <table class="table">
          <thead>
            <tr>
              <th>Mapel</th>
              <th>Judul</th>
              <th>Deadline</th>
              <th>Status</th>
              <th>Nilai</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($tugas_rows)): ?>
              <tr><td colspan="6">
                <div class="muted">Belum ada tugas untuk kelas Anda saat ini.</div>
                <div class="muted">Jika Anda yakin guru sudah memberikan tugas, pastikan profil kelas Anda benar. <a href="lihat_tugas.php?force_kelas=1" class="muted">Sinkronkan kelas</a></div>
              </td></tr>
            <?php else: foreach ($tugas_rows as $t): ?>
              <tr>
                <td><?php echo htmlspecialchars(($t['nama_mapel']) ?? ''); ?></td>
                <td>
                  <div><strong><?php echo htmlspecialchars(($t['judul_tugas']) ?? ''); ?></strong></div>
                  <?php if (!empty($t['deskripsi'])): ?>
                    <div style="color:#64748b; font-size:12px; margin-top:2px;">Deskripsi: <?php echo nl2br(htmlspecialchars(($t['deskripsi']) ?? '')); ?></div>
                  <?php endif; ?>
                  <?php if (!empty($t['isi_tugas'])): ?>
                    <div style="margin-top:6px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:8px;">
                      <div style="font-weight:600; margin-bottom:4px;">Isi Tugas:</div>
                      <div style="white-space:pre-wrap;"><?php echo nl2br(htmlspecialchars(($t['isi_tugas']) ?? '')); ?></div>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($t['lampiran_tugas'])): ?>
                    <div style="margin-top:6px;">
                      <strong>Lampiran:</strong> <a href="../assets/uploads/<?php echo htmlspecialchars(($t['lampiran_tugas']) ?? ''); ?>" target="_blank">Unduh</a>
                    </div>
                  <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars(($t['deadline']) ?? ''); ?></td>
                <td>
                  <?php if ($t['status_pengumpulan'] === 'submitted'): ?>
                    <span class="badge b-warn">Terkirim</span>
                  <?php elseif ($t['status_pengumpulan'] === 'needs_revision'): ?>
                    <span class="badge b-warn">Perlu Revisi</span>
                  <?php elseif ($t['status_pengumpulan'] === 'graded'): ?>
                    <span class="badge b-ok">Dinilai</span>
                  <?php elseif ($t['status_pengumpulan'] === 'late'): ?>
                    <span class="badge b-red">Terlambat</span>
                  <?php else: ?>
                    <span class="badge" style="background:#e2e8f0;color:#0f172a">Belum</span>
                  <?php endif; ?>
                </td>
                <td><?php echo isset($t['nilai']) ? htmlspecialchars(($t['nilai']) ?? '') : '-'; ?></td>
                <td>
                  <?php if (!$t['pengumpulan_id']): ?>
                    <form method="post" enctype="multipart/form-data" class="answer-input">
                      <input type="hidden" name="tugas_id" value="<?php echo (int)$t['id']; ?>" />
                      <div>
                        <label>Isi Jawaban Anda</label>
                        <textarea name="isi_jawaban" placeholder="Tulis jawaban Anda di sini. Anda boleh langsung mengumpulkan tanpa memilih file."></textarea>
                      </div>
                      <div class="answer-actions">
                        <input type="file" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip" />
                        <button type="submit" class="btn">Kumpulkan</button>
                      </div>
                      <div class="answer-help">File bersifat opsional. Anda dapat mengumpulkan hanya dengan teks jawaban.</div>
                    </form>
                  <?php else: ?>
                    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                      <span class="badge" style="background:#eff6ff;color:#1e40af">Sudah dikumpulkan</span>
                      <?php if ($t['status_pengumpulan'] !== 'graded'): ?>
                        <button class="btn btn-sm" onclick="toggleResubmitForm(<?php echo (int)$t['pengumpulan_id']; ?>)">Edit/Resubmit</button>
                      <?php endif; ?>
                    </div>
                    <div id="comments-<?php echo (int)$t['pengumpulan_id']; ?>" class="comment-box" data-auto="1"></div>
                    <div id="resubmit-<?php echo (int)$t['pengumpulan_id']; ?>" class="comment-box" style="display:none;">
                      <form method="post" enctype="multipart/form-data" class="answer-input">
                        <input type="hidden" name="resubmit" value="1" />
                        <input type="hidden" name="pengumpulan_id" value="<?php echo (int)$t['pengumpulan_id']; ?>" />
                        <div>
                          <label>Perbarui Jawaban (opsional)</label>
                          <textarea name="isi_jawaban" placeholder="Perbarui jawaban di sini..."><?php echo htmlspecialchars($t['isi_jawaban'] ?? ''); ?></textarea>
                        </div>
                        <div class="answer-actions">
                          <input type="file" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip" />
                          <button class="btn btn-sm" type="submit">Simpan Perubahan</button>
                        </div>
                        <div class="answer-help">Isi jawaban atau file salah satu cukup. File bersifat opsional.</div>
                      </form>
                    </div>
                                      <?php endif; ?>
                  </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<script>
    // Komentar - auto load
    async function loadCommentsBox(pengumpulanId){
      const box = document.getElementById('comments-'+pengumpulanId);
      if(!box) return;
      box.innerHTML = '<div class="muted">Memuat komentar...</div>';
      const res = await fetch('../includes/comments_api.php?action=list_comments&pengumpulan_id='+pengumpulanId);
      const data = await res.json();
      if(!data.success){ box.innerHTML = '<div class="muted">Gagal memuat komentar</div>'; return; }
      const items = data.data.comments || [];
      if(items.length === 0){ box.innerHTML = '<div class="muted">Belum ada komentar dari guru.</div>'; return; }
      box.innerHTML = items.map(c => `<div class="comment-item"><div><strong>${c.guru_nama||'Guru'}</strong> <span class="muted">${c.created_at}</span></div><div>${(c.comment||'').replaceAll('\n','<br>')}</div></div>`).join('');
    }

    // Resubmit form toggle
    function toggleResubmitForm(pengumpulanId){
      const el = document.getElementById('resubmit-'+pengumpulanId);
      if(!el) return; el.style.display = (el.style.display==='none'||el.style.display==='')? 'block':'none';
    }

    
    // Inisialisasi: auto-muat komentar untuk setiap pengumpulan yang ada
    window.addEventListener('DOMContentLoaded', ()=>{
      document.querySelectorAll('[id^="comments-"]').forEach(el=>{
        const id = parseInt(el.id.replace('comments-',''));
        if(id){ loadCommentsBox(id); }
      });
    });
  </script>
</body>
</html>
