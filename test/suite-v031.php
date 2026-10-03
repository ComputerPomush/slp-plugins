<?php
/**
 * suite-v031.php - validates slp_avalon v0.0.27 Part 3b.
 *
 *   the hours cron: HOURS_CRON_HOOK, the schedule gate, avalon_hours_cron()
 *   wp avalon hours status | sweep | release
 *   the disputed-key block list, in code, and its sync
 *   s0.257  the purge retires none rows and clears business_status
 *   s0.258  both sweep writes carry the place id and the block
 *   s0.259  business_status through NULLIF
 *   s0.260  ok means weekday lines
 *   s0.262  the sweep refreshes before the purge's cap
 *   s0.263  a strike clears the cached Place; the purge clears content
 *           held under pending or blocked and keeps the status
 *   s0.264  a refused queue read is BADQUERY, not an empty queue
 *   s0.265  --max-calls must be a plain whole number of 1 or more; a
 *           release of nothing, or a refused release, is an error that
 *           says which; status reports a refused read as an error
 *
 * WHAT CARRIES FORWARD BY IDENTITY, AND WHAT IS EXECUTED
 *
 * Part 3b touched four regions of the class and nothing else. Diffed
 * against v0.0.27-part3a (8bc1724a) on 2026-09-29, the file cuts at ten
 * anchors into eleven regions. Seven are pinned byte for byte to Part 3a:
 *
 *   H   head
 *   A1  instance() .. add_actions()
 *   A2  register_shortcodes() .. the hours table   (territory gate, import
 *       guard, reconcile, redirects)
 *   T   the hours table, install, schema gate
 *   F   config and the whole Part 2 fetch layer, through the verdict map
 *   B1  REST strip, places schedule gate, seed
 *   B2  the Part 3d resolver, import, CLI - to the end of the file
 *
 * suite-v030 scored F at 109/109 against 18 controls, and suite-v029 scored
 * B2 at 107/107 against 7. Byte identity carries that evidence forward;
 * this suite does not re-run it.
 *
 * The four that changed are executed:
 *
 *   C     the constants - asserted by value, and asserted to be Part 3a's
 *         block plus the Part 3b insertion and nothing else
 *   WIRE  add_actions() - run against recording doubles, and asserted to be
 *         Part 3a's method plus the Part 3b registrations and nothing else
 *   S     the sweep and the seven Part 3b methods
 *   P     the purge
 *
 * $wpdb IS THE SMALL REAL SQL ENGINE suite-v030 introduced and checked
 * against MariaDB 10.11, carried over and extended for Part 3b's
 * statements: NULLIF, NOT IN, COUNT(*) with GROUP BY. It honours the output
 * mode, returns full ties in reverse insertion order, and records any
 * statement it cannot read as unparsed. The finished Part 3b statements
 * were run on MariaDB 10.11 against the same fixtures on 2026-09-29 and
 * agreed row for row.
 *
 * ERRORS ARE REPORTED THE WAY wpdb REPORTS THEM. last_error is reset by
 * every statement; get_results() returns an EMPTY ARRAY when the database
 * refuses a statement and null only for an empty query; query() returns
 * false. suite-v030's double returned null, which is the one shape
 * production never hands back for a refused read - s0.264. prepare()
 * counts placeholders against arguments as wpdb does, and every mismatch
 * fails the HARNESS assertion by name.
 *
 * THE RACE IS SIMULATED, NOT ASSUMED. s0.258's guard can only be seen when
 * the table changes between the queue read and the write. The engine
 * accepts one callback that runs after the SELECT returns; the race
 * scenario uses it to block one row and re-point another under the
 * sweep's feet. A snapshot and a table that always agree cannot see the
 * guard at all - s0.231.
 *
 * THE CLOCK TICKS, and keeps the site four hours behind GMT, as in
 * suite-v030. A CRASH IS A RESULT: anything thrown inside the lifted code
 * fails the HARNESS assertion by name. WP_CLI::error() halts in production;
 * the double throws, and the halt is recorded separately from a crash.
 *
 * ONE PROCESS PER SCENARIO.
 *
 * Usage:
 *   php suite-v031.php <class.slp_avalon.php>
 *
 * Exit 0 all passed, 1 any failed, 2 the suite could not run.
 */

$clsPath = $argv[1] ?? 'build/out-v027p3b/class.slp_avalon.php';

