<#
.SYNOPSIS
    Gives the five placeholder "." commits at the tip of ComputerPomush/slp-plugins
    real messages, and adds the v0.0.4 tag.

.DESCRIPTION
    A second batch of five commits was pushed with "." as the entire message.
    All five are Phase 1 work plus the previous history script:

        a01687d  2026-08-27 23:22   Fix-SlpPluginsHistory.ps1 (+767)
        7cc862d  2026-08-27 23:44   Step 1.0  guard state machine   (+373/-12)
        6e1b870  2026-08-28 12:56   Step 1.1  notification move     (+75/-16)
        078a073  2026-08-28 22:22   Step 1.2  sidebar prompt        (+31/-11)
        ce3a22d  2026-08-29 13:06   Step 2    Layer 3 gate          (+219/-1)

    WHY THIS RUN IS MUCH SAFER THAN THE FIRST ONE
    =============================================

    The first script had to rewrite 7 commits to fix 5, lost two GitHub
    "Verified" badges, and had to repair tags afterwards. None of that applies
    here. Verified against the live repo on 2026-08-29:

      * All five targets are UNSIGNED (git log --format=%G? returns N for each).
        There is no signature to lose.
      * All five have committer ComputerPomush, not GitHub's noreply address.
        No committer reattribution logic is needed, so -KeepWebCommitter is gone.
      * The range is f347dd1..HEAD, i.e. the tip. Every commit in it is a target.
        No commit with a good message gets its SHA churned.
      * v0.0.2 (5807af7) and v0.0.3 (f347dd1) both point at commits BEFORE the
        range. They survive untouched and need no repair.

    Net effect: five SHAs change, nothing else. HANDOFF documents citing SHAs
    from a01687d forward go stale; anything citing f347dd1 or earlier stays valid.

    HOW THE REWRITE WORKS
    =====================

    git commit-tree replay, same plumbing as the first script. Each commit is
    rebuilt against its new parent with the same tree, same author, same
    committer and the same timestamps -- only the message differs.

    The algorithm in this script was executed against a clone of the real
    history before the script was written. Result: root tree hash identical
    (ebf165616d...), git diff backup..HEAD empty, commit count 38 before and
    after, both tags still resolving to f347dd1 and 5807af7.

    SAFETY GATES
    ============

    Preflight   clean worktree, on main, remote reachable, local main == remote
                main, all five target SHAs resolve and still have message "."
    Backup      refs/heads/backup/pre-reword-<timestamp> created before any write
    Self-test   replays into a scratch ref first and asserts the scratch tree
                matches, before touching refs/heads/main
    Post-check  root tree hash equal, git diff vs backup empty, commit count
                equal, every rewritten commit's parent asserted, all five new
                messages read back and confirmed non-"."

    Any failure throws before refs/heads/main is moved.

.PARAMETER RepoPath
    Path to the local clone. Defaults to D:\Temp\Projects\GitHub\slp-plugins.

.PARAMETER Mode
    Preview  (default)  Print everything that would happen. Changes nothing.
    Notes               Non-destructive git notes + tag. No SHA churn, no force
                        push. Downside unchanged from the first script: notes do
                        not render on github.com and are not fetched by a default
                        clone, so the web UI still shows ".".
    Reword              Rewrite the five messages only.
    Tag                 Create v0.0.4 only. Safe standalone or after Reword.
    All                 Reword then Tag.

.PARAMETER Push
    Publish afterwards. Without it the script stops locally and prints the exact
    push commands. Recommended: run without -Push, inspect with git log, push by
    hand.

.EXAMPLE
    .\Fix-SlpPluginsHistory2.ps1
    Preview. Prints every message and the tag it would write. Touches nothing.

.EXAMPLE
    .\Fix-SlpPluginsHistory2.ps1 -Mode All
    Rewrites and tags locally. Review, then push by hand.

.NOTES
    Uses only rev-parse, rev-list, commit-tree, update-ref, notes, tag and
    for-each-ref: long-stable plumbing.

    Invoke-Git's parameter is named $GitArgv, not $Arguments, for the reason
    documented in the first script: PowerShell binds unambiguous prefixes, so
    $Arguments would swallow "-a". Short flags -p, -d and -v are still unsafe
    through the wrapper because they prefix PowerShell common parameters, so
    every call that needs one uses & git natively.
