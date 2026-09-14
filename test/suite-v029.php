<?php
/**
 * suite-v029.php - validates the v0.0.26 Part 3d cron resolver.
 *
 * WHAT IS EXECUTED, AND WHY IT IS EXECUTED RATHER THAN READ
 *
 * The Part 3d block, the cron callback, the purge, the CLI dispatcher and
 * the state/log helpers are lifted out of the class file verbatim and
 * wrapped in a test class, so self:: and $this-> resolve exactly as they do
 * in production and no source is rewritten to make it testable.
 *
 * THE ADDRESS-KEY CLASS IS THE REAL ONE. avalon_places_query() calls
 * SLP_Avalon_AddressKey::vector(), ::norm_text() and ::squash(). A
 * hand-written double of those would assert that the query builder agrees
 * with my belief about the normaliser, which is the defect it is supposed
 * to detect. The file is required from beside the artefact and its absence
 * is exit 2, never a skipped assertion.
 *
 * THE GOOGLE PAYLOADS ARE MEASURED, NOT INVENTED. The NOT_ESTABLISHMENT
 * fixture is the response aurapontoonstg received on 2026-09-14 for the
 * resolver's own Rockingham query: one result, name equal to the address,
 * types street_address,subpremise. s0.223 is asserted against the thing
 * that produced s0.223.
 *
 * THE EXPECTED URL IS PINNED. build/resolve-placeids.py built
 * '...query=Rockingham+Marina+Seattle%2C+...&key=...&location=...&radius=
 * 50000&region=us' for this dealer, and http_build_query() was confirmed to
 * reproduce urllib.parse.urlencode() byte for byte on that parameter set. A
 * query built differently resolves NEW dealers by different rules than the
 * 302 already paid for, and nothing would report it. The assertion is on
 * the finished URL, not on the pieces.
 *
 * $wpdb->query IS A SMALL REAL EXECUTOR, NOT A RECORDER. It parses the
 * FINISHED statement into assignments and conditions, applies the
 * conditions to an in-memory table and returns rows affected. A recorder
 * that returns 1 agrees with every build, including one whose WHERE clause
 * has been deleted.
 *
 * s0.231 - THE NEVER-RE-RESOLVE ASSERTION NEEDS A DIVERGENT FIXTURE. The
 * guard under test is AND place_id IS NULL in the success UPDATE. It cannot
 * be seen with a row that is genuinely NULL, because a build with the
 * clause stripped writes the same row either way. The only state that can
 * see it is a queue snapshot that disagrees with the table: the SELECT
 * reports the row as pending with no id, the table already holds one. That
 * is the race the clause exists for. Snapshot and table are two separate
 * fixtures on purpose. Do not "simplify" them into one array.
 *
 * ONE PROCESS PER SCENARIO. WP_CLI::error() halts in production; the double
 * models the halt as a throw so a scenario can still report what had been
 * written before it fired, and each scenario is a separate php invocation
 * regardless, so no scenario inherits another's table.
 *
 * Usage:
 *   php suite-v029.php <class.slp_avalon.php> [<class.slp_avalon_addresskey.php>]
 */

$clsPath = $argv[1] ?? 'build/out29/class.slp_avalon.php';
$akPath  = $argv[2] ?? (dirname($clsPath) . '/class.slp_avalon_addresskey.php');

$code = @file_get_contents($clsPath);
if ($code === false) {
    fwrite(STDERR, "cannot read {$clsPath}\n");
    exit(2);
}
if (! is_readable($akPath)) {
    fwrite(STDERR, "cannot read the address-key class at {$akPath}\n");
    fwrite(STDERR, "pass it as argv[2] if it does not sit beside the artefact\n");
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

$sane = span($code,
    '        private function avalon_coord_is_sane($lat, $lng){',
    "        /**\r\n         * Import hygiene configuration.");

$state = span($code,
    '        private function avalon_state($key){',
    '        private function avalon_state_bump($key){');

$ilog = span($code,
    '        private function avalon_import_log($record){',
    "        /**\r\n         * Flush the override log and the geocode cache.");

$tableM = span($code,
    '        public static function avalon_hours_table(){',
    '        public static function avalon_hours_install(){');

$config = span($code,
    '        public function avalon_hours_config(){',
    '        public function avalon_rest_protected_slugs(){');

$purge = span($code,
    '        public function avalon_places_purge(){',
    "        /**\r\n         * v0.0.26 Part 3d. Build the Text Search query for one dealer.");

/* The Part 3d block: query, scrub, is_systemic, text_search, log_flush,
   resolve, note - bounded by the Part 3 cron docblock that follows it. */
$p3d = span($code,
    '        private function avalon_places_query( $row ){',
    "        /**\r\n         * v0.0.26 Part 3. The cron callback.");

$cron = span($code,
    '        public function avalon_places_cron(){',
    "        /**\r\n         * v0.0.26 Part 3c. Load resolved place IDs into the queue.");

/* The CLI runs to the end of the class, so there is no following
   declaration to bound it. The class tail is exactly "\r\n    }\r\n}" -
   the brace closing avalon_places_cli is KEPT, the two closing the class
   and the class_exists guard are not. Matched as an exact byte string
   rather than a trailing-brace regex, which would eat one too many. */
$cliStart = strpos($code, '        public static function avalon_places_subcommands(){');
$cli = false;
if ($cliStart !== false) {
    $cli  = substr($code, $cliStart);
    $tail = "\r\n    }\r\n}";
    if (substr($cli, -strlen($tail)) === $tail) {
        $cli = substr($cli, 0, -strlen($tail));
    } else {
        $cli = false;
    }
}

foreach (array('consts' => $consts, 'sane' => $sane, 'state' => $state,
               'ilog' => $ilog, 'table' => $tableM, 'config' => $config,
               'purge' => $purge, 'p3d' => $p3d, 'cron' => $cron,
               'cli' => $cli) as $n => $v) {
    /* An unterminated /** in a span comments out everything that follows
       it, including the methods the next span contributes. The span is
       still "found", so a ===false check cannot see it. Count the
       delimiters instead. */
    if ($v !== false && substr_count($v, '/*') !== substr_count($v, '*/')) {
        fwrite(STDERR, "lift of {$n} splits a comment: "
            . substr_count($v, '/*') . " open, "
            . substr_count($v, '*/') . " close\n");
        exit(2);
    }
}

foreach (array('consts' => $consts, 'sane' => $sane, 'state' => $state,
               'ilog' => $ilog, 'table' => $tableM, 'config' => $config,
               'purge' => $purge, 'p3d' => $p3d, 'cron' => $cron,
               'cli' => $cli) as $n => $v) {
    if ($v === false) {
        fwrite(STDERR, "cannot lift {$n} from {$clsPath}\n");
        exit(2);
    }
}

/* ------------------------------------------------------------------ */
/* Harness.                                                            */
/* ------------------------------------------------------------------ */

$tmp = sys_get_temp_dir() . '/slp29_' . getmypid();
@mkdir($tmp, 0777, true);

$head = <<<'PHPHEAD'
<?php
$GLOBALS['sql']      = array();
$GLOBALS['unparsed'] = array();
$GLOBALS['logged']   = array();
$GLOBALS['cli']      = array();
$GLOBALS['http']     = array();   /* every URL actually requested */
$GLOBALS['canned']   = array();   /* responses, popped in order */
$GLOBALS['options']  = array();
$GLOBALS['snapshot'] = array();   /* what the queue SELECT reports */
$GLOBALS['table']    = array();   /* what the rows actually are */
$GLOBALS['locator']  = array();   /* wp_store_locator by sl_id */

define('ARRAY_A', 'ARRAY_A');
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);

