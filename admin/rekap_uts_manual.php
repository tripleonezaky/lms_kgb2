<?php
// admin/rekap_uts_manual.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/check_session.php';
require_once __DIR__ . '/../includes/auth_admin.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

@query("ALTER TABLE assignment_guru ADD COLUMN kkm INT NULL AFTER tahun_ajaran_id");

function get_all_assignments(){
    $sql = "SELECT ag.id, ag.kkm, ag.kelas_id, ag.mapel_id, ag.tahun_ajaran_id, ag.guru_id,
                   k.nama_kelas, mp.nama_mapel, ta.nama_tahun_ajaran, u.nama_lengkap AS nama_guru
            FROM assignment_guru ag
            JOIN kelas k ON k.id = ag.kelas_id
            JOIN mata_pelajaran mp ON mp.id = ag.mapel_id
            JOIN tahun_ajaran ta ON ta.id = ag.tahun_ajaran_id
            JOIN users u ON u.id = ag.guru_id
            ORDER BY ta.id DESC, k.nama_kelas ASC, mp.nama_mapel ASC, u.nama_lengkap ASC";
    return fetch_all(query($sql));
}
function fetch_kontext($assignment_id){
    $res = query("SELECT ag.*, k.nama_kelas, mp.nama_mapel, ta.nama_tahun_ajaran, u.nama_lengkap AS nama_guru
                  FROM assignment_guru ag
                  JOIN kelas k ON k.id=ag.kelas_id
                  JOIN mata_pelajaran mp ON mp.id=ag.mapel_id
                  JOIN tahun_ajaran ta ON ta.id=ag.tahun_ajaran_id
                  JOIN users u ON u.id=ag.guru_id
                  WHERE ag.id=".(int)$assignment_id." LIMIT 1");
    return $res ? fetch_assoc($res) : null;
}
function fetch_siswa_kelas($kelas_id){
    $sql = "SELECT u.id, u.nama_lengkap, u.nisn, u.nis FROM users u WHERE u.role='siswa' AND u.kelas_id=".(int)$kelas_id." ORDER BY u.nama_lengkap";
    return fetch_all(query($sql));
}
function to_predikat($nilai){ if ($nilai === null) return '-'; $n=(float)$nilai; if($n>=85)return 'A'; if($n>=75)return 'B'; if($n>=60)return 'C'; return 'D'; }
function nilai_formatif($siswa_id,$mapel_id,$kelas_id,$tahun_ajaran_id,$semester){
    $sql = "SELECT ROUND(AVG(n.nilai),2) AS avg_val FROM nilai n WHERE n.siswa_id={$siswa_id} AND n.mapel_id={$mapel_id} AND n.kelas_id={$kelas_id} AND n.tahun_ajaran_id={$tahun_ajaran_id} AND n.semester='".escape_string($semester)."' AND n.jenis_penilaian IN ('Tugas','Quiz','Harian')";
    $res=query($sql); $row=$res?fetch_assoc($res):null; return $row&&$row['avg_val']!==null? (float)$row['avg_val']: null;
}
function nilai_uts($siswa_id,$mapel_id,$kelas_id,$tahun_ajaran_id,$semester){
    $sql = "SELECT ROUND(AVG(n.nilai),2) AS avg_val FROM nilai n WHERE n.siswa_id={$siswa_id} AND n.mapel_id={$mapel_id} AND n.kelas_id={$kelas_id} AND n.tahun_ajaran_id={$tahun_ajaran_id} AND n.semester='".escape_string($semester)."' AND n.jenis_penilaian='UTS'";
    $res=query($sql); $row=$res?fetch_assoc($res):null; return $row&&$row['avg_val']!==null? (float)$row['avg_val']: null;
}

$assignments = get_all_assignments();
$assignment_id = isset($_GET['assignment_id'])? (int)$_GET['assignment_id'] : 0;
$semester = isset($_GET['semester']) && in_array($_GET['semester'], ['Ganjil','Genap'], true) ? $_GET['semester'] : 'Ganjil';

// Handlers (sebelum output)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_kkm'])) {
        $aid = (int)($_POST['assignment_id'] ?? 0);
        $kkm = (int)$_POST['kkm'];
        if ($aid>0 && $kkm>0) {
            $ok = query("UPDATE assignment_guru SET kkm={$kkm} WHERE id={$aid} LIMIT 1");
            set_flash($ok? 'success':'error', $ok? 'KKM disimpan.':'Gagal menyimpan KKM.');
        } else set_flash('error','KKM tidak valid.');
        $qs = http_build_query(['assignment_id'=>$aid,'semester'=>$semester]); redirect('rekap_uts_manual.php?'.$qs);
    }
    if (isset($_POST['save_bulk'])) {
        $aid = (int)($_POST['assignment_id'] ?? 0);
        $ctx = $aid? fetch_kontext($aid): null;
        if (!$ctx) { set_flash('error','Assignment tidak valid.'); redirect('rekap_uts_manual.php'); }
        $mapel_id=(int)$ctx['mapel_id']; $kelas_id=(int)$ctx['kelas_id']; $tahun_ajaran_id=(int)$ctx['tahun_ajaran_id'];
        $fmtArr = isset($_POST['formatif']) && is_array($_POST['formatif']) ? $_POST['formatif'] : [];
        $utsArr = isset($_POST['uts']) && is_array($_POST['uts']) ? $_POST['uts'] : [];
        $updated = 0;
        foreach ($fmtArr as $sid=>$v){ $sid=(int)$sid; $val=trim((string)$v); if($val==='') continue; $num=(float)str_replace(',', '.', $val);
            $cek=query("SELECT id FROM nilai WHERE siswa_id={$sid} AND mapel_id={$mapel_id} AND kelas_id={$kelas_id} AND tahun_ajaran_id={$tahun_ajaran_id} AND semester='".escape_string($semester)."' AND jenis_penilaian='Harian' LIMIT 1");
            $rowC=$cek?fetch_assoc($cek):null; if($rowC) query("UPDATE nilai SET nilai={$num}, updated_at='".date('Y-m-d H:i:s')."' WHERE id=".(int)$rowC['id']);
            else query("INSERT INTO nilai (siswa_id,mapel_id,kelas_id,tahun_ajaran_id,semester,jenis_penilaian,nilai,created_at,updated_at) VALUES ({$sid},{$mapel_id},{$kelas_id},{$tahun_ajaran_id},'".escape_string($semester)."','Harian',{$num},'".date('Y-m-d H:i:s')."','".date('Y-m-d H:i:s')."')");
            $updated++; }
        foreach ($utsArr as $sid=>$v){ $sid=(int)$sid; $val=trim((string)$v); if($val==='') continue; $num=(float)str_replace(',', '.', $val);
            $cek=query("SELECT id FROM nilai WHERE siswa_id={$sid} AND mapel_id={$mapel_id} AND kelas_id={$kelas_id} AND tahun_ajaran_id={$tahun_ajaran_id} AND semester='".escape_string($semester)."' AND jenis_penilaian='UTS' LIMIT 1");
            $rowC=$cek?fetch_assoc($cek):null; if($rowC) query("UPDATE nilai SET nilai={$num}, updated_at='".date('Y-m-d H:i:s')."' WHERE id=".(int)$rowC['id']);
            else query("INSERT INTO nilai (siswa_id,mapel_id,kelas_id,tahun_ajaran_id,semester,jenis_penilaian,nilai,created_at,updated_at) VALUES ({$sid},{$mapel_id},{$kelas_id},{$tahun_ajaran_id},'".escape_string($semester)."','UTS',{$num},'".date('Y-m-d H:i:s')."','".date('Y-m-d H:i:s')."')");
            $updated++; }
        set_flash('success','Tersimpan. Baris diproses: '.$updated);
        $qs = http_build_query(['assignment_id'=>$aid,'semester'=>$semester]); redirect('rekap_uts_manual.php?'.$qs);
    }
    // Export
    if (isset($_POST['export']) || isset($_POST['export_csv'])) {
        $aid = (int)($_POST['assignment_id'] ?? 0);
        $ctx = $aid? fetch_kontext($aid): null; if(!$ctx){ set_flash('error','Assignment tidak valid.'); redirect('rekap_uts_manual.php'); }
        $rows = fetch_siswa_kelas((int)$ctx['kelas_id']);
        $kkm_val = (int)($ctx['kkm'] ?: 75);
        if (isset($_POST['export_csv'])) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=rekap_uts_'.preg_replace('/\s+/','_', $ctx['nama_mapel']).'_'.preg_replace('/\s+/','_', $ctx['nama_kelas']).'_'.strtolower($semester).'.csv');
            echo "\xEF\xBB\xBF"; echo "sep=\t\r\n"; // BOM + separator hint
            echo "No\tNISN\tNama Siswa\tKKM\tFormatif\tPredikat\tUTS\tPredikat\tKeterangan\r\n";
            $no=1; foreach ($rows as $r){
                $nisn=$r['nisn'] ?: ($r['nis'] ?: '');
                $fmt=nilai_formatif((int)$r['id'], (int)$ctx['mapel_id'], (int)$ctx['kelas_id'], (int)$ctx['tahun_ajaran_id'], $semester);
                $uts=nilai_uts((int)$r['id'], (int)$ctx['mapel_id'], (int)$ctx['kelas_id'], (int)$ctx['tahun_ajaran_id'], $semester);
                $ket=($fmt!==null && $uts!==null && $fmt>=$kkm_val && $uts>=$kkm_val)?'Tuntas':'Belum Tuntas';
                $line = [ $no++, $nisn, $r['nama_lengkap'], (string)$kkm_val, $fmt!==null? number_format($fmt,2) : '', to_predikat($fmt), $uts!==null? number_format($uts,2) : '', to_predikat($uts), $ket ];
                echo implode("\t", array_map(function($v){ return str_replace(["\t","\r","\n"], ' ', (string)$v); }, $line))."\r\n";
            }
            exit;
        } else {
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename=rekap_uts_'.preg_replace('/\s+/','_', $ctx['nama_mapel']).'_'.preg_replace('/\s+/','_', $ctx['nama_kelas']).'_'.strtolower($semester).'.xls');
            echo "<html><head><meta charset='UTF-8'></head><body>";
            echo '<table border="1">';
            echo '<tr><th>No</th><th>NISN</th><th>Nama Siswa</th><th>KKM</th><th>Formatif</th><th>Predikat</th><th>UTS</th><th>Predikat</th><th>Keterangan</th></tr>';
            $no=1; foreach ($rows as $r){
                $nisn=$r['nisn'] ?: ($r['nis'] ?: '');
                $fmt=nilai_formatif((int)$r['id'], (int)$ctx['mapel_id'], (int)$ctx['kelas_id'], (int)$ctx['tahun_ajaran_id'], $semester);
                $uts=nilai_uts((int)$r['id'], (int)$ctx['mapel_id'], (int)$ctx['kelas_id'], (int)$ctx['tahun_ajaran_id'], $semester);
                $ket=($fmt!==null && $uts!==null && $fmt>=$kkm_val && $uts>=$kkm_val)?'Tuntas':'Belum Tuntas';
                echo '<tr>'
                    .'<td>'.($no++).'</td>'
                    .'<td>'.htmlspecialchars($nisn, ENT_QUOTES, 'UTF-8').'</td>'
                    .'<td>'.htmlspecialchars($r['nama_lengkap'], ENT_QUOTES, 'UTF-8').'</td>'
                    .'<td>'.$kkm_val.'</td>'
                    .'<td>'.($fmt!==null? number_format($fmt,2) : '').'</td>'
                    .'<td>'.htmlspecialchars((to_predikat($fmt) ?? ''), ENT_QUOTES, 'UTF-8').'</td>'
                    .'<td>'.($uts!==null? number_format($uts,2) : '').'</td>'
                    .'<td>'.htmlspecialchars((to_predikat($uts) ?? ''), ENT_QUOTES, 'UTF-8').'</td>'
                    .'<td>'.htmlspecialchars($ket, ENT_QUOTES, 'UTF-8').'</td>'
                    .'</tr>';
            }
            echo '</table></body></html>';
            exit;
        }
    }
}