$code = @file_get_contents($clsPath);
if ($code === false) {
    fwrite(STDERR, "cannot read {$clsPath}\n");
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
/* The ten anchors and the eleven regions.                             */
/* ------------------------------------------------------------------ */

$ANCHORS = array(
    'C'    => "        /**\r\n         * v0.0.26 Part 1. Schema version for the dealer-places table.",
    'A1'   => '        public static function instance(){',
    'WIRE' => '        private function add_actions(){',
    'A2'   => '        private function register_shortcodes(){',
    'T'    => '        public static function avalon_hours_table(){',
    'F'    => '        public function avalon_hours_config(){',
    'S'    => "        /**\r\n         * v0.0.27 Part 3a. One pass of the hours queue.",
    'B1'   => '        public function avalon_rest_protected_slugs(){',
    'P'    => "        /**\r\n         * v0.0.26 Part 3. The TTL purge.",
    'B2'   => "        /**\r\n         * v0.0.26 Part 3d. Build the Text Search query for one dealer.",
);

/* The bytes of v0.0.27-part3a (8bc1724a7e932fed0bfc7cb18e6cd39f), measured
   2026-09-29 with these same anchors. */
$PINNED_REGIONS = array(
    'H'  => array('22c0b4f1a3d343c421fa6b2b3aaf4e8a',    104),
    'A1' => array('b1e8b7f7dd92b712975535952822b79c',   3273),
    'A2' => array('45613f767e0553551d3d6f22f25e0eeb', 108959),
    'T'  => array('3fb5b916cdc60a42b477b601cf60404f',   6368),
    'F'  => array('6201bd491ec402915fd7347dc93e423e',  16332),
    'B1' => array('2daf45b972c779a95cf3b091e07c31a6',  15461),
    'B2' => array('1267329f7be5c4d011203e40c4131891',  45442),
);
/* Part 3a's versions of the two regions Part 3b ADDED to without editing. */
$PART3A_C    = array('e03fc6d0a90bd723ea8a030aa1c91395', 7267);
$PART3A_WIRE = array('a2c3542e67c810124c084d4780f65080', 7462);

$at = array();
$anchorsOk = true;
foreach ($ANCHORS as $k => $needle) {
    if (substr_count($code, $needle) !== 1) { $anchorsOk = false; continue; }
    $at[$k] = strpos($code, $needle);
}
if ($anchorsOk) {
    $prev = -1;
    foreach (array_keys($ANCHORS) as $k) {
        if ($at[$k] <= $prev) { $anchorsOk = false; }
        $prev = $at[$k];
    }
}
if (! $anchorsOk) {
    fwrite(STDERR, "the ten region anchors are not each present once, in order\n");
    exit(2);
}
$keys = array_keys($ANCHORS);
$regions = array('H' => substr($code, 0, $at['C']));
foreach ($keys as $n => $k) {
    $end = isset($keys[$n + 1]) ? $at[$keys[$n + 1]] : strlen($code);
    $regions[$k] = substr($code, $at[$k], $end - $at[$k]);
}

/* ------------------------------------------------------------------ */
/* Lift the code under test, verbatim.                                 */
/* ------------------------------------------------------------------ */

function span($code, $from, $to)
{
    $a = strpos($code, $from);
    if ($a === false) { return false; }
    $b = strpos($code, $to, $a + 1);
    if ($b === false) { return false; }
    return substr($code, $a, $b - $a);
}

$LIFTS = array(
    'consts'   => $regions['C'],
    'wire'     => $regions['WIRE'],
    'table'    => $regions['T'],
    'fetch'    => $regions['F'],
    'sweep'    => $regions['S'],
    'purge'    => $regions['P'],
    'state'    => span($code, '        private function avalon_state($key){',
                              '        private function avalon_state_bump($key){'),
    'ilog'     => span($code, '        private function avalon_import_log($record){',
                              "        /**\r\n         * Flush the override log and the geocode cache."),
    'systemic' => span($code, '        private function avalon_places_is_systemic( $err ){',
                              "        /**\r\n         * v0.0.26 Part 3d. One legacy Text Search call."),
    'logflush' => span($code, '        private function avalon_places_log_flush(){',
                              "        /**\r\n         * v0.0.26 Part 3d. Resolve pending keys against Text Search."),
);
foreach ($LIFTS as $n => $v) {
    if ($v === false || $v === '') {
        fwrite(STDERR, "cannot lift {$n} from {$clsPath}\n");
        exit(2);
    }
    if (substr_count($v, '/*') !== substr_count($v, '*/')) {
        fwrite(STDERR, "lift of {$n} splits a comment\n");
        exit(2);
    }
}

/* ------------------------------------------------------------------ */
/* Harness.                                                            */
/* ------------------------------------------------------------------ */

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'slp31_' . getmypid();
$abs = $tmp . DIRECTORY_SEPARATOR . 'wp' . DIRECTORY_SEPARATOR;
@mkdir($abs . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes', 0777, true);
$upgrade = $abs . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes'
         . DIRECTORY_SEPARATOR . 'upgrade.php';
file_put_contents($upgrade, "<?php\nfunction dbDelta(\$q) {\n"
    . "    \$GLOBALS['dbdelta'][] = \$q;\n"
    . "    return \$GLOBALS['dbdelta_changes'];\n}\n");

$head = <<<'PHPHEAD'
<?php
$GLOBALS['sql']       = array();   /* every statement, finished */
$GLOBALS['unparsed']  = array();   /* statements the engine could not read */
$GLOBALS['logged']    = array();
$GLOBALS['http']      = array();   /* every request actually made */
$GLOBALS['canned']    = array();   /* responses, popped in order */
$GLOBALS['byplace']   = array();   /* responses, keyed by place id */
$GLOBALS['fallback']  = null;      /* answer for a place nobody expected */
$GLOBALS['options']   = array();
$GLOBALS['table']     = array();
$GLOBALS['dbdelta']   = array();
$GLOBALS['dbdelta_changes'] = array();
$GLOBALS['clock_base']  = time();
$GLOBALS['clock_calls'] = 0;
$GLOBALS['doing_it_wrong'] = array(); /* every prepare() whose placeholders and arguments disagree */
$GLOBALS['after_select'] = null;  /* runs once, after the next SELECT returns */
$GLOBALS['fail_update']  = false; /* every UPDATE is refused, as a dead database would */

define('ARRAY_A', 'ARRAY_A');
define('ARRAY_N', 'ARRAY_N');
define('OBJECT',  'OBJECT');
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);

class WP_Error {
    private $c; private $m;
    public function __construct($c = '', $m = '') { $this->c = $c; $this->m = $m; }
    public function get_error_code()    { return $this->c; }
    public function get_error_message() { return $this->m; }
}
function is_wp_error($t) { return ($t instanceof WP_Error); }

function wp_remote_get($url, $args = array()) {
    $GLOBALS['http'][] = array('url' => $url, 'args' => $args);
    $pid = null;
    if (preg_match('/[?&]place_id=([^&]*)/', $url, $m)) {
        $pid = $m[1];
    } elseif (preg_match('#/v1/places/([^/?]+)$#', $url, $m)) {
        $pid = rawurldecode($m[1]);
    }
    if ($pid !== null && array_key_exists($pid, $GLOBALS['byplace'])) {
        return $GLOBALS['byplace'][$pid];
    }
    if (! empty($GLOBALS['canned'])) {
        return array_shift($GLOBALS['canned']);
    }
    if ($GLOBALS['fallback'] !== null) {
        return $GLOBALS['fallback'];
    }
    return new WP_Error('no_canned_response', 'the scenario supplied no response');
}
function wp_remote_retrieve_response_code($r) { return is_array($r) ? ($r['code'] ?? 0) : 0; }
function wp_remote_retrieve_body($r)          { return is_array($r) ? ($r['body'] ?? '') : ''; }

/* WordPress's add_query_arg() does NOT urlencode: build_query() passes
   urlencode false. Reproduced, so the pinned URL is the one WordPress
   would actually request. */
function add_query_arg($args, $url) {
    $pairs = array();
    foreach ($args as $k => $v) { $pairs[] = $k . '=' . $v; }
    return $url . (strpos($url, '?') === false ? '?' : '&') . implode('&', $pairs);
}

function get_option($k, $d = false) {
    return array_key_exists($k, $GLOBALS['options']) ? $GLOBALS['options'][$k] : $d;
}
function update_option($k, $v, $autoload = null) {
    $GLOBALS['options'][$k] = $v;
    $GLOBALS['logged'][] = array('option', $k, $autoload);
    return true;
}
function wp_json_encode($v) { return json_encode($v); }

/* THE CLOCK TICKS: one second later on every call. And it keeps a site
   four hours behind GMT, so a stamp taken in local time cannot pass for
   one taken in GMT - the TTL cutoffs are computed in GMT. */
function current_time($type, $gmt = 0) {
    $GLOBALS['clock_calls']++;
    $ts = $GLOBALS['clock_base'] + $GLOBALS['clock_calls'];
    if (! $gmt) { $ts -= 4 * 3600; }
    return gmdate('Y-m-d H:i:s', $ts);
}

/* ---------------------------------------------------------------- */
/* A small SQL engine: SELECT and UPDATE over one in-memory table.   */
/* ---------------------------------------------------------------- */

class SLP_SQL_Error extends Exception {}

class SLP_SQL {

    const RESERVED = array('SELECT', 'FROM', 'WHERE', 'ORDER', 'BY', 'LIMIT',
        'UPDATE', 'SET', 'AND', 'OR', 'NOT', 'IS', 'IN', 'NULL', 'ASC', 'DESC',
        'GROUP', 'AS', 'COUNT', 'NULLIF');

    public static function lex($s) {
        $out = array(); $i = 0; $n = strlen($s);
        $unesc = array('0' => "\0", 'n' => "\n", 'r' => "\r", 't' => "\t",
                       'b' => "\x08", 'Z' => "\x1a");
        while ($i < $n) {
            $c = $s[$i];
            if ($c === ' ' || $c === "\t" || $c === "\n" || $c === "\r") { $i++; continue; }
            if ($c === "'") {
                $v = ''; $i++; $closed = false;
                while ($i < $n) {
                    $d = $s[$i];
                    if ($d === '\\' && $i + 1 < $n) {
                        $e = $s[$i + 1];
                        $v .= array_key_exists($e, $unesc) ? $unesc[$e] : $e;
                        $i += 2; continue;
                    }
                    if ($d === "'") {
                        if ($i + 1 < $n && $s[$i + 1] === "'") { $v .= "'"; $i += 2; continue; }
                        $closed = true; $i++; break;
                    }
                    $v .= $d; $i++;
                }
                if (! $closed) { throw new SLP_SQL_Error('unterminated string literal'); }
                $out[] = array('s', $v); continue;
            }
            if ($c >= '0' && $c <= '9') {
                $j = $i;
                while ($j < $n && $s[$j] >= '0' && $s[$j] <= '9') { $j++; }
                $out[] = array('n', (int) substr($s, $i, $j - $i)); $i = $j; continue;
            }
            if (preg_match('/[A-Za-z_]/', $c)) {
                $j = $i;
                while ($j < $n && preg_match('/[A-Za-z0-9_]/', $s[$j])) { $j++; }
                $out[] = array('w', substr($s, $i, $j - $i)); $i = $j; continue;
            }
            $two = substr($s, $i, 2);
            if ($two === '<>' || $two === '!=' || $two === '<=' || $two === '>=') {
                $out[] = array('p', $two === '!=' ? '<>' : $two); $i += 2; continue;
            }
            if (strpos('=<>(),+-*', $c) !== false) { $out[] = array('p', $c); $i++; continue; }
            throw new SLP_SQL_Error("unexpected character '{$c}'");
        }
        return $out;
    }

    private $t; private $i = 0;
    private function __construct($t) { $this->t = $t; }
    private function peek() { return $this->t[$this->i] ?? null; }
    private function isKw($w) {
        $p = $this->peek();
        return $p !== null && $p[0] === 'w' && strtoupper($p[1]) === $w;
    }
    private function kw($w) {
        if (! $this->isKw($w)) { throw new SLP_SQL_Error("expected {$w}"); }
        $this->i++;
    }
    private function isP($c) {
        $p = $this->peek();
        return $p !== null && $p[0] === 'p' && $p[1] === $c;
    }
    private function punct($c) {
        if (! $this->isP($c)) { throw new SLP_SQL_Error("expected '{$c}'"); }
        $this->i++;
    }
    private function ident() {
        $p = $this->peek();
        if ($p === null || $p[0] !== 'w' || in_array(strtoupper($p[1]), self::RESERVED, true)) {
            throw new SLP_SQL_Error('expected an identifier');
        }
        $this->i++;
        return strtolower($p[1]);
    }

    public static function parse($sql) {
        $p = new SLP_SQL(self::lex($sql));
        $st = $p->statement();
        if ($p->peek() !== null) { throw new SLP_SQL_Error('trailing tokens'); }
        return $st;
    }

    /* column [AS alias] | COUNT(*) [AS alias] */
    private function selectItem() {
        if ($this->isKw('COUNT')) {
            $this->kw('COUNT'); $this->punct('('); $this->punct('*'); $this->punct(')');
            $alias = 'COUNT(*)';
            if ($this->isKw('AS')) { $this->kw('AS'); $alias = $this->ident(); }
            return array('count', $alias);
        }
        $name = $this->ident(); $alias = $name;
        if ($this->isKw('AS')) { $this->kw('AS'); $alias = $this->ident(); }
        return array('col', $name, $alias);
    }

    private function statement() {
        if ($this->isKw('SELECT')) {
            $this->kw('SELECT');
            $cols = array();
            if ($this->isP('*')) { $this->punct('*'); $cols = '*'; }
            else {
                $cols[] = $this->selectItem();
                while ($this->isP(',')) { $this->punct(','); $cols[] = $this->selectItem(); }
            }
            $this->kw('FROM');
            $table = $this->ident();
            $where = null; $order = array(); $limit = null; $group = null;
            if ($this->isKw('WHERE')) { $this->kw('WHERE'); $where = $this->expr(); }
            if ($this->isKw('GROUP')) {
                $this->kw('GROUP'); $this->kw('BY');
                $group = array($this->ident());
                while ($this->isP(',')) { $this->punct(','); $group[] = $this->ident(); }
            }
            if ($this->isKw('ORDER')) {
                $this->kw('ORDER'); $this->kw('BY');
                do {
                    if ($this->isP(',')) { $this->punct(','); }
                    $e = $this->expr(); $dir = 'ASC';
                    if ($this->isKw('ASC'))  { $this->kw('ASC'); }
                    elseif ($this->isKw('DESC')) { $this->kw('DESC'); $dir = 'DESC'; }
                    $order[] = array($e, $dir);
                } while ($this->isP(','));
            }
            if ($this->isKw('LIMIT')) {
                $this->kw('LIMIT');
                $p = $this->peek();
                if ($p === null || $p[0] !== 'n') { throw new SLP_SQL_Error('LIMIT needs a number'); }
                $this->i++; $limit = $p[1];
            }
            if ($group !== null && ! empty($order)) {
                throw new SLP_SQL_Error('ORDER BY with GROUP BY is not modelled');
            }
            return array('type' => 'select', 'cols' => $cols, 'table' => $table,
                         'where' => $where, 'order' => $order, 'limit' => $limit,
                         'group' => $group);
        }
        if ($this->isKw('UPDATE')) {
            $this->kw('UPDATE');
            $table = $this->ident();
            $this->kw('SET');
            $sets = array();
            do {
                if ($this->isP(',')) { $this->punct(','); }
                $col = $this->ident(); $this->punct('=');
                $sets[] = array($col, $this->expr());
            } while ($this->isP(','));
            $this->kw('WHERE');
            return array('type' => 'update', 'table' => $table, 'sets' => $sets,
                         'where' => $this->expr());
        }
        throw new SLP_SQL_Error('only SELECT and UPDATE are understood');
    }

    private function expr() { return $this->orx(); }
    private function orx() {
        $l = $this->andx();
        while ($this->isKw('OR')) { $this->kw('OR'); $l = array('or', $l, $this->andx()); }
        return $l;
    }
    private function andx() {
        $l = $this->notx();
        while ($this->isKw('AND')) { $this->kw('AND'); $l = array('and', $l, $this->notx()); }
        return $l;
    }
    private function notx() {
        if ($this->isKw('NOT')) { $this->kw('NOT'); return array('not', $this->notx()); }
        return $this->cmp();
    }
    private function cmp() {
        $l = $this->add();
        foreach (array('=', '<>', '<=', '>=', '<', '>') as $op) {
            if ($this->isP($op)) { $this->punct($op); return array('cmp', $op, $l, $this->add()); }
        }
        if ($this->isKw('IS')) {
            $this->kw('IS'); $neg = false;
            if ($this->isKw('NOT')) { $this->kw('NOT'); $neg = true; }
            $this->kw('NULL');
            return array('isnull', $l, $neg);
        }
        $neg = false;
        if ($this->isKw('NOT')) { $this->kw('NOT'); $neg = true; }
        if ($this->isKw('IN')) {
            $this->kw('IN'); $this->punct('(');
            $list = array($this->add());
            while ($this->isP(',')) { $this->punct(','); $list[] = $this->add(); }
            $this->punct(')');
            return array('in', $l, $list, $neg);
        }
        if ($neg) { throw new SLP_SQL_Error('NOT without IN'); }
        return $l;
    }
    private function add() {
        $l = $this->prim();
        while ($this->isP('+') || $this->isP('-')) {
            $op = $this->peek()[1]; $this->i++;
            $l = array('arith', $op, $l, $this->prim());
        }
        return $l;
    }
    private function prim() {
        $p = $this->peek();
        if ($p === null) { throw new SLP_SQL_Error('unexpected end of statement'); }
        if ($p[0] === 's') { $this->i++; return array('lit', $p[1]); }
        if ($p[0] === 'n') { $this->i++; return array('lit', $p[1]); }
        if ($this->isKw('NULL')) { $this->kw('NULL'); return array('null'); }
        if ($this->isKw('NULLIF')) {
            $this->kw('NULLIF'); $this->punct('(');
            $a = $this->expr(); $this->punct(','); $b = $this->expr();
            $this->punct(')');
            return array('nullif', $a, $b);
        }
        if ($this->isP('(')) { $this->punct('('); $e = $this->expr(); $this->punct(')'); return $e; }
        return array('col', $this->ident());
    }

    /* MySQL semantics, as far as these statements reach. */
    private static function num($v) { return is_int($v) || is_float($v); }
    private static function cmpv($a, $b) {
        if (self::num($a) || self::num($b)) {
            $x = (float) $a; $y = (float) $b;
            return ($x < $y) ? -1 : (($x > $y) ? 1 : 0);
        }
        $c = strcasecmp((string) $a, (string) $b);     /* utf8mb4_general_ci */
        return ($c < 0) ? -1 : (($c > 0) ? 1 : 0);
    }
    private static function tv($v) {
        if ($v === null) { return null; }
        return ((float) $v != 0) ? 1 : 0;
    }
    public static function ev($n, $row) {
        switch ($n[0]) {
            case 'lit':  return $n[1];
            case 'null': return null;
            case 'nullif':
                /* NULL when a = b is TRUE; otherwise a. A NULL b never
                   compares true, so it hands a back. */
                $a = self::ev($n[1], $row);
                if ($a === null) { return null; }
                $b = self::ev($n[2], $row);
                if ($b === null) { return $a; }
                return (self::cmpv($a, $b) === 0) ? null : $a;
            case 'col':
                if (! array_key_exists($n[1], $row)) {
                    throw new SLP_SQL_Error("unknown column {$n[1]}");
                }
                return $row[$n[1]];
            case 'arith':
                $l = self::ev($n[2], $row); $r = self::ev($n[3], $row);
                if ($l === null || $r === null) { return null; }
                return ($n[1] === '+') ? ((int) $l + (int) $r) : ((int) $l - (int) $r);
            case 'cmp':
                $l = self::ev($n[2], $row); $r = self::ev($n[3], $row);
                if ($l === null || $r === null) { return null; }
                $c = self::cmpv($l, $r);
                switch ($n[1]) {
                    case '=':  return (int) ($c === 0);
                    case '<>': return (int) ($c !== 0);
                    case '<':  return (int) ($c < 0);
                    case '>':  return (int) ($c > 0);
                    case '<=': return (int) ($c <= 0);
                    case '>=': return (int) ($c >= 0);
                }
                throw new SLP_SQL_Error('bad operator');
            case 'isnull':
                $v = (self::ev($n[1], $row) === null) ? 1 : 0;
                return $n[2] ? (1 - $v) : $v;
            case 'in':
                $v = self::ev($n[1], $row);
                if ($v === null) { return null; }
                $sawNull = false;
                foreach ($n[2] as $x) {
                    $x = self::ev($x, $row);
                    if ($x === null) { $sawNull = true; continue; }
                    if (self::cmpv($v, $x) === 0) { return $n[3] ? 0 : 1; }
                }
                if ($sawNull) { return null; }
                return $n[3] ? 1 : 0;
            case 'and':
                $l = self::tv(self::ev($n[1], $row));
                if ($l === 0) { return 0; }
                $r = self::tv(self::ev($n[2], $row));
                if ($r === 0) { return 0; }
                return ($l === null || $r === null) ? null : 1;
            case 'or':
                $l = self::tv(self::ev($n[1], $row));
                if ($l === 1) { return 1; }
                $r = self::tv(self::ev($n[2], $row));
                if ($r === 1) { return 1; }
                return ($l === null || $r === null) ? null : 0;
            case 'not':
                $v = self::tv(self::ev($n[1], $row));
                return ($v === null) ? null : (1 - $v);
        }
        throw new SLP_SQL_Error('bad node');
    }

    public static function select($st) {
        $rows = array(); $seq = 0;
        foreach ($GLOBALS['table'] as $row) {
            if ($st['where'] === null || self::tv(self::ev($st['where'], $row)) === 1) {
                $rows[] = array($seq++, $row);
            }
        }
        $hasCount = false;
        if ($st['cols'] !== '*') {
            foreach ($st['cols'] as $c) { if ($c[0] === 'count') { $hasCount = true; } }
        }
        if ($st['group'] !== null || $hasCount) { return self::grouped($st, $rows); }
        $order = $st['order'];
        usort($rows, function ($a, $b) use ($order) {
            foreach ($order as $o) {
                $x = SLP_SQL::ev($o[0], $a[1]); $y = SLP_SQL::ev($o[0], $b[1]);
                if ($x === null && $y === null) { $c = 0; }
                elseif ($x === null) { $c = -1; }            /* NULLS FIRST on ASC */
                elseif ($y === null) { $c = 1; }
                else { $c = SLP_SQL::cmpPublic($x, $y); }
                if ($o[1] === 'DESC') { $c = -$c; }
                if ($c !== 0) { return $c; }
            }
            /* MySQL leaves full ties unordered. Reverse insertion order here,
               so a build that drops a tie-breaker cannot be rescued by a
               fixture that happens to be inserted in key order. */
            return $b[0] - $a[0];
        });
        if ($st['limit'] !== null) { $rows = array_slice($rows, 0, $st['limit']); }
        $out = array();
        foreach ($rows as $r) {
            $row = $r[1]; $sel = array();
            $cols = array();
            if ($st['cols'] === '*') {
                foreach (array_keys($row) as $k) { $cols[] = array('col', $k, $k); }
            } else {
                $cols = $st['cols'];
            }
            foreach ($cols as $c) {
                if (! array_key_exists($c[1], $row)) { throw new SLP_SQL_Error("unknown column {$c[1]}"); }
                /* wpdb hands every value back as a string. */
                $sel[$c[2]] = ($row[$c[1]] === null) ? null : (string) $row[$c[1]];
            }
            $out[] = $sel;
        }
        return $out;
    }

    /* GROUP BY, or an aggregate with none. Groups compare the way the
       collation does - case-insensitively - and NULLs form one group.
       ONLY_FULL_GROUP_BY: a bare column must be a grouped one. Groups come
       back in group-column order, NULL first, as MariaDB returns them. */
    private static function grouped($st, $rows) {
        $group = ($st['group'] === null) ? array() : $st['group'];
        if ($st['cols'] === '*') { throw new SLP_SQL_Error('SELECT * with GROUP BY'); }
        foreach ($st['cols'] as $c) {
            if ($c[0] === 'col' && ! in_array($c[1], $group, true)) {
                throw new SLP_SQL_Error("{$c[1]} is not in GROUP BY");
            }
        }
        $buckets = array();
        foreach ($rows as $r) {
            $row = $r[1]; $key = array();
            foreach ($group as $g) {
                if (! array_key_exists($g, $row)) { throw new SLP_SQL_Error("unknown column {$g}"); }
                $key[] = ($row[$g] === null) ? "\0NULL" : 'v' . strtolower((string) $row[$g]);
            }
            $k = implode("\1", $key);
            if (! isset($buckets[$k])) { $buckets[$k] = array('first' => $row, 'n' => 0); }
            $buckets[$k]['n']++;
        }
        if (empty($group) && empty($buckets)) {
            $buckets[''] = array('first' => array(), 'n' => 0);
        }
        $list = array_values($buckets);
        usort($list, function ($a, $b) use ($group) {
            foreach ($group as $g) {
                $x = $a['first'][$g]; $y = $b['first'][$g];
                if ($x === null && $y === null) { $c = 0; }
                elseif ($x === null) { $c = -1; }
                elseif ($y === null) { $c = 1; }
                else { $c = SLP_SQL::cmpPublic($x, $y); }
                if ($c !== 0) { return $c; }
            }
            return 0;
        });
        $out = array();
        foreach ($list as $b) {
            $sel = array();
            foreach ($st['cols'] as $c) {
                if ($c[0] === 'count') { $sel[$c[1]] = (string) $b['n']; continue; }
                $v = $b['first'][$c[1]];
                $sel[$c[2]] = ($v === null) ? null : (string) $v;
            }
            $out[] = $sel;
        }
        if ($st['limit'] !== null) { $out = array_slice($out, 0, $st['limit']); }
        return $out;
    }
    public static function cmpPublic($a, $b) { return self::cmpv($a, $b); }

    public static function update($st) {
        $n = 0;
        foreach ($GLOBALS['table'] as $key => $row) {
            if (self::tv(self::ev($st['where'], $row)) !== 1) { continue; }
            $new = $row;
            foreach ($st['sets'] as $s) {
                if (! array_key_exists($s[0], $new)) { throw new SLP_SQL_Error("unknown column {$s[0]}"); }
                /* MySQL applies single-table assignments left to right. */
                $new[$s[0]] = self::ev($s[1], $new);
            }
            if ($new !== $row) { $n++; }
            $GLOBALS['table'][$key] = $new;
        }
        return $n;
    }
}

class SLP_Test_wpdb {
    public $prefix = 'wp_';
    public $fail_select = false;
    public $last_error = '';

    public function get_charset_collate() {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
    }

    /* %s is quoted and escaped the way mysqli_real_escape_string does. */
    public function prepare($sql, ...$a) {
        if (count($a) === 1 && is_array($a[0])) { $a = $a[0]; }
        /* wpdb counts placeholders and compares. A mismatch is
           _doing_it_wrong(); too few arguments returns '' (6.x, to avoid a
           PHP 8 fatal), too many are dropped. Either is a statement that
           does not say what its author meant, and is recorded. */
        $want = preg_match_all('/%[sd]/', str_replace('%%', '', $sql));
        if ($want !== count($a)) {
            $GLOBALS['doing_it_wrong'][] = sprintf('prepare: %d placeholders, %d arguments: %s',
                                                   $want, count($a), substr($sql, 0, 120));
            if (count($a) < $want) { return ''; }
        }
        $out = ''; $i = 0; $n = strlen($sql);
        while ($i < $n) {
            $c = $sql[$i];
            if ($c === '%' && $i + 1 < $n) {
                $t = $sql[$i + 1];
                if ($t === 's') {
                    $v = array_shift($a);
                    $out .= "'" . strtr((string) $v, array(
                        '\\' => '\\\\', "'" => "\\'", '"' => '\\"', "\0" => '\\0',
                        "\n" => '\\n', "\r" => '\\r', "\x1a" => '\\Z')) . "'";
                    $i += 2; continue;
                }
                if ($t === 'd') { $out .= (string) (int) array_shift($a); $i += 2; continue; }
                if ($t === '%') { $out .= '%'; $i += 2; continue; }
            }
            $out .= $c; $i++;
        }
        return $out;
    }

    public function get_results($sql, $mode = null) {
        $this->last_error = '';
        if ($sql === null || $sql === '') { return null; }   /* wpdb: no query, null */
        $GLOBALS['sql'][] = $sql;
        if ($this->fail_select) {
            /* wpdb: a refused statement leaves last_result empty, and
               get_results() returns array(), never null. s0.264. */
            $this->last_error = "Table 'wp.wp_avalon_dealer_places' doesn't exist";
            return array();
        }
        try {
            $st = SLP_SQL::parse($sql);
            if ($st['type'] !== 'select' || $st['table'] !== 'wp_avalon_dealer_places') {
                throw new SLP_SQL_Error('not a SELECT on the places table');
            }
            $rows = SLP_SQL::select($st);
            /* THE RACE. Whatever changes the table now changes it after the
               caller has its rows and before it writes - the window s0.258
               guards. Fires once. */
            if (is_callable($GLOBALS['after_select'])) {
                $cb = $GLOBALS['after_select'];
                $GLOBALS['after_select'] = null;
                $cb();
            }
            /* THE OUTPUT MODE IS HONOURED. wpdb's default is OBJECT, and code
               that indexes a row as an array crashes on one. A double that
               always returned arrays would pass a build that drops ARRAY_A
               and dies on the first due row in production. */
            if ($mode === ARRAY_A) { return $rows; }
            if ($mode === ARRAY_N) { return array_map('array_values', $rows); }
            return array_map(function ($r) { return (object) $r; }, $rows);
        } catch (SLP_SQL_Error $e) {
            $GLOBALS['unparsed'][] = $sql . '  <- ' . $e->getMessage();
            $this->last_error = 'unparsed: ' . $e->getMessage();
            return array();
        }
    }

    public function query($sql) {
        $this->last_error = '';
        if ($sql === null || $sql === '') { return false; }  /* wpdb: no query, false */
        $GLOBALS['sql'][] = $sql;
        if ($GLOBALS['fail_update']) {
            $this->last_error = 'MySQL server has gone away';
            return false;
        }
        try {
            $st = SLP_SQL::parse($sql);
            if ($st['type'] !== 'update' || $st['table'] !== 'wp_avalon_dealer_places') {
                throw new SLP_SQL_Error('not an UPDATE on the places table');
            }
            return SLP_SQL::update($st);
        } catch (SLP_SQL_Error $e) {
            $GLOBALS['unparsed'][] = $sql . '  <- ' . $e->getMessage();
            $this->last_error = 'unparsed: ' . $e->getMessage();
            return false;
        }
    }
}
$wpdb = new SLP_Test_wpdb();

class SLP_Test_Opt { public $value = ''; }
class SLP_Test_Smart { public $google_server_key; }
$slplus = new stdClass();
$slplus->SmartOptions = new SLP_Test_Smart();
$slplus->SmartOptions->google_server_key = new SLP_Test_Opt();
$slplus->SmartOptions->google_server_key->value = 'TESTKEY123';

/* ---------------------------------------------------------------- */
/* Fixtures.                                                          */
/* ---------------------------------------------------------------- */

function week_text() {
    $h = "9:00\u{202F}AM\u{2009}\u{2013}\u{2009}5:00\u{202F}PM";
    return array('Monday: Closed', "Tuesday: {$h}", "Wednesday: {$h}",
                 "Thursday: {$h}", "Friday: {$h}", "Saturday: {$h}", 'Sunday: Closed');
}
/* One period per OPEN day and none for a closed day - the measured shape. */
function week_periods() {
    $p = array();
    foreach (array(2, 3, 4, 5, 6) as $d) {
        $p[] = array('open'  => array('day' => $d, 'time' => '0900'),
                     'close' => array('day' => $d, 'time' => '1700'));
    }
    return $p;
}
function legacy_result($pid, $name, $bstat = 'OPERATIONAL', $hours = true, $extra = array()) {
    $r = array('place_id' => $pid, 'name' => $name, 'utc_offset' => -240);
    if ($bstat !== null) { $r['business_status'] = $bstat; }
    if ($hours) {
        $r['opening_hours'] = array('open_now' => false, 'periods' => week_periods(),
                                    'weekday_text' => week_text());
    }
    foreach ($extra as $k => $v) { $r[$k] = $v; }
    return $r;
}
function legacy_ok($pid, $name, $bstat = 'OPERATIONAL', $hours = true,
                   $attrib = array(), $extra = array()) {
    return array('code' => 200, 'body' => json_encode(array(
        'html_attributions' => $attrib,
        'result'            => legacy_result($pid, $name, $bstat, $hours, $extra),
        'status'            => 'OK')));
}
function legacy_status($s, $msg = null) {
    $b = array('html_attributions' => array(), 'status' => $s);
    if ($msg !== null) { $b['error_message'] = $msg; }
    return array('code' => 200, 'body' => json_encode($b));
}
function new_ok($pid, $name) {
    return array('code' => 200, 'body' => json_encode(array(
        'id' => $pid, 'name' => 'places/' . $pid,
        'displayName' => array('text' => $name, 'languageCode' => 'en'),
        'businessStatus' => 'OPERATIONAL', 'utcOffsetMinutes' => -240,
        'regularOpeningHours' => array(
            'openNow' => false,
            'periods' => array(array(
                'open'  => array('day' => 2, 'hour' => 9,  'minute' => 0),
                'close' => array('day' => 2, 'hour' => 17, 'minute' => 0))),
            'weekdayDescriptions' => week_text()),
        'attributions' => array())));
}
function new_err($code, $status, $msg) {
    $e = array('code' => $code, 'message' => $msg);
    if ($status !== null) { $e['status'] = $status; }
    return array('code' => $code, 'body' => json_encode(array('error' => $e)));
}

function ago($days) { return gmdate('Y-m-d H:i:s', time() - (int) round($days * 86400)); }

/* One row, every column the table declares, so an UPDATE naming a
   column that does not exist is refused rather than invented. */
function row($place_status, $place_id, $hours_status, $fetched_at, $error_count = 0,
             $last_error = null) {
    return array(
        'address_key'          => null,
        'sl_id'                => '1',
        'place_id'             => $place_id,
        'place_status'         => $place_status,
        'place_checked_at'     => '2026-09-14 04:06:03',
        'hours_json'           => null,
        'hours_status'         => $hours_status,
        'fetched_at'           => $fetched_at,
        'business_status'      => null,
        'primary_type_display' => null,
        'locality'             => null,
        'admin_area'           => null,
        'attribution_json'     => null,
        'error_count'          => $error_count,
        'last_error'           => $last_error,
        'updated_at'           => null,
    );
}
function seed($rows) {
    $GLOBALS['table'] = array();
    foreach ($rows as $k => $r) {
        $r['address_key'] = $k;
        $GLOBALS['table'][$k] = $r;
    }
}

/* ---------------------------------------------------------------- */
/* Part 3b doubles.                                                   */
/* ---------------------------------------------------------------- */

$GLOBALS['hooks']     = array();
$GLOBALS['cli']       = array();
$GLOBALS['scheduled'] = array();

function t_cb($cb) {
    if (is_array($cb)) {
        $who = is_object($cb[0]) ? get_class($cb[0]) : (string) $cb[0];
        return array($who . '::' . $cb[1],
                     is_object($cb[0]) && $cb[0] === SLP_Avalon::t_instance());
    }
    return array((string) $cb, false);
}
function add_action($hook, $cb, $prio = 10, $args = 1) {
    $c = t_cb($cb);
    $GLOBALS['hooks'][] = array('action', $hook, $c[0], $prio, $args, $c[1]);
    return true;
}
function add_filter($hook, $cb, $prio = 10, $args = 1) {
    $c = t_cb($cb);
    $GLOBALS['hooks'][] = array('filter', $hook, $c[0], $prio, $args, $c[1]);
    return true;
}
function add_shortcode($tag, $cb) {
    $c = t_cb($cb);
    $GLOBALS['hooks'][] = array('shortcode', $tag, $c[0], 0, 0, $c[1]);
}
function wp_next_scheduled($hook) {
    foreach ($GLOBALS['scheduled'] as $e) {
        if ($e['hook'] === $hook) { return $e['ts']; }
    }
    return false;
}
function wp_schedule_event($ts, $rec, $hook) {
    $GLOBALS['scheduled'][] = array('ts' => $ts, 'rec' => $rec, 'hook' => $hook,
                                    'now' => time());
    return true;
}

class SLP_CLI_Halt extends Exception {}
class WP_CLI {
    public static function log($m)     { $GLOBALS['cli'][] = array('log', (string) $m); }
    public static function success($m) { $GLOBALS['cli'][] = array('success', (string) $m); }
    public static function add_command($n, $cb) {
        $c = t_cb($cb);
        $GLOBALS['hooks'][] = array('command', $n, $c[0], 0, 0, $c[1]);
    }
    /* Production exits here. Modelled as a throw so the scenario can still
       report what had - or had not - been written before it fired. */
    public static function error($m) {
        $GLOBALS['cli'][] = array('error', (string) $m);
        throw new SLP_CLI_Halt((string) $m);
    }
}

/* ---------------------------------------------------------------- */
/* Part 3b fixtures.                                                  */
/* ---------------------------------------------------------------- */

/* Every Places content column filled, as a fetched row holds them. */
function full_content($bstat = 'OPERATIONAL') {
    return array('hours_json' => '{"cached":1}', 'business_status' => $bstat,
                 'attribution_json' => '[]', 'primary_type_display' => 'Boat dealer',
                 'locality' => 'Somewhere', 'admin_area' => 'MI');
}

function with($row, $cols) {
    foreach ($cols as $k => $v) { $row[$k] = $v; }
    return $row;
}

/* THE QUEUE, under Part 3b's rules. Eight rows are due, in this order:
     a00000000001  pending, never fetched          NULL first, by key
     a00000000012  pending, never fetched
     a00000000013  failed, never fetched
     a00000000004  none, 35 days                   then oldest first
     a00000000002  ok, 29 days                     due now: s0.262 margin
     a00000000018  none, 28.5 days                 the margin applies to none too
     a00000000006  failed, 8 days
     a00000000016  pending, 3 days (struck once)
   and twelve are not. Two of those are LISTED keys that the table has not
   marked blocked yet - b391a6d50f59 would sort fourth if the queue did not
   exclude it by name - and 3664a8c7ba9e holds a stale payload. */
function queue_fixture() {
    return array(
        'a00000000001' => row('ok', 'ChIJ_A01', 'pending', null),
        'a00000000002' => row('ok', 'ChIJ_A02', 'ok',      ago(29)),
        'a00000000003' => row('ok', 'ChIJ_A03', 'ok',      ago(27)),
        'a00000000004' => row('ok', 'ChIJ_A04', 'none',    ago(35)),
        'a00000000005' => row('ok', 'ChIJ_A05', 'none',    ago(27)),
        'a00000000006' => row('ok', 'ChIJ_A06', 'failed',  ago(8), 3, 'STATUS ZERO_RESULTS'),
        'a00000000007' => row('ok', 'ChIJ_A07', 'failed',  ago(6), 3),
        'a00000000008' => row('ok', 'ChIJ_B08', 'blocked', null),
        'a00000000009' => row('pending', null,  'pending', null),
        'a00000000010' => row('ok', null,       'pending', null),
        'a00000000011' => row('ok', '',         'pending', null),
        'a00000000012' => row('ok', 'ChIJ_A12', 'pending', null),
        'a00000000013' => row('ok', 'ChIJ_A13', 'failed',  null, 3, 'STATUS NOT_FOUND'),
        'a00000000014' => row('ok', 'ChIJ_B14', 'blocked', ago(40)),
        'a00000000015' => row('failed', null,   'pending', null, 3),
        'a00000000016' => row('ok', 'ChIJ_A16', 'pending', ago(3), 1, 'STATUS NOT_FOUND'),
        'a00000000017' => row('pending', 'ChIJ_P17', 'pending', null),
        'a00000000018' => row('ok', 'ChIJ_A18', 'none',    ago(28.5)),
        'b391a6d50f59' => row('ok', 'ChIJ_LISTED_1', 'pending', null),
        '3664a8c7ba9e' => with(row('ok', 'ChIJ_LISTED_2', 'ok', ago(40)),
                               array('hours_json' => '{"shape":"new","stale":true}',
                                     'business_status' => 'OPERATIONAL')),
    );
}
function queue_answers() {
    return array(
        'ChIJ_A01' => legacy_ok('ChIJ_A01', 'Alpha Marine'),
        'ChIJ_A12' => legacy_ok('ChIJ_A12', 'Closed Boats', 'CLOSED_PERMANENTLY', false),
        'ChIJ_A13' => legacy_ok('ChIJ_A13', 'Thirteen Marine'),
        'ChIJ_A04' => legacy_ok('ChIJ_A04', 'Four Marine'),
        'ChIJ_A02' => legacy_ok('ChIJ_A02', 'Two Marine', null),
        'ChIJ_A18' => legacy_ok('ChIJ_A18', 'Eighteen Marine'),
        'ChIJ_A06' => legacy_ok('ChIJ_A06', 'Six Marine'),
        'ChIJ_A16' => legacy_ok('ChIJ_A16', 'Sixteen Marine'),
    );
}
PHPHEAD;

$harness  = $head;
$harness .= "define('ABSPATH', " . var_export($abs, true) . ");\n";
$harness .= "\$scenario = \$argv[1];\n\n";
$harness .= "class SLP_Avalon {\n";
$harness .= "    private static \$instance;\n";
$harness .= "    private \$avalon_import_state = null;\n";
foreach (array('consts', 'wire', 'table', 'fetch', 'sweep', 'purge',
               'state', 'ilog', 'systemic', 'logflush') as $n) {
    $harness .= $LIFTS[$n] . "\n";
}
$harness .= <<<'PHPTAIL'
    private static function log($e) { $GLOBALS['logged'][] = array('log', $e); }
    /* NOT the production flush. If anything in Part 3b reaches for it, the
       CSV import's override log would rotate - s0.233. Recorded. */
    public function avalon_flush_import_log($final = true) {
        $GLOBALS['logged'][] = array('ROTATED', $final);
    }
    public static function t_instance() { return self::$instance; }
    public static function t_set_instance($o) { self::$instance = $o; }
}

