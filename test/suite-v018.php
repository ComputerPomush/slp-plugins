<?php
/**
 * SLP Dealer Guard - v0.0.18 suite. The REST options strip.
 *
 * WHAT THIS RELEASE CHANGES, AND HOW EACH PART IS REACHED HERE.
 *
 *   1. A filter on rest_post_dispatch at priority 999, accepted_args 3, that
 *      removes google_server_key and google_geocode_key from Store Locator
 *      Plus REST responses served to a caller without manage_slp_user.
 *   2. .gitattributes covers the tooling. Asserted by Publish-Step14, not
 *      here - it is not in the artefact this suite reads.
 *
 * THE FIXTURE IS THE MEASURED SHAPE. Aura DEV, 2026-09-05:
 * /wp-json/store-locator-plus/v1/options/all returns HTTP 201, 10,539 bytes,
 * md5 bfe3dbb49153e5046841e7e59006f300, structured
 *
 *     store-locator-le -> settings -> options -> flat map of 120 slugs
 *
 * with every leaf at path depth four, no lists, 118 strings and 2 ints. The
 * fixture reproduces that shape with 120 keys and a synthetic key value. The
 * real key is not in this file and must not be put in it.
 *
 * WHY A WIRING TEST AND A LIVE CURL BOTH. rev16 s0.38: suite-v015 scored 33/33
 * against a build whose hook was mis-wired, because testing a callback directly
 * cannot detect a registration defect. avalon_rest_strip_keys() takes $server
 * and $request as OPTIONAL so that a mis-wired registration cannot fatal every
 * REST response on a live site - which means it fails silently open on the
 * secret. So the registration is parsed out of the artefact text and asserted
 * here, driven through a reproduction of apply_filters() with the accepted_args
 * the artefact actually declares, and confirmed after deploy by an anonymous
 * curl. A green score on this file is not evidence that the filter ran.
 *
 * NEGATIVE CONTROL, decision 20 and rev16 s0.51. The control target is a TAG,
 * not the working tree, so it survives staging and commit:
 *
 *     git show v0.0.17:slp_avalon/inc/class.slp_avalon.php > %TEMP%\ctl.php
 *     php test/suite-v018.php %TEMP%\ctl.php
 *     php test/suite-v018.php build/out18/class.slp_avalon.php
 *
 * v0.0.17 registers ZERO REST hooks, so every [v18] case must fail against it
 * and every [both] case must hold. The required control score is asserted by
 * Publish-Step14. 26/26 against the control means the suite is not testing this
 * release; a score below the expected floor means it is failing for the wrong
 * reason. Both look identical at the exit-code level.
 *
 * rev14 s8: an assertion of the form "nothing happened" passes trivially when
 * the code under test is absent. Three cases here are of that shape - the
 * non-SLP route, the manage_slp_user holder, and the untouched-response check.
 * Each is paired with a predicate that requires the callback to have executed:
 * a sentinel returned when the method is missing, or the removal counter.
 */

declare(strict_types=1);

$artefact = $argv[1] ?? __DIR__ . '/../build/out18/class.slp_avalon.php';
if (!is_readable($artefact)) {
    fwrite(STDERR, "cannot read $artefact\n");
    exit(2);
}
$src = file_get_contents($artefact);

// ---------------------------------------------------------------- extract ---

