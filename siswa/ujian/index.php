<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../includes/check_role.php';
check_role(['siswa']);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$siswa_id = (int)$_SESSION['user_id'];

function get_siswa_kelas_id($siswa_id){
    $res = query("SELECT kelas_id FROM users WHERE id=".(int)$siswa_id." LIMIT 1");
    $row = $res ? fetch_assoc($res) : null;
    return $row ? (int)$row['kelas_id'] : null;
}

$kelas_id = get_siswa_kelas_id($siswa_id);
if (!$kelas_id) {
    set_flash('error','Anda belum terdaftar pada kelas manapun.');
}

$now = date('Y-m-d H:i:s');
$ujian = [];
if ($kelas_id) {
    // Gunakan waktu database agar perbandingan konsisten
    $sql = "SELECT s.id, s.judul_ujian, s.deskripsi, s.waktu_mulai, s.waktu_selesai, s.durasi, s.jenis_ujian, ag.mapel_id, ag.tahun_ajaran_id, ta.semester AS semester_ta, mp.nama_mapel\n            FROM soal s\n            JOIN assignment_guru ag ON ag.id = s.assignment_id\n            JOIN tahun_ajaran ta ON ta.id = ag.tahun_ajaran_id\n            JOIN mata_pelajaran mp ON mp.id = ag.mapel_id\n            WHERE ag.kelas_id = {$kelas_id} AND s.waktu_selesai >= NOW()\n            ORDER BY s.waktu_mulai ASC";
    $ujian = fetch_all(query($sql));
}

// Ambil waktu DB untuk perhitungan status tombol Mulai
$rowNow = fetch_assoc(query("SELECT NOW() AS now_db"));
$now_db = $rowNow ? $rowNow['now_db'] : $now;

$flash = get_flash();

