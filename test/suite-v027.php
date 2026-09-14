<?php
/**
 * suite-v027.php - validates the v0.0.26 Part 3a place-resolution queue.
 *
 * WHAT IS EXECUTED, AND WHY IT IS EXECUTED RATHER THAN READ
 *
 * avalon_places_maybe_schedule, avalon_places_seed, avalon_places_purge,
 * avalon_places_cron and avalon_places_cli are lifted out of the class file
 * verbatim, together with avalon_hours_table, avalon_hours_config,
 * avalon_hours_install and the const block, and wrapped in a test class so
 * self:: resolves exactly as it does in production and no source is rewritten
 * to make it testable.
 *
 * THE ADDRESS-KEY CLASS IS THE REAL ONE. class.slp_avalon_addresskey.php is
 * required, not stubbed. A stub would answer whatever the caller believed,
 * which is the defect suite-v015 demonstrated when it scored 33/33 against a
 * broken build. Because the real class is present, the Leland assertion below
 * compares the key the plugin will actually compute against the value in
 * build/placeid/keyvectors.csv - an oracle produced by a different language.
 * That is a differential across the port, not a restatement of it.
 *
 * $wpdb is stubbed, and its prepare() performs a real %s/%d substitution, so
 * every SQL assertion below runs against the finished statement after
 * interpolation rather than against the source text that produced it. A
 * source-text assertion cannot tell a correct statement from a
 * correct-looking one.
 *
 * ONE PROCESS PER SCENARIO. defined() is permanent within a process, so a
 * single run could test the TTL at exactly one value and the WP_INSTALLING
 * guard not at all. Each scenario is a separate php invocation against a
 * generated harness.
 *
 * EVERY GATE IS TESTED IN BOTH DIRECTIONS. A schedule gate that always
 * schedules passes a fresh-install test perfectly well while re-registering
 * the event on every request forever. purge_disabled is the control that
 * matters most: it asserts the TTL sweep still runs with the feature switched
 * off, because the expiry is a licence obligation and not a feature.
 *
 * Usage:
 *   php suite-v027.php <class.slp_avalon.php> <class.slp_avalon_addresskey.php>
 */

$clsPath = $argv[1] ?? 'build/out27/class.slp_avalon.php';
$keyPath = $argv[2] ?? 'slp_avalon/inc/class.slp_avalon_addresskey.php';

$code = @file_get_contents($clsPath);
if ($code === false) {
    fwrite(STDERR, "cannot read {$clsPath}\n");
    exit(2);
}
if (! is_readable($keyPath)) {
    fwrite(STDERR, "cannot read {$keyPath}\n");
    exit(2);
}

$pass = 0;
$fail = 0;
function check($ok, $label)
{
    global $pass, $fail;
    if ($ok) { $pass++; printf("    [PASS] %s\n", $label); }
    else     { $fail++; printf("    [FAIL] %s\n", $label); }
}

/* ------------------------------------------------------------------ */
/* Lift the methods out of the artefact, verbatim.                     */
/* ------------------------------------------------------------------ */

function span($code, $from, $to)
{
    $a = strpos($code, $from);
    $b = strpos($code, $to);
    if ($a === false || $b === false || $b <= $a) {
        return false;
    }
    return substr($code, $a, $b - $a);
}

$consts = span($code,
    "        const HOURS_DB_VERSION = '1';",
    '        public static function instance(){');
$hours = span($code,
    '        public static function avalon_hours_table(){',
    '        public function avalon_rest_protected_slugs(){');

/* The places block runs to the end of the class, so there is no following
   declaration to bound it. The class tail is exactly "\r\n    }\r\n}" - the
   brace that closes avalon_places_cli is KEPT, the two that close the class
   and the class_exists guard are not. Matched as an exact byte string rather
   than a trailing-brace regex, which would eat one brace too many. */