/**
 * Lift whole method bodies out of the artefact by name.
 *
 * Verbatim from suite-v015, suite-v016 and suite-v017: token_get_all() rather
 * than brace counting, because braces inside strings and comments arrive inside
 * a single token. Missing methods are skipped rather than fatal, so a negative
 * control reports every failed assertion instead of dying on the first absence.
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

$WANTED = [
    'avalon_rest_strip_keys',
    'avalon_rest_strip_walk',
    'avalon_rest_protected_slugs',
];

[$methods, $found] = extract_methods($src, $WANTED);
$missing = array_values(array_diff($WANTED, $found));

// ------------------------------------------------------ WordPress stubs ---
//
// Only what the callback touches. WP_REST_Response records whether set_data()
// was called, which is how the "walked but wrote nothing" case is made
// non-trivial.

class WP_REST_Server {}

class WP_REST_Response
{
    private $data;
    private $status;
    public $set_data_calls = 0;

    public function __construct($data = null, int $status = 200)
    {
        $this->data   = $data;
        $this->status = $status;
    }
    public function get_data()          { return $this->data; }
    public function get_status(): int   { return $this->status; }
    public function set_data($data)     { $this->data = $data; $this->set_data_calls++; }
}

class WP_REST_Request
{
    private $route;
    public function __construct(string $route) { $this->route = $route; }
    public function get_route()                { return $this->route; }
}

class WP_Error
{
    public $code;
    public function __construct($code = 'err') { $this->code = $code; }
}

$GLOBALS['SUITE_CAN'] = false;

function current_user_can($cap)
{
    return ($cap === 'manage_slp_user') ? (bool) $GLOBALS['SUITE_CAN'] : false;
}

// ------------------------------------------------------------- the shim ---

$shim = sys_get_temp_dir() . '/suite-v018-shim-' . getmypid() . '.php';
file_put_contents($shim, "<?php\nclass Suite_Rest {\n" . $methods . "}\n");
require $shim;

// ---------------------------------------------- WordPress dispatch model ---

/**
 * Reproduce WP_Hook::apply_filters() argument slicing, as suite-v017 does.
 *
 * The point is that accepted_args comes from the ARTEFACT, not from this file.
 * If the registration declares fewer than 3, $request arrives null, the
 * callback bails on its own guard, and the payload goes out unstripped - which
 * is what these cases must then report.
 */
function suite_dispatch(callable $cb, int $accepted_args, array $args)
{
    if ($accepted_args === 0) {
        return call_user_func($cb);
    }
    if ($accepted_args >= count($args)) {
        return call_user_func_array($cb, $args);
    }
    return call_user_func_array($cb, array_slice($args, 0, $accepted_args));
}

/** Read the registration off the artefact. Returns [priority, accepted_args]. */
function parse_registration(string $src): ?array
{
    $re = '/add_filter\(\s*\'rest_post_dispatch\'\s*,\s*array\(\s*self::\$instance\s*,'
        . '\s*\'avalon_rest_strip_keys\'\s*\)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)\s*;/';
    if (!preg_match($re, $src, $m)) {
        return null;
    }
    return [(int) $m[1], (int) $m[2]];
}

$reg      = parse_registration($src);
$accepted = $reg === null ? 1 : $reg[1];

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

/** The measured /options/all shape: 120 slugs, every leaf at path depth four. */
function options_payload(): array
{
    $options = [];
    for ($i = 0; $i < 118; $i++) {
        $options['opt_' . $i] = 'value_' . $i;
    }
    $options['google_server_key']  = 'AIzaFIXTURE_SERVER_KEY_NOT_THE_REAL_ONE';
    $options['google_geocode_key'] = 'AIzaFIXTURE_GEOCODE_KEY_NOT_THE_REAL_ON';
    return ['store-locator-le' => ['settings' => ['options' => $options]]];
}

/** $n nested arrays with a protected key at the bottom. */
function nested(int $n): array
{
    $node = ['google_server_key' => 'AIzaFIXTURE_DEEP', 'keep' => 1];
    for ($i = 0; $i < $n; $i++) {
        $node = ['level' . $i => $node];
    }
    return $node;
}

/**
 * Drive the filter the way WordPress would.
 *
 * Returns the SENTINEL string when the method is absent, so a negative control
 * reports a distinct failure per case rather than a cascade off one fatal.
 */
