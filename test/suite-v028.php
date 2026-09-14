<?php
/**
 * suite-v028.php - validates the v0.0.26 Part 3c place ID importer.
 *
 * WHAT IS EXECUTED, AND WHY IT IS EXECUTED RATHER THAN READ
 *
 * avalon_places_import and avalon_places_cli are lifted out of the class
 * file verbatim, together with avalon_hours_table and the const block, and
 * wrapped in a test class so self:: resolves exactly as it does in
 * production and no source is rewritten to make it testable.
 *
 * $wpdb->query IS A SMALL REAL EXECUTOR, NOT A RECORDER. It parses the
 * FINISHED statement - after prepare() has done a real %s substitution -
 * into assignments and conditions, applies the conditions to an in-memory
 * table, and returns rows affected. A recorder that returns 1 agrees with
 * every build, including one whose WHERE clause has been deleted. A
 * statement it cannot parse is a failure, never a pass: a double that
 * shrugs at SQL it does not understand is a double that cannot see a
 * defect.
 *
 * s0.231 - THE NEVER-OVERWRITE ASSERTION NEEDS A DIVERGENT FIXTURE. The
 * guard under test is AND place_status = 'pending' in the UPDATE. It
 * cannot be seen with an already-ok row, because the PHP branch above
 * counts already_ok and continues before the statement ever runs, so a
 * build with the WHERE clause stripped out leaves that row alone too and
 * the control passes while asserting nothing - s0.214, third time. The
 * only state that can see it is a snapshot that disagrees with the row:
 * get_results reports pending, the table holds ok. That is the race the
 * clause exists for. The double therefore keeps the snapshot and the
 * table as two separate fixtures and the race scenario sets them apart
 * deliberately. Do not "simplify" them back into one array.
 *
 * THE FIXTURE IS SYNTHETIC AND SELF-CONTAINED. It does not read the real
 * placeids.json, which is private and lives outside this repo, so this
 * suite runs in a fresh clone. The real file's arithmetic - 302 in, 300
 * matched, 2 not queued, 1 left - is an environment fact and is asserted
 * by the --dry-run on the server, not here. Every resolved_utc in the
 * fixture is in 2021 while current_time is pinned to 2026, so a build
 * that stamps place_checked_at with the import clock cannot accidentally
 * agree with one that stamps it from the file.
 *
 * ONE PROCESS PER SCENARIO. WP_CLI::error() halts in production; the
 * double models the halt as a throw so the scenario can still report what
 * had been written before it fired, and each scenario is a separate php
 * invocation regardless, so no scenario inherits another's table.
 *
 * Usage:
 *   php suite-v028.php <class.slp_avalon.php>
 */

$clsPath = $argv[1] ?? 'build/out28/class.slp_avalon.php';

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

/* Narrow on purpose: the importer needs the table name and nothing else
   from the hours block, and a wider lift would drag in dbDelta. */
$table = span($code,
    '        public static function avalon_hours_table(){',
    '        public static function avalon_hours_install(){');

$import = span($code,
    '        public function avalon_places_import(',
    '        public function avalon_places_cli(');

/* The CLI runs to the end of the class, so there is no following
   declaration to bound it. The class tail is exactly "\r\n    }\r\n}" -
   the brace that closes avalon_places_cli is KEPT, the two that close the
   class and the class_exists guard are not. Matched as an exact byte
   string rather than a trailing-brace regex, which would eat one too
   many. */
$cliStart = strpos($code, '        public function avalon_places_cli(');
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

foreach (array('consts' => $consts, 'table' => $table,
               'import' => $import, 'cli' => $cli) as $n => $v) {
    if ($v === false) {
        fwrite(STDERR, "cannot lift {$n} from {$clsPath}\n");
        exit(2);
    }
}

/* ------------------------------------------------------------------ */
/* Fixture files.                                                      */
/* ------------------------------------------------------------------ */

$tmp = sys_get_temp_dir() . '/slp28_' . getmypid();
@mkdir($tmp, 0777, true);

/* Ten entries, every branch of the reader, and no two counts equal - so
   one assertion cannot be satisfied by the number another is watching. */