$placesStart = strpos($code, '        public function avalon_places_maybe_schedule(){');
$places = false;
if ($placesStart !== false) {
    $places = substr($code, $placesStart);
    $tail   = "\r\n    }\r\n}";
    if (substr($places, -strlen($tail)) === $tail) {
        $places = substr($places, 0, -strlen($tail));
    } else {
        $places = false;
    }
}

/* A build without the methods is not an abort. It is a build that fails every
   executed assertion, which is what a negative control has to be able to
   show. */
$lifted = ($consts !== false && $hours !== false && $places !== false);
if (! $lifted) {
    echo "\n  NOTE: the v0.0.26 Part 3 places methods are absent from this build.\n";
    echo "        Every executed assertion below fails by construction.\n";
}

/* ------------------------------------------------------------------ */
/* Harness.                                                            */
/* ------------------------------------------------------------------ */

$tmp = sys_get_temp_dir() . '/slp27_' . getmypid();
@mkdir($tmp . '/wp-admin/includes', 0777, true);
file_put_contents($tmp . '/wp-admin/includes/upgrade.php',
    "<?php\n" .
    "function dbDelta(\$sql) {\n" .
    "    \$GLOBALS['dbdelta'][] = \$sql;\n" .
    "    return array();\n" .
    "}\n");

$head = <<<'PHPHEAD'
<?php
$GLOBALS['dbdelta']   = array();
$GLOBALS['opts']      = array();
$GLOBALS['sql']       = array();
$GLOBALS['inserts']   = array();
$GLOBALS['updates']   = array();
$GLOBALS['scheduled'] = array();
$GLOBALS['logged']    = array();
$GLOBALS['cli']       = array();
$GLOBALS['rows']      = array();
$GLOBALS['queue']     = array();
$GLOBALS['nextcron']  = false;

define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('ARRAY_A', 'ARRAY_A');

class SLP_Test_wpdb {
    public $prefix = 'wp_';
    public function get_charset_collate() {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
    }
    /* A real substitution, so assertions read the finished statement. */
    public function prepare($sql, ...$a) {
        $out = '';
        $i   = 0;
        $n   = strlen($sql);
        while ($i < $n) {
            $c = $sql[$i];
            if ($c === '%' && $i + 1 < $n) {
                $t = $sql[$i + 1];
                if ($t === 's') { $out .= "'" . array_shift($a) . "'"; $i += 2; continue; }
                if ($t === 'd') { $out .= (string) (int) array_shift($a); $i += 2; continue; }
            }
            $out .= $c;
            $i++;
        }
        return $out;
    }
    public function query($sql) {
        $GLOBALS['sql'][] = $sql;
        return 1;
    }
    public function get_results($sql, $mode = null) {
        $GLOBALS['sql'][] = $sql;
        if (strpos($sql, 'address_key, sl_id') !== false) {
            return $GLOBALS['queue'];
        }
        return array();
    }
    public function insert($table, $data, $fmt = null) {
        $GLOBALS['inserts'][] = array('table' => $table, 'data' => $data, 'fmt' => $fmt);
        return 1;
    }
    public function update($table, $data, $where, $dfmt = null, $wfmt = null) {
        $GLOBALS['updates'][] = array(
            'table' => $table, 'data' => $data, 'where' => $where,
            'dfmt' => $dfmt, 'wfmt' => $wfmt,
        );
        return 1;
    }
}
$wpdb = new SLP_Test_wpdb();

function get_option($k, $d = false) {
    return array_key_exists($k, $GLOBALS['opts']) ? $GLOBALS['opts'][$k] : $d;
}
function update_option($k, $v, $autoload = null) { $GLOBALS['opts'][$k] = $v; return true; }
function current_time($type, $gmt = 0) { return '2026-09-14 00:00:00'; }
function wp_json_encode($v) { return json_encode($v); }
function wp_next_scheduled($hook) { return $GLOBALS['nextcron']; }
function wp_schedule_event($ts, $rec, $hook) {
    $GLOBALS['scheduled'][] = array('ts' => $ts, 'rec' => $rec, 'hook' => $hook);
    return true;
}

