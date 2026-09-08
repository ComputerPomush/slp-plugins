<?php
/**
 * SLP Dealer Guard - v0.0.22 suite. Relink pass and orphan redirect map.
 *
 * WHY THIS SUITE CARRIES MORE WEIGHT THAN suite-v021.
 *
 * v0.0.21 only removed code. This release WRITES to wp_store_locator and
 * INTERCEPTS public requests, and neither behaviour can be exercised on
 * Aura DEV today: all 308 rows are already linked, so the relink pass has
 * nothing to repair, and its first real trigger is whenever a new dealer
 * arrives during an interrupted write - which may be a long wait. Until
 * then this file is the only place either feature is verified at all.
 *
 * WHAT IS BEING TESTED, AND WHAT IS NOT.
 *
 * The $wpdb double answers by query SHAPE, not by running SQL. So these
 * cases prove the LOGIC - which branch fires, what gets written, what is
 * refused - and prove nothing about whether the SQL itself is correct.
 * The queries are asserted structurally instead, at the foot of this file.
 *
 * wp_safe_redirect() is stubbed to THROW rather than return, because the
 * production code calls exit() immediately afterwards and a test process
 * cannot survive that. The throw is caught and inspected; if the exception
 * never arrives, no redirect was issued, which is exactly what several of
 * the guard cases assert.
 *
 * THE TWO GUARDS ARE THE POINT.
 *
 *   Guard 1, self-disabling. Deleting an orphan frees its base slug. If SLP
 *   later creates a real page at /store/victory-marine/, an unguarded map
 *   would hijack it and send visitors to a -2 that may no longer exist.
 *   D2 and D5 assert a live owned page always wins - including on the 410
 *   path, which is the case easiest to forget.
 *
 *   Guard 2, no dead ends. D3 asserts that a target which has itself been
 *   removed produces NO redirect. An honest 404 beats a 301 into another
 *   404, and a redirect chain is worse than either.
 *
 * NEGATIVE CONTROL. The control target is a TAG:
 *
 *     git show v0.0.21:slp_avalon/inc/class.slp_avalon.php > ctl21.php
 *     php test/suite-v022.php ctl21.php
 *     php test/suite-v022.php build/out22/class.slp_avalon.php
 *
 * v0.0.21 has none of these five methods, so every [v22] case must fail
 * against it. This release is pure addition, so the discriminator count is
 * high by construction - that is not slack, it is the shape of the change.
 * The [both] cases exist to prove the suite is not merely asking "does the
 * method exist": they assert that v0.0.21's own behaviour SURVIVED, which
 * a feature release can quietly undo.
 *
 * TAGS ARE CLAIMS AND MUST BE AUDITED. Run test/Verify-Suite022.ps1, which
 * diffs the two runs case by case. suite-v020 carried fourteen false [v20]
 * tags found by hand three revisions late.
 *
 * Usage:  php test/suite-v022.php [artefact]
 */

$artefact = $argv[1] ?? __DIR__ . '/../build/out22/class.slp_avalon.php';

if (!is_readable($artefact)) {
    fwrite(STDERR, "cannot read $artefact\n");
    exit(2);
}
$src = file_get_contents($artefact);

// ---------------------------------------------------------------- extract ---

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
                if ($tokens[$b][0] !== T_WHITESPACE) { $start = $b; }
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
    'avalon_orphan_config',
    'avalon_relink_orphaned_pages',
    'avalon_orphan_redirect_map',
    'avalon_orphan_gone_list',
    'avalon_slug_is_owned',
    'avalon_orphan_redirect',
];
[$methods, $found] = extract_methods($src, $WANTED);

// ------------------------------------------------------ WordPress stubs ---

$GLOBALS['SUITE_ROWS']    = [];   // sl_id => linked_postid
$GLOBALS['SUITE_META']    = [];   // post_id => sl_id from slp_location_id
$GLOBALS['SUITE_OWNED']   = [];   // slugs that are live AND owned by a row
$GLOBALS['SUITE_NAMES']   = [];   // post_id => slug
$GLOBALS['SUITE_UPDATES'] = [];   // [sl_id => post_id] writes attempted
$GLOBALS['SUITE_STATUS']  = null; // status_header()
$GLOBALS['SUITE_404']     = false;

