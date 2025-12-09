<?php
/**
 * File: admin/import_export.php
 * Fitur: Export/Import CSV & XLS untuk Guru dan Siswa
 * Catatan:
 *  - Export XLS menggunakan HTML table (compatible Excel 97-2003)
 *  - Import difokuskan ke CSV agar stabil
 */
require_once __DIR__ . '/../includes/auth_admin.php';
require_once __DIR__ . '/../config/database.php';

function flash($k){ if(isset($_SESSION[$k])){ $m=$_SESSION[$k]; unset($_SESSION[$k]); return $m;} return null; }

// ========================= EXPORT HELPERS (XLS) =========================
function export_xls($title, $headers, $rows, $filename_prefix) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename_prefix . '_' . date('Ymd_His') . '.xls');
    echo "<html><head><meta charset='UTF-8'></head><body>";
    echo '<table border="1">';
    echo '<tr>'; foreach ($headers as $h) echo '<th>'.htmlspecialchars($h, ENT_QUOTES, 'UTF-8').'</th>'; echo '</tr>';
  foreach ($rows as $row) {
    echo '<tr>';
    foreach ($row as $cell) echo '<td>'.htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8').'</td>';
    echo '</tr>';
  }
    echo '</table></body></html>';
    exit;
}

// ========================= EXPORT HANDLERS =========================
if (isset($_GET['action'])) {
    $act = $_GET['action'];

    if ($act === 'export_guru_csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=guru_export_'.date('Ymd_His').'.csv');
        $out=fopen('php://output','w');
        fputcsv($out,['username','nama_lengkap','email','no_whatsapp','kode_guru','nuptk','is_active','password_plain']);
        $q="SELECT username,nama_lengkap,email,no_whatsapp,kode_guru,nuptk,is_active FROM users WHERE role='guru' ORDER BY id";
        $r=query($q);
        while($row=fetch_assoc($r)){
            fputcsv($out,[$row['username'],$row['nama_lengkap'],$row['email'],$row['no_whatsapp'],$row['kode_guru'],$row['nuptk'],$row['is_active'],'']);
        }
        fclose($out); exit;
    }

    if ($act === 'export_guru_xls') {
        $headers = ['username','nama_lengkap','email','no_whatsapp','kode_guru','nuptk','is_active','password_plain'];
        $rows = [];
        $r=query("SELECT username,nama_lengkap,email,no_whatsapp,kode_guru,nuptk,is_active FROM users WHERE role='guru' ORDER BY id");
        while($row=fetch_assoc($r)){
            $rows[] = [$row['username'],$row['nama_lengkap'],$row['email'],$row['no_whatsapp'],$row['kode_guru'],$row['nuptk'],$row['is_active'],''];
        }
        export_xls('Guru', $headers, $rows, 'guru_export');
    }

    if ($act === 'export_siswa_csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=siswa_export_'.date('Ymd_His').'.csv');
        $out=fopen('php://output','w');
        fputcsv($out,['username','nama_lengkap','email','no_whatsapp','nis','kelas_id','is_active','password_plain']);
        $q="SELECT username,nama_lengkap,email,no_whatsapp,nisn,kelas_id,is_active FROM users WHERE role='siswa' ORDER BY id";
        $r=query($q);
        while($row=fetch_assoc($r)){
            fputcsv($out,[$row['username'],$row['nama_lengkap'],$row['email'],$row['no_whatsapp'],$row['nisn'],$row['kelas_id'],$row['is_active'],'']);
        }
        fclose($out); exit;
    }

    if ($act === 'export_siswa_xls') {
        $headers = ['username','nama_lengkap','email','no_whatsapp','nis','kelas_id','is_active','password_plain'];
        $rows = [];
        $r=query("SELECT username,nama_lengkap,email,no_whatsapp,nisn,kelas_id,is_active FROM users WHERE role='siswa' ORDER BY id");
        while($row=fetch_assoc($r)){
            $rows[] = [$row['username'],$row['nama_lengkap'],$row['email'],$row['no_whatsapp'],$row['nisn'],$row['kelas_id'],$row['is_active'],''];
        }
        export_xls('Siswa', $headers, $rows, 'siswa_export');
    }

    if ($act === 'template_guru_csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=template_guru.csv');
        $out=fopen('php://output','w');
        fputcsv($out,['username','nama_lengkap','email','no_whatsapp','kode_guru','nuptk','is_active','password_plain']);
        fclose($out); exit;
    }

    if ($act === 'template_guru_xls') {
        $headers = ['username','nama_lengkap','email','no_whatsapp','kode_guru','nuptk','is_active','password_plain'];
        export_xls('Template', $headers, [], 'template_guru');
    }

    if ($act === 'template_siswa_csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=template_siswa.csv');
        $out=fopen('php://output','w');
        fputcsv($out,['username','nama_lengkap','email','no_whatsapp','nis','kelas_id','is_active','password_plain']);
        fclose($out); exit;
    }

    if ($act === 'template_siswa_xls') {
        $headers = ['username','nama_lengkap','email','no_whatsapp','nis','kelas_id','is_active','password_plain'];
        export_xls('Template', $headers, [], 'template_siswa');
    }

    if ($act === 'export_kelas_csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=kelas_export_'.date('Ymd_His').'.csv');
        $out=fopen('php://output','w');
        fputcsv($out,['id','nama_kelas']);
        $q="SELECT id, nama_kelas FROM kelas ORDER BY id";
        $r=query($q);
        while($row=fetch_assoc($r)){
            fputcsv($out,[$row['id'],$row['nama_kelas']]);
        }
        fclose($out); exit;
    }

    if ($act === 'export_kelas_xls') {
        $headers = ['id','nama_kelas'];
        $rows = [];
        $r=query("SELECT id, nama_kelas FROM kelas ORDER BY id");
        while($row=fetch_assoc($r)){
            $rows[] = [$row['id'],$row['nama_kelas']];
        }
        export_xls('Kelas', $headers, $rows, 'kelas_export');
    }
}

