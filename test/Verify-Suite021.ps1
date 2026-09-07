<#
.SYNOPSIS
    Scores suite-v021 against the v0.0.21 build and the v0.0.20 control, and
    AUDITS THE TAGS.

.DESCRIPTION
    rev20 s8 records that fourteen cases in suite-v020 were tagged [v20] and
    passed against the control, which made the tag a false claim rather than
    a measurement. Those were found by hand. This finds them mechanically.

    A tag is a falsifiable claim about the control run:

      [v21]  MUST fail against the v0.0.20 blob. If it passes, the case does
             not discriminate and the tag is wrong.
      [both] MUST pass against the v0.0.20 blob. If it fails, either the tag
             is wrong or the control is broken for an unrelated reason.

    Both violations are reported. The score triple is only trustworthy once
    the audit is clean, so do not pin it in Publish-Step18 before then.

    Nothing here writes to the repo. The control blob goes to $env:TEMP and
    is removed afterwards.

    r2, and three things it fixes from r1:

      1. Set-Content -Encoding Byte is PowerShell 5.1 only, and would not
         have worked regardless: git's output arrives as PowerShell strings,
         not bytes. Extraction now goes through cmd, whose redirection is a
         byte passthrough. PowerShell's own > re-encodes and, on 5.1,
         re-encodes to UTF-16, which PHP cannot parse at all.
      2. THE CONTROL BLOB IS NOW PINNED. A control whose bytes were altered
         in transit is not a control. If the md5 does not match, this stops.
      3. The suite exits non-zero whenever assertions fail, which is exactly
         what the control is SUPPOSED to do. Under $ErrorActionPreference
         Stop, PowerShell 7.4 turns that into a terminating error and the
         audit never runs. Native exit codes are explicitly not errors here.

.EXAMPLE
    .\test\Verify-Suite021.ps1
    .\test\Verify-Suite021.ps1 -ControlRef 5ae2d40
#>

[CmdletBinding()]
param(
    [string] $ControlRef       = 'v0.0.20',
    [string] $ControlMd5       = 'd00964ee60539ba470ae6d657280aba3',
    [string] $Suite            = 'test\suite-v021.php',
    [string] $Build            = 'build\out21\class.slp_avalon.php',
    [string] $ClassPath        = 'slp_avalon/inc/class.slp_avalon.php',
    [switch] $SkipControlPin
)

$ErrorActionPreference = 'Stop'

# The suite signals "assertions failed" with exit 1. That is the control's
# expected behaviour, not an error condition for this script.
$PSNativeCommandUseErrorActionPreference = $false

function Get-CaseTable {
    param([string[]] $Lines)
    $t = @{}
    foreach ($l in $Lines) {
        # "  pass [v21]  A6  the disposal was recorded"
        if ($l -match '^\s{2}(pass|FAIL)\s+\[(v21|both)\]\s+(\S+)\s+(.*)$') {
            $t[$Matches[3]] = [pscustomobject]@{
                Id     = $Matches[3]
                Result = $Matches[1]
                Tag    = $Matches[2]
                Label  = $Matches[4].Trim()
            }
        }
    }
    return $t
}

Write-Host ''
Write-Host 'Verify-Suite021 r2  -  scoring and tag audit' -ForegroundColor Cyan
Write-Host ''

if (-not (Test-Path $Suite)) { throw "suite not found: $Suite" }
if (-not (Test-Path $Build)) { throw "build not found: $Build - run build\build-v021.py first" }
if (-not (Get-Command php -ErrorAction SilentlyContinue)) { throw 'php is not on PATH' }

# ---------------------------------------------------------------- control ---

& git rev-parse --verify --quiet "$ControlRef^{commit}" | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw "ref '$ControlRef' does not resolve. Pass the commit instead, e.g. -ControlRef 5ae2d40"
}

$ctl = Join-Path $env:TEMP 'ctl20.php'
if (Test-Path $ctl) { Remove-Item $ctl -Force }

Write-Host "extracting control  ${ControlRef}:${ClassPath}"
cmd /c "git show ""${ControlRef}:${ClassPath}"" > ""$ctl""" | Out-Null

if (-not (Test-Path $ctl) -or (Get-Item $ctl).Length -eq 0) {
    throw "could not extract ${ControlRef}:${ClassPath}"
}

$ctlLen  = (Get-Item $ctl).Length
$ctlHash = (Get-FileHash $ctl -Algorithm MD5).Hash.ToLower()
Write-Host ("control blob        {0}  {1:N0} bytes" -f $ctlHash, $ctlLen)

