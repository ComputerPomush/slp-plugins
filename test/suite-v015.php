<?php
/**
 * SLP Dealer Guard - v0.0.15 suite. Import coordinate hygiene, Tier 1 + Tier 2.
 *
 * Same principle as suite-v012: the code under test is the code that ships.
 * class.slp_avalon.php cannot be require'd directly - it calls add_action() at
 * construction and needs WordPress - so the methods under test are lifted out
 * of the artefact with PHP's own tokenizer and run inside a shim class against
 * stubbed WordPress functions.
 *
 * Only ONE thing is retyped rather than extracted: the
 * `private $avalon_import_state = null;` property declaration, because
 * token_get_all() extraction is method-scoped. It holds no logic.
 *
 * NO CONSTANTS ARE DEFINED BY THIS SUITE. PHP constants cannot be redefined,
 * so defining AVALON_TIER2_MAX_CORRECTIONS to something small would have meant
 * testing a configuration that never ships. The cap and the budget are instead
 * exercised by feeding enough synthetic rows to trip the real defaults - 70
 * rows for a cap of 60, 160 addresses for a budget of 150. Geocoding is
 * stubbed, so that costs nothing.
 *
 * NEGATIVE CONTROL, decision 20. Run against the v0.0.14 artefact FIRST and
 * confirm it FAILS:
 *
 *     php test/suite-v015.php slp_avalon/inc/class.slp_avalon.php     <- must FAIL
 *     php test/suite-v015.php build/out15/class.slp_avalon.php        <- must PASS
 *
 * Against v0.0.14 every avalon_* method is absent, so the extractor skips them
 * and each behavioural assertion fails. Four methods DO exist there -
 * is_in_territory, territory_boxes, vincentyGreatCircleDistance and
 * geocode_from_address - so those cases pass in both runs and are marked
 * [both] below. If a case marked [v15] passes against v0.0.14, the extractor
 * is picking up the wrong methods and nothing here can be trusted.
 */

declare(strict_types=1);

$artefact = $argv[1] ?? __DIR__ . '/../build/out15/class.slp_avalon.php';
if (!is_readable($artefact)) {
    fwrite(STDERR, "cannot read $artefact\n");
    exit(2);
}

// ---------------------------------------------------------------- extract ---

/**
 * Lift whole method bodies out of the artefact by name.
 *
 * token_get_all() rather than brace counting, for the reason suite-v012
 * records: braces inside strings and comments arrive inside a single token, so
 * the tokenizer cannot cut a method in half the way an indentation match can.
 *
 * Missing methods are skipped rather than fatal, so the negative control
 * reports every failed assertion instead of dying on the first absent one.
 */
function extract_methods(string $src, array $wanted): array
{
    $tokens = token_get_all($src);
    $n      = count($tokens);
    $out    = '';
    $found  = [];

    for ($i = 0; $i < $n; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }
        $name = null;
        for ($j = $i + 1; $j < $n; $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                continue;
            }
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                $name = $tokens[$j][1];
            }
            break;
        }
        if ($name === null || !in_array($name, $wanted, true)) {
            continue;
        }

        // Walk back over the visibility keywords so the method keeps its
        // signature exactly as shipped.
        $start = $i;
        for ($b = $i - 1; $b >= 0; $b--) {
            if (is_array($tokens[$b]) && in_array($tokens[$b][0],
                [T_WHITESPACE, T_PUBLIC, T_PRIVATE, T_PROTECTED, T_STATIC, T_FINAL], true)) {
                if ($tokens[$b][0] !== T_WHITESPACE) {
                    $start = $b;
                }
                continue;
            }
            break;
        }

        $text  = '';
        $depth = 0;
        $open  = false;
        for ($k = $start; $k < $n; $k++) {
            $t = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
            $text .= $t;
            if ($t === '{') { $depth++; $open = true; }
            elseif ($t === '}') {
                $depth--;
                if ($open && $depth === 0) { break; }
            }
        }
        $out    .= $text . "\n\n";
        $found[] = $name;
    }

    return [$out, $found];
}