$fixture = array(
    'tool'        => 'resolve-placeids',
    'version'     => '1.4.2',
    'written_utc' => '2026-09-14T04:24:43Z',
    'note'        => 'synthetic fixture for suite-v028, not a real cache',
    'dealers'     => array(
        /* pending -> imported, stamp parsed from a date that is not now */
        'k1aaaaaaaaaa' => array('place_id' => 'ChIJ_ONE',    'resolved_utc' => '2021-03-04T05:06:07Z'),
        /* two keys, one place. Legal, counted, never refused. */
        'k2bbbbbbbbbb' => array('place_id' => 'ChIJ_SHARED', 'resolved_utc' => '2021-03-04T05:06:08Z'),
        'k3cccccccccc' => array('place_id' => 'ChIJ_SHARED', 'resolved_utc' => '2021-03-04T05:06:09Z'),
        /* row is already ok -> already_ok, left alone */
        'k4dddddddddd' => array('place_id' => 'ChIJ_FOUR',   'resolved_utc' => '2021-03-04T05:06:10Z'),
        /* row is failed -> other_status, left alone. import is not reset. */
        'k5eeeeeeeeee' => array('place_id' => 'ChIJ_FIVE',   'resolved_utc' => '2021-03-04T05:06:11Z'),
        /* unparsable stamp -> imported anyway, counted as undated */
        'k8hhhhhhhhhh' => array('place_id' => 'ChIJ_EIGHT',  'resolved_utc' => 'not a date at all'),
        /* rejected: embedded whitespace. The key stays uncovered. */
        'k7gggggggggg' => array('place_id' => 'ChIJ HAS SPACE', 'resolved_utc' => '2021-03-04T05:06:12Z'),
        /* rejected: empty */
        'kxiiiiiiiiii' => array('place_id' => '',            'resolved_utc' => '2021-03-04T05:06:13Z'),
        /* rejected: one character wider than the column */
        'kyjjjjjjjjjj' => array('place_id' => str_repeat('X', 192), 'resolved_utc' => '2021-03-04T05:06:14Z'),
        /* resolved, but this dealer is not in this site's queue */
        'kzkkkkkkkkkk' => array('place_id' => 'ChIJ_ZED',    'resolved_utc' => '2021-03-04T05:06:15Z'),
    ),
);
$fixPath = $tmp . '/placeids-fixture.json';
file_put_contents($fixPath, json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
$fixMd5 = md5_file($fixPath);

file_put_contents($tmp . '/not-json.json', "this is not json\n");
file_put_contents($tmp . '/no-dealers.json', json_encode(array('tool' => 'x')));

/* ------------------------------------------------------------------ */
/* Harness.                                                            */
/* ------------------------------------------------------------------ */

$head = <<<'PHPHEAD'
<?php
$GLOBALS['sql']      = array();
$GLOBALS['unparsed'] = array();
$GLOBALS['logged']   = array();
$GLOBALS['cli']      = array();
$GLOBALS['snapshot'] = array();   /* what get_results reports */
$GLOBALS['table']    = array();   /* what the rows actually are */

define('ARRAY_A', 'ARRAY_A');

class SLP_CLI_Halt extends Exception {}

class SLP_Test_wpdb {
    public $prefix = 'wp_';

    /* A real substitution, so every assertion below reads the finished
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

    public function get_results($sql, $mode = null) {
        $GLOBALS['sql'][] = $sql;
        if (strpos($sql, 'address_key, place_status') !== false) {
            $out = array();
            foreach ($GLOBALS['snapshot'] as $k => $status) {
                $out[] = array('address_key' => $k, 'place_status' => $status);
            }
            return $out;
        }
        return array();
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
        $sets  = self::pairs($m[2]);
        $conds = self::pairs($m[3]);
        if ($sets === false || $conds === false) {
            $GLOBALS['unparsed'][] = $sql;
            return false;
        }
        $n = 0;
        foreach ($GLOBALS['table'] as $key => $row) {
            $hit = true;
            foreach ($conds as $col => $want) {
                $have = ($col === 'address_key') ? $key
                      : (array_key_exists($col, $row) ? $row[$col] : null);
                if ((string) $have !== (string) $want) { $hit = false; break; }
            }
            if (! $hit) { continue; }
            foreach ($sets as $col => $val) {
                $GLOBALS['table'][$key][$col] = $val;
            }
            $n++;
        }
        return $n;
    }

    /* col = 'value' pairs. The count of pairs found must equal the count
       of '=' signs present, or the clause was misread and the caller is
       told so rather than handed a partial parse. */
    private static function pairs($clause) {
        preg_match_all("/([a-z_]+) = '([^']*)'/", $clause, $mm, PREG_SET_ORDER);
        if (count($mm) !== substr_count($clause, '=')) { return false; }
        $out = array();
        foreach ($mm as $p) { $out[$p[1]] = $p[2]; }
        return $out;
    }
}
$wpdb = new SLP_Test_wpdb();

function current_time($type, $gmt = 0) { return '2026-09-14 00:00:00'; }

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

PHPHEAD;

$harness  = $head;
$harness .= "\$scenario = \$argv[1];\n";
$harness .= "\$FIX  = " . var_export($fixPath, true) . ";\n";
$harness .= "\$FMD5 = " . var_export($fixMd5, true) . ";\n";
$harness .= "\$TMP  = " . var_export($tmp, true) . ";\n\n";

$harness .= "class SLP_Avalon {\n";
$harness .= $consts . "\n";
$harness .= $table . "\n";
$harness .= $import . "\n";
$harness .= $cli . "\n";
$harness .= <<<'PHPTAIL'
    private static function log($e) { $GLOBALS['logged'][] = array('log', $e); }
    private function avalon_import_log($r) { $GLOBALS['logged'][] = array('import', $r); }
    public function avalon_flush_import_log($final = true) { $GLOBALS['logged'][] = array('flush', $final); }
}