if (-not $SkipControlPin -and $ctlHash -ne $ControlMd5) {
    Write-Host ''
    Write-Host "  control md5 does not match the pin" -ForegroundColor Red
    Write-Host "    got      $ctlHash"
    Write-Host "    expected $ControlMd5"
    Write-Host '    Either the ref is not v0.0.20, or the extraction altered bytes.'
    Write-Host '    A control whose bytes changed in transit is not a control.'
    Remove-Item $ctl -ErrorAction SilentlyContinue
    exit 1
}
Write-Host ''

# ------------------------------------------------------------------- runs ---

Write-Host 'run 1  build' -ForegroundColor Yellow
$buildOut = & php $Suite $Build 2>&1 | ForEach-Object { "$_" }
$buildOut | ForEach-Object { Write-Host "  $_" }

Write-Host ''
Write-Host 'run 2  control  (expected to fail cases - that is the point)' -ForegroundColor Yellow
$ctlOut = & php $Suite $ctl 2>&1 | ForEach-Object { "$_" }

$bTable = Get-CaseTable -Lines $buildOut
$cTable = Get-CaseTable -Lines $ctlOut

if ($bTable.Count -eq 0) {
    Write-Host ''
    Write-Host '  no cases parsed from the build run - suite output format changed?' -ForegroundColor Red
    Remove-Item $ctl -ErrorAction SilentlyContinue
    exit 1
}

$bScore = ($bTable.Values | Where-Object { $_.Result -eq 'pass' }).Count
$cScore = ($cTable.Values | Where-Object { $_.Result -eq 'pass' }).Count
$nCases = $bTable.Count

Write-Host ''
Write-Host 'SCORE' -ForegroundColor Cyan
Write-Host ("  build    {0}/{1}" -f $bScore, $nCases)
Write-Host ("  control  {0}/{1}" -f $cScore, $cTable.Count)
Write-Host ("  {0} discriminators fail against the control" -f ($nCases - $cScore))

# ------------------------------------------------------------- tag audit ---

Write-Host ''
Write-Host 'TAG AUDIT' -ForegroundColor Cyan

$falseV21  = @()
$falseBoth = @()
$missing   = @()

foreach ($id in ($bTable.Keys | Sort-Object)) {
    $b = $bTable[$id]
    if (-not $cTable.ContainsKey($id)) { $missing += $b; continue }
    $c = $cTable[$id]
    if ($b.Tag -eq 'v21'  -and $c.Result -eq 'pass') { $falseV21  += $c }
    if ($b.Tag -eq 'both' -and $c.Result -eq 'FAIL') { $falseBoth += $c }
}

if ($falseV21.Count) {
    Write-Host ''
    Write-Host '  [v21] cases that PASSED against the control - the tag is a false claim:' -ForegroundColor Red
    $falseV21 | ForEach-Object { Write-Host ("    {0}  {1}" -f $_.Id, $_.Label) }
    Write-Host '    Retag [both], or replace with a case that actually discriminates.'
}
if ($falseBoth.Count) {
    Write-Host ''
    Write-Host '  [both] cases that FAILED against the control:' -ForegroundColor Red
    $falseBoth | ForEach-Object { Write-Host ("    {0}  {1}" -f $_.Id, $_.Label) }
    Write-Host '    Either the tag is wrong, or the control fails for an unrelated reason.'
}
if ($missing.Count) {
    Write-Host ''
    Write-Host '  cases absent from the control run:' -ForegroundColor Red
    $missing | ForEach-Object { Write-Host ("    {0}  {1}" -f $_.Id, $_.Label) }
}

$clean = ($falseV21.Count -eq 0 -and $falseBoth.Count -eq 0 -and $missing.Count -eq 0)

Write-Host ''
if ($bScore -ne $nCases) {
    Write-Host '  BUILD DOES NOT PASS ITS OWN SUITE' -ForegroundColor Red
    $verdict = 1
} elseif (-not $clean) {
    Write-Host '  TAGS ARE NOT CLEAN - do not pin this triple' -ForegroundColor Red
    $verdict = 1
} elseif ($cScore -eq $nCases) {
    Write-Host '  CONTROL SCORED PERFECT - this suite is not testing this release' -ForegroundColor Red
    $verdict = 1
} else {
    Write-Host '  clean' -ForegroundColor Green
    Write-Host ''
    Write-Host '  Pin this triple in Publish-Step18:'
    Write-Host ("    build    {0}  {1}/{2}" -f (Get-FileHash $Build -Algorithm MD5).Hash.ToLower(), $bScore, $nCases)
    Write-Host ("    control  {0}  {1}  {2}/{3}" -f $ControlRef, $ctlHash, $cScore, $nCases)
    $verdict = 0
}

Remove-Item $ctl -ErrorAction SilentlyContinue
Write-Host ''
exit $verdict
