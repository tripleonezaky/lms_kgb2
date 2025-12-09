<?php
// guru/ujian/index.php
// List ujian guru + Create ujian + Builder soal (dalam satu file multi-aksi)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../includes/check_role.php';
check_role(['guru']);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$guru_id = (int)$_SESSION['user_id'];

function dt_local_to_mysql($str) {
    // input: YYYY-MM-DDTHH:MM
    $str = trim((string)$str);
    if ($str === '') return null;
    $str = str_replace('T', ' ', $str);
    $ts = strtotime($str);
    if ($ts === false) return null;
    return date('Y-m-d H:i:s', $ts);
}

function get_assignments_guru($guru_id) {
    $sql = "SELECT ag.id, ag.kelas_id, ag.mapel_id, ag.tahun_ajaran_id, mp.nama_mapel, k.nama_kelas, ta.nama_tahun_ajaran\n            FROM assignment_guru ag\n            JOIN mata_pelajaran mp ON mp.id = ag.mapel_id\n            JOIN kelas k ON k.id = ag.kelas_id\n            JOIN tahun_ajaran ta ON ta.id = ag.tahun_ajaran_id\n            WHERE ag.guru_id = ".(int)$guru_id."\n            ORDER BY ta.id DESC, k.nama_kelas ASC, mp.nama_mapel ASC";
    return fetch_all(query($sql));
}

function own_soal($guru_id, $soal_id) {
    $sql = "SELECT 1 FROM soal s JOIN assignment_guru ag ON ag.id = s.assignment_id WHERE s.id=".(int)$soal_id." AND ag.guru_id=".(int)$guru_id." LIMIT 1";
    $r = query($sql);
    return $r && fetch_assoc($r) ? true : false;
}

function get_soal($guru_id, $soal_id) {
    $sql = "SELECT s.*, mp.nama_mapel, k.nama_kelas, ta.nama_tahun_ajaran, ag.kelas_id AS assignment_kelas_id, ag.mapel_id AS assignment_mapel_id\n            FROM soal s\n            JOIN assignment_guru ag ON ag.id = s.assignment_id\n            JOIN mata_pelajaran mp ON mp.id = ag.mapel_id\n            JOIN kelas k ON k.id = ag.kelas_id\n            JOIN tahun_ajaran ta ON ta.id = ag.tahun_ajaran_id\n            WHERE s.id=".(int)$soal_id." AND ag.guru_id=".(int)$guru_id." LIMIT 1";
    $r = query($sql);
    return $r ? fetch_assoc($r) : null;
}

function get_detail_soal($soal_id) {
    return fetch_all(query("SELECT * FROM detail_soal WHERE soal_id=".(int)$soal_id." ORDER BY urutan ASC, id ASC"));
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Export pertanyaan ujian ke CSV/XLS
if ($action === 'export_questions' && isset($_GET['soal_id'])) {
    $soal_id = (int)($_GET['soal_id'] ?? 0);
    if ($soal_id <= 0 || !own_soal($guru_id, $soal_id)) { set_flash('error','Akses ditolak'); redirect('index.php'); }

    $format = strtolower(trim((string)($_GET['format'] ?? 'csv')));
    $headers = ['urutan','bobot','tipe_soal','pertanyaan','pilihan_a','pilihan_b','pilihan_c','pilihan_d','pilihan_e','jawaban_benar','gambar_path','gambar_posisi','video_url','video_posisi'];
    $rows = get_detail_soal($soal_id);

    if ($format === 'xls') {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="soal-'.$soal_id.'.xls"');
        echo "<html><head><meta charset='UTF-8'></head><body>";
        echo "<table border='1'><thead><tr>";
        foreach ($headers as $h) { echo '<th>'.htmlspecialchars(($h) ?? '').'</th>'; }
        echo "</tr></thead><tbody>";
        if ($rows) {
            foreach ($rows as $r) {
                echo '<tr>';
                $data = [
                    (int)$r['urutan'],
                    (int)$r['bobot'],
                    $r['tipe_soal'],
                    $r['pertanyaan'],
                    $r['pilihan_a'],
                    $r['pilihan_b'],
                    $r['pilihan_c'],
                    $r['pilihan_d'],
                    $r['pilihan_e'],
                    $r['jawaban_benar'],
                    $r['gambar_path'],
                    $r['gambar_posisi'],
                    $r['video_url'],
                    $r['video_posisi'],
                ];
                foreach ($data as $val) { echo '<td>'.htmlspecialchars((string)$val).'</td>'; }
                echo '</tr>';
            }
        }
        echo "</tbody></table></body></html>";
        exit;
    } else {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="soal-'.$soal_id.'.csv"');
        $out = fopen('php://output', 'w');
        // BOM untuk Excel
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);
        if ($rows) {
            foreach ($rows as $r) {
                $line = [
                    (int)$r['urutan'],
                    (int)$r['bobot'],
                    $r['tipe_soal'],
                    $r['pertanyaan'],
                    $r['pilihan_a'],
                    $r['pilihan_b'],
                    $r['pilihan_c'],
                    $r['pilihan_d'],
                    $r['pilihan_e'],
                    $r['jawaban_benar'],
                    $r['gambar_path'],
                    $r['gambar_posisi'],
                    $r['video_url'],
                    $r['video_posisi'],
                ];
                fputcsv($out, $line);
            }
        }
        fclose($out);
        exit;
    }
}

