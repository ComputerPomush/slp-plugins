<?php
/**
 * SLP Dealer Guard - v0.0.16 suite. The completion-hook WIRING.
 *
 * WHY THIS SUITE EXISTS, AND WHY suite-v015 SCORED 33/33 ON A BROKEN BUILD.
 *
 * suite-v015 exercised avalon_flush_import_log() by calling it:
 *
 *     function flush_log(Suite_Guard $g, bool $final = true): void
 *
 * A direct PHP call with no argument DOES take the parameter default, so
 * $final was true, the summary was written, and every assertion passed. The
 * defect was never in the method. It was in the registration:
 *
 *     add_action('slp_csv_processing_complete',
 *                array(self::$instance,'avalon_flush_import_log'),500);
 *
 * $accepted_args defaults to 1. do_action() substitutes '' when the caller
 * supplies no argument, and SLP Power fires the hook bare at
 * SLP_Power_Locations_Import.php:773. WP_Hook::apply_filters() dispatches with
 * `1 >= 1`, so $final was bound to '' and the early return fired.
 *
 * So this suite does NOT call the method. It reads the accepted_args value out
 * of the shipped artefact, reproduces WordPress's own dispatch, and fires the
 * hook the way SLP fires it. Testing a callback in isolation cannot see a
 * wiring defect - the same lesson the v0.0.6 suite learned on Get My Position.
 *
 * NEGATIVE CONTROL, decision 20. Run against the v0.0.15 artefact FIRST and
 * confirm it FAILS:
 *
 *     php test/suite-v016.php slp_avalon/inc/class.slp_avalon.php   <- must FAIL
 *     php test/suite-v016.php build/out16/class.slp_avalon.php      <- must PASS
 *
 * Cases marked [both] pass in either run by design: they check the dispatch
 * model itself, or code v0.0.15 already had. If a case marked [v16] passes
 * against v0.0.15, the parser is reading the wrong registration and nothing
 * here can be trusted.
 */

declare(strict_types=1);

$artefact = $argv[1] ?? __DIR__ . '/../build/out16/class.slp_avalon.php';
if (!is_readable($artefact)) {
    fwrite(STDERR, "cannot read $artefact\n");
    exit(2);
}
$src = file_get_contents($artefact);

// ---------------------------------------------------------------- extract ---

/**
 * Lift whole method bodies out of the artefact by name.
 *
 * Verbatim from suite-v015: token_get_all() rather than brace counting,
 * because braces inside strings and comments arrive inside a single token.
 * Missing methods are skipped rather than fatal, so a negative control
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
    'avalon_state',
    'avalon_state_set',
    'avalon_tier2_exclusions',
];

[$methods, $found] = extract_methods($src, $WANTED);
$missing = array_values(array_diff($WANTED, $found));

// ------------------------------------------------- read the registration ---

/**
 * Parse an add_action() registration on slp_csv_processing_complete out of the
 * artefact text.
 *
 * Returns priority, accepted_args, and whether accepted_args was written
 * explicitly. WordPress's own defaults are reproduced here: priority 10,
 * accepted_args 1.
 */
function parse_registration(string $src, string $method): ?array
{
    $re = '/add_action\(\s*\'slp_csv_processing_complete\'\s*,\s*array\(\s*self::\$instance\s*,\s*\''
        . preg_quote($method, '/')
        . '\'\s*\)([^;)]*)\)\s*;/';

    if (!preg_match($re, $src, $m)) {
        return null;
    }
    $tail  = trim($m[1]);
    $parts = ($tail === '')
        ? []
        : array_values(array_filter(
            array_map('trim', explode(',', ltrim($tail, ','))),
            static function ($s) { return $s !== ''; }
          ));

    return [
        'priority'      => isset($parts[0]) ? (int) $parts[0] : 10,
        'accepted_args' => isset($parts[1]) ? (int) $parts[1] : 1,
        'explicit_args' => isset($parts[1]),
    ];
}

$reg     = parse_registration($src, 'avalon_flush_import_log');
$cleanup = parse_registration($src, 'remove_old_csv_files_after_import');

