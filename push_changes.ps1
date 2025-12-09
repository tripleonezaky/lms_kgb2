<#
push_changes.ps1
Script bantu untuk men-push commit lokal ke remote repository.
Cara pakai:
  1) Buka PowerShell di folder project (C:\xampp\htdocs\lms_kgb2)
  2) Jalankan: .\push_changes.ps1
  3) Ikuti prompt (masukkan remote URL jika belum ada)
#>

# Pastikan berada di folder script
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
Set-Location $scriptDir

function Ask-YesNo($msg){
    $ans = Read-Host "$msg (y/n)"
    return $ans -match '^(y|Y)'
}

# Cek git tersedia
try { $gv = & git --version 2>$null } catch { $gv = $null }
if (-not $gv) {
    Write-Host "Perintah 'git' tidak ditemukan. Pastikan Git sudah terinstal dan PATH sudah diperbarui." -ForegroundColor Red
    exit 1
}
Write-Host "Git ditemukan: $gv" -ForegroundColor Green

# Cek status repo
$pwd = (Get-Location).ProviderPath
Write-Host "Folder kerja: $pwd"

# Pastikan folder ini adalah repo git
if (-not (Test-Path .git)) {
    Write-Host "Tidak ada repository git di folder ini. Inisialisasi?" -ForegroundColor Yellow
    if (Ask-YesNo "Inisialisasi git di folder ini sekarang?") { git init } else { exit 1 }
}

# Tampilkan remote
$remotes = git remote -v 2>$null
if ($remotes) {
    Write-Host "Remote yang terkonfigurasi:" -ForegroundColor Cyan
    $remotes | ForEach-Object { Write-Host $_ }
    # Jika pengguna ingin mengganti atau menambahkan origin, beri opsi
    if (Ask-YesNo "Ingin mengganti/menetapkan ulang URL remote 'origin' sekarang?") {
        $url = Read-Host "Masukkan URL remote (contoh: https://github.com/user/repo.git)"
        if ($url -ne '') {
            # Jika origin sudah ada, set-url; jika tidak, tambahkan
            $hasOrigin = (git remote | Select-String '^origin$' 2>$null)
            if ($hasOrigin) {
                git remote set-url origin $url
                Write-Host "URL remote 'origin' di-set ke: $url" -ForegroundColor Green
            } else {
                git remote add origin $url
                Write-Host "Remote 'origin' ditambahkan: $url" -ForegroundColor Green
            }
        } else { Write-Host "URL kosong. Lewati." }
    }
} else {
    Write-Host "Belum ada remote terkonfigurasi." -ForegroundColor Yellow
    if (Ask-YesNo "Ingin menambahkan remote sekarang?") {
        $url = Read-Host "Masukkan URL remote (contoh: https://github.com/user/repo.git)"
        if ($url -ne '') {
            try {
                git remote add origin $url
                Write-Host "Remote 'origin' ditambahkan." -ForegroundColor Green
            } catch {
                # Jika gagal karena origin sudah ada, coba set-url
                Write-Host "Gagal menambahkan remote 'origin' (mungkin sudah ada). Menetapkan URL menggunakan 'git remote set-url'..." -ForegroundColor Yellow
                git remote set-url origin $url
                Write-Host "URL remote 'origin' di-set ke: $url" -ForegroundColor Green
            }
        } else { Write-Host "URL kosong. Batal."; exit 1 }
    } else { Write-Host "Tidak ada remote -> tidak dapat push."; exit 0 }
}

# Tentukan branch saat ini
$branch = git rev-parse --abbrev-ref HEAD 2>$null
if (-not $branch) { Write-Host "Gagal menentukan branch saat ini." -ForegroundColor Red; exit 1 }
Write-Host "Branch saat ini: $branch" -ForegroundColor Cyan

# Pastikan commit ada
$status = git status --porcelain
if (-not $status) {
    Write-Host "Tidak ada perubahan yang belum di-commit." -ForegroundColor Green
} else {
    Write-Host "Ada perubahan belum di-commit, lakukan 'git add' dan 'git commit' dulu." -ForegroundColor Yellow
    if (Ask-YesNo "Ingin menjalankan 'git add -A' dan commit sekarang dengan pesan default?") {
        $msg = Read-Host "Masukkan pesan commit (kosong untuk default)"
        if ($msg -eq '') { $msg = "chore(ui): update" }
        git add -A
        git commit -m "$msg"
    } else { Write-Host "Batal push karena ada perubahan tidak ter-commit."; exit 1 }
}

# Pilih remote target
$remoteName = 'origin'
if (-not (git remote show $remoteName 2>$null)) {
    $remoteList = git remote
    if ($remoteList) { $remoteName = ($remoteList | Select-Object -First 1) }
}

# Push
Write-Host "Mencoba push ke $remoteName/$branch ..." -ForegroundColor Cyan
$push = git push -u $remoteName $branch 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "Push berhasil." -ForegroundColor Green
    Write-Host $push
    exit 0
} else {
    Write-Host "Push gagal. Output:" -ForegroundColor Red
    Write-Host $push
    exit $LASTEXITCODE
}
