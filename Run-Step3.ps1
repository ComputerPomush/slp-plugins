<#
    Run-Step3.ps1
    Execution wrapper for Publish-Step3.ps1.

    Exists because two things reliably bite when a .ps1 arrives from outside
    the machine:

      1. Files downloaded through a browser carry a Zone.Identifier alternate
         data stream. PowerShell refuses to run them, or prompts, regardless of
         execution policy. Unblock-File strips it.
      2. Changing execution policy machine-wide to run one script is heavier
         than the job needs. This sets Bypass for THIS PROCESS ONLY; the
         moment the window closes, nothing has changed.

    Put this next to Publish-Step3.ps1 in the repo root and run one of:

        .\Run-Step3.ps1 -Verify     # checksums + line endings, changes nothing
        .\Run-Step3.ps1 -DryRun     # full walkthrough, stages/commits nothing
        .\Run-Step3.ps1             # stage, commit, tag, push
        .\Run-Step3.ps1 -NoPush     # stage, commit, tag, stop before push

    If PowerShell still refuses to run THIS file, launch it the long way:

        powershell -NoProfile -ExecutionPolicy Bypass -File .\Run-Step3.ps1 -Verify
#>

[CmdletBinding()]
param(
    [switch] $Verify,
    [switch] $DryRun,
    [switch] $NoPush,
    [string] $RepoRoot = 'D:\Temp\Projects\GitHub\slp-plugins'
)

$ErrorActionPreference = 'Stop'

$here   = Split-Path -Parent $MyInvocation.MyCommand.Path
$target = Join-Path $here 'Publish-Step3.ps1'

if (-not (Test-Path $target)) {
    throw "Publish-Step3.ps1 not found next to this script.`nExpected: $target"
}

# Set Bypass for this process only. Does not touch machine or user policy.
try {
    Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass -Force
    Write-Host "Execution policy set to Bypass for this process only." -ForegroundColor DarkGray
}
catch {
    Write-Warning "Could not set process execution policy: $($_.Exception.Message)"
    Write-Warning "If the next step fails, run: powershell -NoProfile -ExecutionPolicy Bypass -File .\Run-Step3.ps1"
}

# Strip the mark-of-the-web from both scripts if present.
foreach ($f in @($target, $MyInvocation.MyCommand.Path)) {
    try {
        if (Get-Item -Path $f -Stream Zone.Identifier -ErrorAction SilentlyContinue) {
            Unblock-File -Path $f
            Write-Host "Unblocked: $(Split-Path -Leaf $f)" -ForegroundColor DarkGray
        }
    }
    catch { }
}

# git must be on PATH. GitHub Desktop's bundled git often is not.
$git = Get-Command git -ErrorAction SilentlyContinue
if (-not $git) {
    throw @'
git is not on PATH in this window.

Install "Git for Windows", or open the shell that ships with GitHub Desktop
(Repository -> Open in Command Prompt), or add git to PATH for this session:

    $env:Path += ';C:\Program Files\Git\cmd'
'@
}
Write-Host "git: $($git.Source)" -ForegroundColor DarkGray

$splat = @{ RepoRoot = $RepoRoot }
if ($Verify) { $splat['VerifyOnly'] = $true }
if ($NoPush) { $splat['SkipPush']   = $true }
if ($DryRun) { $splat['WhatIf']     = $true }

Write-Host ''
& $target @splat
exit $LASTEXITCODE