$WANTED = [
    'avalon_import_coordinate_guard',
    'avalon_field',
    'avalon_coord_is_sane',
    'avalon_import_config',
    'avalon_tier2_exclusions',
    'avalon_exclusion_key',
    'avalon_tier2_is_excluded',
    'avalon_note_exclusion_hit',
    'avalon_geocode_cached',
    'avalon_state',
    'avalon_state_set',
    'avalon_state_bump',
    'avalon_import_log',
    'avalon_flush_import_log',
    'geocode_from_address',
    'is_in_territory',
    'territory_boxes',
    'vincentyGreatCircleDistance',
];

[$methods, $found] = extract_methods(file_get_contents($artefact), $WANTED);
$missing = array_values(array_diff($WANTED, $found));

// ------------------------------------------------------ WordPress stubs ---

$GLOBALS['SUITE_OPTIONS']    = [];
$GLOBALS['SUITE_AUTOLOAD']   = [];
$GLOBALS['SUITE_GEO']        = [];
$GLOBALS['SUITE_GEO_CALLS']  = 0;
$GLOBALS['SUITE_GEO_ARGS']   = [];
$GLOBALS['SUITE_NOTICES']    = [];

function get_option($name, $default = false)
{
    return array_key_exists($name, $GLOBALS['SUITE_OPTIONS'])
        ? $GLOBALS['SUITE_OPTIONS'][$name]
        : $default;
}

function update_option($name, $value, $autoload = null)
{
    $GLOBALS['SUITE_OPTIONS'][$name]  = $value;
    $GLOBALS['SUITE_AUTOLOAD'][$name] = $autoload;
    return true;
}

function wp_json_encode($v) { return json_encode($v); }

function is_wp_error($t) { return ($t instanceof Suite_WP_Error); }

class Suite_WP_Error
{
    private $msg;
    public function __construct($m) { $this->msg = $m; }
    public function get_error_message() { return $this->msg; }
}

/**
 * Stubbed transport. Routes on the address in the query string, so
 * geocode_from_address() itself - including the new timeout argument and the
 * 0,0 refusal - is the real shipped code being exercised.
 */
function wp_remote_get($url, $args = [])
{
    $GLOBALS['SUITE_GEO_CALLS']++;
    $GLOBALS['SUITE_GEO_ARGS'][] = $args;

    $q = [];
    parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
    $addr = strtoupper(trim($q['address'] ?? ''));

    $table = $GLOBALS['SUITE_GEO'];
    $hit   = null;
    foreach ($table as $needle => $val) {
        if (strpos($addr, strtoupper($needle)) !== false) { $hit = $val; break; }
    }
    if ($hit === null) { $hit = ['status' => 'ZERO_RESULTS']; }

    if ($hit === 'HTTP_ERROR') {
        return new Suite_WP_Error('connection timed out');
    }
    if (isset($hit['status']) && $hit['status'] !== 'OK') {
        return ['body' => json_encode(['status' => $hit['status']])];
    }
    return ['body' => json_encode([
        'status'  => 'OK',
        'results' => [['geometry' => ['location' => [
            'lat' => $hit[0], 'lng' => $hit[1],
        ]]]],
    ])];
}

function wp_remote_retrieve_body($r) { return is_array($r) ? ($r['body'] ?? '') : ''; }

class Suite_Option { public $value = 'TEST-SERVER-KEY'; }
class Suite_SmartOptions { public $google_server_key; }
class Suite_SLPlus { public $SmartOptions; }

$slplus = new Suite_SLPlus();
$slplus->SmartOptions = new Suite_SmartOptions();
$slplus->SmartOptions->google_server_key = new Suite_Option();
$GLOBALS['slplus'] = $slplus;

