<#
    Verify-MapDelivery.ps1   r2

    Asserts that a store_page delivers a working Google map to an ANONYMOUS
    visitor. Compares the cached/optimized render against a ?nowprocket=1
    control, so a failure names which optimisation layer caused it.

    No hostname is baked in: -BaseUrl is required, so this runs unchanged
    against all six environments during the promotion wave.

    CHANGED IN r2
      A4 redefined. It previously asserted that the slplus JS global was
      reachable. slp_avalon v0.0.23 removed the store page map's dependency
      on that global entirely, so reachability stopped being the thing worth
      measuring - slplus is still trapped by WP Rocket's deferred-inline
      wrapper and that no longer matters here. A4 now asserts the stronger
      property: the map init does not reference slplus at all.
      A8, A9, A10 added for the v0.0.23 map configuration.

    Machine: Windows workstation, PowerShell 7. Uses curl.exe.

    EXIT 0 = all assertions passed. EXIT 1 = one or more failed.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string] $BaseUrl,
    [string] $Slug    = 'coty-marine-llc',
    [string] $WorkDir = ([System.IO.Path]::GetTempPath()),
    [switch] $KeepFiles
)

$ErrorActionPreference = 'Stop'

$UA   = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36'
$base = $BaseUrl.TrimEnd('/')
$page = "$base/store/$Slug/"
$ctrl = "$page`?nowprocket=1"

$fOpt = Join-Path $WorkDir 'mapverify-optimized.html'
$fCtl = Join-Path $WorkDir 'mapverify-control.html'

# On PowerShell 7 for Windows 'curl' is an alias for Invoke-WebRequest, so
# curl.exe must be named explicitly. On Linux the binary is plain 'curl'.
$CURL = $null
foreach ($c in @('curl.exe', 'curl')) {
    $cmd = Get-Command $c -CommandType Application -ErrorAction SilentlyContinue |
           Select-Object -First 1
    if ($cmd) { $CURL = $cmd.Source; break }
}
if (-not $CURL) { throw 'No curl binary found on PATH (looked for curl.exe, curl).' }

Write-Host ''
Write-Host '=== Verify-MapDelivery r2 ===================================='
Write-Host ("  target  : {0}" -f $page)
Write-Host ("  control : {0}" -f $ctrl)
Write-Host ''

& $CURL -sS -A $UA -o $fOpt $page  | Out-Null
& $CURL -sS -A $UA -o $fCtl $ctrl  | Out-Null

$opt = [System.IO.File]::ReadAllText($fOpt)
$ctl = [System.IO.File]::ReadAllText($fCtl)

Write-Host ("  fetched : optimized {0:N0} bytes / control {1:N0} bytes" -f $opt.Length, $ctl.Length)
Write-Host ''

# ---------------------------------------------------------------- helpers ---
function Get-Blocks([string] $html) {
    [regex]::Matches($html, '(?s)<script\b[^>]*>.*?</script>') | ForEach-Object { $_.Value }
}
function Find-Block([string] $html, [string] $needle) {
    foreach ($b in Get-Blocks $html) { if ($b -match [regex]::Escape($needle)) { return $b } }
    return $null
}
function Split-Block([string] $block) {
    if (-not $block) { return $null }
    $i = $block.IndexOf('>')
    [pscustomobject]@{
        Tag  = $block.Substring(0, $i + 1)
        Body = $block.Substring($i + 1)
    }
}
function Is-Delayed($blk) { $blk -and ($blk.Tag -match 'rocketlazyloadscript') }
function Is-Wrapped($blk) { $blk -and ($blk.Body.TrimStart() -match "^window\.addEventListener\(\s*['""]DOMContentLoaded") }

$script:results = @()
function Assert([string] $id, [string] $what, [bool] $ok, [string] $detail = '') {
    $script:results += [pscustomobject]@{ Id = $id; Check = $what; Pass = $ok; Detail = $detail }
}

# ------------------------------------------------------- control sanity -----
Assert 'C1' 'control has zero delayed scripts (nowprocket works)' `
    (([regex]::Matches($ctl, 'rocketlazyloadscript')).Count -eq 0) `
    ("count={0}" -f ([regex]::Matches($ctl, 'rocketlazyloadscript')).Count)

$ctlSlp = Split-Block (Find-Block $ctl 'slplus')
Assert 'C2' 'control declares slplus at genuine top level' `
    ($null -ne $ctlSlp -and -not (Is-Wrapped $ctlSlp)) `
    $(if ($ctlSlp) { 'unwrapped' } else { 'slplus block not found' })

# ------------------------------------- delay-JS exclusions still in place ---
$optMaps = Split-Block (Find-Block $opt 'maps.googleapis.com')
$optAval = Split-Block (Find-Block $opt 'slp_avalon.js')
$optInit = Split-Block (Find-Block $opt 'avalon_init_location_map')

Assert 'A1' 'Google Maps API is NOT delay-gated' `
    ($null -ne $optMaps -and -not (Is-Delayed $optMaps)) `
    $(if ($optMaps) { if (Is-Delayed $optMaps) { 'text/rocketlazyloadscript' } else { 'plain src' } } else { 'tag absent' })

