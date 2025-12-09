<?php
/**
 * File: admin/cetak_rapor.php
 * Fungsi: Cetak Rapor Siswa (Printable)
 * Data sumber: tbl_komponen_nilai, tbl_nilai (nilai per komponen), assignment_guru (mapel, kelas, TA), users (siswa)
 */
require_once __DIR__ . '/../includes/auth_admin.php';
require_once __DIR__ . '/../config/database.php';

function ta_list(mysqli $conn){
    $r = mysqli_query($conn, "SELECT id, nama_tahun_ajaran, semester, is_active FROM tahun_ajaran ORDER BY nama_tahun_ajaran DESC, semester ASC");
    $out=[]; if($r){ while($row=mysqli_fetch_assoc($r)) $out[]=$row; } return $out;
}
function ta_aktif(mysqli $conn){
    $r = mysqli_query($conn, "SELECT id, nama_tahun_ajaran, semester FROM tahun_ajaran WHERE is_active=1 LIMIT 1");
    return $r? mysqli_fetch_assoc($r): null;
}
function kelas_list(mysqli $conn){
    $r = mysqli_query($conn, "SELECT k.id, k.nama_kelas, j.singkatan FROM kelas k LEFT JOIN jurusan j ON k.jurusan_id=j.id ORDER BY k.tingkat, k.nama_kelas");
    $out=[]; if($r){ while($row=mysqli_fetch_assoc($r)) $out[]=$row; } return $out;
}
function siswa_list_by_kelas(mysqli $conn, $kelas_id){
    $kelas_id=(int)$kelas_id; if($kelas_id<=0) return [];
    $r = mysqli_query($conn, "SELECT id, nama_lengkap FROM users WHERE role='siswa' AND is_active=1 AND kelas_id=$kelas_id ORDER BY nama_lengkap");
    $out=[]; if($r){ while($row=mysqli_fetch_assoc($r)) $out[]=$row; } return $out;
}

$ta_aktif = ta_aktif($conn);
$filter_ta   = isset($_GET['ta'])? (int)$_GET['ta'] : ($ta_aktif['id'] ?? 0);
$filter_kelas= isset($_GET['kelas_id'])? (int)$_GET['kelas_id'] : 0;
$filter_siswa= isset($_GET['siswa_id'])? (int)$_GET['siswa_id'] : 0;

$tas = ta_list($conn);
$kelases = kelas_list($conn);
$siswas = $filter_kelas? siswa_list_by_kelas($conn, $filter_kelas) : [];

// Ambil komponen nilai
$komponen=[]; $rk=mysqli_query($conn, "SELECT id_komponen, nama_komponen, bobot FROM tbl_komponen_nilai ORDER BY id_komponen");
if($rk){ while($row=mysqli_fetch_assoc($rk)) $komponen[]=$row; }

// Ambil assignment (mapel) utk kelas & TA
$mapel_list=[];
if($filter_ta && $filter_kelas){
    $q = "SELECT ag.id AS assignment_id, m.id AS mapel_id, m.nama_mapel
          FROM assignment_guru ag
          INNER JOIN mata_pelajaran m ON m.id=ag.mapel_id
          WHERE ag.tahun_ajaran_id=$filter_ta AND ag.kelas_id=$filter_kelas
          ORDER BY m.nama_mapel";
    $r = mysqli_query($conn, $q);
    if($r){ while($row=mysqli_fetch_assoc($r)) $mapel_list[]=$row; }
}

// Ambil nilai per siswa per assignment per komponen
$nilai_map=[]; // [$assignment_id][$id_komponen] = nilai
if($filter_siswa && !empty($mapel_list)){
    $assignment_ids = array_map(function($m){ return (int)$m['assignment_id']; }, $mapel_list);
    $assignment_ids = array_filter($assignment_ids, fn($v)=>$v>0);
    if(!empty($assignment_ids)){
        $in = implode(',', $assignment_ids);
        $qn = mysqli_query($conn, "SELECT id_assignment, id_komponen, nilai FROM tbl_nilai WHERE id_siswa=$filter_siswa AND id_assignment IN ($in)");
        if($qn){
            while($n=mysqli_fetch_assoc($qn)){
                $nilai_map[(int)$n['id_assignment']][(int)$n['id_komponen']] = (float)$n['nilai'];
            }
        }
    }
}