class SLP_CLI_Halt extends Exception {}

class WP_Error {
    private $m;
    public function __construct($m) { $this->m = $m; }
    public function get_error_message() { return $this->m; }
}
function is_wp_error($t) { return ($t instanceof WP_Error); }

function wp_remote_get($url, $args = array()) {
    $GLOBALS['http'][] = array('url' => $url, 'args' => $args);
    if (empty($GLOBALS['canned'])) {
        return new WP_Error('no canned response left');
    }
    return array_shift($GLOBALS['canned']);
}
function wp_remote_retrieve_response_code($r) { return $r['code'] ?? 0; }
function wp_remote_retrieve_body($r)          { return $r['body'] ?? ''; }

function get_option($k, $d = false) {
    return array_key_exists($k, $GLOBALS['options']) ? $GLOBALS['options'][$k] : $d;
}
function update_option($k, $v, $autoload = null) {
    $GLOBALS['options'][$k] = $v;
    $GLOBALS['logged'][] = array('option', $k, $autoload);
    return true;
}
function wp_json_encode($v) { return json_encode($v); }
function current_time($type, $gmt = 0) { return '2026-09-14 00:00:00'; }

class SLP_Test_wpdb {
    public $prefix = 'wp_';

    /* A real substitution, so every assertion reads the finished
       statement rather than the source text that produced it. */
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
                if ($t === '%') { $out .= '%'; $i += 2; continue; }
            }
            $out .= $c;
            $i++;
        }
        return $out;
    }

    /* THE QUEUE SELECT IS APPLIED, NOT ASSUMED. place_id IS NULL and
       place_status are read off the snapshot and enforced here, so a
       build that drops either from the WHERE selects rows it should
       not have seen and the scenario notices. */
    public function get_results($sql, $mode = null) {
        $GLOBALS['sql'][] = $sql;
        if (strpos($sql, 'address_key, sl_id, error_count') === false) {
            return array();
        }
        $wantPending = (strpos($sql, "place_status = 'pending'") !== false);
        $wantNullId  = (strpos($sql, 'place_id IS NULL') !== false);
        $limit = 1000;
        if (preg_match('/LIMIT (\d+)/', $sql, $m)) { $limit = (int) $m[1]; }

        $out = array();
        foreach ($GLOBALS['snapshot'] as $k => $row) {
            if ($wantPending && $row['place_status'] !== 'pending') { continue; }
            if ($wantNullId  && $row['place_id'] !== null)          { continue; }
            $out[] = array(
                'address_key' => $k,
                'sl_id'       => $row['sl_id'],
                'error_count' => $row['error_count'],
            );
            if (count($out) >= $limit) { break; }
        }
        return $out;
    }

    public function get_row($sql, $mode = null) {
        $GLOBALS['sql'][] = $sql;
        if (! preg_match('/sl_id = (\d+)/', $sql, $m)) { return null; }
        $id = (int) $m[1];
        return $GLOBALS['locator'][$id] ?? null;
    }

    /* Executes the statement against $GLOBALS['table']. Conditions are
       applied, not assumed - which is the entire point, because the
       condition under test is the one a broken build deletes. */
    public function query($sql) {
        $GLOBALS['sql'][] = $sql;
        if (! preg_match('/^UPDATE (\S+) SET (.+) WHERE (.+)$/', $sql, $m)) {
            $GLOBALS['unparsed'][] = $sql;
            return false;
        }
        $sets  = self::sets($m[2]);
        $conds = self::conds($m[3]);
        if ($sets === false || $conds === false) {
            $GLOBALS['unparsed'][] = $sql;
            return false;
        }
        $n = 0;
        foreach ($GLOBALS['table'] as $key => $row) {
            $hit = true;
            foreach ($conds as $c) {
                $col = $c[0]; $op = $c[1]; $want = $c[2];
                $have = ($col === 'address_key') ? $key
                      : (array_key_exists($col, $row) ? $row[$col] : null);
                if ($op === 'ISNULL') {
                    if ($have !== null) { $hit = false; break; }
                } elseif ($op === '>=') {
                    if (! is_numeric($have) || (int) $have < (int) $want) { $hit = false; break; }
                } elseif ($op === '>') {
                    if (! is_numeric($have) || (int) $have <= (int) $want) { $hit = false; break; }
                } else {
                    if ((string) $have !== (string) $want) { $hit = false; break; }
                }
            }
            if (! $hit) { continue; }
            foreach ($sets as $col => $val) {
                if ($val === '@INC') {
                    $GLOBALS['table'][$key][$col] =
                        (int) ($GLOBALS['table'][$key][$col] ?? 0) + 1;
                } elseif ($val === '@NULL') {
                    $GLOBALS['table'][$key][$col] = null;
                } else {
                    $GLOBALS['table'][$key][$col] = $val;
                }
            }
            $n++;
        }
        return $n;
    }

    /* col = 'value' | col = NULL | col = col + 1. Every '=' in the clause
       must be accounted for, or the parse was partial and the caller is
       told so rather than handed a silent half-read. */
    /* Split on commas that are OUTSIDE single quotes. A value such as
       'NOT_ESTABLISHMENT street_address,subpremise' carries one, and
       explode(',') tears it in half. */
    private static function splitTop($clause) {
        $out = array(); $cur = ''; $q = false;
        $n = strlen($clause);
        for ($i = 0; $i < $n; $i++) {
            $c = $clause[$i];
            if ($c === "'") { $q = ! $q; $cur .= $c; continue; }
            if ($c === ',' && ! $q) { $out[] = $cur; $cur = ''; continue; }
            $cur .= $c;
        }
        $out[] = $cur;
        return $out;
    }

    private static function sets($clause) {
        $out  = array();
        $seen = 0;
        foreach (self::splitTop($clause) as $piece) {
            $piece = trim($piece);
            if (preg_match("/^([a-z_]+) = '([^']*)'$/", $piece, $p)) {
                $out[$p[1]] = $p[2]; $seen++;
            } elseif (preg_match('/^([a-z_]+) = NULL$/', $piece, $p)) {
                $out[$p[1]] = '@NULL'; $seen++;
            } elseif (preg_match('/^([a-z_]+) = \1 \+ 1$/', $piece, $p)) {
                $out[$p[1]] = '@INC'; $seen++;
            } elseif (preg_match('/^([a-z_]+) = (\d+)$/', $piece, $p)) {
                $out[$p[1]] = $p[2]; $seen++;
            } else {
                return false;
            }
        }
        $bare = preg_replace("/'[^']*'/", "''", $clause);
        return ($seen === substr_count($bare, '=')) ? $out : false;
    }

    private static function conds($clause) {
        $out  = array();
        $seen = 0;
        foreach (preg_split('/ AND /', $clause) as $piece) {
            $piece = trim($piece);
            if (preg_match("/^([a-z_]+) = '([^']*)'$/", $piece, $p)) {
                $out[] = array($p[1], '=', $p[2]); $seen++;
            } elseif (preg_match('/^([a-z_]+) IS NULL$/', $piece, $p)) {
                $out[] = array($p[1], 'ISNULL', null);
            } elseif (preg_match('/^([a-z_]+) (>=|>) (\d+)$/', $piece, $p)) {
                $out[] = array($p[1], $p[2], $p[3]);
            } else {
                return false;
            }
        }
        return $out;
    }
}
$wpdb = new SLP_Test_wpdb();