// Any notice or warning from the shipped code is a failure, which is how the
// undefined-index defect is tested rather than asserted.
set_error_handler(function ($no, $str, $file, $line) {
    $GLOBALS['SUITE_NOTICES'][] = "$str";
    return true;
});
error_reporting(E_ALL);

$logFile = sys_get_temp_dir() . '/suite-v015-' . getmypid() . '.log';
@unlink($logFile);
ini_set('log_errors', '1');
ini_set('error_log', $logFile);

// ------------------------------------------------------------- the shim ---

$shim = sys_get_temp_dir() . '/suite-v015-shim-' . getmypid() . '.php';
file_put_contents($shim,
    "<?php\n" .
    "class Suite_Guard {\n" .
    "    private \$avalon_import_state = null;\n\n" .
    $methods .
    "}\n"
);
require $shim;

// ------------------------------------------------------------- harness ----

$passed = 0;
$total  = 0;

function ck($got, $want, string $label): void
{
    global $passed, $total;
    $total++;
    $ok = ($got === $want);
    if ($ok) { $passed++; }
    printf("  %s  %s\n", $ok ? 'pass' : 'FAIL', $label);
    if (!$ok) {
        printf("        want %s\n        got  %s\n",
            var_export($want, true), var_export($got, true));
    }
}

function reset_world(): Suite_Guard
{
    $GLOBALS['SUITE_OPTIONS']   = [];
    $GLOBALS['SUITE_AUTOLOAD']  = [];
    $GLOBALS['SUITE_GEO_CALLS'] = 0;
    $GLOBALS['SUITE_GEO_ARGS']  = [];
    $GLOBALS['SUITE_NOTICES']   = [];
    return new Suite_Guard();
}

/** Build a CSV row as it arrives at priority 20. */
function row(array $over = []): array
{
    return $over + [
        'sl_store'     => 'TEST MARINE',
        'sl_address'   => '1 Main St',
        'sl_city'      => 'Saginaw',
        'sl_state'     => 'MI',
        'sl_zip'       => '48601',
        'sl_country'   => 'USA',
        'sl_latitude'  => '43.419500000',
        'sl_longitude' => '-83.950800000',
    ];
}

/** Call the shipped filter, tolerating its absence in the negative control. */
function guard(Suite_Guard $g, array $r): array
{
    if (!method_exists($g, 'avalon_import_coordinate_guard')) { return ['__ABSENT__' => true]; }
    return $g->avalon_import_coordinate_guard($r);
}

/**
 * Did the filter actually run?
 *
 * Several assertions here are of the form "nothing happened" - no notice, no
 * geocode, no option written. Every one of those passes trivially when the
 * method is absent, which would let the negative control score points for code
 * that does not exist. Pairing them with ran() makes them discriminating.
 */
function ran(array $out): bool
{
    return !isset($out['__ABSENT__']);
}

/** Flush, tolerating absence, so the control reports rather than fatals. */
function flush_log(Suite_Guard $g, bool $final = true): void
{
    if (method_exists($g, 'avalon_flush_import_log')) { $g->avalon_flush_import_log($final); }
}

// One degree of latitude is 69.05 miles. All test points stay inside the
// CONUS + Canada box (lat 24.4-83.2, lng -141.0 to -52.0).
$BASE_LAT = 43.4195;
$BASE_LNG = -83.9508;

echo "\nsuite-v015 :: " . basename(dirname($artefact)) . '/' . basename($artefact) . "\n";
echo "  extracted " . count($found) . '/' . count($WANTED) . " methods\n";
if ($missing) { echo "  absent: " . implode(', ', $missing) . "\n"; }
echo "\n";

// ============================================================ TIER 1 ======

echo "Tier 1 - unusable coordinates\n";

$GLOBALS['SUITE_GEO'] = ['SAGINAW' => [$BASE_LAT, $BASE_LNG]];

