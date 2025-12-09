# apply_htmlspecialchars_wrapper.ps1
# Safely replace htmlspecialchars(ARG) -> htmlspecialchars((ARG) ?? '') when ARG doesn't contain a comma

$extensions = @('*.php')
$files = Get-ChildItem -Recurse -Include $extensions -File
$pattern = 'htmlspecialchars\(\s*([^,\)]+?)\s*\)'
$changeCount = 0
foreach ($file in $files) {
    $text = Get-Content -Raw -Path $file.FullName -Encoding UTF8
    $new = $text
    $offset = 0
    $matches = [regex]::Matches($text, $pattern)
    if ($matches.Count -eq 0) { continue }
    # Build new content iteratively to avoid overlapping replacements
    $sb = New-Object System.Text.StringBuilder
    $lastIndex = 0
    foreach ($m in $matches) {
        $sb.Append($text.Substring($lastIndex, $m.Index - $lastIndex)) | Out-Null
        $arg = $m.Groups[1].Value.Trim()
        if ($arg -match '\?\?') { 
            # already null-coalesced
            $sb.Append($m.Value) | Out-Null
        } else {
            $replacement = "htmlspecialchars((" + $arg + ") ?? '')"
            $sb.Append($replacement) | Out-Null
        }
        $lastIndex = $m.Index + $m.Length
    }
    $sb.Append($text.Substring($lastIndex)) | Out-Null
    $new = $sb.ToString()
    if ($new -ne $text) {
        Copy-Item -Path $file.FullName -Destination ($file.FullName + '.bak_fix2') -Force
        Set-Content -Path $file.FullName -Value $new -Encoding UTF8
        Write-Host "Updated: $($file.FullName)"
        $changeCount++
    }
}
Write-Host "Done. Files changed: $changeCount"
