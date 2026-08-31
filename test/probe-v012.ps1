<#
.SYNOPSIS
    SLP Dealer Guard - live acceptance probe for v0.0.12.

.DESCRIPTION
    suite-v012.php tests the state helpers in isolation. This tests the thing
    the customer actually experiences, by POSTing to admin-ajax.php exactly as
    slp_core.js:1849 does and asserting on the payload that comes back.

    Both are needed. Handoff s9 records why: an earlier session's unit tests
    proved a change handler worked while nothing on the live page called it,
    and a live bug shipped underneath them. The helpers passing does not prove
    the SQL limit moved.

    admin-ajax.php accepts these unauthenticated - see s11 - so no credentials
    are needed and nothing is written.

    NEGATIVE CONTROL, decision 20: run this BEFORE deploying v0.0.12 and
    confirm the four state assertions FAIL. They will: MICHIGAN returns 3
    against Michigan's 35, and neither Ontario probe returns an Ontario dealer.

.PARAMETER BaseUrl
    Environment to probe. Defaults to Aura DEV.

.EXAMPLE
    .\probe-v012.ps1                     # before deploy: expect 4 FAIL
    .\probe-v012.ps1                     # after deploy:  expect all PASS
#>

[CmdletBinding()]
param(
    [string]$BaseUrl = 'https://aurapontoonstg.wpenginepowered.com'
)

$ErrorActionPreference = 'Stop'
$endpoint = "$BaseUrl/wp-admin/admin-ajax.php"

# Google's geocode for a bare "Ontario" search: the provincial centroid, up in
# the bush north of Lake Superior. The three nearest dealers to it are all in
# Michigan, which is the screenshot this version exists to change.
$OntarioCentroid = @{ lat = 51.2538; lng = -85.3232 }
$Toronto         = @{ lat = 43.6532; lng = -79.3832 }
$Detroit         = @{ lat = 42.3314; lng = -83.0458 }
$Paris           = @{ lat = 48.8566; lng = 2.3522   }

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
    $raw = Invoke-WebRequest -Uri $endpoint -Method Post -Body $body -UseBasicParsing
    return $raw.Content | ConvertFrom-Json
}

function Get-States {
    param($Result)
    if (-not $Result.response) { return @() }
    return @($Result.response | ForEach-Object { "$($_.state)".ToUpper() })
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

# ------------------------------------------------------------ the case defect
Write-Host ''
Write-Host 'Case-insensitive state lookup' -ForegroundColor Cyan

$mixed = Invoke-Search -Address 'Michigan' -Origin $Detroit
$upper = Invoke-Search -Address 'MICHIGAN' -Origin $Detroit

Assert ($mixed.count -gt 3) 'Michigan raises the SQL limit above the default 3' `
    "count $($mixed.count)"
Assert ($upper.count -eq $mixed.count) 'MICHIGAN returns the same as Michigan' `
    "MICHIGAN $($upper.count) vs Michigan $($mixed.count) - ucwords() case defect"
Assert (@(Get-States $upper | Where-Object { $_ -ne 'MI' }).Count -eq 0) `
    'every MICHIGAN result is in Michigan'

# ------------------------------------------------------------------ provinces
Write-Host ''
Write-Host 'Province recognition' -ForegroundColor Cyan

$onTor = Invoke-Search -Address 'Ontario' -Origin $Toronto
$onTorStates = Get-States $onTor
$onCount = @($onTorStates | Where-Object { $_ -in @('ON', 'ONTARIO') }).Count

Assert ($onCount -gt 0) 'Ontario from Toronto returns Ontario dealers' `
    "states: $($onTorStates -join ', ')"
Assert ($onTorStates.Count -gt 0 -and $onTorStates[0] -in @('ON', 'ONTARIO')) `
    'the first result is an Ontario dealer, not a backfilled US one'

# The centroid case. Measured before this build: the 50-row window reaches
# 568 mi from here, Washago sits at 527 mi and Orono at 588 mi. So exactly one
# Ontario dealer is reachable and the backfill supplies the other two. If this
# fails, check whether dealer density changed rather than assuming a code bug.
$onCen = Invoke-Search -Address 'Ontario' -Origin $OntarioCentroid
$onCenStates = Get-States $onCen
Assert (@($onCenStates | Where-Object { $_ -in @('ON', 'ONTARIO') }).Count -ge 1) `
    'Ontario at the provincial centroid surfaces at least one Ontario dealer' `
    "states: $($onCenStates -join ', ') - was MI,MI,MI at v0.0.11"

$onCanada = Invoke-Search -Address 'Ontario, Canada' -Origin $Toronto
Assert ($onCanada.count -eq $onTor.count) `
    'autocomplete text "Ontario, Canada" behaves as "Ontario"' `
    "Ontario, Canada $($onCanada.count) vs Ontario $($onTor.count)"

# ----------------------------------------------------------- no regressions
Write-Host ''
Write-Host 'Unchanged behaviour' -ForegroundColor Cyan

Assert (@(Get-States $mixed | Where-Object { $_ -ne 'MI' }).Count -eq 0) `
    'Michigan still returns only Michigan dealers'

$junk = Invoke-Search -Address 'Zzqq Notastate' -Origin $Detroit
Assert ($junk.count -eq 3) 'a non-state search still fills to three via the backfill' `
    "count $($junk.count)"

$city = Invoke-Search -Address 'Detroit, MI' -Origin $Detroit
Assert ($city.count -ge 3) 'a city search is unaffected' "count $($city.count)"

# Layer 3 is the backstop for direct POSTs and must survive a PHP change in
# the same file. territory_gate() runs at priority 20 on the same hook this
# version edits at priority 10.
Write-Host ''
Write-Host 'Layer 3 territory gate intact' -ForegroundColor Cyan
$paris = Invoke-Search -Address 'Paris France' -Origin $Paris
Assert ($paris.count -eq 0) 'Paris coordinates return no dealers' "count $($paris.count)"
Assert ([bool]$paris.avalon_territory_rejected) 'the rejection flag is set'

$parisOnload = Invoke-Search -Address 'Paris France' -Origin $Paris -Action 'csl_ajax_onload'
Assert ($parisOnload.count -eq 0) 'Paris also rejected under csl_ajax_onload'

# ---------------------------------------------------------------- report
$total = $script:passed + $script:failures.Count
Write-Host ''
if ($script:failures.Count -eq 0) {
    Write-Host "probe-v012: $script:passed/$total assertions PASS" -ForegroundColor Green
    exit 0
}
Write-Host "probe-v012: $script:passed/$total PASS, $($script:failures.Count) FAIL" -ForegroundColor Red
exit 1