PHPTAIL;

$harness .= <<<'PHPRUN'
$o = new SLP_Avalon();
SLP_Avalon::t_set_instance($o);

function priv($o, $m, $args = array()) {
    $r = new ReflectionMethod('SLP_Avalon', $m);
    if (PHP_VERSION_ID < 80100) { $r->setAccessible(true); }
    return $r->invokeArgs($r->isStatic() ? null : $o, $args);
}

$r = null; $halt = '';

try {
switch ($scenario) {

case 'decl':
    $rc = new ReflectionClass('SLP_Avalon');
    $r = array('consts' => $rc->getConstants(), 'methods' => array(),
               'disputed' => SLP_Avalon::avalon_hours_disputed_keys(),
               'subcommands' => SLP_Avalon::avalon_hours_subcommands());
    foreach (array('avalon_hours_sweep', 'avalon_hours_disputed_keys',
                   'avalon_hours_sync_blocks', 'avalon_hours_release',
                   'avalon_hours_maybe_schedule', 'avalon_hours_cron',
                   'avalon_hours_subcommands', 'avalon_hours_cli',
                   'avalon_places_purge') as $m) {
        $r['methods'][$m] = method_exists('SLP_Avalon', $m);
    }
    break;

/* ---- wiring -------------------------------------------------- */
case 'wire_cli':
    define('WP_CLI', true);
    priv($o, 'add_actions');
    $r = array('done' => 1);
    break;
case 'wire_nocli':
    priv($o, 'add_actions');
    $r = array('done' => 1);
    break;

/* ---- schedule ------------------------------------------------ */
case 'sched_empty':
    $t0 = time();
    $o->avalon_hours_maybe_schedule();
    $r = array('t0' => $t0);
    break;
case 'sched_again':
    $GLOBALS['scheduled'][] = array('ts' => time() + 999, 'rec' => 'daily',
                                    'hook' => 'avalon_hours_refresh', 'now' => time());
    $o->avalon_hours_maybe_schedule();
    $r = array('done' => 1);
    break;
case 'sched_installing':
    define('WP_INSTALLING', true);
    $o->avalon_hours_maybe_schedule();
    $r = array('done' => 1);
    break;

/* ---- block list ---------------------------------------------- */
case 'sync':
    seed(array(
        'b391a6d50f59' => row('ok', 'ChIJ_L1', 'pending', null),
        'e0487241edc4' => with(row('ok', 'ChIJ_L2', 'ok', ago(5), 2),
                               array('hours_json' => '{"x":1}', 'business_status' => 'OPERATIONAL',
                                     'attribution_json' => '[]')),
        '88cd11dcda38' => row('ok', 'ChIJ_L3', 'blocked', null),
        'aae51e47328a' => with(row('ok', 'ChIJ_L4', 'blocked', null),
                               array('hours_json' => '{"stale":1}')),
        'a00000000099' => row('ok', 'ChIJ_H99', 'blocked', null),
        'a00000000098' => with(row('ok', 'ChIJ_K98', 'ok', ago(5)),
                               array('hours_json' => '{"keep":1}', 'business_status' => 'OPERATIONAL')),
    ));
    $before = $GLOBALS['table'];
    $n = priv($o, 'avalon_hours_sync_blocks', array('2026-09-29 12:00:00'));
    $r = array('n' => $n, 'before' => $before);
    break;

case 'release':
    seed(array(
        'b391a6d50f59' => row('ok', 'ChIJ_L1', 'blocked', null),
        'a00000000099' => row('ok', 'ChIJ_H99', 'blocked', null, 3, 'DISPUTED place id'),
        'a00000000098' => row('ok', 'ChIJ_K98', 'ok', ago(5)),
    ));
    $before = $GLOBALS['table'];
    $r = array(
        'listed'  => $o->avalon_hours_release('b391a6d50f59'),
        'bad'     => $o->avalon_hours_release('XYZ'),
        'ok'      => $o->avalon_hours_release(' A00000000099 '),
        'notheld' => $o->avalon_hours_release('a00000000098'),
        'before'  => $before,
    );
    break;

/* ---- the sweep ----------------------------------------------- */
case 'sweep_dry':
    seed(queue_fixture());
    $GLOBALS['fallback'] = legacy_ok('ChIJ_ANY', 'Anybody');
    $before = $GLOBALS['table'];
    $r = array('out' => $o->avalon_hours_sweep(0, true), 'before' => $before);
    break;

case 'sweep_run':
    seed(queue_fixture());
    $GLOBALS['byplace'] = queue_answers();
    $bodies = array();
    foreach ($GLOBALS['byplace'] as $pid => $resp) {
        $bodies[$pid] = json_decode($resp['body'], true);
    }
    $GLOBALS['fallback'] = legacy_ok('ChIJ_UNEXPECTED', 'Should Never Be Asked');
    $before = $GLOBALS['table'];
    $r = array('out' => $o->avalon_hours_sweep(0, false), 'before' => $before,
               'bodies' => $bodies, 'clock_calls' => $GLOBALS['clock_calls']);
    break;

case 'sweep_race':
    seed(array(
        'c00000000001' => row('ok', 'ChIJ_R1', 'pending', null),
        'c00000000002' => row('ok', 'ChIJ_R2', 'pending', null),
        'c00000000003' => row('ok', 'ChIJ_R3', 'pending', null),
        'c00000000004' => row('ok', 'ChIJ_R4', 'pending', null, 1),
        'c00000000005' => row('ok', 'ChIJ_R5', 'pending', null, 1),
    ));
    $GLOBALS['byplace'] = array(
        'ChIJ_R1' => legacy_ok('ChIJ_R1', 'Blocked Underfoot'),
        'ChIJ_R2' => legacy_ok('ChIJ_R2', 'Re-pointed Underfoot'),
        'ChIJ_R3' => legacy_ok('ChIJ_R3', 'Nothing Happened Here'),
        'ChIJ_R4' => legacy_status('ZERO_RESULTS'),
        'ChIJ_R5' => legacy_status('NOT_FOUND'),
    );
    /* After the queue read and before any write: a hand block on one row,
       a Part 3e correction on another, a hand block on a struck one, and a
       Part 3e correction on the other struck one. */
    $GLOBALS['after_select'] = function () {
        $GLOBALS['table']['c00000000001']['hours_status'] = 'blocked';
        $GLOBALS['table']['c00000000002']['place_id']     = 'ChIJ_R2_CORRECTED';
        $GLOBALS['table']['c00000000004']['hours_status'] = 'blocked';
        $GLOBALS['table']['c00000000005']['place_id']     = 'ChIJ_R5_CORRECTED';
    };
    $r = array('out' => $o->avalon_hours_sweep(0, false));
    break;

case 'sweep_lines':
    seed(array(
        'd00000000001' => row('ok', 'ChIJ_L1', 'pending', null),
        'd00000000002' => row('ok', 'ChIJ_L2', 'pending', null),
        'd00000000003' => row('ok', 'ChIJ_L3', 'pending', null),
        'd00000000004' => row('ok', 'ChIJ_L4', 'pending', null),
        'd00000000005' => row('ok', 'ChIJ_L5', 'pending', null),
    ));
    $GLOBALS['byplace'] = array(
        /* openNow and nothing else: something, but nothing to show. */
        'ChIJ_L1' => legacy_ok('ChIJ_L1', 'Open Now Only', 'OPERATIONAL', false,
                               array(), array('opening_hours' => array('open_now' => true))),
        'ChIJ_L2' => legacy_ok('ChIJ_L2', 'Weekday Lines'),
        'ChIJ_L3' => legacy_ok('ChIJ_L3', 'Empty Hours', 'OPERATIONAL', false,
                               array(), array('opening_hours' => array())),
        /* No business_status at all: s0.259. */
        'ChIJ_L4' => legacy_ok('ChIJ_L4', 'No Status', null, false),
        /* periods and no weekday text: machine-readable, nothing to print. */
        'ChIJ_L5' => legacy_ok('ChIJ_L5', 'Periods Only', 'OPERATIONAL', false,
                               array(), array('opening_hours' => array('periods' => week_periods()))),
    );
    $r = array('out' => $o->avalon_hours_sweep(0, false));
    break;

case 'sweep_strike':
    seed(array(
        'e00000000001' => row('ok', 'ChIJ_S1', 'pending', null, 0),
        'e00000000002' => row('ok', 'ChIJ_S2', 'pending', ago(1), 2),
        'e00000000003' => row('ok', 'ChIJ_S3', 'pending', null, 0),
        /* an ok row, re-asked two days early, that Google no longer knows */
        'e00000000004' => with(row('ok', 'ChIJ_S4', 'ok', ago(29), 0),
                               full_content('CLOSED_TEMPORARILY')),
    ));
    $GLOBALS['byplace'] = array(
        'ChIJ_S1' => legacy_status('ZERO_RESULTS'),
        'ChIJ_S2' => legacy_status('NOT_FOUND'),
        'ChIJ_S3' => legacy_ok('ChIJ_S3', 'Three Marine'),
        'ChIJ_S4' => legacy_status('NOT_FOUND'),
    );
    $r = array('out' => $o->avalon_hours_sweep(0, false));
    break;

case 'sweep_systemic':
    seed(array(
        'f00000000001' => row('ok', 'ChIJ_Y1', 'pending', null, 2),
        'f00000000002' => row('ok', 'ChIJ_Y2', 'pending', null, 0),
    ));
    $GLOBALS['byplace'] = array(
        'ChIJ_Y1' => legacy_status('REQUEST_DENIED', 'The provided API key is invalid.'),
        'ChIJ_Y2' => legacy_ok('ChIJ_Y2', 'Never Two'),
    );
    $before = $GLOBALS['table'];
    $r = array('out' => $o->avalon_hours_sweep(0, false), 'before' => $before);
    break;

case 'sweep_badwrite':
    seed(array(
        'f00000000001' => row('ok', 'ChIJ_W1', 'pending', null),
        'f00000000002' => row('ok', 'ChIJ_W2', 'pending', null),
    ));
    $GLOBALS['byplace'] = array(
        'ChIJ_W1' => legacy_ok('ChIJ_W1', 'One'),
        'ChIJ_W2' => legacy_ok('ChIJ_W2', 'Two'),
    );
    $GLOBALS['fail_update'] = true;
    $r = array('out' => $o->avalon_hours_sweep(0, false));
    break;

case 'sweep_badwrite_strike':
    seed(array(
        'f00000000001' => row('ok', 'ChIJ_W1', 'pending', null),
        'f00000000002' => row('ok', 'ChIJ_W2', 'pending', null),
    ));
    $GLOBALS['byplace'] = array(
        'ChIJ_W1' => legacy_status('ZERO_RESULTS'),
        'ChIJ_W2' => legacy_status('ZERO_RESULTS'),
    );
    $GLOBALS['fail_update'] = true;
    $r = array('out' => $o->avalon_hours_sweep(0, false));
    break;

case 'sweep_badquery':
    seed(queue_fixture());
    $GLOBALS['fallback'] = legacy_ok('ChIJ_ANY', 'Anybody');
    $wpdb->fail_select = true;
    $r = array('out' => $o->avalon_hours_sweep(0, false));
    break;

/* ---- the cron ------------------------------------------------ */
case 'cron_on':
    seed(queue_fixture());
    $GLOBALS['byplace'] = queue_answers();
    $GLOBALS['fallback'] = legacy_ok('ChIJ_UNEXPECTED', 'Should Never Be Asked');
    $o->avalon_hours_cron();
    $r = array('done' => 1);
    break;
case 'cron_ceiling':
    define('AVALON_HOURS_DETAILS_CEILING', 2);
    seed(queue_fixture());
    $GLOBALS['byplace'] = queue_answers();
    $o->avalon_hours_cron();
    $r = array('done' => 1);
    break;
case 'cron_off':
    define('AVALON_HOURS_ENABLED', false);
    seed(queue_fixture());
    $GLOBALS['byplace'] = queue_answers();
    $o->avalon_hours_cron();
    $r = array('done' => 1);
    break;
case 'cron_systemic':
    seed(queue_fixture());
    $GLOBALS['byplace'] = array('ChIJ_A01' => legacy_status('REQUEST_DENIED'));
    $GLOBALS['fallback'] = legacy_ok('ChIJ_NEVER', 'Never');
    $o->avalon_hours_cron();
    $r = array('done' => 1);
    break;

/* ---- the CLI ------------------------------------------------- */
case 'cli_bare':
    seed(queue_fixture());
    $o->avalon_hours_cli(array(), array());
    $r = array('done' => 1);
    break;
case 'cli_status_scheduled':
    seed(queue_fixture());
    $GLOBALS['scheduled'][] = array('ts' => 1790000000, 'rec' => 'daily',
                                    'hook' => 'avalon_hours_refresh', 'now' => time());
    $o->avalon_hours_cli(array('status'), array());
    $r = array('done' => 1);
    break;
case 'cli_unknown':
    seed(queue_fixture());
    $o->avalon_hours_cli(array('sweeep'), array());
    break;
case 'cli_dry':
    seed(queue_fixture());
    $GLOBALS['fallback'] = legacy_ok('ChIJ_ANY', 'Anybody');
    $before = $GLOBALS['table'];
    $o->avalon_hours_cli(array('sweep'), array('dry-run' => true));
    $r = array('before' => $before);
    break;
case 'cli_max':
    seed(queue_fixture());
    $GLOBALS['byplace'] = queue_answers();
    $o->avalon_hours_cli(array('sweep'), array('max-calls' => '2'));
    $r = array('done' => 1);
    break;
case 'cli_badquery':
    seed(queue_fixture());
    $GLOBALS['fallback'] = legacy_ok('ChIJ_ANY', 'Anybody');
    $wpdb->fail_select = true;
    $o->avalon_hours_cli(array('sweep'), array());
    break;
case 'cli_max_zero':
case 'cli_max_sci':
    seed(queue_fixture());
    $GLOBALS['fallback'] = legacy_ok('ChIJ_ANY', 'Anybody');
    $before = $GLOBALS['table'];
    $r = array('before' => $before);
    /* 0 would mean details_ceiling to the sweep; 1e3 casts to 1000. */
    $o->avalon_hours_cli(array('sweep'),
        array('max-calls' => ($scenario === 'cli_max_zero') ? '0' : '1e3'));
    break;
case 'cli_status_badquery':
    seed(queue_fixture());
    $wpdb->fail_select = true;
    $o->avalon_hours_cli(array('status'), array());
    break;
case 'cli_status_marked':
    $q = queue_fixture();
    $q['b391a6d50f59']['hours_status']    = 'blocked';
    $q['a00000000012']['business_status'] = 'CLOSED_PERMANENTLY';
    seed($q);
    $o->avalon_hours_cli(array('status'), array());
    $r = array('done' => 1);
    break;
case 'cli_fatal':
    seed(queue_fixture());
    $GLOBALS['byplace'] = array('ChIJ_A01' => legacy_status('REQUEST_DENIED', 'The provided API key is invalid.'));
    $o->avalon_hours_cli(array('sweep'), array());
    break;
case 'cli_disabled':
    define('AVALON_HOURS_ENABLED', false);
    seed(queue_fixture());
    $o->avalon_hours_cli(array('sweep'), array());
    break;
case 'cli_release_listed':
    seed(array('b391a6d50f59' => row('ok', 'ChIJ_L1', 'blocked', null)));
    $o->avalon_hours_cli(array('release'), array('key' => 'b391a6d50f59'));
    break;
case 'cli_release_ok':
    seed(array('a00000000099' => row('ok', 'ChIJ_H99', 'blocked', null)));
    $o->avalon_hours_cli(array('release'), array('key' => 'a00000000099'));
    $r = array('done' => 1);
    break;
case 'cli_release_none':
    seed(array('a00000000098' => row('ok', 'ChIJ_K98', 'ok', ago(5))));
    $o->avalon_hours_cli(array('release'), array('key' => 'a00000000098'));
    break;
case 'cli_release_badwrite':
    seed(array('a00000000099' => row('ok', 'ChIJ_H99', 'blocked', null)));
    $GLOBALS['fail_update'] = true;
    $o->avalon_hours_cli(array('release'), array('key' => 'a00000000099'));
    break;
case 'cli_release_nokey':
    seed(array('a00000000099' => row('ok', 'ChIJ_H99', 'blocked', null)));
    $o->avalon_hours_cli(array('release'), array());
    break;

/* ---- the purge ----------------------------------------------- */
case 'purge':
case 'purge_off':
    if ($scenario === 'purge_off') { define('AVALON_HOURS_ENABLED', false); }
    $full = array('hours_json' => '{"cached":1}', 'business_status' => 'OPERATIONAL',
                  'attribution_json' => '[]', 'primary_type_display' => 'Boat dealer',
                  'locality' => 'Somewhere', 'admin_area' => 'MI');
    seed(array(
        'p00000000001' => with(row('ok', 'ChIJ_P1', 'ok',      ago(31)), $full),
        'p00000000002' => with(row('ok', 'ChIJ_P2', 'ok',      ago(29)), $full),
        'p00000000003' => with(row('ok', 'ChIJ_P3', 'none',    ago(31)),
                               with($full, array('business_status' => 'CLOSED_PERMANENTLY'))),
        'p00000000004' => with(row('ok', 'ChIJ_P4', 'none',    ago(29)), $full),
        'p00000000005' => with(row('ok', 'ChIJ_P5', 'failed',  ago(8), 3), $full),
        'p00000000006' => with(row('ok', 'ChIJ_P6', 'failed',  ago(6), 3), $full),
        'p00000000007' => row('ok', 'ChIJ_P7', 'pending', null),
        'p00000000008' => row('ok', 'ChIJ_P8', 'blocked', null),
        /* s0.263: content held under a status the TTL sweeps never visit */
        'p00000000009' => with(row('ok', 'ChIJ_P9',  'blocked', ago(31)), $full),
        'p00000000010' => with(row('ok', 'ChIJ_P10', 'pending', null), $full),
        'p00000000011' => with(row('ok', 'ChIJ_P11', 'blocked', ago(5)), $full),
        'p00000000012' => row('ok', 'ChIJ_P12', 'pending', ago(40), 1, 'STATUS NOT_FOUND'),
        'p00000000013' => with(row('ok', 'ChIJ_P13', 'pending', ago(20)), $full),
        /* one column of content is still content */
        'p00000000014' => with(row('ok', 'ChIJ_P14', 'blocked', ago(40)),
                               array('business_status' => 'CLOSED_PERMANENTLY')),
        'p00000000015' => with(row('ok', 'ChIJ_P15', 'pending', ago(40)),
                               array('locality' => 'Somewhere')),
    ));
    $before = $GLOBALS['table'];
    $n = $o->avalon_places_purge();
    $r = array('n' => $n, 'before' => $before);
    break;
}
} catch (SLP_CLI_Halt $e) {
    $halt = $e->getMessage();
} catch (Throwable $t) {
    $r = array('crash' => get_class($t) . ': ' . $t->getMessage());
}