class SLP_Test_Opt { public $value = ''; }
class SLP_Test_Smart { public $google_server_key; }
$slplus = new stdClass();
$slplus->SmartOptions = new SLP_Test_Smart();
$slplus->SmartOptions->google_server_key = new SLP_Test_Opt();
$slplus->SmartOptions->google_server_key->value = 'TESTKEY123';

class WP_CLI {
    public static function log($m)     { $GLOBALS['cli'][] = array('log', $m); }
    public static function success($m) { $GLOBALS['cli'][] = array('success', $m); }
    public static function add_command($n, $c) { $GLOBALS['cli'][] = array('cmd', $n); }
    /* Production exits(1) here. Modelled as a throw so the scenario can
       still report what had - or had not - been written before it fired. */
    public static function error($m) {
        $GLOBALS['cli'][] = array('error', $m);
        throw new SLP_CLI_Halt($m);
    }
}

/* The measured Google payloads. Both were observed on aurapontoonstg on
   2026-09-14 against the resolver's own query for dealer 5d8707238cbb. */
function canned_not_establishment() {
    return array('code' => 200, 'body' => json_encode(array(
        'status'  => 'OK',
        'results' => array(array(
            'name'              => '1900 W Nickerson St #112',
            'formatted_address' => '1900 W Nickerson St #112, Seattle, WA 98119',
            'place_id'          => 'ChIJ_STREET_ADDRESS',
            'types'             => array('street_address', 'subpremise'),
        )),
    )));
}
function canned_hit($pid = 'ChIJ_GOOD') {
    return array('code' => 200, 'body' => json_encode(array(
        'status'  => 'OK',
        'results' => array(array(
            'name'              => 'A Real Marina',
            'formatted_address' => '1 Dock Rd, Somewhere, WA 98119',
            'place_id'          => $pid,
            'types'             => array('establishment', 'point_of_interest'),
        )),
    )));
}
function canned_status($s) {
    return array('code' => 200, 'body' => json_encode(array(
        'status' => $s, 'results' => array())));
}

PHPHEAD;

$harness  = $head;
$harness .= "\$scenario = \$argv[1];\n";
$harness .= "require " . var_export(realpath($akPath), true) . ";\n\n";