// ---------------------------------------------- WordPress dispatch model ---

/**
 * Reproduce do_action() + WP_Hook::apply_filters() exactly.
 *
 * wp-includes/plugin.php:
 *     function do_action( $hook_name, ...$arg ) {
 *         ...
 *         if ( empty( $arg ) ) { $arg[] = ''; }
 *
 * wp-includes/class-wp-hook.php:
 *     if ( 0 === $the_['accepted_args'] )            { call_user_func( $fn ); }
 *     elseif ( $the_['accepted_args'] >= $num_args ) { call_user_func_array( $fn, $args ); }
 *     else { call_user_func_array( $fn, array_slice( $args, 0, $accepted_args ) ); }
 *
 * The empty-string substitution is the whole defect. A bare do_action() does
 * NOT mean a zero-argument call.
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

$logFile = sys_get_temp_dir() . '/suite-v016-log-' . getmypid() . '.txt';
@unlink($logFile);
ini_set('log_errors', '1');
ini_set('error_log', $logFile);

// ------------------------------------------------------------- the shim ---
//
// The property declaration is the one thing retyped rather than extracted,
// for the reason suite-v015 records: token_get_all() extraction is
// method-scoped. It holds no logic. suite_seed/suite_peek/suite_state_null
// exist only to reach the private state helpers from outside the class.

$shim = sys_get_temp_dir() . '/suite-v016-shim-' . getmypid() . '.php';
file_put_contents($shim,
    "<?php\n" .
    "class Suite_Guard {\n" .
    "    private \$avalon_import_state = null;\n\n" .
    $methods .
    "    public function suite_seed(\$k, \$v) {\n" .
    "        if (method_exists(\$this, 'avalon_state_set')) { \$this->avalon_state_set(\$k, \$v); }\n" .
    "    }\n" .
    "    public function suite_state_null() { return \$this->avalon_import_state === null; }\n" .
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

/**
 * Seed the per-import state with the values measured on Aura DEV during the
 * first complete warm-cache pass, 2026-09-03. If the summary ever stops
 * reflecting what the counters hold, these numbers move with it.
 */
function seeded_guard(): Suite_Guard
{
    $GLOBALS['SUITE_OPTIONS']  = [];
    $GLOBALS['SUITE_AUTOLOAD'] = [];

    $g = new Suite_Guard();
    $g->suite_seed('log_buffer', [
        ['tier' => 1, 'action' => 'geocoded',   'store' => 'A'],
        ['tier' => 2, 'action' => 'corrected',  'store' => 'B'],
        ['tier' => 2, 'action' => 'observed_not_corrected', 'store' => 'C'],
    ]);
    $g->suite_seed('tier1_written',  11);
    $g->suite_seed('tier2_written',  17);
    $g->suite_seed('observed',       27);
    $g->suite_seed('excluded',        2);
    $g->suite_seed('geocodes_spent', 150);
    $g->suite_seed('tier2_aborted', false);
    // One exclusion matched, one did not. rev14 s3.2: the Cole row was
    // corrected upstream, so its exclusion should report as stale.
    $g->suite_seed('exclusion_hits', ['DONNIE MARCH|HOWELL|MI' => 1]);
    return $g;
}

echo "\nsuite-v016  artefact: $artefact\n\n";

if ($missing) {
    printf("  note  methods not found in artefact: %s\n\n", implode(', ', $missing));
}

// --------------------------------------------------------- registration ---

ck($reg !== null, true,
   '[both] a completion registration for avalon_flush_import_log exists');

ck($reg['priority'] ?? null, 500,
   '[both] registered at priority 500');

ck($reg['explicit_args'] ?? null, true,
   '[v16]  accepted_args is written explicitly, not defaulted');

ck($reg['accepted_args'] ?? null, 0,
   '[v16]  accepted_args is 0');

ck(substr_count($src, "'avalon_flush_import_log'),500);"), 0,
   '[v16]  no three-argument registration survives');

ck(substr_count($src, "'avalon_flush_import_log'),500,0);"), 1,
   '[v16]  the four-argument registration appears exactly once');

