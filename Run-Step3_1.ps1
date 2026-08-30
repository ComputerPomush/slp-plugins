<#
    Run-Step3_1.ps1
    Execution wrapper for Publish-Step3_1.ps1 (v0.0.7).

    Handles the two things that reliably bite when a .ps1 arrives from
    outside the machine:
      1. Downloaded files carry a Zone.Identifier stream; PowerShell refuses
         or prompts regardless of execution policy. Unblock-File strips it.
      2. Sets Bypass for THIS PROCESS ONLY. Nothing machine-wide changes.

    Put next to Publish-Step3_1.ps1 in the repo root:

        .\Run-Step3_1.ps1 -Verify     # checksums + line endings only
        .\Run-Step3_1.ps1 -DryRun     # full walkthrough, changes nothing
        .\Run-Step3_1.ps1             # stage, commit, tag, push
        .\Run-Step3_1.ps1 -NoPush     # commit and tag locally only

    If PowerShell blocks this file itself:

        powershell -NoProfile -ExecutionPolicy Bypass -File .\Run-Step3_1.ps1 -Verify
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
$target = Join-Path $here 'Publish-Step3_1.ps1'

if (-not (Test-Path $target)) {
    throw "Publish-Step3_1.ps1 not found next to this script.`nExpected: $target"
}

try {
    Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass -Force
    Write-Host "Execution policy set to Bypass for this process only." -ForegroundColor DarkGray
}
catch {
    Write-Warning "Could not set process execution policy: $($_.Exception.Message)"
    Write-Warning "If the next step fails: powershell -NoProfile -ExecutionPolicy Bypass -File .\Run-Step3_1.ps1"
}

foreach ($f in @($target, $MyInvocation.MyCommand.Path)) {
    try {
        if (Get-Item -Path $f -Stream Zone.Identifier -ErrorAction SilentlyContinue) {
            Unblock-File -Path $f
            Write-Host "Unblocked: $(Split-Path -Leaf $f)" -ForegroundColor DarkGray
        }
    }
    catch { }
}

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
