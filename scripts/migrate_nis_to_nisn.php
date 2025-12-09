<?php
// scripts/migrate_nis_to_nisn.php
// Salin nilai `nis` ke `nisn` untuk siswa yang belum memiliki `nisn`.
// Guard: jika kolom `nis` tidak ada pada tabel users (skema baru), hentikan dengan pesan informatif.

require_once __DIR__ . '/../config/database.php';

// Cek apakah kolom `nis` ada pada tabel users
$colCheck = query("SHOW COLUMNS FROM users LIKE 'nis'");
if (!$colCheck || num_rows($colCheck) === 0) {
    echo "Kolom 'nis' tidak ditemukan pada tabel users. Skema sudah menggunakan 'nisn' saja. Tidak ada migrasi yang diperlukan.\n";
    exit(0);
}

// Temukan baris yang akan diupdate
$sql = "SELECT id, nama_lengkap, nis, nisn FROM users WHERE role='siswa' AND (nisn IS NULL OR TRIM(nisn)='') AND (nis IS NOT NULL AND TRIM(nis) <> '')";
$res = query($sql);
$rows = fetch_all($res);
$count = count($rows);

if ($count === 0) {
    echo "Tidak ada siswa yang perlu migrasi (tidak ada nis tersedia untuk baris tanpa nisn).\n";
    exit(0);
}

echo "Ditemukan {$count} siswa yang akan diisi nisn dari nis.\n";
echo "Membuat backup CSV sebelum melakukan update...\n";

$backupDir = __DIR__ . '/backups';
if (!is_dir($backupDir)) { mkdir($backupDir, 0755, true); }
$ts = date('Ymd_His');
$backupFile = $backupDir . "/backup_nisn_before_{$ts}.csv";

$fh = fopen($backupFile, 'w');
fputcsv($fh, ['id','nama_lengkap','nis','nisn']);
foreach ($rows as $r) {
    fputcsv($fh, [$r['id'], $r['nama_lengkap'], $r['nis'], $r['nisn']]);
}
fclose($fh);

echo "Backup tersimpan di: {$backupFile}\n";

// Parse CLI args for non-interactive confirmation
$autoYes = false;
if (PHP_SAPI === 'cli') {
    $argv_list = isset($argv) && is_array($argv) ? $argv : [];
    foreach ($argv_list as $a) {
        if ($a === '--yes' || $a === '-y') { $autoYes = true; break; }
    }
}

// Konfirmasi pengguna (jika dijalankan di CLI)
if (PHP_SAPI === 'cli' && !$autoYes) {
    echo "Lanjutkan update (y/n)? ";
    $ans = trim(fgets(STDIN));
    if (strtolower($ans) !== 'y') {
        echo "Dibatalkan oleh pengguna. Tidak ada perubahan yang dibuat.\n";
        exit(1);
    }
} elseif ($autoYes) {
    echo "Auto-confirm enabled (--yes). Melanjutkan...\n";
}

// Lakukan update dalam satu pernyataan (atomic)
$updateSql = "UPDATE users SET nisn = nis WHERE role='siswa' AND (nisn IS NULL OR TRIM(nisn)='') AND (nis IS NOT NULL AND TRIM(nis) <> '')";
query($updateSql);
$affected = mysqli_affected_rows(db());

echo "Update selesai. Baris yang diubah: {$affected}\n";
echo "Selesai. Mohon periksa aplikasi/halaman `guru/ujian/akses.php` untuk memastikan perubahan tampil sesuai.\n";

?>
