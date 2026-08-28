<#
.SYNOPSIS
    Gives the five placeholder "." commits in ComputerPomush/slp-plugins real
    messages, and adds annotated version tags.

.DESCRIPTION
    Five commits were pushed with "." as their entire message:

        8f1468f  2026-08-24 22:32   remove free slp-extended-data-manager
        0c040a7  2026-08-24 22:35   add SLP_Settings_image to store-locator-le
        2b6a5b9  2026-08-25 00:14   add slp-extended-data-manager-premium 6.1.1
        a3c8745  2026-08-26 00:52   Phase 0
        e629547  2026-08-27 15:35   Phase 0.6

    TWO WAYS TO FIX THIS
    ====================

    -Mode Notes    Non-destructive. Attaches git notes to the five commits and
                   creates the tags. No SHA changes, no force-push, GPG
                   signatures untouched, HANDOFF rev 1 / rev 2 SHAs stay valid.
                   DOWNSIDE: git notes do not render on github.com and are not
                   fetched by a default clone. The commit list still shows "."
                   to anyone browsing the repo on the web.

    -Mode All      Rewrites the five messages and creates the tags.
                   DOWNSIDES, all of them real:
                     * Every SHA from 8f1468f forward changes -- 7 commits,
                       including a537441 and ee29cd2 whose messages are fine.
                     * a537441 and ee29cd2 are GPG-signed by GitHub (web UI
                       commits). Those signatures CANNOT survive any rewrite,
                       because a signature covers the parent SHA. Two "Verified"
                       badges are lost. No tool can avoid this.
                     * Requires push --force-with-lease. Other clones must reset.
                     * Any document citing the old SHAs goes stale.

    HOW THE REWRITE WORKS
    =====================

    git commit-tree replay, not git filter-branch. Each commit in the range is
    rebuilt against its new parent with the same tree, the same author, the same
    committer and the same timestamps -- only the message differs. This is core
    plumbing, so unlike filter-branch (deprecated, removal repeatedly signalled)
    it cannot disappear from under you. It also avoids filter-branch's sh
    subshell, so paths containing spaces are fine.

    After the replay the script compares the root tree hash before and after and
    aborts if they differ by a byte, then diffs the result against the backup
    branch and requires an empty diff, then checks the commit count is unchanged.

    COMMITTER REATTRIBUTION
    =======================

    a537441 and ee29cd2 have committer "GitHub <noreply@github.com>" and are
    signed by GitHub. Replaying them faithfully would keep that committer while
    dropping the signature -- leaving two commits that claim GitHub committed
    them with nothing to prove it. By default the script sets committer = author
    on any commit whose committer is GitHub's noreply address. Pass
    -KeepWebCommitter to replay them verbatim instead.

.PARAMETER RepoPath
    Path to the local clone. Defaults to D:\Temp\Projects\GitHub\slp-plugins.

.PARAMETER Mode
    Preview  (default)  Print everything that would happen. Changes nothing.
    Notes               Non-destructive git notes + tags.
    Reword              Rewrite messages only.
    Tag                 Tags only. Safe standalone or after Reword.
    All                 Reword then Tag.

.PARAMETER Push
    Publish afterwards. Without it the script stops locally and prints the exact
    push commands so you can inspect first. Recommended: run without -Push.

.PARAMETER KeepWebCommitter
    Replay GitHub-committed commits verbatim rather than reattributing them.
    See COMMITTER REATTRIBUTION above.

.EXAMPLE
    .\Fix-SlpPluginsHistory.ps1
    Preview. Prints every message and tag it would write. Touches nothing.

.EXAMPLE
    .\Fix-SlpPluginsHistory.ps1 -Mode Notes
    The safe option. No SHA churn, no force-push, signatures intact.

.EXAMPLE
    .\Fix-SlpPluginsHistory.ps1 -Mode All
    Rewrites and tags locally. Review with git log, then push by hand.

.NOTES
    Safe to keep inside the repo -- untracked files no longer block preflight.
    Uses only commit-tree, update-ref, rev-list, notes and tag: long-stable
    plumbing. Algorithm verified against git 2.43 on this exact history.
