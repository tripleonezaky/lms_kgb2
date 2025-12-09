<?php
/**
 * File: guru/nilai/rekap.php
 * Fitur: Rekap nilai untuk Guru berdasarkan assignment (kelas+mapel yang diajar)
 * Filter: Tahun ajaran (opsional, default aktif jika ada), Semester (wajib), Assignment (wajib)
 * Output: Tabel per-siswa dengan kolom komponen (UTS, UAS, Tugas, Quiz, Harian) dan Rata-rata
 * Ekspor: CSV melalui query param ?export=1
 */
session_start();
require_once '../../includes/check_session.php';
require_once '../../includes/check_role.php';
check_role(['guru']);
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$guru_id = (int)$_SESSION['user_id'];

// Helper sederhana untuk ambil semua baris dari query langsung (tanpa prepared), gunakan hati-hati
function fetch_all_rows($sql) {
    $res = query($sql);
    return fetch_all($res);
}

// Ambil tahun ajaran aktif (jika ada)
$tahun_ajaran_aktif = get_tahun_ajaran_aktif();
$ta_aktif_id = $tahun_ajaran_aktif ? (int)$tahun_ajaran_aktif['id'] : 0;

// Ambil filter
$tahun_ajaran_id = isset($_GET['tahun_ajaran_id']) && $_GET['tahun_ajaran_id'] !== '' ? (int)$_GET['tahun_ajaran_id'] : $ta_aktif_id;
$semester        = isset($_GET['semester']) ? trim($_GET['semester']) : '';
$assignment_id   = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

// Dropdown Tahun Ajaran
$tahun_ajaran_opts = fetch_all_rows("SELECT id, nama_tahun_ajaran, semester AS smt, is_active FROM tahun_ajaran ORDER BY is_active DESC, id DESC");

// Dropdown Assignment milik Guru (opsional filter TA)
$sql_assign_opts = "
    SELECT ag.id AS assignment_id,
           mp.nama_mapel,
           mp.kode_mapel,
           k.nama_kelas,
           ta.nama_tahun_ajaran,
           ta.id AS tahun_ajaran_id
    FROM assignment_guru ag
    JOIN mata_pelajaran mp ON ag.mapel_id = mp.id
    JOIN kelas k ON ag.kelas_id = k.id
    JOIN tahun_ajaran ta ON ag.tahun_ajaran_id = ta.id
    WHERE ag.guru_id = {$guru_id}
";
if ($tahun_ajaran_id > 0) {
    $sql_assign_opts .= " AND ag.tahun_ajaran_id = {$tahun_ajaran_id}";
}
$sql_assign_opts .= " ORDER BY ta.id DESC, k.nama_kelas, mp.nama_mapel";
$assignment_opts = fetch_all_rows($sql_assign_opts);

// Cek assignment dipilih valid dan milik guru
$selected_assignment = null;
if ($assignment_id > 0) {
    $sql_sel = "
        SELECT ag.*, k.nama_kelas, mp.nama_mapel
        FROM assignment_guru ag
        JOIN kelas k ON ag.kelas_id = k.id
        JOIN mata_pelajaran mp ON ag.mapel_id = mp.id
        WHERE ag.id = {$assignment_id} AND ag.guru_id = {$guru_id}
        LIMIT 1
    ";
    $res_sel = query($sql_sel);
    $selected_assignment = fetch_assoc($res_sel);
    if (!$selected_assignment) {
        $assignment_id = 0; // reset jika tidak valid
    }
}

// Validasi minimal filter
$has_filter = ($semester === 'Ganjil' || $semester === 'Genap') && $assignment_id > 0 && $tahun_ajaran_id > 0;

$rekap_rows = [];
$error = '';

