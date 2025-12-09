# restore_baks_for_htmlspecialchars_fix.ps1
# Restore .bak -> .php for files where current .php contains the broken pattern "?? '')"
$problemPattern = "?? '')"
Get-ChildItem -Recurse -Filter *.php.bak -File | ForEach-Object {
    $bak = $_.FullName
    $orig = $bak -replace '\.bak$',''
    if (Test-Path $orig) {
        $content = Get-Content -Raw -Path $orig -Encoding UTF8
        if ($content -match [regex]::Escape($problemPattern)) {
            Copy-Item -Path $bak -Destination $orig -Force
            Write-Host "Restored: $orig from $bak"
        }
    }
}
Write-Host "Restore step completed."