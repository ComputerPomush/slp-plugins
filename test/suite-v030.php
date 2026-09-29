<?php
/**
 * suite-v030.php - validates slp_avalon v0.0.27 through Part 3a.
 *
 *   Part 1   business_status column, HOURS_DB_VERSION 1 -> 2
 *   Part 2   Place Details fetch layer, legacy and New, one shape
 *   Part 3a  s0.256 error-vocabulary fix, hours status constants,
 *            avalon_hours_sweep()
 *
 * THE WHOLE FILE IS ACCOUNTED FOR, REGION BY REGION
 *
 * v0.0.27 touched four places in the class and nothing else. Diffed
 * against v0.0.26-part3d on 2026-09-29: five hunks, all inside the
 * constants block, the CREATE TABLE, avalon_hours_config() and the new
 * methods before avalon_rest_protected_slugs(). So the file is cut at
 * six anchors into six regions:
 *
 *   H  head, to the first constants docblock        md5-pinned, unchanged
 *   C  the constants                                 asserted by value
 *   A  instance() .. avalon_hours_table()           md5-pinned, unchanged
 *   T  avalon_hours_table() .. avalon_hours_config() executed
 *   W  avalon_hours_config() .. rest_protected_slugs executed
 *   B  avalon_rest_protected_slugs() .. end of file  md5-pinned, unchanged
 *
 * H, A and B are pinned to the bytes of v0.0.26-part3d, the build the
 * unattended cron proved on 2026-09-15. B holds the whole Part 3d
 * resolver, so suite-v029's 107 assertions carry forward by identity
 * rather than by re-running them. One changed byte in H, A or B fails
 * this suite, and two of the controls exist to show that it does.
 *
 * WHAT IS EXECUTED, AND WHY IT IS EXECUTED RATHER THAN READ
 *
 * T and W are lifted out of the artefact verbatim and wrapped in a test
 * class, so self:: and $this-> resolve exactly as they do in production.
 * avalon_places_is_systemic() is lifted too, from B. It is the classifier
 * the sweep consults, and s0.256 was a disagreement between that
 * classifier and the strings Part 2 emitted. A double of it would let the
 * two disagree again with this suite still green.
 *
 * $wpdb IS A SMALL REAL SQL ENGINE, NOT A RECORDER. The queue SELECT has
 * nested AND/OR, IS NULL, IN, two TTL cutoffs, ORDER BY with an
 * expression and a LIMIT. It is tokenised, parsed and evaluated against
 * an in-memory table with MySQL's three-valued logic, NULLS FIRST on
 * ASC, and case-insensitive string comparison as utf8mb4_general_ci
 * does. UPDATEs are applied the same way. A recorder that answers "the
 * right rows" agrees with every build, including one whose WHERE clause
 * has been deleted. A statement the engine cannot read is recorded as
 * unparsed and asserted empty; it is never silently skipped.
 *
 * prepare() escapes the way mysqli_real_escape_string does and the lexer
 * unescapes the way MySQL does, so a payload with quotes and backslashes
 * round-trips only if the artefact actually binds it.
 *
 * It honours the output mode, because wpdb's default is OBJECT and code
 * that indexes an object row as an array dies in production. Full ties
 * come back in REVERSE insertion order, because MySQL leaves them
 * unordered and a fixture inserted in key order would otherwise hide a
 * dropped tie-breaker.
 *
 * THE ENGINE WAS CHECKED AGAINST A REAL SERVER, NOT ONLY AGAINST ITSELF.
 * On 2026-09-29 the finished queue SELECT from sweep_run's fixture was run
 * on MariaDB 10.11 against the same eighteen rows, inserted in reverse
 * order: same eight rows, same order. The finished UPDATE from
 * sweep_escape was run there too: the stored hours_json was
 * byte-identical to the engine's. The same ORDER BY picked 0021b0d78410
 * then 00a07bb631f1 on Aura DEV's real table that morning, exactly as
 * predicted before the run.
 *
 * AND IT WAS ATTACKED BY A REVIEWER WHO HAD NOT WRITTEN IT. 58 single-point
 * mutations of the v0.0.27 code, each byte-exact on a CRLF copy. The first
 * draft of this suite missed 38. This one misses 20, and every one of
 * those is equivalent under MySQL (IS NULL DESC dropped, place_id IS NOT
 * NULL dropped beside place_id <> ''), unreachable (a one-second TTL
 * boundary), already cut elsewhere (the second truncation of an error),
 * or cosmetic (a trim, a languageCode, an autoload flag).
 *
 * wpdb::prepare() HAS NO NULL. A null bound through %s is escaped as ''.
 * The double reproduces that, and the suite pins what production does
 * with it - see business_status below.
 *
 * THE CLOCK TICKS. current_time() returns a later second on every call.
 * $now is computed once per sweep by design, so every row a run writes
 * must carry one stamp. A build that reads the clock per row writes
 * different stamps, and that is how "computed once" becomes observable.
 * It also keeps the site four hours behind GMT, so a stamp taken without
 * current_time()'s GMT flag cannot pass for one taken with it.
 *
 * A CRASH IS A RESULT. Anything thrown inside the lifted code is caught,
 * recorded against its scenario, and fails the HARNESS assertion by name -
 * it never turns into a suite that could not run.
 *
 * THE FIXTURES. The legacy payload follows the shape measured on Aura DEV
 * on 2026-09-15 and 2026-09-29: seven weekday lines, one period per open
 * day and none for a closed day, utc_offset under the DEPRECATED key.
 * The day names and times are invented. The unicode is not decoration:
 * Google writes the times with U+202F and U+2009 around U+2013, and the
 * stored bytes must survive json_encode, prepare() and json_decode.
 *
 * ONE PROCESS PER SCENARIO. AVALON_HOURS_API, AVALON_HOURS_ENABLED and
 * the ceilings are constants, which PHP will not redefine, and no
 * scenario may inherit another's table.
 *
 * Usage:
 *   php suite-v030.php <class.slp_avalon.php>
 *
 * Exit 0 all passed, 1 any failed, 2 the suite could not run. A scenario
 * that produced no result is exit 2 - never a pass, never a fail.
 */

$clsPath = $argv[1] ?? 'build/out-v027p3a/class.slp_avalon.php';

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
/* The six anchors.                                                    */
/* ------------------------------------------------------------------ */

$ANCHORS = array(
    'C' => "        /**\r\n         * v0.0.26 Part 1. Schema version for the dealer-places table.",
    'A' => '        public static function instance(){',
    'T' => '        public static function avalon_hours_table(){',
    'W' => '        public function avalon_hours_config(){',
    'B' => '        public function avalon_rest_protected_slugs(){',
);

/* The bytes of v0.0.26-part3d, measured 2026-09-29 off the tagged file
   (c066b3f6227073fc0b939e7cdc0b81eb, 194,852 bytes) with these same
   anchors. Identical in 8bc1724a7e932fed0bfc7cb18e6cd39f. */
$PINNED_REGIONS = array(
    'H' => array('22c0b4f1a3d343c421fa6b2b3aaf4e8a',    104),
    'A' => array('2c5cfb441d88675c311d3330cf7c1a73', 119694),
    'B' => array('f128034e29a1c7be3b513b36bb1f01c2',  63483),
);

$at = array();
$anchorsOk = true;
foreach ($ANCHORS as $k => $needle) {
    if (substr_count($code, $needle) !== 1) {
        $anchorsOk = false;
        continue;
    }
    $at[$k] = strpos($code, $needle);
}
if ($anchorsOk) {
    $prev = -1;
    foreach (array('C', 'A', 'T', 'W', 'B') as $k) {
        if ($at[$k] <= $prev) { $anchorsOk = false; }
        $prev = $at[$k];
    }
}
if (! $anchorsOk) {
    /* Without the anchors nothing below can be lifted or pinned. That is
       a suite that cannot run, not a build that failed it. */
    fwrite(STDERR, "the six region anchors are not each present once, in order\n");
    exit(2);
}

$regions = array(
    'H' => substr($code, 0, $at['C']),
    'C' => substr($code, $at['C'], $at['A'] - $at['C']),
    'A' => substr($code, $at['A'], $at['T'] - $at['A']),
    'T' => substr($code, $at['T'], $at['W'] - $at['T']),
    'W' => substr($code, $at['W'], $at['B'] - $at['W']),
    'B' => substr($code, $at['B']),
);

