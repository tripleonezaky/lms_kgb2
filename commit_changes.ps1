# commit_changes.ps1
# Script bantu untuk men-commit perubahan lokal di folder project
# Cara pakai: buka PowerShell di folder project lalu jalankan: .\commit_changes.ps1

Param(
    [string]$Message = "chore(ui): back-btn arrow, hide strip3, compact Ujian Saya"
)

Write-Host "Mulai: Menyiapkan commit di folder" -ForegroundColor Cyan

# Pastikan kita berada di direktori script
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
Set-Location $scriptDir

# Cek apakah git tersedia
try {
    $gitVersion = & git --version 2>$null
} catch {
    $gitVersion = $null
}

if (-not $gitVersion) {
    Write-Host "Perintah 'git' tidak ditemukan. Silakan pasang Git terlebih dahulu." -ForegroundColor Red
    Write-Host "Rekomendasi (winget): winget install --id Git.Git -e" -ForegroundColor Yellow
    exit 1
}
Write-Host "Git tersedia: $gitVersion" -ForegroundColor Green

# Inisialisasi repo bila belum
if (-not (Test-Path .git)) {
    Write-Host "Menginisialisasi repository git..." -ForegroundColor Cyan
    git init
} else {
    Write-Host "Repository git sudah ada." -ForegroundColor Green
}

# Pastikan ada user.name & user.email (local config jika belum ada global)
$uname = git config user.name --get
$uemail = git config user.email --get
if (-not $uname) {
    Write-Host "user.name Git belum terkonfigurasi. Mengatur local user.name -> 'LMS UI Bot'" -ForegroundColor Yellow
    git config user.name "LMS UI Bot"
}
if (-not $uemail) {
    Write-Host "user.email Git belum terkonfigurasi. Mengatur local user.email -> 'bot@local.invalid'" -ForegroundColor Yellow
    git config user.email "bot@local.invalid"
}

# Stage semua perubahan
Write-Host "Menambahkan semua perubahan ke index (git add -A)..." -ForegroundColor Cyan
git add -A

# Cek apakah ada perubahan untuk di-commit
$status = git status --porcelain
if (-not $status) {
    Write-Host "Tidak ada perubahan untuk di-commit." -ForegroundColor Yellow
    exit 0
}

# Commit
Write-Host "Membuat commit dengan pesan: $Message" -ForegroundColor Cyan
$commit = git commit -m "$Message" 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "Commit berhasil." -ForegroundColor Green
    Write-Host $commit
    exit 0
} else {
    Write-Host "Commit gagal. Output:" -ForegroundColor Red
    Write-Host $commit
    exit $LASTEXITCODE
}