function run(string $route, $data, bool $can = false, int $status = 201)
{
    global $accepted;
    if (!method_exists('Suite_Rest', 'avalon_rest_strip_keys')) {
        return 'SENTINEL_METHOD_ABSENT';
    }
    $GLOBALS['SUITE_CAN'] = $can;
    $guard    = new Suite_Rest();
    $response = new WP_REST_Response($data, $status);
    $out      = suite_dispatch(
        [$guard, 'avalon_rest_strip_keys'],
        $accepted,
        [$response, new WP_REST_Server(), new WP_REST_Request($route)]
    );
    $GLOBALS['SUITE_CAN'] = false;
    return $out;
}

/** The options map out of a response, or a sentinel. */
function opts($response)
{
    if (!($response instanceof WP_REST_Response)) {
        return 'SENTINEL_NOT_A_RESPONSE';
    }
    $d = $response->get_data();
    return $d['store-locator-le']['settings']['options'] ?? 'SENTINEL_SHAPE_LOST';
}

echo "\nsuite-v018  artefact: $artefact\n\n";

if ($missing) {
    printf("  note  methods not found in artefact: %s\n\n", implode(', ', $missing));
}

// ------------------------------------------------------------- the wiring ---

ck(substr_count($src, "add_filter('rest_post_dispatch',"
    . "array(self::\$instance,'avalon_rest_strip_keys'),999,3);"), 1,
   '[v18]  registered exactly once, add_filter, priority 999, 3 args');

ck($reg, [999, 3],
   '[v18]  the artefact declares priority 999 and accepted_args 3');

ck(substr_count($src, "add_action('rest_post_dispatch'"), 0,
   '[both] never registered as an action - the return value would be dropped');

ck(substr_count($src, "'rest_post_dispatch'"), 1,
   '[v18]  the hook name appears once, at the registration and nowhere else');

// ------------------------------------------------------------ the slug list ---

ck(method_exists('Suite_Rest', 'avalon_rest_protected_slugs')
    ? (new Suite_Rest())->avalon_rest_protected_slugs()
    : 'SENTINEL_METHOD_ABSENT',
   ['google_server_key', 'google_geocode_key'],
   '[v18]  the protected list is exactly the two measured key slugs');

// -------------------------------------------------------- /options/all, v1 ---

$r = run('/store-locator-plus/v1/options/all', options_payload());

ck(is_array(opts($r)) ? count(opts($r)) : opts($r), 118,
   '[v18]  v1 /options/all: 120 slugs in, 118 out');

ck(is_array(opts($r)) ? array_key_exists('google_server_key', opts($r)) : 'SENTINEL',
   false, '[v18]  v1 /options/all: google_server_key is gone');

ck(is_array(opts($r)) ? array_key_exists('google_geocode_key', opts($r)) : 'SENTINEL',
   false, '[v18]  v1 /options/all: google_geocode_key is gone');

ck(is_array(opts($r)) ? ($opts0 = opts($r))['opt_0'] ?? null : 'SENTINEL', 'value_0',
   '[v18]  v1 /options/all: the other 118 slugs are untouched');

ck($r instanceof WP_REST_Response ? strpos((string) json_encode($r->get_data()), 'AIza') : 'SENTINEL',
   false, '[v18]  v1 /options/all: no AIza substring survives anywhere in the body');

ck($r instanceof WP_REST_Response ? $r->get_status() : 'SENTINEL', 201,
   '[v18]  the 201 SLP answers GET with is not disturbed - rev18 s5');

// -------------------------------------------------------- /options/all, v2 ---
//
// Decision 62. v2 returns bytes identical to v1, md5
// bfe3dbb49153e5046841e7e59006f300. A v1-scoped filter would leave this open,
// so this case is the one that would have caught shipping decisions 58/61 as
// worded.

$r2 = run('/store-locator-plus/v2/options/all', options_payload());

ck(is_array(opts($r2)) ? array_key_exists('google_server_key', opts($r2)) : 'SENTINEL',
   false, '[v18]  v2 /options/all is stripped too - the prefix, not the namespace');

