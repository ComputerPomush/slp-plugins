<#
.SYNOPSIS
    SLP Dealer Guard - commit the repo-hygiene paths the Publish-Step scripts
    have never staged.

.DESCRIPTION
    Publish-Step7.ps1 line 223 builds $toStage from two slp_avalon/ artefacts
    plus $TestFiles. Neither .gitignore nor Publish-Step<N>.ps1 has ever been
    in that list, in any Step script, so both have accumulated as working-tree
    noise across v0.0.8 through v0.0.11. Handoff rev 8 section 1 nevertheless
    lists "Publish-Step4..7.ps1" under "Repo root - committed", which the
    v0.0.11 transcript disproves: git status reports all four as untracked.

    This script closes that gap once, as its own commit, so it cannot be
    confused with a version bump.

    It refuses to run if the plugin artefacts differ from the v0.0.11 pins.
    A hygiene commit must not silently carry a code change.

    Two checks that Publish-Step7.ps1 does not make:

      1. Native exit codes are asserted after every git call. Step7 calls
         `git check-ignore` and `git commit` and reads neither $LASTEXITCODE.
         That is why the second -Mode Commit run in the v0.0.11 transcript
         printed "no changes added to commit", then continued into the byte
         integrity check and the push and reported success.

      2. `git ls-files -i -c --exclude-standard` lists TRACKED files that the
         current .gitignore matches. Committing a widened .gitignore is how a
         path stops being staged later without anything failing loudly, which
         is the shape of the v0.0.8 incident described in handoff section 5.

.PARAMETER Mode
    Verify   - report state and stop. The default.
    Commit   - verify, stage, commit, push.

.PARAMETER GitignoreOnly
    Stage only .gitignore and leave the Publish-Step scripts untracked. Use
    this if the publish scripts are deliberately local-only; note that handoff
    section 1 must then be corrected instead, because it claims otherwise.

.PARAMETER NormalizeTrailingNewline
    OFF by default, so bytes are preserved. The working-tree .gitignore is
    5 bytes, "*.log", with NO trailing newline. That works, but any later
    Add-Content or `echo >>` concatenates onto the *.log line instead of
    starting a new one, silently producing a pattern like "*.logbuild/out".
    Pass this switch to append a single LF first.

.EXAMPLE
    .\Publish-RepoHygiene.ps1 -Mode Verify
    .\Publish-RepoHygiene.ps1 -Mode Commit
#>

[CmdletBinding()]
param(
    [ValidateSet('Verify', 'Commit')]
    [string]$Mode = 'Verify',

    [string]$PluginRepo = 'D:\Temp\Projects\GitHub\slp-plugins',

    [switch]$GitignoreOnly,

    [switch]$NormalizeTrailingNewline
)

$ErrorActionPreference = 'Stop'

# The v0.0.11 pins, copied from Publish-Step7.ps1 $Expected. Asserted here as a
# NEGATIVE condition: this commit must leave them untouched.
$Frozen = @{
    'slp_avalon/assets/js/slp_avalon.js' = 'a6237b4f2c006964710f4b5362437c66'
    'slp_avalon/slp_avalon.php'          = '468bd00d86796abb8220fbf828b45f83'
    'slp_avalon/inc/class.slp_avalon.php' = 'defbb41312071472a7039da37651a0d4'
}

function Assert-Git {
    <#
        Every git call in this script is `& git` inline, never through a
        wrapper - PowerShell prefix-matches parameter names and will eat a git
        flag. This helper only inspects the exit code afterwards.
    #>
    param([string]$What, [int[]]$Allow = @(0))

    if ($Allow -notcontains $LASTEXITCODE) {
        throw "git $What exited $LASTEXITCODE"
    }
}

Write-Host ''
Write-Host 'SLP Dealer Guard - repo hygiene commit' -ForegroundColor Cyan
Write-Host ('-' * 78)

if (-not (Test-Path -LiteralPath $PluginRepo)) {
    throw "Plugin repo not found: $PluginRepo"
}

