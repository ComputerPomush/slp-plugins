<?php
/**
 * SLP Dealer Guard - v0.0.21 suite. Removal of the orphan-trash apparatus.
 *
 * WHY suite-v020 SCORED 61/61 ON A BUILD THAT COULD NOT WORK.
 *
 * suite-v020 stubbed the collaborator like this:
 *
 *     class Suite_Location {
 *         public function delete($sl_id) {
 *             $GLOBALS['SUITE_DELETED'][] = (int) $sl_id;
 *             return true;
 *         }
 *     }
 *
 * It records the sl_id and touches nothing else, so in that world the linked
 * store_page survives the delete. That is exactly the claim the comments at
 * lines 691 and 1269 made, and it is false. The double was built from the
 * CALLER'S BELIEF about the dependency rather than from the dependency's
 * source, so a green run was guaranteed and meaningless for that path. The
 * suite did not miss the defect. It asserted it.
 *
 * WHAT SLP ACTUALLY DOES, read at 2311.17.01 on 2026-09-07:
 *
 *   SLPlus_Location.php:771  delete($id)   -> get_location($id) first, so
 *                                            linked_postid is loaded fresh
 *                            :794          -> delete_store_pages()
 *                            :832-846      -> add_filter('pre_delete_post',
 *                                              'only_delete_store_pages')
 *                                              wp_delete_post($postid, true)
 *                            :848          -> returns false unless
 *                                              $post->post_type ===
 *                                              SLPlus::locationPostType
 *   SLPlus.php:81            const locationPostType = 'store_page'
 *
 * So the delete force-destroys the linked post if and only if it is a
 * store_page. Suite_Location::delete() below models that, and everything in
 * this file follows from it.
 *
 * THE CORRECTED DOUBLE IS ITSELF THE NEGATIVE CONTROL. Scored against the
 * v0.0.20 blob, the trash branch at line 1389 never fires - get_post_type()
 * returns false because SLP destroyed the post one line earlier - so
 * orphans_trashed stays 0 and orphan_skipped is logged instead. That is the
 * production failure, reproduced in a fixture for the first time.
 *
 * WHAT THIS RELEASE CHANGES.
 *
 *   1. avalon_orphan_config() keeps floor_pct only. cleanup and max_trash
 *      configured a branch that could never execute.
 *   2. Pass 2 records each store_page BEFORE SLP destroys it -
 *      page_destroyed_by_slp with the slug, or page_retained_not_store_page
 *      when the pre_delete_post veto will spare it - and bumps
 *      pages_destroyed. The wp_trash_post() branch and the disposal cap are
 *      gone.
 *   3. Summary field orphans_trashed becomes pages_destroyed. The old field
 *      could only ever report 0.
 *
 * TAGS ARE CLAIMS AND MUST BE AUDITED, NOT ASSERTED. rev20 s8 records that
 * fourteen cases in suite-v020 were tagged [v20] and passed against the
 * control, which made the tag a false claim. Verify-Suite021.ps1 diffs the
 * two runs case by case and reports any [v21] that passes against the
 * control or any [both] that fails. Do not pin a score triple until it has
 * run clean.
 *
 * NEGATIVE CONTROL, decision 20 and rev16 s0.51. The control target is a
 * TAG, not the working tree:
 *
 *     git show v0.0.20:slp_avalon/inc/class.slp_avalon.php > ctl20.php
 *     php test/suite-v021.php ctl20.php
 *     php test/suite-v021.php build/out21/class.slp_avalon.php
 *
 * A perfect score against the control means this suite is not testing this
 * release.
 *
 * Usage:  php test/suite-v021.php [artefact] [main|override]
 */

$artefact = $argv[1] ?? __DIR__ . '/../build/out21/class.slp_avalon.php';
$mode     = $argv[2] ?? 'main';

if (!is_readable($artefact)) {
    fwrite(STDERR, "cannot read $artefact\n");
    exit(2);
}
$src = file_get_contents($artefact);

if ($mode === 'override') {
    define('AVALON_RECONCILE_FLOOR_PCT', 0.9);
}

// ---------------------------------------------------------------- extract ---
//
// Verbatim from suite-v015 through suite-v020: token_get_all() rather than
// brace counting, because braces inside strings and comments arrive inside a
// single token. Missing methods are skipped rather than fatal, so a negative
// control reports every failed assertion instead of dying on the first
// absence.

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