$harness .= "class SLP_Avalon {\n";
$harness .= "    private \$avalon_import_state = null;\n";
$harness .= $consts . "\n";
$harness .= $sane . "\n";
$harness .= $state . "\n";
$harness .= $ilog . "\n";
$harness .= $tableM . "\n";
$harness .= $config . "\n";
$harness .= $purge . "\n";
$harness .= $p3d . "\n";
$harness .= $cron . "\n";
$harness .= $cli . "\n";
$harness .= <<<'PHPTAIL'
    private static function log($e) { $GLOBALS['logged'][] = array('log', $e); }
    /* NOT the production flush. If the CLI still reaches for this, the
       scenario sees it - which is the whole of the s0.233 assertion. */
    public function avalon_flush_import_log($final = true) {
        $GLOBALS['logged'][] = array('ROTATED', $final);
    }
    public function avalon_places_seed() {
        $this->avalon_import_log(array('stage' => 'places_seed'));
        return array('rows' => 1, 'keys' => 1, 'inserted' => 1,
                     'updated' => 0, 'skipped' => 0, 'unmapped' => 0);
    }
    public function avalon_places_import($p, $a = false, $m = '') {
        return array('error' => 'not lifted');
    }
}

PHPTAIL;

$harness .= <<<'PHPRUN'
$o = new SLP_Avalon();

/* THE HELPERS ARE PRIVATE AND STAY PRIVATE. Reflection reaches them
   without widening production visibility for the convenience of a test -
   a suite that needs the artefact changed to be testable is testing a
   different artefact. */
function priv($o, $m, $args = array()) {
    $r = new ReflectionMethod('SLP_Avalon', $m);
    $r->setAccessible(true);
    return $r->invokeArgs($o, $args);
}

/* Ordinary state: the snapshot and the table agree. */
function seed($rows, $locator = null) {
    $GLOBALS['snapshot'] = $rows;
    $GLOBALS['table']    = array();
    foreach ($rows as $k => $r) {
        $GLOBALS['table'][$k] = array(
            'place_status'     => $r['place_status'],
            'place_id'         => $r['place_id'],
            'place_checked_at' => null,
            'error_count'      => $r['error_count'],
            'last_error'       => null,
            'updated_at'       => null,
        );
    }
    $GLOBALS['locator'] = $locator ?? array(
        104734 => array(
            'sl_store'     => 'Rockingham Marina Seattle',
            'sl_address'   => '1900 W Nickerson ST #112',
            'sl_city'      => 'Seattle',
            'sl_state'     => 'WA',
            'sl_zip'       => '98119',
            'sl_country'   => 'USA',
            'sl_latitude'  => '47.655606',
            'sl_longitude' => '-122.380791',
        ),
        200 => array(
            'sl_store'     => 'Second Dealer',
            'sl_address'   => '2 Bay St',
            'sl_city'      => 'Tampa',
            'sl_state'     => 'FL',
            'sl_zip'       => '33602',
            'sl_country'   => 'USA',
            'sl_latitude'  => '27.9506',
            'sl_longitude' => '-82.4572',
        ),
        300 => array(
            'sl_store'     => 'Zero Coord Dealer',
            'sl_address'   => '3 Nowhere Rd',
            'sl_city'      => 'Gulf',
            'sl_state'     => 'TX',
            'sl_zip'       => '77001',
            'sl_country'   => 'USA',
            'sl_latitude'  => '0',
            'sl_longitude' => '0',
        ),
    );
}

function one($key = '5d8707238cbb', $sl = 104734, $ec = 0) {
    return array($key => array('place_status' => 'pending', 'place_id' => null,
                               'sl_id' => $sl, 'error_count' => $ec));
}

$r = null; $halt = '';