Push-Location $PluginRepo
try {

    # ------------------------------------------------- artefacts must be frozen
    Write-Host ''
    Write-Host 'Plugin artefacts unchanged at v0.0.11' -ForegroundColor Cyan
    $allOk = $true
    foreach ($rel in $Frozen.Keys | Sort-Object) {
        $full = Join-Path $PluginRepo $rel
        if (-not (Test-Path -LiteralPath $full)) {
            Write-Host ("  FAIL  MISSING: {0}" -f $rel) -ForegroundColor Red
            $allOk = $false
            continue
        }
        $md5 = (Get-FileHash -LiteralPath $full -Algorithm MD5).Hash.ToLower()
        if ($md5 -ne $Frozen[$rel]) {
            Write-Host ("  FAIL  {0}" -f $rel) -ForegroundColor Red
            Write-Host ("          {0} != {1}" -f $md5, $Frozen[$rel]) -ForegroundColor Red
            Write-Host  '          A hygiene commit must not carry a code change.' -ForegroundColor Red
            $allOk = $false
        } else {
            Write-Host ("  ok    {0,-40} {1}" -f $rel, $md5) -ForegroundColor Green
        }
    }

    # ---------------------------------------------------- what is actually here
    Write-Host ''
    Write-Host '.gitignore: HEAD vs working tree' -ForegroundColor Cyan

    $headCopy = & git show 'HEAD:.gitignore' 2>$null
    if ($LASTEXITCODE -ne 0) {
        Write-Host '  note  .gitignore is not tracked at HEAD - this will be an add.' -ForegroundColor Yellow
        $headCopy = $null
    } else {
        Assert-Git 'show HEAD:.gitignore'
    }

    if ($null -ne $headCopy) {
        Write-Host '  HEAD:' -ForegroundColor Gray
        $headCopy | ForEach-Object { Write-Host ("    {0}" -f $_) -ForegroundColor Gray }
    }

    $wtPath = Join-Path $PluginRepo '.gitignore'
    if (Test-Path -LiteralPath $wtPath) {
        $wtBytes = [System.IO.File]::ReadAllBytes($wtPath)
        Write-Host ("  working tree: {0} bytes, trailing newline: {1}" -f `
            $wtBytes.Length, ($wtBytes.Length -gt 0 -and $wtBytes[-1] -eq 10)) -ForegroundColor Gray
        Get-Content -LiteralPath $wtPath | ForEach-Object {
            Write-Host ("    {0}" -f $_) -ForegroundColor Gray
        }

        if ($NormalizeTrailingNewline -and $wtBytes.Length -gt 0 -and $wtBytes[-1] -ne 10) {
            if ($Mode -eq 'Commit') {
                [System.IO.File]::AppendAllText($wtPath, "`n")
                Write-Host '  note  appended a trailing LF (-NormalizeTrailingNewline).' -ForegroundColor Yellow
            } else {
                Write-Host '  note  would append a trailing LF on -Mode Commit.' -ForegroundColor Yellow
            }
        }
    } else {
        Write-Host '  FAIL  no .gitignore in the working tree.' -ForegroundColor Red
        $allOk = $false
    }

    # -------------------------------------------- does it ignore anything tracked
    Write-Host ''
    Write-Host 'Tracked files matched by the current .gitignore' -ForegroundColor Cyan
    $selfIgnored = & git ls-files -i -c --exclude-standard
    Assert-Git 'ls-files -i -c'
    if ($selfIgnored) {
        # Not fatal on its own - a tracked file stays tracked - but it means a
        # future `git add` on that path will be a silent no-op, which is the
        # failure mode section 5 describes.
        Write-Host '  WARN  these are tracked AND ignored:' -ForegroundColor Yellow
        $selfIgnored | ForEach-Object { Write-Host ("          {0}" -f $_) -ForegroundColor Yellow }
    } else {
        Write-Host '  ok    none' -ForegroundColor Green
    }

    # -------------------------------------------------------- assemble the list
    $toStage = @('.gitignore')
    if (-not $GitignoreOnly) {
        Get-ChildItem -LiteralPath $PluginRepo -Filter 'Publish-Step*.ps1' -File |
            Sort-Object Name |
            ForEach-Object { $toStage += $_.Name }
    }

    Write-Host ''
    Write-Host 'To stage' -ForegroundColor Cyan
    foreach ($p in $toStage) {
        $status = & git status --porcelain -- $p
        Assert-Git 'status --porcelain'
        if ($status) {
            Write-Host ("  {0}" -f $status.Trim()) -ForegroundColor Green
        } else {
            Write-Host ("  --    {0}  already clean, nothing to do" -f $p) -ForegroundColor Gray
        }
    }

    # check-ignore: 0 = something matched (bad here), 1 = nothing matched (good),
    # 128 = fatal. Step7 reads the stdout but not the code.
    $ignored = & git check-ignore -- @toStage
    Assert-Git 'check-ignore' -Allow @(0, 1)
    if ($ignored) {
        Write-Host ''
        Write-Host '  FAIL  .gitignore matches paths that must be committed:' -ForegroundColor Red
        $ignored | ForEach-Object { Write-Host ("          {0}" -f $_) -ForegroundColor Red }
        throw 'Remove those .gitignore entries, then re-run. Do not use -f.'
    }

    if (-not $allOk) {
        Write-Host ''
        Write-Host 'VERIFY FAILED - nothing committed.' -ForegroundColor Red
        exit 1
    }

    Write-Host ''
    Write-Host 'All checks passed.' -ForegroundColor Green

    if ($Mode -eq 'Verify') {
        Write-Host 'Verify only. Re-run with -Mode Commit when ready.' -ForegroundColor Yellow
        exit 0
    }

    # --------------------------------------------------------------- commit
    & git add -- @toStage
    Assert-Git 'add'

    $staged = & git diff --cached --name-only
    Assert-Git 'diff --cached'
    if (-not $staged) {
        Write-Host 'Nothing staged - the working tree was already clean. No commit made.' -ForegroundColor Yellow
        exit 0
    }

    & git commit -m 'repo: commit .gitignore and the Publish-Step scripts' -m @'