if ($has_filter) {
    // Ambil kelas_id dan mapel_id dari assignment
    $kelas_id = (int)$selected_assignment['kelas_id'];
    $mapel_id = (int)$selected_assignment['mapel_id'];

    // Query agregasi nilai per siswa pada kelas_id, mapel_id, tahun_ajaran_id, semester
    $sem_esc = escape_string($semester);
    $sql = "
        SELECT 
            u.id AS siswa_id,
            u.username,
            u.nisn,
            u.nama_lengkap,
            k.nama_kelas,
            ROUND(AVG(CASE WHEN n.jenis_penilaian IN ('Tugas','Quiz','Harian') THEN n.nilai END), 2) AS nilai_harian,
            ROUND(AVG(CASE WHEN n.jenis_penilaian = 'UTS' THEN n.nilai END), 2)  AS uts,
            ROUND(AVG(CASE WHEN n.jenis_penilaian = 'UAS' THEN n.nilai END), 2)  AS uas,
            ROUND( (
                COALESCE(AVG(CASE WHEN n.jenis_penilaian IN ('Tugas','Quiz','Harian') THEN n.nilai END), 0) +
                COALESCE(AVG(CASE WHEN n.jenis_penilaian = 'UTS' THEN n.nilai END), 0) +
                COALESCE(AVG(CASE WHEN n.jenis_penilaian = 'UAS' THEN n.nilai END), 0)
            ) / NULLIF(
                (CASE WHEN COUNT(CASE WHEN n.jenis_penilaian IN ('Tugas','Quiz','Harian') THEN 1 END) > 0 THEN 1 ELSE 0 END) +
                (CASE WHEN COUNT(CASE WHEN n.jenis_penilaian = 'UTS' THEN 1 END) > 0 THEN 1 ELSE 0 END) +
                (CASE WHEN COUNT(CASE WHEN n.jenis_penilaian = 'UAS' THEN 1 END) > 0 THEN 1 ELSE 0 END)
            , 0), 2) AS rata_rata
        FROM users u
        LEFT JOIN kelas k ON u.kelas_id = k.id
        LEFT JOIN nilai n ON n.siswa_id = u.id 
            AND n.kelas_id = {$kelas_id}
            AND n.mapel_id = {$mapel_id}
            AND n.tahun_ajaran_id = {$tahun_ajaran_id}
            AND n.semester = '{$sem_esc}'
        WHERE u.role = 'siswa' AND u.kelas_id = {$kelas_id}
        GROUP BY u.id, u.username, u.nisn, u.nama_lengkap, k.nama_kelas
        ORDER BY u.nama_lengkap
    ";
    $res = query($sql);
    if ($res) {
        $rekap_rows = fetch_all($res);
    } else {
        $error = 'Gagal mengambil data rekap.';
    }
}

// Ekspor CSV
if ($has_filter && isset($_GET['export']) && (int)$_GET['export'] === 1) {
    header('Content-Type: text/csv; charset=utf-8');
    $fname = 'rekap_nilai_guru_TA' . $tahun_ajaran_id . '_' . $semester . '_assign' . $assignment_id . '.csv';
    header('Content-Disposition: attachment; filename=' . $fname);
    $output = fopen('php://output', 'w');
    fputcsv($output, ['No', 'NISN/Username', 'Nama Siswa', 'Kelas', 'Nilai Harian', 'UTS', 'UAS', 'Rata-rata']);
    $no = 1;
    foreach ($rekap_rows as $row) {
        fputcsv($output, [
            $no++,
            ($row['nisn'] ?: $row['username']),
            $row['nama_lengkap'],
            $row['nama_kelas'],
            $row['nilai_harian'], $row['uts'], $row['uas'], $row['rata_rata']
        ]);
    }
    fclose($output);
    exit();
}

