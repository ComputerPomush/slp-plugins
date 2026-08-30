<#
    Publish-Step3_1.ps1
    SLP Dealer Guard - Step 3.1 (Get My Position stale-coordinate fix, Issue 14).
    Commits and tags slp-plugins v0.0.7.

    Plugin repo only. No theme file changed.

    Run AFTER both files are in the working tree and AFTER the server-side
    md5 check has passed.

        .\Run-Step3_1.ps1 -Verify     # checksums + line endings, no staging
        .\Run-Step3_1.ps1 -DryRun     # full walkthrough, changes nothing
        .\Run-Step3_1.ps1             # stage, commit, tag, push
        .\Run-Step3_1.ps1 -NoPush     # commit and tag locally only

    git is invoked as `& git` throughout: PowerShell will prefix-match a bare
    git argument against its own parameters, which has bitten this project.
#>

[CmdletBinding(SupportsShouldProcess = $true)]
param(
    [string] $RepoRoot = 'D:\Temp\Projects\GitHub\slp-plugins',
    [switch] $SkipPush,
    [switch] $VerifyOnly
)

$ErrorActionPreference = 'Stop'

$Tag = 'v0.0.7'

$Expected = @{
    'slp_avalon/assets/js/slp_avalon.js' = '50a18f58088564ec8adc2c2ce5684732'
    'slp_avalon/slp_avalon.php'          = '58a632ec05e11906c44f70854c0c800c'
}

# Guard against committing the previous release by mistake.
$Previous = @{
    'slp_avalon/assets/js/slp_avalon.js' = '326a06f317b56051e98af03a3d24986e'
    'slp_avalon/slp_avalon.php'          = 'c0d4104ef0bfaedebd5cba4cd5638a92'
}

function Get-Md5([string] $Path) {
    (Get-FileHash -Path $Path -Algorithm MD5).Hash.ToLower()
}
function Write-Step([string] $Text) {
    Write-Host ''
    Write-Host "== $Text" -ForegroundColor Cyan
}

