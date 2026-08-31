<#
.SYNOPSIS
    SLP Dealer Guard - live acceptance probe for v0.0.12.

.DESCRIPTION
    POSTs to admin-ajax.php the same way slp_core.js:1849 does and checks the
    payload. Unauthenticated, read-only, writes nothing.

    Fixes two defects in the first version of this file:

    1. $Paris held the coordinates and $paris held the response. PowerShell
       variable names are case-insensitive, so those are ONE variable: the
       response overwrote the coordinates and the csl_ajax_onload check died
       on a type error before the summary printed. Every origin is now named
       Origin* so a response variable cannot collide with one.

    2. Three assertions passed against the broken v0.0.11 build and therefore
       tested nothing. From Toronto the two Ontario dealers are the nearest
       dealers anyway, so they came back first with or without the fix. The
       discriminating tests now run from the provincial centroid, where the
       three nearest dealers are all in Michigan. The Toronto checks are kept
       but labelled as regression guards, which is all they are.

    NEGATIVE CONTROL, decision 20: against v0.0.11 the four checks marked
    [DISCRIMINATOR] below fail. Everything else passes on both builds.

.EXAMPLE
    .\probe-v012.ps1
#>

[CmdletBinding()]
param(
    [string]$BaseUrl = 'https://aurapontoonstg.wpenginepowered.com'
)

$ErrorActionPreference = 'Stop'
$endpoint = "$BaseUrl/wp-admin/admin-ajax.php"

# Google geocodes a bare "Ontario" to the provincial centroid, north of Lake
# Superior. The three nearest dealers to it are all in Michigan; the fifty
# nearest reach 568 mi and include Washago at 527 mi. That gap is what makes
# this origin a real test and Toronto a weak one.
$OriginCentroid = @{ lat = 51.2538; lng = -85.3232 }
$OriginToronto  = @{ lat = 43.6532; lng = -79.3832 }
$OriginDetroit  = @{ lat = 42.3314; lng = -83.0458 }
$OriginParis    = @{ lat = 48.8566; lng = 2.3522   }

$script:passed   = 0
$script:failures = @()

function Invoke-Search {
    param([string]$Address, [hashtable]$Origin, [string]$Action = 'csl_ajax_search')

    $body = @{
        action  = $Action
        address = $Address
        lat     = $Origin.lat
        lng     = $Origin.lng
        radius  = 25
    }
    return (Invoke-WebRequest -Uri $endpoint -Method Post -Body $body -UseBasicParsing).Content |
           ConvertFrom-Json
}

function Get-StateList {
    param($Result)
    if (-not $Result.response) { return @() }
    return @($Result.response | ForEach-Object { "$($_.state)".ToUpper() })
}

function Test-HasOntario {
    param($Result)
    return (@(Get-StateList $Result | Where-Object { $_ -in @('ON', 'ONTARIO') }).Count -ge 1)
}

function Assert {
    param([bool]$Condition, [string]$Label, [string]$Detail = '')
    if ($Condition) {
        $script:passed++
        Write-Host ("  ok    {0}" -f $Label) -ForegroundColor Green
    } else {
        $script:failures += $Label
        Write-Host ("  FAIL  {0}" -f $Label) -ForegroundColor Red
        if ($Detail) { Write-Host ("          {0}" -f $Detail) -ForegroundColor Red }
    }
}

Write-Host ''
Write-Host "SLP Dealer Guard - live probe v0.0.12  [$BaseUrl]" -ForegroundColor Cyan
Write-Host ('-' * 78)

# --------------------------------------------------------- the case defect
Write-Host ''
Write-Host 'Case-insensitive state lookup' -ForegroundColor Cyan

$resMixed = Invoke-Search -Address 'Michigan' -Origin $OriginDetroit
$resUpper = Invoke-Search -Address 'MICHIGAN' -Origin $OriginDetroit

Assert ($resMixed.count -gt 3) 'Michigan raises the SQL limit above the default 3' `
    "count $($resMixed.count)"
Assert ($resUpper.count -eq $resMixed.count) `
    '[DISCRIMINATOR] MICHIGAN returns the same as Michigan' `
    "MICHIGAN $($resUpper.count) vs Michigan $($resMixed.count) - ucwords() case defect"
Assert (@(Get-StateList $resUpper | Where-Object { $_ -ne 'MI' }).Count -eq 0) `
    'every MICHIGAN result is in Michigan'