echo json_encode(array(
    'r'           => $r,
    'halt'        => $halt,
    'table'       => $GLOBALS['table'],
    'sql'         => $GLOBALS['sql'],
    'unparsed'    => $GLOBALS['unparsed'],
    'logged'      => $GLOBALS['logged'],
    'http'        => $GLOBALS['http'],
    'options'     => $GLOBALS['options'],
    'hooks'       => $GLOBALS['hooks'],
    'cli'         => $GLOBALS['cli'],
    'scheduled'   => $GLOBALS['scheduled'],
    'clock_calls' => $GLOBALS['clock_calls'],
    'clock_base'  => $GLOBALS['clock_base'],
    'wrong'       => $GLOBALS['doing_it_wrong'],
));
PHPRUN;

$hfile = $tmp . DIRECTORY_SEPARATOR . 'harness.php';
file_put_contents($hfile, $harness);

$CRASHES = array();
$WRONG   = array();

function run($hfile, $scenario)
{
    $err = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'slp31err_' . getmypid() . '_' . $scenario;
    $out = array();
    $rc  = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -d short_open_tag=1 '
         . escapeshellarg($hfile) . ' ' . escapeshellarg($scenario)
         . ' 2>' . escapeshellarg($err), $out, $rc);
    $raw = implode("\n", $out);
    $j   = json_decode($raw, true);
    if (! is_array($j)) {
        fwrite(STDERR, "\n  SUITE BROKEN: scenario '{$scenario}' produced no JSON\n");
        fwrite(STDERR, "  stdout: " . substr($raw, 0, 400) . "\n");
        fwrite(STDERR, "  stderr: " . substr((string) @file_get_contents($err), 0, 800) . "\n");
        exit(2);
    }
    @unlink($err);
    foreach (($j['wrong'] ?? array()) as $w) {
        $GLOBALS['WRONG'][] = $scenario . ' - ' . $w;
        printf("    [note] scenario %s: %s\n", $scenario, $w);
    }
    if (isset($j['r']['crash'])) {
        $GLOBALS['CRASHES'][] = $scenario . ' - ' . $j['r']['crash'];
        printf("    [note] scenario %s crashed: %s\n", $scenario, $j['r']['crash']);
    }
    return $j;
}

