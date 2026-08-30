<#
    Publish-Step3.ps1
    SLP Dealer Guard - Step 3 (Layer 1, client-side country check).
    Commits and tags slp-plugins v0.0.6.

    Step 3 touches the PLUGIN repo only. No theme file changed, so there is
    no second repo to commit this time.

    Run AFTER the two files are in the working tree and AFTER the server-side
    md5 check has passed.

        .\Publish-Step3.ps1                 # verify, commit, tag, push
        .\Publish-Step3.ps1 -WhatIf         # verify and show, change nothing
        .\Publish-Step3.ps1 -SkipPush       # commit and tag locally only

    Note: git is invoked as `& git` throughout. PowerShell will happily
    prefix-match a bare `git` argument against its own parameters, which has
    bitten this project before.
#>

[CmdletBinding(SupportsShouldProcess = $true)]
param(
    [string] $RepoRoot = 'D:\Temp\Projects\GitHub\slp-plugins',
    [switch] $SkipPush,
    [switch] $VerifyOnly
)

$ErrorActionPreference = 'Stop'

$Tag = 'v0.0.6'

# Path -> expected md5 of the v0.0.6 artefact.
$Expected = @{
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

if (-not (Test-Path $RepoRoot)) {
    throw "Repo root not found: $RepoRoot"
}
Push-Location $RepoRoot
try {

    # ---------------------------------------------------------------- verify
    Write-Step 'Verifying working-tree checksums'
    $bad = @()
    foreach ($rel in $Expected.Keys | Sort-Object) {
        $full = Join-Path $RepoRoot $rel
        if (-not (Test-Path $full)) { $bad += "MISSING  $rel"; continue }

        $got = Get-Md5 $full
        if ($got -ne $Expected[$rel]) {
            $bad += "MISMATCH $rel`n           expected $($Expected[$rel])`n           got      $got"
        }
        else {
            Write-Host ("  ok  {0}  {1}" -f $got, $rel) -ForegroundColor Green
        }
    }
    if ($bad.Count) {
        throw "Working tree does not match the v0.0.6 artefacts:`n  $($bad -join "`n  ")"
    }

    # No trailing newline, CRLF preserved. .gitattributes pins slp_avalon/** -text,
    # but verify rather than trust: a wrong answer here is silent.
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

    $existing = & git tag --list $Tag
    if ($existing) { throw "Tag $Tag already exists. Delete it or bump the version." }

    # ---------------------------------------------------------------- commit
    $subject = 'Step 3: Layer 1 client-side country check'
    $body = @'
Rejects out-of-territory searches in the browser, before any request
reaches the server. Closes issues 7 and 8.

Layer 1 sits in the process_geocode_response override installed by
install_geocode_hook(). It reads the country component from
results[0].address_components and rejects anything outside the
seven-entry allow-list US/PR/VI/GU/MP/AS/CA. Where no country component
exists the check no-ops by design and the decision falls to Layer 3.

Autocomplete selections were previously protected by nothing at all on
the client: the Autocomplete constructor took no options object and no
componentRestrictions exist anywhere in the codebase. place_changed now
captures the country alongside the coordinates, and the coords-spoof
payload built in cslmap_searchLocations() carries it forward shaped like
a real geocode result, so Layer 1 needs no special case for that path.

setFields(["address_components","geometry"]) names the only two fields
used and drops the call from the Places Details SKU to Basic Data.

The change handler on #addressInput now nulls place_country alongside
place_lat and place_lng. Nulling only the coordinates would have left a
stale country to validate against a fresh location.

Correction carried in this commit: Layer 1 deliberately does NOT mirror
the ERROR path's `gmap === null -> delegate to original` branch. That
branch is safe below only because status is not OK there, so SLP takes
its failure branch at slp_core.js:1578 and builds a fallback-centred map
without searching. Status is OK on the Layer 1 path, so delegating would
take the success branch at slp_core.js:1527 -> 1555 and call build_map()
with the location just rejected; build_map is overridden to
cslmap_build_map(), whose bootstrap then either re-submits #searchForm or
calls load_markers() around that same point. The coarse server boxes pass
Tijuana, so Layer 3 would not have caught it either. Accepted
consequence: where gmap can still be null at the first geocode a rejected
first search leaves the map unbuilt. Unreachable on Aura; tracked as a
Tahoe/Avalon portability item.

Also removed resizeMap() and its window.resize binding. Every value it
computed was NaN: jQuery("header#header") matched nothing on this page,
and "#sl_bottom_left #search_box" was a descendant selector for a sibling
element. It had been a no-op for the life of the file.

Comment corrections: send_ajax is not shared with
slp.option.get_from_server, which issues its own jQuery.getJSON at
slp_core.js:848 and has no other caller than slp_core.js:1849; the
coords-spoof call site is no longer line 119; REJECTED is no longer
hypothetical; the remaining Step-2 references now name the layer they
mean.

Verified: node --check clean, 98/98 behavioural assertions green against
the built artefact, CRLF and absent trailing newline preserved.

slp_avalon.js   326a06f317b56051e98af03a3d24986e
slp_avalon.php  c0d4104ef0bfaedebd5cba4cd5638a92
'@

    if ($PSCmdlet.ShouldProcess($RepoRoot, "stage, commit and tag $Tag")) {

        Write-Step 'Staging'
        foreach ($rel in $Expected.Keys | Sort-Object) { & git add -- $rel }
        & git status --short

        Write-Step 'Committing'
        & git commit -m $subject -m $body
        if ($LASTEXITCODE -ne 0) { throw 'git commit failed.' }

        # -------------------------------------------------- post-commit check
        # Equal hashes mean no filter rewrote the bytes on check-in.
        Write-Step 'Post-commit SHA check (worktree blob vs committed blob)'
        $drift = @()
        foreach ($rel in $Expected.Keys | Sort-Object) {
            $wt = (& git hash-object --no-filters -- (Join-Path $RepoRoot $rel)).Trim()
            $cm = (& git rev-parse "HEAD:$rel").Trim()
            if ($wt -ne $cm) { $drift += "$rel`n           worktree $wt`n           committed $cm" }
            else { Write-Host ("  ok  {0}  {1}" -f $wt.Substring(0, 12), $rel) -ForegroundColor Green }
        }
        if ($drift.Count) {
            throw "A filter rewrote bytes on check-in:`n  $($drift -join "`n  ")"
        }

        Write-Step "Tagging $Tag"
        & git tag -a $Tag -m 'Step 3: Layer 1 client-side country check (US/PR/VI/GU/MP/AS/CA)'
        if ($LASTEXITCODE -ne 0) { throw 'git tag failed.' }

        if (-not $SkipPush) {
            Write-Step 'Pushing'
            & git push origin HEAD
            if ($LASTEXITCODE -ne 0) { throw 'git push failed.' }
            & git push origin $Tag
            if ($LASTEXITCODE -ne 0) { throw 'git push --tags failed.' }
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