// The fix is inert if the signature ever loses its default.
ck(substr_count($src, 'public function avalon_flush_import_log($final = true){'), 1,
   '[both] the defaulted signature this release exists to reach');

// rev14 s0.27 and the Layer 3 priority lesson: nothing else may drift.
ck($cleanup['priority'] ?? null, 999,
   '[both] remove_old_csv_files_after_import still at 999');

ck(substr_count($src,
   "add_filter('slp_ajax_find_locations_complete',array(self::\$instance,'territory_gate'),20,1);"), 1,
   '[both] the Layer 3 territory gate is still at 20');

// ------------------------------------------------- the dispatch model ------
//
// These two prove the harness discriminates before it is trusted on the
// artefact. They pass in both runs by design.

$seen = null;
suite_dispatch(function ($final = true) use (&$seen) { $seen = $final; }, 1);
ck($seen, '',
   '[both] model: accepted_args=1 and a bare do_action binds the empty string');

$seen = null;
suite_dispatch(function ($final = true) use (&$seen) { $seen = $final; }, 0);
ck($seen, true,
   '[both] model: accepted_args=0 lets the declared default apply');

// --------------------------------------------------- the wiring, end to end ---
//
// Fire the hook the way SLP fires it - bare, no arguments - through the
// accepted_args value read out of the shipped artefact.

$g   = seeded_guard();
$acc = $reg['accepted_args'] ?? 1;

if (!in_array('avalon_flush_import_log', $found, true)) {
    ck(false, true, '[v16]  a bare do_action writes avalon_geocode_last_run');
    ck(false, true, '[v16]  the summary carries the run counters');
    ck(false, true, '[v16]  tier2_aborted is reported');
    ck(false, true, '[v16]  the stale exclusion is reported');
    ck(false, true, '[v16]  avalon_geocode_last_run is not autoloaded');
    ck(false, true, '[v16]  per-import state is reset after the final flush');
} else {
    suite_dispatch([$g, 'avalon_flush_import_log'], $acc);

    $sum = $GLOBALS['SUITE_OPTIONS']['avalon_geocode_last_run'] ?? null;

    ck(is_array($sum), true,
       '[v16]  a bare do_action writes avalon_geocode_last_run');

    ck([
        $sum['tier1_written']  ?? null,
        $sum['tier2_written']  ?? null,
        $sum['observed']       ?? null,
        $sum['excluded']       ?? null,
        $sum['geocodes_spent'] ?? null,
    ], [11, 17, 27, 2, 150],
       '[v16]  the summary carries the run counters');

    ck($sum['tier2_aborted'] ?? null, false,
       '[v16]  tier2_aborted is reported');

    ck($sum['stale_exclusions'] ?? null, ['C/O COLE INTERNATIONAL USA|PEMBINA|ND'],
       '[v16]  the stale exclusion is reported');

    ck($GLOBALS['SUITE_AUTOLOAD']['avalon_geocode_last_run'] ?? null, 'no',
       '[v16]  avalon_geocode_last_run is not autoloaded');

    ck($g->suite_state_null(), true,
       '[v16]  per-import state is reset after the final flush');
}

// ------------------------------------------- the flushes that DID work -----
//
// v0.0.15 wrote both of these before taking the early return, so these pass in
// both runs. They are here so a regression in the fix cannot silently break
// what already worked.

$g2 = seeded_guard();
if (in_array('avalon_flush_import_log', $found, true)) {
    suite_dispatch([$g2, 'avalon_flush_import_log'], $reg['accepted_args'] ?? 1);
}
ck(count($GLOBALS['SUITE_OPTIONS']['avalon_geocode_overrides'] ?? []), 3,
   '[both] the log buffer still reaches avalon_geocode_overrides');

ck($GLOBALS['SUITE_AUTOLOAD']['avalon_geocode_overrides'] ?? null, 'no',
   '[both] avalon_geocode_overrides is not autoloaded');

// ------------------------------------------------------------- verdict ----

@unlink($shim);
@unlink($logFile);

echo "\n";
printf("suite-v016: %d/%d assertions PASS\n", $passed, $total);
exit($passed === $total ? 0 : 1);
