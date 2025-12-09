# make_actions_icon_only.ps1
# Mengganti tombol/anchor dengan class mengandung 'btn-action' menjadi icon-only
# Backup file dibuat dengan ekstensi .bak sebelum perubahan

[void][System.Reflection.Assembly]::LoadWithPartialName('System.Text.RegularExpressions')

$extensions = @('*.php','*.html','*.htm')
$files = Get-ChildItem -Recurse -Include $extensions -File

$changeCount = 0
# make_actions_icon_only.ps1
# Mengganti tombol/anchor dengan class mengandung 'btn-action' menjadi icon-only
# Backup file dibuat dengan ekstensi .bak sebelum perubahan

[void][System.Reflection.Assembly]::LoadWithPartialName('System.Text.RegularExpressions')

$extensions = @('*.php','*.html','*.htm')
$files = Get-ChildItem -Recurse -Include $extensions -File

$changeCount = 0
foreach ($file in $files) {
    $text = Get-Content -Raw -Path $file.FullName -Encoding UTF8
    $new = $text

    $pattern = '<(?<tag>a|button)(?<attrs>[^>]*)>(?<inner>.*?)</\k<tag>>'
    $opts = [System.Text.RegularExpressions.RegexOptions]::Singleline
    $matches = [regex]::Matches($text, $pattern, $opts)

    if ($matches.Count -eq 0) { continue }

    for ($i = $matches.Count - 1; $i -ge 0; $i--) {
        $m = $matches[$i]
        if ($m -eq $null -or $m.Groups.Count -lt 4) { continue }
        $tag = $m.Groups['tag'].Value
        $attrs = $m.Groups['attrs'].Value
        $inner = $m.Groups['inner'].Value

        if ($attrs -notmatch '(?i)\b(btn|btn-action|action|aksi|btn-sm|btn-primary|btn-warning|btn-danger|btn-secondary)\b') { continue }

        # Strip inner HTML to plain text
        $innerPlain = [regex]::Replace($inner, '<.*?>', '')
        $innerTrim = $innerPlain.Trim()

        if ([string]::IsNullOrWhiteSpace($innerTrim)) { continue }

        # Determine icon
        $icon = $null
        switch -Regex ($innerTrim) {
            '(?i)edit|✏|pen' { $icon = 'fa-pen'; break }
            '(?i)hapus|delete|del|buang|remove|⚠|warning' { $icon = 'fa-trash'; break }
            '(?i)lihat|view|preview|detail|📌' { $icon = 'fa-eye'; break }
            '(?i)kembali|back|⬅' { $icon = 'fa-arrow-left'; break }
            '(?i)print|cetak|🖨' { $icon = 'fa-print'; break }
            '(?i)download' { $icon = 'fa-download'; break }
            '(?i)aktif|enable' { $icon = 'fa-toggle-on'; break }
            '(?i)non-?aktif|disable' { $icon = 'fa-toggle-off'; break }
            '(?i)reset|hapus semua' { $icon = 'fa-eraser'; break }
            default { $icon = $null }
        }

        if (-not $icon) { continue }

        # Ensure title attribute exists for accessibility
        $newAttrs = $attrs
        if ($newAttrs -notmatch '\b(title|aria-label)\s*=') {
            $escaped = $innerTrim -replace '"','\"'
            $newAttrs = $newAttrs + ' title="' + $escaped + '"'
        }

        $replacement = '<' + $tag + $newAttrs + '><i class="fas ' + $icon + '" aria-hidden="true"></i></' + $tag + '>'

        $start = $m.Index
        $length = $m.Length
        $new = $new.Substring(0,$start) + $replacement + $new.Substring($start + $length)
    }

    if ($new -ne $text) {
        Copy-Item -Path $file.FullName -Destination ($file.FullName + '.bak') -Force
        Set-Content -Path $file.FullName -Value $new -Encoding UTF8
        Write-Host "Updated: $($file.FullName)"
        $changeCount++
    }
}

Write-Host "Done. Files changed: $changeCount"