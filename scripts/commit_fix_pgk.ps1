<#
Script: commit_fix_pgk.ps1
Purpose: create a branch, commit the changes to `assets/js/script.js` and `README-DEPLOY.md`, and push to remote if available.
Usage (PowerShell):
  Set-Location 'C:\xampp\htdocs\lms_kgb2'
  .\scripts\commit_fix_pgk.ps1
#>

param(
    [string]$BranchName = 'fix/pgk-duplicate',
    [string]$CommitMessage = 'Fix: remove PGK fallback and debug log; rely on template controls'
)

function Abort([string]$msg){ Write-Host "ERROR: $msg" -ForegroundColor Red; exit 1 }

# Ensure git exists
try {
    $git = & git --version 2>$null
} catch {
    Abort 'Git not found. Install Git for Windows from https://git-scm.com/download/win and ensure `git` is on PATH.'
}

# Ensure script running from repo root (heuristic: has .git or assets/js/script.js)
$cwd = Get-Location
if (-not (Test-Path -Path (Join-Path $cwd '.git')) -and -not (Test-Path -Path (Join-Path $cwd 'assets\js\script.js'))) {
    Write-Host "It looks like you're not running from the project root. Changing to script directory path..." -ForegroundColor Yellow
    $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
    $repoRoot = Resolve-Path -Path (Join-Path $scriptDir '..')
    Set-Location $repoRoot
}

Write-Host "Using working directory: $(Get-Location)" -ForegroundColor Green

# Show git status
& git status --porcelain

# Create branch
$branchExists = (& git rev-parse --verify --quiet $BranchName) -ne $null
if (-not $branchExists) {
    Write-Host "Creating branch '$BranchName'..."
    & git checkout -b $BranchName
} else {
    Write-Host "Branch '$BranchName' already exists, checking it out..."
    & git checkout $BranchName
}

# Stage files
Write-Host 'Staging changed files...'
& git add assets/js/script.js README-DEPLOY.md 2>$null

# Commit
try {
    & git commit -m "$CommitMessage"
} catch {
    Write-Host 'Nothing to commit or commit failed. Please check `git status`.' -ForegroundColor Yellow
}

# Push if remote exists
$remote = (& git remote) -join ' '
if ($remote) {
    Write-Host "Remote(s) detected: $remote. Pushing branch to origin..."
    & git push -u origin $BranchName
} else {
    Write-Host 'No git remote configured. Commit is local only.' -ForegroundColor Yellow
}

Write-Host 'Done.' -ForegroundColor Green