Assert 'A2' 'slp_avalon.js is NOT delay-gated' `
    ($null -ne $optAval -and -not (Is-Delayed $optAval)) `
    $(if ($optAval) { if (Is-Delayed $optAval) { 'text/rocketlazyloadscript' } else { 'plain src' } } else { 'tag absent' })

Assert 'A3' 'inline map init is NOT delay-gated' `
    ($null -ne $optInit -and -not (Is-Delayed $optInit)) `
    $(if ($optInit) { if (Is-Delayed $optInit) { 'text/rocketlazyloadscript' } else { 'plain inline' } } else { 'tag absent' })

# ---------------------------- A4 (redefined in r2): independence from slplus -
$initBody = if ($optInit) { $optInit.Body } else { '' }
Assert 'A4' 'map init does NOT reference the slplus global' `
    ($null -ne $optInit -and $initBody -notmatch 'slplus\s*\.') `
    $(if (-not $optInit) { 'init script absent' }
      elseif ($initBody -match 'slplus\s*\.') { 'still reads slplus.* - pre-v0.0.23 build?' }
      else { 'independent' })

# --------------------------------------------------------- layout / CSS -----
Assert 'A5' 'map container has a height rule' `
    ($opt -match '#avalon_location_map\s*\{[^}]*height') `
    'searched whole document incl. wpr-usedcss'

Assert 'A6' 'map wrapper has a height rule' `
    ($opt -match '\.avalon_location_map_container\s*\{[^}]*height') `
    'searched whole document incl. wpr-usedcss'

Assert 'A7' 'exactly one map container is emitted' `
    ((([regex]::Matches($opt, "id=[""']avalon_location_map[""']")).Count + `
      ([regex]::Matches($opt, "class=[""']map-canvas-box[""']")).Count) -eq 1) `
    ("avalon={0} slp_core={1}" -f `
        ([regex]::Matches($opt, "id=[""']avalon_location_map[""']")).Count, `
        ([regex]::Matches($opt, "class=[""']map-canvas-box[""']")).Count)

# ------------------------------------------------ A8-A10: v0.0.23 config ----
$mapsSrc = ''
if ($optMaps) {
    $m = [regex]::Match($optMaps.Tag, '(?:data-rocket-)?src=["'']([^"'']*maps/api/js[^"'']*)["'']')
    if ($m.Success) { $mapsSrc = $m.Groups[1].Value }
}
# WordPress emits enqueued script URLs with &#038; rather than a bare
# ampersand, and WP Rocket may re-encode as &amp;. Normalise before matching
# or the query parameters are invisible to a naive [?&] pattern.
$mapsSrc = $mapsSrc -replace '&#0*38;', '&' -replace '&amp;', '&'
$chan = [regex]::Match($mapsSrc, '[?&]v=([^&"'']+)')
Assert 'A8' 'Maps API pins a release channel (v=)' `
    ($chan.Success) `
    $(if ($chan.Success) { "v=" + $chan.Groups[1].Value } else { 'no v= - defaulting to weekly' })

Assert 'A9' 'map controls are configured explicitly' `
    ($initBody -match 'cameraControl:\s*false' -and $initBody -match 'zoomControl:\s*true') `
    $(if ($initBody -match 'cameraControl:\s*false') { 'cameraControl off' } else { 'cameraControl not disabled' })

$icon = [regex]::Match($initBody, 'marker_options\.icon\s*=\s*["'']([^"'']+)["'']')
Assert 'A10' 'marker icon is emitted from settings' `
    ($icon.Success) `
    $(if ($icon.Success) { Split-Path $icon.Groups[1].Value -Leaf } else { 'no icon emitted (map_end_icon empty?)' })

# ------------------------------------------------------ negative control ----
$stillDelayed = ([regex]::Matches($opt, 'rocketlazyloadscript')).Count
Assert 'N1' 'WP Rocket delay is STILL active on this page' `
    ($stillDelayed -gt 0) `
    ("delayed scripts remaining={0}" -f $stillDelayed)

# ----------------------------------------------------------------- report ---
Write-Host '--- assertions -------------------------------------------------'
foreach ($r in $script:results) {
    $mark = if ($r.Pass) { 'PASS' } else { 'FAIL' }
    Write-Host ("  [{0}] {1,-4} {2,-48} {3}" -f $mark, $r.Id, $r.Check, $r.Detail)
}

$pass = ($script:results | Where-Object Pass).Count
$fail = ($script:results | Where-Object { -not $_.Pass }).Count
Write-Host ''
Write-Host ("  score: {0} pass / {1} fail / {2} total" -f $pass, $fail, $script:results.Count)
Write-Host '================================================================'
Write-Host ''

if (-not $KeepFiles) { Remove-Item $fOpt, $fCtl -ErrorAction SilentlyContinue }
else { Write-Host ("  kept: {0}`n        {1}" -f $fOpt, $fCtl) }

if ($fail -gt 0) { exit 1 } else { exit 0 }