function sqlText($s) { return implode("\n", $s['sql'] ?? array()); }
function updates($s) {
    $n = 0;
    foreach (($s['sql'] ?? array()) as $q) {
        if (stripos(ltrim($q), 'UPDATE') === 0) { $n++; }
    }
    return $n;
}
function asked($s) {
    $out = array();
    foreach (($s['http'] ?? array()) as $h) {
        if (preg_match('/[?&]place_id=([^&]*)/', $h['url'], $m)) { $out[] = $m[1]; }
    }
    return $out;
}
function payload($row) {
    $j = json_decode((string) ($row['hours_json'] ?? ''), true);
    return is_array($j) ? $j : array();
}
function cliText($s) {
    $t = '';
    foreach (($s['cli'] ?? array()) as $l) { $t .= $l[0] . ': ' . $l[1] . "\n"; }
    return $t;
}
function hook($s, $type, $name, $cb) {
    foreach (($s['hooks'] ?? array()) as $h) {
        if ($h[0] === $type && $h[1] === $name && $h[2] === $cb) { return $h; }
    }
    return null;
}
function commands($s) {
    $n = 0;
    foreach (($s['hooks'] ?? array()) as $h) { if ($h[0] === 'command') { $n++; } }
    return $n;
}
function placesLog($s) {
    $log = $s['options']['avalon_places_log'] ?? array();
    return is_array($log) ? $log : array();
}
function rotated($s) {
    foreach (($s['logged'] ?? array()) as $l) { if (($l[0] ?? '') === 'ROTATED') { return true; } }
    return false;
}
function same($a, $b) { return $a === $b; }
/* Index of the first UPDATE / SELECT a scenario issued, -1 if none. */
function firstUpdate($s) {
    foreach (($s['sql'] ?? array()) as $i => $q) { if (stripos(ltrim($q), 'UPDATE') === 0) { return $i; } }
    return -1;
}
function firstSelect($s) {
    foreach (($s['sql'] ?? array()) as $i => $q) { if (stripos(ltrim($q), 'SELECT') === 0) { return $i; } }
    return PHP_INT_MAX;
}
/* The column is there and it is NULL. Never `?? 'x'` - a coalesce reads a
   NULL as missing, and the check could not pass on any build. */