# ------------------------------------------------------ provinces, centroid
# These are the tests that mean something. At v0.0.11 all three return
# MI, MI, MI because the province is not recognised, the SQL limit stays at 3,
# and the three nearest dealers to this point are all in Michigan.
Write-Host ''
Write-Host 'Province recognition - provincial centroid' -ForegroundColor Cyan

$resCen      = Invoke-Search -Address 'Ontario'         -Origin $OriginCentroid
$resCenUpper = Invoke-Search -Address 'ONTARIO'         -Origin $OriginCentroid
$resCenFull  = Invoke-Search -Address 'Ontario, Canada' -Origin $OriginCentroid

Assert (Test-HasOntario $resCen) `
    '[DISCRIMINATOR] Ontario surfaces an Ontario dealer' `
    "states: $((Get-StateList $resCen) -join ', ') - was MI,MI,MI at v0.0.11"
Assert (Test-HasOntario $resCenUpper) `
    '[DISCRIMINATOR] ONTARIO in caps surfaces an Ontario dealer' `
    "states: $((Get-StateList $resCenUpper) -join ', ')"
Assert (Test-HasOntario $resCenFull) `
    '[DISCRIMINATOR] autocomplete text "Ontario, Canada" surfaces one too' `
    "states: $((Get-StateList $resCenFull) -join ', ')"

# Expect exactly one: Washago is 527 mi away and inside the 50-row window,
# Orono is 588 mi and outside it. The backfill supplies the other two, which
# is the behaviour that was chosen deliberately. If this count changes, the
# dealer data moved - it is not a code regression.
$onCount = @(Get-StateList $resCen | Where-Object { $_ -in @('ON', 'ONTARIO') }).Count
Write-Host ("  note  {0} Ontario dealer(s) reachable from the centroid" -f $onCount) `
    -ForegroundColor Gray

# ---------------------------------------------- provinces, Toronto (guards)
# Regression guards only. Ontario dealers are the two NEAREST to Toronto, so
# these pass on the broken build too. Kept to catch a future change that
# breaks them, not to prove this one worked.
Write-Host ''
Write-Host 'Province recognition - Toronto (regression guards)' -ForegroundColor Cyan

$resTor    = Invoke-Search -Address 'Ontario' -Origin $OriginToronto
$torStates = Get-StateList $resTor

Assert (Test-HasOntario $resTor) 'Ontario from Toronto returns Ontario dealers' `
    "states: $($torStates -join ', ')"
Assert ($torStates.Count -gt 0 -and $torStates[0] -in @('ON', 'ONTARIO')) `
    'the first Toronto result is an Ontario dealer'

# ------------------------------------------------------------ no regressions
Write-Host ''
Write-Host 'Unchanged behaviour' -ForegroundColor Cyan

Assert (@(Get-StateList $resMixed | Where-Object { $_ -ne 'MI' }).Count -eq 0) `
    'Michigan still returns only Michigan dealers'

$resJunk = Invoke-Search -Address 'Zzqq Notastate' -Origin $OriginDetroit
Assert ($resJunk.count -eq 3) 'a non-state search still fills to three via the backfill' `
    "count $($resJunk.count)"

$resCity = Invoke-Search -Address 'Detroit, MI' -Origin $OriginDetroit
Assert ($resCity.count -ge 3) 'a city search is unaffected' "count $($resCity.count)"

# ------------------------------------------------- Layer 3 must still hold
# territory_gate() runs at priority 20 on the same hook v0.0.12 edits at
# priority 10, in the same file. Worth re-checking after any PHP change here.
Write-Host ''
Write-Host 'Layer 3 territory gate intact' -ForegroundColor Cyan

$resParis = Invoke-Search -Address 'Paris France' -Origin $OriginParis
Assert ($resParis.count -eq 0) 'Paris coordinates return no dealers' "count $($resParis.count)"
Assert ([bool]$resParis.avalon_territory_rejected) 'the rejection flag is set'

$resParisOnload = Invoke-Search -Address 'Paris France' -Origin $OriginParis -Action 'csl_ajax_onload'
Assert ($resParisOnload.count -eq 0) 'Paris also rejected under csl_ajax_onload' `
    "count $($resParisOnload.count)"

# ------------------------------------------------------------------ report
$total = $script:passed + $script:failures.Count
Write-Host ''
if ($script:failures.Count -eq 0) {
    Write-Host "probe-v012: $script:passed/$total assertions PASS" -ForegroundColor Green
    exit 0
}
Write-Host "probe-v012: $script:passed/$total PASS, $($script:failures.Count) FAIL" -ForegroundColor Red
exit 1