$GLOBALS['SUITE_OPTIONS']       = [];
$GLOBALS['SUITE_POSTS']         = [];  // id => ['type','status','name']
$GLOBALS['SUITE_LINKED']        = [];  // sl_id => post id
$GLOBALS['SUITE_DELETED']       = [];  // sl_ids passed to currentLocation->delete()
$GLOBALS['SUITE_TRASHED']       = [];  // post ids passed to wp_trash_post()
$GLOBALS['SUITE_FORCE_DELETED'] = [];  // post ids SLP destroyed
$GLOBALS['SUITE_VETOED']        = [];  // post ids the pre_delete_post filter spared

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

/**
 * v0.0.21 reads the slug for the disposal log. A destroyed post has no
 * slug, so this returning '' is how the ORDERING is observable: if the log
 * were ever moved after the delete, the recorded slug would go empty while
 * every other assertion still passed.
 */
function get_post_field($field, $id, $context = 'display')
{
    $id = (int) $id;
    if (!isset($GLOBALS['SUITE_POSTS'][$id])) {
        return '';
    }
    if ($field === 'post_name') {
        return $GLOBALS['SUITE_POSTS'][$id]['name'] ?? '';
    }
    return $GLOBALS['SUITE_POSTS'][$id][$field] ?? '';
}

/**
 * Retained so the control build can call it. If v0.0.21 ever reaches this,
 * SUITE_TRASHED is non-empty and the structural case G1 has already failed.
 */
function wp_trash_post($id)
{
    $id = (int) $id;
    if (!isset($GLOBALS['SUITE_POSTS'][$id])) {
        return false;
    }
    $GLOBALS['SUITE_POSTS'][$id]['status'] = 'trash';
    $GLOBALS['SUITE_TRASHED'][]            = $id;
    return (object) ['ID' => $id];
}

/**
 * THE CORRECTION. suite-v020's version of this recorded the sl_id and left
 * the post standing. SLP force-deletes the linked post behind a
 * pre_delete_post filter that vetoes any type other than store_page.
 */