#>

[CmdletBinding()]
param(
    [string] $RepoPath = 'D:\Temp\Projects\GitHub\slp-plugins',

    [ValidateSet('Preview', 'Notes', 'Reword', 'Tag', 'All')]
    [string] $Mode = 'Preview',

    [switch] $Push,

    [switch] $KeepWebCommitter
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

function Write-Step { param([string]$T) Write-Host "`n==> $T" -ForegroundColor Cyan }
function Write-Ok   { param([string]$T) Write-Host "    [ok]   $T" -ForegroundColor Green }
function Write-Warn { param([string]$T) Write-Host "    [warn] $T" -ForegroundColor Yellow }
function Write-Info { param([string]$T) Write-Host "    $T" -ForegroundColor Gray }

    # The parameter is named $GitArgv deliberately, NOT $Arguments. PowerShell
    # binds any unambiguous prefix of a parameter name, so with $Arguments the
    # token "-a" (git tag -a, git commit -a, ...) silently binds to -Arguments,
    # consumes the next token as its value, and leaves the git subcommand with
    # no positional slot -- "A positional parameter cannot be found that accepts
    # argument 'tag'". Nothing git passes short-flags -g/-G, so $GitArgv is safe.
    #
    # Still unsafe through this wrapper, because they prefix PowerShell's common
    # parameters: -p (PipelineVariable), -d (Debug), -v (Verbose). Ambiguous ones
    # (-e, -o, -w, -i) error loudly rather than binding silently. Call & git
    # natively for any of those.
function Invoke-Git {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]] $GitArgv)
    $out = & git @GitArgv 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "git $($GitArgv -join ' ') failed (exit ${LASTEXITCODE}):`n$out"
    }
    return $out
}

function Get-GitValue {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]] $GitArgv)
    return ((Invoke-Git @GitArgv) -join '').Trim()
}

$Utf8NoBom = New-Object System.Text.UTF8Encoding($false)

function Write-MessageFile {
    # LF endings, UTF8 without BOM, exactly one trailing newline.
    # A BOM or CRLF would end up inside the commit message.
    param([string]$Path, [string]$Text)
    $normalized = ($Text -replace "`r`n", "`n").TrimEnd("`n") + "`n"
    [System.IO.File]::WriteAllText($Path, $normalized, $Utf8NoBom)
}

# ===========================================================================
# Commit messages, keyed by original short SHA.
#
# Literal here-strings (@'...'@) -- no PowerShell interpolation, so $ and `
# in the prose are safe. No SHAs referenced inside the bodies, since those
# SHAs are about to change.
# ===========================================================================

$Messages = [ordered]@{}

$Messages['8f1468f'] = @'
Remove free slp-extended-data-manager ahead of premium swap

Deletes the free Extended Data Manager in full (202 files) so the premium
build can be added to a clean tree rather than layered over a partial one.
The premium replacement lands two commits later.

No functional change in isolation -- between this commit and the premium
import the repo has no EDM at all. The sites were never in this state; this
is a repo-only intermediate.
'@

$Messages['0c040a7'] = @'
Add SLP_Settings_image handler to store-locator-le

SLP's settings framework instantiates SLP_Settings_<type> by class name. No
handler shipped for type 'image', so image-type settings rendered as an empty
field with no PHP error and no admin notice. This adds the fallback handler,
which returns the raw custom HTML unmodified.

WARNING -- VENDOR CORE FILE

This adds a file inside the store-locator-le plugin directory. It is not part
of stock Store Locator Plus and WILL BE DELETED by the next SLP update. The
failure mode is silent.

Re-apply after every Store Locator Plus upgrade. Verify with:

    ls wp-content/plugins/store-locator-le/include/module/settings/SLP_Settings_image.php
'@

$Messages['2b6a5b9'] = @'
Add slp-extended-data-manager-premium 6.1.1

Vendor import of the premium Extended Data Manager, replacing the free build
removed two commits earlier. Includes the bundled Freemius SDK.