#>

[CmdletBinding()]
param(
    [string] $RepoPath = 'D:\Temp\Projects\GitHub\slp-plugins',

    [ValidateSet('Preview', 'Notes', 'Reword', 'Tag', 'All')]
    [string] $Mode = 'Preview',

    [switch] $Push
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

function Write-Step { param([string]$T) Write-Host "`n==> $T" -ForegroundColor Cyan }
function Write-Ok   { param([string]$T) Write-Host "    [ok]   $T" -ForegroundColor Green }
function Write-Warn { param([string]$T) Write-Host "    [warn] $T" -ForegroundColor Yellow }
function Write-Fail { param([string]$T) Write-Host "    [FAIL] $T" -ForegroundColor Red }
function Write-Info { param([string]$T) Write-Host "    $T" -ForegroundColor Gray }

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

function Write-MessageFile {
    # commit-tree reads the message from stdin. Piping a PowerShell string adds a
    # BOM and CRLF under some hosts, both of which end up inside the commit
    # object. Write UTF8-no-BOM with LF to a temp file and redirect instead.
    param([string]$Path, [string]$Text)
    $lf  = ($Text -replace "`r`n", "`n")
    $enc = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $lf, $enc)
}

# ---------------------------------------------------------------------------
# The range, and the messages.
#
# BaseSha is the parent of the first target and must NOT be rewritten. It is
# also where v0.0.3 points, which is why the tags survive.
# ---------------------------------------------------------------------------
$BaseSha = 'f347dd1'

$Targets = @(
    @{
        Sha     = 'a01687d'
        Subject = 'Add Fix-SlpPluginsHistory.ps1, the commit-tree history replay'
        Body    = @'
Tooling, not plugin code. This is the script that rewrote the first five
placeholder "." messages and added the annotated v0.0.2 and v0.0.3 tags.

Kept in the repo so the next person can see how the history was repaired
rather than wondering why five SHAs moved. Uses git commit-tree replay
rather than filter-branch: core plumbing that cannot be deprecated out from
under us, and no sh subshell, so paths with spaces are safe.
'@
    },
    @{
        Sha     = '7cc862d'
        Subject = 'Phase 1 Step 1.0: add the SLP Dealer Guard search state machine'
        Body    = @'
Introduces avalon_guard, a single owner for the search lifecycle:

    IDLE -> RESOLVING -> SEARCHING -> RESULTS | EMPTY | ERROR | TIMEOUT

Generation tokens so a stale callback from an abandoned search cannot drive
the UI. One 12-second ceiling armed in start() and deliberately not re-armed
by enter(), so the ceiling covers the whole cycle rather than each leg.

install_geocode_hook() wraps cslmap.process_geocode_response. Assigning to
the instance property covers both the real geocode path and the coords-spoof
path, because slp_core.js resolves that property at call time.

install_transport_hook() reissues slp.send_ajax with a .fail() leg gated on
state === SEARCHING. That closes the second spinner-hang path: slp_core.js
publishes location_search_processed on success AND on failure, but the
transport error itself was unhandled, so a dropped request left the spinner
running with no terminal state.
'@
    },
    @{
        Sha     = '6e1b870'
        Subject = 'Phase 1 Step 1.1: move the search notification above the field'
        Body    = @'
Google's .pac-container renders directly below #addressInput and was covering
the notification, so a failed search looked like nothing had happened. Insert
the message before #addy_in_address instead, with a fallback to the old
position if that wrapper is absent. Adds role="alert" so screen readers
announce it.

Wires REJECTED into finish()'s set_no_results() call. The state is unreachable
until Step 2, but wiring it here means the Layer 1 and Layer 3 rejections
inherit the empty-results presentation for free.

Adds layout_normalized and sidebar_default guards so the layout work is
idempotent and can safely run more than once.
'@
    },
    @{
        Sha     = '078a073'
        Subject = 'Phase 1 Step 1.2: capture the sidebar prompt as text, bootstrap at DOM ready'
        Body    = @'
SLP wraps the sidebar prompt in .text_below_map, which style.css hides so it
cannot flash during the page-load search. Re-emitting it under our own
.avalon_sidebar_prompt class is what keeps the desktop panel populated when a
search fails.

Store textContent rather than innerHTML. The prompt is copy, not markup, and
round-tripping innerHTML risked carrying SLP's hidden wrapper back in with it.

Run normalize_search_layout() and capture_sidebar_default() from
jQuery(document).ready rather than waiting for the Google Maps callback, so
the layout is correct before the map loads. Declared after avalon_guard
exists rather than in the IIFE at the top of the file: that block runs before
the object is assigned, and relying on jQuery deferring the callback long
enough would be a latent ordering trap. Both calls are idempotent and both
re-run at map-ready if the elements were not present yet.
'@
    },
    @{
        Sha     = 'ce3a22d'
        Subject = 'Phase 1 Step 2: add Layer 3, the server-side territory gate'
        Body    = @'
Layers 0 to 2 are client-side and can be bypassed by POSTing admin-ajax.php
directly. Verified: a POST with Paris coordinates returned three US dealers,
because SLP's own SQL with ignore_radius is ORDER BY sl_distance LIMIT n with
no radius bound, and the priority-10 backfill in this plugin guarantees three
results from anywhere on Earth.

territory_gate() filters slp_ajax_find_locations_complete at priority 20 and
zeroes count and response for coordinates outside US + PR + VI + GU + MP + CA,
setting avalon_territory_rejected so the client can tell a rejection from a
genuine empty result.

Priority 20, not below 10, is load-bearing. The priority-10 callback early
returns only when count >= 3; gating first would leave count at 0 and the
backfill would refill the response, defeating the gate. Enforcement must be
unconditionally last.

Seven bounding boxes rather than a server-side reverse geocode, which would
add a billable call and latency to every search for a check Layers 0 to 2
already make precisely. The boxes are coarse and admit northern Mexico, the
Bahamas, Bermuda and open ocean; that is accepted and Layer 1 closes it.
The seventh box covers the western Aleutians between -180 and -173, which
fall outside the Alaska box; widening Alaska instead would have admitted
Wrangel Island.

AVALON_TERRITORY_BOXES and avalon_in_territory() mirror the table in JS for
Layers 0 and 1 to share. on_search_processed() reads the rejection flag and
finishes as REJECTED with the territory message; without that branch the
payload is indistinguishable from EMPTY, which shows no message at all.

Kill switch: define SLP_AVALON_GUARD_DISABLE truthy in wp-config.php.
Plugin version 0.0.3 -> 0.0.4.

Verified on Aura DEV: Paris returns count 0 with the flag set, Detroit
returns count 3, kill switch restores the old behaviour.
'@
    }
)