/* ------------------------------------------------------------------ */
/* Lift the code under test out of the artefact, verbatim.            */
/* ------------------------------------------------------------------ */

function span($code, $from, $to)
{
    $a = strpos($code, $from);
    if ($a === false) { return false; }
    $b = strpos($code, $to, $a + 1);
    if ($b === false) { return false; }
    return substr($code, $a, $b - $a);
}

$consts = span($code, $ANCHORS['C'], $ANCHORS['A']);
$tableM = $regions['T'];
$hours  = $regions['W'];
$systemic = span($code,
    '        private function avalon_places_is_systemic( $err ){',
    "        /**\r\n         * v0.0.26 Part 3d. One legacy Text Search call.");

foreach (array('consts' => $consts, 'table' => $tableM, 'hours' => $hours,
               'systemic' => $systemic) as $n => $v) {
    if ($v === false || $v === '') {
        fwrite(STDERR, "cannot lift {$n} from {$clsPath}\n");
        exit(2);
    }
    /* An unterminated /** in a lift comments out everything after it,
       and the lift still looks found. Count the delimiters. */
    if (substr_count($v, '/*') !== substr_count($v, '*/')) {
        fwrite(STDERR, "lift of {$n} splits a comment: "
            . substr_count($v, '/*') . " open, "
            . substr_count($v, '*/') . " close\n");
        exit(2);
    }
}