This is the version that has been running in production on all three sites.
It was active on the servers but absent from the repo, so the repo did not
match what was deployed. This import closes that gap.

Unmodified vendor tree -- no custom changes.
'@

$Messages['a3c8745'] = @'
Phase 0: consolidate slp_avalon, close the hover handler leak

Plugin-side half of Phase 0. The theme-side deletions of googlelocation.js,
inc/find-a-dealer.php and inc/slplus_customization.php (1,092 lines of dead
duplicate) live in the hello-elementor-child repo.

assets/js/slp_avalon.js
    Port the delegated mouseenter binding out of googlelocation.js before that
    file is deleted, and namespace it as .avalonHover with an .off() ahead of
    the loop.

    The previous binding attached a fresh non-delegated handler directly to the
    results markup on every search. SLP replaces that markup wholesale each
    search, so handlers accumulated -- one generation per search, all of them
    still firing. googlelocation.js was otherwise a strict subset of this file;
    this delegated binding was its only unique behaviour and had to be carried
    over rather than preserved in place.

inc/class.slp_avalon.php
    Drop two dead commented references to WPBF_CHILD_THEME_URI and
    WPBF_CHILD_VERSION, left from the Page Builder Framework child theme that
    is no longer installed.

slp_avalon.php
    Version 0.0.1 -> 0.0.2.

Deployed to Aura DEV and verified byte-exact by server-side md5.
'@

$Messages['e629547'] = @'
Phase 0.6: stop writing logs into the web root

Plugin-side half. Phase 0.5 (asset versioning via filemtime) was theme-side
only -- self::file_version() already existed here, so this commit carries no
0.5 change despite the two phases sharing a work session.

inc/class.slp_avalon.php
    SLP_Avalon::log() appended to slp_avalon/error.log inside the web root.
    WP Engine serves unknown extensions as plaintext, so that file was publicly
    readable -- confirmed by an HTTP 200 fetch of the live URL.

    Rerouted to error_log(), which under PHP-FPM writes to WP Engine's PHP error
    log outside the web root. Destination only; message format and every call
    site are unchanged.

    Also removes an unconditional self::log($current_screen->base) from
    admin_head(), which wrote a line on every single admin page load.

slp_avalon.php
    Version 0.0.2 -> 0.0.3.

Deployed to Aura DEV and verified byte-exact by server-side md5.
'@

# ===========================================================================
# Annotated tags. Targets resolve by subject line AFTER any rewrite, because
# the SHAs move.
# ===========================================================================

$Tags = @(
    @{
        Name       = 'v0.0.2'
        NewSubject = '^Phase 0: consolidate slp_avalon'
        OldShort   = 'a3c8745'
        Message    = @'
slp_avalon 0.0.2 -- Phase 0 (Consolidate)

Removed the duplicate googlelocation.js / find-a-dealer.php /
slplus_customization.php layer and closed the mouseenter handler accumulation
leak. slp_avalon is now the canonical implementation.

Theme-side deletions for this phase are in the hello-elementor-child repo.

Deployed and md5-verified on Aura DEV. Not promoted beyond DEV.
'@
    },
    @{
        Name       = 'v0.0.3'
        NewSubject = '^Add \.gitignore to exclude log files'
        OldShort   = 'ee29cd2'
        Message    = @'
slp_avalon 0.0.3 -- Phases 0.6 and 0.7

0.6  Log writes moved out of the publicly-served web root and into error_log().
     Both exposed log files deleted from the servers.
0.7  Repo hygiene: error.log untracked, .gitignore added for *.log.

Phases 0.5 and 0.7 also carry theme-side changes (avalon_asset_version,
per-file filemtime stylesheet versions) that live in the hello-elementor-child
repo.

Last release before the Dealer Guard. Phase 1 ships as v1.0.0.

Deployed and md5-verified on Aura DEV. Not promoted beyond DEV.
'@
    }
)

$GitHubNoReply = 'noreply@github.com'

# ===========================================================================
# Preflight
# ===========================================================================

