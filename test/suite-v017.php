<?php
/**
 * SLP Dealer Guard - v0.0.17 suite. Rotation, the cap, and the address2 guard.
 *
 * WHAT THIS RELEASE CHANGES, AND HOW EACH PART IS REACHED HERE.
 *
 *   1. avalon_geocode_overrides now ROTATES on the first flush of a run.
 *      Behavioural. Driven through avalon_flush_import_log() with a seeded
 *      option store, including one pass through the WordPress dispatch model so
 *      the rotation is proven reachable on the hook path v0.0.16 repaired -
 *      which is the entire reason it was put in this callback rather than on an
 *      import-start hook.
 *   2. AVALON_TIER2_MAX_CORRECTIONS default 25 -> 60. Asserted by running
 *      avalon_import_config() AND by confirming no `: 25,` default survives in
 *      the artefact. rev16 s0.52: a partial control must assert both numbers,
 *      and so must the release. Arriving at 60 and leaving 25 behind are two
 *      different claims.
 *      The BEHAVIOURAL cap test lives in suite-v015.php, which owns the rails
 *      and was amended this release from 30 rows / 25 to 70 rows / 60.
 *   3. isset() guard on $location['sl_address2']. Asserted by warning count,
 *      and - the load-bearing part - by proving the hash did not move. rev14
 *      s0.32: create_location_hash() covers name_address_address2_city_state_
 *      zip_country_dealer_id and no coordinates, which is what keeps a
 *      coordinate-only write from falling off avalon_updated_slp_locations and
 *      triggering the nightly delete. If '' hashed differently from the null
 *      the unguarded read produced, all 308 rows would look changed.
 *   4. The circuit-breaker comment. Text.
 *
 * NEGATIVE CONTROL, decision 20 and rev16 s0.51. The control target is a TAG,
 * not the working tree, so it survives staging and commit:
 *
 *     git show v0.0.16:slp_avalon/inc/class.slp_avalon.php > %TEMP%\ctl.php
 *     php test/suite-v017.php %TEMP%\ctl.php
 *     php test/suite-v017.php build/out17/class.slp_avalon.php
 *
 * The control must score EXACTLY 10/22. Ten [both] cases hold in either run by
 * design: they check the dispatch model, the defaults this release does not
 * touch, hash neutrality, and v0.0.16's own wiring. Twelve [v17] cases must
 * not. 22/22 against the control means the suite is not testing this release;
 * under 10 means it is failing for the wrong reason. Both look identical at the
 * exit-code level, which is why Publish-Step13 asserts the score.
 *
 * rev14 s8: an assertion of the form "nothing happened" passes trivially when
 * the code under test is absent. The one such case here - no _prev option is
 * created when there is nothing to rotate - is paired with the overrides_rotated
 * state flag, which requires the rotation branch to have executed.
 */

declare(strict_types=1);

$artefact = $argv[1] ?? __DIR__ . '/../build/out17/class.slp_avalon.php';
if (!is_readable($artefact)) {
    fwrite(STDERR, "cannot read $artefact\n");
    exit(2);
}
$src = file_get_contents($artefact);

// ---------------------------------------------------------------- extract ---

/**
 * Lift whole method bodies out of the artefact by name.
 *
 * Verbatim from suite-v015 and suite-v016: token_get_all() rather than brace
 * counting, because braces inside strings and comments arrive inside a single
 * token. Missing methods are skipped rather than fatal, so a negative control
 * reports every failed assertion instead of dying on the first absence.
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
    'avalon_flush_import_log',
    'avalon_import_config',
    'avalon_state',
    'avalon_state_set',
    'avalon_tier2_exclusions',
    'create_location_hash',
];

[$methods, $found] = extract_methods($src, $WANTED);
$missing = array_values(array_diff($WANTED, $found));

// ---------------------------------------------- WordPress dispatch model ---

/**
 * Reproduce do_action() + WP_Hook::apply_filters(), as suite-v016 does.
 *
 * Used here for one case only: proving a bare do_action - the way SLP Power
 * fires the hook at SLP_Power_Locations_Import.php:773 - reaches the rotation.
 * suite-v016 owns the wiring itself; this suite only needs to know the rotation
 * is on that path.
 */
function suite_dispatch(callable $cb, int $accepted_args, array $args = [])
{
    if (empty($args)) {
        $args[] = '';
    }
    $num_args = count($args);

    if ($accepted_args === 0) {
        return call_user_func($cb);
    }
    if ($accepted_args >= $num_args) {
        return call_user_func_array($cb, $args);
    }
    return call_user_func_array($cb, array_slice($args, 0, $accepted_args));
}