/* ------------------------------------------------------------------ */
/* Harness.                                                            */
/* ------------------------------------------------------------------ */

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'slp30_' . getmypid();
$abs = $tmp . DIRECTORY_SEPARATOR . 'wp' . DIRECTORY_SEPARATOR;
@mkdir($abs . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes', 0777, true);

/* avalon_hours_install() does require_once ABSPATH .
   'wp-admin/includes/upgrade.php' and then calls dbDelta(). This is the
   file it finds. It records the statement and returns what the scenario
   says dbDelta would have changed. */
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
        'UPDATE', 'SET', 'AND', 'OR', 'NOT', 'IS', 'IN', 'NULL', 'ASC', 'DESC');

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

    private function statement() {
        if ($this->isKw('SELECT')) {
            $this->kw('SELECT');
            $cols = array();
            if ($this->isP('*')) { $this->punct('*'); $cols = '*'; }
            else {
                $cols[] = $this->ident();
                while ($this->isP(',')) { $this->punct(','); $cols[] = $this->ident(); }
            }
            $this->kw('FROM');
            $table = $this->ident();
            $where = null; $order = array(); $limit = null;
            if ($this->isKw('WHERE')) { $this->kw('WHERE'); $where = $this->expr(); }
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
            return array('type' => 'select', 'cols' => $cols, 'table' => $table,
                         'where' => $where, 'order' => $order, 'limit' => $limit);
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
            $cols = ($st['cols'] === '*') ? array_keys($row) : $st['cols'];
            foreach ($cols as $c) {
                if (! array_key_exists($c, $row)) { throw new SLP_SQL_Error("unknown column {$c}"); }
                /* wpdb hands every value back as a string. */
                $sel[$c] = ($row[$c] === null) ? null : (string) $row[$c];
            }
            $out[] = $sel;
        }
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

    public function get_charset_collate() {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
    }

    /* %s is quoted and escaped the way mysqli_real_escape_string does. */
    public function prepare($sql, ...$a) {
        if (count($a) === 1 && is_array($a[0])) { $a = $a[0]; }
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
        $GLOBALS['sql'][] = $sql;
        if ($this->fail_select) { return null; }
        try {
            $st = SLP_SQL::parse($sql);
            if ($st['type'] !== 'select' || $st['table'] !== 'wp_avalon_dealer_places') {
                throw new SLP_SQL_Error('not a SELECT on the places table');
            }
            $rows = SLP_SQL::select($st);
            /* THE OUTPUT MODE IS HONOURED. wpdb's default is OBJECT, and code
               that indexes a row as an array crashes on one. A double that
               always returned arrays would pass a build that drops ARRAY_A
               and dies on the first due row in production. */
            if ($mode === ARRAY_A) { return $rows; }
            if ($mode === ARRAY_N) { return array_map('array_values', $rows); }
            return array_map(function ($r) { return (object) $r; }, $rows);
        } catch (SLP_SQL_Error $e) {
            $GLOBALS['unparsed'][] = $sql . '  <- ' . $e->getMessage();
            return null;
        }
    }

    public function query($sql) {
        $GLOBALS['sql'][] = $sql;
        try {
            $st = SLP_SQL::parse($sql);
            if ($st['type'] !== 'update' || $st['table'] !== 'wp_avalon_dealer_places') {
                throw new SLP_SQL_Error('not an UPDATE on the places table');
            }
            return SLP_SQL::update($st);
        } catch (SLP_SQL_Error $e) {
            $GLOBALS['unparsed'][] = $sql . '  <- ' . $e->getMessage();
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

/* THE QUEUE. Eight rows are due, in this order:
     a00000000001  pending, never fetched          NULL first, by key
     a00000000012  pending, never fetched
     a00000000013  failed, never fetched
     a00000000018  pending, never fetched          (Google sends empty hours)
     a00000000004  none, 35 days                   then oldest first
     a00000000002  ok, 31 days
     a00000000006  failed, 8 days
     a00000000016  pending, 3 days (struck once)
   and ten are not, each for exactly one reason. a00000000017 is the one
   that isolates place_status: a place id is present and the place is not
   ok, so only that condition keeps it out. The struck and failed rows
   start with a last_error, so "cleared" means something. */
function queue_fixture() {
    return array(
        'a00000000001' => row('ok', 'ChIJ_A01', 'pending', null),
        'a00000000002' => row('ok', 'ChIJ_A02', 'ok',      ago(31)),
        'a00000000003' => row('ok', 'ChIJ_A03', 'ok',      ago(29)),
        'a00000000004' => row('ok', 'ChIJ_A04', 'none',    ago(35)),
        'a00000000005' => row('ok', 'ChIJ_A05', 'none',    ago(29)),
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
        'a00000000018' => row('ok', 'ChIJ_A18', 'pending', null),
    );
}

PHPHEAD;

$harness  = $head;
$harness .= "define('ABSPATH', " . var_export($abs, true) . ");\n";
$harness .= "\$scenario = \$argv[1];\n\n";
$harness .= "class SLP_Avalon {\n";
$harness .= $consts . "\n";
$harness .= $tableM . "\n";
$harness .= $hours . "\n";
$harness .= $systemic . "\n";
$harness .= <<<'PHPTAIL'
    private static function log($e) { $GLOBALS['logged'][] = array('log', $e); }
}

PHPTAIL;

$harness .= <<<'PHPRUN'
$o = new SLP_Avalon();

/* THE HELPERS ARE PRIVATE AND STAY PRIVATE. Reflection reaches them
   without widening production visibility. */
function priv($o, $m, $args = array()) {
    $r = new ReflectionMethod('SLP_Avalon', $m);
    /* A no-op since PHP 8.1 and deprecated in 8.5, where the notice would
       land in this scenario's JSON. Called only where it still matters. */
    if (PHP_VERSION_ID < 80100) { $r->setAccessible(true); }
    return $r->invokeArgs($r->isStatic() ? null : $o, $args);
}

/* Fetch once, and classify what came back with the REAL classifier. */
function fetch($o, $canned, $pid = 'ChIJTUJR1je6rYkR-Tl154_fum8') {
    $GLOBALS['canned'] = array($canned);
    $GLOBALS['http']   = array();
    $r = $o->avalon_hours_details($pid);
    $r['systemic'] = priv($o, 'avalon_places_is_systemic', array($r['error']));
    $r['calls']    = count($GLOBALS['http']);
    $r['url']      = $GLOBALS['http'][0]['url']  ?? '';
    $r['args']     = $GLOBALS['http'][0]['args'] ?? array();
    return $r;
}

$r = null;

/* A crash inside the lifted code is a result, not a broken suite. It is
   recorded, the assertions that needed the output fail, and the harness
   reports it by name. */
try {
switch ($scenario) {

/* ---- declarations and constants ------------------------------ */
case 'decl':
    $rc = new ReflectionClass('SLP_Avalon');
    $r = array('consts' => $rc->getConstants(), 'methods' => array());
    foreach (array('avalon_hours_table', 'avalon_hours_install',
                   'avalon_hours_maybe_install', 'avalon_hours_config',
                   'avalon_hours_details', 'avalon_hours_details_legacy',
                   'avalon_hours_details_new', 'avalon_hours_normalise_legacy',
                   'avalon_hours_point', 'avalon_hours_verdict',
                   'avalon_hours_sweep', 'avalon_places_is_systemic') as $m) {
        $r['methods'][$m] = method_exists('SLP_Avalon', $m);
    }
    break;

/* ---- schema -------------------------------------------------- */
case 'schema_fresh':
    SLP_Avalon::avalon_hours_maybe_install();
    $r = array('done' => 1);
    break;
case 'schema_v1':
    $GLOBALS['options']['avalon_hours_db_version'] = '1';
    $GLOBALS['dbdelta_changes'] = array('wp_avalon_dealer_places.business_status'
        => 'Added column wp_avalon_dealer_places.business_status');
    SLP_Avalon::avalon_hours_maybe_install();
    $r = array('done' => 1);
    break;
case 'schema_v2':
    $GLOBALS['options']['avalon_hours_db_version'] = '2';
    SLP_Avalon::avalon_hours_maybe_install();
    $r = array('done' => 1);
    break;

/* ---- config -------------------------------------------------- */
case 'config_default':
    $r = $o->avalon_hours_config();
    break;
case 'config_new':
    define('AVALON_HOURS_API', 'NEW');
    $r = $o->avalon_hours_config();
    break;
case 'config_typo':
    define('AVALON_HOURS_API', 'nwe');
    $r = $o->avalon_hours_config();
    break;
case 'config_clamp':
    define('AVALON_HOURS_POSITIVE_TTL_DAYS', 60);
    define('AVALON_HOURS_NEGATIVE_TTL_DAYS', 0);
    $r = $o->avalon_hours_config();
    break;
case 'config_clamp2':
    define('AVALON_HOURS_POSITIVE_TTL_DAYS', 0);
    define('AVALON_HOURS_NEGATIVE_TTL_DAYS', 60);
    $r = $o->avalon_hours_config();
    break;

/* ---- fetch, legacy ------------------------------------------- */
case 'legacy_ok':
    $r = fetch($o, legacy_ok('ChIJTUJR1je6rYkR-Tl154_fum8', 'Control Marine',
        'OPERATIONAL', true,
        array('<a href="https://example.com/">Listing by Example</a>')));
    $r['canned_body'] = json_decode(legacy_ok('ChIJTUJR1je6rYkR-Tl154_fum8',
        'Control Marine', 'OPERATIONAL', true,
        array('<a href="https://example.com/">Listing by Example</a>'))['body'], true);
    break;

case 'legacy_shapes':
    $r = array(
        /* Open 24 hours: legacy sends day 0, 0000, and no close. */
        'allday' => fetch($o, array('code' => 200, 'body' => json_encode(array(
            'html_attributions' => array(), 'status' => 'OK',
            'result' => legacy_result('ChIJ_24H', 'All Day Marine', 'OPERATIONAL', false,
                array('opening_hours' => array('open_now' => true,
                    'periods' => array(array('open' => array('day' => 0, 'time' => '0000'))),
                    'weekday_text' => array('Monday: Open 24 hours')))))))),
        /* Three-digit times: legacy has been seen to drop the leading zero. */
        'short' => fetch($o, array('code' => 200, 'body' => json_encode(array(
            'html_attributions' => array(), 'status' => 'OK',
            'result' => legacy_result('ChIJ_930', 'Half Past Marine', 'OPERATIONAL', false,
                array('opening_hours' => array('periods' => array(
                    array('open'  => array('day' => 1, 'time' => '930'),
                          'close' => array('day' => 1, 'time' => '000'))),
                    'weekday_text' => array('x')))))))),
        /* Both offset keys: the modern one wins. */
        'both' => fetch($o, legacy_ok('ChIJ_OFF', 'Offset Marine', 'OPERATIONAL', true,
            array(), array('utc_offset_minutes' => -300))),
        'nohours' => fetch($o, legacy_ok('ChIJ_NOH', 'No Hours Marine',
            'CLOSED_PERMANENTLY', false)),
        'emptyhours' => fetch($o, legacy_ok('ChIJ_EMP', 'Empty Hours Marine',
            'OPERATIONAL', false, array(), array('opening_hours' => array()))),
    );
    break;

case 'legacy_errors':
    $long = str_repeat('x', 500);
    $r = array(
        'zero'    => fetch($o, legacy_status('ZERO_RESULTS')),
        'nf'      => fetch($o, legacy_status('NOT_FOUND')),
        'denied'  => fetch($o, legacy_status('REQUEST_DENIED', 'The provided API key is invalid.')),
        'overq'   => fetch($o, legacy_status('OVER_QUERY_LIMIT')),
        'invalid' => fetch($o, legacy_status('INVALID_REQUEST')),
        'unknown' => fetch($o, legacy_status('UNKNOWN_ERROR')),
        'never'   => fetch($o, legacy_status('BRAND_NEW_STATUS')),
        'okempty' => fetch($o, legacy_status('OK')),
        'wperr'   => fetch($o, new WP_Error('http_request_failed', 'cURL error 28')),
        'badjson' => fetch($o, array('code' => 200, 'body' => 'not json')),
        'long'    => fetch($o, legacy_status('REQUEST_DENIED', $long)),
    );
    break;

case 'guards':
    $r = array('emptyid' => fetch($o, legacy_ok('X', 'X'), ''));
    $GLOBALS['slplus']->SmartOptions->google_server_key->value = '';
    $r['nokey'] = fetch($o, legacy_ok('X', 'X'));
    break;

case 'disabled':
    define('AVALON_HOURS_ENABLED', false);
    $r = array('details' => fetch($o, legacy_ok('X', 'X')));
    seed(queue_fixture());
    $GLOBALS['sql'] = array();
    $GLOBALS['fallback'] = legacy_ok('ChIJ_ANY', 'Anybody');
    $r['sweep'] = $o->avalon_hours_sweep(0, false);
    break;

/* ---- fetch, New ---------------------------------------------- */
case 'new_ok':
    define('AVALON_HOURS_API', 'new');
    $r = fetch($o, new_ok('ChIJ New/Id', 'New Marine'), 'ChIJ New/Id');
    $r['canned_body'] = json_decode(new_ok('ChIJ New/Id', 'New Marine')['body'], true);
    break;
case 'new_errors':
    define('AVALON_HOURS_API', 'new');
    $r = array(
        'denied'  => fetch($o, new_err(403, 'PERMISSION_DENIED', 'Places API (New) has not been used')),
        'nf'      => fetch($o, new_err(404, 'NOT_FOUND', 'Place not found')),
        'bare500' => fetch($o, new_err(500, null, 'backend error')),
        'wperr'   => fetch($o, new WP_Error('http_request_failed', 'timeout')),
        'badjson' => fetch($o, array('code' => 200, 'body' => '<html>')),
    );
    break;

/* ---- the sweep ----------------------------------------------- */
case 'sweep_dry':
    seed(queue_fixture());
    $GLOBALS['fallback'] = legacy_ok('ChIJ_ANY', 'Anybody');
    $before = $GLOBALS['table'];
    $r = array('out' => $o->avalon_hours_sweep(0, true), 'before' => $before);
    break;

case 'sweep_limit':
    seed(queue_fixture());
    $GLOBALS['fallback'] = legacy_ok('ChIJ_ANY', 'Anybody');
    $r = array('out' => $o->avalon_hours_sweep(3, true));
    break;

case 'sweep_ceiling':
    define('AVALON_HOURS_DETAILS_CEILING', 2);
    seed(queue_fixture());
    $GLOBALS['fallback'] = legacy_ok('ChIJ_ANY', 'Anybody');
    $r = array('out' => $o->avalon_hours_sweep(0, true));
    break;

case 'sweep_run':
    seed(queue_fixture());
    $GLOBALS['byplace'] = array(
        'ChIJ_A01' => legacy_ok('ChIJ_A01', 'Alpha Marine'),
        'ChIJ_A12' => legacy_ok('ChIJ_A12', 'Closed Boats', 'CLOSED_PERMANENTLY', false),
        'ChIJ_A13' => legacy_ok('ChIJ_A13', 'Thirteen Marine'),
        'ChIJ_A04' => legacy_ok('ChIJ_A04', 'Four Marine'),
        'ChIJ_A02' => legacy_ok('ChIJ_A02', 'Two Marine', null),
        'ChIJ_A06' => legacy_ok('ChIJ_A06', 'Six Marine'),
        'ChIJ_A16' => legacy_ok('ChIJ_A16', 'Sixteen Marine'),
        'ChIJ_A18' => legacy_ok('ChIJ_A18', 'Empty Hours Marine', 'OPERATIONAL', false,
                                array(), array('opening_hours' => array())),
    );
    $bodies = array();
    foreach ($GLOBALS['byplace'] as $pid => $resp) {
        $bodies[$pid] = json_decode($resp['body'], true);
    }
    /* Anything else asked is answered - so a build that asks a blocked
       row is caught by what it WROTE, not by a crash. */
    $GLOBALS['fallback'] = legacy_ok('ChIJ_UNEXPECTED', 'Should Never Be Asked');
    $before = $GLOBALS['table'];
    $r = array('out' => $o->avalon_hours_sweep(0, false), 'before' => $before,
               'bodies' => $bodies, 'clock_calls' => $GLOBALS['clock_calls']);
    break;

case 'sweep_nothing_due':
    $all = queue_fixture();
    $idle = array();
    foreach (array('a00000000003', 'a00000000005', 'a00000000007', 'a00000000008',
                   'a00000000014', 'a00000000017') as $k) {
        $idle[$k] = $all[$k];
    }
    seed($idle);
    $GLOBALS['fallback'] = legacy_ok('ChIJ_ANY', 'Anybody');
    $before = $GLOBALS['table'];
    $r = array('out' => $o->avalon_hours_sweep(0, false), 'before' => $before);
    break;

case 'sweep_strike':
    seed(array(
        'b00000000001' => row('ok', 'ChIJ_S1', 'pending', null, 0),
        'b00000000002' => row('ok', 'ChIJ_S2', 'pending', ago(2), 1),
        'b00000000003' => row('ok', 'ChIJ_S3', 'pending', ago(1), 2),
        'b00000000004' => row('ok', 'ChIJ_S4', 'failed',  ago(8), 3),
        'b00000000005' => row('ok', 'ChIJ_S5', 'pending', null, 0),
    ));
    $GLOBALS['byplace'] = array(
        'ChIJ_S1' => legacy_status('ZERO_RESULTS'),
        'ChIJ_S2' => legacy_status('NOT_FOUND'),
        'ChIJ_S3' => legacy_status('ZERO_RESULTS'),
        'ChIJ_S4' => legacy_status('NOT_FOUND'),
        'ChIJ_S5' => legacy_ok('ChIJ_S5', 'Five Marine'),
    );
    $r = array('out' => $o->avalon_hours_sweep(0, false));
    break;

case 'sweep_systemic_first':
    seed(array(
        'c00000000001' => row('ok', 'ChIJ_C1', 'pending', null, 2),
        'c00000000002' => row('ok', 'ChIJ_C2', 'pending', null, 0),
        'c00000000003' => row('ok', 'ChIJ_C3', 'pending', null, 0),
    ));
    $GLOBALS['byplace'] = array(
        'ChIJ_C1' => legacy_status('REQUEST_DENIED', 'The provided API key is invalid.'),
        'ChIJ_C2' => legacy_ok('ChIJ_C2', 'Never Two'),
        'ChIJ_C3' => legacy_ok('ChIJ_C3', 'Never Three'),
    );
    $before = $GLOBALS['table'];
    $r = array('out' => $o->avalon_hours_sweep(0, false), 'before' => $before);
    break;

case 'sweep_systemic_mid':
    seed(array(
        'd00000000001' => row('ok', 'ChIJ_D1', 'pending', null, 0),
        'd00000000002' => row('ok', 'ChIJ_D2', 'pending', null, 1),
        'd00000000003' => row('ok', 'ChIJ_D3', 'pending', null, 0),
    ));
    $GLOBALS['byplace'] = array(
        'ChIJ_D1' => legacy_ok('ChIJ_D1', 'One Marine'),
        'ChIJ_D2' => new WP_Error('http_request_failed', 'cURL error 28: timed out'),
        'ChIJ_D3' => legacy_ok('ChIJ_D3', 'Never Three'),
    );
    $before = $GLOBALS['table'];
    $r = array('out' => $o->avalon_hours_sweep(0, false), 'before' => $before);
    break;

case 'sweep_badjson':
    seed(array(
        'e00000000001' => row('ok', 'ChIJ_E1', 'pending', null, 0),
        'e00000000002' => row('ok', 'ChIJ_E2', 'pending', null, 0),
    ));
    $GLOBALS['byplace'] = array(
        'ChIJ_E1' => array('code' => 502, 'body' => '<html>Bad Gateway</html>'),
        'ChIJ_E2' => legacy_ok('ChIJ_E2', 'Never Two'),
    );
    $before = $GLOBALS['table'];
    $r = array('out' => $o->avalon_hours_sweep(0, false), 'before' => $before);
    break;

case 'sweep_new':
    define('AVALON_HOURS_API', 'new');
    seed(array('f00000000001' => row('ok', 'ChIJ_F1', 'pending', null, 0)));
    $GLOBALS['byplace'] = array('ChIJ_F1' => new_ok('ChIJ_F1', 'New Shape Marine'));
    $r = array('out' => $o->avalon_hours_sweep(0, false),
               'body' => json_decode(new_ok('ChIJ_F1', 'New Shape Marine')['body'], true));
    break;

case 'sweep_badquery':
    seed(queue_fixture());
    $GLOBALS['wpdb']->fail_select = true;
    $GLOBALS['fallback'] = legacy_ok('ChIJ_ANY', 'Anybody');
    $r = array('out' => $o->avalon_hours_sweep(0, false));
    break;

case 'sweep_escape':
    seed(array('g00000000001' => row('ok', 'ChIJ_G1', 'pending', null, 0)));
    $name = "O'Brien's \"Dockside\" Marine \\ Co. \u{2013} Lake O'Hare";
    $GLOBALS['byplace'] = array('ChIJ_G1' => legacy_ok('ChIJ_G1', $name));
    $r = array('out' => $o->avalon_hours_sweep(0, false), 'name' => $name);
    break;
}
} catch (Throwable $t) {
    $r = array('crash' => get_class($t) . ': ' . $t->getMessage());
}

echo json_encode(array(
    'r'           => $r,
    'table'       => $GLOBALS['table'],
    'sql'         => $GLOBALS['sql'],
    'unparsed'    => $GLOBALS['unparsed'],
    'logged'      => $GLOBALS['logged'],
    'http'        => $GLOBALS['http'],
    'options'     => $GLOBALS['options'],
    'dbdelta'     => $GLOBALS['dbdelta'],
    'clock_calls' => $GLOBALS['clock_calls'],
    'clock_base'  => $GLOBALS['clock_base'],
));
PHPRUN;

$hfile = $tmp . DIRECTORY_SEPARATOR . 'harness.php';
file_put_contents($hfile, $harness);

function run($hfile, $scenario)
{
    $err = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'slp30err_' . getmypid() . '_' . $scenario;
    $out = array();
    $rc  = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -d short_open_tag=1 '
         . escapeshellarg($hfile) . ' ' . escapeshellarg($scenario)
         . ' 2>' . escapeshellarg($err), $out, $rc);
    $raw = implode("\n", $out);
    $j   = json_decode($raw, true);
    if (! is_array($j)) {
        /* A scenario that did not run is not a pass and not a fail. */
        fwrite(STDERR, "\n  SUITE BROKEN: scenario '{$scenario}' produced no JSON\n");
        fwrite(STDERR, "  stdout: " . substr($raw, 0, 400) . "\n");
        fwrite(STDERR, "  stderr: " . substr((string) @file_get_contents($err), 0, 800) . "\n");
        exit(2);
    }
    @unlink($err);
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
        elseif (preg_match('#/v1/places/([^/?]+)$#', $h['url'], $m)) { $out[] = rawurldecode($m[1]); }
    }
    return $out;
}
function payload($row) {
    $j = json_decode((string) ($row['hours_json'] ?? ''), true);
    return is_array($j) ? $j : array();
}