Write-Step "Preflight"

if (-not (Get-Command git -ErrorAction SilentlyContinue)) { throw "git not found on PATH." }
Write-Ok "git found: $((& git --version))"

if (-not (Test-Path -LiteralPath $RepoPath)) { throw "Repo path does not exist: $RepoPath" }
Set-Location -LiteralPath $RepoPath
Write-Ok "repo path: $RepoPath"

if ((Get-GitValue rev-parse --is-inside-work-tree) -ne 'true') {
    throw "Not a git work tree: $RepoPath"
}

$branch = Get-GitValue rev-parse --abbrev-ref HEAD
if ($branch -ne 'main') { throw "Expected branch 'main', found '$branch'." }
Write-Ok "on branch main"

# Untracked files cannot affect a history rewrite -- this script itself is very
# likely one of them. Only tracked modifications matter.
$dirty = @(Invoke-Git status --porcelain --untracked-files=no)
if ($dirty.Count -gt 0) {
    throw "Tracked files are modified. Commit or stash first:`n$($dirty -join "`n")"
}
Write-Ok "no tracked modifications"

$untracked = @(Invoke-Git ls-files --others --exclude-standard)
if ($untracked.Count -gt 0) {
    Write-Warn "$($untracked.Count) untracked file(s) present -- ignored, they cannot affect the rewrite:"
    $untracked | Select-Object -First 5 | ForEach-Object { Write-Info "  $_" }
}

Invoke-Git fetch origin --quiet | Out-Null
$localHead  = Get-GitValue rev-parse HEAD
$remoteHead = Get-GitValue rev-parse origin/main

if ($localHead -ne $remoteHead) {
    & git merge-base --is-ancestor $remoteHead $localHead 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw @"
Local main and origin/main have diverged, or local is behind.
  local  HEAD  = $localHead
  origin/main  = $remoteHead
Reconcile before touching history.
"@
    }
    $ahead = Get-GitValue rev-list --count "$remoteHead..$localHead"
    Write-Warn "local main is $ahead commit(s) ahead of origin/main -- those get replayed too"
}
else {
    Write-Ok "main == origin/main ($($localHead.Substring(0,7)))"
}

# Resolve targets; skip any already handled so the script is re-runnable.
# Reachability matters: after a successful rewrite the ORIGINAL commits still
# resolve by SHA (they survive in the object store, reachable from the backup
# branch) and still carry ".". Without this check a re-run would report five
# placeholders that are no longer part of main's history.
$Resolved = [ordered]@{}
foreach ($short in $Messages.Keys) {
    $full = $null
    try { $full = Get-GitValue rev-parse $short } catch { }
    if (-not $full) {
        Write-Warn "$short no longer resolves -- already rewritten and pruned, skipping"
        continue
    }

    & git merge-base --is-ancestor $full HEAD 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) {
        Write-Warn "$short is not in main's history -- already rewritten, skipping"
        continue
    }

    $subj = Get-GitValue log -1 --format=%s $full
    if ($subj -ne '.') {
        Write-Warn "$short subject is '$subj', not '.' -- already handled, skipping"
        continue
    }
    $Resolved[$short] = $full
}
Write-Ok "$($Resolved.Count) commit(s) still carry a placeholder message"

if ($Resolved.Count -eq 0 -and $Mode -in @('Reword', 'Notes')) {
    Write-Warn "Nothing to do for messages. Use -Mode Tag for tags alone."
    return
}

$oldestShort = if ($Resolved.Count -gt 0) { @($Resolved.Keys)[0] } else { $null }
$treeBefore  = Get-GitValue rev-parse 'HEAD^{tree}'

if ($oldestShort) {
    $base = Get-GitValue rev-parse "$oldestShort^"

    # Merge commits would need every parent remapped. This history is linear;
    # refuse rather than silently flatten if that ever stops being true.
    $merges = @(Invoke-Git rev-list --merges "$base..HEAD")
    if ($merges.Count -gt 0) {
        throw "Merge commits in the rewrite range; this script handles linear history only:`n$($merges -join "`n")"
    }
    Write-Ok "rewrite range is linear"

    # Signatures no rewrite can preserve.
    $signed = @()
    foreach ($line in @(Invoke-Git rev-list "$base..HEAD")) {
        $c = $line.Trim(); if (-not $c) { continue }
        if ((Get-GitValue log -1 --format=%G? $c) -ne 'N') { $signed += $c }
    }
    if ($signed.Count -gt 0 -and $Mode -in @('Reword', 'All', 'Preview')) {
        Write-Warn "$($signed.Count) commit(s) in range are GPG-signed. A rewrite DROPS those signatures:"
        foreach ($c in $signed) {
            Write-Info "  $($c.Substring(0,7))  $(Get-GitValue log -1 --format=%s $c)"
        }
        Write-Info "  (unavoidable -- a signature covers the parent SHA. -Mode Notes preserves them.)"
    }
}

