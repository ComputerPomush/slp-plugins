<?php
/**
 * diff-address-key.php  --  SLP Dealer Guard, v0.0.26 Part 2, step 2.
 *
 * THE GATE FOR THE PHP ADDRESS-KEY PORT.  rev34 s0.193.
 *
 * dealer_key is sha1(country|state|city_key|zip|street_key)[:12].  A PHP port
 * that disagrees with resolve-placeids.py does not throw.  It writes rows into
 * wp_avalon_dealer_places that never join to placeids.json, which is the one
 * artefact that cannot be re-derived for free.  So the port is not judged
 * against a reading of the Python, or against a second PHP opinion.  It is
 * judged row by row against the Python's own emitted output.
 *
 * THE ORACLE IS OUTSIDE THIS FILE AND OUTSIDE THE IMPLEMENTATION.
 * keyvectors.csv is written by `resolve-placeids.py --emit-keys` and carries,
 * for every vector, the RAW five fields and every intermediate the Python
 * computed from them.  This harness feeds the raw fields to the PHP and
 * compares all nine outputs.  Comparing only dealer_key would report THAT the
 * port diverged without saying WHERE -- sha1 destroys locality -- so every
 * intermediate is compared and reported in its own column.
 *
 * WHAT THE VECTOR SET COVERS, measured 2026-09-14.  The 638 real feed rows
 * exercise 11 of 22 suffixes, 4 of 8 directionals, 4 of 12 unit words, and
 * ZERO non-ASCII bytes -- so real data alone would go green against a port
 * with half the suffix map and no NFKD at all.  111 synthetic vectors drive
 * every branch the feeds miss, and the resolver fails its own self-check if a
 * branch is ever left undriven.  749 vectors total.
 *
 * FIRST RUN IS EXPECTED TO FAIL.  This file is delivered before the
 * implementation exists, so its first run reports a missing implementation and
 * exits 2.  That is the harness proving it can speak before it is ever in a
 * position to say PASS.  rev34 s0.195: a check that has only ever returned one
 * answer has proved nothing, and a green run sitting under a command labelled
 * "negative control" is the worse case.  Here the red run cannot be skipped,
 * because there is nothing to be green about yet.
 *
 * SECOND CONTROL.  --mutate=<column> perturbs the PHP result in one named
 * column on every 7th vector and prints how many it perturbed.  The harness
 * must then report that exact count in that exact column.  A comparator that
 * reports "something failed" is not proof that all nine columns are live.
 *
 * CSV READING IS NOT A DETAIL.  Python's csv module escapes by doubling the
 * quote character and never emits a backslash escape.  PHP's fgetcsv treats
 * backslash as an escape character unless told otherwise.  keyvectors.csv
 * contains exactly one backslash, in the note column of vector nfkd-nbsp, and
 * it is there deliberately: reader_selftest() asserts it survives the read.
 *
 * NO ext-intl, NO ext-mbstring, NO ext-dom.  The assistant container has none
 * of them and the server's availability of intl is unmeasured.  Nothing here
 * or in the implementation may depend on them.
 *
 * Destination:
 *   repo-relative  slp-plugins\test\diff-address-key.php
 *   local          D:\Temp\Projects\GitHub\slp-plugins\test\diff-address-key.php
 *
 * Usage:
 *   php test/diff-address-key.php
 *   php test/diff-address-key.php --vectors=build/placeid/keyvectors.csv
 *   php test/diff-address-key.php --mutate=street_key
 *
 * Exit codes:
 *   0  every vector agrees in every column
 *   1  differential failure -- the port disagrees with the oracle
 *   2  harness could not run (missing vectors, missing implementation,
 *      malformed contract).  Distinct from 1 on purpose: "the gate did not
 *      run" and "the gate ran and failed" are different facts.
 */

// The nine columns under comparison.  basis is included even though it is
// fully determined by the five before it: when basis matches and dealer_key
// does not, the fault is in the hashing, not the normalising, and that is
// worth being able to read off the table directly.
const COLUMNS = array(
    'street_key', 'unit', 'city_key', 'state', 'zip',
    'postal_country', 'country', 'basis', 'dealer_key',
);

