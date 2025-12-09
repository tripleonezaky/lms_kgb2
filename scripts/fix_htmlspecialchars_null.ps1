# fix_htmlspecialchars_null.ps1
# Ganti htmlspecialchars(X) menjadi htmlspecialchars(X ?? '') ketika hanya ada satu argumen

$extensions = @('*.php')
$files = Get-ChildItem -Recurse -Include $extensions -File
$pattern = 'htmlspecialchars\(\s*([^,\)]+?)\s*\)'
$changeCount = 0
foreach ($file in $files) {
    $text = Get-Content -Raw -Path $file.FullName -Encoding UTF8
    $new = $text
    $matches = [regex]::Matches($text, $pattern)
    if ($matches.Count -eq 0) { continue }
    foreach ($m in $matches) {
        $arg = $m.Groups[1].Value.Trim()
        # skip if already uses ??
        if ($arg -match '\?\?') { continue }
        $replacement = "htmlspecialchars($arg ?? '')"
        $new = $new.Replace($m.Value, $replacement)
    }
    if ($new -ne $text) {
        Copy-Item -Path $file.FullName -Destination ($file.FullName + '.bak') -Force
        Set-Content -Path $file.FullName -Value $new -Encoding UTF8
        Write-Host "Updated: $($file.FullName)"
        $changeCount++
    }
}
Write-Host "Done. Files changed: $changeCount"
