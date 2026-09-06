<?php
/**
 * SLP Dealer Guard - v0.0.20 suite. Issue 31, orphan store_page reconciliation.
 *
 * WHAT THIS RELEASE CHANGES, AND HOW EACH PART IS REACHED HERE.
 *
 *   1. avalon_orphan_config() - three wp-config.php constants with defaults
 *      cleanup=true, max_trash=30, floor_pct=0.5.
 *   2. csv_processing_complete_func() rewritten two-pass. Pass 1 identifies
 *      stale rows and reads sl_linked_postid. Two rails then run against the
 *      complete candidate set. Pass 2 deletes rows and TRASHES the linked
 *      store_page post.
 *   3. Three summary fields: rows_removed, orphans_trashed, reconcile_aborted.
 *
 * The JS half of v0.0.20 - threshold 4, s0.77 and s0.79 - is scored by
 * suite-v019.js, not here. The loader version bump is asserted by
 * Publish-Step16, not here; it is not in the artefact this suite reads.
 *
 * THE FIXTURE IS THE MEASURED SHAPE. Aura, 2026-09-05, both environments:
 *
 *     rows 308  =  linked 308      every location row carries sl_linked_postid
 *     posts 321 LIVE / 320 DEV     orphans 13 LIVE / 12 DEV
 *
 * so an orphan is exactly a store_page post with no row pointing at it, with
 * no third case hiding. The twelve shared orphans carry identical post IDs
 * because DEV was cloned from LIVE.
 *
 * WHY THE RAILS ARE TESTED AT ALL. The pre-v0.0.20 loop had none. in_array()
 * against an empty $updated_locations misses every hash, so an import that
 * died before recording anything deleted the entire table - 308 rows. That
 * risk shipped. Adding post trashing on top is what made it unacceptable, so
 * the rail and the trashing land together and are asserted together.
 *
 * WHY A CONSTANT SUBPROCESS. A PHP constant cannot be undefined once set, so
 * defaults and overrides cannot both be exercised in one process. This file
 * re-invokes itself once, in 'override' mode, with all three constants
 * defined to non-defaults. If that spawn fails, its cases are counted as
 * FAILURES, never skipped - a skipped case is a silent pass.
 *
 * The override mode also proves floor_pct REACHES THE LOGIC rather than
 * merely being returned by the getter: the same fixture that proceeds under
 * the 0.5 default is refused under 0.9.
 *
 * NEGATIVE CONTROL, decision 20 and rev16 s0.51. The control target is a TAG,
 * not the working tree, so it survives staging and commit:
 *
 *     git show v0.0.19:slp_avalon/inc/class.slp_avalon.php > %TEMP%\ctl19.php
 *     php test/suite-v020.php %TEMP%\ctl19.php
 *     php test/suite-v020.php build/out20/class.slp_avalon.php
 *
 * v0.0.19 has no avalon_orphan_config() at all and a csv_processing_complete_func
 * with no rails and no trashing. MEASURED against that blob, 2026-09-06:
 *
 *     build   d00964ee60539ba470ae6d657280aba3      61/61
 *     control v0.0.19                               30/61
 *     -> 31 discriminators fail, 30 [both] cases hold
 *
 * Publish-Step16 pins that triple. A perfect score against the control means
 * this suite is not testing this release; a score below 30 means it is failing
 * for the wrong reason. Both look identical at the exit-code level.
 *
 * TAGS ARE LOAD-BEARING AND WERE WRONG IN THE FIRST CUT OF THIS FILE.
 * [v20] means the case MUST fail against the control. [both] means it must
 * hold on either. Fourteen cases were tagged [v20] and passed against the
 * control, so the tag was a false claim. They carry [both] now:
 *
 *   D6a-c, D7a       assert the ordinary delete path, which v0.0.19 does
 *                    identically. Nothing about them is new.
 *   D7b, D7d, D7e,   are of the form "nothing happened" - rev14 s8. They pass
 *   D8b, D9b, D10b   trivially against a build that never trashes. Each is
 *                    PAIRED with a discriminator that requires the new code to
 *                    have executed - D7c, D8a and D8c, D9a and D9c, D10a - and
 *                    it is the pair, not the half, that carries the claim.
 *   D14a-d           assert that cleanup=false reproduces v0.0.19. Against
 *                    v0.0.19 that is true by definition, so D14 cannot
 *                    discriminate. That the constant is READ AT ALL is
 *                    covered by D3a, which does fail against the control.
 *
 * rev20 s8: D11 asserts a TRANSITION, publish before and trash after, not the
 * endpoint. "The post is in trash" is also true of a fixture that was never
 * published.
 */