class WP_CLI {
    public static function log($m)     { $GLOBALS['cli'][] = array('log', $m); }
    public static function success($m) { $GLOBALS['cli'][] = array('success', $m); }
    public static function add_command($n, $c) { $GLOBALS['cli'][] = array('cmd', $n); }
}

PHPHEAD;

$harness  = $head;
$harness .= "require " . var_export(realpath($keyPath), true) . ";\n";
$harness .= "define('ABSPATH', " . var_export($tmp . '/', true) . ");\n\n";
$harness .= "\$scenario = \$argv[1];\n";
$harness .= <<<'PHPCONST'
switch ($scenario) {
    case 'purge_disabled': define('AVALON_HOURS_ENABLED', false);            break;
    case 'purge_ttl':      define('AVALON_HOURS_POSITIVE_TTL_DAYS', 7);
                           define('AVALON_HOURS_NEGATIVE_TTL_DAYS', 3);      break;
    case 'schedule_exists':   $GLOBALS['nextcron'] = 1789000000;             break;
    case 'schedule_installing': define('WP_INSTALLING', true);               break;
}

PHPCONST;

$harness .= "class SLP_Avalon {\n";
$harness .= "    private \$avalon_import_state = array();\n";
if ($lifted) {
    $harness .= $consts . "\n";
    $harness .= $hours . "\n";
    $harness .= $places . "\n";
}
/* Not under test, and therefore recorded rather than lifted: a test double
   taken from the caller's belief proves nothing, but a collaborator that is
   genuinely out of scope has to be substitutable. */
$harness .= <<<'PHPTAIL'
    private static function log($e) { $GLOBALS['logged'][] = array('log', $e); }
    private function avalon_import_log($r) { $GLOBALS['logged'][] = array('import', $r); }
    public function avalon_flush_import_log($final = true) { $GLOBALS['logged'][] = array('flush', $final); }
    public function slp_get_all_locations() { return $GLOBALS['rows']; }
}

PHPTAIL;

$harness .= <<<'PHPRUN'
$o = new SLP_Avalon();

/* Fixtures. The Leland row is verbatim from build/placeid/keyvectors.csv
   row real-aura-2, whose dealer_key the Python resolver computed as
   b88e2e676854. */
$GLOBALS['rows'] = array(
    array('sl_id' => 7,  'sl_address' => '111 E Boulevard Dr', 'sl_address2' => '',
          'sl_city' => 'Leland', 'sl_state' => 'MI', 'sl_zip' => '49654', 'sl_country' => 'USA'),
    /* Same dealer, three feed rows, sl_ids out of order. MIN must win. */
    array('sl_id' => 90, 'sl_address' => '500 Main St', 'sl_address2' => '',
          'sl_city' => 'Alpena', 'sl_state' => 'MI', 'sl_zip' => '49707', 'sl_country' => 'USA'),
    array('sl_id' => 12, 'sl_address' => '500 Main St', 'sl_address2' => 'Suite 9',
          'sl_city' => 'Alpena', 'sl_state' => 'MI', 'sl_zip' => '49707', 'sl_country' => 'USA'),
    /* 'Harbor Landing' is NOT a unit designator, and that is the whole point.
       norm_street() splits 'Suite 9', 'Ste 200', 'Unit B', 'Bldg C' and '#4'
       off into $unit, which does not enter the basis - so appending any of
       those to the address line leaves the key unchanged and an address2 leak
       passes unnoticed. The first revision of this fixture used 'Bldg C' and
       the address2 negative control PASSED, proving nothing. A non-unit second
       line moves street_key and is the only value that can see the defect.
       Do not "simplify" this back to a suite number. s0.214. */
    array('sl_id' => 45, 'sl_address' => '500 Main St', 'sl_address2' => 'Harbor Landing',
          'sl_city' => 'Alpena', 'sl_state' => 'MI', 'sl_zip' => '49707', 'sl_country' => 'USA'),
    /* Neither street nor city. Hashes fine, worth nothing, must be skipped. */
    array('sl_id' => 99, 'sl_address' => '', 'sl_address2' => '',
          'sl_city' => '', 'sl_state' => 'MI', 'sl_zip' => '49707', 'sl_country' => 'USA'),
);