# ===========================================================================
# Self-test
#
# Proves that commit-tree actually receives its -p argument in THIS shell
# before any ref is moved. PowerShell's parameter binder has swallowed "-p"
# before (see the note in the replay loop); a dropped parent produces a root
# commit whose tree is still correct, so tree and diff checks cannot catch it.
# The probe object is dangling and unreferenced -- gc reclaims it.
# ===========================================================================

Write-Step "Self-test: commit-tree parent linkage"

$probeTree   = Get-GitValue rev-parse 'HEAD^{tree}'
$probeParent = Get-GitValue rev-parse HEAD
$probeMsg    = [System.IO.Path]::GetTempFileName()
try {
    Write-MessageFile -Path $probeMsg -Text 'probe: verifying commit-tree parent linkage'

    $probeOut = & git commit-tree $probeTree -p $probeParent -F $probeMsg 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "commit-tree self-test could not create a probe object:`n$($probeOut -join "`n")"
    }
    $probeSha = ($probeOut | Where-Object { $_ -match '^[0-9a-f]{40}$' } | Select-Object -First 1)
    if (-not $probeSha) {
        throw "commit-tree self-test returned no SHA:`n$($probeOut -join "`n")"
    }

    $probeGotParent = Get-GitValue log -1 --format=%P $probeSha
    if ($probeGotParent -ne $probeParent) {
        throw @"
commit-tree self-test FAILED. Nothing was modified.

  expected parent : $probeParent
  actual parent   : '$probeGotParent'

The -p argument is not reaching git in this shell, so every replayed commit
would become a parentless root commit and history would collapse. Do not run
-Mode Reword or -Mode All until this passes. -Mode Notes is unaffected.
"@
    }
    Write-Ok "parent linkage verified (probe $($probeSha.Substring(0,7)) is dangling; gc reclaims it)"
}
finally {
    Remove-Item -LiteralPath $probeMsg -Force -ErrorAction SilentlyContinue
}

# ===========================================================================
# Preview
# ===========================================================================

if ($Mode -eq 'Preview') {
    Write-Step "PREVIEW -- nothing will be modified"
    foreach ($short in $Resolved.Keys) {
        $d = Get-GitValue log -1 --format=%ad --date=short $Resolved[$short]
        Write-Host ("`n--- $short  ($d) " + ('-' * 44)) -ForegroundColor Magenta
        Write-Host $Messages[$short]
    }
    Write-Step "Tags"
    foreach ($t in $Tags) {
        Write-Host "`n--- $($t.Name)  -> $($t.OldShort) " -ForegroundColor Magenta
        Write-Host $t.Message
    }
    Write-Host "`n-Mode Notes  non-destructive: no SHA churn, signatures kept, invisible on github.com" -ForegroundColor Cyan
    Write-Host "-Mode All    rewrite + tag: SHAs change, 2 signatures lost, reads properly on the web`n" -ForegroundColor Cyan
    return
}

# ===========================================================================
# Notes -- non-destructive
# ===========================================================================