declare(strict_types=1);

$artefact = $argv[1] ?? __DIR__ . '/../build/out20/class.slp_avalon.php';
$mode     = $argv[2] ?? 'main';

if (!is_readable($artefact)) {
    fwrite(STDERR, "cannot read $artefact\n");
    exit(2);
}
$src = file_get_contents($artefact);

if ($mode === 'override') {
    define('AVALON_ORPHAN_CLEANUP', false);
    define('AVALON_ORPHAN_MAX_TRASH', 3);
    define('AVALON_RECONCILE_FLOOR_PCT', 0.9);
}

// ---------------------------------------------------------------- extract ---

/**
 * Lift whole method bodies out of the artefact by name.
 *
 * Verbatim from suite-v015 through suite-v018: token_get_all() rather than
 * brace counting, because braces inside strings and comments arrive inside a
 * single token. Missing methods are skipped rather than fatal, so a negative
 * control reports every failed assertion instead of dying on the first
 * absence - which matters here, because avalon_orphan_config() does not exist
 * in v0.0.19 at all.
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

$WANTED = ['csv_processing_complete_func', 'avalon_orphan_config'];
[$methods, $found] = extract_methods($src, $WANTED);

// ------------------------------------------------------ WordPress stubs ---
//
// Only what the method touches. Each stub records what it was asked to do,
// which is how the "nothing happened" cases are made non-trivial.

$GLOBALS['SUITE_OPTIONS'] = [];
$GLOBALS['SUITE_POSTS']   = [];   // id => ['type' => ..., 'status' => ...]
$GLOBALS['SUITE_LINKED']  = [];   // sl_id => post id
$GLOBALS['SUITE_DELETED'] = [];   // sl_ids passed to currentLocation->delete()
$GLOBALS['SUITE_TRASHED'] = [];   // post ids passed to wp_trash_post()
$GLOBALS['SUITE_TRASH_FROM'] = []; // post id => status AT the moment of trashing

function get_option($key, $default = false)
{
    return array_key_exists($key, $GLOBALS['SUITE_OPTIONS'])
        ? $GLOBALS['SUITE_OPTIONS'][$key] : $default;
}

function update_option($key, $value, $autoload = null)
{
    $GLOBALS['SUITE_OPTIONS'][$key] = $value;
    return true;
}

function get_post_type($id)
{
    $id = (int) $id;
    return isset($GLOBALS['SUITE_POSTS'][$id])
        ? $GLOBALS['SUITE_POSTS'][$id]['type'] : false;
}

function wp_trash_post($id)
{
    $id = (int) $id;
    if (!isset($GLOBALS['SUITE_POSTS'][$id])) {
        return false;
    }
    $GLOBALS['SUITE_TRASH_FROM'][$id]      = $GLOBALS['SUITE_POSTS'][$id]['status'];
    $GLOBALS['SUITE_POSTS'][$id]['status'] = 'trash';
    $GLOBALS['SUITE_TRASHED'][]            = $id;
    return (object) ['ID' => $id];
}

class Suite_Location
{
    public function delete($sl_id)
    {
        $GLOBALS['SUITE_DELETED'][] = (int) $sl_id;
        return true;
    }
}

class SLPlus
{
    public $currentLocation;
    public function __construct() { $this->currentLocation = new Suite_Location(); }
}

class Suite_Wpdb
{
    public $prefix = 'wp_';

    public function prepare($query, ...$args)
    {
        return vsprintf($query, $args);
    }

    /** The only query the method issues reads sl_linked_postid by sl_id. */
    public function get_var($query)
    {
        if (!preg_match('/sl_id\s*=\s*(\d+)/', $query, $m)) {
            return null;
        }
        $sl_id = (int) $m[1];
        return isset($GLOBALS['SUITE_LINKED'][$sl_id])
            ? (string) $GLOBALS['SUITE_LINKED'][$sl_id] : null;
    }
}