$NewTags = @(
    @{
        Name    = 'v0.0.4'
        Sha     = 'ce3a22d'
        Message = @'
v0.0.4 - Phase 1 Steps 1.0 to 2

Search state machine with a 12s ceiling and generation tokens, notification
relocated above the field, sidebar prompt preserved across failed searches,
and Layer 3: the server-side territory gate at priority 20 with the shared
bounding-box table and the SLP_AVALON_GUARD_DISABLE kill switch.

Deployed and verified on Aura DEV 2026-08-29.
'@
    }
)

# ---------------------------------------------------------------------------
Write-Step "Repository"
if (-not (Test-Path -LiteralPath $RepoPath)) { throw "RepoPath not found: $RepoPath" }
Set-Location -LiteralPath $RepoPath
Write-Info "path : $RepoPath"
Write-Info "mode : $Mode"
Write-Info "push : $(if ($Push) { 'yes' } else { 'no (recommended)' })"

# ---------------------------------------------------------------------------
Write-Step "Preflight"

$inside = Get-GitValue rev-parse --is-inside-work-tree
if ($inside -ne 'true') { throw "Not a git work tree: $RepoPath" }
Write-Ok "inside a git work tree"

$branch = Get-GitValue rev-parse --abbrev-ref HEAD
if ($branch -ne 'main') { throw "On branch '$branch'. Check out main first." }
Write-Ok "on branch main"

$dirty = Get-GitValue status --porcelain --untracked-files=no
if ($dirty) { throw "Worktree has uncommitted tracked changes. Commit or stash first.`n$dirty" }
Write-Ok "worktree clean (tracked files)"

