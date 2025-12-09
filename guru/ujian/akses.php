<?php
// guru/ujian/akses.php
// Halaman kontrol akses ujian per-siswa untuk Wali Kelas (khusus UTS/UAS)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../includes/check_role.php';
check_role(['guru']);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$guru_id = (int)$_SESSION['user_id'];

// Tabel akses per-siswa (idempotent, aman untuk semua environment)
@query("CREATE TABLE IF NOT EXISTS exam_access_siswa (
  id INT AUTO_INCREMENT PRIMARY KEY,
  soal_id INT NOT NULL,
  siswa_id INT NOT NULL,
  is_allowed TINYINT(1) NOT NULL DEFAULT 1,
  reason TEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uniq (soal_id, siswa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Tabel default akses UTS/UAS per-siswa per-kelas (global)
@query("CREATE TABLE IF NOT EXISTS exam_access_siswa_global (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kelas_id INT NOT NULL,
  siswa_id INT NOT NULL,
  is_allowed TINYINT(1) NOT NULL DEFAULT 1,
  reason TEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uniq (kelas_id, siswa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Konfigurasi default per TA + semester + jenis ujian (UTS/UAS)
@query("CREATE TABLE IF NOT EXISTS exam_access_siswa_global_semester (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kelas_id INT NOT NULL,
  siswa_id INT NOT NULL,
  tahun_ajaran_id INT NOT NULL,
  semester ENUM('Ganjil','Genap') NOT NULL,
  jenis_ujian ENUM('UTS','UAS') NOT NULL,
  is_allowed TINYINT(1) NOT NULL DEFAULT 1,
  reason TEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uniq (kelas_id, siswa_id, tahun_ajaran_id, semester, jenis_ujian)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ambil daftar kelas yang diwalikan oleh guru ini
$kelas_rs = query("SELECT id, nama_kelas FROM kelas WHERE wali_kelas_id=".$guru_id." ORDER BY nama_kelas");
$kelas_list = fetch_all($kelas_rs);

// Helper: ambil daftar ujian UTS/UAS untuk kelas tertentu
function get_soal_uts_uas_by_kelas($kelas_id) {
    $kelas_id = (int)$kelas_id;
    $sql = "SELECT s.id, s.judul_ujian, s.jenis_ujian, s.waktu_mulai, s.waktu_selesai\n            FROM soal s\n            JOIN assignment_guru ag ON ag.id = s.assignment_id\n            WHERE ag.kelas_id={$kelas_id} AND s.jenis_ujian IN ('UTS','UAS')\n            ORDER BY s.waktu_mulai DESC, s.id DESC";
    return fetch_all(query($sql));
}

// Helper: ambil siswa pada kelas
function get_siswa_by_kelas($kelas_id) {
    $kelas_id = (int)$kelas_id;
    // Hanya ambil `nisn` — tampilan akan menampilkan NISN atau '-' jika kosong
    $sql = "SELECT id, nama_lengkap, nisn FROM users WHERE role='siswa' AND kelas_id={$kelas_id} ORDER BY nama_lengkap ASC";
    return fetch_all(query($sql));
}

// Helper: ambil map akses per-siswa untuk suatu soal
function get_access_map($soal_id) {
    $soal_id = (int)$soal_id;
    $rs = query("SELECT siswa_id, is_allowed, reason FROM exam_access_siswa WHERE soal_id={$soal_id}");
    $map = [];
    while ($row = $rs ? fetch_assoc($rs) : null) {
        $map[(int)$row['siswa_id']] = [
            'is_allowed' => (int)$row['is_allowed'],
            'reason' => (string)$row['reason']
        ];
    }
    return $map;
}

$kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
$soal_id = isset($_GET['soal_id']) ? (int)$_GET['soal_id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$semester_opt = isset($_GET['semester']) && in_array($_GET['semester'], ['Ganjil','Genap']) ? $_GET['semester'] : null;
$jenis_opt = isset($_GET['jenis']) && in_array($_GET['jenis'], ['UTS','UAS']) ? $_GET['jenis'] : null;

// Auto pilih kelas jika wali hanya memiliki satu kelas
if ($kelas_id === 0 && is_array($kelas_list) && count($kelas_list) === 1) {
    $kelas_id = (int)$kelas_list[0]['id'];
}

// Dapatkan tahun ajaran aktif
$tahunAktif = get_tahun_ajaran_aktif();
$tahun_ajaran_id = $tahunAktif ? (int)$tahunAktif['id'] : 0;
if (!$semester_opt) { $semester_opt = ($tahunAktif ? ($tahunAktif['semester']==='1'?'Ganjil':'Genap') : 'Ganjil'); }
if (!$jenis_opt) { $jenis_opt = 'UTS'; }

// Validasi kelas milik wali
function wali_memiliki_kelas($guru_id, $kelas_id) {
    $guru_id = (int)$guru_id; $kelas_id = (int)$kelas_id;
    $rs = query("SELECT 1 FROM kelas WHERE id={$kelas_id} AND wali_kelas_id={$guru_id} LIMIT 1");
    return ($rs && fetch_assoc($rs)) ? true : false;
}

// Simpan pengaturan GLOBAL default UTS/UAS per-siswa per-kelas
if ($action === 'save_global' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $kelas_id = (int)($_POST['kelas_id'] ?? 0);
    $tahun_ajaran_id = (int)($_POST['tahun_ajaran_id'] ?? 0);
    $semester_opt = ($_POST['semester'] ?? 'Ganjil') === 'Genap' ? 'Genap' : 'Ganjil';
    $jenis_opt = ($_POST['jenis'] ?? 'UTS') === 'UAS' ? 'UAS' : 'UTS';
    if ($kelas_id <= 0 || $tahun_ajaran_id <= 0) {
        set_flash('error', 'Parameter tidak lengkap.');
        redirect('akses.php');
    }
    if (!wali_memiliki_kelas($guru_id, $kelas_id)) {
        set_flash('error', 'Anda bukan wali kelas untuk kelas ini.');
        redirect('akses.php');
    }

    $allowed = isset($_POST['allowed']) && is_array($_POST['allowed']) ? $_POST['allowed'] : [];
    $reason  = isset($_POST['reason']) && is_array($_POST['reason']) ? $_POST['reason'] : [];

    $siswa_list = get_siswa_by_kelas($kelas_id);
    $now = date('Y-m-d H:i:s');

    foreach ($siswa_list as $s) {
        $sid = (int)$s['id'];
        $is_allowed = isset($allowed[$sid]) ? (int)$allowed[$sid] : 0;
        $alasan = isset($reason[$sid]) ? trim((string)$reason[$sid]) : '';
        $alasan_db = escape_string($alasan);
        $sql = "INSERT INTO exam_access_siswa_global_semester (kelas_id, siswa_id, tahun_ajaran_id, semester, jenis_ujian, is_allowed, reason, created_at, updated_at) VALUES (".
               "{$kelas_id},{$sid},{$tahun_ajaran_id},'{$semester_opt}','{$jenis_opt}',{$is_allowed},".($alasan_db!==''?"'{$alasan_db}'":"NULL").",'{$now}','{$now}') ".
               "ON DUPLICATE KEY UPDATE is_allowed=VALUES(is_allowed), reason=VALUES(reason), updated_at=VALUES(updated_at)";
        query($sql);
    }

    set_flash('success','Pengaturan default akses disimpan untuk TA/semester/jenis tersebut.');
    redirect('akses.php?kelas_id='.$kelas_id.'&semester='.$semester_opt.'&jenis='.$jenis_opt);
}

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $kelas_id = (int)($_POST['kelas_id'] ?? 0);
    $soal_id = (int)($_POST['soal_id'] ?? 0);
    if ($kelas_id <= 0 || $soal_id <= 0) {
        set_flash('error', 'Parameter tidak lengkap.');
        redirect('akses.php');
    }
    if (!wali_memiliki_kelas($guru_id, $kelas_id)) {
        set_flash('error', 'Anda bukan wali kelas untuk kelas ini.');
        redirect('akses.php');
    }

    // pastikan soal memang milik kelas ini dan UTS/UAS
    $cek = query("SELECT 1\n                 FROM soal s\n                 JOIN assignment_guru ag ON ag.id=s.assignment_id\n                 WHERE s.id={$soal_id} AND ag.kelas_id={$kelas_id} AND s.jenis_ujian IN ('UTS','UAS') LIMIT 1");
    if (!$cek || !fetch_assoc($cek)) {
        set_flash('error','Soal tidak valid untuk kelas ini atau bukan UTS/UAS');
        redirect('akses.php?kelas_id='.$kelas_id);
    }

    // Input arrays
    $allowed = isset($_POST['allowed']) && is_array($_POST['allowed']) ? $_POST['allowed'] : [];
    $reason  = isset($_POST['reason']) && is_array($_POST['reason']) ? $_POST['reason'] : [];

    $siswa_list = get_siswa_by_kelas($kelas_id);
    $now = date('Y-m-d H:i:s');

    // Simpan per-siswa (upsert)
    foreach ($siswa_list as $s) {
        $sid = (int)$s['id'];
        $is_allowed = isset($allowed[$sid]) ? (int)$allowed[$sid] : 0; // hidden default 0 lalu checkbox=1
        $alasan = isset($reason[$sid]) ? trim((string)$reason[$sid]) : '';
        $alasan_db = escape_string($alasan);
        $ins = "INSERT INTO exam_access_siswa (soal_id, siswa_id, is_allowed, reason, created_at, updated_at) VALUES (".
               "{$soal_id},{$sid},{$is_allowed},".($alasan_db!==''?"'{$alasan_db}'":"NULL").",'{$now}','{$now}') ".
               "ON DUPLICATE KEY UPDATE is_allowed=VALUES(is_allowed), reason=VALUES(reason), updated_at=VALUES(updated_at)";
        query($ins);
    }

    set_flash('success','Pengaturan akses ujian berhasil disimpan.');
    redirect('akses.php?kelas_id='.$kelas_id.'&soal_id='.$soal_id);
}

// Ambil data untuk render
$soal_list = [];
$siswa_list = [];
$access_map = [];
$global_map = [];
if ($kelas_id > 0 && wali_memiliki_kelas($guru_id, $kelas_id)) {
    // Isi daftar siswa untuk kelas yang dipilih (selalu tampil meski tanpa memilih soal)
    $siswa_list = get_siswa_by_kelas($kelas_id);

    // Siapkan daftar soal (opsional, jika nanti ingin override per-soal)
    $soal_list = get_soal_uts_uas_by_kelas($kelas_id);

    // Muat pengaturan default global per TA+semester+jenis ujian untuk kelas ini
    if ($tahun_ajaran_id > 0) {
        $rs_g = query("SELECT siswa_id, is_allowed, reason FROM exam_access_siswa_global_semester WHERE kelas_id=".$kelas_id." AND tahun_ajaran_id=".$tahun_ajaran_id." AND semester='".$semester_opt."' AND jenis_ujian='".$jenis_opt."'");
        if ($rs_g) {
            while ($row = fetch_assoc($rs_g)) {
                $sid = (int)$row['siswa_id'];
                $global_map[$sid] = [
                    'is_allowed' => (int)$row['is_allowed'],
                    'reason' => (string)($row['reason'] ?? '')
                ];
            }
        }
    }

    // Jika ada soal dipilih, muat access_map per-soal (opsional)
    if ($soal_id > 0) {
        $valid = false; foreach ($soal_list as $sl){ if ((int)$sl['id'] === $soal_id) { $valid = true; break; } }
        if ($valid) {
            $access_map = get_access_map($soal_id);
        } else {
            $soal_id = 0;
        }
    }
}
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kontrol Akses Ujian (Wali Kelas)</title>
  <link rel="stylesheet" href="../../assets/css/style.css">
  <style>
    .container{max-width:1100px;margin:16px auto;padding:12px}
    .card{background:#fff;border:1px solid #ddd;border-radius:10px;margin-bottom:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05)}
    .card-header{padding:14px 18px;background:#f5f7fb;font-weight:600}
    .card-body{padding:18px}
    .row{display:flex;gap:14px;flex-wrap:wrap}
    .row .col{flex:1 1 280px}
    label{display:block;margin-bottom:6px;font-weight:600}
    select,input[type=text],textarea{width:100%;padding:10px;border:1px solid #ced4da;border-radius:8px}
    table{width:100%;border-collapse:collapse}
    th,td{border:1px solid #e5e5e5;padding:10px;text-align:left}
    th{background:#fafafa}
    .btn{display:inline-block;padding:10px 14px;border-radius:8px;border:1px solid #2c7be5;background:#2c7be5;color:#fff;text-decoration:none;cursor:pointer}
    .btn-secondary{background:#6c757d;border-color:#6c757d}
    .alert{padding:12px;border-radius:8px;margin:10px 0}
    .alert-error{background:#fdecea;color:#b00020}
    .alert-success{background:#e6f4ea;color:#1e7e34}
    .muted{color:#666;font-size:12px}
    /* Keep first column (NISN) visible on horizontal scroll */
    .table-container table th:first-child,
    .table-container table td:first-child {
      position: -webkit-sticky; /* Safari */
      position: sticky;
      left: 0;
      background: #fff;
      z-index: 3;
      box-shadow: 2px 0 4px rgba(0,0,0,0.04);
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header">Kontrol Akses Ujian (Per Siswa) - Wali Kelas</div>
      <div class="card-body">
        <div style="margin-bottom:10px"><a href="../dashboard.php" class="btn btn-secondary back-btn" onclick="var href=this.getAttribute('href'); document.body.style.transition='opacity .22s'; document.body.style.opacity=0; setTimeout(function(){ if (history.length>1) { history.back(); } else { window.location=href; } },220); return false;"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali</a></div>
        <?php if ($flash): ?>
          <div class="alert alert-<?php echo htmlspecialchars(($flash['type']) ?? ''); ?>"><?php echo htmlspecialchars(($flash['message']) ?? ''); ?></div>
        <?php endif; ?>

        <?php if (!$kelas_list): ?>
          <div class="alert alert-error">Anda tidak terdaftar sebagai wali kelas pada kelas manapun.</div>
        <?php else: ?>
          <form method="get" action="akses.php" class="row" style="align-items:flex-end">
            <div class="col">
              <label>Kelas yang Anda walikan</label>
              <select name="kelas_id" required onchange="this.form.submit()">
                <option value="">- pilih -</option>
                <?php foreach ($kelas_list as $k): ?>
                  <option value="<?php echo (int)$k['id']; ?>" <?php echo ((int)$k['id']===$kelas_id?'selected':''); ?>><?php echo htmlspecialchars(($k['nama_kelas']) ?? ''); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col">
              <label>Semester</label>
              <select name="semester" onchange="this.form.submit()" <?php echo ($kelas_id>0?'':'disabled'); ?>>
                <option value="Ganjil" <?php echo ($semester_opt==='Ganjil'?'selected':''); ?>>Ganjil</option>
                <option value="Genap" <?php echo ($semester_opt==='Genap'?'selected':''); ?>>Genap</option>
              </select>
            </div>
            <div class="col">
              <label>Jenis Ujian</label>
              <select name="jenis" onchange="this.form.submit()" <?php echo ($kelas_id>0?'':'disabled'); ?>>
                <option value="UTS" <?php echo ($jenis_opt==='UTS'?'selected':''); ?>>UTS</option>
                <option value="UAS" <?php echo ($jenis_opt==='UAS'?'selected':''); ?>>UAS</option>
              </select>
            </div>
          </form>

          <?php if ($kelas_id>0 && $siswa_list): ?>
            <form method="post" action="akses.php?action=save_global" onsubmit="return confirm('Simpan pengaturan untuk TA/semester/jenis ini?');">
              <input type="hidden" name="kelas_id" value="<?php echo (int)$kelas_id; ?>" />
              <input type="hidden" name="tahun_ajaran_id" value="<?php echo (int)$tahun_ajaran_id; ?>" />
              <input type="hidden" name="semester" value="<?php echo htmlspecialchars(($semester_opt) ?? ''); ?>" />
              <input type="hidden" name="jenis" value="<?php echo htmlspecialchars(($jenis_opt) ?? ''); ?>" />
              <div class="muted" style="margin:8px 0">Pengaturan default untuk TA aktif, semester <strong><?php echo htmlspecialchars(($semester_opt) ?? ''); ?></strong>, jenis <strong><?php echo htmlspecialchars(($jenis_opt) ?? ''); ?></strong>. Checklist "Diizinkan" untuk siswa yang boleh mengikuti.</div>
              <div class="table-container">
              <table>
                <thead><tr>
                  <th style="width:60px">No</th>
                  <th style="width:130px">NISN</th>
                  <th>Nama Siswa</th>
                  <th style="width:120px">Diizinkan?</th>
                  <th>Alasan (jika tidak diizinkan)</th>
                </tr></thead>
                <tbody>
                  <?php foreach ($siswa_list as $i => $s): $sid=(int)$s['id']; $acc = $global_map[$sid] ?? ['is_allowed'=>1,'reason'=>'']; ?>
                    <tr>
                      <td data-label="No"><?php echo $i+1; ?></td>
                      <td data-label="NISN"><?php $nisn = trim((string)($s['nisn'] ?? '')); echo htmlspecialchars($nisn !== '' ? $nisn : '-'); ?></td>
                      <td data-label="Nama Siswa"><?php echo htmlspecialchars(($s['nama_lengkap']) ?? ''); ?></td>
                      <td data-label="Diizinkan?">
                        <input type="hidden" name="allowed[<?php echo $sid; ?>]" value="0" />
                        <label><input type="checkbox" name="allowed[<?php echo $sid; ?>]" value="1" <?php echo ((int)$acc['is_allowed']===1?'checked':''); ?>> Ya</label>
                      </td>
                      <td data-label="Alasan">
                        <input type="text" name="reason[<?php echo $sid; ?>]" value="<?php echo htmlspecialchars((string)($acc['reason'] ?? '')); ?>" placeholder="Contoh: Belum melunasi administrasi / pelanggaran kedisiplinan ..." />
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              </div>
              <div style="margin-top:12px">
                <button class="btn" type="submit" title="Simpan Pengaturan Default"><i class="fas fa-pen" aria-hidden="true"></i></button>
              </div>
            </form>
          <?php elseif ($kelas_id>0 && !$siswa_list): ?>
            <div class="alert alert-error">Tidak ada siswa pada kelas ini.</div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <script>
    // Ensure table is scrolled to left (so NISN column is visible) and enable touch scroll
    document.addEventListener('DOMContentLoaded', function(){
      try {
        var tc = document.querySelector('.table-container');
        if (!tc) return;
        // scroll to leftmost
        tc.scrollLeft = 0;
        // On touch devices, ensure horizontal scroll by enabling overflow
        tc.style.overflowX = 'auto';
        tc.style['-webkit-overflow-scrolling'] = 'touch';
      } catch(e){}
    });
  </script>
</body>
</html>


