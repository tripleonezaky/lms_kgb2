<?php
session_start();
require_once '../includes/check_session.php';
require_once '../includes/check_role.php';
check_role(['siswa']);
require_once '../config/database.php';
require_once '../includes/functions.php';

$siswa_id = (int)$_SESSION['user_id'];
$kelas_id = isset($_SESSION['kelas_id']) ? (int)$_SESSION['kelas_id'] : 0;

// Filter: Tahun Ajaran (default aktif), Semester (wajib), Mapel (opsional)
$ta_aktif = get_tahun_ajaran_aktif();
$tahun_ajaran_id = isset($_GET['tahun_ajaran_id']) && $_GET['tahun_ajaran_id']!=='' ? (int)$_GET['tahun_ajaran_id'] : ($ta_aktif ? (int)$ta_aktif['id'] : 0);
$semester = isset($_GET['semester']) ? trim($_GET['semester']) : '';
$mapel_id = isset($_GET['mapel_id']) ? (int)$_GET['mapel_id'] : 0;

$tahun_ajaran_opts = fetch_all(query("SELECT id, nama_tahun_ajaran, is_active FROM tahun_ajaran ORDER BY is_active DESC, id DESC"));
$mapel_opts = fetch_all(query("SELECT DISTINCT mp.id, mp.nama_mapel FROM assignment_guru ag JOIN mata_pelajaran mp ON ag.mapel_id = mp.id WHERE ag.kelas_id={$kelas_id} ORDER BY mp.nama_mapel"));

$has_filter = $tahun_ajaran_id>0 && ($semester==='Ganjil' || $semester==='Genap');
$rows = [];
$error = '';

if ($has_filter) {
    $sem = escape_string($semester);
    $sql = "
      SELECT mp.id AS mapel_id, mp.nama_mapel,
             ROUND(AVG(CASE WHEN n.jenis_penilaian IN ('Tugas','Quiz','Harian') THEN n.nilai END),2) AS nilai_harian,
             ROUND(AVG(CASE WHEN n.jenis_penilaian='UTS' THEN n.nilai END),2) AS uts,
             ROUND(AVG(CASE WHEN n.jenis_penilaian='UAS' THEN n.nilai END),2) AS uas,
             -- Rata-rata keseluruhan dari komponen yang ada: Nilai Harian, UTS, UAS
             ROUND((
                COALESCE(AVG(CASE WHEN n.jenis_penilaian IN ('Tugas','Quiz','Harian') THEN n.nilai END),0) +
                COALESCE(AVG(CASE WHEN n.jenis_penilaian='UTS' THEN n.nilai END),0) +
                COALESCE(AVG(CASE WHEN n.jenis_penilaian='UAS' THEN n.nilai END),0)
             ) / NULLIF(
                (CASE WHEN COUNT(CASE WHEN n.jenis_penilaian IN ('Tugas','Quiz','Harian') THEN 1 END)>0 THEN 1 ELSE 0 END) +
                (CASE WHEN COUNT(CASE WHEN n.jenis_penilaian='UTS' THEN 1 END)>0 THEN 1 ELSE 0 END) +
                (CASE WHEN COUNT(CASE WHEN n.jenis_penilaian='UAS' THEN 1 END)>0 THEN 1 ELSE 0 END)
             ,0),2) AS rata_rata
      FROM mata_pelajaran mp
      LEFT JOIN nilai n ON n.mapel_id = mp.id AND n.siswa_id = {$siswa_id} AND n.kelas_id = {$kelas_id}
           AND n.tahun_ajaran_id = {$tahun_ajaran_id} AND n.semester = '{$sem}'
      JOIN assignment_guru ag ON ag.mapel_id = mp.id AND ag.kelas_id = {$kelas_id}
      WHERE 1=1";
    if ($mapel_id>0) {
        $sql .= " AND mp.id = {$mapel_id}";
    }
    $sql .= " GROUP BY mp.id, mp.nama_mapel ORDER BY mp.nama_mapel";
    $res = query($sql);
    if ($res) {
        $rows = fetch_all($res);
    } else {
        $error = 'Gagal mengambil data nilai.';
    }
}