function isNul($t, $k, $c)
{
    return is_array($t) && isset($t[$k]) && is_array($t[$k])
        && array_key_exists($c, $t[$k]) && $t[$k][$c] === null;
}

/* ------------------------------------------------------------------ */
/* Assertions.                                                         */
/* ------------------------------------------------------------------ */

echo "\n  suite-v031  -  slp_avalon v0.0.27 Part 3b\n";
echo "  artefact  {$clsPath}\n";
printf("  md5 %s  %d bytes\n", md5($code), strlen($code));

$LISTED = array('b391a6d50f59', 'e0487241edc4', '88cd11dcda38', 'aae51e47328a',
                'bd1658e3580d', '369e2ae4210a', 'c0ebc2093268',
                '3664a8c7ba9e', '670217f109e9');

echo "\n  THE FILE, REGION BY REGION\n";
$LABELS = array(
    'H'  => 'H, the head',
    'A1' => 'A1, instance() to add_actions()',
    'A2' => 'A2, register_shortcodes() to the hours table - territory gate, import guard,'
          . ' reconcile, redirects',
    'T'  => 'T, the hours table, install and schema gate',
    'F'  => 'F, the config and the Part 2 fetch layer through the verdict map -'
          . ' suite-v030\'s 109 carry forward',
    'B1' => 'B1, REST strip, places schedule gate and seed',
    'B2' => 'B2, the Part 3d resolver, import and places CLI - suite-v029\'s 107 carry forward',
);
foreach ($PINNED_REGIONS as $k => $pin) {
    check(md5($regions[$k]) === $pin[0] && strlen($regions[$k]) === $pin[1],
        $LABELS[$k] . ', is byte-identical to v0.0.27-part3a');
}
check(substr_count($code, "\r\n") === substr_count($code, "\n")
      && substr_count($code, "\r\n") === substr_count($code, "\r"),
    'the file is pure CRLF - no bare LF, no bare CR (s0.216)');

/* C and WIRE were ADDED to, not edited. Take the Part 3b insertion out and
   what is left must be Part 3a's bytes exactly. */
$C_INSERT = "\r\n        /**\r\n         * v0.0.27 Part 3b. The hours cron.";
$ci = strpos($regions['C'], $C_INSERT);
$cj = ($ci === false) ? false
    : strpos($regions['C'], "        const HOURS_REFRESH_MARGIN_DAYS = 2;\r\n", $ci);
$cRest = ($ci === false || $cj === false) ? ''
    : substr($regions['C'], 0, $ci)
      . substr($regions['C'], $cj + strlen("        const HOURS_REFRESH_MARGIN_DAYS = 2;\r\n"));
check(md5($cRest) === $PART3A_C[0] && strlen($cRest) === $PART3A_C[1],
    'C is Part 3a\'s constants plus the Part 3b block, and nothing else moved');
$W_INSERT = "            //\r\n            // v0.0.27 Part 3b. The hours sweep: its own schedule gate on\r\n"
          . "            // init priority 1 and its own hook, registered unconditionally,\r\n"
          . "            // for the reasons given for the places pair above.\r\n"
          . "            add_action('init', array(self::\$instance,'avalon_hours_maybe_schedule'), 1);\r\n"
          . "            add_action(self::HOURS_CRON_HOOK, array(self::\$instance,'avalon_hours_cron'));\r\n";
$W_CMD    = "                WP_CLI::add_command( 'avalon hours', array(self::\$instance,'avalon_hours_cli') );\r\n";
$wRest = (substr_count($regions['WIRE'], $W_INSERT) === 1 && substr_count($regions['WIRE'], $W_CMD) === 1)
    ? str_replace(array($W_INSERT, $W_CMD), '', $regions['WIRE']) : '';
check(md5($wRest) === $PART3A_WIRE[0] && strlen($wRest) === $PART3A_WIRE[1],
    'add_actions() is Part 3a\'s plus the three Part 3b registrations, and nothing else moved');

echo "\n  THE LIFT\n";
$dl = run($hfile, 'decl');
$allThere = count($dl['r']['methods'] ?? array()) === 9;
foreach (($dl['r']['methods'] ?? array()) as $mm => $there) { if ($there !== true) { $allThere = false; } }
check($allThere, 'the sweep, the seven Part 3b methods and the purge are defined inside the harness');

echo "\n  CONSTANTS\n";
$K = $dl['r']['consts'] ?? array();
check(($K['HOURS_CRON_HOOK'] ?? null) === 'avalon_hours_refresh'
      && ($K['HOURS_CRON_HOOK'] ?? '') !== ($K['PLACES_CRON_HOOK'] ?? ''),
    'HOURS_CRON_HOOK is avalon_hours_refresh - its own hook, not the places one');
check(($K['HOURS_REFRESH_MARGIN_DAYS'] ?? null) === 2, 's0.262 - the refresh margin is 2 days');
$EXPECT_CONSTS = array(
    'HOURS_DB_VERSION' => '2', 'HOURS_DB_OPTION' => 'avalon_hours_db_version',
    'PLACES_CRON_HOOK' => 'avalon_places_resolve', 'PLACES_ERROR_CEILING' => 3,
    'PLACES_STATUS_PENDING' => 'pending', 'PLACES_STATUS_OK' => 'ok',
    'PLACES_STATUS_FAILED' => 'failed',
    'PLACES_ENDPOINT' => 'https://maps.googleapis.com/maps/api/place/textsearch/json',
    'PLACES_BIAS_RADIUS_M' => 50000, 'PLACES_LOG_OPTION' => 'avalon_places_log',
    'PLACES_LOG_MAX' => 50, 'HOURS_ERROR_CEILING' => 3,
    'HOURS_STATUS_PENDING' => 'pending', 'HOURS_STATUS_OK' => 'ok',
    'HOURS_STATUS_NONE' => 'none', 'HOURS_STATUS_BLOCKED' => 'blocked',
    'HOURS_STATUS_FAILED' => 'failed',
    'HOURS_CRON_HOOK' => 'avalon_hours_refresh', 'HOURS_REFRESH_MARGIN_DAYS' => 2,
    'HOURS_ENDPOINT_LEGACY' => 'https://maps.googleapis.com/maps/api/place/details/json',
    'HOURS_ENDPOINT_NEW' => 'https://places.googleapis.com/v1/places/',
    'HOURS_FIELDS_LEGACY' => 'place_id,name,business_status,opening_hours,utc_offset',
    'HOURS_FIELDS_NEW' => 'id,name,displayName,businessStatus,regularOpeningHours,'
                        . 'utcOffsetMinutes,primaryTypeDisplayName,attributions',
);
ksort($K); ksort($EXPECT_CONSTS);
check($K === $EXPECT_CONSTS, 'the class declares exactly these 23 constants with exactly these values');

echo "\n  WIRING\n";
$wc = run($hfile, 'wire_cli');
$h1 = hook($wc, 'action', 'init', 'SLP_Avalon::avalon_hours_maybe_schedule');
check($h1 !== null && $h1[3] === 1 && $h1[5] === true,
    'the hours schedule gate is on init priority 1, on the plugin instance');
$h2 = hook($wc, 'action', 'avalon_hours_refresh', 'SLP_Avalon::avalon_hours_cron');
check($h2 !== null && $h2[5] === true,
    'avalon_hours_cron() listens on HOURS_CRON_HOOK - an event with no listener runs nothing');
check(hook($wc, 'action', 'init', 'SLP_Avalon::avalon_places_maybe_schedule') !== null
      && hook($wc, 'action', 'avalon_places_resolve', 'SLP_Avalon::avalon_places_cron') !== null,
    'the places pair is still registered');
$c1 = hook($wc, 'command', 'avalon hours', 'SLP_Avalon::avalon_hours_cli');
check($c1 !== null && $c1[5] === true
      && hook($wc, 'command', 'avalon places', 'SLP_Avalon::avalon_places_cli') !== null
      && commands($wc) === 2,
    'under WP-CLI, wp avalon hours and wp avalon places are both registered, and nothing else');
$wn = run($hfile, 'wire_nocli');
check(commands($wn) === 0 && hook($wn, 'action', 'avalon_hours_refresh', 'SLP_Avalon::avalon_hours_cron') !== null,
    'outside WP-CLI no command is registered, and the cron still is');

echo "\n  SCHEDULE\n";
$se = run($hfile, 'sched_empty');
$ev = $se['scheduled'][0] ?? array();
check(count($se['scheduled'] ?? array()) === 1 && ($ev['hook'] ?? '') === 'avalon_hours_refresh'
      && ($ev['rec'] ?? '') === 'daily',
    'with nothing scheduled, the gate schedules HOURS_CRON_HOOK daily, once');