?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Nilai - Guru</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/_overrides.css">
    <style>
        body { background:#f5f7fa; font-family: 'Poppins', sans-serif; }
        .container { max-width:1200px; margin:20px auto; }
        .card { background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.08); }
        .card-header { padding:20px 24px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; }
        .card-body { padding:24px; }
        .filters { display:flex; gap:12px; flex-wrap:wrap; }
        .filters select { padding:8px 10px; border:1px solid #ddd; border-radius:6px; min-width:220px; }
        .btn { cursor:pointer; }
        .btn-primary { background:#1e5ba8; color:#fff; padding:8px 14px; border-radius:8px; text-decoration:none; border:none; }
        .btn-secondary { background:#f1f5f9; color:#0f172a; padding:8px 14px; border-radius:8px; text-decoration:none; border:1px solid #e2e8f0; }
        .btn-outline { background:#fff; color:#0f172a; padding:8px 12px; border-radius:8px; border:1px solid #cbd5e1; }
        .table-container { overflow:auto; }
        table { width:100%; border-collapse:collapse; }
        th, td { border-bottom:1px solid #eee; padding:10px; text-align:left; }
        th { background:#f8fafc; }
        .alert { padding:12px 14px; border-radius:8px; margin-bottom:12px; }
        .alert-danger { background:#fef2f2; color:#991b1b; border:1px solid #fee2e2; }
        .alert-info { background:#eff6ff; color:#1e3a8a; border:1px solid #dbeafe; }
        .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; }
        .badge-info { background:#e0f2fe; color:#075985; }
        .header-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-bar">
            <h2>📊 Rekap Nilai Siswa</h2>
            <div>
                <a href="../dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali ke Dashboard</a>
                <?php if ($has_filter && empty($error)) : ?>
                    <a class="btn btn-outline" href="?tahun_ajaran_id=<?php echo $tahun_ajaran_id; ?>&semester=<?php echo urlencode($semester); ?>&assignment_id=<?php echo $assignment_id; ?>&export=1">⬇️ Export CSV</a>
                    <button class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print" aria-hidden="true"></i> Cetak</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Filter Rekap</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="filters" action="">
                    <select name="tahun_ajaran_id">
                        <option value="">-- Pilih Tahun Ajaran --</option>
                        <?php foreach ($tahun_ajaran_opts as $ta): ?>
                            <option value="<?php echo (int)$ta['id']; ?>" <?php echo ($tahun_ajaran_id == (int)$ta['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(($ta['nama_tahun_ajaran']) ?? ''); ?><?php echo ((int)($ta['is_active'] ?? 0) === 1) ? ' (Aktif)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="semester" required>
                        <option value="">-- Pilih Semester --</option>
                        <option value="Ganjil" <?php echo $semester==='Ganjil'?'selected':''; ?>>Ganjil</option>
                        <option value="Genap" <?php echo $semester==='Genap'?'selected':''; ?>>Genap</option>
                    </select>

                    <select name="assignment_id" required>
                        <option value="">-- Pilih Kelas & Mapel (Assignment) --</option>
                        <?php foreach ($assignment_opts as $a): ?>
                            <option value="<?php echo (int)$a['assignment_id']; ?>" <?php echo ($assignment_id == (int)$a['assignment_id']) ? 'selected' : ''; ?>>
                                TA <?php echo htmlspecialchars(($a['nama_tahun_ajaran']) ?? ''); ?> - <?php echo htmlspecialchars(($a['nama_kelas']) ?? ''); ?> - <?php echo htmlspecialchars(($a['nama_mapel']) ?? ''); ?> (<?php echo htmlspecialchars(($a['kode_mapel']) ?? ''); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                    <?php if ($semester || $assignment_id || $tahun_ajaran_id): ?>
                        <a href="rekap.php" class="btn btn-secondary" title="Reset"><i class="fas fa-eraser" aria-hidden="true"></i></a>
                    <?php endif; ?>
                </form>

                <div style="margin-top:16px;">
                    <div class="alert alert-info">
                        Tips: Pilih Tahun Ajaran, Semester, dan Assignment (kelas & mapel) untuk melihat rekap nilai siswa.
                    </div>
                </div>

                <?php if (!empty($error)) : ?>
                    <div class="alert alert-danger">��� <?php echo htmlspecialchars(($error) ?? ''); ?></div>
                <?php endif; ?>

                <?php if ($has_filter && empty($error)) : ?>
                <div class="table-container" style="margin-top:10px;">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>NISN/Username</th>
                                <th>Nama Siswa</th>
                                <th>Kelas</th>
                                <th>Nilai Harian</th>
                                <th>UTS</th>
                                <th>UAS</th>
                                <th>Rata-rata</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($rekap_rows) === 0): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center; padding:20px; color:#64748b;">Tidak ada data nilai untuk kombinasi filter ini.</td>
                                </tr>
                            <?php else: $no = 1; ?>
                                <?php foreach ($rekap_rows as $row): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><span class="badge badge-info"><?php echo htmlspecialchars(($row['nisn'] ?: $row['username']) ?? ''); ?></span></td>
                                        <td><strong><?php echo htmlspecialchars(($row['nama_lengkap']) ?? ''); ?></strong></td>
                                        <td><?php echo htmlspecialchars(($row['nama_kelas']) ?? ''); ?></td>
                                        <td><?php echo $row['nilai_harian'] !== null ? htmlspecialchars(($row['nilai_harian']) ?? '') : '-'; ?></td>
                                        <td><?php echo $row['uts'] !== null ? htmlspecialchars(($row['uts']) ?? '') : '-'; ?></td>
                                        <td><?php echo $row['uas'] !== null ? htmlspecialchars(($row['uas']) ?? '') : '-'; ?></td>
                                        <td><strong><?php echo $row['rata_rata'] !== null ? htmlspecialchars(($row['rata_rata']) ?? '') : '-'; ?></strong></td>
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

