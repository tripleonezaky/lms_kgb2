<#
  scripts/replace_emoji_to_fa.ps1
  Tujuan: Scan file PHP dalam repo, mengganti emoji literal umum dengan
  elemen Font Awesome (<i class="fas fa-..."></i>) untuk menghindari
  masalah encoding dan memastikan konsistensi icon.

  Behavior:
  - Membuat backup file dengan ekstensi .bak sebelum menulis perubahan
  - Mengganti emoji literal berdasarkan mapping sederhana
  - Menulis ringkasan file yang diubah ke output
#>

Set-StrictMode -Version Latest

$function = $null
function Get-CharFromCode {
    param([int]$cp)
    if ($cp -le 0xFFFF) { return [string][char]$cp }
    $v = $cp - 0x10000
    $hi = [int]([math]::Floor($v/0x400)) + 0xD800
    $lo = ($v % 0x400) + 0xDC00
    return [string]::Concat([char]$hi,[char]$lo)
}

$mapping = [ordered]@{}
$mapping[(Get-CharFromCode 0x2795)] = '<i class="fas fa-plus" aria-hidden="true"></i>'   # ➕
$mapping[(Get-CharFromCode 0x270F)] = '<i class="fas fa-pen" aria-hidden="true"></i>'    # ✏
$mapping[(Get-CharFromCode 0x270F) + (Get-CharFromCode 0xFE0F)] = '<i class="fas fa-pen" aria-hidden="true"></i>' # ✏️
$mapping[(Get-CharFromCode 0x1F4BE)] = '<i class="fas fa-save" aria-hidden="true"></i>'   # 💾
$mapping[(Get-CharFromCode 0x1F5A8)] = '<i class="fas fa-print" aria-hidden="true"></i>'  # 🖨
$mapping[(Get-CharFromCode 0x1F5A8) + (Get-CharFromCode 0xFE0F)] = '<i class="fas fa-print" aria-hidden="true"></i>' # 🖨️
$mapping[(Get-CharFromCode 0x2705)] = '<i class="fas fa-check-circle" aria-hidden="true"></i>' # ✅
$mapping[(Get-CharFromCode 0x2714)] = '<i class="fas fa-check-circle" aria-hidden="true"></i>' # ✔
$mapping[(Get-CharFromCode 0x274C)] = '<i class="fas fa-times-circle" aria-hidden="true"></i>' # ❌
$mapping[(Get-CharFromCode 0x2716)] = '<i class="fas fa-times-circle" aria-hidden="true"></i>' # ✖
$mapping[(Get-CharFromCode 0x1F512)] = '<i class="fas fa-lock" aria-hidden="true"></i>'     # 🔒
$mapping[(Get-CharFromCode 0x1F4C1)] = '<i class="fas fa-folder" aria-hidden="true"></i>'   # 📁
$mapping[(Get-CharFromCode 0x1F4DA)] = '<i class="fas fa-book" aria-hidden="true"></i>'     # 📚
$mapping[(Get-CharFromCode 0x1F4E5)] = '<i class="fas fa-file-import" aria-hidden="true"></i>'# 📥
$mapping[(Get-CharFromCode 0x1F4E4)] = '<i class="fas fa-file-export" aria-hidden="true"></i>'# 📤
$mapping[(Get-CharFromCode 0x1F4DD)] = '<i class="fas fa-file-alt" aria-hidden="true"></i>'  # 📝
$mapping[(Get-CharFromCode 0x1F4A1)] = '<i class="fas fa-lightbulb" aria-hidden="true"></i>' # 💡
$mapping[(Get-CharFromCode 0x2796)] = '<i class="fas fa-minus" aria-hidden="true"></i>'     # ➖

$root = Get-Location
# Extensions to scan (add or remove as needed)
$extensions = @('*.php','*.html','*.htm','*.js','*.css','*.md','*.txt')
$files = Get-ChildItem -Path $root -Recurse -Include $extensions -File

Write-Host "Found $($files.Count) PHP files; scanning for emoji mappings..."
$changed = @()

foreach ($f in $files) {
    try {
        $text = [System.IO.File]::ReadAllText($f.FullName, [System.Text.Encoding]::UTF8)
    } catch {
        Write-Warning "Cannot read $($f.FullName): $_"
        continue
    }

    $orig = $text
    foreach ($emo in $mapping.Keys) {
        if ($text -like "*${emo}*") {
            $replacement = $mapping[$emo]
            $text = $text -replace [regex]::Escape($emo), $replacement
        }
    }

    # (Skip mojibake sequence replacements here - we rely on direct emoji matches)

    if ($text -ne $orig) {
        $bak = $f.FullName + '.bak'
        if (-not (Test-Path $bak)) {
            Copy-Item -Path $f.FullName -Destination $bak -Force
        } else {
            $time = Get-Date -Format yyyyMMddHHmmss
            Copy-Item -Path $f.FullName -Destination ($f.FullName + ".bak.$time") -Force
        }
        try {
            [System.IO.File]::WriteAllText($f.FullName, $text, [System.Text.Encoding]::UTF8)
            Write-Host "Updated: $($f.FullName)"
            $changed += $f.FullName
        } catch {
            Write-Warning "Failed to write $($f.FullName): $_"
        }
    }
}

Write-Host "Done. Files changed: $($changed.Count)"
if ($changed.Count -gt 0) { $changed | ForEach-Object { Write-Host " - $_" } }