if ($Mode -eq 'Notes') {
    Write-Step "Attaching git notes"
    foreach ($short in $Resolved.Keys) {
        $sha = $Resolved[$short]
        $tmp = [System.IO.Path]::GetTempFileName()
        try {
            Write-MessageFile -Path $tmp -Text $Messages[$short]
            & git notes add -f -F $tmp $sha 2>&1 | Out-Null
            if ($LASTEXITCODE -ne 0) { throw "git notes add failed for $short" }
        }
        finally { Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue }
        Write-Ok "note attached to $short"
    }
    Write-Info ""
    Write-Info "Notes appear in git log locally but NOT on github.com."
    Write-Info "Publish with:   git push origin refs/notes/commits"
    Write-Info "Other clones:   git fetch origin refs/notes/*:refs/notes/*"
}

# ===========================================================================
# Reword -- commit-tree replay
# ===========================================================================

$backupBranch = $null

if ($Mode -in @('Reword', 'All') -and $Resolved.Count -gt 0) {

    Write-Step "Backup"
    $backupBranch = "backup/pre-reword-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
    Invoke-Git branch $backupBranch | Out-Null
    Write-Ok "created $backupBranch at $($localHead.Substring(0,7))"
    Write-Info "roll back with:  git reset --hard $backupBranch"

    Write-Step "Replaying commits"
    $base      = Get-GitValue rev-parse "$oldestShort^"
    $newParent = $base
    $shaMap    = [ordered]@{}
    $tmpMsg    = [System.IO.Path]::GetTempFileName()

    # Save the caller's ident env so it can be restored afterwards.
    $identVars = @('GIT_AUTHOR_NAME','GIT_AUTHOR_EMAIL','GIT_AUTHOR_DATE',
                   'GIT_COMMITTER_NAME','GIT_COMMITTER_EMAIL','GIT_COMMITTER_DATE')
    $savedEnv = @{}
    foreach ($v in $identVars) { $savedEnv[$v] = [Environment]::GetEnvironmentVariable($v) }

    try {
        foreach ($line in @(Invoke-Git rev-list --reverse "$base..HEAD")) {
            $c = $line.Trim()
            if (-not $c) { continue }

            $tree = Get-GitValue rev-parse "$c^{tree}"

            # Each ident field read individually -- no shell, no word splitting.
            # "Computer Pomush" (with a space) must survive intact.
            $an = Get-GitValue log -1 --format=%an $c
            $ae = Get-GitValue log -1 --format=%ae $c
            $ad = Get-GitValue log -1 --format=%ad --date=raw $c
            $cn = Get-GitValue log -1 --format=%cn $c
            $ce = Get-GitValue log -1 --format=%ce $c
            $cd = Get-GitValue log -1 --format=%cd --date=raw $c

            $note = ''
            if (-not $KeepWebCommitter -and $ce -eq $GitHubNoReply) {
                # Signature is being dropped; don't leave GitHub named as committer.
                $cn = $an; $ce = $ae
                $note = '  (committer reattributed)'
            }

            $short = $c.Substring(0, 7)
            $match = $Resolved.GetEnumerator() | Where-Object { $_.Value -eq $c } | Select-Object -First 1
            if ($match) {
                Write-MessageFile -Path $tmpMsg -Text $Messages[$match.Key]
            }
            else {
                Write-MessageFile -Path $tmpMsg -Text ((Invoke-Git log -1 --format=%B $c) -join "`n")
            }

            $env:GIT_AUTHOR_NAME     = $an
            $env:GIT_AUTHOR_EMAIL    = $ae
            $env:GIT_AUTHOR_DATE     = $ad
            $env:GIT_COMMITTER_NAME  = $cn
            $env:GIT_COMMITTER_EMAIL = $ce
            $env:GIT_COMMITTER_DATE  = $cd

            # Invoked via & git directly, NOT through Invoke-Git/Get-GitValue.
            # Those are advanced functions ([Parameter] attributes), so PowerShell
            # adds the common parameters -- and "-p" is an unambiguous prefix of
            # -PipelineVariable, the only common parameter starting with P. Through
            # the wrapper, "-p <sha>" is swallowed by PowerShell and never reaches
            # git, which then builds a PARENTLESS root commit. The tree is still
            # correct, so neither a tree-hash nor a content-diff check detects it.
            # Native invocation does no parameter binding, so -p passes through.
            $expectedParent = $newParent
            $ctOut = & git commit-tree $tree -p $expectedParent -F $tmpMsg 2>&1
            if ($LASTEXITCODE -ne 0) {
                throw "git commit-tree failed for $($c.Substring(0,7)):`n$($ctOut -join "`n")"
            }
            $newParent = ($ctOut | Where-Object { $_ -match '^[0-9a-f]{40}$' } | Select-Object -First 1)
            if (-not $newParent) {
                throw "git commit-tree returned no SHA for $($c.Substring(0,7)):`n$($ctOut -join "`n")"
            }

            # Fail on the FIRST commit if the parent link did not take, rather
            # than discovering a collapsed history seven commits later.
            $gotParent = Get-GitValue log -1 --format=%P $newParent
            if ($gotParent -ne $expectedParent) {
                throw @"
commit-tree produced $($newParent.Substring(0,7)) with parent '$gotParent',
expected '$expectedParent'. The parent link was not applied, which would
collapse history into a root commit. No refs were moved.
"@
            }

            $shaMap[$short] = $newParent.Substring(0, 7)
            Write-Ok "$short -> $($newParent.Substring(0,7))$note"
        }
    }
    finally {
        Remove-Item -LiteralPath $tmpMsg -Force -ErrorAction SilentlyContinue
        foreach ($v in $identVars) { [Environment]::SetEnvironmentVariable($v, $savedEnv[$v]) }
    }

    # -m supplies the reflog reason. The THIRD positional arg of update-ref is the
    # expected OLD sha (a compare-and-swap guard), not a message -- passing a string
    # there fails with "not a valid old SHA1". Pass $localHead as that guard so the
    # update is refused if anything moved main underneath us.
    Invoke-Git update-ref -m "rewritten by Fix-SlpPluginsHistory" refs/heads/main $newParent $localHead | Out-Null
    Invoke-Git reset --hard HEAD | Out-Null

    Write-Step "Verifying"

    $movedTo = Get-GitValue rev-parse HEAD
    if ($movedTo -ne $newParent) {
        throw "HEAD is $movedTo but should be $newParent. Roll back: git reset --hard $backupBranch"
    }
    Write-Ok "main advanced to $($movedTo.Substring(0,7))"

    $treeAfter = Get-GitValue rev-parse 'HEAD^{tree}'
    if ($treeAfter -ne $treeBefore) {
        throw @"
TREE HASH CHANGED -- file content was modified. This must never happen.
  before = $treeBefore
  after  = $treeAfter
Roll back NOW:  git reset --hard $backupBranch
"@
    }
    Write-Ok "root tree hash unchanged ($($treeAfter.Substring(0,12))) -- messages only"

    $diff = @(Invoke-Git diff --stat $backupBranch HEAD)
    if ($diff.Count -gt 0) {
        throw "Unexpected content diff vs backup:`n$($diff -join "`n")`nRoll back: git reset --hard $backupBranch"
    }
    Write-Ok "zero content diff against the backup branch"

    $countBefore = Get-GitValue rev-list --count $backupBranch
    $countAfter  = Get-GitValue rev-list --count HEAD
    if ($countBefore -ne $countAfter) {
        throw "Commit count changed ($countBefore -> $countAfter). Roll back: git reset --hard $backupBranch"
    }
    Write-Ok "commit count unchanged ($countAfter)"

    Write-Step "SHA map"
    foreach ($k in $shaMap.Keys) { Write-Info "$k -> $($shaMap[$k])" }
    Write-Warn "HANDOFF rev 1 and rev 2 cite the old SHAs -- they are now stale"

    Write-Step "New history"
    & git --no-pager log --format='%h  %ad  %s' --date=short -8 | ForEach-Object { Write-Info $_ }
}

