<?php
/**
 * File: admin/rekap_nilai.php (Leger/Rekap Nilai UAS)
 * Tujuan: Menampilkan Leger UAS sesuai format dokumen revisi (poin f)
 * - Kolom identitas: NISN, NAMA
 * - Kolom dinamis per mapel: (Nilai Akhir, Capaian Kompetensi)
 * - Sumber Nilai Akhir: tabel nilai (jenis_penilaian='UAS')
 * - Sumber Capaian Kompetensi: tabel nilai_deskripsi (baru)
 * - Filter: Tahun Ajaran, Semester (Ganjil/Genap), Kelas
 * - Fitur: Cetak, Export CSV
 */
session_start();
require_once '../includes/check_session.php';
require_once '../includes/check_role.php';
check_role(['admin']);
require_once '../config/database.php';

// ---------------------------------------------
// Bootstrap: pastikan tabel deskripsi tersedia
// ---------------------------------------------
$createDeskripsiSql = "
CREATE TABLE IF NOT EXISTS `nilai_deskripsi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `siswa_id` int(11) NOT NULL,
  `mapel_id` int(11) NOT NULL,
  `kelas_id` int(11) NOT NULL,
  `tahun_ajaran_id` int(11) NOT NULL,
  `semester` enum('Ganjil','Genap') NOT NULL,
  `jenis_penilaian` enum('UTS','UAS','Tugas','Quiz','Harian') NOT NULL DEFAULT 'UAS',
  `deskripsi` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_nd` (`siswa_id`,`mapel_id`,`kelas_id`,`tahun_ajaran_id`,`semester`,`jenis_penilaian`),
  KEY `nd_siswa` (`siswa_id`),
  KEY `nd_mapel` (`mapel_id`),
  KEY `nd_kelas` (`kelas_id`),
  KEY `nd_tahun` (`tahun_ajaran_id`),
  CONSTRAINT `nd_fk_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `nd_fk_mapel` FOREIGN KEY (`mapel_id`) REFERENCES `mata_pelajaran`(`id`) ON DELETE CASCADE,
  CONSTRAINT `nd_fk_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `kelas`(`id`) ON DELETE CASCADE,
  CONSTRAINT `nd_fk_tahun` FOREIGN KEY (`tahun_ajaran_id`) REFERENCES `tahun_ajaran`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
query($createDeskripsiSql);

// ---------------------------------------------
// Ambil filter
// ---------------------------------------------
$tahun_ajaran_id = isset($_GET['tahun_ajaran_id']) ? (int)$_GET['tahun_ajaran_id'] : 0;
$semester        = isset($_GET['semester']) ? trim($_GET['semester']) : '';
$kelas_id        = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;

// Opsi dropdown
$tahun_ajaran_opts = fetch_all(query("SELECT id, nama_tahun_ajaran, semester AS smt, is_active FROM tahun_ajaran ORDER BY is_active DESC, id DESC"));
$kelas_opts        = fetch_all(query("SELECT k.id, k.nama_kelas FROM kelas k ORDER BY k.nama_kelas"));

$has_filter = $tahun_ajaran_id > 0 && ($semester === 'Ganjil' || $semester === 'Genap') && $kelas_id > 0;

$mapel_list = [];
$siswa_list = [];
$nilai_by   = [];
$desc_by    = [];
$error      = '';