// [v15] The keys are genuinely absent at priority 20; SLP backfills them one
// line later at 850. The old (int)$location_data['sl_latitude'] warned here.
$g = reset_world();
$r = row();
unset($r['sl_latitude'], $r['sl_longitude']);
$out = guard($g, $r);
ck($out['sl_latitude'] ?? null, $BASE_LAT, 'absent lat/lng keys are geocoded');
ck(ran($out) && $GLOBALS['SUITE_NOTICES'] === [], true,
   'absent keys raise no notice or warning');

// [v15] The ten Aura rows.
$g = reset_world();
$out = guard($g, row(['sl_latitude' => '0.000000000', 'sl_longitude' => '0.000000000']));
ck($out['sl_latitude'] ?? null, $BASE_LAT, '0,0 is geocoded and written');

// [v15] THE (int)-CAST DEFECT. (int)"-9838239.000000000" is truthy, so this row
// walked straight through the v0.0.14 gate untouched.
$g = reset_world();
$out = guard($g, row(['sl_longitude' => '-9838239.000000000']));
ck($out['sl_longitude'] ?? null, $BASE_LNG, 'out-of-range longitude reaches Tier 1');

$g = reset_world();
$out = guard($g, row(['sl_latitude' => 'N/A', 'sl_longitude' => '']));
ck($out['sl_latitude'] ?? null, $BASE_LAT, 'non-numeric coordinates are geocoded');

// [v15] A geocoder answering 0,0 has found nothing. Writing it back is how a
// zero row stays a zero row.
$g = reset_world();
$GLOBALS['SUITE_GEO'] = ['SAGINAW' => [0, 0]];
$out = guard($g, row(['sl_latitude' => '0.000000000', 'sl_longitude' => '0.000000000']));
ck($out['sl_latitude'] ?? null, '0.000000000', 'a 0,0 geocode answer is refused');

// [v15] Territory gate on the write, reusing Layer 3's predicate.
$g = reset_world();
$GLOBALS['SUITE_GEO'] = ['SAGINAW' => [51.5074, -0.1278]];   // London
$out = guard($g, row(['sl_latitude' => '0.000000000', 'sl_longitude' => '0.000000000']));
ck($out['sl_latitude'] ?? null, '0.000000000', 'out-of-territory geocode is refused');

// [v15] Transport failure leaves the row alone.
$g = reset_world();
$GLOBALS['SUITE_GEO'] = ['SAGINAW' => 'HTTP_ERROR'];
$out = guard($g, row(['sl_latitude' => '0.000000000', 'sl_longitude' => '0.000000000']));
ck($out['sl_latitude'] ?? null, '0.000000000', 'HTTP failure leaves the row unchanged');

// [v15] Nothing to geocode against.
$g = reset_world();
$GLOBALS['SUITE_GEO'] = ['SAGINAW' => [$BASE_LAT, $BASE_LNG]];
$r = row(['sl_address' => '', 'sl_city' => '', 'sl_state' => '',
          'sl_zip' => '', 'sl_country' => '', 'sl_latitude' => '0.000000000',
          'sl_longitude' => '0.000000000']);
$out = guard($g, $r);
ck(ran($out) && $GLOBALS['SUITE_GEO_CALLS'] === 0, true,
   'an empty address is never geocoded');

// ============================================================ TIER 2 ======

echo "\nTier 2 - coordinates that disagree with their address\n";

$GLOBALS['SUITE_GEO'] = ['SAGINAW' => [$BASE_LAT, $BASE_LNG]];

// [v15] 0.69 mi - ordinary geocoder disagreement, below the observation floor.
$g = reset_world();
$out = guard($g, row(['sl_latitude' => (string) ($BASE_LAT + 0.01)]));
ck($out['sl_latitude'] ?? null, (string) ($BASE_LAT + 0.01), 'under 2 mi is left alone');
ck(ran($out) && count($GLOBALS['SUITE_OPTIONS']) === 0, true,
   'under 2 mi writes nothing to the log');