// ------------------------------------------------------------- the shim ---
//
// The extracted methods sit alongside stubbed collaborators, so the fixture
// controls exactly which rows are stale and which post each row points at.
// create_location_hash() is deliberately trivial - 'H' . sl_id - because the
// hashing is v0.0.14 behaviour scored by suite-v012, not this release.

$stubs = <<<'STUBS'
    public $state = [];
    public $log   = [];

    private function avalon_import_log($record)   { $this->log[] = $record; }
    private function avalon_state($key)           { return $this->state[$key] ?? 0; }
    private function avalon_state_set($key, $v)   { $this->state[$key] = $v; }
    private function avalon_state_bump($key)      { $this->state[$key] = ((int) ($this->state[$key] ?? 0)) + 1; }

    public $fixture_locations = [];
    public function slp_get_all_locations()       { return $this->fixture_locations; }
    public function create_location_hash($args)   { return 'H' . $args['location']['sl_id']; }

    /** Records with a given action, for the "it actually ran" predicates. */
    public function log_actions($action)
    {
        $n = 0;
        foreach ($this->log as $r) {
            if (($r['action'] ?? null) === $action) { $n++; }
        }
        return $n;
    }
STUBS;

$shim = sys_get_temp_dir() . '/suite-v020-shim-' . getmypid() . '.php';
file_put_contents($shim, "<?php\nclass Suite_Reconcile {\n" . $methods . $stubs . "\n}\n");
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
 * Build a world and run the reconcile against it.
 *
 * $rows      how many locations exist, sl_id 1..n
 * $stale     which sl_ids are ABSENT from avalon_updated_slp_locations
 * $linked    sl_id => post id; omitted rows get post id 0
 * $posts     post id => ['type' => ..., 'status' => ...]
 *
 * Returns the object so state, log and the global recorders can be read.
 */
function run_world(int $rows, array $stale, array $linked = [], array $posts = []): Suite_Reconcile
{
    $GLOBALS['SUITE_OPTIONS'] = [];
    $GLOBALS['SUITE_POSTS']   = $posts;
    $GLOBALS['SUITE_LINKED']  = $linked;
    $GLOBALS['SUITE_DELETED'] = [];
    $GLOBALS['SUITE_TRASHED'] = [];
    $GLOBALS['SUITE_TRASH_FROM'] = [];
    $GLOBALS['slplus']        = new SLPlus();
    $GLOBALS['wpdb']          = new Suite_Wpdb();

    $locations = [];
    $updated   = [];
    for ($i = 1; $i <= $rows; $i++) {
        $locations[] = [
            'sl_id'      => $i,
            'sl_store'   => 'Store ' . $i,
            'identifier' => 'ID' . $i,
        ];
        if (!in_array($i, $stale, true)) {
            $updated[] = 'H' . $i;
        }
    }

    $o = new Suite_Reconcile();
    $o->fixture_locations = $locations;
    $GLOBALS['SUITE_OPTIONS']['avalon_updated_slp_locations'] = $updated;

    if (method_exists($o, 'csv_processing_complete_func')) {
        $o->csv_processing_complete_func();
    }
    return $o;
}

/** A store_page fixture for n posts starting at 1000. */
function store_posts(array $ids): array
{
    $out = [];
    foreach ($ids as $id) {
        $out[$id] = ['type' => 'store_page', 'status' => 'publish'];
    }
    return $out;
}