// Data identitas untuk header rapor
$identitas = null;
if($filter_siswa){
    $qiden = mysqli_query($conn, "SELECT u.nama_lengkap, k.nama_kelas, j.singkatan FROM users u
        LEFT JOIN kelas k ON u.kelas_id=k.id
        LEFT JOIN jurusan j ON k.jurusan_id=j.id
        WHERE u.id=$filter_siswa LIMIT 1");
    if($qiden && mysqli_num_rows($qiden)>0) $identitas = mysqli_fetch_assoc($qiden);
}

// Profil sekolah (untuk header cetak)
$profil=null; $rp=mysqli_query($conn, "SELECT * FROM tbl_profil_sekolah LIMIT 1"); if($rp && mysqli_num_rows($rp)>0) $profil=mysqli_fetch_assoc($rp);

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cetak Rapor - Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/_overrides.css">
<style>
@media print {
  .sidebar, .top-bar, .toolbar, .btn, .page-link, .alert { display: none !important; }
  .main-content { margin: 0 !important; }
  .card { box-shadow: none; border: none; }
  .print-header { display: block !important; }
}
.print-header { display: none; text-align: center; margin-bottom: 18px; }
.print-header h2 { margin: 0; }
.print-meta { display:flex; gap:20px; flex-wrap:wrap; justify-content:center; color:#444; }
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-content">
    <div class="top-bar">
        <h1>📄 Cetak Rapor</h1>
        <div class="user-info">
            <span>Admin: <strong><?php echo htmlspecialchars(($_SESSION['nama_lengkap']) ?? ''); ?></strong></span>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="content-area">
        <?php if(isset($_SESSION['success'])): ?><div class="alert alert-success"><i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div><?php endif; ?>
        <?php if(isset($_SESSION['error'])): ?><div class="alert alert-danger"><i class="fas fa-times-circle" aria-hidden="true"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>

        <div class="card">
            <div class="card-header"><h3>📋 Pilih Data Rapor</h3></div>
            <div class="card-body">
                <div class="card-toolbar toolbar" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:12px;">
                    <form method="GET" action="" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <label>TA:</label>
                        <select name="ta" onchange="this.form.submit()">
                            <option value="">-- Pilih --</option>
                            <?php foreach($tas as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo ($filter_ta==$t['id'])? 'selected':''; ?>>
                                <?php echo htmlspecialchars(($t['nama_tahun_ajaran']) ?? ''); ?> (<?php echo $t['semester']=='1'?'Ganjil':'Genap'; ?>)
                                <?php echo $t['is_active']? ' - Aktif':''; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <label>Kelas:</label>
                        <select name="kelas_id" onchange="this.form.submit()" <?php echo $filter_ta? '':'disabled'; ?>>
                            <option value="">-- Pilih --</option>
                            <?php foreach($kelases as $k): ?>
                            <option value="<?php echo $k['id']; ?>" <?php echo ($filter_kelas==$k['id'])? 'selected':''; ?>>
                                <?php echo htmlspecialchars(($k['nama_kelas']) ?? ''); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <label>Siswa:</label>
                        <select name="siswa_id" onchange="this.form.submit()" <?php echo ($filter_ta && $filter_kelas)? '':'disabled'; ?>>
                            <option value="">-- Pilih --</option>
                            <?php foreach($siswas as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo ($filter_siswa==$s['id'])? 'selected':''; ?>>
                                <?php echo htmlspecialchars(($s['nama_lengkap']) ?? ''); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-primary" onclick="window.print()" <?php echo ($filter_siswa && $filter_ta && $filter_kelas)? '':'disabled'; ?>><i class="fas fa-print" aria-hidden="true"></i> Cetak</button>
                    </form>
                    <?php if($filter_ta && $filter_kelas && !empty($siswas)): ?>
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:8px;">
                        <?php
                        // Navigasi Next/Prev siswa pada kelas yang sama
                        $ids = array_map(fn($s)=> (int)$s['id'], $siswas);
                        $pos = $filter_siswa ? array_search($filter_siswa, $ids, true) : false;
                        if($pos !== false){
                            $prevId = $ids[$pos-1] ?? null;
                            $nextId = $ids[$pos+1] ?? null;
                            $base = 'cetak_rapor.php?ta=' . urlencode($filter_ta) . '&kelas_id=' . urlencode($filter_kelas) . '&siswa_id=';
                            echo '<a class="btn btn-secondary" href="' . ($prevId ? $base.$prevId : '#') . '" '.($prevId? '':'disabled style="pointer-events:none;opacity:.6;"').'>⟵ Prev</a>';
                            echo '<a class="btn btn-secondary" href="' . ($nextId ? $base.$nextId : '#') . '" '.($nextId? '':'disabled style="pointer-events:none;opacity:.6;"').'>Next ⟶</a>';
                        }
                        ?>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if(!$filter_ta): ?>
                    <div class="alert alert-info">Pilih Tahun Ajaran untuk memulai.</div>
                <?php elseif(!$filter_kelas): ?>
                    <div class="alert alert-info">Pilih Kelas.</div>
                <?php elseif(!$filter_siswa): ?>
                    <div class="alert alert-info">Pilih Siswa.</div>
                <?php elseif(empty($komponen)): ?>
                    <div class="alert alert-warning">Belum ada Komponen Nilai. Tambahkan di menu Komponen Nilai.</div>
                <?php else: ?>
                    <div class="print-header">
                        <h2><?php echo htmlspecialchars($profil['nama_sekolah'] ?? 'SMK Karya Guna Bhakti 2'); ?></h2>
                        <div class="print-meta">
                            <div><strong>Tahun Ajaran:</strong> <?php foreach($tas as $t){ if($t['id']==$filter_ta){ echo htmlspecialchars(($t['nama_tahun_ajaran']) ?? '').' ('.($t['semester']=='1'?'Ganjil':'Genap').')'; break; } } ?></div>
                            <?php if($identitas): ?>
                            <div><strong>Kelas:</strong> <?php echo htmlspecialchars(($identitas['nama_kelas']) ?? ''); ?></div>
                            <div><strong>Siswa:</strong> <?php echo htmlspecialchars(($identitas['nama_lengkap']) ?? ''); ?></div>
                            <?php endif; ?>
                        </div>
                        <hr>
                    </div>

                    <div class="alert alert-info" style="display:flex;gap:16px;flex-wrap:wrap;">
                        <div><strong>Sekolah:</strong> <?php echo htmlspecialchars($profil['nama_sekolah'] ?? 'SMK Karya Guna Bhakti 2'); ?></div>
                        <?php if($identitas): ?>
                        <div><strong>Kelas:</strong> <?php echo htmlspecialchars(($identitas['nama_kelas']) ?? ''); ?></div>
                        <div><strong>Siswa:</strong> <?php echo htmlspecialchars(($identitas['nama_lengkap']) ?? ''); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:5%">No</th>
                                    <th style="width:30%">Mata Pelajaran</th>
                                    <?php foreach($komponen as $k): ?>
                                        <th><?php echo htmlspecialchars(($k['nama_komponen']) ?? ''); ?><br><small>(<?php echo (int)$k['bobot']; ?>%)</small></th>
                                    <?php endforeach; ?>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($mapel_list)): ?>
                                    <tr><td colspan="<?php echo 3+count($komponen); ?>" style="text-align:center; color:#999;">Tidak ada assignment untuk kelas/TA ini.</td></tr>
                                <?php else: $no=1; foreach($mapel_list as $mp): ?>
                                    <?php $total=0.0; ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><strong><?php echo htmlspecialchars(($mp['nama_mapel']) ?? ''); ?></strong></td>
                                        <?php foreach($komponen as $k): ?>
                                            <?php $val = $nilai_map[$mp['assignment_id']][$k['id_komponen']] ?? null; if($val!==null){ $total += ((float)$val * (int)$k['bobot'])/100.0; } ?>
                                            <td><?php echo $val!==null? htmlspecialchars(number_format((float)$val,2,'.','')) : '<em style="color:#bbb;">-</em>'; ?></td>
                                        <?php endforeach; ?>
                                        <td><span class="badge badge-info"><?php echo number_format($total,2); ?></span></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </div>
</div>

<script src="../assets/js/script.js"></script>
<script>
// Fokuskan select siswa setelah navigasi untuk mempercepat kerja admin
(function(){
  const url = new URL(window.location.href);
  if(url.searchParams.get('siswa_id')){
    const el = document.querySelector('select[name="siswa_id"]');
    if(el) el.focus();
  }
})();
</script>
</body>
</html>