if (-not (Test-Path $RepoRoot)) { throw "Repo root not found: $RepoRoot" }
Push-Location $RepoRoot
try {

    Write-Step 'Verifying working-tree checksums'
    $bad = @()
    foreach ($rel in $Expected.Keys | Sort-Object) {
        $full = Join-Path $RepoRoot $rel
        if (-not (Test-Path $full)) { $bad += "MISSING  $rel"; continue }
        $got = Get-Md5 $full
        if ($got -eq $Previous[$rel]) {
            $bad += "STALE    $rel is still v0.0.6 - upload the v0.0.7 file first"
        }
        elseif ($got -ne $Expected[$rel]) {
            $bad += "MISMATCH $rel`n           expected $($Expected[$rel])`n           got      $got"
        }
        else {
            Write-Host ("  ok  {0}  {1}" -f $got, $rel) -ForegroundColor Green
        }
    }
    if ($bad.Count) {
        throw "Working tree does not match the v0.0.7 artefacts:`n  $($bad -join "`n  ")"
    }

    Write-Step 'Verifying line endings survived'
    foreach ($rel in $Expected.Keys | Sort-Object) {
        $bytes = [System.IO.File]::ReadAllBytes((Join-Path $RepoRoot $rel))
        $cr = ($bytes | Where-Object { $_ -eq 13 }).Count
        $lf = ($bytes | Where-Object { $_ -eq 10 }).Count
        $trailing = $bytes[-1] -eq 10
        if ($cr -ne $lf -or $trailing) {
            throw "$rel : CR=$cr LF=$lf trailingNewline=$trailing - expected CR=LF and no trailing newline."
        }
        Write-Host ("  ok  CR={0} LF={1} noTrailingNewline  {2}" -f $cr, $lf, $rel) -ForegroundColor Green
    }

    if ($VerifyOnly) {
        Write-Host ''
        Write-Host '  -VerifyOnly set: checksums and line endings pass. Nothing staged.' -ForegroundColor Yellow
        return
    }

    Write-Step 'Repository status'
    & git status --short
    if ($LASTEXITCODE -ne 0) { throw 'git status failed.' }

    if (& git tag --list $Tag) { throw "Tag $Tag already exists. Delete it or bump the version." }

    $subject = 'Step 3.1: Get My Position writes the position it has (Issue 14)'
    $body = @'
Fixes a stale-coordinate bug on the Get My Position path.

get_user_current_address() reverse-geocoded the GPS fix only to produce
display text, then wrote it with jQuery("#addressInput").val(address). A
programmatic .val() fires no change event, so the reset handler on
#addressInput never ran and whatever place_lat / place_lng were already
on the field survived - the URL bootstrap coordinates written by
cslmap_build_map, or an earlier autocomplete selection.
cslmap_searchLocations() then took the coords branch and searched THAT
location while displaying the geolocated address.

Reproduced on Aura DEV: load ?place_lat=48.86&place_lng=2.35, click Get
My Position from New York, and the field reads "New York, 11224" while
the map stays on the Paris rejection.

rev 6 §0.5 identified the missing change event; its §7.3 specified only
the handler body, which v0.0.6 shipped correctly and which never fires
on this path. This is the other half.

The callback already holds the GPS fix in `pos` and its reverse geocode
in results[0], so this populates all three data values rather than
clearing them. That avoids re-geocoding a truncated display string,
saves a geocode call, and hands Layer 1 a real country - which is what
lets an out-of-territory geolocated position be rejected client-side
instead of on the server.

Verified: node --check clean; 20 new assertions driving
get_user_current_address() end to end, confirmed to FAIL against v0.0.6
before passing against v0.0.7; the 98 v0.0.6 assertions still green.
CRLF and absent trailing newline preserved.

slp_avalon.js   50a18f58088564ec8adc2c2ce5684732
slp_avalon.php  58a632ec05e11906c44f70854c0c800c
'@

    if ($PSCmdlet.ShouldProcess($RepoRoot, "stage, commit and tag $Tag")) {

        Write-Step 'Staging'
        foreach ($rel in $Expected.Keys | Sort-Object) { & git add -- $rel }
        & git status --short

        Write-Step 'Committing'
        & git commit -m $subject -m $body
        if ($LASTEXITCODE -ne 0) { throw 'git commit failed.' }

        Write-Step 'Post-commit SHA check (worktree blob vs committed blob)'
        $drift = @()
        foreach ($rel in $Expected.Keys | Sort-Object) {
            $wt = (& git hash-object --no-filters -- (Join-Path $RepoRoot $rel)).Trim()
            $cm = (& git rev-parse "HEAD:$rel").Trim()
            if ($wt -ne $cm) { $drift += "$rel`n           worktree $wt`n           committed $cm" }
            else { Write-Host ("  ok  {0}  {1}" -f $wt.Substring(0, 12), $rel) -ForegroundColor Green }
        }
        if ($drift.Count) { throw "A filter rewrote bytes on check-in:`n  $($drift -join "`n  ")" }

        Write-Step "Tagging $Tag"
        & git tag -a $Tag -m 'Step 3.1: Get My Position stale-coordinate fix (Issue 14)'
        if ($LASTEXITCODE -ne 0) { throw 'git tag failed.' }

        if (-not $SkipPush) {
            Write-Step 'Pushing'
            & git push origin HEAD
            if ($LASTEXITCODE -ne 0) { throw 'git push failed.' }
            & git push origin $Tag
            if ($LASTEXITCODE -ne 0) { throw 'git push tag failed.' }
        }
        else {
            Write-Host ''
            Write-Host '  -SkipPush set: nothing pushed.' -ForegroundColor Yellow
        }

        Write-Step 'Done'
        & git --no-pager log --oneline -1
        & git --no-pager tag --list $Tag -n1
    }
}
finally {
    Pop-Location
}