switch ($scenario) {
    case 'seed_fresh':
        $r = $o->avalon_places_seed();
        break;
    case 'seed_existing_same':
        $GLOBALS['queue'] = array(
            array('address_key' => 'b88e2e676854', 'sl_id' => '7'),
        );
        $r = $o->avalon_places_seed();
        break;
    case 'seed_existing_moved':
        $GLOBALS['queue'] = array(
            array('address_key' => 'b88e2e676854', 'sl_id' => '4242'),
        );
        $r = $o->avalon_places_seed();
        break;
    case 'schedule_none':
    case 'schedule_exists':
    case 'schedule_installing':
        $o->avalon_places_maybe_schedule();
        $r = null;
        break;
    case 'purge_default':
    case 'purge_disabled':
    case 'purge_ttl':
        $r = $o->avalon_places_purge();
        break;
    case 'cron':
        $o->avalon_places_cron();
        $r = null;
        break;
    case 'cli_reset':
        $o->avalon_places_cli(array('reset'), array());
        $r = null;
        break;
    case 'cli_reset_key':
        $o->avalon_places_cli(array('reset'), array('key' => 'b88e2e676854'));
        $r = null;
        break;
    case 'schema_default':
        SLP_Avalon::avalon_hours_install();
        $r = null;
        break;
}

echo json_encode(array(
    'ret'       => $r,
    'sql'       => $GLOBALS['sql'],
    'inserts'   => $GLOBALS['inserts'],
    'updates'   => $GLOBALS['updates'],
    'scheduled' => $GLOBALS['scheduled'],
    'logged'    => $GLOBALS['logged'],
    'cli'       => $GLOBALS['cli'],
    'dbdelta'   => $GLOBALS['dbdelta'],
    'const'     => $lifted_consts = array(
        'hook'    => defined('X') ? '' : (class_exists('SLP_Avalon') && defined('SLP_Avalon::PLACES_CRON_HOOK') ? constant('SLP_Avalon::PLACES_CRON_HOOK') : ''),
        'pending' => (class_exists('SLP_Avalon') && defined('SLP_Avalon::PLACES_STATUS_PENDING')) ? constant('SLP_Avalon::PLACES_STATUS_PENDING') : '',
        'ceiling' => (class_exists('SLP_Avalon') && defined('SLP_Avalon::PLACES_ERROR_CEILING')) ? constant('SLP_Avalon::PLACES_ERROR_CEILING') : '',
    ),
));
PHPRUN;

$hfile = $tmp . '/harness.php';
file_put_contents($hfile, $harness);

function run($hfile, $scenario)
{
    $out = array();
    $rc  = 0;
    exec(escapeshellcmd(PHP_BINARY) . ' -d short_open_tag=1 '
         . escapeshellarg($hfile) . ' ' . escapeshellarg($scenario) . ' 2>&1', $out, $rc);
    $json = json_decode(end($out), true);
    return is_array($json) ? $json : array('__error' => implode("\n", $out));
}

/* ------------------------------------------------------------------ */
/* Assertions.                                                         */
/* ------------------------------------------------------------------ */

echo "\n  SCHEDULE GATE\n";
$s = run($hfile, 'schedule_none');
check(isset($s['scheduled']) && count($s['scheduled']) === 1,
    'no existing event: schedules exactly one');
check(isset($s['scheduled'][0]['hook']) && $s['scheduled'][0]['hook'] === 'avalon_places_resolve',
    'hook name is avalon_places_resolve');
check(isset($s['scheduled'][0]['rec']) && $s['scheduled'][0]['rec'] === 'daily',
    'recurrence is daily');
check(isset($s['scheduled'][0]['ts']) && $s['scheduled'][0]['ts'] > time(),
    'first fire is in the future, not inside this request');

$s = run($hfile, 'schedule_exists');
check(isset($s['scheduled']) && count($s['scheduled']) === 0,
    'existing event: schedules nothing (negative control)');