// =====================================================================
// OVERRIDE MODE - runs in a subprocess with all three constants defined
// =====================================================================

if ($mode === 'override') {
    echo "suite-v020 [override mode: cleanup=false, max_trash=3, floor_pct=0.9]\n";

    $cfg = method_exists('Suite_Reconcile', 'avalon_orphan_config')
        ? (new Suite_Reconcile())->avalon_orphan_config()
        : 'SENTINEL_METHOD_ABSENT';

    ck(is_array($cfg) ? $cfg['cleanup'] : $cfg, false,
       '[v20]  D3a AVALON_ORPHAN_CLEANUP overrides the default');
    ck(is_array($cfg) ? $cfg['max_trash'] : $cfg, 3,
       '[v20]  D3b AVALON_ORPHAN_MAX_TRASH overrides the default');
    ck(is_array($cfg) ? $cfg['floor_pct'] : $cfg, 0.9,
       '[v20]  D3c AVALON_RECONCILE_FLOOR_PCT overrides the default');

    // D14. cleanup off must reproduce v0.0.19 exactly: rows go, posts stay.
    // 10 rows, 1 stale, so 9 updated - above a 0.9 floor of 9.
    $o = run_world(10, [7], [7 => 1007], store_posts([1007]));
    ck($GLOBALS['SUITE_DELETED'], [7],
       '[both] D14a cleanup off still deletes the stale row');
    ck($GLOBALS['SUITE_TRASHED'], [],
       '[both] D14b cleanup off trashes nothing');
    ck($GLOBALS['SUITE_POSTS'][1007]['status'], 'publish',
       '[both] D14c the orphan post is left published - v0.0.19 behaviour');
    ck($o->state['orphans_trashed'] ?? 0, 0,
       '[both] D14d orphans_trashed stays at zero');

    // floor_pct REACHES THE LOGIC. 10 rows, 2 stale -> 8 updated.
    // floor at 0.9 is 9, so this is refused. Under the 0.5 default it is 5,
    // and the same fixture proceeds - asserted as D6b in main mode.
    $o = run_world(10, [3, 4], [3 => 1003, 4 => 1004], store_posts([1003, 1004]));
    ck($GLOBALS['SUITE_DELETED'], [],
       '[v20]  D3d floor_pct 0.9 refuses a fixture the default 0.5 accepts');
    ck($o->state['reconcile_aborted'] ?? null, true,
       '[v20]  D3e the refusal sets reconcile_aborted, so it is not a no-op');
    ck($o->log_actions('aborted'), 1,
       '[v20]  D3f the refusal writes exactly one aborted record');

    @unlink($shim);
    echo "\n";
    printf("SUBSCORE %d/%d\n", $passed, $total);
    exit($passed === $total ? 0 : 1);
}

// =====================================================================
// MAIN MODE - no constants defined, so the defaults are under test
// =====================================================================

echo "suite-v020: " . basename($artefact) . "\n\n";

// ------------------------------------------------------- D1, D2 the config ---

$has_cfg = method_exists('Suite_Reconcile', 'avalon_orphan_config');
$cfg     = $has_cfg ? (new Suite_Reconcile())->avalon_orphan_config() : 'SENTINEL_METHOD_ABSENT';

ck($has_cfg, true,
   '[v20]  D1a avalon_orphan_config() exists on the artefact');
ck(is_array($cfg) ? array_keys($cfg) : $cfg, ['cleanup', 'max_trash', 'floor_pct'],
   '[v20]  D1b it returns exactly the three keys, in order');
ck(is_array($cfg) ? $cfg['cleanup'] : $cfg, true,
   '[v20]  D2a cleanup defaults to true');
ck(is_array($cfg) ? $cfg['max_trash'] : $cfg, 30,
   '[v20]  D2b max_trash defaults to 30 - sized off the measured 13, not'
   . ' mirrored from the Tier 2 cap of 60');
ck(is_array($cfg) ? $cfg['floor_pct'] : $cfg, 0.5,
   '[v20]  D2c floor_pct defaults to 0.5');

