<?php
// siswa/ujian/take.php
// Halaman ujian + endpoint autosave/violation/autosubmit/submit

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Suppress warnings/notices/deprecated when accessed via public IP to keep HTML form intact
if (!headers_sent()) {
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    if (preg_match('~^\d{1,3}(?:\.\d{1,3}){3}(?::\d+)?$~', $host)) {
        @ini_set('display_errors', '0');
        @error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
    }
}

require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../includes/check_role.php';
$preview = isset($_GET['preview']) ? (int)$_GET['preview'] : 0;
if ($preview === 1) {
    // Izinkan guru/admin melihat preview tanpa enforce role siswa
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['guru','admin','siswa'], true)) { header('Location: ../../index.php'); exit; }
} else {
    check_role(['siswa']);
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$siswa_id = (int)$_SESSION['user_id'];

function json_input() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function map_violation($reason) {
    $r = strtolower((string)$reason);
    if ($r === 'exit_fullscreen') return 'keluar_fullscreen';
    if ($r === 'waktu_habis') return 'auto_submit';
    // tab/blur/devtools -> pindah_tab sebagai fallback
    return 'pindah_tab';
}

function get_soal_detail($soal_id) {
    $soal_id = (int)$soal_id;
    $sql = "SELECT s.*, ag.kelas_id, ag.mapel_id, ag.tahun_ajaran_id, ta.semester
            FROM soal s
            JOIN assignment_guru ag ON ag.id = s.assignment_id
            JOIN tahun_ajaran ta ON ta.id = ag.tahun_ajaran_id
            WHERE s.id = {$soal_id} LIMIT 1";
    $res = query($sql);
    return $res ? fetch_assoc($res) : null;
}

function siswa_boleh_ikut($siswa_id, $soal) {
    // Syarat 1: siswa berada di kelas assignment
    $siswa_id = (int)$siswa_id;
    $sql = "SELECT kelas_id FROM users WHERE id = {$siswa_id} LIMIT 1";
    $u = query($sql);
    $row = $u ? fetch_assoc($u) : null;
    if (!$row) return false;
    if ((int)$row['kelas_id'] !== (int)$soal['kelas_id']) return false;

    // Syarat 2: jika ujian UTS/UAS, cek exam_access_siswa (per siswa)
    $jenis = isset($soal['jenis_ujian']) ? (string)$soal['jenis_ujian'] : '';
    if ($jenis === 'UTS' || $jenis === 'UAS') {
        // pastikan tabel ada
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
        $soal_id = (int)$soal['id'];
        $rs = query("SELECT is_allowed FROM exam_access_siswa WHERE soal_id={$soal_id} AND siswa_id={$siswa_id} LIMIT 1");
        $acc = $rs ? fetch_assoc($rs) : null;
        if ($acc && (int)$acc['is_allowed'] === 0) return false; // dibatasi wali kelas
    }
    return true;
}

function now_dt() {
    return date('Y-m-d H:i:s');
}

function parse_jawaban_pg_kompleks($val) {
    if (is_array($val)) {
        // gabungkan misal [A,C,D] -> 'A,B,D' diurutkan
        $vals = array_map('strval', $val);
        sort($vals);
        return implode(',', $vals);
    }
    return (string)$val;
}

function nilai_otomatis($tipe_soal, $jawaban, $kunci) {
    $tipe = strtolower($tipe_soal);
    if ($tipe === 'pilihan_ganda' || $tipe === 'benar_salah') {
        return ($jawaban === $kunci) ? 1 : 0;
    }
    if ($tipe === 'pilihan_ganda_kompleks') {
        // All-or-nothing hanya untuk is_correct; skor parsial dihitung di simpan_nilai_total
        $a = array_unique(array_filter(array_map('trim', explode(',', (string)$jawaban))));
        $b = array_unique(array_filter(array_map('trim', explode(',', (string)$kunci))));
        $aU = array_map('strtoupper', $a); sort($aU);
        $bU = array_map('strtoupper', $b); sort($bU);
        return ($aU === $bU) ? 1 : 0;
    }
    return null; // essay dinilai manual
}

function heuristik_jenis_ujian($judul) {
    $j = strtolower($judul);
    if (strpos($j, 'uts') !== false) return 'UTS';
    if (strpos($j, 'uas') !== false) return 'UAS';
    if (strpos($j, 'quiz') !== false) return 'Quiz';
    return 'Harian'; // default
}

function simpan_nilai_total($siswa_id, $soal) {
    // Hitung skor total dari jawaban_siswa sesuai bobot detail_soal
    $soal_id = (int)$soal['id'];
    $siswa_id = (int)$siswa_id;
    $q = query("SELECT ds.id as detail_id, ds.bobot, ds.tipe_soal, ds.jawaban_benar, js.jawaban, js.is_correct, js.nilai_essay
                FROM detail_soal ds
                LEFT JOIN jawaban_siswa js ON js.detail_soal_id = ds.id AND js.soal_id = {$soal_id} AND js.siswa_id = {$siswa_id}
                WHERE ds.soal_id = {$soal_id}");
    $total_bobot = 0; $skor_diperoleh = 0; $ada_essay = false;
    while ($row = fetch_assoc($q)) {
        $bobot = (int)$row['bobot'];
        $total_bobot += $bobot;
        $tipe = $row['tipe_soal'];
        if ($tipe === 'essay') { $ada_essay = true; continue; }
        if ($tipe === 'pilihan_ganda_kompleks') {
          // Partial credit with penalty for wrong selections:
          // each correct selected → +unit, each wrong selected → -unit
          // unit = bobot / jumlah_kunci. Final added score for this item is
          // (correct_count - wrong_count) * unit, clamped to minimum 0.
          $kunci = array_unique(array_filter(array_map('trim', explode(',', (string)$row['jawaban_benar']))));
          $jawab = array_unique(array_filter(array_map('trim', explode(',', (string)($row['jawaban'] ?? '')))));
          $kunciU = array_map('strtoupper', $kunci);
          $jawabU = array_map('strtoupper', $jawab);
          $nKey = max(count($kunciU), 1);
          $unit = $bobot / $nKey;
          $correct = 0; $wrong = 0;
          foreach ($jawabU as $v) {
            if (in_array($v, $kunciU, true)) $correct++; else $wrong++;
          }
          $delta = $correct - $wrong;
          $added = $delta * $unit;
          if ($added < 0) $added = 0; // clamp minimum to 0 to avoid negative contribution per item
          $skor_diperoleh += $added;
        } else {
            $benar = isset($row['is_correct']) ? (int)$row['is_correct'] : 0;
            $skor_diperoleh += ($benar * $bobot);
        }
    }
    // Jika ada essay, skor total akhir idealnya setelah dinilai guru. Untuk sementara, skor objektif saja.
    $persen = $total_bobot > 0 ? ($skor_diperoleh / $total_bobot) * 100.0 : 0.0;

    // Tulis ke tabel nilai
    $mapel_id = (int)$soal['mapel_id'];
    $kelas_id = (int)$soal['kelas_id'];
    $tahun_ajaran_id = (int)$soal['tahun_ajaran_id'];
    $semester = ($soal['semester'] == '2') ? 'Genap' : 'Ganjil';
    $jenis = isset($soal['jenis_ujian']) && in_array($soal['jenis_ujian'], ['Harian','UTS','UAS'])
        ? $soal['jenis_ujian']
        : heuristik_jenis_ujian($soal['judul_ujian']);

    // Cek apakah sudah ada nilai untuk siswa-soal jenis ujian dan assignment ini
    // Karena tabel nilai tidak ada kolom soal_id, kita gunakan kombinasi (siswa,mapel,kelas,tahun,semester,jenis) -> upsert
    $sqlCheck = "SELECT id FROM nilai WHERE siswa_id={$siswa_id} AND mapel_id={$mapel_id} AND kelas_id={$kelas_id} AND tahun_ajaran_id={$tahun_ajaran_id} AND semester='".escape_string($semester)."' AND jenis_penilaian='".escape_string($jenis)."' LIMIT 1";
    $r = query($sqlCheck);
    $row = $r ? fetch_assoc($r) : null;
    if ($row) {
        $id = (int)$row['id'];
        query("UPDATE nilai SET nilai=".(float)$persen.", updated_at='".now_dt()."' WHERE id={$id}");
    } else {
        $ins = "INSERT INTO nilai (siswa_id,mapel_id,kelas_id,tahun_ajaran_id,semester,jenis_penilaian,nilai,created_at,updated_at) VALUES ("
            ."{$siswa_id},{$mapel_id},{$kelas_id},{$tahun_ajaran_id},'".escape_string($semester)."','".escape_string($jenis)."',".(float)$persen.",'".now_dt()."','".now_dt()."')";
        query($ins);
    }
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'violation') {
    // POST JSON: { reason }
    $data = json_input();
    $reason = isset($data['reason']) ? $data['reason'] : '';
    $soal_id = isset($_SESSION['current_soal_id']) ? (int)$_SESSION['current_soal_id'] : 0;
    if ($soal_id > 0) {
        $aktivitas = map_violation($reason);
        $sql = "INSERT INTO log_ujian (soal_id, siswa_id, aktivitas, keterangan, created_at) VALUES ("
             ."{$soal_id},{$siswa_id},'".escape_string($aktivitas)."','".escape_string((string)$reason)."','".now_dt()."')";
        query($sql);
        http_response_code(204);
        exit;
    }
    http_response_code(400); echo 'no_soal'; exit;
}

if ($action === 'autosubmit') {
    // POST JSON: { reason }
    $data = json_input();
    $reason = isset($data['reason']) ? $data['reason'] : '';
    $soal_id = isset($_SESSION['current_soal_id']) ? (int)$_SESSION['current_soal_id'] : 0;
    if ($soal_id > 0) {
        $soal = get_soal_detail($soal_id);
        if ($soal && siswa_boleh_ikut($siswa_id, $soal)) {
            // log auto submit
            query("INSERT INTO log_ujian (soal_id, siswa_id, aktivitas, keterangan, created_at) VALUES ({$soal_id},{$siswa_id},'auto_submit','".escape_string($reason)."','".now_dt()."')");
            // tandai selesai + nilai otomatis objektif
            // set waktu_selesai pada semua jawaban siswa yang ada
            query("UPDATE jawaban_siswa SET waktu_selesai='".now_dt()."' WHERE soal_id={$soal_id} AND siswa_id={$siswa_id} AND waktu_selesai IS NULL");
            simpan_nilai_total($siswa_id, $soal);
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
            query("INSERT INTO exam_attempts (soal_id, siswa_id, allowed_attempts, used_attempts, created_at, updated_at) VALUES ({$soal_id},{$siswa_id},1,1,'".now_dt()."','".now_dt()."') ON DUPLICATE KEY UPDATE used_attempts=used_attempts+1, updated_at='".now_dt()."'");
            http_response_code(204);
            exit;
        }
    }
    http_response_code(400); echo 'invalid'; exit;
}

if ($action === 'autosave') {
    // POST FormData: soal_id, answers[detail_id]
    $soal_id = isset($_POST['soal_id']) ? (int)$_POST['soal_id'] : 0;
    if ($soal_id <= 0) { http_response_code(400); echo 'no_soal'; exit; }
    $soal = get_soal_detail($soal_id);
    if (!$soal || !siswa_boleh_ikut($siswa_id, $soal)) { http_response_code(403); echo 'forbidden'; exit; }

    $answers = isset($_POST['answers']) ? $_POST['answers'] : [];
    // Iterate detail soal yang di-submit, upsert jawaban_siswa
    foreach ($answers as $detail_id => $jawab) {
        $detail_id = (int)$detail_id;
        // ambil tipe + kunci untuk auto-grade bila memungkinkan
        $q = query("SELECT tipe_soal, jawaban_benar FROM detail_soal WHERE id={$detail_id} AND soal_id={$soal_id} LIMIT 1");
        $d = $q ? fetch_assoc($q) : null;
        if (!$d) continue;
        $tipe = $d['tipe_soal'];
        if ($tipe === 'pilihan_ganda_kompleks') {
            $jawab_str = parse_jawaban_pg_kompleks($jawab);
        } else {
            $jawab_str = is_array($jawab) ? (string)reset($jawab) : (string)$jawab;
        }
        $jawab_str_db = escape_string($jawab_str);
        $startAt = now_dt();

        // cek existing
        $chk = query("SELECT id FROM jawaban_siswa WHERE soal_id={$soal_id} AND detail_soal_id={$detail_id} AND siswa_id={$siswa_id} LIMIT 1");
        $row = $chk ? fetch_assoc($chk) : null;
        if ($row) {
            query("UPDATE jawaban_siswa SET jawaban='{$jawab_str_db}', updated_at='".now_dt()."' WHERE id=".(int)$row['id']);
        } else {
            query("INSERT INTO jawaban_siswa (soal_id, detail_soal_id, siswa_id, jawaban, waktu_mulai, created_at, updated_at) VALUES ("
                ."{$soal_id},{$detail_id},{$siswa_id},'{$jawab_str_db}','{$startAt}','{$startAt}','{$startAt}')");
        }
    }
    // proses flags (ragu-ragu)
    @query("ALTER TABLE jawaban_siswa ADD COLUMN flagged TINYINT(1) NOT NULL DEFAULT 0");
    $flags = isset($_POST['flags']) ? $_POST['flags'] : [];
    if (is_array($flags)) {
        foreach ($flags as $detail_id => $flag) {
            $detail_id = (int)$detail_id;
            $flagVal = ((string)$flag === '1') ? 1 : 0;
            $chk = query("SELECT id FROM jawaban_siswa WHERE soal_id={$soal_id} AND detail_soal_id={$detail_id} AND siswa_id={$siswa_id} LIMIT 1");
            $row = $chk ? fetch_assoc($chk) : null;
            if ($row) {
                query("UPDATE jawaban_siswa SET flagged={$flagVal}, updated_at='".now_dt()."' WHERE id=".(int)$row['id']);
            } else {
                $startAt = now_dt();
                query("INSERT INTO jawaban_siswa (soal_id, detail_soal_id, siswa_id, jawaban, flagged, waktu_mulai, created_at, updated_at) VALUES ({$soal_id},{$detail_id},{$siswa_id},'',{$flagVal},'{$startAt}','{$startAt}','{$startAt}')");
            }
        }
    }
    http_response_code(204);
    exit;
}

if ($action === 'submit') {
    // Final submit: nilai otomatis untuk objektif, set waktu_selesai dan simpan nilai total
    $soal_id = isset($_POST['soal_id']) ? (int)$_POST['soal_id'] : 0;
    if ($soal_id <= 0) { set_flash('error','Ujian tidak valid'); redirect('index.php'); }
    $soal = get_soal_detail($soal_id);
    if (!$soal || !siswa_boleh_ikut($siswa_id, $soal)) { set_flash('error','Anda tidak berhak mengakses ujian ini'); redirect('../dashboard.php'); }

    // simpan jawaban terakhir dari form (sama mekanisme dengan autosave)
    $answers = isset($_POST['answers']) ? $_POST['answers'] : [];
    foreach ($answers as $detail_id => $jawab) {
        $detail_id = (int)$detail_id;
        $q = query("SELECT tipe_soal, jawaban_benar, bobot FROM detail_soal WHERE id={$detail_id} AND soal_id={$soal_id} LIMIT 1");
        $d = $q ? fetch_assoc($q) : null;
        if (!$d) continue;
        $tipe = $d['tipe_soal'];
        $kunci = (string)$d['jawaban_benar'];
        if ($tipe === 'pilihan_ganda_kompleks') {
            $jawab_str = parse_jawaban_pg_kompleks($jawab);
        } else {
            $jawab_str = is_array($jawab) ? (string)reset($jawab) : (string)$jawab;
        }
        $jawab_str_db = escape_string($jawab_str);

        $chk = query("SELECT id FROM jawaban_siswa WHERE soal_id={$soal_id} AND detail_soal_id={$detail_id} AND siswa_id={$siswa_id} LIMIT 1");
        $row = $chk ? fetch_assoc($chk) : null;
        if ($row) {
            query("UPDATE jawaban_siswa SET jawaban='{$jawab_str_db}', updated_at='".now_dt()."' WHERE id=".(int)$row['id']);
        } else {
            $startAt = now_dt();
            query("INSERT INTO jawaban_siswa (soal_id, detail_soal_id, siswa_id, jawaban, waktu_mulai, created_at, updated_at) VALUES ("
                ."{$soal_id},{$detail_id},{$siswa_id},'{$jawab_str_db}','{$startAt}','{$startAt}','{$startAt}')");
        }
    }
    // Cek: jika masih ada soal belum terjawab dan waktu masih ada, tolak submit (kecuali autosubmit)
    $rowNow = fetch_assoc(query("SELECT NOW() AS now_db"));
    $now_ts = strtotime($rowNow ? $rowNow['now_db'] : now_dt());
    $mulai_ts = strtotime($soal['waktu_mulai']);
    $selesai_ts = strtotime($soal['waktu_selesai']);
    $totRow = fetch_assoc(query("SELECT COUNT(*) AS c FROM detail_soal WHERE soal_id={$soal_id}"));
    $answeredRow = fetch_assoc(query("SELECT COUNT(*) AS c FROM detail_soal ds LEFT JOIN jawaban_siswa js ON js.detail_soal_id=ds.id AND js.soal_id={$soal_id} AND js.siswa_id={$siswa_id} WHERE ds.soal_id={$soal_id} AND COALESCE(js.jawaban,'') <> ''"));
    $totalQ = $totRow ? (int)$totRow['c'] : 0;
    $answeredQ = $answeredRow ? (int)$answeredRow['c'] : 0;
    $autoReason = isset($_POST['auto_submit_reason']) ? trim((string)$_POST['auto_submit_reason']) : '';
    if ($now_ts < $selesai_ts && $answeredQ < $totalQ && $autoReason === '') {
    set_flash('error','Masih ada soal yang belum dikerjakan. Selesaikan terlebih dahulu sebelum mengumpulkan.');
    redirect('take.php?id='.$soal_id);
    }
    
    // Auto grading untuk objektif
    $q = query("SELECT id, tipe_soal, jawaban_benar FROM detail_soal WHERE soal_id={$soal_id}");
    while ($d = fetch_assoc($q)) {
        $detail_id = (int)$d['id'];
        $tipe = $d['tipe_soal'];
        $kunci = (string)$d['jawaban_benar'];
        if ($tipe === 'essay') continue; // manual
        // ambil jawaban siswa
        $j = query("SELECT id, jawaban FROM jawaban_siswa WHERE soal_id={$soal_id} AND detail_soal_id={$detail_id} AND siswa_id={$siswa_id} LIMIT 1");
        $jr = $j ? fetch_assoc($j) : null;
        if ($jr) {
            $jawab_str = (string)$jr['jawaban'];
            $benar = nilai_otomatis($tipe, $jawab_str, $kunci);
            if ($benar !== null) {
                // is_correct tetap 1 jika semua tepat (PGK), nilai total parsial dihitung di simpan_nilai_total
                query("UPDATE jawaban_siswa SET is_correct=".(int)$benar.", updated_at='".now_dt()."' WHERE id=".(int)$jr['id']);
            }
        }
    }

    // set waktu selesai
    query("UPDATE jawaban_siswa SET waktu_selesai='".now_dt()."' WHERE soal_id={$soal_id} AND siswa_id={$siswa_id} AND waktu_selesai IS NULL");

    // simpan nilai total ke tabel nilai
    simpan_nilai_total($siswa_id, $soal);

    // log selesai ujian
    query("INSERT INTO log_ujian (soal_id, siswa_id, aktivitas, keterangan, created_at) VALUES ({$soal_id},{$siswa_id},'selesai_ujian','submit','".now_dt()."')");

    // increment attempt terpakai
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
    query("INSERT INTO exam_attempts (soal_id, siswa_id, allowed_attempts, used_attempts, created_at, updated_at) VALUES ({$soal_id},{$siswa_id},1,1,'".now_dt()."','".now_dt()."') ON DUPLICATE KEY UPDATE used_attempts=used_attempts+1, updated_at='".now_dt()."'");

    set_flash('success','Ujian telah disubmit.');
    redirect('index.php');
}

// GET render halaman ujian
$soal_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($soal_id <= 0) { set_flash('error','Ujian tidak ditemukan'); redirect('index.php'); }
$soal = get_soal_detail($soal_id);
if (!$soal) { set_flash('error','Ujian tidak ditemukan'); redirect('index.php'); }
if (!$preview && !siswa_boleh_ikut($siswa_id, $soal)) { set_flash('error','Anda tidak berhak mengakses ujian ini'); redirect('../dashboard.php'); }

// Batasi attempt: 1 kali kecuali remedial (allowed_attempts > used_attempts)
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
$att = query("SELECT allowed_attempts, used_attempts FROM exam_attempts WHERE soal_id={$soal_id} AND siswa_id={$siswa_id} LIMIT 1");
$attRow = $att ? fetch_assoc($att) : null;
if (!$attRow) {
    query("INSERT INTO exam_attempts (soal_id, siswa_id, allowed_attempts, used_attempts, created_at, updated_at) VALUES ({$soal_id},{$siswa_id},1,0,'".now_dt()."','".now_dt()."')");
    $attRow = ['allowed_attempts'=>1,'used_attempts'=>0];
}
if (!$preview && (int)$attRow['used_attempts'] >= (int)$attRow['allowed_attempts']) {
    set_flash('error','Anda sudah menyelesaikan ujian ini. Hubungi guru jika membutuhkan remedial.');
    redirect('index.php');
}

// validasi window waktu ujian menggunakan waktu DB agar konsisten
$rowNow = fetch_assoc(query("SELECT NOW() AS now_db"));
$now_ts = strtotime($rowNow ? $rowNow['now_db'] : now_dt());
$mulai = strtotime($soal['waktu_mulai']);
$selesai = strtotime($soal['waktu_selesai']);
if (!$preview && $now_ts < $mulai) { set_flash('error','Ujian belum dimulai'); redirect('index.php'); }
if (!$preview && $now_ts > $selesai) { set_flash('error','Ujian telah berakhir'); redirect('index.php'); }

// set current soal id di session
if (!$preview) { $_SESSION['current_soal_id'] = $soal_id; }

// siapkan start time per siswa/soal (di sesi). Durasi individual mengikuti soal.durasi
$session_key = 'exam_start_'.$soal_id;
if (!isset($_SESSION[$session_key])) {
    $_SESSION[$session_key] = time();
    // log mulai ujian
    query("INSERT INTO log_ujian (soal_id, siswa_id, aktivitas, keterangan, created_at) VALUES ({$soal_id},{$siswa_id},'mulai_ujian',NULL,'".now_dt()."')");
}
$elapsed = isset($_SESSION[$session_key]) ? (time() - (int)$_SESSION[$session_key]) : 0;
$durasi_detik = $preview ? ((int)$soal['durasi'] * 60) : max(0, ((int)$soal['durasi']) * 60 - $elapsed);

// ambil detail soal
$detail = fetch_all(query("SELECT * FROM detail_soal WHERE soal_id={$soal_id} ORDER BY urutan ASC, id ASC"));

// Randomisasi urutan soal
$seed = $siswa_id * 100000 + $soal_id; // deterministik per siswa-ujian
mt_srand($seed);
shuffle($detail);
mt_srand();

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars(($soal['judul_ujian']) ?? ''); ?></title>
  <link rel="stylesheet" href="../../assets/css/style.css">
  <style>
    .container{max-width:1000px;margin:16px auto;padding:12px}
    .card{background:#fff;border:1px solid #ddd;border-radius:10px;margin-bottom:16px;overflow:hidden}
    .card-header{padding:14px 18px;background:#f5f5f5;font-weight:600}
    .card-body{padding:18px}
    hr{margin:14px 0}
    .soal{margin-bottom:16px}
    .opsi{margin-left:16px}
    .timer{position:sticky;top:0;background:#111;color:#fff;padding:10px 14px;border-radius:8px;display:inline-block;margin-bottom:10px;box-shadow:0 2px 6px rgba(0,0,0,.15)}
    .btn{display:inline-block;padding:10px 16px;border-radius:8px;border:1px solid #2c7be5;background:#2c7be5;color:#fff;cursor:pointer}
    .btn-primary{background:#2c7be5;color:#fff;border-color:#2c7be5}
    .btn-secondary{background:#6c757d;color:#fff;border-color:#6c757d}
    .btn:disabled{opacity:0.6;cursor:not-allowed}
    .actions{display:flex;gap:14px;flex-wrap:wrap}
    .qnav{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}
    .qnav-btn{min-width:40px;height:40px;border-radius:8px;border:1px solid #ccc;background:#f7f7f7;cursor:pointer}
    .qnav-btn.active{border-color:#2c7be5;background:#e8f2ff}
    .qnav-btn.answered{background:#e6f4ea;border-color:#9ad0a4}
    .qnav-btn.flagged{background:#fff3cd;border-color:#ffca2c}
    .legend{display:flex;gap:12px;margin:8px 0 12px;align-items:center;font-size:14px}
    .legend-item{display:flex;align-items:center;gap:6px}
    .legend-box{width:14px;height:14px;border:1px solid #ccc;border-radius:4px;display:inline-block}
    .legend-box.answered{background:#e6f4ea;border-color:#9ad0a4}
    .legend-box.unanswered{background:#f7f7f7;border-color:#ccc}
    .legend-box.flagged{background:#fff3cd;border-color:#ffca2c}
    .flag-row{margin-top:10px}
    .opsi > div{margin-bottom:8px}
    .nav-actions{margin-top:16px;display:flex;justify-content:space-between;align-items:center;gap:12px}
    .nav-buttons{display:flex;gap:12px;flex-wrap:wrap}
    .preview-score{font-weight:600;background:#f1f3f5;padding:2px 8px;border-radius:8px;margin-left:8px;font-size:0.95em;color:#222;display:inline-block}
    @media (max-width:576px){.container{padding:10px}.opsi{margin-left:8px}.qnav{gap:6px}}
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header">
        <?php echo htmlspecialchars(($soal['judul_ujian']) ?? ''); ?>
      </div>
      <div class="card-body">
        <div class="actions" style="justify-content:space-between;align-items:center">
          <div>Waktu tersisa: <span id="exam-timer" class="timer">--:--</span></div>
          <div>Durasi: <?php echo (int)$soal['durasi']; ?> menit</div>
        </div>
        <hr>
        <form id="exam-form" method="post" action="take.php?action=submit">
          <input type="hidden" name="soal_id" value="<?php echo $soal_id; ?>" />
          <input type="hidden" name="auto_submit_reason" id="auto-submit-reason" value="" />
          <div id="qnav" class="qnav" aria-label="Daftar Nomor Soal"></div>
          <div class="legend" aria-hidden="false">
            <span class="legend-item"><span class="legend-box answered"></span> Sudah dikerjakan</span>
            <span class="legend-item"><span class="legend-box flagged"></span> Ragu-ragu</span>
            <span class="legend-item"><span class="legend-box unanswered"></span> Belum dikerjakan</span>
          </div>
          <?php 
            // Bagi menjadi dua section: Objektif (PG, PGK, Benar/Salah) dan Essay
            $obj = []; $essayQ = [];
            foreach ($detail as $d) { if ($d['tipe_soal'] === 'essay') $essayQ[] = $d; else $obj[] = $d; }
            $qno = 1;
            if (count($obj) > 0) {
              echo '<h4 style="margin:10px 0">Bagian 1 - Pilihan Ganda</h4>';
              foreach ($obj as $d) {
          ?>
            <div class="soal" data-type="<?php echo htmlspecialchars(($d['tipe_soal']) ?? ''); ?>" data-key="<?php echo htmlspecialchars(($d['jawaban_benar']) ?? ''); ?>" data-id="<?php echo (int)$d['id']; ?>" data-bobot="<?php echo (int)$d['bobot']; ?>">
              <?php if (!empty($d['gambar_path']) && $d['gambar_posisi']==='atas'): ?>
                <div style="margin:8px 0"><img src="../../assets/uploads/<?php echo htmlspecialchars(($d['gambar_path']) ?? ''); ?>" alt="Gambar Soal" style="max-width:100%;height:auto;border:1px solid #eee;border-radius:6px"></div>
              <?php endif; ?>
              <?php if (!empty($d['video_url']) && $d['video_posisi']==='atas'): ?>
                <div style="margin:8px 0">
                  <?php $vu = $d['video_url']; if (preg_match('~(?:youtu.be/|youtube.com/(?:watch\?v=|embed/))([\w-]{11})~', $vu, $m)): ?>
                    <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:6px;border:1px solid #eee">
                      <iframe class="yt-embed" src="https://www.youtube.com/embed/<?php echo htmlspecialchars(($m[1]) ?? ''); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen style="position:absolute;top:0;left:0;width:100%;height:100%"></iframe>
                    </div>
                  <?php else: ?>
                    <video controls style="max-width:100%;border-radius:6px;border:1px solid #eee">
                      <source src="<?php echo (strpos($vu,'http')===0? htmlspecialchars(($vu) ?? '') : ('../../assets/uploads/'.htmlspecialchars(($vu) ?? ''))); ?>" type="video/mp4" />
                      Browser tidak mendukung video.
                    </video>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              <div><strong><?php echo ($qno++).'. '; ?></strong><?php echo nl2br(htmlspecialchars(($d['pertanyaan']) ?? '')); ?></div>
              <div class="preview-status" style="margin:6px 0; font-weight:600; display:none;">&nbsp;</div>
              <div class="opsi">
                <?php if ($d['tipe_soal'] === 'pilihan_ganda'): ?>
                  <?php 
                    $opsi = [];
                    foreach (['A','B','C','D','E'] as $opt){
                      $key = 'pilihan_'.strtolower($opt);
                      $imgKey = $key.'_path';
                      if (!empty($d[$key]) || !empty($d[$imgKey])){
                        if (!empty($d[$imgKey])) {
                          $labelHtml = '<img src="../../assets/uploads/'.htmlspecialchars(($d[$imgKey]) ?? '').'" alt="Pilihan '.htmlspecialchars(($opt) ?? '').'" style="max-width:220px;height:auto;border:1px solid #eee;border-radius:6px">';
                          $opsi[] = [$opt, $labelHtml, true];
                        } else {
                          $opsi[] = [$opt, $d[$key], false];
                        }
                      }
                    }
                    $seedOpsi = crc32($siswa_id.'-'.$soal_id.'-'.$d['id']);
                    mt_srand($seedOpsi);
                    shuffle($opsi);
                    mt_srand();
                    foreach ($opsi as $item): list($opt,$label,$isHtml) = $item; 
                  ?>
                    <div>
                      <label>
                        <input type="radio" name="answers[<?php echo (int)$d['id']; ?>]" value="<?php echo $opt; ?>"> <?php echo $isHtml ? $label : htmlspecialchars(($label) ?? ''); ?>
                      </label>
                    </div>
                  <?php endforeach; ?>
                <?php elseif ($d['tipe_soal'] === 'pilihan_ganda_kompleks'): ?>
                  <?php 
                    $opsi = [];
                    foreach (['A','B','C','D','E'] as $opt){
                      $key = 'pilihan_'.strtolower($opt);
                      $imgKey = $key.'_path';
                      if (!empty($d[$key]) || !empty($d[$imgKey])){
                        if (!empty($d[$imgKey])) {
                          $labelHtml = '<img src="../../assets/uploads/'.htmlspecialchars(($d[$imgKey]) ?? '').'" alt="Pilihan '.htmlspecialchars(($opt) ?? '').'" style="max-width:220px;height:auto;border:1px solid #eee;border-radius:6px">';
                          $opsi[] = [$opt, $labelHtml, true];
                        } else {
                          $opsi[] = [$opt, $d[$key], false];
                        }
                      }
                    }
                    $seedOpsi = crc32($siswa_id.'-'.$soal_id.'-'.$d['id'].'-pgk');
                    mt_srand($seedOpsi);
                    shuffle($opsi);
                    mt_srand();
                    foreach ($opsi as $item): list($opt,$label,$isHtml) = $item; 
                  ?>
                    <div>
                      <label>
                        <input type="checkbox" name="answers[<?php echo (int)$d['id']; ?>][]" value="<?php echo $opt; ?>"> <?php echo $isHtml ? $label : htmlspecialchars(($label) ?? ''); ?>
                      </label>
                    </div>
                  <?php endforeach; ?>
                <?php elseif ($d['tipe_soal'] === 'benar_salah'): ?>
                  <div><label><input type="radio" name="answers[<?php echo (int)$d['id']; ?>]" value="benar"> Benar</label></div>
                  <div><label><input type="radio" name="answers[<?php echo (int)$d['id']; ?>]" value="salah"> Salah</label></div>
                <?php endif; ?>
                <?php if (!empty($d['video_url']) && $d['video_posisi']==='bawah'): ?>
                  <div style="margin:8px 0">
                    <?php $vu = $d['video_url']; if (preg_match('~(?:youtu.be/|youtube.com/(?:watch\?v=|embed/))([\w-]{11})~', $vu, $m)): ?>
                      <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:6px;border:1px solid #eee">
                        <iframe class="yt-embed" src="https://www.youtube.com/embed/<?php echo htmlspecialchars(($m[1]) ?? ''); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen style="position:absolute;top:0;left:0;width:100%;height:100%"></iframe>
                      </div>
                    <?php else: ?>
                      <video controls style="max-width:100%;border-radius:6px;border:1px solid #eee">
                        <source src="<?php echo (strpos($vu,'http')===0? htmlspecialchars(($vu) ?? '') : ('../../assets/uploads/'.htmlspecialchars(($vu) ?? ''))); ?>" type="video/mp4" />
                        Browser tidak mendukung video.
                      </video>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($d['gambar_path']) && $d['gambar_posisi']==='bawah'): ?>
                  <div style="margin:8px 0"><img src="../../assets/uploads/<?php echo htmlspecialchars(($d['gambar_path']) ?? ''); ?>" alt="Gambar Soal" style="max-width:100%;height:auto;border:1px solid #eee;border-radius:6px"></div>
                <?php endif; ?>
                <div class="flag-row">
                  <input type="hidden" name="flags[<?php echo (int)$d['id']; ?>]" value="0" />
                  <label><input type="checkbox" name="flags[<?php echo (int)$d['id']; ?>]" value="1"> Tandai ragu-ragu</label>
                </div>
                <?php if ($preview && isset($_SESSION['role']) && in_array($_SESSION['role'], ['guru','admin'], true)): ?>
                  <div style="margin-top:8px">
                    <a class="btn btn-secondary icon-btn" href="../../guru/ujian/index.php?action=edit_question&soal_id=<?php echo $soal_id; ?>&id=<?php echo (int)$d['id']; ?>" target="_blank" rel="noopener" title="Edit" aria-label="Edit">Edit</a>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php 
              }
            }
            if (count($essayQ) > 0) {
              echo '<h4 style="margin:16px 0 10px">Bagian 2 - Essay</h4>';
              foreach ($essayQ as $d) {
          ?>
            <div class="soal" data-type="essay" data-key="" data-id="<?php echo (int)$d['id']; ?>" data-bobot="<?php echo (int)$d['bobot']; ?>">
              <?php if (!empty($d['gambar_path']) && $d['gambar_posisi']==='atas'): ?>
                <div style="margin:8px 0"><img src="../../assets/uploads/<?php echo htmlspecialchars(($d['gambar_path']) ?? ''); ?>" alt="Gambar Soal" style="max-width:100%;height:auto;border:1px solid #eee;border-radius:6px"></div>
              <?php endif; ?>
              <?php if (!empty($d['video_url']) && $d['video_posisi']==='atas'): ?>
                <div style="margin:8px 0">
                  <?php $vu = $d['video_url']; if (preg_match('~(?:youtu.be/|youtube.com/(?:watch\?v=|embed/))([\w-]{11})~', $vu, $m)): ?>
                    <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:6px;border:1px solid #eee">
                      <iframe class="yt-embed" src="https://www.youtube.com/embed/<?php echo htmlspecialchars(($m[1]) ?? ''); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen style="position:absolute;top:0;left:0;width:100%;height:100%"></iframe>
                    </div>
                  <?php else: ?>
                    <video controls style="max-width:100%;border-radius:6px;border:1px solid #eee">
                      <source src="<?php echo (strpos($vu,'http')===0? htmlspecialchars(($vu) ?? '') : ('../../assets/uploads/'.htmlspecialchars(($vu) ?? ''))); ?>" type="video/mp4" />
                      Browser tidak mendukung video.
                    </video>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              <div><strong><?php echo ($qno++).'. '; ?></strong><?php echo nl2br(htmlspecialchars(($d['pertanyaan']) ?? '')); ?></div>
              <div class="preview-status" style="margin:6px 0; font-weight:600; display:none;">&nbsp;</div>
              <div class="opsi">
                <textarea name="answers[<?php echo (int)$d['id']; ?>]" rows="5" style="width:100%" placeholder="Ketik jawaban Anda..."></textarea>
                <?php if (!empty($d['video_url']) && $d['video_posisi']==='bawah'): ?>
                  <div style="margin:8px 0">
                    <?php $vu = $d['video_url']; if (preg_match('~(?:youtu.be/|youtube.com/(?:watch\?v=|embed/))([\w-]{11})~', $vu, $m)): ?>
                      <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:6px;border:1px solid #eee">
                        <iframe class="yt-embed" src="https://www.youtube.com/embed/<?php echo htmlspecialchars(($m[1]) ?? ''); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen style="position:absolute;top:0;left:0;width:100%;height:100%"></iframe>
                      </div>
                    <?php else: ?>
                      <video controls style="max-width:100%;border-radius:6px;border:1px solid #eee">
                        <source src="<?php echo (strpos($vu,'http')===0? htmlspecialchars(($vu) ?? '') : ('../../assets/uploads/'.htmlspecialchars(($vu) ?? ''))); ?>" type="video/mp4" />
                        Browser tidak mendukung video.
                      </video>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($d['gambar_path']) && $d['gambar_posisi']==='bawah'): ?>
                  <div style="margin:8px 0"><img src="../../assets/uploads/<?php echo htmlspecialchars(($d['gambar_path']) ?? ''); ?>" alt="Gambar Soal" style="max-width:100%;height:auto;border:1px solid #eee;border-radius:6px"></div>
                <?php endif; ?>
                <div class="flag-row">
                  <input type="hidden" name="flags[<?php echo (int)$d['id']; ?>]" value="0" />
                  <label><input type="checkbox" name="flags[<?php echo (int)$d['id']; ?>]" value="1"> Tandai ragu-ragu</label>
                </div>
                  <?php if ($preview && isset($_SESSION['role']) && in_array($_SESSION['role'], ['guru','admin'], true)): ?>
                    <div style="margin-top:8px">
                      <a class="btn btn-secondary icon-btn" href="../../guru/ujian/index.php?action=edit_question&soal_id=<?php echo $soal_id; ?>&id=<?php echo (int)$d['id']; ?>" target="_blank" rel="noopener" title="Edit" aria-label="Edit">Edit</a>
                    </div>
                  <?php endif; ?>
              </div>
            </div>
          <?php 
              }
            }
          ?>
          <div class="nav-actions">
            <div class="nav-buttons">
              <button type="button" class="btn btn-secondary" id="btn-prev">Sebelumnya</button>
              <button type="button" class="btn" id="btn-next">Berikutnya</button>
            </div>
            <button type="submit" class="btn btn-primary">Kumpulkan Ujian</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php if (!$preview): ?>
  <script src="../../assets/js/exam_lockdown.js"></script>
  <script>
    // Inisialisasi lockdown + timer
    ExamLockdown.init({ durationSeconds: <?php echo (int)$durasi_detik; ?> });
  </script>
  <?php else: ?>
  <script>
    // Preview mode: tanpa lockdown, nonaktifkan submit dan tampilkan pengecekan jawaban real-time
    document.addEventListener('DOMContentLoaded', function(){
      var form = document.getElementById('exam-form');
      if (form) {
        var btn = form.querySelector('button[type=submit]');
        if (btn) { btn.disabled = true; btn.textContent = 'Preview - Submit Nonaktif'; }
      }
      // apakah pengguna preview adalah guru/admin? (hanya guru/admin yang akan melihat kunci terisi otomatis)
      var isTeacherPreview = <?php echo (isset($_SESSION['role']) && in_array($_SESSION['role'], ['guru','admin'], true)) ? 'true' : 'false'; ?>;

      function normSet(val){
        if (!val) return '';
        if (Array.isArray(val)) { return val.map(String).map(function(s){return s.trim().toUpperCase();}).sort().join(','); }
        return String(val).trim().toUpperCase();
      }

      // Jika preview oleh guru/admin, isi pilihan sesuai kunci agar terlihat langsung
      function applyKeySelections(){
        if (!isTeacherPreview) return;
        var soalEls = Array.from(document.querySelectorAll('.soal'));
        soalEls.forEach(function(el){
          var tipe = (el.getAttribute('data-type') || '').toLowerCase();
          var kunci = (el.getAttribute('data-key') || '').trim();
          if (!kunci) return;
          if (tipe === 'pilihan_ganda'){
            var radios = el.querySelectorAll('input[type="radio"]');
            radios.forEach(function(r){ if (normSet(r.value) === normSet(kunci)) r.checked = true; });
          } else if (tipe === 'pilihan_ganda_kompleks'){
            var keyArr = normSet(kunci).split(',').filter(Boolean);
            var checks = el.querySelectorAll('input[type="checkbox"]');
            checks.forEach(function(c){ c.checked = (keyArr.indexOf(normSet(c.value)) !== -1); });
          } else if (tipe === 'benar_salah'){
            var radios = el.querySelectorAll('input[type="radio"]');
            radios.forEach(function(r){ if (normSet(r.value) === normSet(kunci)) r.checked = true; });
          }
        });
      }

      function evaluatePreview(){
        var soalEls = Array.from(document.querySelectorAll('.soal'));
        soalEls.forEach(function(el){
          var tipe = (el.getAttribute('data-type') || '').toLowerCase();
          var kunci = (el.getAttribute('data-key') || '').trim();
          var status = el.querySelector('.preview-status');
          if (!status || !tipe || !kunci) { if (status){ status.style.display='none'; } return; }
          var message = '';
          if (tipe === 'pilihan_ganda'){
            var sel = el.querySelector('input[type="radio"]:checked');
            if (!sel) { status.style.display='none'; return; }
            var benar = (normSet(sel.value) === normSet(kunci));
            message = benar ? 'Jawaban benar' : 'Jawaban belum sesuai';
            status.style.color = benar ? '#1e7e34' : '#b00020';
          } else if (tipe === 'pilihan_ganda_kompleks'){
            var sels = Array.from(el.querySelectorAll('input[type="checkbox"]:checked')).map(function(i){return i.value;});
            if (sels.length === 0) { status.style.display='none'; return; }
            var sArr = normSet(sels).split(',').filter(Boolean);
            var kArr = normSet(kunci).split(',').filter(Boolean);
            // count how many selected options are in the key
            var common = sArr.filter(function(x){ return kArr.indexOf(x) !== -1; }).length;
            var wrong = sArr.length - common;
            var bobot = parseFloat(el.getAttribute('data-bobot')) || 1;
            var unit = (kArr.length>0) ? (bobot / kArr.length) : bobot;
            var delta = common - wrong;
            var added = Math.max(0, delta * unit);
            // concise message + numeric badge for preview (keeps consistent with server-side rule)
            var shortMsg = '';
            var badge = '';
            if (sArr.length === kArr.length && common === kArr.length) {
              shortMsg = 'Jawaban benar';
              badge = '<span class="preview-score">'+added.toFixed(2)+'/'+bobot+'</span>';
              status.style.color = '#1e7e34';
            } else if (common > 0) {
              shortMsg = 'Sebagian benar ('+common+'/'+kArr.length+')';
              badge = '<span class="preview-score">'+added.toFixed(2)+'/'+bobot+'</span>';
              status.style.color = '#e6a700';
            } else {
              shortMsg = 'Jawaban belum sesuai';
              badge = '<span class="preview-score">0/'+bobot+'</span>';
              status.style.color = '#b00020';
            }
            status.innerHTML = shortMsg + ' ' + badge;
            status.style.display = 'block';
            return;
          } else if (tipe === 'benar_salah'){
            var selbs = el.querySelector('input[type="radio"]:checked');
            if (!selbs) { status.style.display='none'; return; }
            var benar = (String(selbs.value).toLowerCase() === String(kunci).toLowerCase());
            message = benar ? 'Jawaban benar' : 'Jawaban belum sesuai';
            status.style.color = benar ? '#1e7e34' : '#b00020';
          } else {
            status.style.display = 'none';
            return;
          }
          status.textContent = message;
          status.style.display = 'block';
        });
      }

      // jika guru preview, terapkan kunci langsung lalu evaluasi agar status langsung terlihat
      applyKeySelections();
      evaluatePreview();

      document.getElementById('exam-form').addEventListener('change', evaluatePreview);
      document.getElementById('exam-form').addEventListener('input', evaluatePreview);
    });
  </script>
  <?php endif; ?>
  <script>
    // Navigasi per nomor per halaman
    (function(){
      const soalEls = Array.from(document.querySelectorAll('.soal'));
      if (!soalEls.length) return;
      soalEls.forEach((el,i)=>{ el.dataset.idx = i; });
      let current = 0;
      function show(i){
        current = Math.max(0, Math.min(i, soalEls.length-1));
        soalEls.forEach((el,idx)=>{ el.style.display = idx===current ? 'block' : 'none'; el.classList.toggle('active', idx===current); });
        updateButtons();
        highlightNav();
        window.scrollTo({ top: 0, behavior: 'instant' });
      }
      function isAnswered(el){
        const inputs = el.querySelectorAll('input,textarea');
        for (const inp of inputs){
          if (inp.type==='radio' || inp.type==='checkbox'){
            if (inp.checked) return true;
          } else if (inp.value && inp.value.trim() !== '') {
            return true;
          }
        }
        return false;
      }
      function buildNav(){
        const nav = document.getElementById('qnav');
        if (!nav) return;
        nav.innerHTML = '';
        soalEls.forEach((el,idx)=>{
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'qnav-btn';
          btn.textContent = (idx+1).toString();
          btn.addEventListener('click', ()=> show(idx));
          nav.appendChild(btn);
        });
      }
      function isFlagged(el){
        const f = el.querySelector('input[type="checkbox"][name^="flags["]');
        return f && f.checked;
      }
      function highlightNav(){
        const nav = document.getElementById('qnav');
        if (!nav) return;
        const btns = nav.querySelectorAll('.qnav-btn');
        btns.forEach((b,idx)=>{
          b.classList.toggle('active', idx===current);
          b.classList.toggle('answered', isAnswered(soalEls[idx]));
          b.classList.toggle('flagged', isFlagged(soalEls[idx]));
        });
      }
      function updateButtons(){
        const prev = document.getElementById('btn-prev');
        const next = document.getElementById('btn-next');
        if (prev) prev.disabled = (current<=0);
        if (next) next.disabled = (current>=soalEls.length-1);
      }
      function bindInputs(){
        const form = document.getElementById('exam-form');
        if (!form) return;
        form.addEventListener('change', function(){
          highlightNav();
        });
        form.addEventListener('input', function(){
          highlightNav();
        });
      }
      function bindNavButtons(){
        const prev = document.getElementById('btn-prev');
        const next = document.getElementById('btn-next');
        if (prev) prev.addEventListener('click', ()=> show(current-1));
        if (next) next.addEventListener('click', ()=> show(current+1));
      }
      buildNav();
      bindNavButtons();
      bindInputs();
      // tampilkan soal pertama
      show(0);
    })();
  </script>
  <script>
    // Cegah submit jika masih ada soal belum terjawab (kecuali autosubmit)
    (function(){
      const form = document.getElementById('exam-form');
      if (!form) return;
      function soalEls(){ return Array.from(document.querySelectorAll('.soal')); }
      function isAnswered(el){
        const inputs = el.querySelectorAll('input,textarea');
        for (const inp of inputs){
          if (inp.type==='radio' || inp.type==='checkbox'){
            if (inp.checked) return true;
          } else if (inp.value && inp.value.trim() !== '') {
            return true;
          }
        }
        return false;
      }
      function hasUnanswered(){ return soalEls().some(el => !isAnswered(el)); }
      function isAutoSubmit(){ var r = document.getElementById('auto-submit-reason'); return r && r.value && r.value.length>0; }
      form.addEventListener('submit', function(e){
        if (isAutoSubmit()) return; // izinkan autosubmit
        if (hasUnanswered()){
          e.preventDefault();
          alert('Masih ada soal yang belum dikerjakan. Harap lengkapi terlebih dahulu.');
        }
      });
    })();
  </script>
</body>
</html>