if ($has_filter) {
    // Ambil daftar mapel relevan berdasarkan assignment_guru
    $sqlMapel = "
        SELECT DISTINCT mp.id, mp.nama_mapel
        FROM mata_pelajaran mp
        INNER JOIN assignment_guru ag ON ag.mapel_id = mp.id
        WHERE ag.kelas_id = {$kelas_id}
          AND ag.tahun_ajaran_id = {$tahun_ajaran_id}
        ORDER BY mp.nama_mapel
    ";
    $mapel_list = fetch_all(query($sqlMapel));

    // Jika belum ada assignment, fallback ke mapel yang punya nilai UAS di kombinasi ini
    if (count($mapel_list) === 0) {
        $sqlMapel2 = "
            SELECT DISTINCT mp.id, mp.nama_mapel
            FROM mata_pelajaran mp
            INNER JOIN nilai n ON n.mapel_id = mp.id
            WHERE n.kelas_id = {$kelas_id}
              AND n.tahun_ajaran_id = {$tahun_ajaran_id}
              AND n.semester = '" . escape_string($semester) . "'
              AND n.jenis_penilaian = 'UAS'
            ORDER BY mp.nama_mapel
        ";
        $mapel_list = fetch_all(query($sqlMapel2));
    }

    // Jika tetap kosong, fallback ke semua mapel (supaya header tidak kosong)
    if (count($mapel_list) === 0) {
        $mapel_list = fetch_all(query("SELECT id, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel"));
    }

    // Ambil daftar siswa di kelas
    $siswa_list = fetch_all(query("SELECT id, username, nisn, nama_lengkap FROM users WHERE role='siswa' AND kelas_id = {$kelas_id} ORDER BY nama_lengkap"));

    // Nilai UAS per siswa x mapel
    $nilai_rows = fetch_all(query("\n        SELECT siswa_id, mapel_id, ROUND(AVG(nilai), 2) AS nilai_akhir\n        FROM nilai\n        WHERE kelas_id = {$kelas_id}\n          AND tahun_ajaran_id = {$tahun_ajaran_id}\n          AND semester = '" . escape_string($semester) . "'\n          AND jenis_penilaian = 'UAS'\n        GROUP BY siswa_id, mapel_id\n    "));
    foreach ($nilai_rows as $nr) {
        $sid = (int)$nr['siswa_id'];
        $mid = (int)$nr['mapel_id'];
        $nilai_by[$sid][$mid] = $nr['nilai_akhir'];
    }

    // Deskripsi capaian per siswa x mapel
    $desc_rows = fetch_all(query("\n        SELECT siswa_id, mapel_id, deskripsi\n        FROM nilai_deskripsi\n        WHERE kelas_id = {$kelas_id}\n          AND tahun_ajaran_id = {$tahun_ajaran_id}\n          AND semester = '" . escape_string($semester) . "'\n          AND jenis_penilaian = 'UAS'\n    "));
    foreach ($desc_rows as $dr) {
        $sid = (int)$dr['siswa_id'];
        $mid = (int)$dr['mapel_id'];
        $desc_by[$sid][$mid] = $dr['deskripsi'];
    }
}