/** Thrown in place of wp_safe_redirect(), whose exit() a test cannot survive. */
class Suite_Redirect extends Exception
{
    public $url;
    public $status;
    public function __construct($url, $status)
    {
        $this->url    = $url;
        $this->status = $status;
        parent::__construct('redirect');
    }
}

function wp_safe_redirect($url, $status = 302) { throw new Suite_Redirect($url, $status); }
function home_url($path = '')                  { return 'https://example.test' . $path; }
function is_admin()                            { return $GLOBALS['SUITE_IS_ADMIN'] ?? false; }
function status_header($code)                  { $GLOBALS['SUITE_STATUS'] = (int) $code; }
function nocache_headers()                     { }
function get_post_field($field, $id, $ctx = 'display')
{
    return $GLOBALS['SUITE_NAMES'][(int) $id] ?? '';
}

class WP_Query
{
    public $is_404 = false;
    public function set_404() { $this->is_404 = true; $GLOBALS['SUITE_404'] = true; }
}

/**
 * Routes by query SHAPE. Four distinct queries reach this double, and each
 * is identified by a fragment that only it contains.
 */
class Suite_Wpdb
{
    public $prefix   = 'wp_';
    public $posts    = 'wp_posts';
    public $postmeta = 'wp_postmeta';

    public function prepare($query, ...$args)
    {
        foreach ($args as $a) {
            $query = preg_replace('/%[sd]/', is_int($a) ? (string) $a : "'" . $a . "'", $query, 1);
        }
        return $query;
    }

    public function get_col($query)
    {
        // 1. rows that lost their page link
        if (strpos($query, 'sl_linked_postid IS NULL') !== false) {
            $out = [];
            foreach ($GLOBALS['SUITE_ROWS'] as $sl_id => $pid) {
                if ((int) $pid === 0) { $out[] = (string) $sl_id; }
            }
            return $out;
        }
        // 2. store_pages carrying this sl_id in slp_location_id
        if (strpos($query, 'slp_location_id') !== false) {
            preg_match("/meta_value = '?(\d+)'?/", $query, $m);
            $want = isset($m[1]) ? (int) $m[1] : -1;
            $out  = [];
            foreach ($GLOBALS['SUITE_META'] as $post_id => $sl_id) {
                if ((int) $sl_id === $want) { $out[] = (string) $post_id; }
            }
            return $out;
        }
        return [];
    }

    public function get_var($query)
    {
        // 3. which row already owns this post?
        if (strpos($query, 'WHERE sl_linked_postid =') !== false) {
            preg_match('/sl_linked_postid = (\d+)/', $query, $m);
            $pid = isset($m[1]) ? (int) $m[1] : -1;
            foreach ($GLOBALS['SUITE_ROWS'] as $sl_id => $linked) {
                if ((int) $linked === $pid && $pid > 0) { return (string) $sl_id; }
            }
            return null;
        }
        // 4. is this slug a live OWNED store page?
        if (strpos($query, 'p.post_name') !== false) {
            preg_match("/p.post_name   = '([a-z0-9\-]+)'/", $query, $m);
            $slug = $m[1] ?? '';
            return in_array($slug, $GLOBALS['SUITE_OWNED'], true) ? '999' : null;
        }
        return null;
    }