const RAW_COLUMNS = array(
    'raw_address', 'raw_city', 'raw_state', 'raw_zip', 'raw_country',
);

const MUTATE_EVERY = 7;

function out($s = '') { fwrite(STDOUT, $s . PHP_EOL); }

function fail_setup($msg) {
    out();
    out('SETUP FAILURE');
    out('  ' . $msg);
    out();
    out('  exit 2 -- the gate did not run.  This is not a differential result.');
    exit(2);
}

function arg_value($name, $default = null) {
    foreach ($GLOBALS['argv'] as $a) {
        if (strpos($a, '--' . $name . '=') === 0) {
            return substr($a, strlen($name) + 3);
        }
    }
    return $default;
}

/**
 * Read the vector file.  escape is passed explicitly as "" because Python's
 * csv module has no backslash escaping and PHP's default does.  See the
 * docblock.
 */
function read_vectors($path) {
    $fh = fopen($path, 'r');
    if ($fh === false) {
        fail_setup('cannot open vectors file: ' . $path);
    }
    $header = fgetcsv($fh, 0, ',', '"', '');
    if ($header === false || $header === null) {
        fail_setup('vectors file is empty: ' . $path);
    }
    $rows = array();
    while (($line = fgetcsv($fh, 0, ',', '"', '')) !== false) {
        if ($line === array(null)) { continue; }
        if (count($line) !== count($header)) {
            fail_setup(sprintf(
                'vectors row %d has %d fields, header has %d -- the reader and '
                . 'the writer disagree about the format',
                count($rows) + 2, count($line), count($header)));
        }
        $rows[] = array_combine($header, $line);
    }
    fclose($fh);
    return array($header, $rows);
}

/**
 * Prove the reader parses what Python wrote, before trusting anything it
 * returns.  Each of these is a byte Python placed in the file on purpose.
 */
function reader_selftest($header, $rows) {
    $problems = array();

    foreach (array_merge(array('vid', 'class', 'note'), RAW_COLUMNS, COLUMNS) as $c) {
        if (!in_array($c, $header, true)) {
            $problems[] = 'header is missing column ' . $c;
        }
    }

    $by_vid = array();
    foreach ($rows as $r) { $by_vid[$r['vid']] = $r; }

    $classes = array();
    foreach ($rows as $r) {
        $classes[$r['class']] = isset($classes[$r['class']]) ? $classes[$r['class']] + 1 : 1;
    }
    if (!isset($classes['real']) || $classes['real'] < 1) {
        $problems[] = 'no real vectors present -- pointed at the wrong file?';
    }
    if (!isset($classes['synth']) || $classes['synth'] < 1) {
        $problems[] = 'no synthetic vectors present -- the branch coverage '
                    . 'that makes this gate meaningful is absent';
    }

    // The one backslash in the file.  If PHP's proprietary escaping is active
    // this field comes back altered and the reader is silently lossy.
    if (!isset($by_vid['nfkd-nbsp'])) {
        $problems[] = 'vector nfkd-nbsp absent -- cannot verify escape handling';
    } elseif (strpos($by_vid['nfkd-nbsp']['note'], '\\') === false) {
        $problems[] = 'the backslash in nfkd-nbsp.note did not survive the read '
                    . '-- fgetcsv escaping is active and every field is suspect';
    }

    // The two literal tabs Python wrote unquoted.
    if (!isset($by_vid['punct-tab'])) {
        $problems[] = 'vector punct-tab absent -- cannot verify tab handling';
    } elseif (substr_count($by_vid['punct-tab']['raw_address'], "\t") !== 2) {
        $problems[] = 'the tabs in punct-tab.raw_address did not survive the read';
    }

    // A vector whose raw bytes are non-ASCII.  If these arrive mangled the
    // NFKD comparison below is meaningless whatever it reports.
    if (!isset($by_vid['nfkd-acute'])) {
        $problems[] = 'vector nfkd-acute absent -- cannot verify UTF-8 passthrough';
    } elseif (strlen($by_vid['nfkd-acute']['raw_address'])
              === strlen(utf8_decode_len_probe($by_vid['nfkd-acute']['raw_address']))) {
        $problems[] = 'nfkd-acute.raw_address has no multi-byte sequence -- '
                    . 'UTF-8 did not survive the read';
    }

    return array($problems, $classes);
}