// ---------------------------------------------
// Ekspor CSV (dinamis per mapel)
// ---------------------------------------------
if ($has_filter && isset($_GET['export']) && (int)$_GET['export'] === 1) {
    header('Content-Type: text/csv; charset=utf-8');
    $fname = 'leger_uas_' . $tahun_ajaran_id . '_' . $semester . '_kelas' . $kelas_id . '.csv';
    header('Content-Disposition: attachment; filename=' . $fname);
    $output = fopen('php://output', 'w');

    $head = ['No', 'NISN/Username', 'Nama Siswa'];
    foreach ($mapel_list as $mp) {
        $head[] = $mp['nama_mapel'] . ' - Nilai Akhir';
        $head[] = $mp['nama_mapel'] . ' - Capaian Kompetensi';
    }
    fputcsv($output, $head);

    $no = 1;
    foreach ($siswa_list as $s) {
        $row = [];
        $row[] = $no++;
        $row[] = ($s['nisn'] ?: $s['username']);
        $row[] = $s['nama_lengkap'];
        $sid = (int)$s['id'];
        foreach ($mapel_list as $mp) {
            $mid = (int)$mp['id'];
            $row[] = isset($nilai_by[$sid][$mid]) ? $nilai_by[$sid][$mid] : '';
            $row[] = isset($desc_by[$sid][$mid]) ? preg_replace("/[\r\n]+/", ' ', $desc_by[$sid][$mid]) : '';
        }
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leger UAS - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/_overrides.css">
    <style>
        .top-bar { display: flex; justify-content: space-between; align-items: center; padding: 20px 30px; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 999; }
        .content-area { padding: 30px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .card-header { padding: 20px 24px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 24px; }
        .filters { display:flex; gap:12px; flex-wrap:wrap; }
        .filters select { padding: 8px 10px; border:1px solid #ddd; border-radius:6px; min-width: 220px; }
        .btn { cursor:pointer; }
        .btn-primary { background:#1e5ba8; color:#fff; padding:8px 14px; border-radius:8px; text-decoration:none; border:none; }
        .btn-secondary { background:#f1f5f9; color:#0f172a; padding:8px 14px; border-radius:8px; text-decoration:none; border:1px solid #e2e8f0; }
        .btn-outline { background:#fff; color:#0f172a; padding:8px 12px; border-radius:8px; border:1px solid #cbd5e1; }
        .table-container { overflow:auto; }
        table { width:100%; border-collapse: collapse; }
        th, td { border-bottom:1px solid #eee; padding:10px; text-align:left; vertical-align: top; }
        th { background:#f8fafc; }
        .alert { padding:12px 14px; border-radius:8px; margin-bottom:12px; }
        .alert-info { background:#eff6ff; color:#1e3a8a; border:1px solid #dbeafe; }
        .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; }
        .badge-info { background:#e0f2fe; color:#075985; }
        .toolbar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .mapel-head { text-align:center; }
        .desc-cell { white-space: normal; word-break: break-word; min-width: 260px; }
        @media print {
            .top-bar, .filters, .toolbar { display:none !important; }
            .card { box-shadow: none; }
            th, td { border: 1px solid #000 !important; }
            th { background: #fff !important; }
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-content">
    <div class="top-bar">
        <h1>📑 Leger UAS</h1>
        <div class="user-info">
            <span>Admin: <strong><?php echo htmlspecialchars(($_SESSION['nama_lengkap']) ?? ''); ?></strong></span>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="content-area">
        <div class="card">
            <div class="card-header">
                <h3>Filter Leger</h3>
                <div class="toolbar">
                    <?php if ($has_filter) : ?>
                        <a class="btn btn-outline" href="?tahun_ajaran_id=<?php echo $tahun_ajaran_id; ?>&semester=<?php echo urlencode($semester); ?>&kelas_id=<?php echo $kelas_id; ?>&export=1">⬇️ Export CSV</a>
                        <button class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print" aria-hidden="true"></i> Cetak</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" class="filters" action="">
                    <select name="tahun_ajaran_id" required>
                        <option value="">-- Pilih Tahun Ajaran --</option>
                        <?php foreach ($tahun_ajaran_opts as $ta): ?>
                            <option value="<?php echo $ta['id']; ?>" <?php echo ($tahun_ajaran_id == (int)$ta['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(($ta['nama_tahun_ajaran']) ?? ''); ?><?php echo ((int)$ta['is_active'] === 1) ? ' (Aktif)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="semester" required>
                        <option value="">-- Pilih Semester --</option>
                        <option value="Ganjil" <?php echo $semester==='Ganjil'?'selected':''; ?>>Ganjil</option>
                        <option value="Genap" <?php echo $semester==='Genap'?'selected':''; ?>>Genap</option>
                    </select>

                    <select name="kelas_id" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($kelas_opts as $k): ?>
                            <option value="<?php echo $k['id']; ?>" <?php echo ($kelas_id == (int)$k['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(($k['nama_kelas']) ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                    <?php if ($has_filter): ?>
                        <a href="rekap_nilai.php" class="btn btn-secondary" title="Reset"><i class="fas fa-eraser" aria-hidden="true"></i></a>
                    <?php endif; ?>
                </form>

                <div style="margin-top:16px;">
                    <div class="alert alert-info">
                        Leger UAS: pilih Tahun Ajaran, Semester, dan Kelas lalu klik Terapkan Filter. Tabel akan memuat kolom dinamis untuk setiap mapel: Nilai Akhir dan Capaian Kompetensi.
                    </div>
                </div>

                <?php if (!empty($error)) : ?>
                    <div class="alert alert-danger"><i class="fas fa-times-circle" aria-hidden="true"></i> <?php echo htmlspecialchars(($error) ?? ''); ?></div>
                <?php endif; ?>

                <?php if ($has_filter) : ?>
                <div class="table-container" style="margin-top:10px;">
                    <table>
                        <thead>
                            <tr>
                                <th rowspan="2">No</th>
                                <th rowspan="2">NISN/Username</th>
                                <th rowspan="2">Nama Siswa</th>
                                <?php foreach ($mapel_list as $mp): ?>
                                    <th class="mapel-head" colspan="2"><?php echo htmlspecialchars(($mp['nama_mapel']) ?? ''); ?></th>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <?php foreach ($mapel_list as $mp): ?>
                                    <th>Nilai Akhir</th>
                                    <th>Capaian Kompetensi</th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($siswa_list) === 0): ?>
                                <tr>
                                    <td colspan="<?php echo 3 + (count($mapel_list) * 2); ?>" style="text-align:center; padding:20px; color:#64748b;">Tidak ada siswa di kelas ini.</td>
                                </tr>
                            <?php else: $no = 1; ?>
                                <?php foreach ($siswa_list as $s): $sid = (int)$s['id']; ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><span class="badge badge-info"><?php echo htmlspecialchars(($s['nisn'] ?: $s['username']) ?? ''); ?></span></td>
                                        <td><strong><?php echo htmlspecialchars(($s['nama_lengkap']) ?? ''); ?></strong></td>
                                        <?php foreach ($mapel_list as $mp): $mid = (int)$mp['id']; ?>
                                            <td><?php echo isset($nilai_by[$sid][$mid]) ? htmlspecialchars(($nilai_by[$sid][$mid]) ?? '') : '-'; ?></td>
                                            <td class="desc-cell"><?php echo isset($desc_by[$sid][$mid]) ? nl2br(htmlspecialchars(($desc_by[$sid][$mid]) ?? '')) : '-'; ?></td>
                                        <?php endforeach; ?>
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
</div>
</body>
</html>