# git fetch prints progress on stderr; & git natively so Invoke-Git's exit
# check does not trip over it.
& git fetch --quiet origin 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) { throw "git fetch origin failed. Check network and credentials." }
Write-Ok "fetched origin"

$localMain  = Get-GitValue rev-parse main
$remoteMain = Get-GitValue rev-parse origin/main
if ($localMain -ne $remoteMain) {
    throw "main and origin/main differ. Pull or push first.`n  local  $localMain`n  remote $remoteMain"
}
Write-Ok "main == origin/main ($($localMain.Substring(0,7)))"

$baseFull = Get-GitValue rev-parse "$BaseSha^{commit}"
Write-Ok "base (not rewritten) $($baseFull.Substring(0,7))"

$rangeList = @(Invoke-Git rev-list --reverse "$baseFull..HEAD" | ForEach-Object { $_.Trim() } | Where-Object { $_ })
if ($rangeList.Count -ne $Targets.Count) {
    throw ("Range $BaseSha..HEAD holds $($rangeList.Count) commits but $($Targets.Count) messages are defined. " +
           "New commits were pushed after this script was written. Update `$Targets before running.")
}
Write-Ok "range holds exactly $($rangeList.Count) commits, one per message"

for ($i = 0; $i -lt $Targets.Count; $i++) {
    $full  = Get-GitValue rev-parse "$($Targets[$i].Sha)^{commit}"
    if ($full -ne $rangeList[$i]) {
        throw "Order mismatch at position $($i+1): expected $($Targets[$i].Sha), range has $($rangeList[$i].Substring(0,7))"
    }
    $subj  = Get-GitValue log -1 --format=%s $full
    $sig   = Get-GitValue log -1 --format=%G? $full
    if ($subj -ne '.') { throw "$($Targets[$i].Sha) subject is '$subj', not '.'. Already reworded? Aborting." }
    if ($sig  -eq 'G' -or $sig -eq 'U') { Write-Warn "$($Targets[$i].Sha) is signed; the signature will be lost" }
    $Targets[$i].Full = $full
    $Targets[$i].Tree = Get-GitValue rev-parse "$full^{tree}"
}
Write-Ok "all five resolve, all still have message '.', none signed"

$origHead  = Get-GitValue rev-parse HEAD
$origTree  = Get-GitValue rev-parse "HEAD^{tree}"
$origCount = [int](Get-GitValue rev-list --count HEAD)
Write-Info "orig HEAD  $($origHead.Substring(0,7))"
Write-Info "orig tree  $($origTree.Substring(0,10))"
Write-Info "orig count $origCount"

# ---------------------------------------------------------------------------
Write-Step "Plan"
foreach ($t in $Targets) {
    $stat = (Get-GitValue show --stat --format='' $t.Full) -split "`n" | Select-Object -Last 1
    Write-Host "  $($t.Sha)  " -NoNewline -ForegroundColor DarkGray
    Write-Host $t.Subject -ForegroundColor White
    Write-Info "           $($stat.Trim())"
}
foreach ($tag in $NewTags) {
    $exists = Get-GitValue tag -l $tag.Name
    if ($exists) { Write-Warn "tag $($tag.Name) already exists; Tag mode will skip it" }
    else { Write-Host "  tag       " -NoNewline -ForegroundColor DarkGray; Write-Host "$($tag.Name) -> $($tag.Sha)" -ForegroundColor White }
}

if ($Mode -eq 'Preview') {
    Write-Step "Preview only. Nothing changed."
    Write-Info "Re-run with -Mode All to rewrite and tag, or -Mode Notes for the non-destructive option."
    return
}

# ---------------------------------------------------------------------------
if ($Mode -eq 'Notes') {
    Write-Step "Attaching git notes (non-destructive)"
    $tmp = [System.IO.Path]::GetTempFileName()
    try {
        foreach ($t in $Targets) {
            Write-MessageFile -Path $tmp -Text ("$($t.Subject)`n`n$($t.Body)".Trim())
            & git notes add --force --file $tmp $t.Full 2>&1 | Out-Null
            if ($LASTEXITCODE -ne 0) { throw "git notes add failed for $($t.Sha)" }
            Write-Ok "note on $($t.Sha)"
        }
    } finally { Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue }
    Write-Warn "Notes do not render on github.com and are not fetched by a default clone."
    Write-Info "Publish with:  git push origin refs/notes/commits"
}