try {
    switch ($scenario) {

    /* ---- the lift itself ---------------------------------------- */
    case 'decl':
        $r = array();
        foreach (array('avalon_places_query', 'avalon_places_scrub',
                       'avalon_places_is_systemic', 'avalon_places_text_search',
                       'avalon_places_log_flush', 'avalon_places_resolve',
                       'avalon_places_note', 'avalon_places_cron',
                       'avalon_places_cli', 'avalon_places_subcommands',
                       'avalon_places_purge', 'avalon_hours_config') as $mm) {
            $r[$mm] = method_exists('SLP_Avalon', $mm);
        }
        break;

    /* ---- query builder ------------------------------------------- */
    case 'query':
        seed(one());
        $r = array(
            'rockingham' => priv($o, 'avalon_places_query', array($GLOBALS['locator'][104734])),
            'no_unit'    => priv($o, 'avalon_places_query', array(array(
                'sl_store' => 'Unit Free', 'sl_address' => '5 Main St',
                'sl_city' => 'Akron', 'sl_state' => 'OH', 'sl_zip' => '44301',
                'sl_country' => 'USA'))),
            'unit_split' => priv($o, 'avalon_places_query', array(array(
                'sl_store' => 'Suite Dealer', 'sl_address' => '7 Pier Rd',
                'sl_city' => 'Barrie', 'sl_state' => 'ON', 'sl_zip' => 'L4M 1A1',
                'sl_country' => 'CANADA'))),
            'empty'      => priv($o, 'avalon_places_query', array(array())),
        );
        break;

    /* ---- classifier ---------------------------------------------- */
    case 'classify':
        $m = function ($e) use ($o) { return priv($o, 'avalon_places_is_systemic', array($e)); };
        $r = array(
            'fatal'  => $m('FATAL REQUEST_DENIED'),
            'http'   => $m('HTTP 403'),
            'oneword'=> $m('BADJSON'),
            'noplace'=> $m('NO_PLACE_ID'),
            'nores'  => $m('no result'),
            'notest' => $m('NOT_ESTABLISHMENT street_address,subpremise'),
            'status' => $m('STATUS INVALID_REQUEST'),
            'empty'  => $m(''),
        );
        break;

    /* ---- adapter ------------------------------------------------- */
    case 'adapter':
        $call = function ($canned, $lat = '47.655606', $lng = '-122.380791',
                          $region = 'US', $key = 'TESTKEY123') use ($o) {
            $GLOBALS['canned'] = array($canned);
            $GLOBALS['http']   = array();
            $out = priv($o, 'avalon_places_text_search', array(
                'Rockingham Marina Seattle, 1900 W Nickerson ST #112,'
                . ' Seattle WA 98119, USA', $lat, $lng, $region, $key, 8));
            $out['url'] = $GLOBALS['http'][0]['url'] ?? '';
            $out['args'] = $GLOBALS['http'][0]['args'] ?? array();
            $out['calls'] = count($GLOBALS['http']);
            return $out;
        };
        $r = array(
            'hit'      => $call(canned_hit()),
            'street'   => $call(canned_not_establishment()),
            'emptyok'  => $call(array('code' => 200, 'body' => json_encode(
                            array('status' => 'OK', 'results' => array())))),
            'zero'     => $call(canned_status('ZERO_RESULTS')),
            'denied'   => $call(canned_status('REQUEST_DENIED')),
            'overq'    => $call(canned_status('OVER_QUERY_LIMIT')),
            'invalid'  => $call(canned_status('INVALID_REQUEST')),
            'http403'  => $call(array('code' => 403, 'body' => '{}')),
            'badjson'  => $call(array('code' => 200, 'body' => 'not json')),
            'zerocoord'=> $call(canned_hit(), '0', '0'),
            'noregion' => $call(canned_hit(), '47.655606', '-122.380791', 'MX'),
            'nokey'    => $call(canned_hit(), '47.655606', '-122.380791', 'US', ''),
        );
        /* A transport error carries the message the key could be in. */
        $GLOBALS['canned'] = array();
        $GLOBALS['http']   = array();
        $r['wperr'] = priv($o, 'avalon_places_text_search', array(
            'q', '', '', 'US', 'TESTKEY123', 8));
        break;

    /* ---- resolve: a clean hit ------------------------------------ */
    case 'hit':
        seed(one());
        $GLOBALS['canned'] = array(canned_hit('ChIJ_RESOLVED'));
        $r = $o->{'avalon_places_resolve'}(0, false);
        break;

    /* ---- resolve: the race, s0.231 ------------------------------- */
    case 'race':
        seed(one());
        /* The snapshot says no id; the table already has one. place_status
           stays 'pending' in BOTH - if the table were flipped to 'ok' the
           place_status condition in the same WHERE would match nothing on
           its own and the place_id guard would never be exercised. s0.231:
           a guard can only be tested where the branch is not also true. */
        $GLOBALS['table']['5d8707238cbb']['place_id'] = 'ChIJ_ALREADY';
        $GLOBALS['canned'] = array(canned_hit('ChIJ_LATE'));
        $r = $o->{'avalon_places_resolve'}(0, false);
        break;

    /* ---- resolve: first strike ----------------------------------- */
    case 'strike1':
        seed(one('5d8707238cbb', 104734, 0));
        $GLOBALS['canned'] = array(canned_not_establishment());
        $r = $o->{'avalon_places_resolve'}(0, false);
        break;

    /* ---- resolve: second strike, must NOT fail -------------------- */
    case 'strike2':
        seed(one('5d8707238cbb', 104734, 1));
        $GLOBALS['canned'] = array(canned_not_establishment());
        $r = $o->{'avalon_places_resolve'}(0, false);
        break;

    /* ---- resolve: third strike, must fail ------------------------ */
    case 'strike3':
        seed(one('5d8707238cbb', 104734, 2));
        $GLOBALS['canned'] = array(canned_not_establishment());
        $r = $o->{'avalon_places_resolve'}(0, false);
        break;

    /* ---- resolve: a clean miss is still a strike ------------------ */
    case 'miss':
        seed(one());
        $GLOBALS['canned'] = array(canned_status('ZERO_RESULTS'));
        $r = $o->{'avalon_places_resolve'}(0, false);
        break;

    /* ---- resolve: systemic must not strike ----------------------- */
    case 'systemic':
        seed(one('5d8707238cbb', 104734, 2));
        $GLOBALS['canned'] = array(array('code' => 500, 'body' => '{}'));
        $r = $o->{'avalon_places_resolve'}(0, false);
        break;

    /* ---- resolve: FATAL breaks the loop -------------------------- */
    case 'fatal':
        $rows = one() + array('bbbbbbbbbbbb' => array(
            'place_status' => 'pending', 'place_id' => null,
            'sl_id' => 200, 'error_count' => 0));
        seed($rows);
        $GLOBALS['canned'] = array(canned_status('REQUEST_DENIED'),
                                   canned_hit('ChIJ_NEVER'));
        $r = $o->{'avalon_places_resolve'}(0, false);
        break;

    /* ---- resolve: dry run ---------------------------------------- */
    case 'dry':
        seed(one());
        $GLOBALS['canned'] = array(canned_hit());
        $r = $o->{'avalon_places_resolve'}(0, true);
        break;

    /* ---- resolve: the ceiling bounds the spend -------------------- */
    case 'ceiling':
        $rows = one() + array(
            'bbbbbbbbbbbb' => array('place_status' => 'pending', 'place_id' => null,
                                    'sl_id' => 200, 'error_count' => 0),
            'cccccccccccc' => array('place_status' => 'pending', 'place_id' => null,
                                    'sl_id' => 300, 'error_count' => 0));
        seed($rows);
        $GLOBALS['canned'] = array(canned_hit('A'), canned_hit('B'), canned_hit('C'));
        $r = $o->{'avalon_places_resolve'}(2, false);
        break;

    /* ---- resolve: an already-resolved row is never selected ------- */
    case 'skip_ok':
        seed(array('okokokokokok' => array('place_status' => 'ok',
                   'place_id' => 'ChIJ_X', 'sl_id' => 200, 'error_count' => 0)));
        $GLOBALS['canned'] = array(canned_hit());
        $r = $o->{'avalon_places_resolve'}(0, false);
        break;

    /* ---- cron ---------------------------------------------------- */
    case 'cron_on':
        seed(one());
        $GLOBALS['canned'] = array(canned_hit('ChIJ_CRON'));
        $o->avalon_places_cron();
        $r = array('done' => 1);
        break;
    case 'cron_off':
        seed(one());
        define('AVALON_HOURS_ENABLED', false);
        $GLOBALS['canned'] = array(canned_hit('ChIJ_NEVER'));
        $o->avalon_places_cron();
        $r = array('done' => 1);
        break;

    /* ---- CLI ----------------------------------------------------- */
    case 'cli_unknown':
        seed(one());
        $o->avalon_places_cli(array('statsu'), array());
        break;
    case 'cli_status':
        seed(one());
        $o->avalon_places_cli(array('status'), array());
        $r = array('done' => 1);
        break;
    case 'cli_bare':
        seed(one());
        $o->avalon_places_cli(array(), array());
        $r = array('done' => 1);
        break;
    case 'cli_dry':
        seed(one());
        $GLOBALS['canned'] = array(canned_hit());
        $o->avalon_places_cli(array('resolve'), array('dry-run' => true));
        $r = array('done' => 1);
        break;
    case 'cli_fatal':
        seed(one());
        $GLOBALS['canned'] = array(canned_status('REQUEST_DENIED'));
        $o->avalon_places_cli(array('resolve'), array());
        break;
    case 'cli_seed':
        seed(one());
        $o->avalon_places_cli(array('seed'), array());
        $r = array('done' => 1);
        break;
    }
} catch (SLP_CLI_Halt $e) {
    $halt = $e->getMessage();
}