Neither path has ever appeared in a Publish-Step script's $toStage list, so
both have sat in the working tree since v0.0.8 while handoff rev 8 section 1
listed Publish-Step4..7.ps1 as committed. The v0.0.11 transcript shows all
four untracked and .gitignore modified.

No slp_avalon/ artefact is touched: the three v0.0.11 md5s are asserted
unchanged before staging.

Publish-Step<N>.ps1 is the tooling that produces every md5, CRLF and suite
claim in the handoff. Decision 28 requires those claims to be reproducible,
which they are not from a fresh clone while the scripts are local-only.
'@
    Assert-Git 'commit'

    Write-Host ''
    Write-Host 'After commit' -ForegroundColor Cyan
    foreach ($p in $toStage) {
        $status = & git status --porcelain -- $p
        Assert-Git 'status --porcelain'
        if ($status) {
            Write-Host ("  FAIL  still dirty: {0}" -f $status.Trim()) -ForegroundColor Red
            throw 'Commit did not take. Do NOT push.'
        }
        Write-Host ("  ok    {0}" -f $p) -ForegroundColor Green
    }

    # The artefacts must still hash-match HEAD. If .gitattributes stopped
    # applying, this catches it here rather than on the next SFTP deploy.
    foreach ($rel in $Frozen.Keys | Sort-Object) {
        $tree = (& git hash-object --no-filters -- $rel).Trim()
        Assert-Git 'hash-object'
        $head = (& git rev-parse "HEAD:$rel").Trim()
        Assert-Git 'rev-parse'
        if ($tree -ne $head) {
            Write-Host ("  FAIL  {0}: worktree {1} != HEAD {2}" -f $rel, $tree, $head) -ForegroundColor Red
            throw 'Byte integrity check failed. Do NOT push.'
        }
        Write-Host ("  ok    {0,-40} {1}" -f $rel, $head.Substring(0, 12)) -ForegroundColor Green
    }

    & git push origin HEAD
    Assert-Git 'push'

    Write-Host ''
    Write-Host 'Pushed. No deploy needed - nothing in this commit is served.' -ForegroundColor Cyan
    Write-Host 'Correct handoff section 1 to match: the publish scripts are now genuinely committed.' -ForegroundColor Gray

} finally {
    Pop-Location
}