// Unduh template CSV/XLS untuk import soal
if ($action === 'download_template') {
    $format = strtolower(trim((string)($_GET['format'] ?? 'csv')));
    $headers = ['urutan','bobot','tipe_soal','pertanyaan','pilihan_a','pilihan_b','pilihan_c','pilihan_d','pilihan_e','jawaban_benar','gambar_path','gambar_posisi','video_url','video_posisi'];
    $sample = [1,1,'pilihan_ganda','Contoh pertanyaan','Opsi A','Opsi B','Opsi C','Opsi D','Opsi E','A','','','',''];

    if ($format === 'xls') {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="template-soal.xls"');
        echo "<html><head><meta charset='UTF-8'></head><body>";
        echo "<table border='1'><thead><tr>";
        foreach ($headers as $h) { echo '<th>'.htmlspecialchars(($h) ?? '').'</th>'; }
        echo "</tr></thead><tbody><tr>";
        foreach ($sample as $val) { echo '<td>'.htmlspecialchars((string)$val).'</td>'; }
        echo "</tr></tbody></table>";
        echo "<p>Keterangan: tipe_soal = pilihan_ganda | pilihan_ganda_kompleks | benar_salah | essay.\nKunci: contoh 'A' atau 'A,B' atau 'benar'/'salah'. gambar_posisi/video_posisi: 'atas' atau 'bawah'. gambar_path relatif dari assets/uploads/.</p>";
        echo "</body></html>";
        exit;
    } else {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="template-soal.csv"');
        $out = fopen('php://output','w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);
        fputcsv($out, $sample);
        fclose($out);
        exit;
    }
}

// Import soal dari CSV/XLS (XLS hasil export sistem ini berbentuk HTML-table)
if ($action === 'import_questions' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $soal_id = (int)($_POST['soal_id'] ?? 0);
    if ($soal_id <= 0 || !own_soal($guru_id, $soal_id)) { set_flash('error','Akses ditolak'); redirect('index.php'); }
    if (!isset($_FILES['file_soal']) || $_FILES['file_soal']['error'] !== UPLOAD_ERR_OK) {
        set_flash('error','Unggah file CSV/XLS terlebih dahulu'); redirect('index.php?action=builder&soal_id='.$soal_id);
    }

    $tmp = $_FILES['file_soal']['tmp_name'];
    $name = $_FILES['file_soal']['name'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    // Pastikan kolom opsional tersedia (idempotent di semua environment)
    @query("ALTER TABLE detail_soal ADD COLUMN IF NOT EXISTS gambar_path VARCHAR(255) NULL AFTER pilihan_e");
    @query("ALTER TABLE detail_soal ADD COLUMN IF NOT EXISTS gambar_posisi ENUM('atas','bawah') NULL AFTER gambar_path");
    @query("ALTER TABLE detail_soal ADD COLUMN IF NOT EXISTS video_url VARCHAR(255) NULL AFTER gambar_posisi");
    @query("ALTER TABLE detail_soal ADD COLUMN IF NOT EXISTS video_posisi ENUM('atas','bawah') NULL AFTER video_url");

    $allowed = ['csv','xls'];
    if (!in_array($ext, $allowed)) { set_flash('error','Format tidak didukung. Gunakan .csv atau .xls'); redirect('index.php?action=builder&soal_id='.$soal_id); }

    $headers = ['urutan','bobot','tipe_soal','pertanyaan','pilihan_a','pilihan_b','pilihan_c','pilihan_d','pilihan_e','jawaban_benar','gambar_path','gambar_posisi','video_url','video_posisi'];

    // Ambil urutan awal
    $startUrutan = 0; $rsu = query("SELECT MAX(urutan) AS m FROM detail_soal WHERE soal_id=".(int)$soal_id);
    $rowu = $rsu ? fetch_assoc($rsu) : null; if ($rowu && isset($rowu['m'])) { $startUrutan = (int)$rowu['m']; }

    $imported = 0; $failed = 0; $lineNo = 0;
    $now = date('Y-m-d H:i:s');

    if ($ext === 'csv') {
        // Deteksi delimiter , atau ;
        $content = file_get_contents($tmp);
        $delim = (substr_count(strtok($content, "\n"), ';') > substr_count(strtok($content, "\n"), ',')) ? ';' : ',';
        $fh = fopen($tmp, 'r');
        // Baca header
        $head = fgetcsv($fh, 0, $delim); $lineNo++;
        if (!$head) { fclose($fh); set_flash('error','Header CSV tidak terbaca'); redirect('index.php?action=builder&soal_id='.$soal_id); }
        $map = [];
        foreach ($head as $i => $h) { $key = strtolower(trim($h)); $map[$key] = $i; }
        // Valid minimal kolom
        if (!isset($map['tipe_soal']) || !isset($map['pertanyaan'])) { fclose($fh); set_flash('error','Kolom wajib tipe_soal dan pertanyaan tidak ditemukan'); redirect('index.php?action=builder&soal_id='.$soal_id); }

        while (($row = fgetcsv($fh, 0, $delim)) !== false) {
            $lineNo++;
            if (count(array_filter($row, fn($v)=>trim((string)$v)!=='')) === 0) continue; // skip baris kosong
            $data = [];
            foreach ($headers as $h) { $data[$h] = isset($map[$h]) ? trim((string)$row[$map[$h]]) : ''; }

            $tipe = strtolower($data['tipe_soal']);
            if (!in_array($tipe, ['pilihan_ganda','pilihan_ganda_kompleks','benar_salah','essay'])) { $failed++; continue; }
            $pertanyaan = escape_string($data['pertanyaan']); if ($pertanyaan==='') { $failed++; continue; }
            $bobot = (int)$data['bobot']; if ($bobot<=0) $bobot = 1;
            $urutan = (int)$data['urutan']; if ($urutan<=0) { $startUrutan++; $urutan = $startUrutan; }
            $jawaban_benar = escape_string($data['jawaban_benar']);
            $gp = in_array($data['gambar_posisi'], ['atas','bawah']) ? $data['gambar_posisi'] : '';
            $vp = in_array($data['video_posisi'], ['atas','bawah']) ? $data['video_posisi'] : '';

            $sql = "INSERT INTO detail_soal (soal_id, tipe_soal, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, pilihan_e, jawaban_benar, bobot, urutan, gambar_path, gambar_posisi, video_url, video_posisi, created_at, updated_at) VALUES (".
                   (int)$soal_id.
                   ", '".escape_string($tipe)."', '".$pertanyaan."', '".escape_string($data['pilihan_a'])."', '".escape_string($data['pilihan_b'])."', '".escape_string($data['pilihan_c'])."', '".escape_string($data['pilihan_d'])."', '".escape_string($data['pilihan_e'])."', '".$jawaban_benar."', ".(int)$bobot.", ".(int)$urutan.", "
                   .($data['gambar_path']!==''?"'".escape_string($data['gambar_path'])."'":"NULL").", "
                   .($gp!==''?"'".escape_string($gp)."'":"NULL").", "
                   .($data['video_url']!==''?"'".escape_string($data['video_url'])."'":"NULL").", "
                   .($vp!==''?"'".escape_string($vp)."'":"NULL").
                   ", '".$now."', '".$now."')";
            if (query($sql)) { $imported++; } else { $failed++; }
        }
        fclose($fh);
    } else { // xls (HTML table hasil export)
        $html = file_get_contents($tmp);
        if ($html === false) { set_flash('error','Tidak bisa membaca file XLS'); redirect('index.php?action=builder&soal_id='.$soal_id); }
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML($html);
        libxml_clear_errors();
        if (!$loaded) { set_flash('error','Format XLS tidak dikenali'); redirect('index.php?action=builder&soal_id='.$soal_id); }
        $rowsNode = $doc->getElementsByTagName('tr');
        if (!$rowsNode || $rowsNode->length === 0) { set_flash('error','Tabel tidak ditemukan dalam XLS'); redirect('index.php?action=builder&soal_id='.$soal_id); }
        $map = [];
        foreach ($rowsNode as $idx => $tr) {
            $cells = $tr->getElementsByTagName('th');
            if ($cells->length === 0 && $idx === 0) { $cells = $tr->getElementsByTagName('td'); }
            if ($idx === 0) {
                foreach ($cells as $i => $th) { $key = strtolower(trim($th->textContent)); $map[$key] = $i; }
                if (!isset($map['tipe_soal']) || !isset($map['pertanyaan'])) { set_flash('error','Header XLS tidak valid'); redirect('index.php?action=builder&soal_id='.$soal_id); }
                continue;
            }
            $tds = $tr->getElementsByTagName('td');
            if ($tds->length === 0) continue;
            $data = [];
            foreach ($headers as $h) {
                $i = $map[$h] ?? null;
                $data[$h] = $i!==null ? trim((string)$tds->item($i)->textContent) : '';
            }
            $tipe = strtolower($data['tipe_soal']);
            if (!in_array($tipe, ['pilihan_ganda','pilihan_ganda_kompleks','benar_salah','essay'])) { $failed++; continue; }
            $pertanyaan = escape_string($data['pertanyaan']); if ($pertanyaan==='') { $failed++; continue; }
            $bobot = (int)$data['bobot']; if ($bobot<=0) $bobot = 1;
            $urutan = (int)$data['urutan']; if ($urutan<=0) { $startUrutan++; $urutan = $startUrutan; }
            $jawaban_benar = escape_string($data['jawaban_benar']);
            $gp = in_array($data['gambar_posisi'], ['atas','bawah']) ? $data['gambar_posisi'] : '';
            $vp = in_array($data['video_posisi'], ['atas','bawah']) ? $data['video_posisi'] : '';

            $sql = "INSERT INTO detail_soal (soal_id, tipe_soal, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, pilihan_e, jawaban_benar, bobot, urutan, gambar_path, gambar_posisi, video_url, video_posisi, created_at, updated_at) VALUES (".
                   (int)$soal_id.
                   ", '".escape_string($tipe)."', '".$pertanyaan."', '".escape_string($data['pilihan_a'])."', '".escape_string($data['pilihan_b'])."', '".escape_string($data['pilihan_c'])."', '".escape_string($data['pilihan_d'])."', '".escape_string($data['pilihan_e'])."', '".$jawaban_benar."', ".(int)$bobot.", ".(int)$urutan.", "
                   .($data['gambar_path']!==''?"'".escape_string($data['gambar_path'])."'":"NULL").", "
                   .($gp!==''?"'".escape_string($gp)."'":"NULL").", "
                   .($data['video_url']!==''?"'".escape_string($data['video_url'])."'":"NULL").", "
                   .($vp!==''?"'".escape_string($vp)."'":"NULL").
                   ", '".$now."', '".$now."')";
            if (query($sql)) { $imported++; } else { $failed++; }
        }
    }

    if ($imported > 0) {
        set_flash('success', 'Import selesai. Berhasil: '.(int)$imported.'; Gagal: '.(int)$failed);
    } else {
        set_flash('error', 'Import gagal atau tidak ada baris valid. Gagal: '.(int)$failed);
    }
    redirect('index.php?action=builder&soal_id='.$soal_id);
}

// Handle Update Exam (edit data ujian)
if ($action === 'update_exam' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $soal_id = (int)($_POST['soal_id'] ?? 0);
    if ($soal_id <= 0 || !own_soal($guru_id, $soal_id)) { set_flash('error','Akses ditolak'); redirect('index.php'); }

    // Ambil data soal sumber untuk mengetahui judul lama dan memetakan kelompok salinan
    $src = get_soal($guru_id, $soal_id);
    if (!$src) { set_flash('error','Ujian tidak ditemukan'); redirect('index.php'); }
    $judul_lama = (string)$src['judul_ujian'];

    $judul = isset($_POST['judul_ujian']) ? escape_string($_POST['judul_ujian']) : '';
    $deskripsi = isset($_POST['deskripsi']) ? escape_string($_POST['deskripsi']) : '';
    $waktu_mulai = dt_local_to_mysql($_POST['waktu_mulai'] ?? '');
    $waktu_selesai = dt_local_to_mysql($_POST['waktu_selesai'] ?? '');
    $durasi = (int)($_POST['durasi'] ?? 0);
    $tampil_nilai = isset($_POST['tampil_nilai']) ? 1 : 0;
    $jenis_ujian = (isset($_POST['jenis_ujian']) && in_array($_POST['jenis_ujian'], ['Harian','UTS','UAS'])) ? $_POST['jenis_ujian'] : 'Harian';
    @query("ALTER TABLE soal ADD COLUMN IF NOT EXISTS jenis_ujian ENUM('Harian','UTS','UAS') NOT NULL DEFAULT 'Harian' AFTER deskripsi");
    $semester = (isset($_POST['semester']) && in_array($_POST['semester'], ['Ganjil','Genap'])) ? $_POST['semester'] : 'Ganjil';
    @query("ALTER TABLE soal ADD COLUMN IF NOT EXISTS semester ENUM('Ganjil','Genap') NOT NULL DEFAULT 'Ganjil' AFTER jenis_ujian");

    if (!$judul) { set_flash('error','Judul wajib diisi'); redirect('index.php?action=builder&soal_id='.$soal_id); }

    // Kumpulkan semua soal milik guru ini dengan judul lama yang sama (grup salinan)
    $soal_rs = query("SELECT s.id FROM soal s JOIN assignment_guru ag ON ag.id=s.assignment_id WHERE ag.guru_id={$guru_id} AND s.judul_ujian='".escape_string($judul_lama)."'");
    $ids = [];
    while ($row = $soal_rs ? fetch_assoc($soal_rs) : null) { $ids[] = (int)$row['id']; }

    if (empty($ids)) { // fallback update single
        $ids = [$soal_id];
    }

    $now = date('Y-m-d H:i:s');
    $updatedCnt = 0; $skippedCnt = 0;

    foreach ($ids as $sid) {
        // Cegah perubahan jadwal/durasi jika sudah ada jawaban siswa untuk soal tersebut
        $rAtt = query("SELECT 1 FROM jawaban_siswa WHERE soal_id={$sid} LIMIT 1");
        $hasAttempt = ($rAtt && fetch_assoc($rAtt));

        $set_parts = [
            "judul_ujian='{$judul}'",
            "deskripsi='{$deskripsi}'",
            "jenis_ujian='".escape_string($jenis_ujian)."'",
            "semester='".escape_string($semester)."'",
            "tampil_nilai={$tampil_nilai}"
        ];
        if (!$hasAttempt) {
            if ($waktu_mulai && $waktu_selesai && $durasi > 0) {
                $set_parts[] = "waktu_mulai='{$waktu_mulai}'";
                $set_parts[] = "waktu_selesai='{$waktu_selesai}'";
                $set_parts[] = "durasi={$durasi}";
            } else {
                // jika data jadwal tidak lengkap, lewati perubahan jadwal untuk soal ini
            }
        } // jika sudah ada attempt, jadwal/durasi tetap dipertahankan
        $set_sql = implode(',', $set_parts);

        if (query("UPDATE soal SET {$set_sql}, updated_at='{$now}' WHERE id={$sid} LIMIT 1")) { $updatedCnt++; } else { $skippedCnt++; }
    }

    if ($updatedCnt > 0) {
        $msg = "Ujian diperbarui pada ".$updatedCnt." kelas";
        if ($skippedCnt > 0) { $msg .= ", sebagian jadwal tidak diubah karena sudah ada jawaban."; }
        set_flash('success', $msg);
    } else {
        set_flash('error','Gagal memperbarui ujian.');
    }
    redirect('index.php?action=builder&soal_id='.$soal_id);
}

// Handle Clone Exam to other assignments (duplicate exam and its questions)
if ($action === 'clone_to_assignments' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $source_soal_id = (int)($_POST['source_soal_id'] ?? 0);
    $target_assignments = isset($_POST['assignment_ids']) ? array_map('intval', (array)$_POST['assignment_ids']) : [];

    if ($source_soal_id <= 0 || !own_soal($guru_id, $source_soal_id)) {
        set_flash('error', 'Akses ditolak.');
        redirect('index.php');
    }
    if (empty($target_assignments)) {
        set_flash('error', 'Pilih minimal satu assignment tujuan.');
        redirect('index.php?action=builder&soal_id=' . $source_soal_id);
    }

    // Ambil data soal sumber
    $src = query("SELECT * FROM soal WHERE id=".$source_soal_id." LIMIT 1");
    $soal_src = $src ? fetch_assoc($src) : null;
    if (!$soal_src) {
        set_flash('error','Ujian sumber tidak ditemukan.');
        redirect('index.php');
    }
    $current_assignment_id = (int)$soal_src['assignment_id'];

    // Validasi assignment tujuan milik guru dan bukan assignment saat ini
    $ids_str = implode(',', array_unique(array_filter($target_assignments)));
    $valid_targets = [];
    if ($ids_str !== '') {
        $rs = query("SELECT id FROM assignment_guru WHERE guru_id=".$guru_id." AND id IN (".$ids_str.")");
        while ($row = $rs ? fetch_assoc($rs) : null) {
            $aid = (int)$row['id'];
            if ($aid !== $current_assignment_id) { $valid_targets[] = $aid; }
        }
    }
    if (empty($valid_targets)) {
        set_flash('error','Assignment tujuan tidak valid atau sama dengan assignment saat ini.');
        redirect('index.php?action=builder&soal_id=' . $source_soal_id);
    }

    // Siapkan data untuk cloning
    $judul = escape_string($soal_src['judul_ujian']);
    $deskripsi = escape_string((string)$soal_src['deskripsi']);
    $jenis_ujian = escape_string((string)($soal_src['jenis_ujian'] ?? 'Harian'));
    $semester = escape_string((string)($soal_src['semester'] ?? 'Ganjil'));
    $waktu_mulai = escape_string((string)$soal_src['waktu_mulai']);
    $waktu_selesai = escape_string((string)$soal_src['waktu_selesai']);
    $durasi = (int)$soal_src['durasi'];
    $tampil_nilai = (int)$soal_src['tampil_nilai'];
    $now = date('Y-m-d H:i:s');

    // Ambil detail soal sumber
    $detail_src = fetch_all(query("SELECT * FROM detail_soal WHERE soal_id=".$source_soal_id." ORDER BY urutan ASC, id ASC"));

    $created = 0; $first_new_id = 0;
    foreach ($valid_targets as $aid) {
        $ins = "INSERT INTO soal (assignment_id, judul_ujian, deskripsi, jenis_ujian, semester, waktu_mulai, waktu_selesai, durasi, tampil_nilai, created_at, updated_at) VALUES (".
               $aid.", '".$judul."', '".$deskripsi."', '".$jenis_ujian."', '".$semester."', '".$waktu_mulai."', '".$waktu_selesai."', ".$durasi.", ".$tampil_nilai.", '".$now."', '".$now."')";
        if (query($ins)) {
            $new_soal_id = last_insert_id();
            if ($first_new_id === 0) { $first_new_id = $new_soal_id; }
            $created++;
            // Clone detail
            if ($detail_src) {
                foreach ($detail_src as $d) {
                    $sqlD = "INSERT INTO detail_soal (soal_id, tipe_soal, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, pilihan_e, gambar_path, gambar_posisi, video_url, video_posisi, jawaban_benar, bobot, urutan, created_at, updated_at) VALUES (".
                            (int)$new_soal_id.
                            ", '".escape_string($d['tipe_soal'])."', '".escape_string($d['pertanyaan'])."', '".escape_string($d['pilihan_a'])."', '".escape_string($d['pilihan_b'])."', '".escape_string($d['pilihan_c'])."', '".escape_string($d['pilihan_d'])."', '".escape_string($d['pilihan_e'])."', ".
                            ($d['gambar_path']!==''?"'".escape_string($d['gambar_path'])."'":"NULL").", ".
                            ($d['gambar_posisi']!==''?"'".escape_string($d['gambar_posisi'])."'":"NULL").", ".
                            ($d['video_url']!==''?"'".escape_string($d['video_url'])."'":"NULL").", ".
                            ($d['video_posisi']!==''?"'".escape_string($d['video_posisi'])."'":"NULL").", '".
                            escape_string($d['jawaban_benar'])."', ".(int)$d['bobot'].", ".(int)$d['urutan'].", '".$now."', '".$now."')";
                    @query($sqlD);
                }
            }
        }
    }

    if ($created > 0) {
        set_flash('success', 'Berhasil menambahkan ujian ke '.(int)$created.' assignment.');
        redirect('index.php?action=builder&soal_id=' . $source_soal_id);
    } else {
        set_flash('error','Gagal menambahkan ke assignment manapun.');
        redirect('index.php?action=builder&soal_id=' . $source_soal_id);
    }
}

// Handle Create Ujian
if ($action === 'create_exam' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $assign_ids = isset($_POST['assignment_ids']) ? array_map('intval', (array)$_POST['assignment_ids']) : [];
    $judul = isset($_POST['judul_ujian']) ? escape_string($_POST['judul_ujian']) : '';
    $deskripsi = isset($_POST['deskripsi']) ? escape_string($_POST['deskripsi']) : '';
    $jenis_ujian = (isset($_POST['jenis_ujian']) && in_array($_POST['jenis_ujian'], ['Harian','UTS','UAS'])) ? $_POST['jenis_ujian'] : 'Harian';
    $semester = (isset($_POST['semester']) && in_array($_POST['semester'], ['Ganjil','Genap'])) ? $_POST['semester'] : '';
    $waktu_mulai = dt_local_to_mysql($_POST['waktu_mulai'] ?? '');
    $waktu_selesai = dt_local_to_mysql($_POST['waktu_selesai'] ?? '');
    $durasi = (int)($_POST['durasi'] ?? 0);
    $tampil_nilai = isset($_POST['tampil_nilai']) ? 1 : 0;

    if (empty($assign_ids)) {
        set_flash('error', 'Pilih minimal satu assignment.');
        redirect('index.php');
    }
    if (!$judul || !$semester || !$waktu_mulai || !$waktu_selesai || $durasi <= 0) {
        set_flash('error', 'Lengkapi judul, semester, jadwal ujian, dan durasi.');
        redirect('index.php');
    }

    @query("ALTER TABLE soal ADD COLUMN IF NOT EXISTS jenis_ujian ENUM('Harian','UTS','UAS') NOT NULL DEFAULT 'Harian' AFTER deskripsi");
    @query("ALTER TABLE soal ADD COLUMN IF NOT EXISTS semester ENUM('Ganjil','Genap') NOT NULL DEFAULT 'Ganjil' AFTER jenis_ujian");
    $now = date('Y-m-d H:i:s');

    // Validasi assignment milik guru
    $ids_str = implode(',', array_unique(array_filter($assign_ids)));
    $valid_ids = [];
    if ($ids_str !== '') {
        $rs = query("SELECT id FROM assignment_guru WHERE guru_id={$guru_id} AND id IN ({$ids_str})");
        while ($row = $rs ? fetch_assoc($rs) : null) { $valid_ids[] = (int)$row['id']; }
    }

    $created = 0; $first_new_id = 0;
    foreach ($valid_ids as $aid) {
        $sql = "INSERT INTO soal (assignment_id, judul_ujian, deskripsi, jenis_ujian, semester, waktu_mulai, waktu_selesai, durasi, tampil_nilai, created_at, updated_at) VALUES ("
             ."{$aid},'{$judul}','{$deskripsi}','".escape_string($jenis_ujian)."','".escape_string($semester)."','{$waktu_mulai}','{$waktu_selesai}',{$durasi},{$tampil_nilai},'{$now}','{$now}')";
        if (query($sql)) {
            $created++;
            if ($first_new_id === 0) { $first_new_id = last_insert_id(); }
        }
    }

    if ($created > 0) {
        set_flash('success', 'Ujian berhasil dibuat pada '.(int)$created.' assignment. Tambahkan butir soal.');
        redirect('index.php?action=builder&soal_id='.$first_new_id);
    } else {
        set_flash('error', 'Gagal membuat ujian.');
        redirect('index.php');
    }
}

// Handle Add Question
if ($action === 'add_question' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $soal_id = (int)($_POST['soal_id'] ?? 0);
    if (!own_soal($guru_id, $soal_id)) { set_flash('error','Akses ditolak'); redirect('index.php'); }

    $tipe = $_POST['tipe_soal'] ?? '';
    $pertanyaan = escape_string($_POST['pertanyaan'] ?? '');
    $gambar_posisi = isset($_POST['gambar_posisi']) ? ($_POST['gambar_posisi']==='atas' || $_POST['gambar_posisi']==='bawah' ? $_POST['gambar_posisi'] : '') : '';
    $gambar_path = '';

    // Upload gambar (opsional)
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $dir = __DIR__ . '/../../assets/uploads/soal/';
            if (!is_dir($dir)) { mkdir($dir, 0777, true); }
            $newName = 'soal-'.$soal_id.'-'.time().'-'.bin2hex(random_bytes(3)).'.'.$ext;
            $dest = $dir.$newName;
            if (move_uploaded_file($_FILES['gambar']['tmp_name'], $dest)) {
                $gambar_path = 'soal/'.$newName; // relative to assets/uploads
            }
        }
    }
    $bobot = (int)($_POST['bobot'] ?? 1);
    $urutan = (int)($_POST['urutan'] ?? 1);

    // Pilihan
    $pilihan = [
        'A' => $_POST['pilihan_a'] ?? '',
        'B' => $_POST['pilihan_b'] ?? '',
        'C' => $_POST['pilihan_c'] ?? '',
        'D' => $_POST['pilihan_d'] ?? '',
      'E' => $_POST['pilihan_e'] ?? '',
    ];

    // Handle optional image uploads for options A-E
    $pilihan_path = [ 'A' => '', 'B' => '', 'C' => '', 'D' => '', 'E' => '' ];
    foreach (['A','B','C','D','E'] as $opt) {
      $field = 'pilihan_'.strtolower($opt).'_img';
      if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
          $dirOpt = __DIR__ . '/../../assets/uploads/soal_options/';
          if (!is_dir($dirOpt)) { @mkdir($dirOpt, 0777, true); }
          $newName = 'soal-'.$soal_id.'-opt-'.strtolower($opt).'-'.time().'-'.bin2hex(random_bytes(3)).'.'.$ext;
          if (@move_uploaded_file($_FILES[$field]['tmp_name'], $dirOpt.$newName)) {
            $pilihan_path[$opt] = 'soal_options/'.$newName; // relative to assets/uploads
          }
        }
      }
    }

    // Jawaban benar
    $jawaban_benar = '';
    if ($tipe === 'pilihan_ganda') {
        $jawaban_benar = strtoupper(trim((string)($_POST['jawaban_pg'] ?? '')));
        if (!in_array($jawaban_benar, ['A','B','C','D','E'])) $jawaban_benar = '';
    } elseif ($tipe === 'pilihan_ganda_kompleks') {
        $arr = isset($_POST['jawaban_pgk']) ? (array)$_POST['jawaban_pgk'] : [];
        $arr = array_map('strtoupper', $arr);
        $arr = array_values(array_intersect($arr, ['A','B','C','D','E']));
        sort($arr);
        $jawaban_benar = implode(',', $arr);
    } elseif ($tipe === 'benar_salah') {
        $jb = strtolower(trim((string)($_POST['jawaban_bs'] ?? '')));
        $jawaban_benar = in_array($jb, ['benar','salah']) ? $jb : '';
    } elseif ($tipe === 'essay') {
        $jawaban_benar = '';
    }

    if ($tipe === 'benar_salah' && $jawaban_benar === '') {
        set_flash('error','Kunci jawaban Benar/Salah wajib diisi.');
        redirect('index.php?action=builder&soal_id='.$soal_id);
    }
    if (!$tipe || !$pertanyaan || $bobot <= 0) {
        set_flash('error','Lengkapi tipe, pertanyaan dan bobot.');
        redirect('index.php?action=builder&soal_id='.$soal_id);
    }

    $now = date('Y-m-d H:i:s');
    // Video (opsional)
    $video_url = '';
    $video_posisi = isset($_POST['video_posisi']) && in_array($_POST['video_posisi'], ['atas','bawah']) ? $_POST['video_posisi'] : '';
    if (!empty($_POST['video_url'])) {
        $video_url = trim($_POST['video_url']);
    } elseif (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $allowedV = ['video/mp4' => 'mp4'];
        $tmpV = $_FILES['video']['tmp_name'];
        $sizeV = (int)$_FILES['video']['size'];
        $typeV = function_exists('mime_content_type') ? mime_content_type($tmpV) : $_FILES['video']['type'];
        if (isset($allowedV[$typeV]) && $sizeV <= 100*1024*1024) {
            $extV = $allowedV[$typeV];
            $dirV = __DIR__ . '/../../assets/uploads/soal_video/';
            if (!is_dir($dirV)) { @mkdir($dirV, 0777, true); }
            $newV = 'soal-'.$soal_id.'-'.time().'-'.bin2hex(random_bytes(3)).'.'.$extV;
            if (@move_uploaded_file($tmpV, $dirV.$newV)) {
                $video_url = 'soal_video/'.$newV; // relative
            }
        }
    }

    // Simpan termasuk gambar/video bila ada; siapkan kolom jika belum ada (idempotent)
    @query("ALTER TABLE detail_soal ADD COLUMN IF NOT EXISTS gambar_path VARCHAR(255) NULL AFTER pilihan_e");
    @query("ALTER TABLE detail_soal ADD COLUMN IF NOT EXISTS gambar_posisi ENUM('atas','bawah') NULL AFTER gambar_path");
    @query("ALTER TABLE detail_soal ADD COLUMN IF NOT EXISTS video_url VARCHAR(255) NULL AFTER gambar_posisi");
    @query("ALTER TABLE detail_soal ADD COLUMN IF NOT EXISTS video_posisi ENUM('atas','bawah') NULL AFTER video_url");
    // opsi gambar per pilihan
    @query("ALTER TABLE detail_soal ADD COLUMN IF NOT EXISTS pilihan_a_path VARCHAR(255) NULL AFTER pilihan_a");
    @query("ALTER TABLE detail_soal ADD COLUMN IF NOT EXISTS pilihan_b_path VARCHAR(255) NULL AFTER pilihan_b");
    @query("ALTER TABLE detail_soal ADD COLUMN IF NOT EXISTS pilihan_c_path VARCHAR(255) NULL AFTER pilihan_c");
    @query("ALTER TABLE detail_soal ADD COLUMN IF NOT EXISTS pilihan_d_path VARCHAR(255) NULL AFTER pilihan_d");
    @query("ALTER TABLE detail_soal ADD COLUMN IF NOT EXISTS pilihan_e_path VARCHAR(255) NULL AFTER pilihan_e");

        $sql = "INSERT INTO detail_soal (soal_id, tipe_soal, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, pilihan_e, pilihan_a_path, pilihan_b_path, pilihan_c_path, pilihan_d_path, pilihan_e_path, jawaban_benar, bobot, urutan, gambar_path, gambar_posisi, video_url, video_posisi, created_at, updated_at) VALUES ("
         ."{$soal_id},'".escape_string($tipe)."','{$pertanyaan}',"
         ."'".escape_string($pilihan['A'])."','".escape_string($pilihan['B'])."','".escape_string($pilihan['C'])."','".escape_string($pilihan['D'])."','".escape_string($pilihan['E'])."',"
          ."'".escape_string($pilihan_path['A'])."','".escape_string($pilihan_path['B'])."','".escape_string($pilihan_path['C'])."','".escape_string($pilihan_path['D'])."','".escape_string($pilihan_path['E'])."',"
          ."'".escape_string($jawaban_benar)."',{$bobot},{$urutan},'".escape_string($gambar_path)."','".escape_string($gambar_posisi)."',"
         .($video_url!==''?"'".escape_string($video_url)."'":"NULL").","
         .($video_posisi!==''?"'".escape_string($video_posisi)."'":"NULL").
         ",'{$now}','{$now}')";
    $ok = query($sql);
    if ($ok) {
        // Tambah kolom referensi master jika belum ada
        @query("ALTER TABLE detail_soal ADD COLUMN IF NOT EXISTS master_soal_id INT NULL AFTER soal_id");
        @query("CREATE INDEX IF NOT EXISTS idx_master_soal_id ON detail_soal(master_soal_id)");

        // Jadikan baris yang baru sebagai master dan replikasi ke ujian lain dengan judul yang sama milik guru
        $master_id = (int)last_insert_id();
        @query("UPDATE detail_soal SET master_soal_id={$master_id} WHERE id={$master_id} LIMIT 1");

        // Cari semua soal lain milik guru dengan judul yang sama (grup multi-kelas)
        $rsTitle = query("SELECT judul_ujian FROM soal WHERE id={$soal_id} LIMIT 1");
        $rowTitle = $rsTitle ? fetch_assoc($rsTitle) : null;
        if ($rowTitle && $rowTitle['judul_ujian'] !== '') {
            $judulLike = escape_string($rowTitle['judul_ujian']);
            $rsTargets = query("SELECT s.id AS sid FROM soal s JOIN assignment_guru ag ON ag.id=s.assignment_id WHERE ag.guru_id={$guru_id} AND s.judul_ujian='{$judulLike}' AND s.id<>{$soal_id}");
            $replicated = 0; $skipped = 0;
            while ($t = $rsTargets ? fetch_assoc($rsTargets) : null) {
                $sid = (int)$t['sid'];
                // Skip jika sudah ada jawaban pada soal target
                $rAtt = query("SELECT 1 FROM jawaban_siswa WHERE soal_id={$sid} LIMIT 1");
                if ($rAtt && fetch_assoc($rAtt)) { $skipped++; continue; }
                $insRep = "INSERT INTO detail_soal (soal_id, master_soal_id, tipe_soal, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, pilihan_e, pilihan_a_path, pilihan_b_path, pilihan_c_path, pilihan_d_path, pilihan_e_path, jawaban_benar, bobot, urutan, gambar_path, gambar_posisi, video_url, video_posisi, created_at, updated_at) VALUES ("
                  .$sid.", {$master_id}, '".escape_string($tipe)."', '{$pertanyaan}', '".escape_string($pilihan['A'])."', '".escape_string($pilihan['B'])."', '".escape_string($pilihan['C'])."', '".escape_string($pilihan['D'])."', '".escape_string($pilihan['E'])."', '".escape_string($pilihan_path['A'])."','".escape_string($pilihan_path['B'])."','".escape_string($pilihan_path['C'])."','".escape_string($pilihan_path['D'])."','".escape_string($pilihan_path['E'])."', '".escape_string($jawaban_benar)."', {$bobot}, {$urutan}, "
                  .($gambar_path!==''?"'".escape_string($gambar_path)."'":"NULL").", "
                  .($gambar_posisi!==''?"'".escape_string($gambar_posisi)."'":"NULL").", "
                  .($video_url!==''?"'".escape_string($video_url)."'":"NULL").", "
                  .($video_posisi!==''?"'".escape_string($video_posisi)."'":"NULL").
                  ", '".$now."', '".$now."')";
                if (query($insRep)) { $replicated++; }
            }
            if ($replicated>0 || $skipped>0) {
                set_flash('success', 'Butir soal ditambahkan (master) dan direplikasi: '.$replicated.' kelas'.($skipped>0?'; dilewati '.$skipped.' kelas karena sudah ada jawaban.':''));
            }
        }

        // Auto-simpan ke Bank Soal untuk semua butir
        @query("CREATE TABLE IF NOT EXISTS bank_soal (
          id INT AUTO_INCREMENT PRIMARY KEY,
          guru_id INT NOT NULL,
          mapel_id INT NULL,
          tipe_soal VARCHAR(64) NOT NULL,
          pertanyaan TEXT NOT NULL,
          pilihan_a TEXT NULL,
          pilihan_b TEXT NULL,
          pilihan_c TEXT NULL,
          pilihan_d TEXT NULL,
          pilihan_e TEXT NULL,
          pilihan_a_path VARCHAR(255) NULL,
          pilihan_b_path VARCHAR(255) NULL,
          pilihan_c_path VARCHAR(255) NULL,
          pilihan_d_path VARCHAR(255) NULL,
          pilihan_e_path VARCHAR(255) NULL,
          jawaban_benar VARCHAR(255) NULL,
          bobot INT NOT NULL DEFAULT 1,
          gambar_path VARCHAR(255) NULL,
          gambar_posisi ENUM('atas','bawah') NULL,
          video_url VARCHAR(255) NULL,
          video_posisi ENUM('atas','bawah') NULL,
          created_at DATETIME NULL,
          updated_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Pastikan kolom pilihan_*_path ada pada bank_soal (untuk instalasi lama)
        @query("ALTER TABLE bank_soal ADD COLUMN IF NOT EXISTS pilihan_a_path VARCHAR(255) NULL AFTER pilihan_a");
        @query("ALTER TABLE bank_soal ADD COLUMN IF NOT EXISTS pilihan_b_path VARCHAR(255) NULL AFTER pilihan_b");
        @query("ALTER TABLE bank_soal ADD COLUMN IF NOT EXISTS pilihan_c_path VARCHAR(255) NULL AFTER pilihan_c");
        @query("ALTER TABLE bank_soal ADD COLUMN IF NOT EXISTS pilihan_d_path VARCHAR(255) NULL AFTER pilihan_d");
        @query("ALTER TABLE bank_soal ADD COLUMN IF NOT EXISTS pilihan_e_path VARCHAR(255) NULL AFTER pilihan_e");

        $rsm = query("SELECT ag.mapel_id FROM soal s JOIN assignment_guru ag ON ag.id=s.assignment_id WHERE s.id={$soal_id} LIMIT 1");
        $rsrow = $rsm ? fetch_assoc($rsm) : null;
        $mapel_id = $rsrow ? (int)$rsrow['mapel_id'] : null;
        $now = date('Y-m-d H:i:s');
        $insBank = "INSERT INTO bank_soal (guru_id, mapel_id, tipe_soal, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, pilihan_e, jawaban_benar, bobot, gambar_path, gambar_posisi, video_url, video_posisi, pilihan_a_path, pilihan_b_path, pilihan_c_path, pilihan_d_path, pilihan_e_path, created_at, updated_at) VALUES ("
                 ."{$guru_id},".($mapel_id!==null?$mapel_id:"NULL").",'".escape_string($tipe)."','{$pertanyaan}','".escape_string($pilihan['A'])."','".escape_string($pilihan['B'])."','".escape_string($pilihan['C'])."','".escape_string($pilihan['D'])."','".escape_string($pilihan['E'])."','".escape_string($jawaban_benar)."',{$bobot},"
                 .($gambar_path!==''?"'".escape_string($gambar_path)."'":"NULL").",".
                  ($gambar_posisi!==''?"'".escape_string($gambar_posisi)."'":"NULL").",".
                  ($video_url!==''?"'".escape_string($video_url)."'":"NULL").",".
                  ($video_posisi!==''?"'".escape_string($video_posisi)."'":"NULL").",".
                  "'".escape_string($pilihan_path['A'])."','".escape_string($pilihan_path['B'])."','".escape_string($pilihan_path['C'])."','".escape_string($pilihan_path['D'])."','".escape_string($pilihan_path['E'])."' ,'{$now}','{$now}')";
        @query($insBank);
        set_flash('success','Butir soal ditambahkan (tersimpan ke Bank Soal)');
    } else {
        set_flash('error','Gagal menambahkan butir soal');
    }
    redirect('index.php?action=builder&soal_id='.$soal_id);
    if (query($sql)) {
        set_flash('success','Butir soal ditambahkan');
    } else {
        set_flash('error','Gagal menambahkan butir soal');
    }
    redirect('index.php?action=builder&soal_id='.$soal_id);
}