// Tabel attempts (satu kali kesempatan, remedial via allowed_attempts)
@query("CREATE TABLE IF NOT EXISTS exam_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  soal_id INT NOT NULL,
  siswa_id INT NOT NULL,
  allowed_attempts INT NOT NULL DEFAULT 1,
  used_attempts INT NOT NULL DEFAULT 0,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uniq (soal_id, siswa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ujian Saya</title>
  <link rel="stylesheet" href="../../assets/css/style.css">
  <style>
    .container{max-width:1100px;margin:16px auto;padding:12px}
    .card{background:#fff;border:1px solid #ddd;border-radius:10px;margin-bottom:16px;overflow:hidden}
    .card-header{padding:14px 18px;background:#f5f5f5;font-weight:600}
    .card-body{padding:18px}
    .table{width:100%;border-collapse:collapse}
    .table th,.table td{border:1px solid #e5e5e5;padding:12px}
    .table th{background:#fafafa;text-align:left}
    .badge{display:inline-block;padding:6px 10px;border-radius:14px;font-size:12px}
    .badge-info{background:#e7f1ff;color:#2c7be5}
    .badge-success{background:#e6f4ea;color:#1e7e34}
    .badge-warning{background:#fff4e5;color:#b36b00}
    .btn{display:inline-block;padding:8px 12px;border-radius:8px;border:1px solid #2c7be5;background:#2c7be5;color:#fff;cursor:pointer;text-decoration:none}
    .btn[disabled]{pointer-events:none}
    .btn:disabled{opacity:.6;cursor:not-allowed}
    .actions{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
    .icon-btn{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;border:1px solid #d0d7de;background:#fff;color:#2c7be5;text-decoration:none}
    .icon-btn:hover{background:#f3f6ff}
    .icon-btn[aria-disabled="true"]{opacity:.5;pointer-events:none}
    .alert{padding:12px;border-radius:8px;margin-bottom:12px}
    .alert-error{background:#fdecea;color:#b00020}
    .alert-success{background:#e6f4ea;color:#1e7e34}
    .scroll-x{overflow-x:auto}
    @media (max-width:576px){.table th,.table td{white-space:nowrap}.container{padding:10px}}
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header">Daftar Ujian</div>
      <div class="card-body">
        <div style="margin-bottom:8px">
          <a class="btn back-btn" href="../dashboard.php" onclick="var href=this.getAttribute('href'); document.body.style.transition='opacity .22s'; document.body.style.opacity=0; setTimeout(function(){ if (history.length>1) { history.back(); } else { window.location=href; } },220); return false;"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali</a>
        </div>
        <?php if ($flash): ?>
          <div class="alert alert-<?php echo htmlspecialchars(($flash['type']) ?? ''); ?>"><?php echo htmlspecialchars(($flash['message']) ?? ''); ?></div>
        <?php endif; ?>
        <?php if (!$kelas_id): ?>
          <p>Hubungi admin untuk mendaftarkan kelas Anda.</p>
        <?php else: ?>
        <div class="scroll-x">
          <table class="table">
            <thead>
              <tr>
                <th>Mapel</th>
                <th>Judul Ujian</th>
                <th>Mulai</th>
                <th>Selesai</th>
                <th>Durasi</th>
                <th>Status</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$ujian): ?>
                <tr><td colspan="7">Belum ada ujian untuk kelas Anda.</td></tr>
              <?php else: ?>
                <?php foreach ($ujian as $u): ?>
                  <?php
                    $nowT = (int)strtotime($now_db);
                    $mulai = (int)strtotime($u['waktu_mulai']);
                    $selesai = (int)strtotime($u['waktu_selesai']);
                    $status = '';$badge='badge-info';
                    $canStart = false; $blockedByWali = false; $blockedReason = '';
                    if ($nowT < $mulai) { $status = 'Belum mulai'; $badge='badge-warning'; }
                    elseif ($nowT >= $mulai && $nowT <= $selesai) { $status = 'Berlangsung'; $badge='badge-success'; $canStart = true; }
                    else { $status = 'Selesai'; $badge=''; }
                    // Cek attempt siswa
                    $att = query("SELECT allowed_attempts, used_attempts FROM exam_attempts WHERE soal_id=".(int)$u['id']." AND siswa_id={$siswa_id} LIMIT 1");
                    $attRow = $att ? fetch_assoc($att) : null;
                    $allowed = $attRow ? (int)$attRow['allowed_attempts'] : 1;
                    $used = $attRow ? (int)$attRow['used_attempts'] : 0;
                    $locked = ($used >= $allowed);
                    if ($locked) { $canStart = false; }

                    // Tambahan: deteksi blokir wali kelas untuk UTS/UAS
                    $jenis = isset($u['jenis_ujian']) ? (string)$u['jenis_ujian'] : '';
                    if (($jenis === 'UTS' || $jenis === 'UAS')) {
                        // 1) cek per-soal
                        $rsBlk = query("SELECT is_allowed, reason FROM exam_access_siswa WHERE soal_id=".(int)$u['id']." AND siswa_id={$siswa_id} LIMIT 1");
                        $rowBlk = $rsBlk ? fetch_assoc($rsBlk) : null;
                        if ($rowBlk && (int)$rowBlk['is_allowed'] === 0) { $blockedByWali = true; $blockedReason = (string)($rowBlk['reason'] ?? ''); }
                        // 2) jika tidak ada per-soal, cek default per TA+semester+jenis
                        if (!$blockedByWali) {
                          $ta_id = (int)$u['tahun_ajaran_id'];
                          $semester_str = ((string)$u['semester_ta'] === '2') ? 'Genap' : 'Ganjil';
                          $rsG = query("SELECT is_allowed, reason FROM exam_access_siswa_global_semester WHERE kelas_id={$kelas_id} AND siswa_id={$siswa_id} AND tahun_ajaran_id={$ta_id} AND semester='".escape_string($semester_str)."' AND jenis_ujian='".escape_string($jenis)."' LIMIT 1");
                          $rowG = $rsG ? fetch_assoc($rsG) : null;
                          if ($rowG && (int)$rowG['is_allowed'] === 0) { $blockedByWali = true; $blockedReason = (string)($rowG['reason'] ?? ''); }
                        }
                        if ($blockedByWali) { $canStart = false; if ($status==='Berlangsung') { $status = 'Diblokir Wali'; $badge=''; } }
                    }
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars(($u['nama_mapel']) ?? ''); ?></td>
                    <td><?php echo htmlspecialchars(($u['judul_ujian']) ?? ''); ?></td>
                    <td><?php echo htmlspecialchars(($u['waktu_mulai']) ?? ''); ?></td>
                    <td><?php echo htmlspecialchars(($u['waktu_selesai']) ?? ''); ?></td>
                    <td><?php echo (int)$u['durasi']; ?> menit</td>
                    <td>
                      <span class="badge <?php echo $badge; ?>"><?php echo $status; ?></span>
                      <?php if ($blockedByWali): ?>
                        <div style="color:#b00020;font-size:12px;margin-top:4px">Diblokir oleh wali kelas<?php echo ($blockedReason? ': '.htmlspecialchars(($blockedReason) ?? ''):''); ?></div>
                      <?php endif; ?>
                      <?php if ($locked): ?>
                        <div style="color:#6c757d;font-size:12px;margin-top:4px">Anda sudah submit!</div>
                      <?php endif; ?>
                    </td>
                    <td class="actions">
                      <?php if ($canStart && !$blockedByWali): ?>
                        <a class="icon-btn" href="take.php?id=<?php echo (int)$u['id']; ?>" title="Mulai" aria-label="Mulai">
                          <span aria-hidden="true">▶</span>
                        </a>
                      <?php elseif ($blockedByWali): ?>
                        <span class="icon-btn" aria-disabled="true" title="Diblokir oleh wali kelas">
                          <span aria-hidden="true">▶</span>
                        </span>
                      <?php elseif ($locked): ?>
                        <span class="icon-btn" aria-disabled="true" title="Sudah menyelesaikan (minta remedial ke guru)">
                          <span aria-hidden="true">▶</span>
                        </span>
                      <?php elseif ($nowT < $mulai): ?>
                        <span class="icon-btn" aria-disabled="true" title="Belum waktu mulai">
                          <span aria-hidden="true">▶</span>
                        </span>
                      <?php else: ?>
                        <span class="icon-btn" aria-disabled="true" title="Ujian sudah berakhir">
                          <span aria-hidden="true">▶</span>
                        </span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>

