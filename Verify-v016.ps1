<#
    Verify-v016.ps1 - SLP Dealer Guard v0.0.16 pre-stage verification.

    Run this BEFORE staging v0.0.16 into the working tree. The negative control
    needs the working tree to still hold v0.0.15; once you overwrite
    slp_avalon\inc\class.slp_avalon.php the control target is gone and this
    script will stop at step 5 rather than silently testing v0.0.16 against
    itself.

        cd D:\Temp\Projects\GitHub\slp-plugins
        .\Verify-v016.ps1

    Saved as a file and run as a file, not pasted: `throw` stops nothing at an
    interactive prompt.

    WHAT IT PROVES

      1  php -l on every suite and on the built class file
      2  build\out16 artefacts match the pins the build printed
      3  slp_avalon.js is untouched by this release
      4  the working tree still holds v0.0.15, so the control is valid
      5  suite-v016 scores 9/19 against v0.0.15   <- the negative control
      6  suite-v016 scores 19/19 against v0.0.16
      7  suite-v015 still scores 33/33 against the changed class file
      8  suite-v012 still scores 68/68 against the changed class file

    Step 5 is the one that matters. suite-v015 scored 33/33 against a build
    where the completion hook was broken, because it called
    avalon_flush_import_log() directly instead of firing the hook. A suite that
    has not been shown to fail is not evidence. If step 5 comes back with a
    score other than 9/19, the registration parser in suite-v016 is reading the
    wrong add_action() line and nothing downstream can be trusted.

    php -l here runs on whatever PHP is on this Windows box. WP Engine is on
    8.4. The authoritative lint is still the one on the server.
#>

$ErrorActionPreference = 'Stop'

# PowerShell's location and .NET's CurrentDirectory are separate. Resolve to an
# absolute path and set both before any System.IO call.
$repo = (Resolve-Path -LiteralPath $PSScriptRoot).Path
Set-Location -LiteralPath $repo
[System.IO.Directory]::SetCurrentDirectory($repo)

Write-Host ''
Write-Host "  repo       $repo"
Write-Host ''

# --------------------------------------------------------------- pins ------
# Expected hashes live inside a hashtable literal. A bare hash string on its
# own line is parsed as a command and the shell tries to execute it.

$pins = @{
    'build\out16\class.slp_avalon.php'     = '998e343bbe324656f8282c238f323441'
    'build\out16\slp_avalon.php'           = '5ff1a2b8f5a63d693587e994cbe947cb'
    'slp_avalon\assets\js\slp_avalon.js'   = '8c93719e41af3232c18773a104e8dedd'
    'slp_avalon\inc\class.slp_avalon.php'  = 'c5ff85e089366ded99b7b7d8b083f537'
}

$sizes = @{
    'build\out16\class.slp_avalon.php' = @{ Bytes = 83200; Crlf = 1831 }
    'build\out16\slp_avalon.php'       = @{ Bytes =  1808; Crlf =   59 }
}

$suites = @{
    'v016' = 'test\suite-v016.php'
    'v015' = 'test\suite-v015.php'
    'v012' = 'test\suite-v012.php'
}

$built    = 'build\out16\class.slp_avalon.php'
$builtPlg = 'build\out16\slp_avalon.php'
$working  = 'slp_avalon\inc\class.slp_avalon.php'

# ---------------------------------------------------------- helpers --------

function Get-Md5 {
    param([Parameter(Mandatory)][string] $RelPath)
    $abs = Join-Path $repo $RelPath
    if (-not (Test-Path -LiteralPath $abs)) { throw "VERIFY FAIL: missing $RelPath" }
    return (Get-FileHash -LiteralPath $abs -Algorithm MD5).Hash.ToLowerInvariant()
}

function Get-ByteStats {
    param([Parameter(Mandatory)][string] $RelPath)
    $abs   = Join-Path $repo $RelPath
    $bytes = [System.IO.File]::ReadAllBytes($abs)
    $crlf  = 0
    for ($i = 0; $i -lt $bytes.Length - 1; $i++) {
        if ($bytes[$i] -eq 13 -and $bytes[$i + 1] -eq 10) { $crlf++ }
    }
    return [pscustomobject]@{ Bytes = $bytes.Length; Crlf = $crlf }
}

function Assert-Pin {
    param([Parameter(Mandatory)][string] $RelPath)
    $got  = Get-Md5 $RelPath
    $want = $pins[$RelPath]
    if ($got -ne $want) {
        throw "VERIFY FAIL: $RelPath md5 $got, expected $want"
    }
    Write-Host ("  md5    ok  {0,-38} {1}" -f $RelPath, $got)
}

function Assert-Size {
    param([Parameter(Mandatory)][string] $RelPath)
    $s    = Get-ByteStats $RelPath
    $want = $sizes[$RelPath]
    if ($s.Bytes -ne $want.Bytes) {
        throw "VERIFY FAIL: $RelPath is $($s.Bytes) bytes, expected $($want.Bytes)"
    }
    if ($s.Crlf -ne $want.Crlf) {
        throw "VERIFY FAIL: $RelPath has CRLF=$($s.Crlf), expected $($want.Crlf)"
    }
    Write-Host ("  bytes  ok  {0,-38} {1} bytes  CRLF={2}" -f $RelPath, $s.Bytes, $s.Crlf)
}