$ctx = $assignment_id>0? fetch_kontext($assignment_id): null;
$kkm_val = 75; if ($ctx && (int)$ctx['kkm']>0) $kkm_val=(int)$ctx['kkm'];
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin - Rekap Nilai UTS (Manual)</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .container{max-width:1200px;margin:14px auto;padding:12px}
    .card{background:#fff;border:1px solid #ddd;border-radius:10px;margin-bottom:14px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05)}
    .card-header{padding:14px 18px;background:#f5f7fb;font-weight:600}
    .card-body{padding:18px}
    .row{display:flex;gap:12px;flex-wrap:wrap}
    .row .col{flex:1 1 260px}
    label{display:block;margin-bottom:6px;font-weight:600}
    select,input[type=number]{width:100%;padding:10px;border:1px solid #ced4da;border-radius:8px;background:#fff;box-sizing:border-box}
    .btn{display:inline-block;padding:10px 14px;border-radius:8px;border:1px solid #2c7be5;background:#2c7be5;color:#fff;cursor:pointer;text-decoration:none}
    .btn:hover{filter:brightness(0.95)}
    .table{width:100%;border-collapse:collapse}
    .table th,.table td{border:1px solid #333;padding:8px}
    .table th{background:#f3f6ff;text-align:center}
    .muted{color:#64748b;font-size:12px}
    @media print { @page { size: A4 portrait; margin:12mm } .btn,.alert,.muted{display:none!important} .card{border:0;box-shadow:none} .table th{background:#e5e7eb!important} }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header">Admin - Rekap Nilai UTS (Manual)</div>
      <div class="card-body">
        <div style="margin-bottom:10px"><a class="btn back-btn" href="dashboard.php" onclick="var href=this.getAttribute('href'); document.body.style.transition='opacity .22s'; document.body.style.opacity=0; setTimeout(function(){ if (history.length>1) { history.back(); } else { window.location=href; } },220); return false;"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali</a></div>
        <?php if ($flash): ?><div class="alert alert-<?php echo htmlspecialchars(($flash['type']) ?? ''); ?>"><?php echo htmlspecialchars(($flash['message']) ?? ''); ?></div><?php endif; ?>
        <?php if (!$assignments): ?>
          <div class="muted">Belum ada assignment guru.</div>
        <?php else: ?>
          <form method="get" action="rekap_uts_manual.php" class="row" style="align-items:flex-end;margin-bottom:12px">
            <div class="col">
              <label>Assignment (Guru - Kelas - Mapel - TA)</label>
              <select name="assignment_id" required>
                <option value="">- pilih -</option>
                <?php foreach ($assignments as $a): $sel = ($assignment_id==(int)$a['id'])? 'selected':''; ?>
                  <option value="<?php echo (int)$a['id']; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars(($a['nama_guru'].' - '.$a['nama_kelas'].' - '.$a['nama_mapel'].' ('.$a['nama_tahun_ajaran'].')') ?? ''); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col">
              <label>Semester</label>
              <select name="semester">
                <option value="Ganjil" <?php echo $semester==='Ganjil'?'selected':''; ?>>Ganjil</option>
                <option value="Genap" <?php echo $semester==='Genap'?'selected':''; ?>>Genap</option>
              </select>
            </div>
            <div class="col"><button class="btn" type="submit">Terapkan</button></div>
          </form>
          <?php if ($ctx): ?>
          <form method="post" enctype="multipart/form-data" class="row" style="align-items:flex-end;margin-bottom:12px; gap:12px">
            <input type="hidden" name="assignment_id" value="<?php echo (int)$assignment_id; ?>" />
            <input type="hidden" name="semester" value="<?php echo htmlspecialchars(($semester) ?? ''); ?>" />
            <div class="col" style="max-width:160px">
              <label>KKM</label>
              <input type="number" name="kkm" value="<?php echo (int)$kkm_val; ?>" min="1" max="100" required />
            </div>
            <div class="col" style="flex:0 0 auto"><button class="btn" type="submit" name="save_kkm" value="1">Simpan KKM</button></div>
            <div class="col" style="flex:0 0 auto"><button class="btn" type="submit" name="export" value="1">Export XLS</button></div>
            <div class="col" style="flex:0 0 auto"><button class="btn" type="submit" name="export_csv" value="1">Export CSV</button></div>
            <div class="col" style="flex:0 0 auto"><button class="btn" type="button" onclick="window.print()">Print</button></div>
            <div class="col" style="flex:1 1 auto"><div class="muted">Konteks: <?php echo htmlspecialchars(($ctx['nama_guru'].' - '.$ctx['nama_mapel'].' - '.$ctx['nama_kelas'].' ('.$ctx['nama_tahun_ajaran'].')') ?? ''); ?></div></div>
          </form>
          <?php $siswa_list = fetch_siswa_kelas((int)$ctx['kelas_id']); ?>
          <form method="post">
            <input type="hidden" name="assignment_id" value="<?php echo (int)$assignment_id; ?>" />
            <input type="hidden" name="semester" value="<?php echo htmlspecialchars(($semester) ?? ''); ?>" />
            <div style="margin-bottom:10px"><button class="btn" type="submit" name="save_bulk" value="1">Simpan Semua</button></div>
          <div class="scroll-x">
            <table class="table">
              <thead>
                <tr>
                  <th>No.</th><th>NISN</th><th>Nama Siswa</th><th>KKM</th><th>Formatif</th><th>Predikat</th><th>UTS</th><th>Predikat</th><th>Keterangan</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$siswa_list): ?>
                  <tr><td colspan="9" style="text-align:center">Tidak ada siswa pada kelas ini.</td></tr>
                <?php else: $no=1; foreach ($siswa_list as $s): 
                  $nisn = $s['nisn'] ?: ($s['nis'] ?: '');
                  $fmt = nilai_formatif((int)$s['id'], (int)$ctx['mapel_id'], (int)$ctx['kelas_id'], (int)$ctx['tahun_ajaran_id'], $semester);
                  $uts = nilai_uts((int)$s['id'], (int)$ctx['mapel_id'], (int)$ctx['kelas_id'], (int)$ctx['tahun_ajaran_id'], $semester);
                  $ket = ($fmt!==null && $uts!==null && $fmt>=$kkm_val && $uts>=$kkm_val) ? 'Tuntas' : 'Belum Tuntas';
                ?>
                  <tr>
                    <td style="text-align:center"><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars(($nisn) ?? ''); ?></td>
                    <td><?php echo htmlspecialchars(($s['nama_lengkap']) ?? ''); ?></td>
                    <td style="text-align:center"><?php echo (int)$kkm_val; ?></td>
                    <td style="text-align:center"><input type="number" step="0.01" min="0" max="100" name="formatif[<?php echo (int)$s['id']; ?>]" value="<?php echo $fmt!==null? htmlspecialchars(number_format($fmt,2,'.','')) : ''; ?>" style="width:100px;padding:6px;border:1px solid #cbd5e1;border-radius:6px;text-align:right"></td>
                    <td style="text-align:center"><?php echo htmlspecialchars((to_predikat($fmt) ?? '')); ?></td>
                    <td style="text-align:center"><input type="number" step="0.01" min="0" max="100" name="uts[<?php echo (int)$s['id']; ?>]" value="<?php echo $uts!==null? htmlspecialchars(number_format($uts,2,'.','')) : ''; ?>" style="width:100px;padding:6px;border:1px solid #cbd5e1;border-radius:6px;text-align:right"></td>
                    <td style="text-align:center"><?php echo htmlspecialchars((to_predikat($uts) ?? '')); ?></td>
                    <td style="text-align:center"><?php echo htmlspecialchars(($ket) ?? ''); ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
            <div style="margin-top:10px"><button class="btn" type="submit" name="save_bulk" value="1">Simpan Semua</button></div>
          </form>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>