check(isset($ev['ts'], $ev['now'], $se['r']['t0'])
      && $ev['ts'] >= $se['r']['t0'] + 3600 && $ev['ts'] <= $ev['now'] + 3600,
    'the first run is an hour out - a deploy cannot sweep inside the request that installed it');
$sa = run($hfile, 'sched_again');
check(count($sa['scheduled'] ?? array()) === 1, 'with the event already scheduled, the gate adds nothing');
$si = run($hfile, 'sched_installing');
check(count($si['scheduled'] ?? array(1)) === 0, 'during WP_INSTALLING the gate does nothing');

echo "\n  THE BLOCK LIST\n";
$D = $dl['r']['disputed'] ?? array();
$dk = array_keys($D); sort($dk); $lk = $LISTED; sort($lk);
check($dk === $lk,
    'the list is exactly the nine keys decided 2026-09-29: seven proven wrong, two unchecked');
$why = true;
foreach ($D as $k => $v) { if (! is_string($v) || strlen(trim($v)) < 10) { $why = false; } }
check($why && count($D) === 9, 'every listed key carries its reason');
check(! array_key_exists('0021b0d78410', $D),
    '0021b0d78410 is NOT listed - fetched 2026-09-29 and it is the right place');

$sy = run($hfile, 'sync');
$Y  = $sy['table'] ?? array();
$cleared = function ($row) {
    return ($row['hours_status'] ?? '') === 'blocked'
        && array_key_exists('hours_json', $row) && $row['hours_json'] === null
        && array_key_exists('business_status', $row) && $row['business_status'] === null
        && array_key_exists('fetched_at', $row) && $row['fetched_at'] === null
        && ($row['last_error'] ?? '') === 'DISPUTED place id';
};
check($cleared($Y['b391a6d50f59'] ?? array()) && $cleared($Y['e0487241edc4'] ?? array()),
    'sync marks a listed row blocked and clears what was cached for it');
check(isNul($Y, 'e0487241edc4', 'attribution_json')
      && ($Y['e0487241edc4']['place_id'] ?? '') === 'ChIJ_L2',
    'the attribution goes with the payload; the place id stays - Part 3e needs it');
check((int) ($Y['e0487241edc4']['error_count'] ?? -1) === 0
      && ($Y['e0487241edc4']['updated_at'] ?? '') === '2026-09-29 12:00:00',
    'a blocked row starts clean - no strikes carried, stamped with the run\'s time');
check($cleared($Y['aae51e47328a'] ?? array()),
    'a row blocked by hand with its payload still in place is cleared too');
check(($Y['88cd11dcda38'] ?? null) === ($sy['r']['before']['88cd11dcda38'] ?? 'x')
      && ($sy['r']['n'] ?? -1) === 3,
    'a listed row already blocked and empty is not rewritten; three rows changed');
check(($Y['a00000000099'] ?? null) === ($sy['r']['before']['a00000000099'] ?? 'x')
      && ($Y['a00000000098'] ?? null) === ($sy['r']['before']['a00000000098'] ?? 'x'),
    'sync is one-way: an unlisted block stays, an unlisted row is untouched');

$rl = run($hfile, 'release');
$R  = $rl['table'] ?? array();
check(is_string($rl['r']['listed'] ?? null) && strpos($rl['r']['listed'], 'still listed') === 0
      && ($R['b391a6d50f59']['hours_status'] ?? '') === 'blocked',
    'release refuses a key that is still listed - the next run would undo it');
check(is_string($rl['r']['bad'] ?? null) && strpos($rl['r']['bad'], 'not an address key') === 0,
    'release refuses something that is not an address key');
check(($rl['r']['ok'] ?? null) === 1 && ($R['a00000000099']['hours_status'] ?? '') === 'pending'
      && isNul($R, 'a00000000099', 'last_error'),
    'release returns an unlisted blocked key to pending, case and spaces forgiven');
check((int) ($R['a00000000099']['error_count'] ?? -1) === 0,
    'a released row starts with no strikes - three old ones would fail it on its first miss');
check(($rl['r']['notheld'] ?? null) === 0
      && ($R['a00000000098'] ?? null) === ($rl['r']['before']['a00000000098'] ?? 'x'),
    'release touches nothing that is not blocked');

echo "\n  SWEEP - THE QUEUE\n";
$QUEUE_KEYS = array('a00000000001', 'a00000000012', 'a00000000013', 'a00000000004',
                    'a00000000002', 'a00000000018', 'a00000000006', 'a00000000016');
$QUEUE_IDS  = array('ChIJ_A01', 'ChIJ_A12', 'ChIJ_A13', 'ChIJ_A04', 'ChIJ_A02', 'ChIJ_A18',
                    'ChIJ_A06', 'ChIJ_A16');
$dr = run($hfile, 'sweep_dry');
check(($dr['r']['out']['due'] ?? null) === $QUEUE_KEYS,
    'a dry run lists the eight due rows in queue order');
check(! in_array('b391a6d50f59', $dr['r']['out']['due'] ?? array('b391a6d50f59'), true)
      && ! in_array('3664a8c7ba9e', $dr['r']['out']['due'] ?? array('3664a8c7ba9e'), true),
    'a listed key is excluded by the queue itself, before anything has marked it blocked');
check(in_array('a00000000002', $dr['r']['out']['due'] ?? array(), true)
      && in_array('a00000000018', $dr['r']['out']['due'] ?? array(), true)
      && ! in_array('a00000000003', $dr['r']['out']['due'] ?? array('a00000000003'), true)
      && ! in_array('a00000000005', $dr['r']['out']['due'] ?? array('a00000000005'), true),
    's0.262 - ok and none are due two days before the 30-day cap, not at it');
check(count($dr['http'] ?? array(1)) === 0 && updates($dr) === 0
      && ($dr['table'] ?? null) === ($dr['r']['before'] ?? 'x'),
    'a dry run spends nothing and writes nothing');
check(strpos(sqlText($dr), "hours_status <> 'blocked'") !== false
      && strpos(sqlText($dr), "address_key NOT IN ('b391a6d50f59'") !== false,
    'the finished SELECT names both exclusions');
check(empty($dr['unparsed']), 'the queue SELECT was read and executed, not skipped');

echo "\n  SWEEP - A REAL RUN\n";
$rn  = run($hfile, 'sweep_run');
$T   = $rn['table'] ?? array();
$out = $rn['r']['out'] ?? array();
check(asked($rn) === $QUEUE_IDS, 'rows are asked in queue order');
check(! in_array('ChIJ_LISTED_1', asked($rn), true) && ! in_array('ChIJ_LISTED_2', asked($rn), true),
    'no listed place id is ever asked');
check(($out['scanned'] ?? 0) === 8 && ($out['ok'] ?? 0) === 7 && ($out['none'] ?? 0) === 1
      && ($out['struck'] ?? 1) === 0 && ($out['raced'] ?? 1) === 0 && ($out['errors'] ?? null) === array(),
    'counts: eight scanned, seven ok, one none, nothing struck or raced');
$written = array();
foreach ($T as $k => $row) { if ($row !== ($rn['r']['before'][$k] ?? null)) { $written[] = $k; } }
sort($written); $qk = $QUEUE_KEYS; sort($qk);
check($written === $qk, 'exactly the eight due rows were written, and nothing else');
$stamps = array();
foreach ($written as $k) {
    $stamps[] = $T[$k]['fetched_at'] ?? null;
    $stamps[] = payload($T[$k])['at'] ?? null;
}
$st0 = (count($stamps) > 0 && $stamps[0] !== null) ? strtotime($stamps[0] . ' UTC') : false;
$cb  = (int) ($rn['clock_base'] ?? 0);
check(count(array_unique($stamps)) === 1 && ($rn['clock_calls'] ?? 0) === 1
      && $st0 !== false && $st0 > $cb && $st0 <= $cb + 1,
    '$now is read once, in GMT');
check(isNul($T, 'a00000000002', 'business_status')
      && array_key_exists('business_status', $T['a00000000002'] ?? array()),
    "s0.259 - Google sent no business_status and the column is NULL, not ''");
check(($T['a00000000001']['business_status'] ?? '') === 'OPERATIONAL'
      && ($T['a00000000012']['business_status'] ?? '') === 'CLOSED_PERMANENTLY'
      && ($T['a00000000012']['hours_status'] ?? '') === 'none',
    'a value that is there is written as it is; a closed place with no hours is none');
check(($T['3664a8c7ba9e'] ?? null) === ($rn['r']['before']['3664a8c7ba9e'] ?? 'x'),
    'the sweep itself never writes a listed row - clearing it is sync\'s job, not the queue\'s');
check(empty($rn['unparsed']) && updates($rn) === 8, 'one UPDATE per row asked, every one applied');

echo "\n  SWEEP - THE RACE  (s0.258)\n";
$rc = run($hfile, 'sweep_race');
$X  = $rc['table'] ?? array();
check(($X['c00000000001']['hours_status'] ?? '') === 'blocked'
      && isNul($X, 'c00000000001', 'hours_json'),
    'a row blocked between the read and the write is not overwritten');
check(($X['c00000000002']['place_id'] ?? '') === 'ChIJ_R2_CORRECTED'
      && isNul($X, 'c00000000002', 'hours_json')
      && ($X['c00000000002']['hours_status'] ?? '') === 'pending',
    'a row re-pointed between the read and the write does not get the old place\'s hours');
check(($X['c00000000004']['hours_status'] ?? '') === 'blocked'
      && (int) ($X['c00000000004']['error_count'] ?? 0) === 1,
    'a strike on a row blocked underfoot is refused too - no count, no stamp');
check(($X['c00000000005']['place_id'] ?? '') === 'ChIJ_R5_CORRECTED'
      && (int) ($X['c00000000005']['error_count'] ?? 0) === 1
      && isNul($X, 'c00000000005', 'fetched_at') && isNul($X, 'c00000000005', 'last_error'),
    'a strike for a place the row no longer holds is refused - the corrected id starts clean');
check(($X['c00000000003']['hours_status'] ?? '') === 'ok'
      && ($rc['r']['out']['raced'] ?? 0) === 4 && ($rc['r']['out']['ok'] ?? 0) === 1
      && ($rc['r']['out']['struck'] ?? 1) === 0,
    'the untouched row is written; four raced, one ok, none struck');
check(asked($rc) === array('ChIJ_R1', 'ChIJ_R2', 'ChIJ_R3', 'ChIJ_R4', 'ChIJ_R5'),
    'a raced row is skipped, never a reason to stop: all five were asked, in order');

echo "\n  SWEEP - WHAT COUNTS AS HOURS  (s0.260)\n";
$ln = run($hfile, 'sweep_lines');
$L  = $ln['table'] ?? array();
check(($L['d00000000001']['hours_status'] ?? '') === 'none',
    'hours carrying only openNow are none - there are no lines to show');
check(($L['d00000000002']['hours_status'] ?? '') === 'ok', 'weekday lines are ok');
check(($L['d00000000003']['hours_status'] ?? '') === 'none', 'empty hours are none');
check(($L['d00000000005']['hours_status'] ?? '') === 'none',
    'periods without weekday lines are none - the renderer prints lines, not periods');
check(($L['d00000000004']['hours_status'] ?? '') === 'none'
      && array_key_exists('business_status', $L['d00000000004'] ?? array())
      && $L['d00000000004']['business_status'] === null,
    "no hours and no business_status: none, and NULL, not ''");

echo "\n  SWEEP - STRIKES, STOPS AND FAILED WRITES\n";
$sk = run($hfile, 'sweep_strike');
$S  = $sk['table'] ?? array();
check((int) ($S['e00000000001']['error_count'] ?? 0) === 1
      && ($S['e00000000001']['hours_status'] ?? '') === 'pending'
      && ($S['e00000000001']['last_error'] ?? '') === 'STATUS ZERO_RESULTS',
    'a data negative still takes one strike through the guarded write');
check((int) ($S['e00000000002']['error_count'] ?? 0) === 3
      && ($S['e00000000002']['hours_status'] ?? '') === 'failed',
    'the third strike still fails the row');
check(($S['e00000000003']['hours_status'] ?? '') === 'ok'
      && ($sk['r']['out']['struck'] ?? 0) === 3 && ($sk['r']['out']['failed'] ?? 0) === 1,
    'strikes do not stop the sweep; three struck, one failed');
$wiped = true;
foreach (array('hours_json', 'business_status', 'attribution_json', 'primary_type_display',
               'locality', 'admin_area') as $c) {
    if (! isNul($S, 'e00000000004', $c)) { $wiped = false; }
}
check($wiped && ($S['e00000000004']['hours_status'] ?? '') === 'pending'
      && (int) ($S['e00000000004']['error_count'] ?? 0) === 1
      && ($S['e00000000004']['place_id'] ?? '') === 'ChIJ_S4',
    's0.263 - a strike on a row holding a Place clears the Place; the place id stays');
$ss = run($hfile, 'sweep_systemic');
check(count($ss['http'] ?? array()) === 1 && updates($ss) === 0
      && ($ss['table'] ?? null) === ($ss['r']['before'] ?? 'x')
      && ($ss['r']['out']['systemic'] ?? 0) === 1,
    'REQUEST_DENIED still stops the sweep at one call and writes nothing');
