<#
.SYNOPSIS
    SLP Dealer Guard - commit the repo-hygiene paths the Publish-Step scripts
    have never staged.

.DESCRIPTION
    Publish-Step7.ps1 line 223 builds $toStage from two slp_avalon/ artefacts
    plus $TestFiles. Neither .gitignore nor Publish-Step<N>.ps1 has ever been
    in that list, in any Step script, so both accumulated as working-tree noise
    from v0.0.8 onward while handoff rev 8 section 1 listed the publish scripts
    as committed.

    Most of that gap is now closed. A -Mode Verify run at v0.0.13 reports
    .gitignore, Publish-Step3.ps1, Publish-Step3_1.ps1, Publish-Step4..8.ps1
    and this script all tracked and clean, with Publish-Step9.ps1 the only
    remaining untracked path. Expect this script to find little or nothing to
    do; that is the intended end state, not a fault.

    It refuses to run if the plugin artefacts differ from HEAD, NOT from a set
    of pinned md5s - see the $Frozen comment below for why that distinction
    matters. A hygiene commit must not silently carry a code change.

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
    OFF by default, so bytes are preserved. Now a no-op in practice: as of
    v0.0.13 the working-tree .gitignore is 6 bytes, "*.log" plus a trailing
    LF, and HEAD's copy matches. It was 5 bytes with no trailing newline when
    this switch was written.

    Kept because the hazard is real and recurs. Without a trailing newline any
    later Add-Content or `echo >>` concatenates onto the *.log line instead of
    starting a new one, silently producing a pattern like "*.logbuild/out".
    style.css has exactly this shape today - 218,328 bytes ending in a closing
    brace - so the same care applies during the cosmetic pass.

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

# Artefacts this commit must not touch. Compared against HEAD, not against
# hardcoded md5s: a pinned hash goes stale on every version and then reports
# a legitimate, already-committed change as "a hygiene commit must not carry a
# code change." Comparing to HEAD asks the question that actually matters -
# is there an UNCOMMITTED artefact change riding along - and never needs
# editing again. It was pinned to v0.0.11, went stale at v0.0.12, and this is
# the fix rather than another manual bump.
$Frozen = @(
    'slp_avalon/assets/js/slp_avalon.js',
    'slp_avalon/slp_avalon.php',
    'slp_avalon/inc/class.slp_avalon.php'
)

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
    Write-Host 'Plugin artefacts match HEAD' -ForegroundColor Cyan
    $allOk = $true
    foreach ($rel in $Frozen) {
        if (-not (Test-Path -LiteralPath (Join-Path $PluginRepo $rel))) {
            Write-Host ("  FAIL  MISSING: {0}" -f $rel) -ForegroundColor Red
            $allOk = $false
            continue
        }
        # --no-filters so the comparison is the bytes on disk, not what
        # core.autocrlf would make of them. slp_avalon/** is -text anyway.
        $tree = (& git hash-object --no-filters -- $rel).Trim()
        Assert-Git 'hash-object'
        $head = (& git rev-parse "HEAD:$rel").Trim()
        Assert-Git 'rev-parse'

        if ($tree -ne $head) {
            Write-Host ("  FAIL  {0}" -f $rel) -ForegroundColor Red
            Write-Host ("          worktree {0}" -f $tree) -ForegroundColor Red
            Write-Host ("          HEAD     {0}" -f $head) -ForegroundColor Red
            Write-Host  '          Uncommitted artefact change. Commit it with the' -ForegroundColor Red
            Write-Host  '          matching Publish-Step script first.' -ForegroundColor Red
            $allOk = $false
        } else {
            Write-Host ("  ok    {0,-40} {1}" -f $rel, $head.Substring(0, 12)) -ForegroundColor Green
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
        Get-ChildItem -LiteralPath $PluginRepo -Filter 'Publish-*.ps1' -File |
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

    & git commit -m 'repo: track Publish-Step9.ps1, correct four stale claims in the tooling' -m @'
Publish-Step9.ps1 is the last repo-root script still untracked. .gitignore and
Publish-Step3..Step8 were committed earlier; a -Mode Verify run at v0.0.13
reports every one of them clean and Step9 as untracked.

Four prose corrections ride with it. No logic changes anywhere.

Publish-Step9.ps1 - the -Mode Tag client checklist had three items that could
not pass as written and was missing a fourth. Item 4 expected a fresh load to
reproduce a manual search's result count, which Issue 22 makes impossible: the
first load of a page goes out as csl_ajax_onload and honours radius, every
later search goes as csl_ajax_search and does not. Item 5 asked for a
dealerless search, which Decision 29's uncapped backfill makes unreachable.
Item 6 called the address bar "clean" after a Tijuana rejection, when
constraint C1 requires every attribution key to survive. The all-caps MICHIGAN
case was absent. Replaced with the seven items from handoff rev 10 section 8.

Publish-Step9.ps1 - the tag refusal printed "Not tagged." with no diagnostic
after a lowercase yes, three times in a row. It now prints what it expected
and what it received.

Publish-RepoHygiene.ps1 - the description claimed .gitignore and every
Publish-Step script were untracked, and that the artefact check compares
against the v0.0.11 md5 pins. It compares against HEAD, which is what stopped
it going stale at v0.0.12. -NormalizeTrailingNewline described a 5-byte
.gitignore with no trailing newline; it is 6 bytes with one, so the switch is
now a no-op.

test/suite-core.js - the territory_boxes() line reference read 1103-1122,
exact at v0.0.11 and stale since build-v012.py inserted 141 lines above the
method. It is 1244-1263 at v0.0.13, and the comment now says the method name
is the anchor rather than the range.

No slp_avalon/ artefact is touched: all three are asserted byte-identical to
HEAD before staging.
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
    foreach ($rel in $Frozen) {
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