// Handle Delete Exam (non-force)
if ($action === 'delete_exam' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if (!own_soal($guru_id, $id)) { set_flash('error','Akses ditolak'); redirect('index.php'); }
    $r = query("SELECT 1 FROM jawaban_siswa WHERE soal_id={$id} LIMIT 1");
    if ($r && fetch_assoc($r)) {
        set_flash('error','Tidak dapat menghapus: Sudah ada jawaban siswa. Gunakan Hapus Paksa jika perlu.');
        redirect('index.php');
    }
    // Hapus turunan minimal (tidak menyentuh bank_soal)
    @query("DELETE FROM exam_access_siswa WHERE soal_id={$id}");
    @query("DELETE FROM exam_attempts WHERE soal_id={$id}");
    @query("DELETE FROM log_ujian WHERE soal_id={$id}");
    query("DELETE FROM detail_soal WHERE soal_id={$id}");
    query("DELETE FROM soal WHERE id={$id} LIMIT 1");
    set_flash('success','Ujian berhasil dihapus.');
    redirect('index.php');
}

// Handle Force Delete Exam (hapus paksa: pertahankan bank_soal)
if ($action === 'delete_force' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if (!own_soal($guru_id, $id)) { set_flash('error','Akses ditolak'); redirect('index.php'); }
    // Hapus semua data ujian ini kecuali bank_soal
    @query("DELETE FROM jawaban_siswa WHERE soal_id={$id}");
    @query("DELETE FROM log_ujian WHERE soal_id={$id}");
    @query("DELETE FROM exam_attempts WHERE soal_id={$id}");
    @query("DELETE FROM exam_access_siswa WHERE soal_id={$id}");
    query("DELETE FROM detail_soal WHERE soal_id={$id}");
    query("DELETE FROM soal WHERE id={$id} LIMIT 1");
    set_flash('success','Ujian dihapus paksa. Bank soal tetap tersimpan.');
    redirect('index.php');
}