?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Nilai Saya - Siswa</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .container{max-width:1100px;margin:10px auto;padding:10px}
    .card{background:#fff;border:1px solid #ddd;border-radius:8px;margin-bottom:12px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,.04)}
    .card-header{padding:12px 16px;background:#f5f5f5;font-weight:600;display:flex;justify-content:space-between;align-items:center}
    .card-body{padding:16px}
    .filters{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
    label{font-weight:600}
    select{padding:10px;border:1px solid #ced4da;border-radius:6px}
    .btn{display:inline-block;padding:10px 14px;border-radius:6px;border:1px solid #2c7be5;background:#2c7be5;color:#fff;text-decoration:none;cursor:pointer}
    .btn:hover{filter:brightness(0.95)}
    .table{width:100%;border-collapse:collapse}
    .table th,.table td{border:1px solid #e5e5e5;padding:10px}
    .table th{background:#fafafa;text-align:left}
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header">
        <div>Nilai Saya</div>
        <div>
          <a href="dashboard.php" class="btn back-btn" title="Kembali" onclick="var href=this.getAttribute('href'); document.body.style.transition='opacity .22s'; document.body.style.opacity=0; setTimeout(function(){ if (history.length>1) { history.back(); } else { window.location=href; } },220); return false;"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali</a>
        </div>
      </div>
      <div class="card-body">
        <form method="get" class="filters">
          <div>
            <label>Tahun Ajaran</label>
            <select name="tahun_ajaran_id" onchange="this.form.submit()">
              <option value="">Pilih Tahun Ajaran</option>
              <?php foreach($tahun_ajaran_opts as $t): ?>
                <option value="<?php echo (int)$t['id']; ?>" <?php echo $tahun_ajaran_id==(int)$t['id']?'selected':''; ?>>
                  <?php echo htmlspecialchars(($t['nama_tahun_ajaran']) ?? ''); ?><?php echo ((int)($t['is_active']??0)===1?' (Aktif)':''); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Semester</label>
            <select name="semester" onchange="this.form.submit()">
              <option value="">Pilih Semester</option>
              <option value="Ganjil" <?php echo $semester==='Ganjil'?'selected':''; ?>>Ganjil</option>
              <option value="Genap" <?php echo $semester==='Genap'?'selected':''; ?>>Genap</option>
            </select>
          </div>
          <div>
            <label>Mapel</label>
            <select name="mapel_id" onchange="this.form.submit()">
              <option value="">Semua Mapel</option>
              <?php foreach($mapel_opts as $m): ?>
                <option value="<?php echo (int)$m['id']; ?>" <?php echo $mapel_id==(int)$m['id']?'selected':''; ?>><?php echo htmlspecialchars(($m['nama_mapel']) ?? ''); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>

        <?php if ($error): ?>
          <div class="alert alert-error" style="padding:10px;border-radius:6px;margin-top:10px;background:#fdecea;color:#b00020;"><?php echo htmlspecialchars(($error) ?? ''); ?></div>
        <?php endif; ?>

        <?php if ($has_filter && !$error): ?>
          <div style="margin-top:12px">
            <table class="table">
              <thead>
                <tr>
                  <th>Mapel</th>
                  <th>Nilai Harian</th>
                  <th>UTS</th>
                  <th>UAS</th>
                  <th>Rata-rata</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($rows)): ?>
                  <tr><td colspan="5">Belum ada nilai untuk filter ini.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                  <tr>
                    <td><?php echo htmlspecialchars(($r['nama_mapel']) ?? ''); ?></td>
                    <td><?php echo $r['nilai_harian']!==null?htmlspecialchars(($r['nilai_harian']) ?? ''):'-'; ?></td>
                    <td><?php echo $r['uts']!==null?htmlspecialchars(($r['uts']) ?? ''):'-'; ?></td>
                    <td><?php echo $r['uas']!==null?htmlspecialchars(($r['uas']) ?? ''):'-'; ?></td>
                    <td><strong><?php echo $r['rata_rata']!==null?htmlspecialchars(($r['rata_rata']) ?? ''):'-'; ?></strong></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</body>
</html>