function Invoke-Lint {
    param([Parameter(Mandatory)][string] $RelPath)
    $abs = Join-Path $repo $RelPath
    $out = & php -l $abs 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host ($out | Out-String)
        throw "VERIFY FAIL: php -l $RelPath"
    }
    Write-Host ("  lint   ok  {0}" -f $RelPath)
}

<#
    Run one suite and assert its score.

    The score line is the strong assertion; the exit code alone only says
    pass-or-fail, and a negative control that fails for the wrong reason looks
    identical to one that fails for the right reason. If a suite emits no score
    line the exit code is still checked and the discrepancy is reported.
#>
function Invoke-Suite {
    param(
        [Parameter(Mandatory)][string] $SuitePath,
        [Parameter(Mandatory)][string] $ArtefactPath,
        [Parameter(Mandatory)][int]    $ExpectPass,
        [Parameter(Mandatory)][int]    $ExpectTotal,
        [Parameter(Mandatory)][string] $Label
    )

    $suiteAbs = Join-Path $repo $SuitePath
    $artAbs   = Join-Path $repo $ArtefactPath

    $wantCode = 1
    if ($ExpectPass -eq $ExpectTotal) { $wantCode = 0 }

    Write-Host ''
    Write-Host ("  ---- {0}" -f $Label)
    Write-Host ("       {0}" -f $SuitePath)
    Write-Host ("       {0}" -f $ArtefactPath)

    $raw  = & php $suiteAbs $artAbs 2>&1
    $code = $LASTEXITCODE
    $text = ($raw | Out-String)

    $m = [regex]::Match($text, '(\d+)\s*/\s*(\d+)\s+assertions PASS')
    if ($m.Success) {
        $got = [int] $m.Groups[1].Value
        $tot = [int] $m.Groups[2].Value

        if ($tot -ne $ExpectTotal) {
            Write-Host $text
            throw "VERIFY FAIL [$Label]: suite has $tot assertions, expected $ExpectTotal"
        }
        if ($got -ne $ExpectPass) {
            Write-Host $text
            throw "VERIFY FAIL [$Label]: scored $got/$tot, expected $ExpectPass/$ExpectTotal"
        }
        Write-Host ("       score  {0}/{1}  as expected" -f $got, $tot)
    }
    else {
        Write-Warning "[$Label] no score line found; falling back to exit code only"
        Write-Host $text
    }

    if ($code -ne $wantCode) {
        throw "VERIFY FAIL [$Label]: exit code $code, expected $wantCode"
    }
    Write-Host ("       exit   {0}  as expected" -f $code)
}

# ============================================================ 1  lint =======

Write-Host '  == 1  syntax =='
Invoke-Lint $suites['v016']
Invoke-Lint $suites['v015']
Invoke-Lint $suites['v012']
Invoke-Lint $built
Invoke-Lint $builtPlg

# ============================================== 2  built artefact pins ======

Write-Host ''
Write-Host '  == 2  build\out16 =='
Assert-Pin  $built
Assert-Size $built
Assert-Pin  $builtPlg
Assert-Size $builtPlg

# ============================================== 3  js untouched =============

Write-Host ''
Write-Host '  == 3  slp_avalon.js is not in this release =='
Assert-Pin 'slp_avalon\assets\js\slp_avalon.js'

# ============================================== 4  control target ===========

Write-Host ''
Write-Host '  == 4  working tree still holds v0.0.15 =='
$workingMd5 = Get-Md5 $working
if ($workingMd5 -eq $pins[$built]) {
    throw ("VERIFY FAIL: the working tree already holds v0.0.16. " +
           "Run this script BEFORE staging - the negative control needs v0.0.15.")
}
Assert-Pin $working

# ============================================== 5  negative control =========

Write-Host ''
Write-Host '  == 5  NEGATIVE CONTROL =='
Invoke-Suite -SuitePath $suites['v016'] -ArtefactPath $working `
             -ExpectPass 9 -ExpectTotal 19 `
             -Label 'suite-v016 against v0.0.15 - MUST FAIL 9/19'

# ============================================== 6-8  positive runs ==========

Write-Host ''
Write-Host '  == 6-8  suites against v0.0.16 =='
Invoke-Suite -SuitePath $suites['v016'] -ArtefactPath $built `
             -ExpectPass 19 -ExpectTotal 19 `
             -Label 'suite-v016 against v0.0.16'

Invoke-Suite -SuitePath $suites['v015'] -ArtefactPath $built `
             -ExpectPass 33 -ExpectTotal 33 `
             -Label 'suite-v015 regression'

Invoke-Suite -SuitePath $suites['v012'] -ArtefactPath $built `
             -ExpectPass 68 -ExpectTotal 68 `
             -Label 'suite-v012 regression'

# ============================================== verdict =====================

Write-Host ''
Write-Host '  ========================================================'
Write-Host '   v0.0.16 VERIFIED'
Write-Host ''
Write-Host ('   class.slp_avalon.php  {0}' -f (Get-Md5 $built))
Write-Host ('   slp_avalon.php        {0}' -f (Get-Md5 $builtPlg))
Write-Host ''
Write-Host '   PHP suites   19 + 33 + 68 = 120'
Write-Host '   JS suites   161 unchanged, slp_avalon.js untouched'
Write-Host '   TOTAL       281'
Write-Host ''
Write-Host '   Next: stage, php -l on the server (PHP 8.4), commit, tag,'
Write-Host '         deploy to Aura DEV, then read avalon_geocode_last_run.'
Write-Host '  ========================================================'
Write-Host ''