class Suite_Location
{
    public function delete($sl_id)
    {
        $sl_id = (int) $sl_id;
        $GLOBALS['SUITE_DELETED'][] = $sl_id;

        $post_id = (int) ($GLOBALS['SUITE_LINKED'][$sl_id] ?? 0);
        if ($post_id > 0 && isset($GLOBALS['SUITE_POSTS'][$post_id])) {
            if ($GLOBALS['SUITE_POSTS'][$post_id]['type'] === 'store_page') {
                unset($GLOBALS['SUITE_POSTS'][$post_id]);
                $GLOBALS['SUITE_FORCE_DELETED'][] = $post_id;
            } else {
                $GLOBALS['SUITE_VETOED'][] = $post_id;
            }
        }
        unset($GLOBALS['SUITE_LINKED'][$sl_id]);
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

    public function log_actions($action)
    {
        $n = 0;
        foreach ($this->log as $r) {
            if (($r['action'] ?? null) === $action) { $n++; }
        }
        return $n;
    }

    /** First record with the given action, or null. */
    public function log_first($action)
    {
        foreach ($this->log as $r) {
            if (($r['action'] ?? null) === $action) { return $r; }
        }
        return null;
    }
STUBS;

$shim = sys_get_temp_dir() . '/suite-v021-shim-' . getmypid() . '.php';
file_put_contents($shim, "<?php\nclass Suite_Reconcile {\n" . $methods . $stubs . "\n}\n");
require $shim;

// ------------------------------------------------------------- harness ----

$passed = 0;
$total  = 0;

/**
 * $tag is [v21] when the case MUST fail against the v0.0.20 control, and
 * [both] when it must hold on either. Verify-Suite021.ps1 audits both
 * claims; nothing here is trusted on assertion alone.
 */
function ck($got, $want, string $tag, string $label): void
{
    global $passed, $total;
    $total++;
    $ok = ($got === $want);
    if ($ok) { $passed++; }
    printf("  %-4s %-6s %s\n", $ok ? 'pass' : 'FAIL', $tag, $label);
    if (!$ok) {
        printf("        want %s\n        got  %s\n",
            var_export($want, true), var_export($got, true));
    }
}

function run_world(int $rows, array $stale, array $linked = [], array $posts = []): Suite_Reconcile
{
    $GLOBALS['SUITE_OPTIONS']       = [];
    $GLOBALS['SUITE_POSTS']         = $posts;
    $GLOBALS['SUITE_LINKED']        = $linked;
    $GLOBALS['SUITE_DELETED']       = [];
    $GLOBALS['SUITE_TRASHED']       = [];
    $GLOBALS['SUITE_FORCE_DELETED'] = [];
    $GLOBALS['SUITE_VETOED']        = [];
    $GLOBALS['slplus']              = new SLPlus();
    $GLOBALS['wpdb']                = new Suite_Wpdb();

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

/** store_page fixtures carrying a slug, so ordering is observable. */
function store_posts(array $ids): array
{
    $out = [];
    foreach ($ids as $id) {
        $out[$id] = ['type' => 'store_page', 'status' => 'publish',
                     'name' => 'store-' . $id];
    }
    return $out;
}

// =====================================================================
// OVERRIDE MODE - floor_pct must REACH the logic, not merely be returned
// =====================================================================

if ($mode === 'override') {
    echo "suite-v021 [override mode: floor_pct=0.9]\n";

    $cfg = method_exists('Suite_Reconcile', 'avalon_orphan_config')
        ? (new Suite_Reconcile())->avalon_orphan_config() : [];
    ck($cfg['floor_pct'] ?? null, 0.9, '[both]',
       'O1  floor_pct honours AVALON_RECONCILE_FLOOR_PCT');

    // 10 rows, 8 updated. Proceeds under the 0.5 default; refused under 0.9.
    $o = run_world(10, [9, 10], [9 => 1009, 10 => 1010], store_posts([1009, 1010]));
    ck((bool) ($o->state['reconcile_aborted'] ?? false), true, '[both]',
       'O2  0.9 floor refuses a fixture the 0.5 default accepts');
    ck($GLOBALS['SUITE_DELETED'], [], '[both]',
       'O3  refusal deletes nothing');

    printf("suite-v021-override: %d/%d\n", $passed, $total);
    exit($passed === $total ? 0 : 1);
}

// =====================================================================
// MAIN
// =====================================================================

echo "suite-v021  artefact: $artefact\n";
echo "  methods extracted: " . (count($found) ? implode(', ', $found) : 'NONE') . "\n\n";

// --- A. one stale row linked to a published store_page ------------------
echo "A  stale row, linked store_page - the ordinary disposal\n";
$o = run_world(3, [2], [2 => 1002], store_posts([1002]));

ck((int) ($o->state['rows_removed'] ?? 0), 1, '[both]',
   'A1  rows_removed 1');
ck($GLOBALS['SUITE_DELETED'], [2], '[both]',
   'A2  the row was passed to currentLocation->delete()');
ck($GLOBALS['SUITE_FORCE_DELETED'], [1002], '[both]',
   'A3  SLP force-destroyed the linked store_page');
ck(isset($GLOBALS['SUITE_POSTS'][1002]), false, '[both]',
   'A4  the post is gone, not trashed');
ck($GLOBALS['SUITE_TRASHED'], [], '[both]',
   'A5  wp_trash_post was never called');

ck($o->log_actions('page_destroyed_by_slp'), 1, '[v21]',
   'A6  the disposal was recorded');
ck((int) ($o->state['pages_destroyed'] ?? 0), 1, '[v21]',
   'A7  pages_destroyed 1');
ck($o->log_actions('orphan_skipped'), 0, '[v21]',
   'A8  no orphan_skipped - v0.0.20 logs one here');
ck($o->log_actions('orphan_trashed'), 0, '[both]',
   'A9  no orphan_trashed on either build');

$rec = $o->log_first('page_destroyed_by_slp');
ck($rec['post_id'] ?? null, 1002, '[v21]',
   'A10 the record names the post');
ck($rec['slug'] ?? null, 'store-1002', '[v21]',
   'A11 THE ORDERING: the slug was read BEFORE the post was destroyed');
ck($rec['store'] ?? null, 'Store 2', '[v21]',
   'A12 the record names the store');

// --- B. stale row linked to a post SLP will refuse to delete ------------
echo "\nB  stale row linked to a 'page' - the pre_delete_post veto\n";
$posts = ['1003' => ['type' => 'page', 'status' => 'draft', 'name' => 'not-a-store']];
$o = run_world(3, [3], [3 => 1003], [1003 => ['type' => 'page', 'status' => 'draft', 'name' => 'not-a-store']]);

ck(isset($GLOBALS['SUITE_POSTS'][1003]), true, '[both]',
   'B1  the veto spared the post');
ck($GLOBALS['SUITE_VETOED'], [1003], '[both]',
   'B2  the veto is what spared it, not an accident of the fixture');
ck((int) ($o->state['rows_removed'] ?? 0), 1, '[both]',
   'B3  the row still went');
ck($o->log_actions('page_retained_not_store_page'), 1, '[v21]',
   'B4  retention was recorded');
ck((int) ($o->state['pages_destroyed'] ?? 0), 0, '[both]',
   'B5  pages_destroyed not bumped for a spared post');
ck($o->log_actions('orphan_skipped'), 0, '[v21]',
   'B6  no orphan_skipped - v0.0.20 logs one here too');
ck($GLOBALS['SUITE_TRASHED'], [], '[both]',
   'B7  the guard held on both builds - nothing was trashed');

// --- C. stale row with no linked post -----------------------------------
echo "\nC  stale row, sl_linked_postid 0\n";
$o = run_world(3, [1], [], []);
ck((int) ($o->state['rows_removed'] ?? 0), 1, '[both]',
   'C1  rows_removed 1');
ck($o->log_actions('page_destroyed_by_slp'), 0, '[both]',
   'C2  nothing logged as destroyed');
ck($o->log_actions('page_retained_not_store_page'), 0, '[both]',
   'C3  nothing logged as retained');

// --- D. the floor rail, which survives this release ----------------------
echo "\nD  Rail 1 - the floor still refuses an implausible feed\n";
$o = run_world(10, [1,2,3,4,5,6,7,8,9,10], [1 => 1001], store_posts([1001]));
ck((bool) ($o->state['reconcile_aborted'] ?? false), true, '[both]',
   'D1  reconcile_aborted on an empty updated set');
ck($GLOBALS['SUITE_DELETED'], [], '[both]',
   'D2  nothing was deleted');
ck($GLOBALS['SUITE_FORCE_DELETED'], [], '[both]',
   'D3  no post was destroyed');
ck($o->log_actions('aborted'), 1, '[both]',
   'D4  the abort was logged');
ck(isset($GLOBALS['SUITE_POSTS'][1001]), true, '[both]',
   'D5  the page survives - the rail protects pages now, not just rows');

// The boundary is >=, not >. Carried forward from suite-v020 D6a, which is
// RETIRED in this release: v0.0.20's suite asserts max_trash and cleanup,
// which no longer exist, and it fatals against this class because its stubs
// predate get_post_field(). Retiring a suite means proving its surviving
// coverage lives somewhere else first, so that case moves here.
// 10 rows, 5 stale -> 5 updated against a floor of ceil(10*0.5)=5.
$o = run_world(10, [6,7,8,9,10], [], []);
ck((bool) ($o->state['reconcile_aborted'] ?? false), false, '[both]',
   'D6  5 hashes against a floor of 5 PROCEEDS - the boundary is >=, not >');
ck((int) ($o->state['rows_removed'] ?? 0), 5, '[both]',
   'D7  and the five stale rows actually went, so D6 is not a trivial pass');
$o = run_world(10, [5,6,7,8,9,10], [], []);
ck((bool) ($o->state['reconcile_aborted'] ?? false), true, '[both]',
   'D8  4 hashes against a floor of 5 is refused - one either side of it');

// --- E. the cap is gone --------------------------------------------------
echo "\nE  the disposal cap no longer exists\n";
// 70 rows, not 40. Rail 1 refuses any pass whose updated set falls below
// ceil(rows * 0.5), and 40 rows with 31 stale leaves 9 updated against a
// floor of 20 - so the FIRST cut of this fixture aborted before pass 2 on
// BOTH builds and measured nothing. 70 rows leaves 39 updated against a
// floor of 35, which proceeds, while 31 stale still exceeds v0.0.20's
// max_trash of 30. A cap test has to clear the rail that runs before it.
$stale  = range(1, 31);
$linked = [];
$ids    = [];
foreach ($stale as $i) { $linked[$i] = 2000 + $i; $ids[] = 2000 + $i; }
$o = run_world(70, $stale, $linked, store_posts($ids));

ck((int) ($o->state['rows_removed'] ?? 0), 31, '[both]',
   'E1  all 31 rows removed on either build');
ck((bool) ($o->state['reconcile_aborted'] ?? false), false, '[v21]',
   'E2  31 disposals do NOT abort - v0.0.20 trips max_trash at 30');
ck($o->log_actions('orphan_cap_exceeded'), 0, '[v21]',
   'E3  no cap log - v0.0.20 emits one');
ck((int) ($o->state['pages_destroyed'] ?? 0), 31, '[v21]',
   'E4  all 31 disposals recorded');
ck(count($GLOBALS['SUITE_FORCE_DELETED']), 31, '[both]',
   'E5  SLP destroyed all 31 pages regardless of build');

// --- F. config surface ---------------------------------------------------
echo "\nF  avalon_orphan_config()\n";
$cfg = method_exists('Suite_Reconcile', 'avalon_orphan_config')
    ? (new Suite_Reconcile())->avalon_orphan_config() : [];
ck(array_key_exists('floor_pct', $cfg), true, '[both]',
   'F1  floor_pct retained');
ck($cfg['floor_pct'] ?? null, 0.5, '[both]',
   'F2  floor_pct defaults to 0.5');
ck(array_key_exists('cleanup', $cfg), false, '[v21]',
   'F3  cleanup removed');
ck(array_key_exists('max_trash', $cfg), false, '[v21]',
   'F4  max_trash removed');
// NOT count($cfg) === 1, which is what the first cut asserted. v0.0.22
// added relink_max - a correct addition - and broke it. A snapshot of how
// many keys exist today is not the invariant; the invariant is that NO key
// configuring post disposal survives, and that holds however many other
// keys are added later.
$disposal = array_values(array_filter(array_keys($cfg), function ($k) {
    return ($k === 'cleanup') || (strpos($k, 'trash') !== false);
}));
ck($disposal, [], '[v21]',
   'F5  no post-disposal key remains, whatever else is added later');

// --- G. structural, against the artefact text ----------------------------
echo "\nG  structural\n";
$code = preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $src);

ck(substr_count($code, 'wp_trash_post'), 0, '[v21]',
   'G1  wp_trash_post absent from code');
ck(substr_count($code, 'orphans_trashed'), 0, '[v21]',
   'G2  orphans_trashed absent');
ck(substr_count($code, 'AVALON_ORPHAN_CLEANUP'), 0, '[v21]',
   'G3  AVALON_ORPHAN_CLEANUP absent');
ck(substr_count($code, 'AVALON_ORPHAN_MAX_TRASH'), 0, '[v21]',
   'G4  AVALON_ORPHAN_MAX_TRASH absent');
ck(substr_count($code, 'trash_ok'), 0, '[v21]',
   'G5  trash_ok absent');
// Two sites, not one: the defined() guard and the cast beside it. The
// first cut asserted 1 without counting, and failed on both builds.
ck(substr_count($code, 'AVALON_RECONCILE_FLOOR_PCT'), 2, '[both]',
   'G6  the floor constant is read at both its sites');
ck(substr_count($code, 'pages_destroyed') > 0, true, '[v21]',
   'G7  pages_destroyed present');
ck(substr_count($code, 'page_destroyed_by_slp'), 1, '[v21]',
   'G8  the disposal action string appears once');

$a = strpos($code, 'page_destroyed_by_slp');
$b = strpos($code, '$slplus->currentLocation->delete(');
ck($a !== false && $b !== false && $a < $b, true, '[v21]',
   'G9  the disposal log precedes the delete in source order');

// --- H. the override subprocess -----------------------------------------
echo "\nH  override subprocess\n";
$php  = PHP_BINARY;
$cmd  = escapeshellarg($php) . ' ' . escapeshellarg(__FILE__) . ' '
      . escapeshellarg($artefact) . ' override 2>&1';
$out  = shell_exec($cmd);
$hit  = (is_string($out) && preg_match('/suite-v021-override:\s*(\d+)\/(\d+)/', $out, $m));

if (!$hit) {
    echo "  FAIL [both] H1  the override subprocess did not report a score\n";
    if (is_string($out)) { echo "        " . str_replace("\n", "\n        ", trim($out)) . "\n"; }
    $total++;
} else {
    ck((int) $m[1], (int) $m[2], '[both]',
       "H1  override subprocess {$m[1]}/{$m[2]}");
}

@unlink($shim);
printf("\nsuite-v021: %d/%d assertions PASS\n", $passed, $total);
exit($passed === $total ? 0 : 1);