PHPTAIL;

$harness .= <<<'PHPRUN'
$o = new SLP_Avalon();

/* The ordinary state: snapshot and table agree. */
function seed_queue() {
    $rows = array(
        'k1aaaaaaaaaa' => 'pending',
        'k2bbbbbbbbbb' => 'pending',
        'k3cccccccccc' => 'pending',
        'k4dddddddddd' => 'ok',        /* already resolved */
        'k5eeeeeeeeee' => 'failed',    /* struck out, import must not reset it */
        'k6ffffffffff' => 'pending',   /* no entry in the file at all */
        'k7gggggggggg' => 'pending',   /* entry rejected, so still uncovered */
        'k8hhhhhhhhhh' => 'pending',
    );
    $GLOBALS['snapshot'] = $rows;
    $GLOBALS['table']    = array();
    foreach ($rows as $k => $s) {
        $GLOBALS['table'][$k] = array(
            'place_status'     => $s,
            'place_id'         => ($s === 'ok') ? 'ChIJ_ORIGINAL' : null,
            'place_checked_at' => null,
            'updated_at'       => null,
        );
    }
}

$r = null;
$halt = '';
seed_queue();

try {
    switch ($scenario) {
        case 'dry':
            $r = $o->avalon_places_import($FIX, false, '');
            break;

        case 'apply':
            $r = $o->avalon_places_import($FIX, true, '');
            break;

        case 'rerun':
            $o->avalon_places_import($FIX, true, '');
            /* The second pass sees the table the first left behind. */
            foreach ($GLOBALS['table'] as $k => $row) {
                $GLOBALS['snapshot'][$k] = $row['place_status'];
            }
            $GLOBALS['sql'] = array();
            $GLOBALS['logged'] = array();
            $r = $o->avalon_places_import($FIX, true, '');
            break;

        /* s0.231. The snapshot says pending; the row is already ok and
           carries a place_id somebody paid for. Only the WHERE clause can
           refuse this write - the PHP branch above cannot see it. */
        case 'race':
            $GLOBALS['snapshot'] = array('k1aaaaaaaaaa' => 'pending');
            $GLOBALS['table']    = array('k1aaaaaaaaaa' => array(
                'place_status'     => 'ok',
                'place_id'         => 'ChIJ_ORIGINAL',
                'place_checked_at' => '2019-01-01 00:00:00',
                'updated_at'       => '2019-01-01 00:00:00',
            ));
            $r = $o->avalon_places_import($FIX, true, '');
            break;

        case 'pin_good':
            $r = $o->avalon_places_import($FIX, true, $FMD5);
            break;

        case 'pin_wrong':
            $r = $o->avalon_places_import($FIX, true, str_repeat('0', 32));
            break;

        case 'pin_case':
            $r = $o->avalon_places_import($FIX, true, strtoupper($FMD5));
            break;

        case 'missing_file':
            $r = $o->avalon_places_import($TMP . '/nothing-here.json', true, '');
            break;

        case 'not_json':
            $r = $o->avalon_places_import($TMP . '/not-json.json', true, '');
            break;

        case 'no_dealers':
            $r = $o->avalon_places_import($TMP . '/no-dealers.json', true, '');
            break;

        case 'cli_no_file':
            $o->avalon_places_cli(array('import'), array());
            break;

        case 'cli_no_pin':
            $o->avalon_places_cli(array('import'), array('file' => $FIX));
            break;

        case 'cli_dry':
            $o->avalon_places_cli(array('import'), array('file' => $FIX, 'dry-run' => true));
            break;

        case 'cli_apply':
            $o->avalon_places_cli(array('import'),
                array('file' => $FIX, 'expect-md5' => $FMD5));
            break;

        case 'cli_bad_path':
            $o->avalon_places_cli(array('import'),
                array('file' => $TMP . '/nothing-here.json', 'expect-md5' => $FMD5));
            break;

        default:
            fwrite(STDERR, "unknown scenario {$scenario}\n");
            exit(2);
    }
} catch (SLP_CLI_Halt $e) {
    $halt = $e->getMessage();
}