/** Byte-length probe that needs no mbstring: strip continuation bytes. */
function utf8_decode_len_probe($s) {
    $out = '';
    $n = strlen($s);
    for ($i = 0; $i < $n; $i++) {
        if ((ord($s[$i]) & 0xC0) !== 0x80) { $out .= $s[$i]; }
    }
    return $out;
}

// --------------------------------------------------------------------------

out('diff-address-key  --  PHP address-key port vs resolve-placeids.py');
out(str_repeat('=', 72));

$here      = dirname(__FILE__);
$repo_root = dirname($here);

$vectors_path = arg_value('vectors', $repo_root . '/build/placeid/keyvectors.csv');
$impl_path    = arg_value('impl', $repo_root . '/slp_avalon/inc/class.slp_avalon_addresskey.php');
$mutate       = arg_value('mutate', '');
$max_examples = (int) arg_value('max-examples', 12);

if ($mutate !== '' && !in_array($mutate, COLUMNS, true)) {
    fail_setup('--mutate=' . $mutate . ' is not one of: ' . implode(', ', COLUMNS));
}

out('PHP       ' . PHP_VERSION
    . '  short_open_tag=' . (ini_get('short_open_tag') ? 'On' : 'Off')
    . '  intl=' . (extension_loaded('intl') ? 'LOADED' : 'absent')
    . '  mbstring=' . (extension_loaded('mbstring') ? 'LOADED' : 'absent'));

if (!file_exists($vectors_path)) {
    fail_setup('vectors not found: ' . $vectors_path . PHP_EOL
        . '  Generate with:  python build/resolve-placeids.py --feeds <dir> '
        . '--out build/placeid --emit-keys');
}
out('VECTORS   ' . $vectors_path);
out('          md5 ' . md5_file($vectors_path) . '  bytes ' . filesize($vectors_path));

// ---- the implementation.  Absent on the first run, by design. -------------
if (!file_exists($impl_path)) {
    out('IMPL      ' . $impl_path);
    out('          NOT PRESENT');
    out();
    out('The implementation has not been written yet.  This is the expected');
    out('first result: the gate is delivered before the code it gates, so its');
    out('failure path is exercised before it can ever report PASS.');
    out();
    out('Contract the implementation must satisfy:');
    out('  class   SLP_Avalon_AddressKey');
    out('  method  public static function vector( array $raw ): array');
    out('  input   keys raw_address, raw_city, raw_state, raw_zip, raw_country');
    out('  output  keys ' . implode(', ', COLUMNS));
    out('  deps    none -- no WordPress, no intl, no mbstring, no dom');
    fail_setup('no implementation at ' . $impl_path);
}
out('IMPL      ' . $impl_path);
out('          md5 ' . md5_file($impl_path) . '  bytes ' . filesize($impl_path));

require_once $impl_path;

if (!class_exists('SLP_Avalon_AddressKey')) {
    fail_setup('class SLP_Avalon_AddressKey not defined by ' . $impl_path);
}
if (!method_exists('SLP_Avalon_AddressKey', 'vector')) {
    fail_setup('SLP_Avalon_AddressKey::vector() not defined');
}

// ---- read and vet the oracle ---------------------------------------------
list($header, $rows) = read_vectors($vectors_path);
list($problems, $classes) = reader_selftest($header, $rows);

out();
out('READER SELF-TEST');
out(sprintf('  %-52s %s %s', 'vectors read', 'PASS', count($rows) . ' rows'));
$cls = array();
foreach ($classes as $k => $v) { $cls[] = $k . '=' . $v; }
out(sprintf('  %-52s %s %s', 'both vector classes present',
    (isset($classes['real']) && isset($classes['synth'])) ? 'PASS' : 'FAIL',
    implode(' ', $cls)));