// ========================= IMPORT HANDLERS (CSV ONLY) =========================
function import_guru_csv($tmp) {
    $ok=0;$fail=0;$errs=[]; $h=fopen($tmp,'r'); if(!$h){ $_SESSION['error']='Gagal membuka CSV'; return; }
    $hdr=fgetcsv($h); if(!$hdr){ $_SESSION['error']='Header CSV tidak valid'; return; }
    $map=array_flip($hdr);
    while(($row=fgetcsv($h))!==false){
        $username_raw = isset($row[$map['username']]) ? $row[$map['username']] : '';
        $email_raw    = isset($row[$map['email']]) ? $row[$map['email']] : '';
        $kode_raw     = isset($row[$map['kode_guru']]) ? $row[$map['kode_guru']] : '';
        $nuptk_raw    = isset($row[$map['nuptk']]) ? $row[$map['nuptk']] : '';
        $wa_raw       = isset($row[$map['no_whatsapp']]) ? $row[$map['no_whatsapp']] : '';
        $nama_raw     = isset($row[$map['nama_lengkap']]) ? $row[$map['nama_lengkap']] : '';
        $act_raw      = isset($row[$map['is_active']]) ? $row[$map['is_active']] : '1';
        $pwd_plain    = (isset($map['password_plain']) && isset($row[$map['password_plain']])) ? trim($row[$map['password_plain']]) : '';

        // Normalisasi & escape
        $username = escape_string(trim($username_raw));
        $email    = escape_string(trim($email_raw));
        $kode     = escape_string(trim($kode_raw));
        $nuptk    = escape_string(trim($nuptk_raw));
        $wa       = escape_string(trim($wa_raw));
        $nama     = escape_string(trim($nama_raw));
        $active   = (int)trim($act_raw);

        if($username===''||$nama===''||$email===''||$kode===''){ $fail++; $errs[]="Baris invalid untuk username: $username"; continue; }

        // Cek apakah user guru sudah ada
        $ex = query("SELECT id FROM users WHERE username='$username' AND role='guru' LIMIT 1");
        $exRow = fetch_assoc($ex);
        if($exRow){
            $id = (int)$exRow['id'];
            // Validasi unik email/kode/nuptk kecuali diri sendiri
            $dupE=query("SELECT id FROM users WHERE email='$email' AND id!=$id LIMIT 1"); if(fetch_assoc($dupE)){ $fail++; $errs[]="Email duplikat: $email"; continue; }
            $dupK=query("SELECT id FROM users WHERE kode_guru='$kode' AND id!=$id LIMIT 1"); if(fetch_assoc($dupK)){ $fail++; $errs[]="Kode guru duplikat: $kode"; continue; }
            if($nuptk!==''){ $dupN=query("SELECT id FROM users WHERE nuptk='$nuptk' AND id!=$id LIMIT 1"); if(fetch_assoc($dupN)){ $fail++; $errs[]="NUPTK duplikat: $nuptk"; continue; } }
            $wa_sql = $wa!==''? "no_whatsapp='$wa'":"no_whatsapp=NULL";
            $nuptk_sql = $nuptk!==''? "nuptk='$nuptk'":"nuptk=NULL";
            $pwd_sql = ($pwd_plain!=='')? ", password='".escape_string(password_hash($pwd_plain, PASSWORD_DEFAULT))."'" : '';
            $upd_sql = "UPDATE users SET nama_lengkap='$nama', email='$email', $wa_sql, kode_guru='$kode', $nuptk_sql, is_active=$active $pwd_sql WHERE id=$id AND role='guru'";
            $okRun = query($upd_sql);
            if($okRun){ $ok++; } else { $fail++; $errs[]="Gagal update: $username"; }
        } else {
            // INSERT baru
            $dupE=query("SELECT id FROM users WHERE email='$email' LIMIT 1"); if(fetch_assoc($dupE)){ $fail++; $errs[]="Email duplikat: $email"; continue; }
            $dupK=query("SELECT id FROM users WHERE kode_guru='$kode' LIMIT 1"); if(fetch_assoc($dupK)){ $fail++; $errs[]="Kode guru duplikat: $kode"; continue; }
            if($nuptk!==''){ $dupN=query("SELECT id FROM users WHERE nuptk='$nuptk' LIMIT 1"); if(fetch_assoc($dupN)){ $fail++; $errs[]="NUPTK duplikat: $nuptk"; continue; } }
            $hash = password_hash($pwd_plain!==''? $pwd_plain : 'guru123', PASSWORD_DEFAULT);
            $wa_sql = $wa!==''? "'$wa'":"NULL"; $nuptk_sql=$nuptk!==''? "'$nuptk'":"NULL";
            $ins=query("INSERT INTO users (username,password,role,nama_lengkap,email,no_whatsapp,kode_guru,nuptk,is_active) VALUES ('$username','$hash','guru','$nama','$email',$wa_sql,'$kode',$nuptk_sql,$active)");
            if($ins) $ok++; else { $fail++; $errs[]='Gagal insert'; }
        }
    }
    fclose($h);
    $_SESSION['success']="Import Guru selesai. Sukses: $ok, Gagal: $fail"; if($fail>0) $_SESSION['error']=implode('; ',array_slice($errs,0,10));
}
function import_siswa_csv($tmp) {
    $ok=0;$fail=0;$errs=[]; $h=fopen($tmp,'r'); if(!$h){ $_SESSION['error']='Gagal membuka CSV'; return; }
    $hdr=fgetcsv($h); if(!$hdr){ $_SESSION['error']='Header CSV tidak valid'; return; }
    // Normalisasi header
    $normHdr = [];
    foreach ($hdr as $i=>$hname) {
        $key = strtolower(trim($hname));
        $key = preg_replace('/\s+|\-+/', '_', $key);
        $normHdr[$key] = $i;
    }
    $col = function($name) use ($normHdr){ return isset($normHdr[$name]) ? $normHdr[$name] : null; };
    $idx_username = $col('username');
    $idx_email = $col('email');
    $idx_wa = $col('no_whatsapp'); if ($idx_wa===null) $idx_wa = $col('no_whats_app'); if ($idx_wa===null) $idx_wa = $col('no_wa');
    $idx_nama = $col('nama_lengkap');
    $idx_nis = $col('nis'); if ($idx_nis===null) $idx_nis = $col('nisn');
    $idx_kelas_id = $col('kelas_id');
    $idx_kelas_nama = $col('kelas_nama');
    $idx_active = $col('is_active');
    $idx_pwd = $col('password_plain');

    $rownum = 1;
    while(($row=fgetcsv($h))!==false){
        $rownum++;
        $username_raw = ($idx_username!==null && isset($row[$idx_username])) ? $row[$idx_username] : '';
        $email_raw    = ($idx_email!==null && isset($row[$idx_email])) ? $row[$idx_email] : '';
        $wa_raw       = ($idx_wa!==null && isset($row[$idx_wa])) ? $row[$idx_wa] : '';
        $nama_raw     = ($idx_nama!==null && isset($row[$idx_nama])) ? $row[$idx_nama] : '';
        $nis_raw      = ($idx_nis!==null && isset($row[$idx_nis])) ? $row[$idx_nis] : '';
        $kelas_id_raw = ($idx_kelas_id!==null && isset($row[$idx_kelas_id])) ? $row[$idx_kelas_id] : '';
        $kelas_nama   = ($idx_kelas_nama!==null && isset($row[$idx_kelas_nama])) ? trim($row[$idx_kelas_nama]) : '';
        $act_raw      = ($idx_active!==null && isset($row[$idx_active])) ? $row[$idx_active] : '1';
        $pwd_plain    = ($idx_pwd!==null && isset($row[$idx_pwd])) ? trim($row[$idx_pwd]) : '';

        // Normalisasi username
        $username = trim((string)$username_raw);
        $username = preg_replace('/\s+/', '', $username);
        if (preg_match('/^[0-9]+$/', $username)===0) {
            $clean = preg_replace('/\D+/', '', $username);
            if ($clean!=='') $username = $clean;
        }
        $username = escape_string($username);

        $email    = escape_string(trim((string)$email_raw)); if ($email==='') $email = null;
        $wa       = escape_string(trim((string)$wa_raw)); if ($wa==='') $wa = null;
        $nama     = escape_string(trim((string)$nama_raw));
        $nis      = escape_string(trim((string)$nis_raw));
        $kelas_id = 0; if ($kelas_id_raw!=='' && $kelas_id_raw!==null) { $kelas_id = (int)trim((string)$kelas_id_raw); }
        $active   = (int)trim((string)$act_raw);

        if ($username==='' || $nama==='') { $fail++; $errs[]="Baris $rownum invalid: username/nama kosong"; continue; }

        if ($kelas_id<=0 && $kelas_nama!=='') {
            $qk = query("SELECT id FROM kelas WHERE LOWER(nama_kelas)=LOWER('".escape_string($kelas_nama)."') LIMIT 1");
            $rk = $qk? fetch_assoc($qk) : null;
            if ($rk) { $kelas_id = (int)$rk['id']; }
        }
        if ($kelas_id<=0) { $fail++; $errs[]="Baris $rownum invalid: kelas tidak valid (kelas_id kosong/tidak ditemukan)"; continue; }

        $cekK=query("SELECT id FROM kelas WHERE id=$kelas_id LIMIT 1"); if(!fetch_assoc($cekK)){ $fail++; $errs[]="Baris $rownum invalid: kelas_id tidak valid: $kelas_id"; continue; }

        $ex = query("SELECT id FROM users WHERE username='$username' AND role='siswa' LIMIT 1");
        $exRow = fetch_assoc($ex);
        if($exRow){
            $id=(int)$exRow['id'];
            if ($email!==null) { $dupE=query("SELECT id FROM users WHERE email='".$email."' AND id!=$id LIMIT 1"); if(fetch_assoc($dupE)){ $fail++; $errs[]="Baris $rownum: Email duplikat: ".$email; continue; } }
            if ($nis!=='') { $dupN=query("SELECT id FROM users WHERE nisn='".$nis."' AND id!=$id LIMIT 1"); if(fetch_assoc($dupN)){ $fail++; $errs[]="Baris $rownum: NIS/NISN duplikat: ".$nis; continue; } }
            $wa_sql = $wa!==null? "no_whatsapp='$wa'":"no_whatsapp=NULL";
            $email_sql = $email!==null? "email='$email'":"email=NULL";
            $nis_sql = $nis!==''? "nisn='$nis'":"nisn=NULL";
            $pwd_sql = ($pwd_plain!=='')? ", password='".escape_string(password_hash($pwd_plain, PASSWORD_DEFAULT))."'" : '';
            $upd_sql = "UPDATE users SET nama_lengkap='$nama', $email_sql, $wa_sql, $nis_sql, kelas_id=$kelas_id, is_active=$active $pwd_sql WHERE id=$id AND role='siswa'";
            $okRun = query($upd_sql);
            if($okRun){ $ok++; } else { $fail++; $errs[]="Baris $rownum: Gagal update: $username"; }
        } else {
            if ($email!==null) { $dupE=query("SELECT id FROM users WHERE email='".$email."' LIMIT 1"); if(fetch_assoc($dupE)){ $fail++; $errs[]="Baris $rownum: Email duplikat: ".$email; continue; } }
            if ($nis!=='') { $dupN=query("SELECT id FROM users WHERE nisn='".$nis."' LIMIT 1"); if(fetch_assoc($dupN)){ $fail++; $errs[]="Baris $rownum: NIS/NISN duplikat: ".$nis; continue; } }
            $hash = password_hash($pwd_plain!==''? $pwd_plain : 'siswa123', PASSWORD_DEFAULT);
            $wa_sql = $wa!==null? "'$wa'":"NULL";
            $email_sql = $email!==null? "'$email'":"NULL";
            $nis_sql = $nis!==''? "'$nis'":"NULL";
            $ins=query("INSERT INTO users (username,password,role,nama_lengkap,email,no_whatsapp,nisn,kelas_id,is_active) VALUES ('$username','$hash','siswa','$nama',$email_sql,$wa_sql,$nis_sql,$kelas_id,$active)");
            if($ins) $ok++; else { $fail++; $errs[]='Baris '.$rownum.': Gagal insert'; }
        }
    }
    fclose($h);
    $_SESSION['success']="Import Siswa selesai. Sukses: $ok, Gagal: $fail"; if($fail>0) $_SESSION['error']=implode('; ',array_slice($errs,0,20));
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['import_type'])) {
    $type = $_POST['import_type'];
    if (!isset($_FILES['file']) || $_FILES['file']['error']!==UPLOAD_ERR_OK) { $_SESSION['error']='Upload file gagal'; header('Location: import_export.php'); exit; }
    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') { $_SESSION['error'] = 'Format tidak didukung. Gunakan CSV.'; header('Location: import_export.php'); exit; }
    if ($type==='guru') {
        import_guru_csv($_FILES['file']['tmp_name']);
    } elseif ($type==='siswa') {
        import_siswa_csv($_FILES['file']['tmp_name']);
    }
    header('Location: import_export.php'); exit;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Import/Export - Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/_overrides.css">
<style>
.container{max-width:1100px;margin:10px auto;padding:10px}
.card{background:#fff;border:1px solid #ddd;border-radius:8px;margin-bottom:12px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,.04)}
.card-header{padding:12px 16px;background:#f5f5f5;font-weight:600;display:flex;justify-content:space-between;align-items:center}
.card-body{padding:16px}
.btn{display:inline-block;padding:10px 14px;border-radius:6px;border:1px solid #2c7be5;background:#2c7be5;color:#fff;text-decoration:none;cursor:pointer}
.btn:hover{filter:brightness(0.95)}
.row{display:flex;gap:12px;flex-wrap:wrap}
.col{flex:1 1 280px}
.alert{padding:10px;border-radius:6px;margin:10px 0}
.alert-success{background:#e6f4ea;color:#1e7e34}
.alert-error{background:#fdecea;color:#b00020}
.section{border:1px dashed #ddd;border-radius:8px;padding:12px;margin:6px 0}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-content">
  <div class="top-bar">
    <h1><i class="fas fa-file-import" aria-hidden="true"></i> Import/Export Data</h1>
    <div class="user-info">
      <span>Admin: <strong><?php echo htmlspecialchars(($_SESSION['nama_lengkap']) ?? ''); ?></strong></span>
      <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
  </div>
  <div class="content-area">
    <?php if($m=flash('success')): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $m; ?></div><?php endif; ?>
    <?php if($m=flash('error')): ?><div class="alert alert-error"><i class="fas fa-times-circle"></i> <?php echo $m; ?></div><?php endif; ?>

    <div class="card">
      <div class="card-header"><i class="fas fa-chalkboard-teacher" aria-hidden="true"></i> Data Guru</div>
      <div class="card-body">
        <div class="section">
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a class="btn icon-btn" data-icon="csv" href="import_export.php?action=export_guru_csv" title="Export CSV" aria-label="Export CSV">Export CSV</a>
            <a class="btn icon-btn" data-icon="xls" href="import_export.php?action=export_guru_xls" title="Export XLS" aria-label="Export XLS">Export XLS</a>
            <a class="btn icon-btn" data-icon="template" href="import_export.php?action=template_guru_csv" title="Template CSV" aria-label="Template CSV">Template CSV</a>
            <a class="btn icon-btn" data-icon="template" href="import_export.php?action=template_guru_xls">Template XLS</a>
          </div>
        </div>
        <div class="section">
          <form method="post" enctype="multipart/form-data" class="row">
            <input type="hidden" name="import_type" value="guru" />
            <div class="col">
              <label>Import File (CSV)</label>
              <input type="file" name="file" accept=".csv" required />
            </div>
            <div class="col" style="align-self:end"><button type="submit" class="btn">Import Guru</button></div>
          </form>
          <div style="color:#64748b; font-size:12px; margin-top:8px;">Catatan: Gunakan template CSV yang diunduh dari halaman ini.</div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="fas fa-user-graduate" aria-hidden="true"></i> Data Siswa</div>
      <div class="card-body">
        <div class="section">
          <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a class="btn icon-btn" data-icon="csv" href="import_export.php?action=export_siswa_csv" title="Export CSV" aria-label="Export CSV">Export CSV</a>
            <a class="btn icon-btn" data-icon="xls" href="import_export.php?action=export_siswa_xls" title="Export XLS" aria-label="Export XLS">Export XLS</a>
            <a class="btn icon-btn" data-icon="template" href="import_export.php?action=template_siswa_csv" title="Template CSV" aria-label="Template CSV">Template CSV</a>
            <a class="btn icon-btn" data-icon="template" href="import_export.php?action=template_siswa_xls">Template XLS</a>
            <a class="btn icon-btn" data-icon="csv" href="import_export.php?action=export_kelas_csv" title="Export Kelas CSV" aria-label="Export Kelas CSV">Export Kelas CSV</a>
            <a class="btn icon-btn" data-icon="xls" href="import_export.php?action=export_kelas_xls" title="Export Kelas XLS" aria-label="Export Kelas XLS">Export Kelas XLS</a>
          </div>
        </div>
        <div class="section">
          <form method="post" enctype="multipart/form-data" class="row">
            <input type="hidden" name="import_type" value="siswa" />
            <div class="col">
              <label>Import File (CSV)</label>
              <input type="file" name="file" accept=".csv" required />
            </div>
            <div class="col" style="align-self:end"><button type="submit" class="btn">Import Siswa</button></div>
          </form>
          <div style="color:#64748b; font-size:12px; margin-top:8px;">Catatan: Gunakan template CSV yang diunduh dari halaman ini.</div>
        </div>
      </div>
    </div>

  </div>
</div>
</body>
</html>


