<?php
/**
 * suite-v026.php - validates the v0.0.26 Part 1 storage layer.
 *
 * WHAT IS EXECUTED, AND WHY IT IS EXECUTED RATHER THAN READ
 *
 * avalon_hours_table, avalon_hours_install, avalon_hours_maybe_install,
 * avalon_hours_config and activate are lifted out of the class file verbatim
 * and wrapped in a test class, so self:: resolves exactly as it does in
 * production and no source is rewritten to make it testable.
 *
 * $wpdb is stubbed with a prefix and a collation. ABSPATH is pointed at a real
 * temporary directory holding a real wp-admin/includes/upgrade.php, so the
 * require_once line in the installer is EXECUTED rather than edited out - that
 * file defines a dbDelta() that records the statement it was handed instead of
 * touching a database. The consequence worth having is that every structural
 * assertion below runs against the string dbDelta actually receives, after
 * $wpdb->prefix and get_charset_collate() have been interpolated, not against
 * the source text of the file. A source-text assertion cannot tell the
 * difference between a correct statement and a correct-looking one.
 *
 * ONE PROCESS PER SCENARIO. defined() is permanent within a process, so a
 * single run could test the TTL clamp at exactly one value and the
 * WP_INSTALLING guard not at all. Each scenario is a separate php invocation
 * against a generated harness.
 *
 * THE GATE IS TESTED IN BOTH DIRECTIONS. s0.189 is a defect of omission, and a
 * gate that always installs would satisfy a fresh-install test perfectly well
 * while making a DDL statement run on every request forever. The 'current'
 * scenario is the negative control: same code, same check, opposite answer.
 * The TTL clamp gets the same treatment - 60 clamps to 30, 0 raises to 1, and
 * 14 passes through as 14, because a clamp that has only ever returned 30 has
 * not been shown to be a clamp.
 *
 * Usage: php suite-v026.php <path-to-class.slp_avalon.php> [<path-to-slp_avalon.php>]
 */

$clsPath  = $argv[1] ?? 'build/out26/class.slp_avalon.php';
$bootPath = $argv[2] ?? 'build/out26/slp_avalon.php';