$bw = run($hfile, 'sweep_badwrite');
check(count($bw['http'] ?? array()) === 1 && ($bw['r']['out']['errors'] ?? null) === array('BADWRITE')
      && ($bw['r']['out']['systemic'] ?? 0) === 1 && ($bw['r']['out']['raced'] ?? 1) === 0,
    'a write the database refuses is BADWRITE, systemic, and stops - not a race');
$bs = run($hfile, 'sweep_badwrite_strike');
check(count($bs['http'] ?? array()) === 1 && ($bs['r']['out']['errors'] ?? null) === array('BADWRITE')
      && ($bs['r']['out']['raced'] ?? 1) === 0 && ($bs['r']['out']['struck'] ?? 1) === 0,
    'a strike the database refuses is BADWRITE too, and stops');
$bq = run($hfile, 'sweep_badquery');
check(($bq['r']['out']['errors'] ?? null) === array('BADQUERY') && count($bq['http'] ?? array(1)) === 0
      && ($bq['r']['out']['scanned'] ?? 1) === 0,
    's0.264 - a queue read the database refused is BADQUERY, not an empty queue');

echo "\n  THE CRON\n";
$co = run($hfile, 'cron_on');
$CO = $co['table'] ?? array();
check(($CO['3664a8c7ba9e']['hours_status'] ?? '') === 'blocked'
      && isNul($CO, '3664a8c7ba9e', 'hours_json')
      && ($CO['b391a6d50f59']['hours_status'] ?? '') === 'blocked',
    'the cron blocks the listed keys and clears the stale payload');
check(asked($co) === $QUEUE_IDS, 'then sweeps the queue in order');
$pl = placesLog($co);
$last = end($pl);
check(is_array($last) && ($last['stage'] ?? '') === 'hours_sweep' && ($last['ok'] ?? -1) === 7
      && ($last['blocked'] ?? -1) === 2,
    'and records the run - two blocked, seven ok - in the places log');
check(firstUpdate($co) < firstSelect($co) && firstUpdate($co) >= 0,
    'the blocks are written before the queue is read');
check(! rotated($co), 's0.233 - the cron never rotates the CSV import override log');
$cc = run($hfile, 'cron_ceiling');
check(count($cc['http'] ?? array()) === 2, 'the cron sweeps at details_ceiling');
$cf = run($hfile, 'cron_off');
check(count($cf['http'] ?? array(1)) === 0 && isNul($cf['table'], '3664a8c7ba9e', 'hours_json')
      && ($cf['table']['3664a8c7ba9e']['hours_status'] ?? '') === 'blocked',
    'switched off, the cron spends nothing - and still clears the disputed payload');
$cs = run($hfile, 'cron_systemic');
check(count($cs['http'] ?? array()) === 1
      && isNul($cs['table'], '3664a8c7ba9e', 'hours_json'),
    'a refused key stops the sweep; the blocks were already applied before it');

echo "\n  THE CLI\n";
$cb1 = run($hfile, 'cli_bare');
check(strpos(sqlText($cb1), 'GROUP BY hours_status') !== false
      && strpos(cliText($cb1), 'log: pending ') !== false && ($cb1['halt'] ?? 'x') === '',
    'a bare wp avalon hours means status');
check(strpos(cliText($cb1), 'disputed listed 9  in this table 2  marked blocked 0') !== false,
    'status compares the list to the table - two listed rows present, none marked yet');
$cbm = run($hfile, 'cli_status_marked');
check(strpos(cliText($cbm), 'disputed listed 9  in this table 2  marked blocked 1') !== false
      && strpos(cliText($cbm), "log: CLOSED_PERMANENTLY 1  (never rendered)\n") !== false
      && strpos(cliText($cbm), 'OPERATIONAL') === false,
    'status counts the marked rows, and lists closed dealers - not operational ones');
check(strpos(cliText($cb1), 'next sweep NOT SCHEDULED') !== false,
    'status says so when nothing is scheduled');
$csb = run($hfile, 'cli_status_badquery');
check(strpos($csb['halt'] ?? '', 'status could not read the table') === 0
      && strpos(cliText($csb), 'queue empty') === false,
    's0.264 - status does not report a refused read as an empty queue');
$cb2 = run($hfile, 'cli_status_scheduled');
check(strpos(cliText($cb2), 'next sweep ' . gmdate('Y-m-d H:i:s', 1790000000) . ' UTC') !== false,
    'and names the next run when there is one');
$cu = run($hfile, 'cli_unknown');
check(strpos($cu['halt'] ?? '', 'unknown subcommand "sweeep"') === 0
      && strpos($cu['halt'] ?? '', 'sweep, release, status') !== false
      && strpos(sqlText($cu), 'GROUP BY') === false,
    's0.232 - an unknown subcommand errors, lists the valid ones, and prints no status table');
$cd = run($hfile, 'cli_dry');
check(count($cd['http'] ?? array(1)) === 0 && updates($cd) === 0
      && ($cd['table'] ?? null) === ($cd['r']['before'] ?? 'x'),
    'sweep --dry-run spends nothing and writes nothing - not even the blocks');
check(strpos(cliText($cd), "log:   a00000000001\n") !== false
      && strpos(cliText($cd), 'success: dry run, nothing spent and nothing written') !== false,
    'it lists what is due and says it spent nothing');
$cm = run($hfile, 'cli_max');
check(count($cm['http'] ?? array()) === 2
      && ($cm['table']['3664a8c7ba9e']['hours_status'] ?? '') === 'blocked'
      && firstUpdate($cm) >= 0 && firstUpdate($cm) < firstSelect($cm)
      && strpos(cliText($cm), 'success: sweep complete') !== false,
    'sweep --max-calls=2 caps the pass, applies the blocks first, and succeeds');
$mz = run($hfile, 'cli_max_zero');
check(strpos($mz['halt'] ?? '', '--max-calls must be') === 0 && count($mz['http'] ?? array(1)) === 0
      && updates($mz) === 0 && ($mz['table'] ?? null) === ($mz['r']['before'] ?? 'x'),
    's0.265 - --max-calls=0 is refused before anything is spent or written');
$ms = run($hfile, 'cli_max_sci');
check(strpos($ms['halt'] ?? '', '--max-calls must be') === 0 && count($ms['http'] ?? array(1)) === 0
      && updates($ms) === 0,
    's0.265 - --max-calls=1e3 is refused too; PHP would read it as 1000');
$cml = placesLog($cm);
$cmLast = end($cml);
check(is_array($cmLast) && ($cmLast['stage'] ?? '') === 'hours_cli' && ! rotated($cm),
    'a real CLI sweep records itself in the places log, without rotating anything');
$ft = run($hfile, 'cli_fatal');
check(strpos($ft['halt'] ?? '', 'aborted on FATAL REQUEST_DENIED') === 0,
    's0.220 - a refused key exits through WP_CLI::error, not success');
$ftl = placesLog($ft);
$ftLast = end($ftl);
check(is_array($ftLast) && ($ftLast['stage'] ?? '') === 'hours_cli'
      && strpos((string) ($ftLast['errors'] ?? ''), 'FATAL REQUEST_DENIED') === 0,
    'and the failed run is recorded in the places log before it exits');
$bqc = run($hfile, 'cli_badquery');
check(strpos($bqc['halt'] ?? '', 'aborted on BADQUERY') === 0 && count($bqc['http'] ?? array(1)) === 0,
    's0.264 - a CLI sweep whose queue read failed exits non-zero');
$dz = run($hfile, 'cli_disabled');
check(strpos($dz['halt'] ?? '', 'aborted on DISABLED') === 0 && count($dz['http'] ?? array(1)) === 0,
    'switched off, a CLI sweep errors on DISABLED and spends nothing');
$c7 = run($hfile, 'cli_release_listed');
check(strpos($c7['halt'] ?? '', 'still listed') === 0
      && ($c7['table']['b391a6d50f59']['hours_status'] ?? '') === 'blocked',
    'release --key on a listed key is refused, and the row stays blocked');
$c8 = run($hfile, 'cli_release_ok');
check(($c8['table']['a00000000099']['hours_status'] ?? '') === 'pending'
      && strpos(cliText($c8), 'success: 1 key(s) returned to pending') !== false,
    'release --key on an unlisted blocked key returns it to the queue');
$cn = run($hfile, 'cli_release_none');
check(strpos($cn['halt'] ?? '', 'nothing released') === 0
      && ($cn['table']['a00000000098']['hours_status'] ?? '') === 'ok',
    's0.265 - release of a key with no blocked row is an error, not a success');
$crb = run($hfile, 'cli_release_badwrite');
check(strpos($crb['halt'] ?? '', 'the database refused the release') === 0,
    's0.265 - a refused release says the database refused it, not that nothing was blocked');
$c9 = run($hfile, 'cli_release_nokey');
check(strpos($c9['halt'] ?? '', '--key=') === 0
      && ($c9['table']['a00000000099']['hours_status'] ?? '') === 'blocked',
    'release without --key is an error, and changes nothing');

echo "\n  THE PURGE  (s0.257)\n";
$pg = run($hfile, 'purge');
$P  = $pg['table'] ?? array();
$gone = function ($row) {
    foreach (array('hours_json', 'business_status', 'attribution_json', 'primary_type_display',
                   'locality', 'admin_area', 'fetched_at') as $c) {
        if (! array_key_exists($c, $row) || $row[$c] !== null) { return false; }
    }
    return ($row['hours_status'] ?? '') === 'pending';
};
check($gone($P['p00000000001'] ?? array()),
    'an ok row past 30 days is retired: payload, business_status and the Place columns cleared');
check($gone($P['p00000000003'] ?? array()),
    's0.257 - a none row past 30 days is retired too, CLOSED_PERMANENTLY with it');
check($gone($P['p00000000005'] ?? array()), 'a failed row past 7 days is retired');
check(($P['p00000000002'] ?? null) === ($pg['r']['before']['p00000000002'] ?? 'x')
      && ($P['p00000000004'] ?? null) === ($pg['r']['before']['p00000000004'] ?? 'x')
      && ($P['p00000000006'] ?? null) === ($pg['r']['before']['p00000000006'] ?? 'x')
      && ($P['p00000000007'] ?? null) === ($pg['r']['before']['p00000000007'] ?? 'x')
      && ($P['p00000000008'] ?? null) === ($pg['r']['before']['p00000000008'] ?? 'x'),
    'rows inside their TTL, pending and blocked rows are untouched');
check(($P['p00000000001']['place_id'] ?? '') === 'ChIJ_P1'
      && ($P['p00000000001']['place_status'] ?? '') === 'ok'
      && ($P['p00000000001']['place_checked_at'] ?? '') === '2026-09-14 04:06:03',
    'place_id, place_status and place_checked_at are never touched - the one thing the terms let us keep');
$held = function ($row, $status) {
    foreach (array('hours_json', 'business_status', 'attribution_json', 'primary_type_display',
                   'locality', 'admin_area') as $c) {
        if (! array_key_exists($c, $row) || $row[$c] !== null) { return false; }
    }
    return ($row['hours_status'] ?? '') === $status;
};
check($held($P['p00000000009'] ?? array(), 'blocked')
      && ($P['p00000000009']['fetched_at'] ?? '') === ($pg['r']['before']['p00000000009']['fetched_at'] ?? 'x'),
    's0.263 - content under a hand block past 30 days is cleared, and the block stays');
check($held($P['p00000000010'] ?? array(), 'pending'),
    's0.263 - content under pending with no fetch stamp is of unknown age, and is cleared');
check(($P['p00000000011'] ?? null) === ($pg['r']['before']['p00000000011'] ?? 'x')
      && ($P['p00000000013'] ?? null) === ($pg['r']['before']['p00000000013'] ?? 'x')
      && ($P['p00000000012'] ?? null) === ($pg['r']['before']['p00000000012'] ?? 'x'),
    'content inside its 30 days - at 5 and at 20 - and a struck row holding nothing, are untouched');
check($held($P['p00000000014'] ?? array(), 'blocked') && $held($P['p00000000015'] ?? array(), 'pending'),
    's0.263 - one column of content is content: a lone CLOSED_* or locality is cleared too');
check(($pg['r']['n'] ?? 0) === 7 && empty($pg['unparsed']), 'the purge reports seven rows cleared');
$po = run($hfile, 'purge_off');
check($gone($po['table']['p00000000003'] ?? array()) && ($po['r']['n'] ?? 0) === 7,
    'switched off, the purge still runs - expiry is a licence obligation, not a feature');

echo "\n  HARNESS\n";
check(count($CRASHES) === 0,
    'no scenario crashed inside the lifted code' . (count($CRASHES) ? ' - ' . implode('; ', $CRASHES) : ''));
check(count($WRONG) === 0,
    'every prepare() had as many arguments as placeholders' . (count($WRONG) ? ' - ' . implode('; ', $WRONG) : ''));

@unlink($hfile);
@unlink($upgrade);
@rmdir($abs . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes');
@rmdir($abs . 'wp-admin');
@rmdir($abs);
@rmdir($tmp);

printf("\n  %d passed, %d failed, %d total\n\n", $pass, $fail, $pass + $fail);
exit($fail === 0 ? 0 : 1);