// [v15] 5.0 mi - the Midwest Assets case. Observed, not corrected.
$g = reset_world();
$out = guard($g, row(['sl_latitude' => (string) ($BASE_LAT + 0.0725)]));
ck($out['sl_latitude'] ?? null, (string) ($BASE_LAT + 0.0725), '5 mi is NOT corrected');
flush_log($g);
$log = $GLOBALS['SUITE_OPTIONS']['avalon_geocode_overrides'] ?? [];
ck($log[0]['action'] ?? null, 'observed_not_corrected', '5 mi is logged as observed');

// [v15] 207 mi - the BAY OUTBOARD case.
$g = reset_world();
$out = guard($g, row(['sl_latitude' => (string) ($BASE_LAT + 3.0)]));
ck($out['sl_latitude'] ?? null, $BASE_LAT, '207 mi is corrected');

// [v15] Both exclusions, at distances that would otherwise be corrected.
$GLOBALS['SUITE_GEO'] = ['HOWELL' => [42.6073, -83.9294], 'PEMBINA' => [48.9686, -97.2456]];

$g = reset_world();
$r = row(['sl_store' => 'DONNIE MARCH', 'sl_city' => 'HOWELL', 'sl_state' => 'MI',
          'sl_latitude' => '42.220530000', 'sl_longitude' => '-83.466000000']);
$out = guard($g, $r);
ck($out['sl_latitude'] ?? null, '42.220530000', 'DONNIE MARCH is never moved');

$g = reset_world();
$r = row(['sl_store' => 'C/O Cole International USA', 'sl_city' => 'Pembina',
          'sl_state' => 'ND', 'sl_latitude' => '50.264520000',
          'sl_longitude' => '-96.049220000']);
$out = guard($g, $r);
ck($out['sl_latitude'] ?? null, '50.264520000', 'C/O Cole International is never moved');

// [v15] Matching survives a whitespace or case change in the feed.
$g = reset_world();
$r = row(['sl_store' => '  donnie   march ', 'sl_city' => 'howell', 'sl_state' => 'mi',
          'sl_latitude' => '42.220530000', 'sl_longitude' => '-83.466000000']);
$out = guard($g, $r);
ck($out['sl_latitude'] ?? null, '42.220530000', 'exclusion match ignores case and spacing');

// ======================================================= RAILS ===========

echo "\nRails\n";

// [v15] Correction cap. 70 rows all 207 mi off; exactly 60 move, then the pass
// aborts. The cap LATCHES; it does not roll back the 60 already written. It
// bounds how far a systemic geocode failure can get, which is all it ever
// did. v0.0.17 raised the default from 25: 17 corrections a night is a
// permanent baseline, not an exceptional event. rev15 s5, rev16 s7.2.
$g = reset_world();
$GLOBALS['SUITE_GEO'] = ['CITY' => [$BASE_LAT, $BASE_LNG]];
$moved = 0;
for ($i = 0; $i < 70; $i++) {
    $r = row([
        'sl_store'    => "STORE $i",
        'sl_city'     => "CITY $i",
        'sl_address'  => "$i Main St",
        'sl_latitude' => (string) ($BASE_LAT + 3.0),
    ]);
    $out = guard($g, $r);
    if (($out['sl_latitude'] ?? null) === $BASE_LAT) { $moved++; }
}
ck($moved, 60, 'correction cap stops at exactly 60');
flush_log($g);
$sum = $GLOBALS['SUITE_OPTIONS']['avalon_geocode_last_run'] ?? [];
ck($sum['tier2_aborted'] ?? null, true, 'the summary records the abort');