# ===========================================================================
# Tags
# ===========================================================================

$createdTags = @()

if ($Mode -in @('Tag', 'All', 'Notes')) {
    Write-Step "Annotated tags"
    foreach ($t in $Tags) {

        if (@(Invoke-Git tag --list $t.Name).Count -gt 0) {
            Write-Warn "$($t.Name) already exists -- skipping (delete it first to recreate)"
            continue
        }

        # Resolve by subject: the SHA may have moved during the replay.
        # Built as a variable first -- "--grep=$t.NewSubject" is a bareword
        # expandable string, where only $t expands and ".NewSubject" stays
        # literal. Git would then match nothing and silently skip the tag.
        $grepArg = "--grep=$($t.NewSubject)"
        $hits = @(& git rev-list -1 $grepArg HEAD)
        if ($LASTEXITCODE -ne 0 -or $hits.Count -eq 0 -or -not $hits[0]) {
            Write-Warn "no commit matched /$($t.NewSubject)/ -- skipping $($t.Name)"
            continue
        }
        $sha = $hits[0].Trim()

        $tmp = [System.IO.Path]::GetTempFileName()
        try {
            Write-MessageFile -Path $tmp -Text $t.Message
            # Native invocation: "-a" would otherwise prefix-bind to the wrapper's
            # own parameter and swallow the tag name. See the note on Invoke-Git.
            $tagOut = & git tag -a $t.Name -F $tmp $sha 2>&1
            if ($LASTEXITCODE -ne 0) {
                throw "git tag -a $($t.Name) failed:`n$($tagOut -join "`n")"
            }
        }
        finally { Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue }

        # Confirm the tag exists, is annotated, and points where intended.
        $tagCommit = Get-GitValue rev-parse "$($t.Name)^{commit}"
        if ($tagCommit -ne $sha) {
            throw "tag $($t.Name) resolves to $tagCommit but should be $sha"
        }
        $tagType = Get-GitValue cat-file -t $t.Name
        if ($tagType -ne 'tag') {
            throw "tag $($t.Name) is a $tagType, not an annotated tag object"
        }

        $createdTags += $t.Name
        Write-Ok "$($t.Name) -> $($sha.Substring(0,7))  $(Get-GitValue log -1 --format=%s $sha)"
    }
}

# ===========================================================================
# Push
# ===========================================================================

if ($Push) {
    Write-Step "Pushing"

    if ($backupBranch) {
        Invoke-Git push origin $backupBranch | Out-Null
        Write-Ok "pushed $backupBranch"
    }

    if ($Mode -in @('Reword', 'All')) {
        Write-Warn "force-with-lease on main -- any other clone will need a hard reset"
        Invoke-Git push --force-with-lease origin main | Out-Null
        Write-Ok "pushed main (rewritten)"
    }

    if ($Mode -eq 'Notes') {
        Invoke-Git push origin refs/notes/commits | Out-Null
        Write-Ok "pushed refs/notes/commits"
    }

    foreach ($tag in $createdTags) {
        Invoke-Git push origin $tag | Out-Null
        Write-Ok "pushed $tag"
    }
}
else {
    Write-Step "Not pushed"
    Write-Info "Review:  git log --stat -8"
    Write-Info ""
    Write-Info "Publish with:"
    if ($backupBranch)                { Write-Info "  git push origin $backupBranch" }
    if ($Mode -in @('Reword', 'All')) { Write-Info "  git push --force-with-lease origin main" }
    if ($Mode -eq 'Notes')            { Write-Info "  git push origin refs/notes/commits" }
    foreach ($tag in $createdTags)    { Write-Info "  git push origin $tag" }
}

Write-Step "Done"
if ($backupBranch) {
    Write-Info "Backup   : $backupBranch"
    Write-Info "Roll back: git reset --hard $backupBranch"
    Write-Info ""
    Write-Info "Once satisfied and pushed, reclaim the old objects:"
    Write-Info "  git branch -D $backupBranch"
    Write-Info "  git reflog expire --expire=now --all; git gc --prune=now"
}
Write-Host ""