echo json_encode(array(
    'r'        => $r,
    'halt'     => $halt,
    'table'    => $GLOBALS['table'],
    'sql'      => $GLOBALS['sql'],
    'unparsed' => $GLOBALS['unparsed'],
    'logged'   => $GLOBALS['logged'],
    'cli'      => $GLOBALS['cli'],
    'http'     => $GLOBALS['http'],
    'options'  => $GLOBALS['options'],
));
PHPRUN;

$hfile = $tmp . '/harness.php';
file_put_contents($hfile, $harness);

function run($hfile, $scenario)
{
    $err = sys_get_temp_dir() . '/slp29err_' . getmypid() . '_' . $scenario;
    $out = array();
    $rc  = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -d short_open_tag=1 '
         . escapeshellarg($hfile) . ' ' . escapeshellarg($scenario)
         . ' 2>' . escapeshellarg($err), $out, $rc);
    $raw = implode("\n", $out);
    $j   = json_decode($raw, true);
    if (! is_array($j)) {
        /* A scenario that did not run is not a pass and not a fail. The
           suite has nothing to say about a build it could not execute,
           and a vacuous PASS here is worse than no result at all. */
        fwrite(STDERR, "\n  SUITE BROKEN: scenario '{$scenario}' produced no JSON\n");
        fwrite(STDERR, "  stdout: " . substr($raw, 0, 400) . "\n");
        fwrite(STDERR, "  stderr: " . substr(@file_get_contents($err), 0, 800) . "\n");
        exit(2);
    }
    @unlink($err);
    return $j;
}

function cliText($s) {
    $t = '';
    foreach (($s['cli'] ?? array()) as $l) { $t .= $l[1] . "\n"; }
    return $t;
}
function sqlText($s) { return implode("\n", $s['sql'] ?? array()); }

/* ------------------------------------------------------------------ */
/* Assertions.                                                         */
/* ------------------------------------------------------------------ */

echo "\n  suite-v029  -  v0.0.26 Part 3d\n";
echo "  artefact  {$clsPath}\n";
echo "  addresskey {$akPath}\n";

/* THE PINNED QUERY. Reproduced from build/resolve-placeids.py running
   offline against the three feeds on 2026-09-14. Not typed from the
   handoff - s0.235, where a hand-typed query asked a different question
   and answered it convincingly. */
$PINNED_QUERY = 'Rockingham Marina Seattle, 1900 W Nickerson ST #112,'
              . ' Seattle WA 98119, USA';
$PINNED_URL   = 'https://maps.googleapis.com/maps/api/place/textsearch/json'
              . '?query=Rockingham+Marina+Seattle%2C+1900+W+Nickerson+ST'
              . '+%23112%2C+Seattle+WA+98119%2C+USA&key=TESTKEY123'
              . '&location=47.655606%2C-122.380791&radius=50000&region=us';

echo "\n  THE LIFT\n";
$dl = run($hfile, 'decl');
foreach (($dl['r'] ?? array()) as $mm => $there) {
    check($there === true, "{$mm} is defined inside the harness");
}

echo "\n  QUERY BUILDER\n";
$q = run($hfile, 'query');
check(($q['r']['rockingham'] ?? '') === $PINNED_QUERY,
    'the query is byte-equal to the one that resolved the other 302');
check(strpos($q['r']['rockingham'] ?? '', 'Seattle WA 98119') !== false,
    'the city is RAW - Seattle, not the normalised SEATTLE');
check(strpos($q['r']['rockingham'] ?? '', '1900 W Nickerson ST #112') !== false,
    'the address is RAW, whole line, unit included');
check(substr_count($q['r']['rockingham'] ?? '', '#112') === 1,
    's0.222 - the unit is not spelled twice');
check(substr($q['r']['rockingham'] ?? '', -5) === ', USA',
    'a US dealer ends the query with USA');
check(substr($q['r']['unit_split'] ?? '', -8) === ', Canada',
    'a CA dealer ends the query with Canada');
check(strpos($q['r']['no_unit'] ?? '', 'Unit Free, 5 Main St, Akron OH 44301') === 0,
    'a dealer with no unit builds without one');
check(($q['r']['empty'] ?? 'x') === '',
    'an empty row asks Google nothing');

echo "\n  FAILURE TAXONOMY  (s0.224)\n";
$c = run($hfile, 'classify');
check(($c['r']['fatal']   ?? null) === true,  'FATAL ... is systemic');
check(($c['r']['http']    ?? null) === true,  'HTTP ... is systemic');
check(($c['r']['oneword'] ?? null) === true,  'BADJSON - one word - is systemic');
check(($c['r']['noplace'] ?? null) === true,  'NO_PLACE_ID is systemic');
check(($c['r']['nores']   ?? null) === false, 'no result is dealer data');
check(($c['r']['notest']  ?? null) === false, 'NOT_ESTABLISHMENT is dealer data');
check(($c['r']['status']  ?? null) === false, 'STATUS ... is dealer data');
check(($c['r']['empty']   ?? null) === false, 'no error is not a systemic error');