out(sprintf('  %-52s %s', 'escape/tab/utf-8 survive the read',
    empty($problems) ? 'PASS' : 'FAIL'));
if (!empty($problems)) {
    foreach ($problems as $p) { out('      ' . $p); }
    fail_setup('the reader is lossy -- no differential result can be trusted');
}

// ---- the differential -----------------------------------------------------
$mutated_vids = array();
$fail_counts  = array_fill_keys(COLUMNS, 0);
$examples     = array();
$compared     = 0;
$threw        = array();

foreach ($rows as $i => $r) {
    $raw = array();
    foreach (RAW_COLUMNS as $c) { $raw[$c] = $r[$c]; }

    try {
        $got = SLP_Avalon_AddressKey::vector($raw);
    } catch (Throwable $e) {
        $threw[] = $r['vid'] . ': ' . get_class($e) . ' ' . $e->getMessage();
        continue;
    }
    if (!is_array($got)) {
        $threw[] = $r['vid'] . ': vector() returned ' . gettype($got) . ', expected array';
        continue;
    }

    if ($mutate !== '' && ($i % MUTATE_EVERY) === 0) {
        $got[$mutate] = (isset($got[$mutate]) ? $got[$mutate] : '') . 'ZZ';
        $mutated_vids[] = $r['vid'];
    }

    $compared++;
    foreach (COLUMNS as $c) {
        $want = $r[$c];
        $have = array_key_exists($c, $got) ? $got[$c] : '<<MISSING KEY>>';
        if ($have !== $want) {
            $fail_counts[$c]++;
            if (count($examples) < $max_examples) {
                $examples[] = array($r['vid'], $r['class'], $c, $want, $have,
                                    $r['raw_address'], $r['raw_state'], $r['raw_zip']);
            }
        }
    }
}

out();
out('DIFFERENTIAL');
if ($mutate !== '') {
    out('  MUTATION ACTIVE  column=' . $mutate
        . '  perturbed=' . count($mutated_vids)
        . '  (every ' . MUTATE_EVERY . 'th vector)');
    out('  This run MUST report exactly ' . count($mutated_vids)
        . ' failure(s) in ' . $mutate . ' and 0 elsewhere.');
}
out(sprintf('  %-22s %s', 'vectors compared', $compared));
out(sprintf('  %-22s %s', 'threw or malformed', count($threw)));
foreach ($threw as $t) { out('      ' . $t); }

out();
out(sprintf('  %-18s %10s %10s', 'column', 'agree', 'disagree'));
out('  ' . str_repeat('-', 40));
$total_fail = 0;
foreach (COLUMNS as $c) {
    $total_fail += $fail_counts[$c];
    out(sprintf('  %-18s %10d %10d%s', $c, $compared - $fail_counts[$c],
        $fail_counts[$c], $fail_counts[$c] ? '  <--' : ''));
}

if (!empty($examples)) {
    out();
    out('  FIRST ' . count($examples) . ' DISAGREEMENT(S)');
    foreach ($examples as $e) {
        out(sprintf('    %-18s %-6s %s', $e[0], $e[1], $e[2]));
        out(sprintf('      python %s', var_export($e[3], true)));
        out(sprintf('      php    %s', var_export($e[4], true)));
        out(sprintf('      raw    address=%s state=%s zip=%s',
            var_export($e[5], true), var_export($e[6], true), var_export($e[7], true)));
    }
}

out();
if (count($threw) > 0) {
    out('RESULT  the implementation threw on ' . count($threw) . ' vector(s).');
    out('        exit 1');
    exit(1);
}
if ($total_fail === 0) {
    out('RESULT  ' . $compared . ' vectors, ' . count(COLUMNS)
        . ' columns, 0 disagreements.');
    out('        The PHP port reproduces the Python oracle exactly.');
    out('        exit 0');
    exit(0);
}
out('RESULT  ' . $total_fail . ' disagreement(s) across '
    . count(array_filter($fail_counts)) . ' column(s).');
out('        exit 1');
exit(1);
