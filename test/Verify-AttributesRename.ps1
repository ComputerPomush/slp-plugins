<#
    Verify-AttributesRename.ps1   (v2)

    Confirms that renaming 'gitattributes' -> '.gitattributes' brought the
    slp_avalon/** -text rule into force WITHOUT disturbing any blob, then
    optionally commits it.

    Context: core.autocrlf is 'true' on this machine. Twelve releases survived
    only because git's has_crlf_in_index() skips CRLF->LF conversion for paths
    already stored with CRLF. That net does not cover newly added paths.

    v2 fixes a defect in v1: [System.IO.File]::ReadAllBytes() resolves relative
    paths against the .NET current directory, which Set-Location does not
    change. v1 died in section 4 looking for the file under C:\WINDOWS\system32.
    v2 pins the .NET CWD, builds absolute paths for every raw .NET call, and
    records an unexpected exception as a failure instead of aborting the report.

    RUN AS A FILE. Do not paste into an interactive prompt - each statement
    would get its own scope and 'throw' would stop nothing.

        cd D:\Temp\Projects\GitHub\slp-plugins
        .\Verify-AttributesRename.ps1 -MoveParked
        .\Verify-AttributesRename.ps1 -Commit
#>

[CmdletBinding()]
param(
    [string] $RepoPath   = 'D:\Temp\Projects\GitHub\slp-plugins',
    [string] $ParkedPath = 'D:\Temp\Projects\slp-v015\parked',
    [switch] $MoveParked,
    [switch] $Commit
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$script:Failures = 0

function Write-Check {
    param([string] $Label, [bool] $Ok, [string] $Detail = '')
    if ($Ok) {
        Write-Host ("  [ OK ] {0}" -f $Label) -ForegroundColor Green
    } else {
        Write-Host ("  [FAIL] {0}" -f $Label) -ForegroundColor Red
        $script:Failures++
    }
    if ($Detail) { Write-Host ("         {0}" -f $Detail) -ForegroundColor DarkGray }
}

function Write-Note {
    param([string] $Text)
    Write-Host ("  [note] {0}" -f $Text) -ForegroundColor DarkYellow
}

function Get-Md5 {
    param([string] $Path)
    return (Get-FileHash -LiteralPath $Path -Algorithm MD5).Hash.ToLowerInvariant()
}

# ---------------------------------------------------------------- setup ----

if (-not (Test-Path -LiteralPath $RepoPath)) { throw "Repo not found: $RepoPath" }
Set-Location -LiteralPath $RepoPath

# THE v1 BUG. PowerShell's location and .NET's CurrentDirectory are separate.
# Pin them together before any raw System.IO call.
$RepoRoot = (Get-Location).Path
[System.Environment]::CurrentDirectory = $RepoRoot

Write-Host ''
Write-Host 'Verify-AttributesRename  v2' -ForegroundColor Cyan
Write-Host ("PS location : {0}" -f $RepoRoot)
Write-Host (".NET CWD    : {0}" -f ([System.Environment]::CurrentDirectory))
Write-Host ''

# v0.0.14 pins, from handoff rev12 section 1. Kept inside hashtable literals so
# no bare hash value ever sits on its own line where a shell would execute it.
$PinnedMd5 = @{
    'slp_avalon/assets/js/slp_avalon.js'  = '8c93719e41af3232c18773a104e8dedd'
    'slp_avalon/inc/class.slp_avalon.php' = 'f6a07b929ceed4de0d6bd5fa034eda6b'
    'slp_avalon/slp_avalon.php'           = 'e61ce5ea8e64e5b5a6f89649fa623afd'
}
$AttrMd5 = '4ecda2243f179695cb31942fcbe9634d'

# Expected CR counts, so a silent line-ending change cannot pass unnoticed.
$PinnedCr = @{
    'slp_avalon/assets/js/slp_avalon.js'  = 1604
    'slp_avalon/inc/class.slp_avalon.php' = 1353
    'slp_avalon/slp_avalon.php'           = 59
}

# The parked Issue 22 artefacts. build-v015.py and suite-v015.js occupy the
# filenames the data-hygiene release needs, so they must leave the repo.
$ParkedInRepo = @(
    'build\build-v015.py'
    'test\suite-v015.js'
)

# ------------------------------------------------------ 0. move parked ----

if ($MoveParked) {
    Write-Host '0. Moving parked Issue 22 artefacts out of the repo' -ForegroundColor Yellow

    if (-not (Test-Path -LiteralPath $ParkedPath)) {
        New-Item -ItemType Directory -Path $ParkedPath -Force | Out-Null
        Write-Note ("created {0}" -f $ParkedPath)
    }

    foreach ($rel in $ParkedInRepo) {
        $full = Join-Path -Path $RepoRoot -ChildPath $rel
        if (Test-Path -LiteralPath $full) {
            Move-Item -LiteralPath $full -Destination $ParkedPath -Force
            Write-Host ("  moved  {0}  ->  {1}" -f $rel, $ParkedPath) -ForegroundColor Green
        } else {
            Write-Host ("  absent {0}  (already moved)" -f $rel) -ForegroundColor DarkGray
        }
    }
    Write-Host ''
}

# --------------------------------------------------- 1. attributes file ----

Write-Host '1. Attributes file' -ForegroundColor Yellow

$dotted   = Test-Path -LiteralPath (Join-Path $RepoRoot '.gitattributes')
$undotted = Test-Path -LiteralPath (Join-Path $RepoRoot 'gitattributes')
Write-Check '.gitattributes exists' $dotted
Write-Check 'old undotted gitattributes is gone' (-not $undotted)

if ($dotted) {
    $actual = Get-Md5 -Path (Join-Path $RepoRoot '.gitattributes')
    Write-Check 'content unchanged by the rename' ($actual -eq $AttrMd5) `
        ("expected {0}, got {1}" -f $AttrMd5, $actual)
}

# ------------------------------------------------ 2. rule now in force ----

Write-Host ''
Write-Host '2. Is slp_avalon/** -text actually in force now?' -ForegroundColor Yellow

foreach ($path in ($PinnedMd5.Keys | Sort-Object)) {
    $attr = (& git check-attr text -- $path) -join ''
    $ok = $attr -match 'text:\s*unset\s*$'
    Write-Check ("check-attr text -> unset : {0}" -f $path) $ok $attr
}

# ------------------------------------------- 3. no blob was renormalised ----

Write-Host ''
Write-Host '3. Did the rename disturb any slp_avalon blob?' -ForegroundColor Yellow

foreach ($path in ($PinnedMd5.Keys | Sort-Object)) {
    $headBlob  = (& git rev-parse ("HEAD:{0}" -f $path)).Trim()
    $stageLine = (& git ls-files --stage -- $path) -join ''
    $indexBlob = ''
    if ($stageLine -match '^\d+\s+([0-9a-f]{40})\s') { $indexBlob = $Matches[1] }

    Write-Check ("index blob == HEAD blob : {0}" -f $path) ($indexBlob -eq $headBlob) `
        ("HEAD {0} / index {1}" -f $headBlob, $indexBlob)
}

# -------------------------------------------- 4. working tree unchanged ----

Write-Host ''
Write-Host '4. Working tree still byte-exact at v0.0.14' -ForegroundColor Yellow

foreach ($path in ($PinnedMd5.Keys | Sort-Object)) {
    $full = Join-Path -Path $RepoRoot -ChildPath ($path -replace '/', '\')

    if (-not (Test-Path -LiteralPath $full)) {
        Write-Check ("present : {0}" -f $path) $false ("looked for {0}" -f $full)
        continue
    }

    $actual = Get-Md5 -Path $full
    Write-Check ("md5 : {0}" -f $path) ($actual -eq $PinnedMd5[$path]) `
        ("expected {0}, got {1}" -f $PinnedMd5[$path], $actual)

    try {
        $bytes = [System.IO.File]::ReadAllBytes($full)
        $cr = 0; $lf = 0
        foreach ($b in $bytes) { if ($b -eq 13) { $cr++ } elseif ($b -eq 10) { $lf++ } }
        $expectCr = $PinnedCr[$path]
        Write-Check ("CRLF preserved : {0}" -f $path) `
            (($cr -eq $expectCr) -and ($cr -eq $lf)) `
            ("CR={0} LF={1} expected CR={2} bytes={3}" -f $cr, $lf, $expectCr, $bytes.Length)
    }
    catch {
        Write-Check ("CRLF preserved : {0}" -f $path) $false `
            ("unexpected error: {0}" -f $_.Exception.Message)
    }
}

# --------------------------------------------------- 5. status is sane ----

Write-Host ''
Write-Host '5. git status' -ForegroundColor Yellow

$status          = @(& git status --porcelain)
$renameLines     = @($status | Where-Object { $_ -match '^R' })
$modifiedTracked = @($status | Where-Object { $_ -match '^(M|\sM|MM)' })

Write-Check 'exactly one staged rename' ($renameLines.Count -eq 1) (($renameLines -join ' | '))
Write-Check 'no tracked file shows as modified' ($modifiedTracked.Count -eq 0) `
    (($modifiedTracked -join ' | '))

# ------------------------------------- 6. stray residue / parked files ----

Write-Host ''
Write-Host '6. Stray residue and parked artefacts' -ForegroundColor Yellow

$out15    = Join-Path -Path $RepoRoot -ChildPath 'out15'
$hasOut15 = Test-Path -LiteralPath $out15
Write-Check 'no stray out15\ build-output folder' (-not $hasOut15) `
    $(if ($hasOut15) { "found $out15 - the parked build script ran; delete it" } else { '' })

foreach ($rel in $ParkedInRepo) {
    $full    = Join-Path -Path $RepoRoot -ChildPath $rel
    $present = Test-Path -LiteralPath $full
    Write-Check ("parked artefact is OUT of the repo : {0}" -f $rel) (-not $present) `
        $(if ($present) { 'still present - re-run with -MoveParked' } else { '' })
}

$untracked = @($status | Where-Object { $_ -match '^\?\?' })
if ($untracked.Count -gt 0) {
    Write-Host ''
    Write-Note 'Untracked files remaining (informational):'
    $untracked | ForEach-Object { Write-Host ("           {0}" -f $_) -ForegroundColor DarkGray }
    Write-Note 'Publish-Step10.ps1 is expected here - Publish-Step11.ps1 starts from it.'
}

# ------------------------------------------------------------- verdict ----

Write-Host ''
if ($script:Failures -gt 0) {
    Write-Host ("FAILED - {0} check(s) did not pass. Do not commit." -f $script:Failures) -ForegroundColor Red
    exit 1
}

Write-Host 'All checks passed.' -ForegroundColor Green

if (-not $Commit) {
    Write-Host ''
    Write-Host 'Re-run with -Commit to create the commit.' -ForegroundColor Cyan
    exit 0
}

# --------------------------------------------------------------- commit ----

Write-Host ''
Write-Host 'Committing the rename...' -ForegroundColor Yellow

$message = @'
chore: rename gitattributes -> .gitattributes so the -text rule applies

The file was committed in 7218566 without a leading dot, so git never read
it and `slp_avalon/** -text` has never been in force. core.autocrlf is true
on the build machine; the CRLF in these blobs survived twelve releases only
because git's has_crlf_in_index() skips conversion for paths already stored
with CRLF. That heuristic does not cover newly added paths.

Content is unchanged. Verified before and after: working-tree md5 and CR
count of all three slp_avalon files, and index blob == HEAD blob.
'@

& git commit --message $message
if ($LASTEXITCODE -ne 0) { throw "git commit failed with exit code $LASTEXITCODE" }

Write-Host ''
Write-Host 'Post-commit blob check' -ForegroundColor Yellow
foreach ($path in ($PinnedMd5.Keys | Sort-Object)) {
    $newBlob = (& git rev-parse ("HEAD:{0}" -f $path)).Trim()
    $oldBlob = (& git rev-parse ("HEAD~1:{0}" -f $path)).Trim()
    Write-Check ("blob unchanged across the commit : {0}" -f $path) ($newBlob -eq $oldBlob) `
        ("{0} -> {1}" -f $oldBlob, $newBlob)
}

Write-Host ''
if ($script:Failures -gt 0) {
    Write-Host 'A blob changed during the commit. Investigate before pushing.' -ForegroundColor Red
    exit 1
}
Write-Host 'Commit created and verified. Safe to push.' -ForegroundColor Green
exit 0