echo "\n  TEXT SEARCH ADAPTER\n";
$a = run($hfile, 'adapter');
check(($a['r']['hit']['url'] ?? '') === $PINNED_URL,
    'the finished URL is byte-equal to the resolver\'s');
check(($a['r']['hit']['place_id'] ?? '') === 'ChIJ_GOOD'
      && ($a['r']['hit']['err'] ?? 'x') === '',
    'an establishment returns its place_id with no error');
check(($a['r']['street']['place_id'] ?? 'x') === ''
      && strpos($a['r']['street']['err'] ?? '', 'NOT_ESTABLISHMENT') === 0,
    's0.223 - the measured street_address response is refused, not stored');
check(strpos($a['r']['street']['err'] ?? '', 'street_address') !== false,
    'the refusal names the types it saw');
check(($a['r']['emptyok']['err'] ?? 'x') === ''
      && ($a['r']['emptyok']['place_id'] ?? 'x') === '',
    'OK with no results is a clean miss, not an error');
check(($a['r']['zero']['err'] ?? 'x') === '',
    'ZERO_RESULTS is a miss, not an error');
check(($a['r']['denied']['err'] ?? '') === 'FATAL REQUEST_DENIED',
    's0.220 - REQUEST_DENIED is FATAL');
check(($a['r']['overq']['err'] ?? '') === 'FATAL OVER_QUERY_LIMIT',
    's0.220 - OVER_QUERY_LIMIT is FATAL');
check(($a['r']['invalid']['err'] ?? '') === 'STATUS INVALID_REQUEST',
    'an unlisted status is per-query, not fatal');
check(($a['r']['http403']['err'] ?? '') === 'HTTP 403',
    'a non-200 is reported as HTTP with its code');
check(($a['r']['badjson']['err'] ?? '') === 'BADJSON',
    'an unparsable body is BADJSON');
check(strpos($a['r']['zerocoord']['url'] ?? '', 'location=') === false,
    '0,0 is not a location - no bias is sent');
check(strpos($a['r']['noregion']['url'] ?? '', 'region=') === false,
    'a region that is neither US nor CA is omitted');
check(($a['r']['nokey']['err'] ?? '') === 'FATAL NO_KEY'
      && ($a['r']['nokey']['calls'] ?? 1) === 0,
    'a missing key is fatal BEFORE any call is made');
check(strpos($a['r']['wperr']['err'] ?? '', 'HTTP ') === 0,
    'a transport failure is HTTP, which never strikes');
check(strpos(json_encode($a['r']['wperr'] ?? array()), 'TESTKEY123') === false,
    'the key does not survive into the returned error');
check((($a['r']['hit']['args']['timeout'] ?? 0) === 8)
      && (($a['r']['hit']['args']['sslverify'] ?? false) === true),
    'the request carries the timeout and verifies TLS');

echo "\n  RESOLVE - WRITES\n";
$h = run($hfile, 'hit');
$hrow = $h['table']['5d8707238cbb'] ?? array();
check(($h['r']['resolved'] ?? 0) === 1 && ($h['r']['called'] ?? 0) === 1,
    'a hit is one call and one resolution');
check(($hrow['place_id'] ?? '') === 'ChIJ_RESOLVED'
      && ($hrow['place_status'] ?? '') === 'ok',
    'the row takes the place_id and goes ok');
check(($hrow['place_checked_at'] ?? '') === '2026-09-14 00:00:00',
    'place_checked_at records when Google was asked');
check(($hrow['error_count'] ?? -1) === '0' || ($hrow['error_count'] ?? -1) === 0,
    'a hit clears the strike count');
check(empty($h['unparsed']),
    'every statement the resolver issued was parseable SQL');

$rc = run($hfile, 'race');
check(($rc['table']['5d8707238cbb']['place_id'] ?? '') === 'ChIJ_ALREADY',
    's0.231 - never-re-resolve holds when the snapshot is stale');
check(($rc['table']['5d8707238cbb']['place_status'] ?? '') === 'pending',
    'and the stale row is not flipped to ok on the strength of a stale read');
check(($rc['r']['raced'] ?? 0) === 1 && ($rc['r']['resolved'] ?? 0) === 0,
    'the race is counted, not silently swallowed');

echo "\n  RESOLVE - THE STRIKE RULE\n";
$s1 = run($hfile, 'strike1');
$s1row = $s1['table']['5d8707238cbb'] ?? array();
check(empty($s1['unparsed']),
    'the strike statements are parseable - a refusal to write is not a policy');
check((int) ($s1row['error_count'] ?? 0) === 1, 'a data negative increments the count');
check(($s1row['place_status'] ?? '') === 'pending', 'one strike does not fail the row');
check(strpos($s1row['last_error'] ?? '', 'NOT_ESTABLISHMENT') === 0,
    'the reason is recorded on the row');
check(($s1row['place_checked_at'] ?? '') === '2026-09-14 00:00:00',
    'a data negative stamps place_checked_at - Google did answer');

$s2 = run($hfile, 'strike2');
check(($s2['table']['5d8707238cbb']['place_status'] ?? '') === 'pending',
    'two strikes still does not fail the row');
check(($s2['r']['failed'] ?? 1) === 0, 'and does not report a failure');

$s3 = run($hfile, 'strike3');
check(($s3['table']['5d8707238cbb']['place_status'] ?? '') === 'failed',
    'the third strike fails the row - the ceiling is 3, not 4');
check(($s3['r']['failed'] ?? 0) === 1, 'and reports it');

$ms = run($hfile, 'miss');
check((int) ($ms['table']['5d8707238cbb']['error_count'] ?? 0) === 1,
    'a clean miss is a strike too');