/* ------------------------------------------------------------------ */
/* Assertions.                                                         */
/* ------------------------------------------------------------------ */

$CRASHES = array();

echo "\n  suite-v030  -  slp_avalon v0.0.27 Parts 1, 2 and 3a\n";
echo "  artefact  {$clsPath}\n";
printf("  md5 %s  %d bytes\n", md5($code), strlen($code));

echo "\n  THE FILE, REGION BY REGION\n";
foreach ($PINNED_REGIONS as $k => $pin) {
    $got = $regions[$k];
    $labels = array(
        'H' => 'H, the head, is byte-identical to v0.0.26-part3d',
        'A' => 'A, instance() to the hours table - territory gate, import guard,'
             . ' reconcile, redirects - is byte-identical to v0.0.26-part3d',
        'B' => 'B, the Part 3d resolver, purge, seed, import and CLI, is'
             . ' byte-identical to the build the unattended cron proved',
    );
    check(md5($got) === $pin[0] && strlen($got) === $pin[1], $labels[$k]);
}
check(substr_count($code, "\r\n") === substr_count($code, "\n")
      && substr_count($code, "\r\n") === substr_count($code, "\r"),
    'the file is pure CRLF - no bare LF, no bare CR (s0.216)');

echo "\n  THE LIFT\n";
$dl = run($hfile, 'decl');
$allThere = true;
foreach (($dl['r']['methods'] ?? array()) as $mm => $there) {
    if ($there !== true) { $allThere = false; }
}
check($allThere && count($dl['r']['methods'] ?? array()) === 12,
    'all twelve hours methods are defined inside the harness');