// The bare report namespace is inside the same prefix.
$r3 = run('/store-locator-plus/report/location/search_history',
          ['records' => [['google_server_key' => 'AIzaFIXTURE_REPORT']]]);
ck($r3 instanceof WP_REST_Response
    ? strpos((string) json_encode($r3->get_data()), 'AIza') : 'SENTINEL',
   false, '[v18]  the bare store-locator-plus report namespace is covered');

// -------------------------------------------------------------- scoping ---
//
// rev14 s8. "The key survives" passes trivially when the callback is absent,
// so it is paired with the set_data counter, which requires the callback to
// have run and decided not to write.

$foreign = new WP_REST_Response(options_payload(), 201);
if (method_exists('Suite_Rest', 'avalon_rest_strip_keys')) {
    suite_dispatch([new Suite_Rest(), 'avalon_rest_strip_keys'], $accepted,
        [$foreign, new WP_REST_Server(), new WP_REST_Request('/wp/v2/posts')]);
    $foreignKept = array_key_exists(
        'google_server_key', $foreign->get_data()['store-locator-le']['settings']['options']);
    $foreignWrites = $foreign->set_data_calls;
} else {
    $foreignKept = 'SENTINEL_METHOD_ABSENT';
    $foreignWrites = 'SENTINEL_METHOD_ABSENT';
}

ck($foreignKept, true,
   '[v18]  a non-SLP route is not touched - the filter does not over-reach');

ck($foreignWrites, 0,
   '[v18]  and it wrote nothing: no set_data() on a route it does not own');

// A caller holding manage_slp_user keeps the keys. Paired with the write
// counter for the same reason.
$mgr = new WP_REST_Response(options_payload(), 201);
if (method_exists('Suite_Rest', 'avalon_rest_strip_keys')) {
    $GLOBALS['SUITE_CAN'] = true;
    suite_dispatch([new Suite_Rest(), 'avalon_rest_strip_keys'], $accepted,
        [$mgr, new WP_REST_Server(), new WP_REST_Request('/store-locator-plus/v1/options/all')]);
    $GLOBALS['SUITE_CAN'] = false;
    $mgrKept   = array_key_exists(
        'google_server_key', $mgr->get_data()['store-locator-le']['settings']['options']);
    $mgrWrites = $mgr->set_data_calls;
} else {
    $mgrKept   = 'SENTINEL_METHOD_ABSENT';
    $mgrWrites = 'SENTINEL_METHOD_ABSENT';
}

ck($mgrKept, true,
   '[v18]  manage_slp_user still receives the keys - the admin UI keeps working');

ck($mgrWrites, 0,
   '[v18]  and the response object was not rewritten on the way past');

// ------------------------------------------------------------ route limb ---
//
// Decision 63. /options/<slug> names the option in the ROUTE and returns it in
// a generically named field, so a name-keyed walk cannot see it. Both this
// route and /options/filtered/<slug> return HTTP 500 today on v1 and v2 alike,
// which is why the body shape below is a guess and the limb blanks wholesale
// rather than editing fields.

$r4 = run('/store-locator-plus/v1/options/google_server_key',
          ['slug' => 'google_server_key', 'value' => 'AIzaFIXTURE_BY_ROUTE']);

ck($r4 instanceof WP_REST_Response ? $r4->get_data() : 'SENTINEL', [],
   '[v18]  route limb: /options/google_server_key returns an empty body');

$r5 = run('/store-locator-plus/v2/options/filtered/google_geocode_key',
          ['slug' => 'google_geocode_key', 'value' => 'AIzaFIXTURE_BY_ROUTE']);

ck($r5 instanceof WP_REST_Response ? $r5->get_data() : 'SENTINEL', [],
   '[v18]  route limb: /options/filtered/<slug> on v2 is covered as well');

// 'all' and 'import' are slugs too. If the limb fired on them it would blank
// the whole options response instead of stripping two keys from it.
$r6 = run('/store-locator-plus/v1/options/all', options_payload());
ck(is_array(opts($r6)) ? count(opts($r6)) : opts($r6), 118,
   '[v18]  route limb does NOT fire on the slug "all" - 118 keys still served');