$code = file_get_contents($clsPath);
if ($code === false) {
    fwrite(STDERR, "cannot read {$clsPath}\n");
    exit(2);
}
$boot = file_get_contents($bootPath);
if ($boot === false) {
    fwrite(STDERR, "cannot read {$bootPath}\n");
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

$activate = span($code,
    '        public static function activate(){',
    '        private function includes(){');
$hours = span($code,
    '        public static function avalon_hours_table(){',
    '        public function avalon_rest_protected_slugs(){');

/* A build without the methods is not an abort. It is a build that fails every
   executed assertion, which is what a negative control has to be able to
   show. */
$lifted = ($activate !== false && $hours !== false);
if (! $lifted) {
    echo "\n  NOTE: the v0.0.26 hours methods are absent from this build.\n";
    echo "        Every executed assertion below fails by construction.\n";
}

/* ------------------------------------------------------------------ */
/* Build the harness: stubs + lifted code + a scenario driver.         */
/* ------------------------------------------------------------------ */

$tmp = sys_get_temp_dir() . '/slp26_' . getmypid();
@mkdir($tmp . '/wp-admin/includes', 0777, true);

/* A real upgrade.php on a real ABSPATH, so require_once is exercised. */
file_put_contents($tmp . '/wp-admin/includes/upgrade.php',
    "<?php\n" .
    "function dbDelta(\$sql) {\n" .
    "    \$GLOBALS['dbdelta'][] = \$sql;\n" .
    "    return array();\n" .
    "}\n");

$harness = <<<'PHPHEAD'
<?php
$GLOBALS['dbdelta'] = array();
$GLOBALS['opts']    = array();
$GLOBALS['writes']  = array();

class SLP_Test_wpdb {
    public $prefix = 'wp_';
    public function get_charset_collate() {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
    }
}
$wpdb = new SLP_Test_wpdb();

function get_option($k, $d = false) {
    return array_key_exists($k, $GLOBALS['opts']) ? $GLOBALS['opts'][$k] : $d;
}
function update_option($k, $v, $autoload = null) {
    $GLOBALS['opts'][$k]   = $v;
    $GLOBALS['writes'][]   = array($k, $v, $autoload);
    return true;
}
function print_r_stub($x) { return ''; }

PHPHEAD;

$harness .= "define('ABSPATH', " . var_export($tmp . '/', true) . ");\n\n";

/* Scenario constants must be defined BEFORE the class is declared only in the
   sense that they must precede the call; defined() is evaluated at call time.
   They are emitted here so each process differs by exactly these lines. */
$harness .= "\$scenario = \$argv[1];\n";
$harness .= <<<'PHPCONST'
switch ($scenario) {
    case 'current':    $GLOBALS['opts']['avalon_hours_db_version'] = '1'; break;
    case 'stale':      $GLOBALS['opts']['avalon_hours_db_version'] = '0'; break;
    case 'installing': define('WP_INSTALLING', true);                     break;
    case 'cfg_high':   define('AVALON_HOURS_POSITIVE_TTL_DAYS', 60);
                       define('AVALON_HOURS_NEGATIVE_TTL_DAYS', 90);      break;
    case 'cfg_zero':   define('AVALON_HOURS_POSITIVE_TTL_DAYS', 0);
                       define('AVALON_HOURS_NEGATIVE_TTL_DAYS', 0);       break;
    case 'cfg_mid':    define('AVALON_HOURS_POSITIVE_TTL_DAYS', 14);
                       define('AVALON_HOURS_NEGATIVE_TTL_DAYS', 3);       break;
    case 'cfg_off':    define('AVALON_HOURS_ENABLED', false);             break;
}

PHPCONST;

$harness .= "class SLP_Avalon {\n";
$harness .= "    const HOURS_DB_VERSION = '1';\n";
$harness .= "    const HOURS_DB_OPTION  = 'avalon_hours_db_version';\n";
$harness .= "    private static function log(\$e) {}\n";
$harness .= ($lifted ? $activate . "\n" . $hours : '');
$harness .= "}\n\n";

$harness .= <<<'PHPTAIL'
switch ($scenario) {
    case 'activate':
        SLP_Avalon::activate();
        break;
    case 'cfg_default':
    case 'cfg_high':
    case 'cfg_zero':
    case 'cfg_mid':
    case 'cfg_off':
        $o = new SLP_Avalon();
        echo json_encode(array('cfg' => $o->avalon_hours_config()));
        exit(0);
    case 'table':
        echo json_encode(array('table' => SLP_Avalon::avalon_hours_table()));
        exit(0);
    default:
        SLP_Avalon::avalon_hours_maybe_install();
}
echo json_encode(array(
    'dbdelta' => $GLOBALS['dbdelta'],
    'opts'    => $GLOBALS['opts'],
    'writes'  => $GLOBALS['writes'],
));
PHPTAIL;

$harnessPath = $tmp . '/harness.php';
file_put_contents($harnessPath, $harness);

function run($harnessPath, $scenario)
{
    $out = array();
    $rc  = 0;
    exec('php ' . escapeshellarg($harnessPath) . ' ' . escapeshellarg($scenario)
         . ' 2>&1', $out, $rc);
    $raw = implode("\n", $out);
    $j   = json_decode($raw, true);
    return array($rc, $j, $raw);
}

/* The harness must parse before any scenario means anything. */
$lintOut = array(); $lintRc = 0;
exec('php -l ' . escapeshellarg($harnessPath) . ' 2>&1', $lintOut, $lintRc);
check($lintRc === 0,
      'the lifted methods parse inside a test class'
      . ($lintRc === 0 ? '' : ': ' . implode(' ', $lintOut)));

/* ------------------------------------------------------------------ */
/* The gate, in both directions.                                       */
/* ------------------------------------------------------------------ */

echo "\n  gate\n";

list($rc, $fresh) = run($harnessPath, 'fresh');
check($rc === 0 && is_array($fresh) && count($fresh['dbdelta']) === 1,
      'a site with no schema option installs: dbDelta called once');
check($rc === 0 && is_array($fresh) && ($fresh['opts']['avalon_hours_db_version'] ?? null) === '1',
      'the schema option is written with the current version');

/* THE NEGATIVE CONTROL. Same gate, same code path, opposite answer. Without
   this a gate hardwired to install would pass every other assertion here. */
list($rc, $current) = run($harnessPath, 'current');
check($rc === 0 && is_array($current) && count($current['dbdelta']) === 0,
      'a site already at the current version does NOT run dbDelta');
check($rc === 0 && is_array($current) && count($current['writes']) === 0,
      'and does not rewrite the option either');

list($rc, $stale) = run($harnessPath, 'stale');
check($rc === 0 && is_array($stale) && count($stale['dbdelta']) === 1,
      'a site at an older schema version installs');

list($rc, $inst) = run($harnessPath, 'installing');
check($rc === 0 && is_array($inst) && count($inst['dbdelta']) === 0,
      'WP_INSTALLING suppresses the install');

/* s0.189 is a defect of omission in BOTH directions - the activation path has
   to work for a genuinely new install even though it cannot carry the load. */
list($rc, $act) = run($harnessPath, 'activate');
check($rc === 0 && is_array($act) && count($act['dbdelta']) === 1,
      'activate() installs, for the fresh-install case it does cover');

/* ------------------------------------------------------------------ */
/* The statement dbDelta actually receives.                            */
/* ------------------------------------------------------------------ */

echo "\n  schema\n";

$sql = (is_array($fresh) && ! empty($fresh['dbdelta'][0])) ? $fresh['dbdelta'][0] : '';

check(strpos($sql, 'CREATE TABLE wp_avalon_dealer_places (') === 0,
      'the statement opens on the prefixed table name');
check(strpos($sql, "\r") === false,
      'no CR reached the statement, so dbDelta never depends on trimming one');
check(substr_count($sql, "\n") === 21,
      'one field or key per line, 21 newlines - dbDelta parses by line');
check(strpos($sql, "\n  PRIMARY KEY  (address_key),") !== false,
      'PRIMARY KEY carries the two spaces dbDelta parses on');
check(substr_count($sql, "\n  KEY ") === 4,
      'four secondary keys: sl_id, place_id and the two sweep indexes');
check(stripos($sql, ' INDEX ') === false,
      'no INDEX keyword');
check(strpos($sql, 'CURRENT_TIMESTAMP') === false,
      'no CURRENT_TIMESTAMP default');
check(strpos($sql, '0000-00-00') === false,
      'no zero date');
check(strpos($sql, 'utf8mb4') !== false,
      'the charset collate from $wpdb was interpolated, not dropped');

/* Lowercase types, asserted on the field lines only. The collation clause is
   uppercase by WordPress convention and is not a column type. */
$fieldLines = array();
foreach (explode("\n", $sql) as $line) {
    /* Key lines are identified by their PREFIX, not by containing 'KEY'.
       A contains-test drops address_key, because 'address_key char' contains
       the substring 'key '. The column it silently removed was the primary
       key of the table. s0.194. */
    if (strpos($line, '  ') !== 0)            { continue; }
    if (strpos($line, '  KEY ') === 0)        { continue; }
    if (strpos($line, '  PRIMARY KEY') === 0) { continue; }
    $fieldLines[] = $line;
}
$upper = array();
foreach (array('VARCHAR', 'DATETIME', 'BIGINT', 'CHAR(', 'LONGTEXT', 'SMALLINT',
               'NOT NULL', 'DEFAULT') as $t) {
    foreach ($fieldLines as $l) {
        if (strpos($l, $t) !== false) { $upper[] = $t; break; }
    }
}
check(count($fieldLines) === 15,
      'fifteen column definitions');
check(empty($upper),
      'every column type and attribute is lowercase, so dbDelta cannot issue '
      . 'the same ALTER on every run'
      . (empty($upper) ? '' : ': ' . implode(', ', $upper)));
check(strpos($sql, 'place_id varchar(191)') !== false
      && strpos($sql, 'KEY place_id (place_id)') !== false,
      'place_id is index-safe width and indexed whole, not by prefix');

list($rc, $tbl) = run($harnessPath, 'table');
check($rc === 0 && is_array($tbl) && $tbl['table'] === 'wp_avalon_dealer_places',
      'the table helper returns the per-site prefixed name');

/* ------------------------------------------------------------------ */
/* The config, and the clamp at three values.                          */
/* ------------------------------------------------------------------ */

echo "\n  config\n";

list($rc, $d) = run($harnessPath, 'cfg_default');
$dc = is_array($d) ? $d['cfg'] : array();
check($rc === 0 && ($dc['positive_ttl_days'] ?? null) === 30,
      'positive TTL defaults to 30, the Places cap');
check($rc === 0 && ($dc['negative_ttl_days'] ?? null) === 7,
      'negative TTL defaults to 7, so a dealer who adds hours is not invisible '
      . 'for a month');
check($rc === 0 && ($dc['enabled'] ?? null) === true,
      'the feature defaults on');
check($rc === 0 && ($dc['resolve_ceiling'] ?? null) === 50
      && ($dc['details_ceiling'] ?? null) === 50
      && ($dc['timeout'] ?? null) === 8,
      'the Part 2 contract keys are present with their defaults');

list($rc, $h) = run($harnessPath, 'cfg_high');
$hc = is_array($h) ? $h['cfg'] : array();
check($rc === 0 && ($hc['positive_ttl_days'] ?? null) === 30
      && ($hc['negative_ttl_days'] ?? null) === 30,
      'a constant above the cap is clamped down to 30, both TTLs');

list($rc, $z) = run($harnessPath, 'cfg_zero');
$zc = is_array($z) ? $z['cfg'] : array();
check($rc === 0 && ($zc['positive_ttl_days'] ?? null) === 1
      && ($zc['negative_ttl_days'] ?? null) === 1,
      'a zero constant is raised to 1 rather than caching nothing forever');

/* A clamp that has only ever returned 30 has not been shown to be a clamp. */
list($rc, $m) = run($harnessPath, 'cfg_mid');
$mc = is_array($m) ? $m['cfg'] : array();
check($rc === 0 && ($mc['positive_ttl_days'] ?? null) === 14
      && ($mc['negative_ttl_days'] ?? null) === 3,
      'a legitimate value inside the cap passes through unchanged');

list($rc, $o) = run($harnessPath, 'cfg_off');
$oc = is_array($o) ? $o['cfg'] : array();
check($rc === 0 && ($oc['enabled'] ?? null) === false,
      'the feature can be switched off by constant');

/* ------------------------------------------------------------------ */
/* Wiring and artefact-level facts the executed tests cannot see.      */
/* ------------------------------------------------------------------ */

echo "\n  wiring\n";

check(substr_count($code,
      "add_action('init', array(self::\$instance,'avalon_hours_maybe_install'), 1);") === 1,
      'the gate is registered on init at priority 1, exactly once');
check(substr_count($code, "'avalon_hours_maybe_install'") === 1,
      'and is registered on no other hook');
/* The CALL, not the word. activate()'s own docblock names
   register_activation_hook while explaining why activation cannot carry the
   install on its own, so a bare-word test asserts the code is undocumented. */
check(strpos($code, 'register_activation_hook(') === false,
      'the class file never calls register_activation_hook - slp_avalon.php does');
check(substr_count($boot,
      "array( 'SLP_Avalon', 'activate' )") === 1,
      'slp_avalon.php still points the activation hook at activate()');
check(substr_count($boot, 'Version: 0.0.26') === 1
      && substr_count($boot, 'Version: 0.0.25') === 0,
      'the plugin header declares 0.0.26 and no longer declares 0.0.25');

/* php -l on the artefact itself, under the server's short_open_tag setting. */
foreach (array($clsPath, $bootPath) as $p) {
    $o = array(); $r = 0;
    exec('php -d short_open_tag=1 -l ' . escapeshellarg($p) . ' 2>&1', $o, $r);
    check($r === 0, 'php -l parses ' . basename($p) . ' with short_open_tag=On'
          . ($r === 0 ? '' : ': ' . implode(' ', $o)));
}

/* ------------------------------------------------------------------ */

@unlink($harnessPath);
@unlink($tmp . '/wp-admin/includes/upgrade.php');
@rmdir($tmp . '/wp-admin/includes');
@rmdir($tmp . '/wp-admin');
@rmdir($tmp);

printf("\n  %d passed, %d failed, %d total\n\n", $pass, $fail, $pass + $fail);
exit($fail === 0 ? 0 : 1);