    public function update($table, $data, $where, $fmt = null, $wfmt = null)
    {
        $GLOBALS['SUITE_UPDATES'][(int) $where['sl_id']] = (int) $data['sl_linked_postid'];
        $GLOBALS['SUITE_ROWS'][(int) $where['sl_id']]    = (int) $data['sl_linked_postid'];
        return 1;
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

    public function log_actions($action)
    {
        $n = 0;
        foreach ($this->log as $r) {
            if (($r['action'] ?? null) === $action) { $n++; }
        }
        return $n;
    }
    public function log_first($action)
    {
        foreach ($this->log as $r) {
            if (($r['action'] ?? null) === $action) { return $r; }
        }
        return null;
    }
STUBS;

$shim = sys_get_temp_dir() . '/suite-v022-shim-' . getmypid() . '.php';
file_put_contents($shim, "<?php\nclass Suite_V22 {\n" . $methods . $stubs . "\n}\n");
require $shim;

// ------------------------------------------------------------- harness ----

$passed = 0;
$total  = 0;

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

function fresh(array $rows = [], array $meta = [], array $owned = [], array $names = []): Suite_V22
{
    $GLOBALS['SUITE_ROWS']    = $rows;
    $GLOBALS['SUITE_META']    = $meta;
    $GLOBALS['SUITE_OWNED']   = $owned;
    $GLOBALS['SUITE_NAMES']   = $names;
    $GLOBALS['SUITE_UPDATES'] = [];
    $GLOBALS['SUITE_STATUS']  = null;
    $GLOBALS['SUITE_404']     = false;
    $GLOBALS['SUITE_IS_ADMIN'] = false;
    $GLOBALS['wpdb']          = new Suite_Wpdb();
    $GLOBALS['wp_query']      = new WP_Query();
    return new Suite_V22();
}

/** Run the handler for one path and report what it did. */
function visit(Suite_V22 $o, string $path): array
{
    $_SERVER['REQUEST_URI'] = $path;
    $redirect = null;
    if (method_exists($o, 'avalon_orphan_redirect')) {
        try {
            $o->avalon_orphan_redirect();
        } catch (Suite_Redirect $e) {
            $redirect = ['url' => $e->url, 'status' => $e->status];
        }
    }
    return [
        'redirect' => $redirect,
        'status'   => $GLOBALS['SUITE_STATUS'],
        'is_404'   => $GLOBALS['SUITE_404'],
    ];
}

echo "suite-v022  artefact: $artefact\n";
echo "  methods extracted: " . (count($found) ? implode(', ', $found) : 'NONE') . "\n\n";

// =====================================================================
// R  the relink pass
// =====================================================================
echo "R  avalon_relink_orphaned_pages - repair, and refuse to guess\n";

// R1. One unlinked row, exactly one page carrying its sl_id.
$o = fresh([50 => 0, 51 => 1051], [1050 => 50, 1051 => 51], [], [1050 => 'a-dealer']);
if (method_exists($o, 'avalon_relink_orphaned_pages')) { $o->avalon_relink_orphaned_pages(); }
ck($GLOBALS['SUITE_UPDATES'], [50 => 1050], '[v22]',
   'R1  the unambiguous case is repaired');
ck((int) ($o->state['pages_relinked'] ?? 0), 1, '[v22]',
   'R2  pages_relinked 1');
$rec = $o->log_first('page_relinked');
ck($rec['slug'] ?? null, 'a-dealer', '[v22]',
   'R3  the repair records the slug it linked');

// R4. No page carries this sl_id - the five that died before their first
// add_post_meta. Nothing identifies them, so nothing is written.
$o = fresh([60 => 0], [1060 => 99], [], []);
if (method_exists($o, 'avalon_relink_orphaned_pages')) { $o->avalon_relink_orphaned_pages(); }
ck($GLOBALS['SUITE_UPDATES'], [], '[both]',
   'R4  zero candidates writes nothing');
ck($o->log_actions('relink_skipped'), 1, '[v22]',
   'R5  and says so rather than failing silently');

// R6. Two pages claim the same row. Picking one would be a guess.
$o = fresh([70 => 0], [1070 => 70, 1071 => 70], [], []);
if (method_exists($o, 'avalon_relink_orphaned_pages')) { $o->avalon_relink_orphaned_pages(); }
ck($GLOBALS['SUITE_UPDATES'], [], '[both]',
   'R6  two candidates writes nothing - never guess');
$rec = $o->log_first('relink_skipped');
ck($rec['candidates'] ?? null, 2, '[v22]',
   'R7  and records how many claimed it');

// R8. The page is already owned by a different row. Linking would steal it.
$o = fresh([80 => 0, 81 => 1080], [1080 => 80], [], []);
if (method_exists($o, 'avalon_relink_orphaned_pages')) { $o->avalon_relink_orphaned_pages(); }
ck($GLOBALS['SUITE_UPDATES'], [], '[both]',
   'R8  a page another row owns is never stolen');
$rec = $o->log_first('relink_skipped');
ck(strpos((string) ($rec['reason'] ?? ''), 'already owned') !== false, true, '[v22]',
   'R9  and the reason names the conflict');

// R10. Cap. Thirty unlinked rows is not this defect, it is a different one.
$rows = [];
$meta = [];
for ($i = 1; $i <= 30; $i++) { $rows[$i] = 0; $meta[2000 + $i] = $i; }
$o = fresh($rows, $meta, [], []);
if (method_exists($o, 'avalon_relink_orphaned_pages')) { $o->avalon_relink_orphaned_pages(); }
// [both], not [v22]. This asserts an ABSENCE, and absence is free against
// a build with no such method - rev14 s8, and the shape of suite-v020's
// fourteen false tags. R11 and R12 carry the discrimination: they assert
// the code RAN and chose not to act, which the control cannot fake.
ck($GLOBALS['SUITE_UPDATES'], [], '[both]',
   'R10 30 unlinked rows exceeds the cap and writes NOTHING');
ck((bool) ($o->state['relink_aborted'] ?? false), true, '[v22]',
   'R11 the abort is recorded, not silent');
ck($o->log_actions('relink_cap_exceeded'), 1, '[v22]',
   'R12 and logged once');

// R13. Nothing to do is not an error.
$o = fresh([90 => 1090], [1090 => 90], [], []);
if (method_exists($o, 'avalon_relink_orphaned_pages')) { $o->avalon_relink_orphaned_pages(); }
ck($GLOBALS['SUITE_UPDATES'], [], '[both]',
   'R13 a fully linked table writes nothing');
ck(count($o->log), 0, '[both]',
   'R14 and logs nothing - this runs nightly');

// =====================================================================
// D  the redirect map
// =====================================================================
echo "\nD  avalon_orphan_redirect - both guards\n";

$owned = ['victory-marine-2', 'ashley-marine-llc', 'i-94-marine-watersports-llc-3'];

// D1. The ordinary case: orphan slug, live target.
$o = fresh([], [], $owned, []);
$r = visit($o, '/store/victory-marine/');
ck($r['redirect']['url'] ?? null, 'https://example.test/store/victory-marine-2/', '[v22]',
   'D1  a mapped orphan redirects to its target');
ck($r['redirect']['status'] ?? null, 301, '[v22]',
   'D2  as a 301, not a 302');

// D3. GUARD 1. The slug now belongs to a live owned page.
$o = fresh([], [], array_merge($owned, ['victory-marine']), []);
$r = visit($o, '/store/victory-marine/');
ck($r['redirect'], null, '[both]',
   'D3  GUARD 1: a live owned page at the slug is never hijacked');

// D4. GUARD 2. The target itself is gone.
$o = fresh([], [], ['ashley-marine-llc'], []);
$r = visit($o, '/store/victory-marine/');
ck($r['redirect'], null, '[both]',
   'D4  GUARD 2: no redirect when the target does not resolve');
ck($r['status'], null, '[both]',
   'D5  and no status is forced - the 404 is allowed to happen');

// D6. The 410 path.
$o = fresh([], [], $owned, []);
$r = visit($o, '/store/firefish-industries-ltd/');
ck($r['status'], 410, '[v22]',
   'D6  a departed dealer with no survivor returns 410');
ck($r['is_404'], true, '[v22]',
   'D7  and the 404 template is selected to render it');
ck($r['redirect'], null, '[both]',
   'D8  the gone path never redirects');

// D9. GUARD 1 ON THE 410 PATH - the case easiest to forget.
$o = fresh([], [], array_merge($owned, ['firefish-industries-ltd']), []);
$r = visit($o, '/store/firefish-industries-ltd/');
ck($r['status'], null, '[both]',
   'D9  GUARD 1 applies to the gone list too: a revived dealer is not 410ed');

// D10. Everything else is untouched.
$o = fresh([], [], $owned, []);
$r = visit($o, '/store/some-other-dealer/');
ck([$r['redirect'], $r['status']], [null, null], '[both]',
   'D10 an unmapped store slug is left alone');

$o = fresh([], [], $owned, []);
$r = visit($o, '/find-a-dealer/');
ck([$r['redirect'], $r['status']], [null, null], '[both]',
   'D11 a non-store path is left alone');

$o = fresh([], [], $owned, []);
$GLOBALS['SUITE_IS_ADMIN'] = true;
$r = visit($o, '/store/victory-marine/');
ck($r['redirect'], null, '[both]',
   'D12 admin requests are left alone');

// D13. Both I-94 orphans land on the same live page.
$o = fresh([], [], $owned, []);
$a = visit($o, '/store/i-94-marine-watersports-llc/');
$o = fresh([], [], $owned, []);
$b = visit($o, '/store/i-94-marine-watersports-llc-2/');
ck([$a['redirect']['url'] ?? null, $b['redirect']['url'] ?? null],
   ['https://example.test/store/i-94-marine-watersports-llc-3/',
    'https://example.test/store/i-94-marine-watersports-llc-3/'], '[v22]',
   'D13 both I-94 orphans resolve to the one live page');

// D14. Opelika. A product decision, asserted so it cannot drift silently.
$o = fresh([], [], $owned, []);
$r = visit($o, '/store/ashley-marine-llc-3/');
ck($r['redirect']['url'] ?? null, 'https://example.test/store/ashley-marine-llc/', '[v22]',
   'D14 the closed Opelika branch goes to the Columbus GA store');

// =====================================================================
// M  the table itself
// =====================================================================
echo "\nM  the disposition table\n";

$o   = fresh();
$map  = method_exists($o, 'avalon_orphan_redirect_map') ? $o->avalon_orphan_redirect_map() : [];
$gone = method_exists($o, 'avalon_orphan_gone_list') ? $o->avalon_orphan_gone_list() : [];

ck(count($map), 11, '[v22]',  'M1  eleven redirects');
ck(count($gone), 1, '[v22]',  'M2  one 410');
ck(array_intersect(array_keys($map), $gone), [], '[both]',
   'M3  no slug is both redirected and gone');
ck(array_intersect(array_values($map), array_keys($map)), [], '[both]',
   'M4  NO CHAINS: no target is itself a redirect source');
$self = [];
foreach ($map as $from => $to) { if ($from === $to) { $self[] = $from; } }
ck($self, [], '[both]', 'M5  no slug redirects to itself');

// =====================================================================
// S  structural, and v0.0.21 retention
// =====================================================================
echo "\nS  structural\n";
$code = preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $src);

ck(substr_count($code, "'avalon_relink_orphaned_pages'), 5)"), 1, '[v22]',
   'S1  relink runs at priority 5, BEFORE the reconcile at 10');
ck(substr_count($code, "'avalon_orphan_redirect'), 1)"), 1, '[v22]',
   'S2  the redirect handler runs at priority 1');
// Two sites, not one: the defined() guard and the cast beside it. Written
// as 1 in the first cut, which is the same error suite-v021 G6 made.
ck(substr_count($code, 'AVALON_RELINK_MAX'), 2, '[v22]',
   'S3  the relink cap is overridable, at both its sites');
ck(substr_count($code, 'wp_safe_redirect('), 1, '[v22]',
   'S4  redirects go through wp_safe_redirect, not header()');

// The SQL is not executed by this suite, so it is asserted by shape.
ck(substr_count($code, 'sl_linked_postid IS NULL OR sl_linked_postid = 0'), 1, '[v22]',
   'S5  the unlinked query treats NULL and 0 alike');
ck(substr_count($code, "meta_key   = 'slp_location_id'"), 1, '[v22]',
   'S6  candidates are found by slp_location_id');
ck(substr_count($code, "AND p.post_status = 'publish'") >= 2, true, '[v22]',
   'S7  both lookups are scoped to published posts');

// A feature release must not quietly undo the previous one.
ck(substr_count($code, 'wp_trash_post'), 0, '[both]',
   'S8  v0.0.21 removals stay removed');
ck(substr_count($code, 'AVALON_RECONCILE_FLOOR_PCT'), 2, '[both]',
   'S9  the floor rail survives');
ck(substr_count($code, 'page_destroyed_by_slp'), 1, '[both]',
   'S10 the disposal log survives');

$cfg = method_exists($o, 'avalon_orphan_config') ? $o->avalon_orphan_config() : [];
ck($cfg['floor_pct'] ?? null, 0.5, '[both]',
   'S11 floor_pct still defaults to 0.5');
ck($cfg['relink_max'] ?? null, 25, '[v22]',
   'S12 relink_max defaults to 25');

@unlink($shim);
printf("\nsuite-v022: %d/%d assertions PASS\n", $passed, $total);
exit($passed === $total ? 0 : 1);