$s = run($hfile, 'schedule_installing');
check(isset($s['scheduled']) && count($s['scheduled']) === 0,
    'WP_INSTALLING: schedules nothing');

echo "\n  SEED - KEY FIDELITY\n";
$s = run($hfile, 'seed_fresh');
$keys = array();
foreach (($s['inserts'] ?? array()) as $i) { $keys[] = $i['data']['address_key']; }
check(in_array('b88e2e676854', $keys, true),
    'Leland row yields b88e2e676854, the key resolve-placeids.py computed');
check(isset($s['ret']['rows']) && $s['ret']['rows'] === 5, 'reads all 5 feed rows');
check(isset($s['ret']['keys']) && $s['ret']['keys'] === 2,
    '5 rows fold to 2 keys (3 share one, 1 skipped)');
check(isset($s['ret']['skipped']) && $s['ret']['skipped'] === 1,
    'blank street and city is skipped, not queued');
check(count($keys) === count(array_unique($keys)), 'no key inserted twice');

echo "\n  SEED - sl_address2 EXCLUSION\n";
$alpena = null;
foreach (($s['inserts'] ?? array()) as $i) {
    if ($i['data']['address_key'] !== 'b88e2e676854') { $alpena = $i; }
}
check($alpena !== null,
    'the 3 Alpena rows - differing ONLY in sl_address2 - collapse to one key');
check(isset($s['ret']['keys']) && $s['ret']['keys'] === 2,
    'a NON-unit address2 (Harbor Landing) still does not change the key');
check(count($s['inserts'] ?? array()) === 2,
    'exactly 2 rows queued, so no address2 variant leaked a fourth key');

echo "\n  SEED - THE 1:N FOLD\n";
check($alpena !== null && (int) $alpena['data']['sl_id'] === 12,
    'sl_id 90/12/45 folds to 12, the MIN - deterministic, not most-recent');

echo "\n  SEED - WRITE DISCIPLINE\n";
check($alpena !== null && $alpena['data']['place_status'] === 'pending',
    'insert seeds place_status pending');
check($alpena !== null && $alpena['table'] === 'wp_avalon_dealer_places',
    'writes to wp_ prefixed dealer-places table');

$s2 = run($hfile, 'seed_existing_same');
check(isset($s2['updates']) && count($s2['updates']) === 0,
    'existing key, unchanged sl_id: no UPDATE, updated_at not churned');
check(isset($s2['ret']['inserted']) && $s2['ret']['inserted'] === 1,
    'existing key is not re-inserted; the other key still is');

$s3 = run($hfile, 'seed_existing_moved');
check(isset($s3['updates']) && count($s3['updates']) === 1,
    'existing key, moved sl_id: exactly one UPDATE');
$u = $s3['updates'][0] ?? array('data' => array(), 'where' => array());
check(array_keys($u['data']) === array('sl_id', 'updated_at'),
    'UPDATE sets ONLY sl_id and updated_at');
check(! array_key_exists('place_id', $u['data']) && ! array_key_exists('place_status', $u['data']),
    'UPDATE can never reset place_id or place_status - never-re-resolve is in the writer');
check(array_keys($u['where']) === array('address_key'),
    'UPDATE is keyed on address_key, the primary key');

echo "\n  SEED - s0.209 UNMAPPED\n";
$imported = null;
foreach (($s['logged'] ?? array()) as $l) { if ($l[0] === 'import') { $imported = $l[1]; } }
check($imported !== null && array_key_exists('unmapped', $imported),
    'seed logs an unmapped figure');
check($imported !== null && $imported['unmapped'] === 0,
    'clean ASCII fixture logs unmapped 0, not 1 - the count, not the array');
check($imported !== null && $imported['stage'] === 'places_seed',
    'log record is staged places_seed');