echo "\n  CONSTANTS\n";
$K = $dl['r']['consts'] ?? array();
check(($K['HOURS_DB_VERSION'] ?? null) === '2',
    'Part 1 - HOURS_DB_VERSION is 2, which is what migrates an existing site');
check(($K['HOURS_STATUS_PENDING'] ?? null) === 'pending'
      && ($K['HOURS_STATUS_OK'] ?? null) === 'ok'
      && ($K['HOURS_STATUS_NONE'] ?? null) === 'none'
      && ($K['HOURS_STATUS_BLOCKED'] ?? null) === 'blocked'
      && ($K['HOURS_STATUS_FAILED'] ?? null) === 'failed',
    'the five hours states are pending, ok, none, blocked, failed');
check(isset($K['HOURS_STATUS_PENDING'], $K['PLACES_STATUS_PENDING'])
      && $K['HOURS_STATUS_PENDING'] === $K['PLACES_STATUS_PENDING']
      && $K['HOURS_STATUS_OK'] === $K['PLACES_STATUS_OK']
      && $K['HOURS_STATUS_FAILED'] === $K['PLACES_STATUS_FAILED'],
    's0.256 - one vocabulary: pending, ok and failed are spelled as the places side spells them');
check(($K['HOURS_ERROR_CEILING'] ?? null) === 3, 'the hours strike ceiling is 3');
check(($K['HOURS_FIELDS_LEGACY'] ?? null)
      === 'place_id,name,business_status,opening_hours,utc_offset',
    'the legacy mask asks for business_status and opening_hours');
$newMask = explode(',', (string) ($K['HOURS_FIELDS_NEW'] ?? ''));
check(count(array_diff(array('id', 'displayName', 'businessStatus', 'regularOpeningHours',
                             'utcOffsetMinutes', 'attributions'), $newMask)) === 0,
    'the New mask carries every field the normalised shape needs');
check(($K['HOURS_ENDPOINT_LEGACY'] ?? null) === 'https://maps.googleapis.com/maps/api/place/details/json'
      && ($K['HOURS_ENDPOINT_NEW'] ?? null) === 'https://places.googleapis.com/v1/places/',
    'both Place Details endpoints are declared');
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
    'HOURS_ENDPOINT_LEGACY' => 'https://maps.googleapis.com/maps/api/place/details/json',
    'HOURS_ENDPOINT_NEW' => 'https://places.googleapis.com/v1/places/',
    'HOURS_FIELDS_LEGACY' => 'place_id,name,business_status,opening_hours,utc_offset',
    'HOURS_FIELDS_NEW' => 'id,name,displayName,businessStatus,regularOpeningHours,'
                        . 'utcOffsetMinutes,primaryTypeDisplayName,attributions',
);
ksort($K); ksort($EXPECT_CONSTS);
check($K === $EXPECT_CONSTS,
    'the class declares exactly these 21 constants with exactly these values');

echo "\n  SCHEMA  (Part 1, s0.255)\n";
$sf = run($hfile, 'schema_fresh');
check(count($sf['dbdelta'] ?? array()) === 1,
    'a site with no stored schema version runs dbDelta once');
check(($sf['options']['avalon_hours_db_version'] ?? null) === '2',
    'and records version 2');
$s1 = run($hfile, 'schema_v1');
check(count($s1['dbdelta'] ?? array()) === 1
      && ($s1['options']['avalon_hours_db_version'] ?? null) === '2',
    'an existing v1 site migrates on the next init - an SFTP overwrite IS the migration');
$s2 = run($hfile, 'schema_v2');
check(count($s2['dbdelta'] ?? array()) === 0,
    'a site already at v2 reads one option and returns');
$ddl   = (string) (($sf['dbdelta'] ?? array())[0] ?? '');
$lines = explode("\n", $ddl);
check(count(array_keys($lines, '  business_status varchar(24) null default null,', true)) === 1,
    'business_status varchar(24) null is declared exactly once, alone on its line');
$typesLower = true; $colLines = 0;
foreach ($lines as $ln) {
    if (preg_match('/^  ([a-z_]+) ([A-Za-z]+)[ (,]/', $ln, $m)) {
        $colLines++;
        if ($m[2] !== strtolower($m[2])) { $typesLower = false; }
    }
}
check($typesLower && $colLines === 16,
    'every column type is lowercase - dbDelta re-ALTERs uppercase types forever');
$keys = array_values(preg_grep('/KEY/', $lines));
check($keys === array('  PRIMARY KEY  (address_key),', '  KEY sl_id (sl_id),',
                      '  KEY place_id (place_id),',
                      '  KEY hours_sweep (hours_status, fetched_at),',
                      '  KEY place_sweep (place_status, place_checked_at)'),
    'the five keys are exactly as v0.0.26 left them - no KEY added, changed or removed');
check(in_array("  hours_status varchar(16) not null default 'pending',", $lines, true)
      && ($K['HOURS_STATUS_PENDING'] ?? '') === 'pending',
    'the hours_status column default is the HOURS_STATUS_PENDING spelling');
check($ddl !== '' && strpos($ddl, "\r") === false,
    'the statement carries no CR, although the file is CRLF');
$WANT_DDL = implode("\n", array(
    'CREATE TABLE wp_avalon_dealer_places (',
    '  address_key char(12) not null,',
    '  sl_id bigint(20) unsigned null default null,',
    '  place_id varchar(191) null default null,',
    "  place_status varchar(16) not null default 'pending',",
    '  place_checked_at datetime null default null,',
    '  hours_json longtext null default null,',
    "  hours_status varchar(16) not null default 'pending',",
    '  fetched_at datetime null default null,',
    '  business_status varchar(24) null default null,',
    '  primary_type_display varchar(190) null default null,',
    '  locality varchar(190) null default null,',
    '  admin_area varchar(190) null default null,',
    '  attribution_json text null default null,',
    '  error_count smallint(5) unsigned not null default 0,',
    '  last_error varchar(190) null default null,',
    '  updated_at datetime null default null,',
    '  PRIMARY KEY  (address_key),',
    '  KEY sl_id (sl_id),',
    '  KEY place_id (place_id),',
    '  KEY hours_sweep (hours_status, fetched_at),',
    '  KEY place_sweep (place_status, place_checked_at)',
    ') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;',
));
/* The whole statement, not a sample of it. With a version bump pending,
   any other column drift here is a silent ALTER on every site - a
   narrower place_id or last_error truncates what is already stored. */
check($ddl === $WANT_DDL,
    'the whole CREATE TABLE is exactly the v2 schema: sixteen columns, five keys, nothing else');

echo "\n  CONFIG  (Part 2)\n";
$cd = run($hfile, 'config_default');
check(($cd['r']['api'] ?? '') === 'legacy', 'the default endpoint is legacy - the one that answers');
$wantCfg = array('enabled' => true, 'positive_ttl_days' => 30, 'negative_ttl_days' => 7,
                 'resolve_ceiling' => 50, 'details_ceiling' => 50, 'timeout' => 8,
                 'api' => 'legacy');