/** Read accepted_args off the completion registration in the artefact. */
function parse_accepted_args(string $src, string $method): int
{
    $re = '/add_action\(\s*\'slp_csv_processing_complete\'\s*,\s*array\(\s*self::\$instance\s*,\s*\''
        . preg_quote($method, '/')
        . '\'\s*\)([^;)]*)\)\s*;/';
    if (!preg_match($re, $src, $m)) {
        return 1;
    }
    $parts = array_values(array_filter(
        array_map('trim', explode(',', ltrim(trim($m[1]), ','))),
        static function ($s) { return $s !== ''; }
    ));
    return isset($parts[1]) ? (int) $parts[1] : 1;
}

// ------------------------------------------------------ WordPress stubs ---

$GLOBALS['SUITE_OPTIONS']  = [];
$GLOBALS['SUITE_AUTOLOAD'] = [];

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

$logFile = sys_get_temp_dir() . '/suite-v017-log-' . getmypid() . '.txt';
@unlink($logFile);
ini_set('log_errors', '1');
ini_set('error_log', $logFile);

// ------------------------------------------------------------- the shim ---
//
// The property declaration is the one thing retyped rather than extracted, for
// the reason suite-v015 records: token_get_all() extraction is method-scoped.
// It holds no logic. suite_seed and suite_peek exist only to reach the private
// state helpers from outside the class.