// ---------------------------------------------------- D4, D5, D6 the floor ---
//
// D4 is THE case. Against v0.0.19 an empty updated_locations deletes all 20
// rows. Against v0.0.20 it deletes none and says why.

$o = run_world(20, range(1, 20), [], []);
ck(count($GLOBALS['SUITE_DELETED']), 0,
   '[v20]  D4a empty updated_locations deletes NOTHING - v0.0.19 deletes all 20');
ck($o->state['reconcile_aborted'] ?? null, true,
   '[v20]  D4b and sets reconcile_aborted, so D4a is not a trivial pass');
ck($o->log_actions('aborted'), 1,
   '[v20]  D4c and writes exactly one aborted record');
ck($GLOBALS['SUITE_OPTIONS']['avalon_updated_slp_locations'], [],
   '[both] the option is still cleared on the refusal path');

// 10 rows, floor = ceil(10 * 0.5) = 5. 6 stale leaves 4 updated: below.
$o = run_world(10, [1, 2, 3, 4, 5, 6], [], []);
ck(count($GLOBALS['SUITE_DELETED']), 0,
   '[v20]  D5a four hashes against a floor of five is refused');
ck($o->state['reconcile_aborted'] ?? null, true,
   '[v20]  D5b the refusal is recorded');

// 5 stale leaves 5 updated: exactly at the floor, so it proceeds.
$o = run_world(10, [1, 2, 3, 4, 5], [], []);
ck($GLOBALS['SUITE_DELETED'], [1, 2, 3, 4, 5],
   '[both] D6a five hashes against a floor of five proceeds - the boundary'
   . ' is >=, not >');
ck($o->state['reconcile_aborted'] ?? 0, 0,
   '[both] D6b proceeding leaves reconcile_aborted unset');

// The fixture the override mode refuses at 0.9 must proceed at 0.5.
$o = run_world(10, [3, 4], [3 => 1003, 4 => 1004], store_posts([1003, 1004]));
ck($GLOBALS['SUITE_DELETED'], [3, 4],
   '[both] D6c the 0.9-refused fixture proceeds under the 0.5 default');

// ------------------------------------------------------- D7, D8 the cap ---
//
// Default cap is 30. 40 rows with 31 stale leaves 9 updated against a floor
// of 20 - which would be refused by rail 1 before rail 2 is reached. So the
// cap needs a table large enough that 31 stale still clears the floor:
// 70 rows, 31 stale leaves 39 updated against a floor of 35.

$linked = [];
$posts  = [];
for ($i = 1; $i <= 31; $i++) { $linked[$i] = 2000 + $i; $posts[2000 + $i] = ['type' => 'store_page', 'status' => 'publish']; }

$o = run_world(70, range(1, 31), $linked, $posts);
ck(count($GLOBALS['SUITE_DELETED']), 31,
   '[both] D7a 31 stale rows over a cap of 30: the rows still go');
ck($GLOBALS['SUITE_TRASHED'], [],
   '[both] D7b but NOT ONE post is trashed - the cap aborts the whole pass');
ck($o->log_actions('orphan_cap_exceeded'), 1,
   '[v20]  D7c and one orphan_cap_exceeded record says so');
ck($GLOBALS['SUITE_POSTS'][2001]['status'], 'publish',
   '[both] D7d the first candidate post is untouched, not half-applied');
ck($GLOBALS['SUITE_POSTS'][2031]['status'], 'publish',
   '[both] D7e and so is the last');

// 30 stale, exactly at the cap, so it acts.
$linked = [];
$posts  = [];
for ($i = 1; $i <= 30; $i++) { $linked[$i] = 3000 + $i; $posts[3000 + $i] = ['type' => 'store_page', 'status' => 'publish']; }

$o = run_world(70, range(1, 30), $linked, $posts);
ck(count($GLOBALS['SUITE_TRASHED']), 30,
   '[v20]  D8a 30 stale rows at a cap of 30 are all trashed - the boundary'
   . ' is >, not >=');