$gotCfg = is_array($cd['r'] ?? null) ? $cd['r'] : array();
ksort($wantCfg); ksort($gotCfg);
/* Every key, because region B reads this method too: the pinned resolver
   and cron take resolve_ceiling from it, so byte-pinning B does not pin
   what B is told to do. */
check($gotCfg === $wantCfg,
    'the config is exactly: enabled, TTLs 30 and 7, resolve and details ceilings 50,'
    . ' timeout 8, api legacy');
$cn = run($hfile, 'config_new');
check(($cn['r']['api'] ?? '') === 'new', 'AVALON_HOURS_API NEW selects the New endpoint, case-folded');
$ct = run($hfile, 'config_typo');
check(($ct['r']['api'] ?? '') === 'legacy', 'a typo lands on legacy, not on the endpoint that 403s');
$cc  = run($hfile, 'config_clamp');
$cc2 = run($hfile, 'config_clamp2');
check(($cc['r']['positive_ttl_days'] ?? 0) === 30 && ($cc['r']['negative_ttl_days'] ?? 0) === 1
      && ($cc2['r']['positive_ttl_days'] ?? 0) === 1 && ($cc2['r']['negative_ttl_days'] ?? 0) === 30,
    'both TTLs are clamped to 1..30 at both ends - the 30-day cap is not configurable');

echo "\n  FETCH - LEGACY  (Part 2)\n";
$lo = run($hfile, 'legacy_ok');
$L  = $lo['r'] ?? array();
$P  = $L['place'] ?? array();
check(($L['calls'] ?? 0) === 1
      && ($L['url'] ?? '') === 'https://maps.googleapis.com/maps/api/place/details/json'
         . '?place_id=ChIJTUJR1je6rYkR-Tl154_fum8'
         . '&fields=place_id,name,business_status,opening_hours,utc_offset&key=TESTKEY123',
    'one call, and the finished URL is exactly the legacy Place Details request');
check(($L['args']['sslverify'] ?? false) === true && ($L['args']['timeout'] ?? 0) === 8,
    'the request verifies TLS and carries the timeout');
check(($L['ok'] ?? false) === true && ($L['api'] ?? '') === 'legacy' && ($L['error'] ?? 'x') === '',
    'a legacy OK is ok, from legacy, with no error');
check(($P['id'] ?? '') === 'ChIJTUJR1je6rYkR-Tl154_fum8'
      && ($P['name'] ?? '') === 'places/ChIJTUJR1je6rYkR-Tl154_fum8'
      && ($P['displayName']['text'] ?? '') === 'Control Marine',
    'normalised to the New shape: id, name places/<id>, displayName.text');
check(($P['businessStatus'] ?? '') === 'OPERATIONAL', 'business_status becomes businessStatus');
check(array_key_exists('utcOffsetMinutes', $P) && $P['utcOffsetMinutes'] === -240,
    'utcOffsetMinutes is read from the DEPRECATED utc_offset key, as measured');
$wd = $P['regularOpeningHours']['weekdayDescriptions'] ?? array();
$lb = $L['canned_body']['result']['opening_hours']['weekday_text'] ?? array('?');
check(count($wd) === 7 && $wd === $lb,
    'seven weekday lines, verbatim, unicode intact');
$pe = $P['regularOpeningHours']['periods'] ?? array();
check(count($pe) === 5
      && ($pe[0] ?? null) === array('open' => array('day' => 2, 'hour' => 9, 'minute' => 0),
                                    'close' => array('day' => 2, 'hour' => 17, 'minute' => 0)),
    'five periods beside seven lines - a closed day has no period, as measured');
check(array_key_exists('openNow', $P['regularOpeningHours'] ?? array())
      && $P['regularOpeningHours']['openNow'] === false,
    'openNow is carried across (never to be rendered from cache)');
check(($P['attributions'] ?? null)
      === array('<a href="https://example.com/">Listing by Example</a>'),
    'html_attributions become attributions, verbatim');
check(($L['raw'] ?? null) === ($L['canned_body'] ?? 'x'),
    'raw is the verbatim decoded response');

$ls = run($hfile, 'legacy_shapes');
$ad = $ls['r']['allday']['place']['regularOpeningHours']['periods'] ?? array();
check(count($ad) === 1 && ($ad[0]['open'] ?? null) === array('day' => 0, 'hour' => 0, 'minute' => 0)
      && ! array_key_exists('close', $ad[0] ?? array('close' => 1)),
    'open 24 hours: one period, no close key - not a null close');
$sp = $ls['r']['short']['place']['regularOpeningHours']['periods'][0] ?? array();
check(($sp['open'] ?? null) === array('day' => 1, 'hour' => 9, 'minute' => 30)
      && ($sp['close'] ?? null) === array('day' => 1, 'hour' => 0, 'minute' => 0),
    'a three-digit time is left-padded: 930 is 9:30, not hour 93');
check(($ls['r']['both']['place']['utcOffsetMinutes'] ?? null) === -300,
    'when both offset keys arrive, utc_offset_minutes wins');
check(($ls['r']['nohours']['ok'] ?? false) === true
      && ! array_key_exists('regularOpeningHours', $ls['r']['nohours']['place'] ?? array('regularOpeningHours' => 1)),
    'no opening_hours in the answer means no regularOpeningHours - nothing invented');
check(($ls['r']['emptyhours']['place']['regularOpeningHours'] ?? null) === array(),
    'an empty opening_hours normalises to an empty regularOpeningHours');

$le = run($hfile, 'legacy_errors');
$E  = $le['r'] ?? array();
check(($E['zero']['error'] ?? '') === 'STATUS ZERO_RESULTS' && ($E['zero']['systemic'] ?? null) === false,
    's0.256 - ZERO_RESULTS is STATUS ZERO_RESULTS: dealer data, the row takes the strike');
check(($E['nf']['error'] ?? '') === 'STATUS NOT_FOUND' && ($E['nf']['systemic'] ?? null) === false,
    's0.256 - NOT_FOUND is STATUS NOT_FOUND: dealer data');
check(($E['denied']['error'] ?? '') === 'FATAL REQUEST_DENIED The provided API key is invalid.'
      && ($E['denied']['systemic'] ?? null) === true,
    's0.256 - REQUEST_DENIED is FATAL: an auth failure never strikes 301 dealers');
check(($E['overq']['error'] ?? '') === 'FATAL OVER_QUERY_LIMIT' && ($E['overq']['systemic'] ?? null) === true,
    'OVER_QUERY_LIMIT is FATAL');
check(($E['invalid']['error'] ?? '') === 'FATAL INVALID_REQUEST' && ($E['invalid']['systemic'] ?? null) === true,
    'INVALID_REQUEST is FATAL');
check(($E['unknown']['error'] ?? '') === 'FATAL UNKNOWN_ERROR' && ($E['unknown']['systemic'] ?? null) === true,
    'UNKNOWN_ERROR is FATAL');
check(($E['never']['error'] ?? '') === 'FATAL BRAND_NEW_STATUS' && ($E['never']['systemic'] ?? null) === true,
    'a status this code has never seen is FATAL - the default is systemic');
check(($E['okempty']['error'] ?? '') === 'FATAL OK' && ($E['okempty']['systemic'] ?? null) === true,
    'OK with no result is malformed, and systemic - not a fact about the dealer');
check(($E['wperr']['error'] ?? '') === 'FATAL TRANSPORT http_request_failed'
      && ($E['wperr']['systemic'] ?? null) === true,
    's0.256 - a transport failure is FATAL TRANSPORT: one bad afternoon strikes nobody');
check(($E['badjson']['error'] ?? '') === 'BADJSON' && ($E['badjson']['systemic'] ?? null) === true,
    's0.256 - an unparsable body is BADJSON, one word, systemic');
check(strlen($E['long']['error'] ?? '') === 190,
    'an error is cut to 190 characters - last_error is varchar(190), pinned in the schema above');
$leak = false;
foreach ($E as $one) { if (strpos((string) ($one['error'] ?? ''), 'TESTKEY123') !== false) { $leak = true; } }
check(! $leak, 'the key reaches no error string');