$shim = sys_get_temp_dir() . '/suite-v017-shim-' . getmypid() . '.php';
file_put_contents($shim,
    "<?php\n" .
    "class Suite_Guard {\n" .
    "    private \$avalon_import_state = null;\n\n" .
    $methods .
    "    public function suite_seed(\$k, \$v) {\n" .
    "        if (method_exists(\$this, 'avalon_state_set')) { \$this->avalon_state_set(\$k, \$v); }\n" .
    "    }\n" .
    "    public function suite_peek(\$k) {\n" .
    "        return method_exists(\$this, 'avalon_state') ? \$this->avalon_state(\$k) : null;\n" .
    "    }\n" .
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

/** A guard for a brand-new import, with an empty option store. */
function fresh_guard(): Suite_Guard
{
    $GLOBALS['SUITE_OPTIONS']  = [];
    $GLOBALS['SUITE_AUTOLOAD'] = [];
    return new Suite_Guard();
}

/** n override records, tagged so the two runs are distinguishable. */
function records(int $n, string $tag): array
{
    $out = [];
    for ($i = 0; $i < $n; $i++) {
        $out[] = ['tier' => 2, 'action' => 'corrected', 'store' => "$tag $i"];
    }
    return $out;
}

function flush_buffer(Suite_Guard $g, array $recs): void
{
    $g->suite_seed('log_buffer', $recs);
    if (method_exists($g, 'avalon_flush_import_log')) {
        $g->avalon_flush_import_log(false);
    }
}

function opt(string $k)
{
    return $GLOBALS['SUITE_OPTIONS'][$k] ?? null;
}

echo "\nsuite-v017  artefact: $artefact\n\n";

if ($missing) {
    printf("  note  methods not found in artefact: %s\n\n", implode(', ', $missing));
}

// ------------------------------------------------------------- the cap ----

$cfg = method_exists('Suite_Guard', 'avalon_import_config')
    ? (new Suite_Guard())->avalon_import_config()
    : [];

ck($cfg['max_corrections'] ?? null, 60,
   '[v17]  default correction cap is 60');

// Both numbers. 60 arriving and 25 leaving are separate claims, and the old
// comment carried a second 25 that edit 4 had to take with it.
ck(substr_count($src, ': 25,') + substr_count($src, 'first 25'), 0,
   '[v17]  no default of 25 survives anywhere in the artefact');

ck($cfg['correct_mi'] ?? null, 10.0,
   '[both] correction threshold still 10 mi - rev15 s3, 8.32 to 10.27');

ck($cfg['observe_mi'] ?? null, 2.0,
   '[both] observation floor still 2 mi');

ck($cfg['geocode_budget'] ?? null, 150,
   '[both] geocode budget still 150');

ck([$cfg['tier1'] ?? null, $cfg['tier2'] ?? null], [true, true],
   '[both] both tiers still default on');

// ---------------------------------------------------------- the rotation ---
//
// Run 1's log is already in the option store. Run 2 opens and flushes.

$g = fresh_guard();
$GLOBALS['SUITE_OPTIONS']['avalon_geocode_overrides']  = records(5, 'run1');
$GLOBALS['SUITE_AUTOLOAD']['avalon_geocode_overrides'] = 'no';

flush_buffer($g, records(3, 'run2a'));

ck(count(opt('avalon_geocode_overrides_prev') ?? []), 5,
   '[v17]  the first flush of a run moves the previous log to _prev');

ck(count(opt('avalon_geocode_overrides') ?? []), 3,
   '[v17]  the current option holds only this run');

ck($GLOBALS['SUITE_AUTOLOAD']['avalon_geocode_overrides_prev'] ?? null, 'no',
   '[v17]  _prev is not autoloaded');

// Same run, buffer fills again at 20. Three or four of these happen per import.

flush_buffer($g, records(2, 'run2b'));

ck(count(opt('avalon_geocode_overrides_prev') ?? []), 5,
   '[v17]  a later flush in the same run does not rotate again');

ck(count(opt('avalon_geocode_overrides') ?? []), 5,
   '[v17]  a later flush appends to this run rather than replacing it');

// Run 3, through the WordPress dispatch model, fired bare the way SLP fires it.
// The option store carries over; a new guard is a new import.

$g2  = new Suite_Guard();
$acc = parse_accepted_args($src, 'avalon_flush_import_log');
$g2->suite_seed('log_buffer', records(4, 'run3'));
if (in_array('avalon_flush_import_log', $found, true)) {
    suite_dispatch([$g2, 'avalon_flush_import_log'], $acc);
}

ck([count(opt('avalon_geocode_overrides_prev') ?? []),
    count(opt('avalon_geocode_overrides') ?? [])], [5, 4],
   '[v17]  a new run rotates again, on the bare do_action path');

// Nothing to rotate. Paired with the state flag: without it this assertion
// would pass on an artefact that has no rotation code at all (rev14 s8).

$g3 = fresh_guard();
flush_buffer($g3, records(3, 'first'));

ck([array_key_exists('avalon_geocode_overrides_prev', $GLOBALS['SUITE_OPTIONS']),
    count(opt('avalon_geocode_overrides') ?? []),
    $g3->suite_peek('overrides_rotated')], [false, 3, true],
   '[v17]  nothing to rotate: no _prev is created, and the rotation still ran');

ck($GLOBALS['SUITE_AUTOLOAD']['avalon_geocode_overrides'] ?? null, 'no',
   '[both] avalon_geocode_overrides is still not autoloaded');

ck($g3->suite_peek('log_buffer'), [],
   '[both] the buffer is cleared after a flush');

// ------------------------------------------------------ the address2 guard ---
//
// rev16 s0.48: these warnings are NOT suppressed on the web or cron path, so
// ~300 a night reach the WP Engine error log and compete with the guard's own
// output for a 1500-row window.

$loc = [
    'sl_store'   => 'BAY OUTBOARD MARINE',
    'sl_address' => '123 Main St',
    'sl_city'    => 'SAGINAW',
    'sl_state'   => 'MI',
    'sl_zip'     => '48601',
    'sl_country' => 'US',
    'identifier' => 'CDMIBM1222',
];

$g4       = fresh_guard();
$warnings = 0;
set_error_handler(static function ($no, $str) use (&$warnings) {
    $warnings++;
    return true;
}, E_WARNING);
$hashMissing = method_exists($g4, 'create_location_hash')
    ? $g4->create_location_hash(['location' => $loc])
    : null;
restore_error_handler();

ck($warnings, 0,
   '[v17]  a location with no sl_address2 key raises no warning');

$hashEmpty = method_exists($g4, 'create_location_hash')
    ? $g4->create_location_hash(['location' => $loc + ['sl_address2' => '']])
    : null;

// THE assertion of this edit. An unguarded read yields null, and null
// interpolates as ''. If the guard substituted anything else, every one of the
// 308 location hashes would change and the reconcile at priority 10 would see
// 308 changed rows.
ck($hashMissing !== null && $hashMissing === $hashEmpty, true,
   '[both] the guard is hash-neutral: absent key hashes as the empty string');

$hashReal = method_exists($g4, 'create_location_hash')
    ? $g4->create_location_hash(['location' => $loc + ['sl_address2' => 'Suite 4']])
    : null;

ck($hashReal !== null && $hashReal !== $hashEmpty, true,
   '[both] a real address2 still changes the hash - the field is not neutered');

// ------------------------------------------------- the circuit breaker ----

ck(substr_count($src, 'and then stop half way.'), 0,
   '[v17]  the comment no longer claims the pass is all-or-nothing');

ck(substr_count($src, 'It does NOT roll back. Corrections already made'), 1,
   '[v17]  the comment records that the latch does not roll back');

// ------------------------------------------------------- v0.0.16 wiring ---
//
// This release reopens the file that carried the two-byte hook fix. A wiring
// defect here is invisible to a callback test - rev16 s0.38 - so it is checked
// on the way past.

ck(substr_count($src, "'avalon_flush_import_log'),500,0);"), 1,
   '[both] v0.0.16 accepted_args=0 survives');

ck(substr_count($src, "'avalon_import_coordinate_guard'),20,1);"), 1,
   '[both] the coordinate guard is still registered at priority 20');

// ------------------------------------------------------------- verdict ----

@unlink($shim);
@unlink($logFile);

echo "\n";
printf("suite-v017: %d/%d assertions PASS\n", $passed, $total);
exit($passed === $total ? 0 : 1);