# ---------------------------------------------------------------------------
if ($Mode -eq 'Reword' -or $Mode -eq 'All') {

    $stamp  = Get-Date -Format 'yyyyMMdd-HHmmss'
    $backup = "backup/pre-reword-$stamp"
    Invoke-Git branch $backup HEAD | Out-Null
    Write-Step "Backup"
    Write-Ok "branch $backup -> $($origHead.Substring(0,7))"
    Write-Info "recover at any time with:  git reset --hard $backup"

    Write-Step "Self-test: replay into a scratch ref first"

    function Invoke-Replay {
        param([string]$StartParent)
        $parent = $StartParent
        $made   = @()
        $tmp    = [System.IO.Path]::GetTempFileName()
        try {
            foreach ($t in $Targets) {
                Write-MessageFile -Path $tmp -Text ("$($t.Subject)`n`n$($t.Body)".Trim())

                $env:GIT_AUTHOR_NAME     = Get-GitValue log -1 --format=%an $t.Full
                $env:GIT_AUTHOR_EMAIL    = Get-GitValue log -1 --format=%ae $t.Full
                $env:GIT_AUTHOR_DATE     = Get-GitValue log -1 --format=%aI $t.Full
                $env:GIT_COMMITTER_NAME  = Get-GitValue log -1 --format=%cn $t.Full
                $env:GIT_COMMITTER_EMAIL = Get-GitValue log -1 --format=%ce $t.Full
                $env:GIT_COMMITTER_DATE  = Get-GitValue log -1 --format=%cI $t.Full

                # -p is a PowerShell common-parameter prefix (PipelineVariable),
                # so this must go through & git natively, not Invoke-Git.
                $new = (& git commit-tree $t.Tree -p $parent -F $tmp 2>&1 | Out-String).Trim()
                if ($LASTEXITCODE -ne 0 -or $new -notmatch '^[0-9a-f]{40}$') {
                    throw "commit-tree failed for $($t.Sha): $new"
                }
                $made  += [pscustomobject]@{ Old = $t.Full; New = $new; Parent = $parent; Sha = $t.Sha }
                $parent = $new
            }
        } finally {
            Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue
            Remove-Item Env:GIT_AUTHOR_NAME, Env:GIT_AUTHOR_EMAIL, Env:GIT_AUTHOR_DATE,
                        Env:GIT_COMMITTER_NAME, Env:GIT_COMMITTER_EMAIL, Env:GIT_COMMITTER_DATE `
                        -ErrorAction SilentlyContinue
        }
        return $made
    }

    $trial = Invoke-Replay -StartParent $baseFull
    $trialHead = $trial[-1].New
    Invoke-Git update-ref refs/heads/selftest-reword $trialHead | Out-Null
    try {
        $trialTree  = Get-GitValue rev-parse "$trialHead^{tree}"
        $trialCount = [int](Get-GitValue rev-list --count $trialHead)
        if ($trialTree  -ne $origTree)  { throw "SELF-TEST FAILED: tree $trialTree != $origTree" }
        if ($trialCount -ne $origCount) { throw "SELF-TEST FAILED: count $trialCount != $origCount" }
        $trialDiff = Get-GitValue diff $origHead $trialHead
        if ($trialDiff) { throw "SELF-TEST FAILED: content differs from the original" }
        Write-Ok "scratch tree matches ($($trialTree.Substring(0,10)))"
        Write-Ok "scratch count matches ($trialCount)"
        Write-Ok "scratch content diff empty"
    } finally {
        # -d prefixes PowerShell's -Debug common parameter and would bind to it
        # silently through Invoke-Git, leaving the scratch ref behind. Native.
        & git update-ref -d refs/heads/selftest-reword 2>&1 | Out-Null
    }

    Write-Step "Rewriting main"
    $newHead = $trial[-1].New
    Invoke-Git update-ref refs/heads/main $newHead | Out-Null
    Invoke-Git reset --hard main | Out-Null
    foreach ($m in $trial) {
        Write-Ok "$($m.Sha) -> $($m.New.Substring(0,7))  parent $($m.Parent.Substring(0,7))"
    }

    Write-Step "Post-checks"
    $nowTree  = Get-GitValue rev-parse "HEAD^{tree}"
    $nowCount = [int](Get-GitValue rev-list --count HEAD)
    if ($nowTree -ne $origTree) { throw "ABORT: root tree changed. Recover: git reset --hard $backup" }
    Write-Ok "root tree unchanged  $($nowTree.Substring(0,10))"
    if ($nowCount -ne $origCount) { throw "ABORT: commit count changed. Recover: git reset --hard $backup" }
    Write-Ok "commit count unchanged  $nowCount"
    $diff = Get-GitValue diff $backup HEAD
    if ($diff) { throw "ABORT: content differs from backup. Recover: git reset --hard $backup" }
    Write-Ok "content diff vs backup empty"

    foreach ($m in $trial) {
        $p = Get-GitValue rev-parse "$($m.New)^"
        if ($p -ne $m.Parent) { throw "ABORT: parent mismatch on $($m.New.Substring(0,7))" }
        $s = Get-GitValue log -1 --format=%s $m.New
        if ($s -eq '.' -or -not $s) { throw "ABORT: $($m.New.Substring(0,7)) still has a placeholder subject" }
    }
    Write-Ok "all parents asserted, all five subjects non-empty"

    $tagCheck = Get-GitValue rev-parse "v0.0.3^{commit}"
    if ($tagCheck -ne $baseFull) { Write-Warn "v0.0.3 no longer points at the base commit" }
    else { Write-Ok "v0.0.2 and v0.0.3 unaffected (they precede the range)" }
}

# ---------------------------------------------------------------------------
if ($Mode -eq 'Tag' -or $Mode -eq 'All') {
    Write-Step "Tags"
    $tmp = [System.IO.Path]::GetTempFileName()
    try {
        foreach ($tag in $NewTags) {
            if (Get-GitValue tag -l $tag.Name) { Write-Warn "$($tag.Name) exists, skipping"; continue }

            # After a reword the original SHA is gone. Resolve by subject on the
            # rewritten history instead of trusting the pre-rewrite short SHA.
            $wanted = ($Targets | Where-Object { $_.Sha -eq $tag.Sha }).Subject
            $target = Get-GitValue log -1 --format=%H --fixed-strings "--grep=$wanted" HEAD
            if (-not $target) {
                $target = Get-GitValue rev-parse "$($tag.Sha)^{commit}"   # Tag-only run, no reword
            }

            Write-MessageFile -Path $tmp -Text $tag.Message
            # -a and -F are safe through Invoke-Git; -m would not be.
            Invoke-Git tag -a $tag.Name -F $tmp $target | Out-Null
            Write-Ok "$($tag.Name) -> $($target.Substring(0,7))"
        }
    } finally { Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue }
}

# ---------------------------------------------------------------------------
Write-Step "Publish"
$didRewrite = ($Mode -eq 'Reword' -or $Mode -eq 'All')

if ($Push) {
    if ($didRewrite) {
        & git push --force-with-lease origin main
        if ($LASTEXITCODE -ne 0) { throw "force-with-lease push rejected. Someone else pushed; re-run preflight." }
        Write-Ok "pushed main (force-with-lease)"
    }
    if ($Mode -eq 'Tag' -or $Mode -eq 'All') {
        Invoke-Git push origin --tags | Out-Null
        Write-Ok "pushed tags"
    }
    if ($Mode -eq 'Notes') {
        Invoke-Git push origin refs/notes/commits | Out-Null
        Write-Ok "pushed notes"
    }
} else {
    Write-Info "Nothing pushed. Review first:"
    Write-Info "    git log --format='%h %an %ad%n    %s' --date=short -6"
    Write-Info ""
    Write-Info "Then publish:"
    if ($didRewrite) { Write-Info "    git push --force-with-lease origin main" }
    if ($Mode -eq 'Tag' -or $Mode -eq 'All') { Write-Info "    git push origin --tags" }
    if ($Mode -eq 'Notes') { Write-Info "    git push origin refs/notes/commits" }
    if ($didRewrite) {
        Write-Info ""
        Write-Warn "Other clones must run:  git fetch origin && git reset --hard origin/main"
    }
}

Write-Step "Done"