// Handle Delete Question (sinkron multi-kelas berbasis master_soal_id)
if ($action === 'delete_question' && isset($_GET['id']) && isset($_GET['soal_id'])) {
    $id = (int)$_GET['id'];
    $soal_id = (int)$_GET['soal_id'];
    if (!own_soal($guru_id, $soal_id)) { set_flash('error','Akses ditolak'); redirect('index.php'); }

    @query("ALTER TABLE detail_soal ADD COLUMN IF NOT EXISTS master_soal_id INT NULL AFTER soal_id");

    // Ambil info butir + master id
    $q = query("SELECT ds.*, s.judul_ujian FROM detail_soal ds JOIN soal s ON s.id=ds.soal_id WHERE ds.id={$id} AND ds.soal_id={$soal_id} LIMIT 1");
    $d = $q ? fetch_assoc($q) : null;
    if (!$d) { set_flash('error','Butir tidak ditemukan'); redirect('index.php?action=builder&soal_id='.$soal_id); }

    $mid = (int)($d['master_soal_id'] ?: 0);
    if ($mid === 0) { $mid = (int)$id; }
    $judul = escape_string($d['judul_ujian']);

    // Kumpulkan target soal lainnya (judul sama, milik guru)
    $rsTargets = query("SELECT s.id AS sid FROM soal s JOIN assignment_guru ag ON ag.id=s.assignment_id WHERE ag.guru_id={$guru_id} AND s.judul_ujian='{$judul}'");
    $deleted = 0; $skipped = 0;
    while ($t = $rsTargets ? fetch_assoc($rsTargets) : null) {
        $sid = (int)$t['sid'];
        // Skip jika sudah ada jawaban pada soal target
        $rAtt = query("SELECT 1 FROM jawaban_siswa WHERE soal_id={$sid} LIMIT 1");
        if ($rAtt && fetch_assoc($rAtt)) { $skipped++; continue; }
        if ($sid === $soal_id && (int)$id === $mid) {
            // hapus master baris
            query("DELETE FROM detail_soal WHERE id={$mid} AND soal_id={$sid} LIMIT 1");
            $deleted++;
        } else {
            // hapus replika berdasar master_soal_id
            $ok = query("DELETE FROM detail_soal WHERE soal_id={$sid} AND master_soal_id={$mid}");
            if ($ok) { $deleted++; }
        }
    }
    if ($deleted>0 || $skipped>0) {
        set_flash('success', 'Butir soal dihapus pada '.$deleted.' kelas'.($skipped>0?'; dilewati '.$skipped.' kelas karena sudah ada jawaban.':''));
    } else {
        set_flash('success','Butir soal dihapus');
    }
    redirect('index.php?action=builder&soal_id='.$soal_id);
}

// Note: 'save_to_bank' action removed â€” bank entries are handled automatically on create

// Grant remedial (tambah allowed_attempts)
if ($action === 'grant_remedial' && $_SERVER['REQUEST_METHOD']==='POST') {
    $soal_id = (int)($_POST['soal_id'] ?? 0);
    $siswa_id = (int)($_POST['siswa_id'] ?? 0);
    if (!$soal_id || !$siswa_id || !own_soal($guru_id, $soal_id)) { set_flash('error','Akses ditolak'); redirect('index.php'); }
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
    $now = date('Y-m-d H:i:s');
    $ins = "INSERT INTO exam_attempts (soal_id, siswa_id, allowed_attempts, used_attempts, created_at, updated_at) VALUES ({$soal_id},{$siswa_id},2,0,'{$now}','{$now}') ON DUPLICATE KEY UPDATE allowed_attempts=allowed_attempts+1, updated_at='{$now}'";
    if (query($ins)) set_flash('success','Remedial ditambahkan untuk siswa ID '.$siswa_id); else set_flash('error','Gagal menambah remedial');
    redirect('index.php?action=builder&soal_id='.$soal_id);
}