echo json_encode(array(
    'out'      => $r,
    'halt'     => $halt,
    'sql'      => $GLOBALS['sql'],
    'unparsed' => $GLOBALS['unparsed'],
    'logged'   => $GLOBALS['logged'],
    'cli'      => $GLOBALS['cli'],
    'table'    => $GLOBALS['table'],
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

function writes($r)
{
    $n = 0;
    foreach (($r['sql'] ?? array()) as $s) {
        if (stripos($s, 'UPDATE ') === 0) { $n++; }
    }
    return $n;
}

function logs($r)
{
    $n = 0;
    foreach (($r['logged'] ?? array()) as $l) {
        if (($l[0] ?? '') === 'import' || ($l[0] ?? '') === 'flush') { $n++; }
    }
    return $n;
}

/* ------------------------------------------------------------------ */
/* Assertions.                                                         */
/* ------------------------------------------------------------------ */

printf("\n  suite-v028  artefact %s\n", $clsPath);
printf("              md5 %s  bytes %s\n",
    md5($code), number_format(strlen($code)));

echo "\n  READER ARITHMETIC (dry run)\n";
$d = run($hfile, 'dry');
$o = $d['out'] ?? array();
check(empty($d['unparsed']), 'every statement issued was parseable');
check(($o['keys_in_file'] ?? -1) === 10, 'keys_in_file counts every entry in the file');
check(($o['rejected'] ?? -1) === 3,      'rejected counts empty, whitespace and over-width IDs');
check(($o['not_in_queue'] ?? -1) === 1,  'not_in_queue counts a resolved dealer this site does not carry');
check(($o['matched'] ?? -1) === 6,       'matched counts usable keys present in the queue');
check(($o['already_ok'] ?? -1) === 1,    'already_ok counts a row that had resolved');
check(($o['other_status'] ?? -1) === 1,  'other_status counts a failed row, which import does not reset');
check(($o['imported'] ?? -1) === 4,      'a dry run reports what it would import');
check(($o['undated'] ?? -1) === 1,       'undated counts an unparsable resolved_utc');
check(($o['distinct'] ?? -1) === 3,      'distinct counts place IDs, not keys');
check(($o['shared'] ?? -1) === 1,        'shared counts place IDs held by more than one key');
check(($o['no_place_id'] ?? -1) === 2,   'no_place_id counts pending keys the file leaves uncovered');
check(isset($o['groups']['ChIJ_SHARED'])
      && count($o['groups']['ChIJ_SHARED']) === 2,
    'the shared group names both dealer keys');
check(($o['md5'] ?? '') !== '' && ($o['bytes'] ?? 0) > 0,
    'the dry run reports the hash and length of what it read');

echo "\n  A DRY RUN WRITES NOTHING\n";
check(writes($d) === 0,  'no UPDATE is issued');
check(logs($d) === 0,    'no import-log record is written');
/* array_key_exists, not ??. A row whose place_id is legitimately null is
   exactly the state under test here, and ?? cannot tell that apart from a
   column the double never wrote - the first revision of this assertion
   read ?? 'x' and failed against a correct build. Every row is checked,
   because "one row did not move" is a weaker claim than the one the
   section heading makes. */
$untouched = true;
foreach (($d['table'] ?? array()) as $k => $row) {
    $wantId = ($k === 'k4dddddddddd') ? 'ChIJ_ORIGINAL' : null;
    $wantSt = ($k === 'k4dddddddddd') ? 'ok'
            : (($k === 'k5eeeeeeeeee') ? 'failed' : 'pending');
    if (! array_key_exists('place_id', $row) || $row['place_id'] !== $wantId) { $untouched = false; }
    if (($row['place_status'] ?? '') !== $wantSt) { $untouched = false; }
    if (! array_key_exists('place_checked_at', $row) || $row['place_checked_at'] !== null) { $untouched = false; }
}
check($untouched && count($d['table'] ?? array()) === 8,
    'every row in the table is untouched');

echo "\n  APPLY\n";
$a = run($hfile, 'apply');
$ao = $a['out'] ?? array();
check(empty($a['unparsed']), 'every statement issued was parseable');
check(($ao['imported'] ?? -1) === 4, 'imported counts rows the statement actually moved');
check(($ao['raced'] ?? -1) === 0,    'nothing raced when the snapshot and the rows agree');
check(writes($a) === 4,              'exactly one UPDATE per imported row');
check(($a['table']['k1aaaaaaaaaa']['place_status'] ?? '') === 'ok',
    'a pending row is moved to ok');
check(($a['table']['k1aaaaaaaaaa']['place_id'] ?? '') === 'ChIJ_ONE',
    'the place ID from the file lands in the row');
check(($a['table']['k6ffffffffff']['place_status'] ?? '') === 'pending',
    'a key the file does not cover is left pending');
check(($a['table']['k5eeeeeeeeee']['place_status'] ?? '') === 'failed',
    'a failed row is not resurrected by an import');
check(($a['table']['k4dddddddddd']['place_id'] ?? '') === 'ChIJ_ORIGINAL',
    'a row that had resolved keeps the ID it already had');
check(logs($a) === 2, 'applying writes one import-log record and flushes it once');

echo "\n  place_checked_at IS THE RESOLUTION, NOT THE IMPORT\n";
check(($a['table']['k1aaaaaaaaaa']['place_checked_at'] ?? '') === '2021-03-04 05:06:07',
    'place_checked_at carries the file resolved_utc, converted to GMT');
check(($a['table']['k1aaaaaaaaaa']['updated_at'] ?? '') === '2026-09-14 00:00:00',
    'updated_at carries the import clock, which is a different column');
check(($a['table']['k8hhhhhhhhhh']['place_checked_at'] ?? '') === '2026-09-14 00:00:00',
    'an unparsable stamp falls back to the import clock');
check(($a['table']['k8hhhhhhhhhh']['place_id'] ?? '') === 'ChIJ_EIGHT',
    'an unparsable stamp does not cost the row its place ID');

echo "\n  NEVER RE-RESOLVE\n";
$rr = run($hfile, 'rerun');
$ro = $rr['out'] ?? array();
check(($ro['imported'] ?? -1) === 0, 'a second import moves nothing');
check(($ro['already_ok'] ?? -1) === 5,
    'the rows the first pass resolved are counted already_ok by the second');
check(writes($rr) === 0, 'a second import issues no UPDATE at all');

$rc = run($hfile, 'race');
$rco = $rc['out'] ?? array();
check(empty($rc['unparsed']), 'the race statement was parseable');
check(($rc['table']['k1aaaaaaaaaa']['place_id'] ?? '') === 'ChIJ_ORIGINAL',
    's0.231 snapshot says pending, row says ok: the WHERE clause refuses the write');
check(($rc['table']['k1aaaaaaaaaa']['place_checked_at'] ?? '') === '2019-01-01 00:00:00',
    's0.231 the refused row keeps its own place_checked_at');
check(($rco['raced'] ?? -1) === 1,
    's0.231 a statement that matched nothing is counted as raced, not as imported');
check(($rco['imported'] ?? -1) === 0,
    's0.231 a refused write is never counted as an import');

echo "\n  THE FILE IS PINNED\n";
$pg = run($hfile, 'pin_good');
check((($pg['out']['error'] ?? 'x') === '') && ($pg['out']['imported'] ?? 0) === 4,
    'a correct pin lets the import run');
$pw = run($hfile, 'pin_wrong');
check(strpos($pw['out']['error'] ?? '', 'md5 ') === 0,
    'a wrong pin reports the hash it found and the hash it wanted');
check(writes($pw) === 0 && logs($pw) === 0,
    'a wrong pin writes nothing at all');
$pc = run($hfile, 'pin_case');
check((($pc['out']['error'] ?? 'x') === '') && ($pc['out']['imported'] ?? 0) === 4,
    'an upper-case pin is accepted, because a hash is not case');

echo "\n  BAD INPUT IS REFUSED, NOT GUESSED\n";
$mf = run($hfile, 'missing_file');
check(strpos($mf['out']['error'] ?? '', 'cannot read') === 0 && writes($mf) === 0,
    'an unreadable path is an error and writes nothing');
$nj = run($hfile, 'not_json');
check(($nj['out']['error'] ?? '') !== '' && writes($nj) === 0,
    'a file that is not JSON is an error and writes nothing');
$nd = run($hfile, 'no_dealers');
check(strpos($nd['out']['error'] ?? '', 'no dealers object') === 0 && writes($nd) === 0,
    'JSON without a dealers object is an error and writes nothing');

echo "\n  THE CLI REFUSES BEFORE IT WRITES\n";
$cnf = run($hfile, 'cli_no_file');
check(strpos($cnf['halt'] ?? '', '--file') === 0,
    'import without --file halts on --file');
check(writes($cnf) === 0, 'import without --file writes nothing');
$cnp = run($hfile, 'cli_no_pin');
check(strpos($cnp['halt'] ?? '', '--expect-md5') === 0,
    'a write without --expect-md5 halts on --expect-md5');
check(writes($cnp) === 0 && logs($cnp) === 0,
    'a write without --expect-md5 writes nothing');
$cbp = run($hfile, 'cli_bad_path');
check(($cbp['halt'] ?? '') !== '' && writes($cbp) === 0,
    'the CLI surfaces a reader error as a halt rather than reporting success');

echo "\n  THE CLI REPORTS WHAT IT DID\n";
$cd = run($hfile, 'cli_dry');
$cdText = '';
foreach (($cd['cli'] ?? array()) as $l) { $cdText .= $l[1] . "\n"; }
check(writes($cd) === 0 && logs($cd) === 0, '--dry-run through the CLI writes nothing');
check(strpos($cdText, 'dry run, nothing written') !== false,
    '--dry-run says so rather than reporting an import');
check(strpos($cdText, 'ChIJ_SHARED') !== false
      && strpos($cdText, 'k2bbbbbbbbbb') !== false,
    '--dry-run prints the shared place IDs and the keys under them');
check(strpos($cdText, 'would import 4') !== false,
    '--dry-run labels the count as would-import');
$ca = run($hfile, 'cli_apply');
$caText = '';
foreach (($ca['cli'] ?? array()) as $l) { $caText .= $l[1] . "\n"; }
check(writes($ca) === 4 && logs($ca) === 2,
    'a pinned CLI write imports and logs');
check(strpos($caText, 'import complete') !== false,
    'the apply reports completion');
check(strpos($caText, 'ChIJ_SHARED ') === false,
    'the apply does not reprint the shared groups');

echo "\n  DECLARATIONS\n";
check(substr_count($code, 'public function avalon_places_import(') === 1,
    'avalon_places_import is declared exactly once');
check(strpos($code, "if ( 'import' === \$sub ) {") !== false,
    "the CLI dispatches on 'import'");
check(strpos($code, "'seed'") !== false && strpos($code, "'reset'") !== false,
    'the Part 3a subcommands survive alongside it');

printf("\n  %d passed, %d failed, %d total\n\n", $pass, $fail, $pass + $fail);
exit($fail === 0 ? 0 : 1);