// [v15] Geocode budget. 160 distinct addresses, budget of 150.
$g = reset_world();
for ($i = 0; $i < 160; $i++) {
    $outB = guard($g, row([
        'sl_store'    => "BUDGET $i",
        'sl_city'     => "CITY $i",
        'sl_address'  => "$i Budget Rd",
        'sl_latitude' => '0.000000000', 'sl_longitude' => '0.000000000',
    ]));
}
ck(ran($outB) && $GLOBALS['SUITE_GEO_CALLS'] === 150, true,
   'geocode budget stops at exactly 150');

// [v15] Cache. The same address twice costs one call.
$g = reset_world();
$GLOBALS['SUITE_GEO'] = ['SAGINAW' => [$BASE_LAT, $BASE_LNG]];
guard($g, row(['sl_latitude' => '0.000000000', 'sl_longitude' => '0.000000000']));
$out2 = guard($g, row(['sl_latitude' => '0.000000000', 'sl_longitude' => '0.000000000']));
ck(ran($out2) && $GLOBALS['SUITE_GEO_CALLS'] === 1, true,
   'the address cache prevents a second geocode');

// ================================================== INFRASTRUCTURE =======

echo "\nInfrastructure\n";

// [both] The timeout that v0.0.14's bare curl_exec never had.
$g = reset_world();
// Wrapped: v0.0.14's geocode_from_address() calls curl_init() directly, so on
// a PHP build without ext-curl the negative control would die here on an
// uncaught Error instead of reporting the remaining assertions. Either way the
// assertion below fails against v0.0.14 - with curl present it reaches
// curl_exec and never touches the stubbed transport, so no timeout is recorded.
$hasGeo = method_exists($g, 'geocode_from_address');
if ($hasGeo) {
    try { $g->geocode_from_address('1 Main St, Saginaw, MI'); }
    catch (Throwable $e) { /* recorded by the assertion failing */ }
}
$args = $GLOBALS['SUITE_GEO_ARGS'][0] ?? [];
ck($hasGeo && isset($args['timeout']) && $args['timeout'] > 0, true,
   'geocode passes a positive timeout');

// [v15] Shipped defaults, asserted rather than assumed.
$g = reset_world();
$cfg = method_exists($g, 'avalon_import_config') ? $g->avalon_import_config() : [];
ck($cfg['correct_mi']      ?? null, 10.0, 'default correction threshold is 10 mi');
ck($cfg['observe_mi']      ?? null, 2.0,  'default observation floor is 2 mi');
ck($cfg['max_corrections'] ?? null, 60,   'default correction cap is 60');
ck($cfg['geocode_budget']  ?? null, 150,  'default geocode budget is 150');
ck($cfg['tier1']           ?? null, true, 'Tier 1 defaults on');
ck($cfg['tier2']           ?? null, true, 'Tier 2 defaults on');

// [v15] None of the three options may autoload.
$g = reset_world();
$GLOBALS['SUITE_GEO'] = ['SAGINAW' => [$BASE_LAT, $BASE_LNG]];
guard($g, row(['sl_latitude' => '0.000000000', 'sl_longitude' => '0.000000000']));
flush_log($g);
foreach (['avalon_geocode_overrides', 'avalon_geocode_cache', 'avalon_geocode_last_run'] as $opt) {
    ck($GLOBALS['SUITE_AUTOLOAD'][$opt] ?? null, 'no', "$opt is not autoloaded");
}

// [v15] A stale exclusion is the signal that a dealer was renamed.
$sum = $GLOBALS['SUITE_OPTIONS']['avalon_geocode_last_run'] ?? [];
ck(count($sum['stale_exclusions'] ?? []), 2, 'unmatched exclusions are reported');

// [v15] Overrides reach the PHP error log, not the web root.
ck(strpos((string) @file_get_contents($logFile), 'SLP Dealer Guard import') !== false,
   true, 'overrides are written to the PHP error log');

// ------------------------------------------------------------- verdict ----

@unlink($shim);
@unlink($logFile);
restore_error_handler();

echo "\n";
printf("suite-v015: %d/%d assertions PASS\n", $passed, $total);
exit($passed === $total ? 0 : 1);