// Edit question form
if ($action === 'edit_question' && isset($_GET['id']) && isset($_GET['soal_id'])) {
    $id = (int)$_GET['id'];
    $soal_id = (int)$_GET['soal_id'];
    if (!own_soal($guru_id, $soal_id)) { set_flash('error','Akses ditolak'); redirect('index.php'); }
    $q = query("SELECT * FROM detail_soal WHERE id={$id} AND soal_id={$soal_id} LIMIT 1");
    $d = $q ? fetch_assoc($q) : null;
    if (!$d) { set_flash('error','Butir soal tidak ditemukan'); redirect('index.php?action=builder&soal_id='.$soal_id); }
    $flash = get_flash();
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Butir Soal</title>
  <link rel="stylesheet" href="../../assets/css/style.css">
  <style>
    .container{max-width:1000px;margin:16px auto;padding:12px}
    .card{background:#fff;border:1px solid #ddd;border-radius:10px;margin-bottom:14px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05)}
    .card-header{padding:14px 18px;background:#f5f7fb;font-weight:600}
    .card-body{padding:18px}
    .row{display:flex;gap:14px;flex-wrap:wrap}
    .row .col{flex:1 1 280px}
    label{display:block;margin-bottom:6px;font-weight:600}
    input[type=text],input[type=number],select,textarea{width:100%;padding:12px;border:1px solid #ced4da;border-radius:8px;box-sizing:border-box}
    textarea{min-height:120px}
    .btn{display:inline-block;padding:10px 14px;border-radius:8px;border:1px solid #2c7be5;background:#2c7be5;color:#fff;text-decoration:none}
    .btn-danger{background:#d9534f;border-color:#d9534f}
  </style>
</head>
<body>
<div class="container">
  <div class="card">
    <div class="card-header">Edit Butir Soal</div>
    <div class="card-body">
    <div style="margin-bottom:12px"><a class="btn back-btn" href="index.php?action=builder&soal_id=<?php echo (int)$soal_id; ?>" onclick="var href=this.getAttribute('href'); document.body.style.transition='opacity .22s'; document.body.style.opacity=0; setTimeout(function(){ if (history.length>1) { history.back(); } else { window.location=href; } },220); return false;"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali</a></div>
      <?php if ($flash): ?><div class="alert alert-<?php echo htmlspecialchars(($flash['type']) ?? ''); ?>"><?php echo htmlspecialchars(($flash['message']) ?? ''); ?></div><?php endif; ?>
      <form method="post" action="index.php?action=update_question" enctype="multipart/form-data">
        <input type="hidden" name="soal_id" value="<?php echo (int)$soal_id; ?>" />
        <input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>" />
        <div class="row">
          <div class="col">
            <label>Tipe Soal</label>
            <select name="tipe_soal" id="tipe_soal_edit" required onchange="onTypeChangeEdit(this.value)">
              <?php $tipe=$d['tipe_soal']; ?>
              <option value="pilihan_ganda" <?php echo $tipe==='pilihan_ganda'?'selected':''; ?>>Pilihan Ganda</option>
              <option value="pilihan_ganda_kompleks" <?php echo $tipe==='pilihan_ganda_kompleks'?'selected':''; ?>>Pilihan Ganda Kompleks</option>
              <option value="benar_salah" <?php echo $tipe==='benar_salah'?'selected':''; ?>>Benar/Salah</option>
              <option value="essay" <?php echo $tipe==='essay'?'selected':''; ?>>Essay</option>
            </select>
          </div>
          <div class="col">
            <label>Bobot</label>
            <input type="number" name="bobot" value="<?php echo (int)$d['bobot']; ?>" min="1" required />
          </div>
          <div class="col">
            <label>Urutan</label>
            <input type="number" name="urutan" value="<?php echo (int)$d['urutan']; ?>" min="1" required />
          </div>
        </div>
        <div>
          <label>Pertanyaan</label>
          <textarea name="pertanyaan" rows="4" required><?php echo htmlspecialchars(($d['pertanyaan']) ?? ''); ?></textarea>
        </div>
        <div class="row" id="opsi-row">
          <div class="col">
            <label>Pilihan A (teks atau gambar)</label>
            <input type="text" name="pilihan_a" value="<?php echo htmlspecialchars(($d['pilihan_a']) ?? ''); ?>" />
            <input type="file" name="pilihan_a_img" accept="image/*" style="margin-top:6px" />
            <?php if (!empty($d['pilihan_a_path'])): ?>
              <div style="margin-top:6px"><img src="../../assets/uploads/<?php echo htmlspecialchars(($d['pilihan_a_path']) ?? ''); ?>" alt="Pilihan A" style="max-width:150px;border:1px solid #eee;border-radius:6px"></div>
            <?php endif; ?>
          </div>
          <div class="col">
            <label>Pilihan B (teks atau gambar)</label>
            <input type="text" name="pilihan_b" value="<?php echo htmlspecialchars(($d['pilihan_b']) ?? ''); ?>" />
            <input type="file" name="pilihan_b_img" accept="image/*" style="margin-top:6px" />
            <?php if (!empty($d['pilihan_b_path'])): ?>
              <div style="margin-top:6px"><img src="../../assets/uploads/<?php echo htmlspecialchars(($d['pilihan_b_path']) ?? ''); ?>" alt="Pilihan B" style="max-width:150px;border:1px solid #eee;border-radius:6px"></div>
            <?php endif; ?>
          </div>
          <div class="col">
            <label>Pilihan C (teks atau gambar)</label>
            <input type="text" name="pilihan_c" value="<?php echo htmlspecialchars(($d['pilihan_c']) ?? ''); ?>" />
            <input type="file" name="pilihan_c_img" accept="image/*" style="margin-top:6px" />
            <?php if (!empty($d['pilihan_c_path'])): ?>
              <div style="margin-top:6px"><img src="../../assets/uploads/<?php echo htmlspecialchars(($d['pilihan_c_path']) ?? ''); ?>" alt="Pilihan C" style="max-width:150px;border:1px solid #eee;border-radius:6px"></div>
            <?php endif; ?>
          </div>
          <div class="col">
            <label>Pilihan D (teks atau gambar)</label>
            <input type="text" name="pilihan_d" value="<?php echo htmlspecialchars(($d['pilihan_d']) ?? ''); ?>" />
            <input type="file" name="pilihan_d_img" accept="image/*" style="margin-top:6px" />
            <?php if (!empty($d['pilihan_d_path'])): ?>
              <div style="margin-top:6px"><img src="../../assets/uploads/<?php echo htmlspecialchars(($d['pilihan_d_path']) ?? ''); ?>" alt="Pilihan D" style="max-width:150px;border:1px solid #eee;border-radius:6px"></div>
            <?php endif; ?>
          </div>
          <div class="col">
            <label>Pilihan E (teks atau gambar)</label>
            <input type="text" name="pilihan_e" value="<?php echo htmlspecialchars(($d['pilihan_e']) ?? ''); ?>" />
            <input type="file" name="pilihan_e_img" accept="image/*" style="margin-top:6px" />
            <?php if (!empty($d['pilihan_e_path'])): ?>
              <div style="margin-top:6px"><img src="../../assets/uploads/<?php echo htmlspecialchars(($d['pilihan_e_path']) ?? ''); ?>" alt="Pilihan E" style="max-width:150px;border:1px solid #eee;border-radius:6px"></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="row">
          <div class="col">
            <label>Kunci Jawaban</label>
            <div id="kunci-pg-edit">
              <select name="jawaban_pg_edit">
                <option value="">- pilih -</option>
                <option value="A" <?php echo strtoupper($d['jawaban_benar'])==='A'?'selected':''; ?>>A</option>
                <option value="B" <?php echo strtoupper($d['jawaban_benar'])==='B'?'selected':''; ?>>B</option>
                <option value="C" <?php echo strtoupper($d['jawaban_benar'])==='C'?'selected':''; ?>>C</option>
                <option value="D" <?php echo strtoupper($d['jawaban_benar'])==='D'?'selected':''; ?>>D</option>
                <option value="E" <?php echo strtoupper($d['jawaban_benar'])==='E'?'selected':''; ?>>E</option>
              </select>
            </div>
            <div id="kunci-pgk-edit" style="display:none">
              <?php $sel_pgk = array_map('trim', explode(',', strtoupper($d['jawaban_benar']))); ?>
              <div>
                <label><input type="checkbox" name="jawaban_pgk_edit[]" value="A" <?php echo in_array('A',$sel_pgk)?'checked':''; ?>> A</label>
                <label><input type="checkbox" name="jawaban_pgk_edit[]" value="B" <?php echo in_array('B',$sel_pgk)?'checked':''; ?>> B</label>
                <label><input type="checkbox" name="jawaban_pgk_edit[]" value="C" <?php echo in_array('C',$sel_pgk)?'checked':''; ?>> C</label>
                <label><input type="checkbox" name="jawaban_pgk_edit[]" value="D" <?php echo in_array('D',$sel_pgk)?'checked':''; ?>> D</label>
                <label><input type="checkbox" name="jawaban_pgk_edit[]" value="E" <?php echo in_array('E',$sel_pgk)?'checked':''; ?>> E</label>
              </div>
            </div>
            <div id="kunci-bs-edit" style="display:none">
              <select name="jawaban_bs_edit" id="jawaban_bs_edit">
                <option value="">- pilih -</option>
                <option value="benar" <?php echo strtolower($d['jawaban_benar'])==='benar'?'selected':''; ?>>Benar</option>
                <option value="salah" <?php echo strtolower($d['jawaban_benar'])==='salah'?'selected':''; ?>>Salah</option>
              </select>
            </div>
          </div>
          <div class="col">
            <label>Gambar (opsional)</label>
            <input type="file" name="gambar" accept="image/*" />
            <?php if(!empty($d['gambar_path'])): ?><div style="margin-top:6px"><img src="../../assets/uploads/<?php echo htmlspecialchars(($d['gambar_path']) ?? ''); ?>" style="max-width:150px;border:1px solid #eee;border-radius:6px"/></div><?php endif; ?>
          </div>
          <div class="col">
            <label>Posisi Gambar</label>
            <select name="gambar_posisi">
              <?php $gp = $d['gambar_posisi']; ?>
              <option value="">-</option>
              <option value="atas" <?php echo $gp==='atas'?'selected':''; ?>>Atas</option>
              <option value="bawah" <?php echo $gp==='bawah'?'selected':''; ?>>Bawah</option>
            </select>
          </div>
        </div>
        <div style="margin-top:12px">
          <button class="btn" type="submit">Simpan Perubahan</button>
          <a class="btn btn-danger" href="index.php?action=builder&soal_id=<?php echo (int)$soal_id; ?>">Batal</a>
        </div>
      </form>
      <script>
        function onTypeChangeEdit(val){
          try {
            var sel = document.getElementById('tipe_soal_edit');
            var form = sel ? sel.closest('form') : null;
            if (window.syncQuestionTypeControls) { window.syncQuestionTypeControls(val, form); }
          } catch(e){}
        }
        document.addEventListener('DOMContentLoaded', function(){
          var sel = document.getElementById('tipe_soal_edit');
          try { if (sel && window.syncQuestionTypeControls) { window.syncQuestionTypeControls(sel.value, sel.closest('form')); } } catch(e){}
        });
      </script>
    </div>
  </div>
</div>
</body>
</html>
<?php
    exit;
}

// Update question handler
if ($action === 'update_question' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $soal_id = (int)($_POST['soal_id'] ?? 0);
    $id = (int)($_POST['id'] ?? 0);
    if (!$soal_id || !$id || !own_soal($guru_id, $soal_id)) { set_flash('error','Akses ditolak'); redirect('index.php'); }

    @query("ALTER TABLE detail_soal ADD COLUMN IF NOT EXISTS master_soal_id INT NULL AFTER soal_id");

    $tipe = escape_string($_POST['tipe_soal'] ?? '');
    $pertanyaan = escape_string($_POST['pertanyaan'] ?? '');
    $bobot = (int)($_POST['bobot'] ?? 1);
    $urutan = (int)($_POST['urutan'] ?? 1);
    $pilihan = [
      'pilihan_a' => escape_string($_POST['pilihan_a'] ?? ''),
      'pilihan_b' => escape_string($_POST['pilihan_b'] ?? ''),
      'pilihan_c' => escape_string($_POST['pilihan_c'] ?? ''),
      'pilihan_d' => escape_string($_POST['pilihan_d'] ?? ''),
      'pilihan_e' => escape_string($_POST['pilihan_e'] ?? '')
    ];
    // Tentukan kunci jawaban berdasarkan tipe soal (edit form)
    $jawaban_benar_raw = '';
    if ($tipe === 'pilihan_ganda') {
        $jawaban_benar_raw = strtoupper(trim((string)($_POST['jawaban_pg'] ?? $_POST['jawaban_pg_edit'] ?? '')));
        if (!in_array($jawaban_benar_raw, ['A','B','C','D','E'])) $jawaban_benar_raw = '';
    } elseif ($tipe === 'pilihan_ganda_kompleks') {
        $arr = $_POST['jawaban_pgk'] ?? $_POST['jawaban_pgk_edit'] ?? [];
        if (!is_array($arr)) { $arr = [$arr]; }
        $arr = array_map(function($v){ return strtoupper(trim((string)$v)); }, $arr);
        $arr = array_values(array_intersect($arr, ['A','B','C','D','E']));
        sort($arr);
        $jawaban_benar_raw = implode(',', $arr);
    } elseif ($tipe === 'benar_salah') {
        $jb = strtolower(trim((string)($_POST['jawaban_bs'] ?? $_POST['jawaban_bs_edit'] ?? '')));
        $jawaban_benar_raw = in_array($jb, ['benar','salah']) ? $jb : '';
    } else {
        $jawaban_benar_raw = '';
    }
    $jawaban_benar = escape_string($jawaban_benar_raw);
    $gambar_posisi = isset($_POST['gambar_posisi']) && in_array($_POST['gambar_posisi'], ['atas','bawah']) ? $_POST['gambar_posisi'] : '';

    // pastikan kolom pilihan_*_path ada
    @query("ALTER TABLE detail_soal ADD COLUMN IF NOT EXISTS pilihan_a_path VARCHAR(255) NULL AFTER pilihan_a");
    @query("ALTER TABLE detail_soal ADD COLUMN IF NOT EXISTS pilihan_b_path VARCHAR(255) NULL AFTER pilihan_b");
    @query("ALTER TABLE detail_soal ADD COLUMN IF NOT EXISTS pilihan_c_path VARCHAR(255) NULL AFTER pilihan_c");
    @query("ALTER TABLE detail_soal ADD COLUMN IF NOT EXISTS pilihan_d_path VARCHAR(255) NULL AFTER pilihan_d");
    @query("ALTER TABLE detail_soal ADD COLUMN IF NOT EXISTS pilihan_e_path VARCHAR(255) NULL AFTER pilihan_e");

    // ambil current paths untuk preservasi jika tidak ada upload baru
    $cur = fetch_assoc(query("SELECT pilihan_a_path, pilihan_b_path, pilihan_c_path, pilihan_d_path, pilihan_e_path FROM detail_soal WHERE id={$id} AND soal_id={$soal_id} LIMIT 1"));
    $pilihan_existing = [
      'A' => $cur['pilihan_a_path'] ?? '',
      'B' => $cur['pilihan_b_path'] ?? '',
      'C' => $cur['pilihan_c_path'] ?? '',
      'D' => $cur['pilihan_d_path'] ?? '',
      'E' => $cur['pilihan_e_path'] ?? ''
    ];

    // upload gambar bila ada (pertanyaan)
    $set_gambar = '';
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $dir = __DIR__ . '/../../assets/uploads/soal/';
            if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
            $newName = 'soal-'.$soal_id.'-'.time().'-'.bin2hex(random_bytes(3)).'.'.$ext;
            if (@move_uploaded_file($_FILES['gambar']['tmp_name'], $dir.$newName)) {
                $set_gambar = ", gambar_path='".escape_string('soal/'.$newName)."'";
            }
        }
    }

    // proses upload gambar opsi A-E jika ada
    $pilihan_path_updates = [];
    foreach (['A','B','C','D','E'] as $opt) {
      $field = 'pilihan_'.strtolower($opt).'_img';
      if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
          $dirOpt = __DIR__ . '/../../assets/uploads/soal_options/';
          if (!is_dir($dirOpt)) { @mkdir($dirOpt, 0777, true); }
          $newName = 'soal-'.$soal_id.'-opt-'.strtolower($opt).'-'.time().'-'.bin2hex(random_bytes(3)).'.'.$ext;
          if (@move_uploaded_file($_FILES[$field]['tmp_name'], $dirOpt.$newName)) {
            $pilihan_path_updates[$opt] = 'soal_options/'.$newName;
          }
        }
      }
    }

    // tentukan final pilihan_*_path (preserve jika tidak diupload baru)
    $final_pilihan_paths = [
      'A' => $pilihan_existing['A'],
      'B' => $pilihan_existing['B'],
      'C' => $pilihan_existing['C'],
      'D' => $pilihan_existing['D'],
      'E' => $pilihan_existing['E']
    ];
    foreach ($pilihan_path_updates as $k=>$v) { $final_pilihan_paths[$k] = $v; }

    // build part untuk update pilihan path
    $setPathsParts = [];
    foreach (['A','B','C','D','E'] as $opt) {
        $col = 'pilihan_'.strtolower($opt).'_path';
        $val = isset($final_pilihan_paths[$opt]) && $final_pilihan_paths[$opt] !== '' ? "'".escape_string($final_pilihan_paths[$opt])."'" : "NULL";
        $setPathsParts[] = "{$col}={$val}";
    }
    $setPathsSql = count($setPathsParts) ? (', '.implode(', ', $setPathsParts)) : '';

    $sql = "UPDATE detail_soal SET tipe_soal='{$tipe}', pertanyaan='{$pertanyaan}', bobot={$bobot}, urutan={$urutan}, ".
           "pilihan_a='{$pilihan['pilihan_a']}', pilihan_b='{$pilihan['pilihan_b']}', pilihan_c='{$pilihan['pilihan_c']}', pilihan_d='{$pilihan['pilihan_d']}', pilihan_e='{$pilihan['pilihan_e']}', ".
           "jawaban_benar='{$jawaban_benar}', gambar_posisi='".escape_string($gambar_posisi)."'{$set_gambar}{$setPathsSql}, updated_at='".date('Y-m-d H:i:s')."' WHERE id={$id} AND soal_id={$soal_id} LIMIT 1";

    if (query($sql)) {
        // Sinkronisasi update ke semua replika (judul sama) berbasis master_soal_id
        // Ambil master id dan judul
        $rowD = fetch_assoc(query("SELECT ds.master_soal_id, s.judul_ujian FROM detail_soal ds JOIN soal s ON s.id=ds.soal_id WHERE ds.id={$id} AND ds.soal_id={$soal_id} LIMIT 1"));
        $mid = $rowD && (int)$rowD['master_soal_id']>0 ? (int)$rowD['master_soal_id'] : (int)$id;
        $judul = $rowD ? escape_string($rowD['judul_ujian']) : '';
        if ($judul !== '') {
            $rsTargets = query("SELECT s.id AS sid FROM soal s JOIN assignment_guru ag ON ag.id=s.assignment_id WHERE ag.guru_id={$guru_id} AND s.judul_ujian='{$judul}'");
            $updated = 0; $skipped=0;
            while ($t = $rsTargets ? fetch_assoc($rsTargets) : null) {
                $sid = (int)$t['sid'];
                if ($sid === $soal_id) { $updated++; continue; }
                // Skip jika sudah ada jawaban pada soal target
                $rAtt = query("SELECT 1 FROM jawaban_siswa WHERE soal_id={$sid} LIMIT 1");
                if ($rAtt && fetch_assoc($rAtt)) { $skipped++; continue; }
                $sqlU = "UPDATE detail_soal SET tipe_soal='{$tipe}', pertanyaan='{$pertanyaan}', bobot={$bobot}, urutan={$urutan}, "
                      ."pilihan_a='{$pilihan['pilihan_a']}', pilihan_b='{$pilihan['pilihan_b']}', pilihan_c='{$pilihan['pilihan_c']}', pilihan_d='{$pilihan['pilihan_d']}', pilihan_e='{$pilihan['pilihan_e']}', "
                      ."jawaban_benar='{$jawaban_benar}', gambar_posisi='".escape_string($gambar_posisi)."'".
                      ($set_gambar!==''?str_replace("UPDATE detail_soal SET","",$set_gambar):''). // ignore for replicas unless new image uploaded
                      ", updated_at='".date('Y-m-d H:i:s')."' WHERE soal_id={$sid} AND master_soal_id={$mid} LIMIT 1";
                @query($sqlU);
                $updated++;
            }
            if ($updated>0 || $skipped>0) {
                set_flash('success','Butir soal diperbarui (sinkron): '.$updated.' kelas'.($skipped>0?'; dilewati '.$skipped.' kelas karena sudah ada jawaban.':''));
            } else {
                set_flash('success','Butir soal diperbarui');
            }
        } else {
            set_flash('success','Butir soal diperbarui');
        }
    } else {
        set_flash('error','Gagal memperbarui butir soal');
    }
    redirect('index.php?action=builder&soal_id='.$soal_id);
}

$flash = get_flash();

// Builder view
if ($action === 'builder' && isset($_GET['soal_id'])) {
    $soal_id = (int)$_GET['soal_id'];
    if (!own_soal($guru_id, $soal_id)) { set_flash('error','Akses ditolak'); redirect('index.php'); }
    $soal = get_soal($guru_id, $soal_id);
    $detail = get_detail_soal($soal_id);
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Builder Ujian - <?php echo htmlspecialchars(($soal['judul_ujian']) ?? ''); ?></title>
  <link rel="stylesheet" href="../../assets/css/style.css">
  <style>
    .container{max-width:1100px;margin:16px auto;padding:12px}
    .card{background:#fff;border:1px solid #ddd;border-radius:10px;margin-bottom:14px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05)}
    .card-header{padding:14px 18px;background:#f5f7fb;font-weight:600}
    .card-body{padding:18px}
    .grid{display:grid;grid-template-columns:1fr;gap:14px}
    .table{width:100%;min-width:0;border-collapse:collapse}
    .table th,.table td{border:1px solid #e5e5e5;padding:12px}
    .table th{background:#fafafa;text-align:left}
    /* Column sizing to keep builder table proportional */
    .table thead th:nth-child(1){width:70px;text-align:center}
    .table thead th:nth-child(2){width:70px;text-align:center}
    .table thead th:nth-child(3){width:120px;text-align:center}
    .table thead th:nth-child(4){min-width:360px}
    .table thead th:nth-child(5){width:120px;text-align:center}
    .table thead th:nth-child(6){width:100px;text-align:center}
    .btn{display:inline-block;padding:10px 14px;border-radius:8px;border:1px solid #2c7be5;background:#2c7be5;color:#fff;cursor:pointer;text-decoration:none}
    .btn:hover{filter:brightness(0.95)}
    .text-link{color:#2c7be5;text-decoration:none;padding:0;margin-right:12px;font-weight:600}
    .text-link:hover{text-decoration:underline}
    /* make table action buttons inline and compact */
    .table td .btn{display:inline-block;margin-right:8px;padding:6px 8px;font-size:13px}
    /* Ensure .text-link also applies to button elements (remove default button background) */
    button.text-link, .text-link { background: none !important; border: none !important; padding: 0 !important; margin: 0 12px 0 0; color: #2c7be5; cursor: pointer; }
    /* Force action buttons inside the actions cell to be inline and not stack */
    .table td.actions > a.btn, .table td.actions > button.btn { display: inline-flex !important; flex: 0 0 auto !important; align-items: center; justify-content: center; margin: 0 6px; padding: 6px 8px !important; height: 36px; }
    /* Align action header to right */
    .table thead th:last-child { text-align: right; }
    .btn-danger{background:#d9534f;border-color:#d9534f}
    .row{display:flex;gap:14px;flex-wrap:wrap}
    .row .col{flex:1 1 280px}
    label{display:block;margin-bottom:6px;font-weight:600}
    input[type=text],input[type=number],input[type=datetime-local],select,textarea{width:100%;padding:12px;border:1px solid #ced4da;border-radius:8px;background:#fff;box-sizing:border-box}
    textarea{min-height:120px}
    input:focus,select:focus,textarea:focus{outline:none;border-color:#2c7be5;box-shadow:0 0 0 3px rgba(44,123,229,.15)}
    .alert{padding:12px;border-radius:8px;margin-bottom:12px}
    .alert-error{background:#fdecea;color:#b00020}
    .alert-success{background:#e6f4ea;color:#1e7e34}
    .scroll-x{overflow-x:auto;padding-bottom:16px}
    .header-row{margin:16px 0;align-items:center}
    .header-row .btn{margin-left:16px}
    /* Responsive adjustments: allow action icons to wrap and reduce sizes on smaller screens */
    @media (max-width: 768px) {
      .table th, .table td { padding: 10px; }
      .table thead th:nth-child(1), .table thead th:nth-child(2), .table thead th:nth-child(3), .table thead th:nth-child(5), .table thead th:nth-child(6) { width: auto; }
      .table thead th:nth-child(4) { min-width: 220px; }
      .table td.actions { flex-wrap: wrap; gap: 6px; padding-right: 12px; }
      .table td.actions .btn { width: 32px !important; height: 32px !important; }
    }
    @media (max-width: 576px) {
      .table th, .table td { padding: 8px; font-size: 13px; }
      .table td.actions { justify-content: flex-start; }
      .table td.actions .btn { width: 30px !important; height: 30px !important; }
      .table thead th:nth-child(4) { min-width: 180px; }
    }
    /* Tampilkan tombol aksi sebagai ikon kecil berjajar horizontal (memakai pseudo-icon global) */
    .table th:last-child, .table td:last-child { width: auto; text-align: right; }
    .table td:last-child { vertical-align: middle; }
    .table td.actions { display:flex; gap:8px; justify-content:flex-end; align-items:center; white-space:nowrap; padding-right:12px }
    .table td.actions .btn {
      display: inline-flex !important;
      align-items: center;
      justify-content: center;
      width: 36px !important;
      height: 36px !important;
      padding: 0 !important;
      margin: 0 !important;
      border-radius: 8px !important;
      text-indent: -9999px; /* sembunyikan teks, gunakan icon pseudo-element */
      overflow: hidden !important;
      position: relative;
      background: transparent !important;
      border: none !important;
    }
    .table td.actions .btn:hover { background:#f3f6ff; border:1px solid #d0d7de; }
    /* Biarkan pseudo-element ::before dari stylesheet global yang menambahkan ikon bekerja */
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header">Edit Ujian: <?php echo htmlspecialchars(($soal['judul_ujian']) ?? ''); ?> (<?php echo htmlspecialchars(($soal['nama_mapel'].' - '.$soal['nama_kelas']) ?? ''); ?>)</div>
      <div class="card-body">
        <div class="row" style="justify-content:space-between;align-items:center;margin-bottom:8px">
          <a href="index.php" class="btn back-btn" onclick="var href=this.getAttribute('href'); document.body.style.transition='opacity .22s'; document.body.style.opacity=0; setTimeout(function(){ if (history.length>1) { history.back(); } else { window.location=href; } },220); return false;"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali</a>
          <a href="../dashboard.php" class="btn back-btn" onclick="var href=this.getAttribute('href'); document.body.style.transition='opacity .22s'; document.body.style.opacity=0; setTimeout(function(){ if (history.length>1) { history.back(); } else { window.location=href; } },220); return false;"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali ke Dashboard</a>
        </div>
        <?php if ($flash): ?>
          <div class="alert alert-<?php echo htmlspecialchars(($flash['type']) ?? ''); ?>"><?php echo htmlspecialchars(($flash['message']) ?? ''); ?></div>
        <?php endif; ?>
        <h3>Data Ujian</h3>
        <form method="post" action="index.php?action=update_exam" class="row" style="display:block; margin-bottom:10px;">
          <input type="hidden" name="soal_id" value="<?php echo (int)$soal_id; ?>" />
          <div class="row">
            <div class="col">
              <label>Jenis Ujian</label>
              <select name="jenis_ujian">
                <?php $ju = $soal['jenis_ujian'] ?? 'Harian'; ?>
                <option value="Harian" <?php echo ($ju==='Harian'?'selected':''); ?>>Harian</option>
                <option value="UTS" <?php echo ($ju==='UTS'?'selected':''); ?>>UTS</option>
                <option value="UAS" <?php echo ($ju==='UAS'?'selected':''); ?>>UAS</option>
              </select>
            </div>
            <div class="col">
              <label>Semester</label>
              <select name="semester">
                <?php $sem = $soal['semester'] ?? 'Ganjil'; ?>
                <option value="Ganjil" <?php echo ($sem==='Ganjil'?'selected':''); ?>>Ganjil</option>
                <option value="Genap" <?php echo ($sem==='Genap'?'selected':''); ?>>Genap</option>
              </select>
            </div>
            <div class="col" style="flex:1 1 100%">
              <label>Deskripsi</label>
              <textarea name="deskripsi" rows="4" placeholder="Keterangan/petunjuk ujian... (Ganjil: Juli-Desember, Genap: Januari-Juni)"><?php echo htmlspecialchars(($soal['deskripsi']) ?? ''); ?></textarea>
            </div>
          </div>
          <div class="row">
            <div class="col">
              <label>Judul Ujian</label>
              <input type="text" name="judul_ujian" value="<?php echo htmlspecialchars(($soal['judul_ujian']) ?? ''); ?>" required />
            </div>
          </div>
          <div class="row">
            <div class="col">
              <label>Waktu Mulai</label>
              <input type="datetime-local" name="waktu_mulai" value="<?php echo htmlspecialchars(str_replace(' ', 'T', $soal['waktu_mulai'])); ?>" />
            </div>
            <div class="col">
              <label>Waktu Selesai</label>
              <input type="datetime-local" name="waktu_selesai" value="<?php echo htmlspecialchars(str_replace(' ', 'T', $soal['waktu_selesai'])); ?>" />
            </div>
            <div class="col">
              <label>Durasi (menit)</label>
              <input type="number" name="durasi" min="1" value="<?php echo (int)$soal['durasi']; ?>" />
            </div>
            <div class="col" style="display:flex; align-items:center; gap:8px;">
              <label><input type="checkbox" name="tampil_nilai" <?php echo ((int)$soal['tampil_nilai']===1?'checked':''); ?> /> Tampilkan nilai ke siswa setelah submit</label>
            </div>
          </div>
          <div style="margin-top:8px;">
            <button class="btn" type="submit">Simpan Perubahan Ujian</button>
            <a class="btn" href="../../siswa/ujian/take.php?id=<?php echo $soal_id; ?>&preview=1" target="_blank">Pratinjau (mode guru)</a>
          </div>
        </form>
        <h3>Tambahkan ke Assignment Lain</h3>
        <form method="post" action="index.php?action=clone_to_assignments" onsubmit="return confirm('Tambahkan ujian ini ke assignment terpilih? Semua butir soal akan diduplikasi. Lanjutkan?');">
          <input type="hidden" name="source_soal_id" value="<?php echo (int)$soal_id; ?>" />
          <div class="muted">Centang assignment lain milik Anda untuk menambahkan ujian ini ke kelas tersebut (soal akan diduplikasi):</div>
          <div style="max-height:220px;overflow:auto;border:1px solid #e5e5e5;border-radius:8px;padding:10px;margin-top:6px">
            <?php
              $assignments_all = get_assignments_guru($guru_id);
              $current_assignment_id = (int)$soal['assignment_id'];
              // Tampilkan hanya assignment lain (belum ada ujian ini); jika saat pembuatan ujian dipilih semua kelas (>= total assignment guru), sembunyikan blok ini.
              $totalAssign = is_array($assignments_all) ? count($assignments_all) : 0;
              // Hitung berapa assignment sudah memiliki ujian dengan judul yang sama milik guru ini
              $judulLike = escape_string($soal['judul_ujian']);
              $rsSame = query("SELECT COUNT(*) AS c FROM soal s JOIN assignment_guru ag ON ag.id=s.assignment_id WHERE ag.guru_id={$guru_id} AND s.judul_ujian='{$judulLike}'");
              $rowSame = $rsSame ? fetch_assoc($rsSame) : ['c'=>0];
              $countSame = (int)$rowSame['c'];

              $hasOption = false;
              if ($assignments_all && $countSame < $totalAssign) {
                foreach ($assignments_all as $a) {
                  if ((int)$a['id'] === $current_assignment_id) continue; // skip assignment saat ini
                  // Skip assignment yang sudah ada ujian dengan judul yang sama
                  $check = query("SELECT 1 FROM soal s WHERE s.assignment_id=".(int)$a['id']." AND s.judul_ujian='".$judulLike."' LIMIT 1");
                  $exists = $check && fetch_assoc($check);
                  if ($exists) continue; // sudah ada -> jangan tampilkan

                  $hasOption = true;
                  echo '<label style="display:block;margin-bottom:6px">'
                       .'<input type="checkbox" name="assignment_ids[]" value="'.(int)$a['id'].'"> '
                       .htmlspecialchars(($a['nama_mapel'].' - '.$a['nama_kelas'].' ('.$a['nama_tahun_ajaran'].')') ?? '')
                       .'</label>';
                }
              }
            ?>
          </div>
          <div style="margin-top:8px">
            <?php if (!empty($assignments_all) && $countSame < $totalAssign) { ?>
              <button class="btn" type="submit" <?php echo $hasOption? '':'disabled'; ?>>Tambahkan</button>
              <?php if (!$hasOption) { echo '<span class="muted" style="margin-left:8px">Tidak ada assignment lain yang tersedia.</span>'; } ?>
            <?php } else { ?>
              <span class="muted">Semua kelas yang Anda ajar sudah memiliki ujian ini. Tidak perlu menambahkan lagi.</span>
            <?php } ?>
          </div>
        </form>
        <hr>
        <h3>Butir Soal</h3>
        <div style="display:flex;align-items:center;gap:12px;margin:10px 0;flex-wrap:nowrap;overflow:auto;padding-bottom:6px">
          <a class="text-link" href="index.php?action=export_questions&soal_id=<?php echo (int)$soal_id; ?>&format=csv">Export CSV</a>
          <a class="text-link" href="index.php?action=export_questions&soal_id=<?php echo (int)$soal_id; ?>&format=xls">Export XLS</a>
          <a class="text-link icon-btn" href="index.php?action=download_template&format=csv">Unduh Template CSV</a>
          <a class="text-link icon-btn" href="index.php?action=download_template&format=xls">Unduh Template XLS</a>
          <form method="post" action="index.php?action=import_questions" enctype="multipart/form-data" style="display:flex;align-items:center;gap:8px;margin:0">
            <input type="hidden" name="soal_id" value="<?php echo (int)$soal_id; ?>" />
            <input type="file" name="file_soal" accept=".csv,.xls" required />
            <button class="text-link" type="submit">Import Soal</button>
          </form>
        </div>
        <div class="scroll-x">
          <table class="table builder">
            <thead><tr>
              <th>Urutan</th>
              <th>Bobot</th>
              <th>Tipe</th>
              <th>Pertanyaan</th>
              <th>Kunci</th>
              <th>Aksi</th>
            </tr></thead>
            <tbody>
              <?php if (!$detail): ?>
                <tr><td colspan="6">Belum ada butir soal.</td></tr>
              <?php else: ?>
                <tr><td colspan="6"><strong>Section 1: Pilihan Ganda, Pilihan Ganda Kompleks, Benar/Salah</strong></td></tr>
                <?php foreach ($detail as $d): if (!in_array($d['tipe_soal'], ['pilihan_ganda','pilihan_ganda_kompleks','benar_salah'])) continue; ?>
                <tr>
                  <td data-label="Urutan"><?php echo (int)$d['urutan']; ?></td>
                  <td data-label="Bobot"><?php echo (int)$d['bobot']; ?></td>
                  <td data-label="Tipe"><?php echo htmlspecialchars(($d['tipe_soal']) ?? ''); ?></td>
                  <td data-label="Pertanyaan"><?php echo nl2br(htmlspecialchars(($d['pertanyaan']) ?? '')); ?></td>
                  <td data-label="Kunci"><?php echo htmlspecialchars(($d['jawaban_benar']) ?? ''); ?></td>
                  <td class="actions" data-label="Aksi">
                    <a class="btn icon-btn" href="index.php?action=edit_question&soal_id=<?php echo $soal_id; ?>&id=<?php echo (int)$d['id']; ?>" title="Edit butir" aria-label="Edit butir"><span class="sr-only">Edit</span></a>
                    <a class="btn btn-danger icon-btn" href="index.php?action=delete_question&soal_id=<?php echo $soal_id; ?>&id=<?php echo (int)$d['id']; ?>" onclick="return confirm('Hapus butir ini?')" title="Hapus butir" aria-label="Hapus butir"><span class="sr-only">Hapus</span></a>
                  </td>
                </tr>
                <?php endforeach; ?>
                <tr><td colspan="6"><strong>Section 2: Essay</strong></td></tr>
                <?php foreach ($detail as $d): if ($d['tipe_soal'] !== 'essay') continue; ?>
                <tr>
                  <td data-label="Urutan"><?php echo (int)$d['urutan']; ?></td>
                  <td data-label="Bobot"><?php echo (int)$d['bobot']; ?></td>
                  <td data-label="Tipe"><?php echo htmlspecialchars(($d['tipe_soal']) ?? ''); ?></td>
                  <td data-label="Pertanyaan"><?php echo nl2br(htmlspecialchars(($d['pertanyaan']) ?? '')); ?></td>
                  <td data-label="Kunci"><?php echo htmlspecialchars(($d['jawaban_benar']) ?? ''); ?></td>
                  <td class="actions" data-label="Aksi">
                    <a class="btn icon-btn" href="index.php?action=edit_question&soal_id=<?php echo $soal_id; ?>&id=<?php echo (int)$d['id']; ?>" title="Edit butir" aria-label="Edit butir">
                      <span class="sr-only">Edit</span>
                    </a>
                    <a class="btn btn-danger icon-btn" href="index.php?action=delete_question&soal_id=<?php echo $soal_id; ?>&id=<?php echo (int)$d['id']; ?>" onclick="return confirm('Hapus butir ini?')" title="Hapus butir" aria-label="Hapus butir">
                      <span class="sr-only">Hapus</span>
                    </a>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <hr>
        <div class="row" style="justify-content:space-between;align-items:center">
          <h3>Tambah Butir Soal</h3>
          <div>
            <a class="btn btn-sm" href="bank.php" target="_blank" style="margin-left:8px;">Buka Bank Soal</a>
          </div>
        </div>
        <form method="post" action="index.php?action=add_question" enctype="multipart/form-data">
          <input type="hidden" name="soal_id" value="<?php echo $soal_id; ?>" />
          <div class="row">
            <div class="col">
              <label>Tipe Soal</label>
              <select name="tipe_soal" id="tipe_soal" required onchange="onTypeChange(this.value)">
                <option value="">- pilih -</option>
                <option value="pilihan_ganda">Pilihan Ganda</option>
                <option value="pilihan_ganda_kompleks">Pilihan Ganda Kompleks</option>
                <option value="benar_salah">Benar/Salah</option>
                <option value="essay">Essay</option>
              </select>
            </div>
            <div class="col">
              <label>Bobot</label>
              <input type="number" name="bobot" value="1" min="1" required />
            </div>
            <div class="col">
              <label>Urutan</label>
              <input type="number" name="urutan" value="<?php echo count($detail)+1; ?>" min="1" required />
            </div>
          </div>
          <div>
            <label>Pertanyaan</label>
            <textarea name="pertanyaan" rows="4" required></textarea>
          </div>
          <div id="opsi-container">
            <div class="row" id="opsi-row">
              <div class="col">
                <label>Pilihan A (teks atau gambar)</label>
                <input type="text" name="pilihan_a" />
                <input type="file" name="pilihan_a_img" accept="image/*" style="margin-top:6px" />
              </div>
              <div class="col">
                <label>Pilihan B (teks atau gambar)</label>
                <input type="text" name="pilihan_b" />
                <input type="file" name="pilihan_b_img" accept="image/*" style="margin-top:6px" />
              </div>
              <div class="col">
                <label>Pilihan C (teks atau gambar)</label>
                <input type="text" name="pilihan_c" />
                <input type="file" name="pilihan_c_img" accept="image/*" style="margin-top:6px" />
              </div>
              <div class="col">
                <label>Pilihan D (teks atau gambar)</label>
                <input type="text" name="pilihan_d" />
                <input type="file" name="pilihan_d_img" accept="image/*" style="margin-top:6px" />
              </div>
              <div class="col">
                <label>Pilihan E (teks atau gambar)</label>
                <input type="text" name="pilihan_e" />
                <input type="file" name="pilihan_e_img" accept="image/*" style="margin-top:6px" />
              </div>
            </div>
            <div id="kunci-pg">
              <label>Kunci Jawaban (PG)</label>
              <select name="jawaban_pg">
                <option value="">- pilih -</option>
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
                <option value="D">D</option>
                <option value="E">E</option>
              </select>
            </div>
            <div id="kunci-pgk" style="display:none">
              <label>Kunci Jawaban (PG Kompleks)</label>
              <div>
                <label><input type="checkbox" name="jawaban_pgk[]" value="A"> A</label>
                <label><input type="checkbox" name="jawaban_pgk[]" value="B"> B</label>
                <label><input type="checkbox" name="jawaban_pgk[]" value="C"> C</label>
                <label><input type="checkbox" name="jawaban_pgk[]" value="D"> D</label>
                <label><input type="checkbox" name="jawaban_pgk[]" value="E"> E</label>
              </div>
            </div>
            <div id="kunci-bs" style="display:none">
              <label>Kunci Jawaban (Benar/Salah)</label>
              <select name="jawaban_bs" id="jawaban_bs">
                <option value="">- pilih -</option>
                <option value="benar">Benar</option>
                <option value="salah">Salah</option>
              </select>
            </div>
            <hr>
            <div class="row">
              <div class="col">
                <label>Gambar (opsional)</label>
                <input type="file" name="gambar" accept="image/*" />
              </div>
              <div class="col">
                <label>Posisi Gambar</label>
                <select name="gambar_posisi">
                  <option value="">- pilih posisi -</option>
                  <option value="atas">Atas</option>
                  <option value="bawah">Bawah</option>
                </select>
                <small>Pilih posisi tampilan gambar di soal.</small>
              </div>
            </div>
            <div class="row">
              <div class="col">
                <label>Video (opsional)</label>
                <input type="file" name="video" accept="video/mp4" />
                <small>MP4, maks 100MB atau gunakan URL video</small>
              </div>
              <div class="col">
                <label>Video URL (opsional)</label>
                <input type="text" name="video_url" placeholder="https://youtu.be/... atau https://.../file.mp4" />
              </div>
              <div class="col">
                <label>Posisi Video</label>
                <select name="video_posisi">
                  <option value="">-</option>
                  <option value="atas">Atas</option>
                  <option value="bawah">Bawah</option>
                </select>
                <small>Pilih posisi tampilan video.</small>
              </div>
            </div>
          </div>
          <div style="margin-top:14px">
            <button class="btn" type="submit">Tambah Butir</button>
            <a class="btn" href="../../siswa/ujian/take.php?id=<?php echo $soal_id; ?>&preview=1" target="_blank">Pratinjau (mode guru)</a>
          </div>
        </form>
        <hr>
        <h3>Kelola Remedial</h3>
        <form method="post" action="index.php?action=grant_remedial" class="row" id="form-remedial" style="font-size:14px">
          <input type="hidden" name="soal_id" value="<?php echo (int)$soal_id; ?>" />
          <div class="col" style="flex:1 1 420px">
            <label>Pilih Siswa</label>
            <div style="display:flex;flex-direction:column;gap:8px">
              <select name="siswa_id" id="select-siswa" required style="font-size:14px">
                <option value="">- pilih -</option>
                <?php
                  $kelasId = isset($soal['assignment_kelas_id']) ? (int)$soal['assignment_kelas_id'] : 0;
                  if ($kelasId>0){
                    $rs = query("SELECT u.id, u.nama_lengkap, k.nama_kelas FROM users u JOIN kelas k ON k.id=u.kelas_id WHERE u.role='siswa' AND u.kelas_id={$kelasId} ORDER BY u.nama_lengkap");
                    while ($u = $rs ? fetch_assoc($rs) : null) {
                    $opt = htmlspecialchars(($u['nama_lengkap']) ?? '')." (".htmlspecialchars(($u['nama_kelas']) ?? '').")";
                    echo '<option value="'.(int)$u['id'].'">'.$opt.'</option>';
                    }
                  }
                ?>
              </select>
            </div>
          </div>
          <div class="col" style="align-self:flex-end">
            <button class="btn" type="submit" style="font-size:14px">Tambah Kesempatan (+1)</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <script>
    function onTypeChange(val){
      try {
        var sel = document.getElementById('tipe_soal');
        var form = sel ? sel.closest('form') : null;
        if (window.syncQuestionTypeControls) { window.syncQuestionTypeControls(val, form); return; }
      } catch(e){}
      // fallback minimal
      const row = document.getElementById('opsi-row');
      const kpg = document.getElementById('kunci-pg');
      const kpgk = document.getElementById('kunci-pgk');
      const kbs = document.getElementById('kunci-bs');
      const bs = document.getElementById('jawaban_bs');
      if (val==='pilihan_ganda'){
        if (row) row.style.display='flex'; if (kpg) kpg.style.display='block'; if (kpgk) kpgk.style.display='none'; if (kbs) kbs.style.display='none';
        if (bs) bs.required = false;
      } else if (val==='pilihan_ganda_kompleks'){
        if (row) row.style.display='flex'; if (kpg) kpg.style.display='none'; if (kpgk) kpgk.style.display='block'; if (kbs) kbs.style.display='none';
        if (bs) bs.required = false;
        if (kpgk) { Array.from(kpgk.querySelectorAll('input[type="checkbox"]')).forEach(function(cb){ try{ cb.style.display=''; cb.disabled=false; if(cb.parentElement) cb.parentElement.style.display=''; }catch(e){} }); }
      } else if (val==='benar_salah'){
        if (row) row.style.display='none'; if (kpg) kpg.style.display='none'; if (kpgk) kpgk.style.display='none'; if (kbs) kbs.style.display='block';
        if (bs) bs.required = true;
      } else { // essay
        if (row) row.style.display='none'; if (kpg) kpg.style.display='none'; if (kpgk) kpgk.style.display='none'; if (kbs) kbs.style.display='none';
        if (bs) bs.required = false;
      }
    }
  </script>
  <script>
  // Inisialisasi tampilan field jawaban sesuai tipe saat halaman builder dimuat
  (function(){
    var sel = document.getElementById('tipe_soal');
    try { if (sel && window.syncQuestionTypeControls) { window.syncQuestionTypeControls(sel.value, sel.closest('form')); } } catch(e){}
  })();
  </script>
</body>
</html>
<?php
    exit;
}

// Default: List ujian guru + form create
$assignments = get_assignments_guru($guru_id);
$ujian = fetch_all(query("SELECT MIN(s.id) AS rep_id,
  s.judul_ujian, s.waktu_mulai, s.waktu_selesai, s.durasi, s.jenis_ujian, s.semester, s.tampil_nilai,
  mp.nama_mapel,
  COUNT(DISTINCT ag.kelas_id) AS kelas_count,
  GROUP_CONCAT(DISTINCT k.nama_kelas ORDER BY k.nama_kelas SEPARATOR '||') AS kelas_list
  FROM soal s
  JOIN assignment_guru ag ON ag.id = s.assignment_id
  JOIN mata_pelajaran mp ON mp.id = ag.mapel_id
  JOIN kelas k ON k.id = ag.kelas_id
  WHERE ag.guru_id = {$guru_id}
  GROUP BY s.judul_ujian, s.waktu_mulai, s.waktu_selesai, s.durasi, s.jenis_ujian, s.semester, s.tampil_nilai, mp.nama_mapel
  ORDER BY s.waktu_mulai DESC, rep_id DESC"));
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
    .card{background:#fff;border:1px solid #ddd;border-radius:10px;margin-bottom:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05)}
    .card-header{padding:14px 18px;background:#f5f7fb;font-weight:600}
    .card-body{padding:18px}
    .table{width:100%;border-collapse:collapse}
    .table th,.table td{border:1px solid #e5e5e5;padding:12px}
    /* Prevent excessive wrapping: use ellipsis for overflow by default */
    .table th, .table td { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .table th{background:#fafafa;text-align:left}
    .row{display:flex;gap:14px;flex-wrap:wrap}
    .row .col{flex:1 1 280px}
    .btn{display:inline-block;padding:10px 14px;border-radius:8px;border:1px solid #2c7be5;background:#2c7be5;color:#fff;cursor:pointer;text-decoration:none}
    .btn:hover{filter:brightness(0.95)}
    .actions{display:flex;gap:10px;align-items:center}
    .icon-btn{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;border:1px solid #d0d7de;background:#fff;color:#2c7be5;text-decoration:none;margin:0 4px}
    .icon-btn:hover{background:#f3f6ff}
    /* Actions layout: horizontal icons (no-wrap) */
    .actions{display:flex;gap:8px;align-items:center;flex-wrap:nowrap}
    .actions a.icon-btn{flex:0 0 auto}
    /* keep actions column compact and centered */
    .table td.actions{white-space:nowrap;padding-right:12px;text-align:right;width:auto;min-width:96px}
    .table th,.table td{vertical-align:middle}
    .icon-btn{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;border:1px solid #d0d7de;background:#fff;color:#2c7be5;text-decoration:none}
    .icon-btn:hover{background:#f3f6ff}
    label{display:block;margin-bottom:6px;font-weight:600}
    input[type=text],input[type=number],input[type=datetime-local],select,textarea{width:100%;padding:12px;border:1px solid #ced4da;border-radius:8px;background:#fff;box-sizing:border-box}
    textarea{min-height:120px}
    input:focus,select:focus,textarea:focus{outline:none;border-color:#2c7be5;box-shadow:0 0 0 3px rgba(44,123,229,.15)}
    .alert{padding:12px;border-radius:8px;margin-bottom:12px}
    .alert-error{background:#fdecea;color:#b00020}
    .alert-success{background:#e6f4ea;color:#1e7e34}
    .scroll-x{overflow-x:auto}
    /* Builder table: keep columns stable on desktop */
    .table.builder{table-layout:fixed;width:100%}
    .table.builder th:nth-child(1){width:14%}
    .table.builder th:nth-child(2){width:10%}
    .table.builder th:nth-child(3){width:26%}
    .table.builder th:nth-child(4){width:6%}
    .table.builder th:nth-child(5){width:6%}
    .table.builder th:nth-child(6){width:10%}
    .table.builder th:nth-child(7){width:10%}
    .table.builder th:nth-child(8){width:6%}
    .table.builder th:nth-child(9){width:6%}
    .table.builder th:nth-child(10){width:6%}

    /* Make actions column wider on desktop so icons never overflow */
    .table td.actions{white-space:nowrap;padding-right:12px;text-align:right;width:auto;min-width:220px}

    /* Mobile: hide less important columns and keep horizontal scrolling for the table */
    @media (max-width: 760px) {
      .scroll-x { overflow-x: auto; -webkit-overflow-scrolling: touch; }
      /* hide columns that are non-essential on small screens */
      .table.builder thead th:nth-child(2),
      .table.builder tbody td:nth-child(2),
      .table.builder thead th:nth-child(4),
      .table.builder tbody td:nth-child(4),
      .table.builder thead th:nth-child(5),
      .table.builder tbody td:nth-child(5),
      .table.builder thead th:nth-child(8),
      .table.builder tbody td:nth-child(8),
      .table.builder thead th:nth-child(9),
      .table.builder tbody td:nth-child(9) {
        display: none;
      }
      .table.builder th:nth-child(3), .table.builder td:nth-child(3) { width: 56%; }
      .icon-btn{width:34px;height:34px;font-size:14px;padding:0}
      .table.builder td.actions{min-width:140px !important; padding-right:8px; text-align:right}
      /* Reduce icon size slightly on smaller screens */
      .icon-btn{width:30px;height:30px}
    }

    /* Ensure actions column stays wide enough on desktop (override any earlier rules) */
    .table.builder td.actions{min-width:240px !important; text-align:right !important}

    /* Allow title column to wrap when needed (avoid truncating long titles entirely) */
    .table th:nth-child(3), .table td:nth-child(3) { white-space: normal; }

  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header">Buat Ujian</div>
      <div class="card-body">
        <div style="margin-bottom:10px">
          <a href="../dashboard.php" class="btn back-btn" onclick="var href=this.getAttribute('href'); document.body.style.transition='opacity .22s'; document.body.style.opacity=0; setTimeout(function(){ if (history.length>1) { history.back(); } else { window.location=href; } },220); return false;"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali</a>
        </div>
        <?php if ($flash): ?>
          <div class="alert alert-<?php echo htmlspecialchars(($flash['type']) ?? ''); ?>"><?php echo htmlspecialchars(($flash['message']) ?? ''); ?></div>
        <?php endif; ?>
        <?php if (!$assignments): ?>
          <p>Anda belum memiliki assignment (mapel-kelas). Minta admin menambahkan assignment_guru.</p>
        <?php else: ?>
        <form method="post" action="index.php?action=create_exam">
          <div class="row">
            <div class="col" style="flex:1 1 100%">
              <label>Pilih Assignment (Mapel - Kelas - Tahun Ajaran)</label>
              <div style="max-height:220px;overflow:auto;border:1px solid #e5e5e5;border-radius:8px;padding:10px">
                <?php foreach ($assignments as $a): ?>
                  <label style="display:block;margin-bottom:6px">
                    <input type="checkbox" name="assignment_ids[]" value="<?php echo (int)$a['id']; ?>"> <?php echo htmlspecialchars(($a['nama_mapel'].' - '.$a['nama_kelas'].' ('.$a['nama_tahun_ajaran'].')') ?? ''); ?>
                  </label>
                <?php endforeach; ?>
              </div>
              <div class="muted">Pilih satu atau lebih assignment sebagai target ujian.</div>
            </div>
            <div class="col">
              <label>Jenis Ujian</label>
              <select name="jenis_ujian">
                <option value="Harian">Harian</option>
                <option value="UTS">UTS</option>
                <option value="UAS">UAS</option>
              </select>
            </div>
            <div class="col">
              <label>Semester</label>
              <select name="semester" required>
                <option value="">- pilih -</option>
                <option value="Ganjil">Ganjil</option>
                <option value="Genap">Genap</option>
              </select>
            </div>
          </div>
          <div>
            <label>Deskripsi (opsional)</label>
            <textarea name="deskripsi" rows="3" placeholder="Contoh: Ujian dilaksanakan pada rentang bulan sesuai semester (Ganjil: Juli-Desember, Genap: Januari-Juni)"></textarea>
          </div>
          <div class="row">
            <div class="col">
              <label>Judul Ujian</label>
              <input type="text" name="judul_ujian" placeholder="Contoh: UTS Semester Ganjil 2025/2026" required />
            </div>
          </div>
          <div class="row">
            <div class="col">
              <label>Waktu Mulai</label>
              <input type="datetime-local" name="waktu_mulai" required />
            </div>
            <div class="col">
              <label>Waktu Selesai</label>
              <input type="datetime-local" name="waktu_selesai" required />
            </div>
            <div class="col">
              <label>Durasi (menit)</label>
              <input type="number" name="durasi" min="1" required />
            </div>
            <div class="col" style="display:flex;align-items:center;gap:10px;min-height:60px">
              <label style="margin:0"><input type="checkbox" name="tampil_nilai" /> Tampilkan nilai ke siswa setelah selesai</label>
            </div>
          </div>
          <div style="margin-top:12px">
            <button class="btn" type="submit">Simpan & Tambah Soal</button>
          </div>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Ujian Saya</div>
      <div class="card-body">
        <?php if (!$ujian): ?>
          <div class="muted">Belum ada ujian</div>
        <?php else: ?>
          <div class="item-list">
            <?php foreach ($ujian as $u):
              $kelas_names = $u['kelas_list'] ? explode('||', $u['kelas_list']) : [];
              $kelas_count = (int)$u['kelas_count'];
              $rep_id = (int)($u['rep_id'] ?? 0);
            ?>
              <div class="item" style="border-bottom:1px solid #eef2f5;padding:10px 0;display:flex;justify-content:space-between;align-items:center">
                <div>
                  <div style="font-weight:600;font-size:15px"><?php echo htmlspecialchars(($u['judul_ujian']) ?? ''); ?></div>
                  <div style="font-size:13px;color:#666;margin-top:6px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <span style="display:inline-flex;align-items:center;gap:6px"><strong><?php echo htmlspecialchars(($u['nama_mapel']) ?? ''); ?></strong></span>
                    <?php if ($kelas_count >= 4): ?>
                      <span class="badge" style="background:#e6f4ea;color:#1e7e34;padding:4px 8px;border-radius:12px;font-size:12px"><?php echo $kelas_count; ?> kelas</span>
                    <?php else: foreach ($kelas_names as $kn): ?>
                      <span class="badge" style="background:#f1f5f9;color:#333;padding:4px 8px;border-radius:12px;font-size:12px"><?php echo htmlspecialchars(($kn) ?? ''); ?></span>
                    <?php endforeach; endif; ?>
                    <span style="color:#888;font-size:13px;margin-left:6px">
                      <?php echo htmlspecialchars(($u['waktu_mulai']) ?? ''); ?> â€” <?php echo htmlspecialchars(($u['waktu_selesai']) ?? ''); ?>
                    </span>
                  </div>
                </div>
                <div style="display:flex;gap:8px;align-items:center">
                  <a class="btn icon-btn" href="index.php?action=builder&soal_id=<?php echo $rep_id; ?>" title="Edit" aria-label="Edit">Edit</a>
                  <a class="btn icon-btn" target="_blank" href="../../siswa/ujian/take.php?id=<?php echo $rep_id; ?>&preview=1" title="Pratinjau" aria-label="Pratinjau">Pratinjau</a>
                  <a class="btn btn-danger icon-btn" href="index.php?action=delete_force&id=<?php echo $rep_id; ?>" title="Hapus Paksa" aria-label="Hapus Paksa" onclick="return confirm('Hapus Paksa ujian ini? Semua jawaban/attempt/log/akses untuk ujian ini akan dihapus, bank soal tetap aman. Lanjutkan?');"><i class="fas fa-trash" aria-hidden="true"></i></a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>