ck($o->log_actions('orphan_cap_exceeded'), 0,
   '[both] D8b and no cap record is written');
ck($o->state['orphans_trashed'] ?? 0, 30,
   '[v20]  D8c orphans_trashed counts every one');

// ------------------------------------------- D9, D10 the disposal guards ---
//
// A stale sl_linked_postid pointing at a page is the case that would trash
// an unrelated page. 10 rows, 2 stale, 8 updated against a floor of 5.

$o = run_world(10, [4, 8],
    [4 => 4004, 8 => 4008],
    [4004 => ['type' => 'page', 'status' => 'publish'],
     4008 => ['type' => 'store_page', 'status' => 'publish']]);

ck($GLOBALS['SUITE_TRASHED'], [4008],
   '[v20]  D9a only the store_page is trashed');
ck($GLOBALS['SUITE_POSTS'][4004]['status'], 'publish',
   '[both] D9b the page is left alone');
ck($o->log_actions('orphan_skipped'), 1,
   '[v20]  D9c and one orphan_skipped record proves the guard ran, rather'
   . ' than the loop never reaching it');
ck($GLOBALS['SUITE_DELETED'], [4, 8],
   '[both] both stale rows are deleted regardless of what their post was');

// post id 0 - a row that never had a page.
$o = run_world(10, [2, 5], [5 => 5005], store_posts([5005]));
ck($GLOBALS['SUITE_TRASHED'], [5005],
   '[v20]  D10a sl_id 2 has no linked post and contributes no disposal;'
   . ' only sl_id 5 is trashed');
ck($o->log_actions('orphan_skipped'), 0,
   '[both] D10b and writes no skip record - post id 0 is not an anomaly');
ck($GLOBALS['SUITE_DELETED'], [2, 5],
   '[both] both rows still go');

// -------------------------------------------------- D11 the transition ---
//
// rev20 s8. Endpoint alone is not evidence; a fixture that was never
// published is also "in trash". Both numbers.

$o = run_world(10, [6], [6 => 6006], store_posts([6006]));
ck([$GLOBALS['SUITE_TRASH_FROM'][6006] ?? null,
    $GLOBALS['SUITE_POSTS'][6006]['status']],
   ['publish', 'trash'],
   '[v20]  D11 the post transitions publish -> trash across the call. The'
   . ' before-state is captured INSIDE wp_trash_post(), so it is measured at'
   . ' the transition rather than re-read from the fixture literal');
ck($o->state['rows_removed'] ?? 0, 1,
   '[v20]  D12a rows_removed is bumped');
ck($o->state['orphans_trashed'] ?? 0, 1,
   '[v20]  D12b orphans_trashed is bumped');
ck($o->log_actions('orphan_trashed'), 1,
   '[v20]  D12c one orphan_trashed record carries the disposal');

// ---------------------------------------------- D12, D13 on the artefact ---

ck(substr_count($src, "'rows_removed'      => (int)"), 1,
   '[v20]  D12d the summary carries rows_removed');
ck(substr_count($src, "'orphans_trashed'   => (int)"), 1,
   '[v20]  D12e the summary carries orphans_trashed');
ck(substr_count($src, "'reconcile_aborted' => (bool)"), 1,
   '[v20]  D12f the summary carries reconcile_aborted');
ck(substr_count($src, "\$identifier = \$location['identifier'];"), 0,
   '[v20]  D13 the dead $identifier assignment is gone');

ck([substr_count($src, 'wp_trash_post('), substr_count($src, 'wp_delete_post(')],
   [1, 0],
   '[v20]  the disposal is a trash and never a delete - both numbers');

// ------------------------------------------------------------- guards -----