check(($ms['table']['5d8707238cbb']['last_error'] ?? '') === 'no result',
    'a miss is spelled, not left blank - blank would read as never tried');
check(($ms['r']['missed'] ?? 0) === 1, 'and is counted separately from a refusal');

$sy = run($hfile, 'systemic');
$syrow = $sy['table']['5d8707238cbb'] ?? array();
check((int) ($syrow['error_count'] ?? 9) === 2,
    'a systemic failure does NOT strike - the count is untouched');
check(($syrow['place_status'] ?? '') === 'pending',
    'and cannot fail a row that was one strike from the ceiling');
check(array_key_exists('place_checked_at', $syrow)
      && $syrow['place_checked_at'] === null,
    'and does NOT stamp place_checked_at - it answered about the project');
check(strpos($syrow['last_error'] ?? '', 'HTTP') === 0,
    'but is still recorded, so the operator can see it');
check(($sy['r']['systemic'] ?? 0) === 1, 'and is counted as systemic');

$ft = run($hfile, 'fatal');
check(count($ft['http'] ?? array()) === 1,
    's0.220 - a FATAL stops the run; the second dealer is never called');
check(strpos($ft['r']['fatal'] ?? '', 'FATAL') === 0,
    'the fatal is returned so the caller can refuse to report success');
check(($ft['r']['considered'] ?? 0) === 1,
    'and no further row is even considered');

echo "\n  RESOLVE - DRY RUN AND BOUNDS\n";
$d = run($hfile, 'dry');
check(count($d['http'] ?? array()) === 0, 'a dry run spends nothing');
check(($d['table']['5d8707238cbb']['place_status'] ?? '') === 'pending'
      && ($d['table']['5d8707238cbb']['last_error'] ?? null) === null,
    'a dry run writes no row');
check(empty($d['logged']), 'a dry run writes no log either - the log is a write');
check(($d['r']['queries']['5d8707238cbb'] ?? '') === $PINNED_QUERY,
    'a dry run returns the query it would have sent');

$ce = run($hfile, 'ceiling');
check(count($ce['http'] ?? array()) === 2, 'the ceiling bounds the number of calls');
check(($ce['r']['considered'] ?? 0) === 2, 'and is applied in the SELECT, not after');

$sk = run($hfile, 'skip_ok');
check(count($sk['http'] ?? array()) === 0,
    'a row that already has a place_id is never re-resolved');
check(strpos(sqlText($sk), 'place_id IS NULL') !== false,
    'and the SELECT says so explicitly');

echo "\n  CRON\n";
$on = run($hfile, 'cron_on');
check(strpos(sqlText($on), 'hours_status') !== false, 'the cron still purges');
check(count($on['http'] ?? array()) === 1, 'and resolves when enabled');
$off = run($hfile, 'cron_off');
check(strpos(sqlText($off), 'hours_status') !== false,
    'the purge is NOT gated on enabled - expiry is a licence obligation');
check(count($off['http'] ?? array()) === 0, 'but resolution is');

echo "\n  CLI\n";
$cu = run($hfile, 'cli_unknown');
check(strpos($cu['halt'] ?? '', 'unknown subcommand') !== false,
    's0.232 - an unknown subcommand errors');
check(strpos($cu['halt'] ?? '', 'resolve') !== false,
    'and lists the valid ones');
check(strpos(sqlText($cu), 'GROUP BY place_status') === false,
    'and does NOT silently print a status table');
$cs = run($hfile, 'cli_status');
check(strpos(sqlText($cs), 'GROUP BY place_status') !== false,
    'status asked for by name still works');
$cb = run($hfile, 'cli_bare');
check(strpos(sqlText($cb), 'GROUP BY place_status') !== false,
    'and a bare invocation still means status');
$cd = run($hfile, 'cli_dry');
check(count($cd['http'] ?? array()) === 0
      && strpos(cliText($cd), 'nothing spent') !== false,
    'resolve --dry-run spends nothing and says so');
check(strpos(cliText($cd), $PINNED_QUERY) !== false,
    'and prints the query for the operator to read first');
$cf = run($hfile, 'cli_fatal');
check(strpos($cf['halt'] ?? '', 'aborted on FATAL') !== false,
    'a fatal run exits through WP_CLI::error, not success');
$cse = run($hfile, 'cli_seed');
$rotated = false;
foreach (($cse['logged'] ?? array()) as $l) {
    if (($l[0] ?? '') === 'ROTATED') { $rotated = true; }
}
check(! $rotated,
    's0.233 - the CLI no longer rotates the CSV import override log');
check(array_key_exists('avalon_places_log', $cse['options'] ?? array()),
    'it persists its own record instead');
$autoload = null;
foreach (($cse['logged'] ?? array()) as $l) {
    if (($l[0] ?? '') === 'option' && ($l[1] ?? '') === 'avalon_places_log') {
        $autoload = $l[2];
    }
}
check($autoload === 'no', 'and writes it with autoload no');

echo "\n  DECLARATIONS\n";
foreach (array(
    'private function avalon_places_query(',
    'private function avalon_places_scrub(',
    'private function avalon_places_is_systemic(',
    'private function avalon_places_text_search(',
    'private function avalon_places_log_flush(',
    'private function avalon_places_note(',
    'public function avalon_places_resolve(',
    'public static function avalon_places_subcommands(',
) as $decl) {
    check(substr_count($code, $decl) === 1, trim($decl, '(') . ' declared once');
}
check(strpos($code, 'places.googleapis.com') === false
      && strpos($code, 'maps/api/place/textsearch/json') !== false,
    'the legacy endpoint is used, not the disabled New one');
check(strpos($code, 'const PLACES_BIAS_RADIUS_M  = 50000;') !== false,
    'the bias radius is pinned at the value that resolved the 302');
check(strpos($code, '$results[1]') === false,
    'results[1:] is not scanned for a nearby establishment');

printf("\n  %d passed, %d failed, %d total\n\n", $pass, $fail, $pass + $fail);
exit($fail === 0 ? 0 : 1);
