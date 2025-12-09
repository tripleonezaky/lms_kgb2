Ringkasan Perubahan UI — KGB2 LMS

Tanggal: 2025-12-09
Oleh: automated edit (assistant)

Perubahan utama:

1) Tombol "Kembali"
- Menyatukan perilaku tombol "Kembali" di banyak halaman:
  - Menambahkan kelas `back-btn` di CSS (`assets/css/style.css`).
  - Menambahkan ikon panah kiri (FontAwesome) dan teks "Kembali" untuk semua tautan yang semula hanya ikon.
  - Menambahkan onclick lightweight JS untuk fade-out (opacity) dan `history.back()` fallback ke href.
  - File PHP yang diubah (daftar):
    - guru/ujian/index.php
    - guru/ujian/akses.php
    - guru/ujian/bank.php
    - guru/materi/index.php
    - guru/materi/edit.php
    - guru/tugas/index.php
    - guru/tugas/detail.php (tidak diubah, sudah berisi teks)
    - guru/nilai/rekap_uts.php
    - guru/nilai/rekap_uas.php
    - guru/nilai/tugas.php (sudah berisi teks)
    - guru/nilai/ujian.php (sudah berisi teks)
    - siswa/lihat_materi.php
    - siswa/lihat_tugas.php
    - siswa/ujian/index.php
    - siswa/nilai_saya.php
    - profile.php
    - ubah_password.php
    - admin/rekap_uts_manual.php

2) Styling global
- `assets/css/style.css`:
  - Menambahkan `.back-btn` style (warna, padding, hover)
  - Menambahkan `::before` SVG fallback panah kiri
  - Menambahkan media-query untuk menyembunyikan varian "strip 3" di desktop (.strip-3, .strip3, .stip-3, .stip3, [data-strip="3"]).
  - Menambahkan icon data-URI rules sebelumnya (CSV/XLS/template) — sudah ada dari perubahan sebelumnya.

3) Dashboard Guru
- `guru/dashboard.php`: menambahkan CSS untuk panel "Ujian Saya" agar tampil ringkas (item-list compact).

Catatan penting:
- Git tidak ditemukan di environment sehingga commit otomatis tidak dilakukan. Untuk menyimpan perubahan ke git, jalankan perintah lokal di PowerShell (setelah memasang Git):

```powershell
cd 'C:\xampp\htdocs\lms_kgb2'
git init
git add -A
git commit -m "chore(ui): back-btn arrow, hide strip3, compact Ujian Saya"
```

- Jika Anda ingin saya menyiapkan patch/diff atau zip file perubahan untuk diunduh, beri tahu saya dan saya buatkan.

Jika Anda ingin penyesuaian warna, ukuran ikon, atau animasi fade (durasi), saya bisa langsung ubah ke nilai yang Anda inginkan.