$r7 = run('/store-locator-plus/v1/options/mapdomain', ['slug' => 'mapdomain', 'value' => 'maps.google.com']);
ck($r7 instanceof WP_REST_Response ? $r7->get_data() : 'SENTINEL',
   ['slug' => 'mapdomain', 'value' => 'maps.google.com'],
   '[v18]  an unprotected slug route passes through untouched');

// ---------------------------------------------------------------- depths ---

$r8 = run('/store-locator-plus/v1/options/all', nested(4));
ck($r8 instanceof WP_REST_Response
    ? strpos((string) json_encode($r8->get_data()), 'AIza') : 'SENTINEL',
   false, '[v18]  a protected key nested four deep is removed');

$r9 = run('/store-locator-plus/v1/options/all', nested(14));
ck($r9 instanceof WP_REST_Response
    ? (strpos((string) json_encode($r9->get_data()), 'AIzaFIXTURE_DEEP') !== false) : 'SENTINEL',
   true, '[v18]  the depth cap holds: nothing is walked past 10 levels');

// stdClass, because a REST body can be objects rather than arrays.
$obj = new stdClass();
$obj->google_server_key = 'AIzaFIXTURE_OBJECT';
$obj->keep = 'yes';
$r10 = run('/store-locator-plus/v1/options/all', ['settings' => $obj]);
ck($r10 instanceof WP_REST_Response
    ? strpos((string) json_encode($r10->get_data()), 'AIza') : 'SENTINEL',
   false, '[v18]  an stdClass branch is stripped as well as an array one');

// ------------------------------------------------------------ pass-through ---

$err  = new WP_Error('rest_forbidden');
$r11  = run('/store-locator-plus/v1/options/all', null);
$r11b = method_exists('Suite_Rest', 'avalon_rest_strip_keys')
    ? suite_dispatch([new Suite_Rest(), 'avalon_rest_strip_keys'], $accepted,
        [$err, new WP_REST_Server(), new WP_REST_Request('/store-locator-plus/v1/options/all')])
    : 'SENTINEL_METHOD_ABSENT';

ck($r11b instanceof WP_Error ? $r11b->code : $r11b, 'rest_forbidden',
   '[v18]  a WP_Error result is returned untouched, not coerced');

// ------------------------------------------------- earlier releases intact ---
//
// This build reopens the file that carries every prior release. Checked on the
// way past, the way suite-v017 checks v0.0.16's wiring.

ck(substr_count($src, "'avalon_flush_import_log'),500,0);"), 1,
   '[both] v0.0.16 accepted_args=0 survives');

ck(substr_count($src, "'avalon_import_coordinate_guard'),20,1);"), 1,
   '[both] the coordinate guard is still registered at priority 20');

ck(substr_count($src, "'territory_gate'),20,1);"), 1,
   '[both] the Layer 3 territory gate is still registered at priority 20');

ck(substr_count($src, 'AVALON_TIER2_MAX_CORRECTIONS  : 60,')
    + substr_count($src, ': 25,'), 1,
   '[both] v0.0.17 cap of 60 present and no default of 25 came back');

ck(substr_count($src, 'overrides_rotated'), 3,
   '[both] v0.0.17 rotation state key: read, write, docblock');

ck(substr_count($src,
    "\$address2 = isset(\$location['sl_address2']) ? \$location['sl_address2'] : '';"), 1,
   '[both] v0.0.17 guarded sl_address2 read survives');

ck(substr_count($src, "'DONNIE MARCH|HOWELL|MI',"), 1,
   '[both] the DONNIE MARCH exclusion survives');

// ------------------------------------------------------------- verdict ----

@unlink($shim);

echo "\n";
printf("suite-v018: %d/%d assertions PASS\n", $passed, $total);
exit($passed === $total ? 0 : 1);