$g = run($hfile, 'guards');
check(($g['r']['emptyid']['error'] ?? '') === 'EMPTY_PLACE_ID' && ($g['r']['emptyid']['calls'] ?? 1) === 0,
    'an empty place id is refused before any call');
check(($g['r']['nokey']['error'] ?? '') === 'NO_KEY' && ($g['r']['nokey']['calls'] ?? 1) === 0,
    'a missing key is refused before any call');
$ds = run($hfile, 'disabled');
check(($ds['r']['details']['error'] ?? '') === 'DISABLED' && ($ds['r']['details']['calls'] ?? 1) === 0,
    'AVALON_HOURS_ENABLED false: the fetch spends nothing');
check(($ds['r']['sweep']['errors'] ?? null) === array('DISABLED') && count($ds['sql'] ?? array(1)) === 0
      && count($ds['http'] ?? array(1)) === 0,
    'and the sweep returns DISABLED without reading the queue');

echo "\n  FETCH - NEW  (Part 2, unreachable today)\n";
$no = run($hfile, 'new_ok');
$N  = $no['r'] ?? array();
check(($N['url'] ?? '') === 'https://places.googleapis.com/v1/places/ChIJ%20New%2FId'
      && strpos($N['url'] ?? 'TESTKEY123', 'TESTKEY123') === false,
    'the place id is a rawurlencoded path segment and the key is NOT in the URL');
check(($N['args']['headers']['X-Goog-Api-Key'] ?? '') === 'TESTKEY123'
      && ($N['args']['headers']['X-Goog-FieldMask'] ?? '') === ($K['HOURS_FIELDS_NEW'] ?? '?'),
    'the key travels as X-Goog-Api-Key and the field mask is sent');
check(($N['args']['sslverify'] ?? false) === true && ($N['args']['timeout'] ?? 0) === 8,
    'the New request verifies TLS too - the key is in its headers');
check(($N['ok'] ?? false) === true && ($N['api'] ?? '') === 'new'
      && ($N['place'] ?? null) === ($N['canned_body'] ?? 'x'),
    'a New 200 is already the canonical shape and is kept verbatim');
$ne = run($hfile, 'new_errors');
$NE = $ne['r'] ?? array();
check(($NE['denied']['error'] ?? '') === 'FATAL PERMISSION_DENIED Places API (New) has not been used'
      && ($NE['denied']['systemic'] ?? null) === true,
    'a New 403 PERMISSION_DENIED goes through the verdict map to FATAL');
check(($NE['nf']['error'] ?? '') === 'STATUS NOT_FOUND Place not found'
      && ($NE['nf']['systemic'] ?? null) === false,
    'a New 404 NOT_FOUND is dealer data, as it is on legacy');
check(($NE['bare500']['error'] ?? '') === 'HTTP 500 backend error' && ($NE['bare500']['systemic'] ?? null) === true,
    'a status-less HTTP error is HTTP, systemic under the shape rule');
check(($NE['wperr']['error'] ?? '') === 'FATAL TRANSPORT http_request_failed'
      && ($NE['wperr']['systemic'] ?? null) === true,
    'a New transport failure is FATAL TRANSPORT too');
check(($NE['badjson']['error'] ?? '') === 'BADJSON' && ($NE['badjson']['systemic'] ?? null) === true,
    'an unparsable New body is BADJSON');

echo "\n  SWEEP - THE QUEUE  (Part 3a)\n";
$QUEUE = array('ChIJ_A01', 'ChIJ_A12', 'ChIJ_A13', 'ChIJ_A18',
               'ChIJ_A04', 'ChIJ_A02', 'ChIJ_A06', 'ChIJ_A16');
$NOTDUE = array('a00000000003', 'a00000000005', 'a00000000007', 'a00000000008',
                'a00000000009', 'a00000000010', 'a00000000011', 'a00000000014',
                'a00000000015', 'a00000000017');

$dr = run($hfile, 'sweep_dry');
check(($dr['r']['out']['scanned'] ?? 0) === 8, 'a dry run finds exactly the eight due rows');
check(count($dr['http'] ?? array(1)) === 0, 'a dry run spends nothing');
check(updates($dr) === 0 && ($dr['table'] ?? null) === ($dr['r']['before'] ?? 'x'),
    'a dry run writes nothing - the table is byte-identical');
$o0 = $dr['r']['out'] ?? array();
check(($o0['ok'] ?? 1) === 0 && ($o0['none'] ?? 1) === 0 && ($o0['struck'] ?? 1) === 0
      && ($o0['failed'] ?? 1) === 0 && ($o0['systemic'] ?? 1) === 0 && ($o0['dry'] ?? false) === true,
    'and reports itself as dry with every outcome at zero');
check(strpos(sqlText($dr), "hours_status <> 'blocked'") !== false,
    'blocked is excluded by name - no OR branch admits it today, and this'
    . ' keeps it that way if one is added');
check(empty($dr['unparsed']), 'the queue SELECT was read and executed, not skipped');

$sl = run($hfile, 'sweep_limit');
check(($sl['r']['out']['scanned'] ?? 0) === 3, 'an explicit limit bounds the sweep');
$sc = run($hfile, 'sweep_ceiling');
check(($sc['r']['out']['scanned'] ?? 0) === 2, 'limit 0 takes details_ceiling');

echo "\n  SWEEP - A REAL RUN\n";
$rn  = run($hfile, 'sweep_run');
$T   = $rn['table'] ?? array();
$out = $rn['r']['out'] ?? array();
check(asked($rn) === $QUEUE,
    'rows are asked in queue order: never-fetched first by key, then oldest first');
check(! in_array('ChIJ_B08', asked($rn), true) && ! in_array('ChIJ_B14', asked($rn), true)
      && ($T['a00000000008'] ?? null) === ($rn['r']['before']['a00000000008'] ?? 'x')
      && ($T['a00000000014'] ?? null) === ($rn['r']['before']['a00000000014'] ?? 'x'),
    'THE GATE - a blocked row is never asked and never written');
$still = true;
foreach ($NOTDUE as $k) {
    if (($T[$k] ?? null) !== ($rn['r']['before'][$k] ?? 'x')) { $still = false; }
}
check($still, 'every row that was not due is byte-identical afterwards');
check(($out['scanned'] ?? 0) === 8 && ($out['ok'] ?? 0) === 6 && ($out['none'] ?? 0) === 2
      && ($out['struck'] ?? 1) === 0 && ($out['failed'] ?? 1) === 0 && ($out['systemic'] ?? 1) === 0
      && ($out['dry'] ?? true) === false && ($out['errors'] ?? null) === array(),
    'counts: eight scanned, six ok, two none, nothing struck');
/* The properties below are asserted over the rows this run actually
   WROTE, not over the seven the fixture expects. Which rows are due is
   asserted once, above; repeating it inside every property would make a
   queue defect fail five unrelated assertions and blur what each control
   is evidence of. Every set is also required to be non-empty, so no
   property can pass vacuously on a run that wrote nothing. */
$written = array();
foreach ($T as $k => $row) {
    if ($row !== ($rn['r']['before'][$k] ?? null)) { $written[] = $k; }
}
$stamps = array();
foreach ($written as $k) {
    $stamps[] = $T[$k]['fetched_at'] ?? null;
    $stamps[] = $T[$k]['updated_at'] ?? null;
    $stamps[] = payload($T[$k] ?? array())['at'] ?? null;
}
check(count($written) >= 2 && count(array_unique($stamps)) === 1 && $stamps[0] !== null
      && ($rn['clock_calls'] ?? 0) === 1,
    '$now is read once: every row the run wrote carries one stamp in fetched_at,'
    . ' updated_at and the payload');
/* GMT, not "the first tick": any tick of this run's clock, read in GMT,
   passes; the same tick read in site-local time is four hours out. */
$st0 = (count($stamps) > 0 && $stamps[0] !== null) ? strtotime($stamps[0] . ' UTC') : false;
$cb  = (int) ($rn['clock_base'] ?? 0);
check($st0 !== false && $st0 > $cb && $st0 <= $cb + (int) ($rn['clock_calls'] ?? 0),
    'and the stamp is GMT, like the TTL cutoffs it is compared against');