$GLOBALS['slplus'] = null;
$GLOBALS['wpdb']   = new Suite_Wpdb();
$GLOBALS['SUITE_OPTIONS'] = ['avalon_updated_slp_locations' => ['H1']];
$GLOBALS['SUITE_DELETED'] = [];
$g = new Suite_Reconcile();
$g->fixture_locations = [['sl_id' => 1, 'sl_store' => 'S', 'identifier' => 'I']];
if (method_exists($g, 'csv_processing_complete_func')) {
    $g->csv_processing_complete_func();
}
ck($GLOBALS['SUITE_DELETED'], [],
   '[both] G1a a missing SLPlus deletes nothing');
ck($GLOBALS['SUITE_OPTIONS']['avalon_updated_slp_locations'], [],
   '[both] G1b and the option is still cleared on that path');

$o = run_world(10, [], [], []);
ck($GLOBALS['SUITE_DELETED'], [],
   '[both] G2 a location whose hash IS present is never deleted');
ck($GLOBALS['SUITE_OPTIONS']['avalon_updated_slp_locations'], [],
   '[both] G3 the option is cleared on the normal path too');

ck(substr_count($src, 'public function has_dupes('), 1,
   '[both] G4 has_dupes() is untouched');
ck(substr_count($src, "'avalon_flush_import_log'),500,0);"), 1,
   '[both] G5a the flush stays at priority 500, accepted_args 0');
ck(substr_count($src, "'remove_old_csv_files_after_import'), 999);"), 1,
   '[both] G5b the working-directory cleanup stays at 999. NOTE the space'
   . ' after array(...) and the ABSENT accepted_args - line 62 is formatted'
   . ' differently from line 85 next to it, and this literal was wrong in the'
   . ' first cut of this file because it was copied from 85 rather than read');
ck(substr_count($src, 'public function create_location_hash('), 1,
   '[both] G6 create_location_hash() is still declared exactly once');

// ------------------------------------------------- earlier releases intact ---
//
// This build reopens the file that carries every prior release. Checked on the
// way past, the way suite-v018 checks v0.0.17's.

ck([substr_count($src, 'AVALON_TIER2_MAX_CORRECTIONS  : 60,'),
    substr_count($src, 'AVALON_TIER2_MAX_CORRECTIONS  : 25,')], [1, 0],
   '[both] v0.0.17 Tier 2 cap of 60 survives and 25 has not come back');
ck(substr_count($src, "'avalon_import_coordinate_guard'),20,1);"), 1,
   '[both] the v0.0.15 coordinate guard is still registered at priority 20');
ck(substr_count($src, "'territory_gate'),20,1);"), 1,
   '[both] the Layer 3 territory gate is still registered at priority 20');
ck(substr_count($src, "'DONNIE MARCH|HOWELL|MI',"), 1,
   '[both] the DONNIE MARCH exclusion survives');
ck(substr_count($src, 'overrides_rotated'), 3,
   '[both] v0.0.17 rotation state key: read, write, docblock');

// ---------------------------------------------------- the override subprocess

echo "\n";
$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
     . ' ' . escapeshellarg($artefact) . ' override 2>&1';
$out = shell_exec($cmd);

if ($out === null || !preg_match('/SUBSCORE (\d+)\/(\d+)/', (string) $out, $m)) {
    // Never skip. A spawn that did not run is a set of cases that did not pass.
    echo "  FAIL  the override subprocess did not report a score\n";
    if ($out !== null) { echo "        " . str_replace("\n", "\n        ", trim((string) $out)) . "\n"; }
    $total += 10;
} else {
    foreach (explode("\n", rtrim((string) $out)) as $line) {
        if ($line !== '' && strpos($line, 'SUBSCORE') !== 0) {
            echo "  " . $line . "\n";
        }
    }
    $passed += (int) $m[1];
    $total  += (int) $m[2];
}

// ------------------------------------------------------------- verdict ----

@unlink($shim);

$missing = array_values(array_diff($WANTED, $found));
if ($missing) {
    echo "\n  note: methods absent from this artefact: " . implode(', ', $missing) . "\n";
}

echo "\n";
printf("suite-v020: %d/%d assertions PASS\n", $passed, $total);
exit($passed === $total ? 0 : 1);