echo "\n  PURGE\n";
$p = run($hfile, 'purge_default');
check(isset($p['sql']) && count($p['sql']) === 2, 'two sweeps, one per TTL');
$all = implode("\n", $p['sql'] ?? array());
check(strpos($all, 'hours_json = NULL') !== false
      && strpos($all, 'attribution_json = NULL') !== false
      && strpos($all, 'fetched_at = NULL') !== false,
    'clears hours_json, attribution_json and fetched_at');
check(strpos($all, 'place_id') === false,
    'never clears place_id - the terms permit holding it indefinitely');
check(strpos($all, 'place_status') === false && strpos($all, 'place_checked_at') === false,
    'never touches place_status or place_checked_at');
check(substr_count($all, 'hours_status =') >= 2 && strpos($all, 'fetched_at <') !== false,
    'driven off hours_status and fetched_at - the hours_sweep index');

$pd = run($hfile, 'purge_disabled');
check(isset($pd['sql']) && count($pd['sql']) === 2,
    'AVALON_HOURS_ENABLED false: purge STILL runs (licence obligation, not a feature)');

$pt = run($hfile, 'purge_ttl');
$cut = array();
foreach (($pt['sql'] ?? array()) as $q) {
    if (preg_match("/fetched_at < '([^']+)'/", $q, $m)) { $cut[] = strtotime($m[1]); }
}
check(count($cut) === 2 && $cut[0] !== $cut[1],
    'the two sweeps use two different cutoffs, not one shared value');
/* Ordering is asserted separately from magnitude, deliberately. A single
   abs() difference is satisfied just as well by the two TTLs being swapped,
   which is a real defect: it would hold negative results for 30 days and expire
   positive ones in 7. The longer TTL must produce the OLDER cutoff. */
check(count($cut) === 2 && $cut[0] < $cut[1],
    'the 7d positive sweep cuts further back than the 3d negative one');
check(count($cut) === 2 && abs(($cut[1] - $cut[0]) - (4 * 86400)) < 120,
    'the two cutoffs are exactly 4 days apart, as configured');

echo "\n  CRON CALLBACK\n";
$c = run($hfile, 'cron');
check(isset($c['sql']) && count($c['sql']) === 2, 'cron runs the purge');
check(isset($c['inserts']) && count($c['inserts']) === 0
      && isset($c['updates']) && count($c['updates']) === 0,
    'Part 3a cron writes no rows and spends nothing');

echo "\n  CLI RESET\n";
$cr = run($hfile, 'cli_reset');
$q  = $cr['sql'][0] ?? '';
check(count($cr['sql'] ?? array()) === 1, 'reset issues exactly one statement');
check(strpos($q, "place_status = 'pending'") !== false
      && strpos($q, 'error_count = 0') !== false,
    'reset returns the key to pending and clears the strike count');
check(strpos($q, "WHERE place_status = 'failed'") !== false,
    'reset touches only failed rows, never ok ones');
check(strpos($q, 'hours_json') === false && strpos($q, 'place_id') === false,
    'reset never touches cached hours or a resolved place_id');
$crk = run($hfile, 'cli_reset_key');
check(strpos($crk['sql'][0] ?? '', "address_key = 'b88e2e676854'") !== false,
    '--key scopes the reset to one address key');

echo "\n  CROSS-PART CONSISTENCY\n";
$sc  = run($hfile, 'schema_default');
$ddl = implode("\n", $sc['dbdelta'] ?? array());
preg_match("/place_status varchar\(16\) not null default '([^']+)'/", $ddl, $m);
$dflt = $m[1] ?? '';
check($dflt !== '' && $dflt === ($sc['const']['pending'] ?? null),
    'PLACES_STATUS_PENDING equals the place_status column default in the DDL');
check(($sc['const']['hook'] ?? '') === 'avalon_places_resolve',
    'PLACES_CRON_HOOK is declared and reachable as a constant');
check((int) ($sc['const']['ceiling'] ?? 0) === 3,
    'PLACES_ERROR_CEILING is 3');

printf("\n  %d passed, %d failed, %d total\n\n", $pass, $fail, $pass + $fail);
exit($fail === 0 ? 0 : 1);
