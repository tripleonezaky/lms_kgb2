# Add icon-btn class to action anchors and buttons across the repo
# Usage: run in project root (script expects to be in scripts folder)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
$root = Split-Path -Parent $scriptDir
if (-not (Test-Path $root)) { $root = Get-Location }
Push-Location $root
Write-Host "Running add_icon_btn.ps1 in $root"
$hrefKeywords = 'action=edit|action=builder|action=delete|delete_force|delete\?|hapus|preview|take\.php|export|download|import|clone|reset_password'

$files = Get-ChildItem -Path $root -Recurse -Include *.php,*.html,*.htm -File | Where-Object { $_.FullName -notmatch '\\vendor\\' -and $_.FullName -notmatch '\\.git\\' }
$changedFiles = @()
foreach ($file in $files) {
    try {
        $text = Get-Content -Raw -LiteralPath $file.FullName
        $orig = $text

        $hrefRegex = [regex]::new('(?i)action=edit|action=builder|action=delete|delete_force|delete\?|hapus|preview|take\.php|export|download|import|clone|reset_password')
        $anchorMatches = [regex]::Matches($text, '<a\b[^>]*>', 'IgnoreCase')
        foreach ($m in $anchorMatches) {
            $tag = $m.Value
            if ($tag -match '\bicon-btn\b') { continue }

            # extract href value if present
            $hrefVal = ''
            if ($tag -match 'href\s*=\s*"([^"]*)"') { $hrefVal = $Matches[1] }
            elseif ($tag -match "href\s*=\s*'([^']*)'") { $hrefVal = $Matches[1] }
            if (-not $hrefVal) { continue }

            if ($hrefRegex.IsMatch($hrefVal)) {
                # need to add icon-btn to this tag
                if ($tag -match '\bclass\s*=\s*"([^"]*)"') {
                    $cls = $Matches[1]
                    $newCls = ($cls + ' icon-btn').Trim()
                    $newTag = [regex]::Replace($tag, '\bclass\s*=\s*"([^"]*)"', 'class="' + $newCls + '"')
                } elseif ($tag -match "\bclass\s*=\s*'([^']*)'") {
                    $cls = $Matches[1]
                    $newCls = ($cls + ' icon-btn').Trim()
                    $newTag = [regex]::Replace($tag, "\bclass\s*=\s*'([^']*)'", "class='" + $newCls + "'")
                } else {
                    $newTag = $tag -replace '<a', '<a class="icon-btn"'
                }
                if ($newTag -ne $tag) {
                    $text = $text.Replace($tag, $newTag)
                }
            }
        }

        if ($text -ne $orig) {
            Copy-Item -LiteralPath $file.FullName -Destination ($file.FullName + '.bak') -Force
            Set-Content -LiteralPath $file.FullName -Value $text -Encoding UTF8
            $changedFiles += $file.FullName
            Write-Host "Updated: $($file.FullName)"
        }
    } catch {
        Write-Warning "Failed to process $($file.FullName): $_"
    }
}

Write-Host "Done. Files changed: $($changedFiles.Count)"
if ($changedFiles.Count -gt 0) { $changedFiles | ForEach-Object { Write-Host " - $_" } }
Pop-Location