$okRows = true; $okSeen = 0;
foreach ($written as $k) {
    $row = $T[$k];
    $pid = $rn['r']['before'][$k]['place_id'] ?? '?';
    if (empty($rn['r']['bodies'][$pid]['result']['opening_hours'])) { continue; }
    $okSeen++;
    /* array_key_exists, not ??: a NULL column is exactly what is wanted
       here, and ?? cannot tell NULL from missing. */
    if (($row['hours_status'] ?? '') !== 'ok' || (int) ($row['error_count'] ?? 9) !== 0
        || ! array_key_exists('last_error', $row) || $row['last_error'] !== null) {
        $okRows = false;
    }
}
check($okRows && $okSeen >= 1,
    'each row answered with hours is ok, with its strikes and last error cleared');
check(($T['a00000000006']['hours_status'] ?? '') === 'ok'
      && (int) ($T['a00000000006']['error_count'] ?? 9) === 0,
    'a failed row that answers on its 7-day retry is restored');
check(($T['a00000000012']['hours_status'] ?? '') === 'none'
      && ($T['a00000000012']['business_status'] ?? '') === 'CLOSED_PERMANENTLY',
    'a place with no hours is none - not ok, not a failure - and its business_status is kept');
check(($T['a00000000018']['hours_status'] ?? '') === 'none',
    'an EMPTY opening_hours is none too - an empty regularOpeningHours is not hours');
/* NOT NULL, AND THAT IS PRODUCTION'S BEHAVIOUR, NOT THIS DOUBLE'S. The
   sweep binds $bstat = null through %s, and wpdb::prepare() escapes a
   non-scalar as '' - there is no NULL through a %s placeholder. So a
   dealer Google sends no business_status for is stored as '', not NULL.
   Harmless to the render gate, which only refuses CLOSED_*, but it is
   not what the null in the code says. Pinned as it is, recorded as a
   finding, and corrected in Part 3b. */
check(($T['a00000000001']['business_status'] ?? '') === 'OPERATIONAL'
      && array_key_exists('business_status', $T['a00000000002'] ?? array())
      && $T['a00000000002']['business_status'] === '',
    "s0.255 - business_status is written to its column; '' when Google sent none,"
    . ' because prepare() has no NULL');
$shapeOk = true; $ownOk = true; $rawOk = true;
foreach ($written as $k) {
    $p = payload($T[$k] ?? array());
    if (($p['shape'] ?? '') !== 'new' || ($p['source'] ?? '') !== 'legacy') { $shapeOk = false; }
    $pid = $rn['r']['before'][$k]['place_id'] ?? '?';
    if (($p['place']['id'] ?? '') !== $pid) { $ownOk = false; }
    if (($p['raw'] ?? null) !== ($rn['r']['bodies'][$pid] ?? 'x')) { $rawOk = false; }
}
check($shapeOk && count($written) >= 1, 'hours_json records shape new and source legacy');
check($ownOk && count($written) >= 1, 'each row holds the answer for its OWN place id');
check($rawOk && count($written) >= 1,
    'raw is stored beside place, verbatim - a wrong inner name costs a re-normalise, not 301 calls');
check(strpos(json_encode($T), 'TESTKEY123') === false, 'the key reaches no stored column');
check(empty($rn['unparsed']) && updates($rn) === count(asked($rn)) && count(asked($rn)) >= 1,
    'one UPDATE per row asked, every one read and applied by the engine');

echo "\n  SWEEP - THE STRIKE RULE\n";
$st = run($hfile, 'sweep_strike');
$S  = $st['table'] ?? array();
check((int) ($S['b00000000001']['error_count'] ?? 0) === 1
      && ($S['b00000000001']['hours_status'] ?? '') === 'pending'
      && ($S['b00000000001']['last_error'] ?? '') === 'STATUS ZERO_RESULTS'
      && ($S['b00000000001']['fetched_at'] ?? null) !== null,
    'a data negative takes one strike, keeps the reason, and stamps fetched_at - Google did answer');
check((int) ($S['b00000000002']['error_count'] ?? 0) === 2
      && ($S['b00000000002']['hours_status'] ?? '') === 'pending',
    'a second strike is still pending');
check((int) ($S['b00000000003']['error_count'] ?? 0) === 3
      && ($S['b00000000003']['hours_status'] ?? '') === 'failed',
    'the third strike fails the row - the ceiling is 3, not 4');
check((int) ($S['b00000000004']['error_count'] ?? 0) === 4
      && ($S['b00000000004']['hours_status'] ?? '') === 'failed',
    'a failed row that misses again on its retry stays failed');
check(($S['b00000000005']['hours_status'] ?? '') === 'ok',
    'strikes do not stop the sweep - the row after them is still fetched');
$so = $st['r']['out'] ?? array();
check(($so['scanned'] ?? 0) === 5 && ($so['ok'] ?? 0) === 1 && ($so['struck'] ?? 0) === 4
      && ($so['failed'] ?? 0) === 2 && ($so['systemic'] ?? 1) === 0,
    'counts: five scanned, one ok, four struck, two ending failed');

echo "\n  SWEEP - SYSTEMIC FAILURE STOPS IT\n";
$sf1 = run($hfile, 'sweep_systemic_first');
check(count($sf1['http'] ?? array()) === 1,
    'REQUEST_DENIED stops the sweep: one call, the other two are never bought');
check(($sf1['r']['out']['systemic'] ?? 0) === 1 && ($sf1['r']['out']['scanned'] ?? 0) === 1
      && ($sf1['r']['out']['errors'] ?? null) === array('FATAL REQUEST_DENIED The provided API key is invalid.'),
    'and says why, with the verdict it acted on');
check(updates($sf1) === 0 && ($sf1['table'] ?? null) === ($sf1['r']['before'] ?? 'x'),
    'the systemic branch writes NOTHING - no strike, no stamp, the row keeps its place');
$sm = run($hfile, 'sweep_systemic_mid');
check(($sm['table']['d00000000001']['hours_status'] ?? '') === 'ok'
      && ($sm['table']['d00000000002'] ?? null) === ($sm['r']['before']['d00000000002'] ?? 'x')
      && ($sm['table']['d00000000003'] ?? null) === ($sm['r']['before']['d00000000003'] ?? 'x')
      && count($sm['http'] ?? array()) === 2,
    'a transport failure mid-run keeps what was written and touches nothing after it');
$sb = run($hfile, 'sweep_badjson');
check(count($sb['http'] ?? array()) === 1 && updates($sb) === 0
      && ($sb['r']['out']['errors'] ?? null) === array('BADJSON'),
    'a body that is not JSON stops the sweep and writes nothing');

echo "\n  SWEEP - EDGES\n";
$sn = run($hfile, 'sweep_new');
$np = payload($sn['table']['f00000000001'] ?? array());
check(($np['source'] ?? '') === 'new' && ($np['place'] ?? null) === ($sn['r']['body'] ?? 'x'),
    'on the New endpoint the payload records source new and keeps the Place verbatim');
$nd = run($hfile, 'sweep_nothing_due');
check(($nd['r']['out']['scanned'] ?? 1) === 0 && ($nd['r']['out']['errors'] ?? null) === array()
      && count($nd['http'] ?? array(1)) === 0 && updates($nd) === 0
      && ($nd['table'] ?? null) === ($nd['r']['before'] ?? 'x'),
    'a sweep with nothing due reads the queue, spends nothing and reports no error');
$bq = run($hfile, 'sweep_badquery');
check(($bq['r']['out']['errors'] ?? null) === array('BADQUERY') && count($bq['http'] ?? array(1)) === 0,
    'a failed queue read is BADQUERY and spends nothing');
$se = run($hfile, 'sweep_escape');
$ep = payload($se['table']['g00000000001'] ?? array());
check(($ep['place']['displayName']['text'] ?? '') === ($se['r']['name'] ?? '?') && empty($se['unparsed']),
    'quotes, backslashes and unicode survive the write - the payload is bound, not concatenated');

echo "\n  HARNESS\n";
check(count($CRASHES) === 0,
    'no scenario crashed inside the lifted code'
    . (count($CRASHES) ? ' - ' . implode('; ', $CRASHES) : ''));

/* ------------------------------------------------------------------ */

@unlink($hfile);
@unlink($upgrade);
@rmdir($abs . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes');
@rmdir($abs . 'wp-admin');
@rmdir($abs);
@rmdir($tmp);

printf("\n  %d passed, %d failed, %d total\n\n", $pass, $fail, $pass + $fail);
exit($fail === 0 ? 0 : 1);
