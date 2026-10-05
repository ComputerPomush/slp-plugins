<?php
/**
 * suite-v032.php - validates slp_avalon v0.0.27 Part 4.
 *
 *   s0.273  the key swap: the Maps loader prints the Browser Key only;
 *           import geocoding, the hours fetch (legacy and New) and the
 *           place resolver send the Geocoding Key, else the Browser Key
 *   the IANA zone resolver, against both 2026 daylight-saving states,
 *           the no-DST zones and their neighbours, the hour neighbouring
 *           zones cross as clocks fall back, and every candidate name
 *           checked against PHP's own zone database
 *   the guard that withholds Vancouver and Edmonton while the server's
 *           zone data lacks their 2026 rules, set both ways
 *   s0.278  the offset schedule the browser adds, from the server's zone
 *           data - across the November change, and a zone with none
 *   Google's weekday lines, compacted
 *   the gate: ok, not a disputed key, not closed in the row or in the
 *           cached Place, and fetched within the positive TTL
 *   weekday numbers from the English names, in any order
 *   the payload: seven lines or nothing; periods only with a zone;
 *           never openNow, never raw
 *   the markup: store page (two copies, the fold by CSS) and card; no
 *           empty span (s0.274); no ids; escaping
 *   [avalon_store_hours] and its two reads, by address key
 *   the labels on slp_results_marker_data: Address:, Phone: with tel:
 *           from the row's written country, never a postal hint or a state
 *           over a written country
 *   the hours on the results: one IN () read per search, hours only
 *           through the gate, a refused read harmless
 *   the results layout: Address:, Phone: with tel:, Hours: after the
 *           phone line; idempotent step by step; a layout it does not know
 *           untouched; s0.279 registered after SLP Experience, whose filter
 *           at 90 discards what it is handed - run through a real filter
 *           chain - and again on slp_js_options after Experience merges
 *           its stored settings at 90
 *   the enqueue, Elementor's breakpoint, the two WP Rocket filters
 *   the registrations
 *
 * WHAT CARRIES FORWARD BY IDENTITY
 *
 * Part 4 is Part 3b plus six edits and one inserted block. The first
 * assertion takes the block out, reverses the six edits, and requires
 * the result to be v0.0.27-part3b byte for byte - 3bd20941, 246,313
 * bytes. Every region Part 4 did not touch therefore carries suite-v031's
 * 114/114 and its 54 controls forward unchanged, and the edits cannot
 * have touched anything else. A seventh, unlisted change anywhere in the
 * file fails that one assertion.
 *
 * WHAT IS EXECUTED
 *
 * The constants, file_version(), add_actions() with register_shortcodes(),
 * the Maps loader, geocode_from_address(), avalon_hours_table(), the whole
 * hours fetch layer (config through the verdict map) and the Part 4 block
 * are lifted verbatim into a harness class and run against recording
 * doubles. ONE PROCESS PER SCENARIO, so a constant such as
 * AVALON_HOURS_API or a class such as Elementor's can be present in one
 * scenario and absent in another. Every warning and notice is turned into
 * an exception: A CRASH IS A RESULT, and it fails the scenario by name.
 *
 * The test data is synthetic. slp-plugins is public: no dealer address,
 * phone number or place id from the feeds appears here.
 *
 * NO ASSERTION DEPENDS ON THE DAY IT RUNS OR ON THE MACHINE'S ZONE DATA.
 * Pure scenarios pass their own clock; scenarios that read the live clock
 * build their rows from it. Zone cases avoid British Columbia and Alberta
 * after their 2026 rule changes, which an older zone database would read
 * differently, and every scenario starts with the zone-rules guard primed
 * as current; the rules scenario sets it both ways. The machine's own
 * answer is printed, not asserted.
 *
 * Usage:
 *   php suite-v032.php <class.slp_avalon.php> [<class.slp_avalon_addresskey.php>]
 *
 * Exit 0 all passed, 1 any failed, 2 the suite could not run.
 */

$clsPath = $argv[1] ?? 'build/out-v027p4/class.slp_avalon.php';
$akPath  = $argv[2] ?? (__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'slp_avalon'
                       . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'class.slp_avalon_addresskey.php');

$code = @file_get_contents($clsPath);
if ($code === false) {
    fwrite(STDERR, "cannot read {$clsPath}\n");
    exit(2);
}
$ak = @file_get_contents($akPath);
if ($ak === false) {
    fwrite(STDERR, "cannot read {$akPath}\n");
    exit(2);
}
/* The address-key class Part 4 keys with is Part 2's, unchanged since. */
if (md5($ak) !== 'c663c7e3c70a995d1405365a3647b77f' || strlen($ak) !== 36641) {
    fwrite(STDERR, "{$akPath} is not the pinned address-key class (c663c7e3, 36641 bytes)\n");
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

echo "suite-v032  slp_avalon 0.0.27 Part 4\n";
printf("  class   %s\n          %s  %d bytes\n", $clsPath, md5($code), strlen($code));
printf("  addrkey %s\n          %s  %d bytes  (pinned)\n\n", $akPath, md5($ak), strlen($ak));

/* ------------------------------------------------------------------ */
/* IDENTITY: Part 4 minus its edits is Part 3b, byte for byte.          */
/* ------------------------------------------------------------------ */

$P3B = array('3bd2094189c58318a827ca01f990f4fc', 246313);

$DISPLAY_START = "        /**\r\n         * v0.0.27 Part 4. Showing the hours.";
$DISPLAY_END   = "        public function avalon_rest_protected_slugs(){";
$DISPLAY_PIN   = array('acc7c83c3588eda6bdf92bcf1c731c69', 52477);

/* label => array( what Part 4 wrote, what Part 3b had ) */
$EDITS = array(
    's0.273 Maps loader' => array(
        "            // v0.0.27 Part 4, s0.273. The Browser Key and only the Browser\r\n            // Key - SLP's google_server_key. Until Part 4 this read\r\n            // google_geocode_key first, SLP's server-side Geocoding Key.\r\n            \$the_key = \$this->avalon_google_browser_key();\r\n",
        "            // Google JavaScript API server Key\r\n            // \$server_key = !empty(\$slplus->SmartOptions->google_server_key->value) ? '&key=' . \$slplus->SmartOptions->google_server_key->value : '';\r\n            \$the_key = ! empty ( \$slplus->SmartOptions->google_geocode_key->value ) ? \$slplus->SmartOptions->google_geocode_key->value : '';\r\n            if ( empty( \$the_key ) ) {\r\n                \$the_key = ! empty ( \$slplus->SmartOptions->google_server_key->value ) ? \$slplus->SmartOptions->google_server_key->value : '';\r\n            }\r\n"),
    's0.273 import geocoding' => array(
        "            //v0.0.27 Part 4, s0.273. SLP's Geocoding Key, else its Browser Key.\r\n            \$server_key = \$this->avalon_google_server_key();\r\n",
        "            \$server_key = !empty(\$slplus->SmartOptions->google_server_key->value) ? \$slplus->SmartOptions->google_server_key->value : '';\r\n"),
    's0.273 hours fetch' => array(
        "            //v0.0.27 Part 4, s0.273. SLP's Geocoding Key, else its Browser\r\n            //Key - the server's key, never the one the page prints.\r\n            \$api_key = \$this->avalon_google_server_key();\r\n",
        "            global \$slplus;\r\n            \$api_key = '';\r\n            if ( isset( \$slplus ) && is_object( \$slplus ) ) {\r\n                \$api_key = (string) \$slplus->SmartOptions->google_server_key->value;\r\n            }\r\n"),
    's0.273 place resolver' => array(
        "            //v0.0.27 Part 4, s0.273. SLP's Geocoding Key, else its Browser Key.\r\n            \$key = \$this->avalon_google_server_key();\r\n",
        "            \$key = '';\r\n            if ( isset( \$slplus->SmartOptions->google_server_key->value ) ) {\r\n                \$key = (string) \$slplus->SmartOptions->google_server_key->value;\r\n            }\r\n"),
    'registrations in add_actions()' => array(
        "            add_action(self::HOURS_CRON_HOOK, array(self::\$instance,'avalon_hours_cron'));\r\n            //\r\n            // v0.0.27 Part 4. Showing the hours.\r\n            //\r\n            // The stylesheet and the script on wp_enqueue_scripts, which\r\n            // fires only on the front end. The phone and address labels onto\r\n            // every marker at 20, after SLP Experience's marker filter at 15.\r\n            // The hours onto each result at priority 30 - after the backfill\r\n            // at 10 and territory_gate at 20, so exactly the markers that\r\n            // will be sent are decorated, with one read for all of them. The\r\n            // fields into the results layout at 100 - after SLP Experience,\r\n            // whose filter at 90 starts again from the stored layout\r\n            // (s0.279) - and into the script options at 100, after it merges\r\n            // its stored settings at 90. The two WP Rocket filters are\r\n            // no-ops where WP Rocket is not installed.\r\n            add_action('wp_enqueue_scripts', array(self::\$instance,'avalon_hours_enqueue'), 20);\r\n            add_filter('slp_results_marker_data', array(self::\$instance,'avalon_marker_labels'), 20, 1);\r\n            add_filter('slp_ajax_find_locations_complete', array(self::\$instance,'avalon_hours_attach_markers'), 30, 1);\r\n            add_filter('slp_javascript_results_string', array(self::\$instance,'avalon_results_layout'), 100, 1);\r\n            add_filter('slp_js_options', array(self::\$instance,'avalon_js_options_layout'), 100, 1);\r\n            add_filter('rocket_delay_js_exclusions', array('SLP_Avalon','avalon_rocket_delay_exclusions'));\r\n            add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist'));\r\n",
        "            add_action(self::HOURS_CRON_HOOK, array(self::\$instance,'avalon_hours_cron'));\r\n"),
    '[avalon_store_hours] in register_shortcodes()' => array(
        "            add_shortcode('avalon_map_location', array(self::\$instance,'avalon_map_location_sc_func'));\r\n            add_shortcode('avalon_store_hours', array(self::\$instance,'avalon_store_hours_sc_func'));\r\n",
        "            add_shortcode('avalon_map_location', array(self::\$instance,'avalon_map_location_sc_func'));\r\n"),
);

echo "  IDENTITY\n";
$anchorsOk = substr_count($code, $DISPLAY_START) === 1 && substr_count($code, $DISPLAY_END) === 1
          && strpos($code, $DISPLAY_START) < strpos($code, $DISPLAY_END);
check($anchorsOk, 'the Part 4 block is present once, directly before avalon_rest_protected_slugs()');
if (! $anchorsOk) {
    fwrite(STDERR, "cannot find the Part 4 block; nothing else can be lifted\n");
    exit(2);
}
$a = strpos($code, $DISPLAY_START);
$b = strpos($code, $DISPLAY_END);
$display = substr($code, $a, $b - $a);
check(md5($display) === $DISPLAY_PIN[0] && strlen($display) === $DISPLAY_PIN[1],
      sprintf('the block is the one this suite was written against (%s, %d bytes)', $DISPLAY_PIN[0], $DISPLAY_PIN[1]));

$rev = substr($code, 0, $a) . substr($code, $b);
$editsOk = true;
foreach ($EDITS as $label => $pair) {
    if (substr_count($rev, $pair[0]) !== 1) {
        $editsOk = false;
        printf("      edit not found exactly once: %s\n", $label);
        continue;
    }
    $rev = implode($pair[1], explode($pair[0], $rev, 2));
}
check($editsOk, 'each of the six edits is present exactly once');
check(md5($rev) === $P3B[0] && strlen($rev) === $P3B[1],
      'block out and six edits reversed, the file IS v0.0.27-part3b (3bd20941, 246,313 bytes)');
check(substr_count($code, "\r\n") === substr_count($code, "\n") && substr_count($code, "\r") === substr_count($code, "\r\n"),
      'pure CRLF - no bare LF, no bare CR');

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
    'consts'  => span($code, "        /**\r\n         * v0.0.26 Part 1. Schema version for the dealer-places table.",
                             '        public static function instance(){'),
    'filever' => span($code, '        public static function file_version($filename)',
                             '        private function init_frontend(){'),
    'wiring'  => span($code, '        private function add_actions(){',
                             '        private function init_admin(){'),
    'loader'  => span($code, '        public function splus_get_google_maps_url(){',
                             '        public function slp_ajax_find_locations_complete_filter($results){'),
    'geocode' => span($code, '        public function geocode_from_address($address)',
                             '        public function slp_get_all_locations()'),
    'table'   => span($code, '        public static function avalon_hours_table(){',
                             "        /**\r\n         * v0.0.26 Part 1. Create or migrate the dealer-places table."),
    'fetch'   => span($code, '        public function avalon_hours_config(){',
                             "        /**\r\n         * v0.0.27 Part 3a. One pass of the hours queue."),
    'disputed' => span($code, '        public static function avalon_hours_disputed_keys(){',
                              "        /**\r\n         * v0.0.27 Part 3b. Mark every disputed key blocked"),
    'display' => $display,
);
foreach ($LIFTS as $n => $v) {
    if ($v === false || $v === '') {
        fwrite(STDERR, "cannot lift {$n} from {$clsPath}\n");
        exit(2);
    }
    // PHP's own tokenizer, not a count of comment markers: a regex in the
    // Part 4 block holds a star followed by a slash that closes nothing.
    foreach (token_get_all("<?php\nclass T {\n" . $v . "\n}\n") as $tok) {
        if (is_array($tok) && in_array($tok[0], array(T_COMMENT, T_DOC_COMMENT), true)
            && strpos($tok[1], '/*') === 0 && substr($tok[1], -2) !== '*/') {
            fwrite(STDERR, "lift of {$n} splits a comment\n");
            exit(2);
        }
    }
}

/* ------------------------------------------------------------------ */
/* Harness.                                                            */
/* ------------------------------------------------------------------ */

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'slp32_' . getmypid();
@mkdir($tmp . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css', 0777, true);
@mkdir($tmp . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js', 0777, true);
file_put_contents($tmp . '/plugin/assets/css/avalon-hours.css', '/* fixture */');
file_put_contents($tmp . '/plugin/assets/js/avalon-hours.js', '/* fixture */');
touch($tmp . '/plugin/assets/css/avalon-hours.css', 1790000000);
touch($tmp . '/plugin/assets/js/avalon-hours.js', 1790000100);
$akCopy = $tmp . DIRECTORY_SEPARATOR . 'addresskey.php';
file_put_contents($akCopy, $ak);

$head = <<<'PHPHEAD'
<?php
error_reporting(E_ALL);
set_error_handler(function ($no, $str, $file, $line) {
    throw new ErrorException($str, 0, $no, $file, $line);
});
define('ARRAY_A', 'ARRAY_A');
define('DAY_IN_SECONDS', 86400);
define('ASLP_URL', (($argv[1] ?? '') === 'rocket_path')
    ? 'https://example.test/sub/wp-content/plugins/slp_avalon-renamed/'
    : 'https://example.test/wp-content/plugins/slp_avalon/');
define('T_NOW', 1791079200);   /* 2026-10-04 02:00:00 UTC - Saturday 22:00 EDT */
define('ASLP_DIR', __DIR__ . '/plugin/');
if (($argv[1] ?? '') === 'details_new') {
    define('AVALON_HOURS_API', 'new');
}

$GLOBALS['REC'] = array('actions' => array(), 'filters' => array(), 'shortcodes' => array(),
                        'styles' => array(), 'scripts' => array(), 'inline' => array(), 'http' => array());
function cbname($cb) {
    if (is_array($cb)) { return (is_object($cb[0]) ? get_class($cb[0]) . '->' : $cb[0] . '::') . $cb[1]; }
    return is_string($cb) ? $cb : 'closure';
}
function add_action($h, $cb, $p = 10, $a = 1) { $GLOBALS['REC']['actions'][] = array($h, cbname($cb), $p, $a); return true; }
function add_filter($h, $cb, $p = 10, $a = 1) { $GLOBALS['REC']['filters'][] = array($h, cbname($cb), $p, $a); return true; }
function add_shortcode($t, $cb) { $GLOBALS['REC']['shortcodes'][] = array($t, cbname($cb)); }
function is_admin() { return ! empty($GLOBALS['IS_ADMIN']); }
function wp_enqueue_style($h, $src = '', $deps = array(), $ver = false, $media = 'all') {
    $GLOBALS['REC']['styles'][] = array($h, $src, $deps, $ver, $media);
}
function wp_enqueue_script($h, $src = '', $deps = array(), $ver = false, $foot = false) {
    $GLOBALS['REC']['scripts'][] = array($h, $src, $deps, $ver, $foot);
}
function wp_add_inline_style($h, $css) { $GLOBALS['REC']['inline'][] = array($h, $css); return true; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s)  { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function wp_json_encode($v) { return json_encode($v); }
function get_the_ID() { return $GLOBALS['THE_ID'] ?? 0; }
function get_queried_object_id() { return $GLOBALS['QUERIED_ID'] ?? 0; }
function get_post_type($id) { return $GLOBALS['POST_TYPES'][(int) $id] ?? false; }
function is_singular($t = '') { return ($GLOBALS['SINGULAR'] ?? '') === $t; }
function wp_make_link_relative($url) { return preg_replace('|^(https?:)?//[^/]+(/?.*)|i', '$2', $url); }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
class WP_Error {
    public $code;
    function __construct($c = '', $m = '') { $this->code = $c; }
    function get_error_code() { return $this->code; }
    function get_error_message() { return 'transport'; }
}
function is_wp_error($t) { return $t instanceof WP_Error; }
function wp_remote_get($url, $args = array()) {
    $GLOBALS['REC']['http'][] = array($url, $args);
    return array('code' => 200, 'body' => json_encode(array('status' => 'ZERO_RESULTS', 'results' => array())));
}
function wp_remote_retrieve_response_code($r) { return is_array($r) ? ($r['code'] ?? 0) : 0; }
function wp_remote_retrieve_body($r) { return is_array($r) ? ($r['body'] ?? '') : ''; }
function current_time($t, $gmt = 0) { return gmdate('Y-m-d H:i:s'); }
function get_option($k, $d = false) { return $d; }
function update_option($k, $v, $a = null) { return true; }

class SLPlus {
    public $SmartOptions;
    public $options_nojs = array('map_language' => 'en');
    public $javascript_is_forced = false;
}
class SLP_Country_Manager {
    public $countries = array();
    private static $i;
    public static function get_instance() { return self::$i ?: (self::$i = new self()); }
}
class T_Opt { public $value; function __construct($v) { $this->value = $v; } }
/* SLP's two key fields. null = the option does not exist at all. */
function slp_keys($browser, $geocode) {
    global $slplus;
    $slplus = new SLPlus();
    $slplus->SmartOptions = new stdClass();
    if ($browser !== null) { $slplus->SmartOptions->google_server_key  = new T_Opt($browser); }
    if ($geocode !== null) { $slplus->SmartOptions->google_geocode_key = new T_Opt($geocode); }
}

/* A wpdb double for the three Part 4 reads. prepare() counts placeholders
   against arguments as wpdb does and takes one array as the argument list;
   any statement it cannot read is recorded as UNPARSED. */
class T_wpdb {
    public $prefix = 'wp_';
    public $last_error = '';
    public $log = array();
    public $locator = array();
    public $places = array();
    public $refuse = false;
    public function prepare($q, ...$args) {
        if (count($args) === 1 && is_array($args[0])) { $args = $args[0]; }
        if (preg_match_all('/%[sd]/', $q) !== count($args)) { $this->log[] = array('MISMATCH', $q); }
        $i = 0;
        return preg_replace_callback('/%([sd])/', function ($m) use (&$i, $args) {
            $v = $args[$i++];
            return $m[1] === 'd' ? (string) (int) $v : "'" . addslashes((string) $v) . "'";
        }, $q);
    }
    public function get_row($sql, $mode = 'OBJECT') {
        $this->log[] = array('get_row', $sql, $mode);
        $this->last_error = '';
        if ($this->refuse) { $this->last_error = 'refused'; return null; }
        if (preg_match("/^SELECT sl_id, sl_address, sl_city, sl_state, sl_zip, sl_country FROM wp_store_locator"
                     . " WHERE sl_linked_postid = (\d+) ORDER BY sl_id ASC LIMIT 1$/", $sql, $m)) {
            return $this->locator[(int) $m[1]] ?? null;
        }
        if (preg_match("/^SELECT address_key, hours_status, business_status, fetched_at, hours_json"
                     . " FROM wp_avalon_dealer_places WHERE address_key = '([0-9a-f]{12})'$/", $sql, $m)) {
            return $this->places[$m[1]] ?? null;
        }
        $this->log[] = array('UNPARSED', $sql);
        return null;
    }
    public function get_results($sql, $mode = 'OBJECT') {
        $this->log[] = array('get_results', $sql, $mode);
        $this->last_error = '';
        if ($this->refuse) { $this->last_error = 'refused'; return array(); }
        if (preg_match("/^SELECT address_key, hours_status, business_status, fetched_at, hours_json"
                     . " FROM wp_avalon_dealer_places WHERE address_key IN \(([^)]*)\)$/", $sql, $m)) {
            $out = array();
            foreach (explode(',', $m[1]) as $k) {
                $k = trim($k, " '");
                if (isset($this->places[$k])) { $out[] = $this->places[$k]; }
            }
            return $out;
        }
        $this->log[] = array('UNPARSED', $sql);
        return array();
    }
}
$wpdb = new T_wpdb();
PHPHEAD;

$classTail = <<<'PHPTAIL'
    /* Every scenario starts with both 2026 zones read as current, so the
       guard answers the same on any machine; 'rules' sets it both ways. */
    public static function t_boot() {
        self::$avalon_hours_rules = array('America/Vancouver' => true, 'America/Edmonton' => true);
        return self::$instance = new SLP_Avalon();
    }
    public static function t_rules($a) { self::$avalon_hours_rules = $a; }
    public static function t_rules_get() { return self::$avalon_hours_rules; }
    public function t_call($m, $args = array()) { return call_user_func_array(array($this, $m), $args); }
    public function t_wire() { $this->add_actions(); $this->register_shortcodes(); }
}
PHPTAIL;

$run = <<<'PHPRUN'

/* ---------------------------------------------------------------- data */

function nn() { return "\xE2\x80\xAF"; }
function th() { return "\xE2\x80\x89"; }
function nd() { return "\xE2\x80\x93"; }
function gline($day, $o, $c) { return $day . ': ' . $o . nn() . th() . nd() . th() . $c; }
function week_lines() {
    $l = array();
    foreach (array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday') as $d) {
        $l[] = $d . ': 9:00' . nn() . 'AM' . th() . nd() . th() . '5:00' . nn() . 'PM';
    }
    $l[] = 'Saturday: 9:00' . nn() . 'AM' . th() . nd() . th() . '1:00' . nn() . 'PM';
    $l[] = 'Sunday: Closed';
    return $l;
}
function week_periods() {
    $p = array();
    for ($d = 1; $d <= 5; $d++) {
        $p[] = array('open' => array('day' => $d, 'hour' => 9, 'minute' => 0), 'close' => array('day' => $d, 'hour' => 17, 'minute' => 0));
    }
    $p[] = array('open' => array('day' => 6, 'hour' => 9, 'minute' => 0), 'close' => array('day' => 6, 'hour' => 13, 'minute' => 0));
    return $p;
}
function place($over = array()) {
    return array_merge(array(
        'id' => 'TESTPLACE', 'name' => 'places/TESTPLACE', 'businessStatus' => 'OPERATIONAL',
        'utcOffsetMinutes' => -240, 'attributions' => array(),
        'regularOpeningHours' => array('openNow' => true, 'weekdayDescriptions' => week_lines(), 'periods' => week_periods()),
    ), $over);
}
function prow($over = array(), $place = null) {
    $p = $place === null ? place() : $place;
    return array_merge(array(
        'address_key' => 'aaaaaaaaaaaa', 'hours_status' => 'ok', 'business_status' => 'OPERATIONAL',
        'fetched_at' => '2026-10-03 21:38:49',
        'hours_json' => json_encode(array('shape' => 'new', 'source' => 'legacy', 'at' => '2026-10-03 21:38:49',
                                          'place' => $p, 'raw' => array('opening_hours' => array('open_now' => true)))),
    ), $over);
}
/* A row fetched an hour ago by the live clock, with the offset New York
   really had then - so live-clock scenarios read the same in any season. */
function fresh_prow($key) {
    $ts = time() - 3600;
    $ny = new DateTimeZone('America/New_York');
    $p  = place(array('utcOffsetMinutes' => intdiv($ny->getOffset(new DateTime('@' . $ts)), 60)));
    return prow(array('address_key' => $key, 'fetched_at' => gmdate('Y-m-d H:i:s', $ts)), $p);
}
function locrow($over = array()) {
    return array_merge(array('sl_id' => '1', 'sl_address' => '100 Test Street', 'sl_city' => 'Testville',
                             'sl_state' => 'NY', 'sl_zip' => '10001', 'sl_country' => 'US'), $over);
}
function key_of($row) {
    return SLP_Avalon_AddressKey::dealer_key(array('raw_address' => $row['sl_address'], 'raw_city' => $row['sl_city'],
        'raw_state' => $row['sl_state'], 'raw_zip' => $row['sl_zip'], 'raw_country' => $row['sl_country']));
}
function marker($row, $phone) {
    return array('id' => $row['sl_id'], 'name' => 'Test Dealer', 'address' => esc_attr($row['sl_address']),
                 'phone' => esc_attr($phone), 'data' => array_merge($row, array('sl_phone' => $phone)));
}

/* ----------------------------------------------------------- scenarios */

$s = $argv[1] ?? '';
$out = array();
try {
    $o = SLP_Avalon::t_boot();
    switch ($s) {

    case 'keys':
        foreach (array(array('B', 'G'), array('B', ''), array('B', '   '), array('B', null),
                       array('', 'G'), array(null, 'G'), array(null, null)) as $c) {
            slp_keys($c[0], $c[1]);
            $out['k'][] = array($o->t_call('avalon_google_browser_key'), $o->t_call('avalon_google_server_key'));
        }
        $GLOBALS['slplus'] = null;
        $out['none'] = array($o->t_call('avalon_google_browser_key'), $o->t_call('avalon_google_server_key'));
        break;

    case 'loader':
        foreach (array(array('B', 'G'), array('', 'G'), array('B', '')) as $c) {
            slp_keys($c[0], $c[1]);
            $url = $o->splus_get_google_maps_url();
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $out['l'][] = array($url, $q['key'] ?? null);
        }
        break;

    case 'geocode':
        foreach (array(array('B', 'G'), array('B', '')) as $c) {
            slp_keys($c[0], $c[1]);
            $GLOBALS['REC']['http'] = array();
            $o->geocode_from_address('100 Test Street, Testville, NY');
            parse_str((string) parse_url($GLOBALS['REC']['http'][0][0] ?? '', PHP_URL_QUERY), $q);
            $out['g'][] = $q['key'] ?? null;
        }
        break;

    case 'details_legacy':
        foreach (array(array('B', 'G'), array('B', '')) as $c) {
            slp_keys($c[0], $c[1]);
            $GLOBALS['REC']['http'] = array();
            $o->avalon_hours_details('TESTPLACE');
            parse_str((string) parse_url($GLOBALS['REC']['http'][0][0] ?? '', PHP_URL_QUERY), $q);
            $out['d'][] = array($q['key'] ?? null, $q['fields'] ?? null);
        }
        break;

    case 'details_new':
        foreach (array(array('B', 'G'), array('B', '')) as $c) {
            slp_keys($c[0], $c[1]);
            $GLOBALS['REC']['http'] = array();
            $o->avalon_hours_details('TESTPLACE');
            $out['d'][] = $GLOBALS['REC']['http'][0][1]['headers']['X-Goog-Api-Key'] ?? null;
        }
        break;

    case 'zone':
        $su = '2026-10-03 21:38:49';
        $wi = '2026-01-15 17:00:00';
        $cases = array(
            array('US', 'MI', -240, $su), array('US', 'MI', -300, $su), array('US', 'FL', -300, $su),
            array('US', 'FL', -240, $su), array('US', 'FL', -360, $wi), array('US', 'AZ', -420, $su),
            array('US', 'AZ', -360, $su), array('US', 'AZ', -420, $wi), array('CA', 'SK', -360, $su),
            array('CA', 'SK', -360, $wi), array('CA', 'SK', -420, $wi), array('US', 'ID', -420, $su),
            array('US', 'ID', -360, $su), array('US', 'NE', -360, $su), array('CA', 'BC', -420, $su),
            array('CA', 'BC', -480, $wi), array('US', 'IN', -300, $su), array('US', 'IN', -240, $su),
            array('US', 'OH', -300, $su), array('US', 'MI', null, $su), array('US', 'MI', -240, ''),
            array('US', 'MI', -240, '2026-10-03T21:38:49Z'), array('MX', 'JAL', -360, $su),
            array('us', 'mi', -240, $su), array('US', 'TN', -300, $su), array('US', 'TN', -240, $su),
            array('CA', 'QC', -240, $su), array('CA', 'ON', -300, $su), array('US', 'HI', -600, $su),
            array('US', 'TN', -300, '2026-11-01 06:30:00'), array('US', 'FL', -300, '2026-11-01 06:30:00'),
            array('US', 'NE', -360, '2026-11-01 07:30:00'), array('CA', 'ON', -300, '2026-11-01 06:30:00'),
            array('US', 'TN', -300, '2026-11-01 05:30:00'), array('US', 'TN', -300, '2026-11-01 07:30:00'),
            array('US', 'AZ', -420, '2026-11-01 08:30:00'), array('CA', 'NU', -360, $wi),
            array('CA', 'NU', -420, $wi), array('CA', 'NT', -420, $wi),
        );
        foreach ($cases as $c) {
            $out['z'][] = SLP_Avalon::avalon_hours_zone($c[0], $c[1], $c[2], $c[3]);
        }
        $bad = array();
        $n = 0;
        foreach (SLP_Avalon::avalon_hours_zone_candidates() as $cc => $states) {
            foreach ($states as $st => $zones) {
                foreach ($zones as $z) {
                    $n++;
                    try { new DateTimeZone($z); } catch (Exception $e) { $bad[] = $z; }
                }
            }
        }
        $all = SLP_Avalon::avalon_hours_zone_candidates();
        $out['names'] = array($n, $bad, count($all['US']), count($all['CA']));
        break;

    case 'rules':
        $su = '2026-10-03 21:38:49';
        $Z = function () use ($su) {
            return array(SLP_Avalon::avalon_hours_zone('CA', 'BC', -420, $su), SLP_Avalon::avalon_hours_zone('CA', 'AB', -360, $su),
                         SLP_Avalon::avalon_hours_zone('CA', 'SK', -360, $su), SLP_Avalon::avalon_hours_zone('CA', 'ON', -240, $su),
                         SLP_Avalon::avalon_hours_zone('US', 'NY', -240, $su));
        };
        SLP_Avalon::t_rules(array('America/Vancouver' => false, 'America/Edmonton' => false));
        $out['stale'] = $Z();
        $out['stale_payload'] = SLP_Avalon::avalon_hours_payload(prow(array(), place(array('utcOffsetMinutes' => -420))), 'CA', 'BC', 30, T_NOW);
        SLP_Avalon::t_rules(array('America/Vancouver' => true, 'America/Edmonton' => false));
        $out['half'] = $Z();
        SLP_Avalon::t_rules(array('America/Vancouver' => true, 'America/Edmonton' => true));
        $out['current'] = $Z();
        SLP_Avalon::t_rules(array());
        $jan = new DateTime('2027-01-15 12:00:00', new DateTimeZone('UTC'));
        $out['machine'] = array(intdiv((new DateTimeZone('America/Vancouver'))->getOffset($jan), 60),
                                intdiv((new DateTimeZone('America/Edmonton'))->getOffset($jan), 60), timezone_version_get());
        $out['live'] = array(SLP_Avalon::avalon_hours_rules_current('America/Vancouver'),
                             SLP_Avalon::avalon_hours_rules_current('America/Edmonton'),
                             SLP_Avalon::avalon_hours_rules_current('America/New_York'));
        $out['cache'] = SLP_Avalon::t_rules_get();
        break;

    case 'line':
        $in = array(
            gline('Monday', '9:00' . nn() . 'AM', '5:00' . nn() . 'PM'),
            'Sunday: 10:00 AM - 3:00 PM',
            'Tuesday: 9:00 AM ' . nd() . ' 12:30 PM, 1:00 ' . nd() . ' 5:00 PM',
            'Wednesday: 9:00' . "\xC2\xA0" . 'AM' . nd() . '5:00' . "\xC2\xA0" . 'PM',
            'Saturday: Closed',
            'Friday: Open 24 hours',
            'Thursday: 12:00 PM ' . "\xE2\x80\x94" . ' 12:00 AM',
            'no separator here',
            ': 9:00 AM - 5:00 PM',
        );
        foreach ($in as $l) { $out['l'][] = SLP_Avalon::avalon_hours_line($l); }
        break;

    case 'pt_attr':
        foreach (array(array('day' => 1, 'hour' => 9, 'minute' => 30), array('day' => 0, 'hour' => 0),
                       array('day' => 6, 'hour' => 24, 'minute' => 0), array('day' => 7, 'hour' => 9, 'minute' => 0),
                       array('day' => -1, 'hour' => 9), array('day' => 1, 'hour' => 25), array('day' => 1, 'hour' => 9, 'minute' => 60),
                       array('hour' => 9), 'string', null) as $p) {
            $out['p'][] = SLP_Avalon::avalon_hours_pt($p);
        }
        $out['a'] = SLP_Avalon::avalon_hours_attributions(array(
            'Listings by <a href="https://example.com/x?a=1&amp;b=2" target="_blank">Example &amp; Co</a>',
            array('provider' => 'Provider', 'providerUri' => 'https://provider.example/'),
            '<a href="javascript:alert(1)">Bad link</a>',
            '<a href=\'http://single.example/\'><b>Bold</b> name</a>',
            'no anchor at all',
            array('provider' => '', 'providerUri' => 'https://x.example/'),
            array('provider' => 'No link'),
            '<a href="https://6.example/">6</a>', '<a href="https://7.example/">7</a>',
            '<a href="https://8.example/">8</a>', '<a href="https://9.example/">9</a>',
        ));
        $out['a_bad'] = array(SLP_Avalon::avalon_hours_attributions('x'), SLP_Avalon::avalon_hours_attributions(null));
        break;

    case 'payload':
        $P = function ($row, $cc = 'US', $st = 'NY') { return SLP_Avalon::avalon_hours_payload($row, $cc, $st, 30, T_NOW); };
        $out['base'] = $P(prow());
        foreach (array('none', 'blocked', 'failed', 'pending', '', 'OK') as $hs) {
            $out['hs'][$hs] = $P(prow(array('hours_status' => $hs)));
        }
        foreach (array('CLOSED_PERMANENTLY', 'CLOSED_TEMPORARILY', 'closed_permanently') as $bs) {
            $out['bs'][$bs] = $P(prow(array('business_status' => $bs)));
            $out['ps'][$bs] = $P(prow(array(), place(array('businessStatus' => $bs))));
        }
        $out['bs_null'] = $P(prow(array('business_status' => null)));
        $six = place(); array_pop($six['regularOpeningHours']['weekdayDescriptions']);
        $out['six'] = $P(prow(array(), $six));
        $eight = place(); $eight['regularOpeningHours']['weekdayDescriptions'][] = 'Monday: Closed';
        $out['eight'] = $P(prow(array(), $eight));
        $broken = place(); $broken['regularOpeningHours']['weekdayDescriptions'][3] = 'Thursday 9 to 5';
        $out['broken_line'] = $P(prow(array(), $broken));
        $out['bad_json'] = $P(prow(array('hours_json' => '{not json')));
        $out['no_place'] = $P(prow(array('hours_json' => json_encode(array('shape' => 'new')))));
        $out['not_array'] = $P(null);
        $out['mismatch'] = $P(prow(), 'US', 'IL');
        $noff = place(); unset($noff['utcOffsetMinutes']);
        $out['no_offset'] = $P(prow(array(), $noff));
        $allday = place(array('regularOpeningHours' => array('weekdayDescriptions' => array_map(function ($d) {
            return $d . ': Open 24 hours'; }, array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')),
            'periods' => array(array('open' => array('day' => 0, 'hour' => 0, 'minute' => 0))))));
        $out['allday'] = $P(prow(array(), $allday));
        $junk = place(); $junk['regularOpeningHours']['periods'][] = array('open' => array('day' => 9, 'hour' => 9, 'minute' => 0));
        $junk['regularOpeningHours']['periods'][] = 'not a period';
        $junk['regularOpeningHours']['periods'][] = array('close' => array('day' => 1, 'hour' => 9, 'minute' => 0));
        $out['junk'] = $P(prow(array(), $junk));
        $out['json'] = json_encode($out['base']);
        $out['stale'] = $P(prow(array('fetched_at' => gmdate('Y-m-d H:i:s', T_NOW - 31 * 86400))));
        $out['day29'] = $P(prow(array('fetched_at' => gmdate('Y-m-d H:i:s', T_NOW - 29 * 86400))));
        $out['ttl7'] = SLP_Avalon::avalon_hours_payload(prow(array('fetched_at' => gmdate('Y-m-d H:i:s', T_NOW - 8 * 86400))), 'US', 'NY', 7, T_NOW);
        $out['no_fetched'] = $P(prow(array('fetched_at' => null)));
        $out['bad_fetched'] = $P(prow(array('fetched_at' => '2026-10-03T21:38:49Z')));
        $out['future'] = $P(prow(array('fetched_at' => gmdate('Y-m-d H:i:s', T_NOW + 2 * 86400))));
        $sun = place(); $wl = $sun['regularOpeningHours']['weekdayDescriptions'];
        $sun['regularOpeningHours']['weekdayDescriptions'] = array_merge(array($wl[6]), array_slice($wl, 0, 6));
        $out['sunday_first'] = $P(prow(array(), $sun));
        $dup = place(); $dup['regularOpeningHours']['weekdayDescriptions'][6] = 'Monday: Closed';
        $out['dup_day'] = $P(prow(array(), $dup));
        $es = place(); $es['regularOpeningHours']['weekdayDescriptions'][0] = 'lunes: 9:00 AM - 5:00 PM';
        $out['not_english'] = $P(prow(array(), $es));
        $dk = array_keys(SLP_Avalon::avalon_hours_disputed_keys());
        $out['disputed'] = $P(prow(array('address_key' => $dk[0])));
        $out['disputed_last'] = $P(prow(array('address_key' => $dk[count($dk) - 1])));
        $out['disputed_n'] = count($dk);
        $out['names'] = array_map(array('SLP_Avalon', 'avalon_hours_day_number'),
                                  array('Sunday', 'monday', ' TUESDAY ', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Mon', ''));
        break;

    case 'offsets':
        $out['ny'] = SLP_Avalon::avalon_hours_offsets('America/New_York', T_NOW);
        $out['phx'] = SLP_Avalon::avalon_hours_offsets('America/Phoenix', T_NOW);
        $out['bad'] = SLP_Avalon::avalon_hours_offsets('Not/AZone', T_NOW);
        $out['none'] = SLP_Avalon::avalon_hours_offsets('', T_NOW);
        $out['nov1'] = strtotime('2026-11-01 06:00:00 UTC');
        $out['mar14'] = strtotime('2027-03-14 07:00:00 UTC');
        break;

    case 'markup':
        $p = SLP_Avalon::avalon_hours_payload(prow(array(), place(array('attributions' => array(
            '<a href="https://example.com/a?x=1&amp;y=2">Ex &amp; Co</a>', array('provider' => 'Plain'))))), 'US', 'NY', 30, T_NOW);
        $out['store'] = SLP_Avalon::avalon_hours_markup($p, 'store');
        $out['card']  = SLP_Avalon::avalon_hours_markup($p, 'card');
        $evil = $p;
        $evil['days'][0] = array(1, '<img src=x onerror=alert(1)>', '9 AM' . nd() . '5 PM"><script>');
        $evil['tz'] = '"><script>alert(1)</script>';
        $out['evil'] = SLP_Avalon::avalon_hours_markup($evil, 'store');
        $out['empty'] = array(SLP_Avalon::avalon_hours_markup(null, 'store'),
                              SLP_Avalon::avalon_hours_markup(array('days' => array()), 'card'));
        $q = $p; $q['tz'] = ''; $q['periods'] = array(); $q['o'] = array(); $q['u'] = 0;
        $out['nozone'] = SLP_Avalon::avalon_hours_markup($q, 'card');
        break;

    case 'store':
        global $wpdb;
        $row = locrow();
        $key = key_of($row);
        $wpdb->locator[777] = $row;
        $wpdb->places[$key] = fresh_prow($key);
        $GLOBALS['POST_TYPES'] = array(777 => 'store_page', 778 => 'page', 779 => 'store_page', 780 => 'store_page',
                                       140017 => 'elementor_library');
        $GLOBALS['THE_ID'] = 777;
        $out['ok'] = $o->avalon_store_hours_sc_func(array());
        $out['ok_log'] = $wpdb->log;
        $GLOBALS['THE_ID'] = 140017; $GLOBALS['QUERIED_ID'] = 777; $GLOBALS['SINGULAR'] = 'store_page'; $wpdb->log = array();
        $out['queried'] = $o->avalon_store_hours_sc_func(array());
        $out['queried_sql'] = $wpdb->log[0][1] ?? '';
        $GLOBALS['THE_ID'] = 140017; $GLOBALS['QUERIED_ID'] = 0; $wpdb->log = array();
        $out['template_only'] = $o->avalon_store_hours_sc_func(array());
        $GLOBALS['SINGULAR'] = ''; $GLOBALS['QUERIED_ID'] = 0;
        $wpdb->locator[778] = $row;   /* a plain page some row links: only the post-type check refuses it */
        $GLOBALS['THE_ID'] = 778; $out['not_store'] = $o->avalon_store_hours_sc_func(array());
        $GLOBALS['THE_ID'] = 0; $out['no_post'] = $o->avalon_store_hours_sc_func(array());
        $GLOBALS['THE_ID'] = 779; $wpdb->log = array();
        $out['no_locator'] = $o->avalon_store_hours_sc_func(array());
        $out['no_locator_log'] = count($wpdb->log);
        $wpdb->locator[780] = locrow(array('sl_address' => '200 Other Road'));
        $GLOBALS['THE_ID'] = 780; $out['no_place'] = $o->avalon_store_hours_sc_func(array());
        $wpdb->places[$key]['hours_status'] = 'none';
        $GLOBALS['THE_ID'] = 777; $out['none'] = $o->avalon_store_hours_sc_func(array());
        $wpdb->places[$key]['hours_status'] = 'ok';
        $keep = $wpdb->places[$key]['fetched_at'];
        $wpdb->places[$key]['fetched_at'] = gmdate('Y-m-d H:i:s', time() - 31 * 86400);
        $out['stale'] = $o->avalon_store_hours_sc_func(array());
        $wpdb->places[$key]['fetched_at'] = $keep;
        $wpdb->locator[781] = locrow(array('sl_address' => '', 'sl_city' => ''));
        $GLOBALS['POST_TYPES'][781] = 'store_page';
        $GLOBALS['THE_ID'] = 781; $wpdb->log = array();
        $out['keyless'] = $o->avalon_store_hours_sc_func(array());
        $out['keyless_log'] = $wpdb->log;
        $wpdb->refuse = true; $GLOBALS['THE_ID'] = 777;
        $out['refused'] = $o->avalon_store_hours_sc_func(array());
        $wpdb->refuse = false;
        $out['unparsed'] = array_values(array_filter($wpdb->log, function ($e) { return in_array($e[0], array('UNPARSED', 'MISMATCH'), true); }));
        break;

    case 'markers':
        global $wpdb;
        $r1 = locrow(array('sl_id' => '1'));
        $r2 = locrow(array('sl_id' => '2', 'sl_address' => '200 Second Avenue'));
        $r4 = locrow(array('sl_id' => '4'));
        $r5 = locrow(array('sl_id' => '5', 'sl_address' => '500 Fifth Lane', 'sl_country' => 'MX', 'sl_state' => 'JAL'));
        $k1 = key_of($r1);
        $k2 = key_of($r2);
        $wpdb->places[$k1] = fresh_prow($k1);
        $wpdb->places[$k2] = array_merge(fresh_prow($k2), array('hours_status' => 'none'));
        $res = array('count' => 5, 'response' => array(
            marker($r1, '212-555-0101'),
            marker($r2, '(212) 555-0102'),
            array('id' => '3', 'name' => 'No data', 'address' => '1 Nowhere', 'phone' => '212-555-0103'),
            marker($r4, ''),
            marker($r5, '212-555-0105'),
        ));
        $out['res'] = $o->avalon_hours_attach_markers($res);
        $out['log'] = $wpdb->log;
        $out['in'] = $res;
        $wpdb->log = array();
        $wpdb->refuse = true;
        $out['refused'] = $o->avalon_hours_attach_markers($res);
        $out['refused_same'] = ($out['refused'] === $res);
        $wpdb->refuse = false;
        $out['empty'] = $o->avalon_hours_attach_markers(array('count' => 0, 'response' => array()));
        $out['notarray'] = $o->avalon_hours_attach_markers('x');
        $out['nokeys_log'] = array();
        $wpdb->log = array();
        $o->avalon_hours_attach_markers(array('response' => array(array('id' => '9', 'phone' => '', 'address' => ''))));
        $out['nokeys_log'] = $wpdb->log;
        break;

    case 'labels':
        $L = array(
            marker(locrow(array('sl_id' => '1')), '212-555-0101'),
            marker(locrow(array('sl_id' => '2', 'sl_address' => '200 Second Avenue')), '(212) 555-0102'),
            array('id' => '3', 'name' => 'No data', 'address' => '1 Nowhere', 'phone' => '212-555-0103'),
            marker(locrow(array('sl_id' => '4')), ''),
            marker(locrow(array('sl_id' => '5', 'sl_address' => '500 Fifth Lane', 'sl_country' => 'MX', 'sl_state' => 'JAL')), '212-555-0105'),
            marker(locrow(array('sl_id' => '6', 'sl_address' => '600 Sixth Street', 'sl_country' => 'Mexico', 'sl_state' => 'BC',
                                'sl_zip' => '22000')), '212-555-0106'),
            marker(locrow(array('sl_id' => '7', 'sl_address' => '700 Seventh Street', 'sl_country' => '', 'sl_state' => 'ON',
                                'sl_zip' => 'M5V 2T6')), '416-555-0107'),
            marker(locrow(array('sl_id' => '8', 'sl_address' => '800 Eighth Street', 'sl_country' => 'Mexico', 'sl_state' => 'JAL',
                                'sl_zip' => '44100')), '212-555-0108'),
        );
        foreach ($L as $m) { $out['m'][] = $o->avalon_marker_labels($m); }
        $out['in'] = $L;
        $out['notarray'] = $o->avalon_marker_labels('x');
        $out['tc'] = array(
            SLP_Avalon::avalon_tel_country(array('sl_country' => 'USA'), 'ON'),
            SLP_Avalon::avalon_tel_country(array('sl_country' => 'Canada'), 'NY'),
            SLP_Avalon::avalon_tel_country(array('sl_country' => 'Mexico'), 'BC'),
            SLP_Avalon::avalon_tel_country(array('sl_country' => ''), 'BC'),
            SLP_Avalon::avalon_tel_country(array('sl_country' => ' '), 'NY'),
            SLP_Avalon::avalon_tel_country(array(), ''),
            SLP_Avalon::avalon_tel_country(null, 'NY'),
        );
        break;

    case 'layout':
        $aura = '<div id="slp_results_[slp_location id]" class="results_entry [slp_location featured]"> <div class="results_row_full_column" id="slp_left_cell_[slp_location id]" > <h3 class="store_locator_name"><a href="[html ifset url][slp_location url][html ifset url]">[slp_location name]</a></h3> <span class="location_distance">Distance: [slp_location distance_1] [slp_location distance_unit]</span> </div> <div class="results_row_full_column sl_contact__info" id="slp_center_cell_[slp_location id]" > <span class="slp_result_address slp_result_street">[slp_location address]</span> <span class="slp_result_address slp_result_street2">[slp_location address2]</span> <span class="slp_result_address slp_result_citystatezip">[slp_location city_state_zip]</span> <span class="slp_result_address slp_result_country">[slp_location country]</span> <span class="slp_result_address slp_result_phone">[slp_location phone]</span> <span class="slp_result_address slp_result_fax">[slp_location fax]</span> </div> <div class="results_row_full_column" id="slp_right_cell_[slp_location id]" > <span class="slp_result_contact slp_result_directions"><a href="http://[slp_location map_domain]/maps?saddr=[slp_location search_address]&daddr=[slp_location location_address]" target="_blank" class="btn button btn-primary btn-lg store_locator_get_direction">[slp_location directions_text]</a></span> <span class="slp_result_contact slp_result_directions"><a href="/contact-dealer/?store_id=[slp_location id]&dealer_id=[slp_location identifier]&search=[slp_location search_address]&address=[slp_location location_address]" class="btn button btn-primary btn-lg store_locator_contact_store"> Contact Dealer</a></span> <span class="slp_result_contact slp_result_hours">[slp_location hours]</span> [slp_location iconarray wrap="fullspan"] </div> </div>';
        $out['aura'] = $o->avalon_results_layout($aura);
        $out['again'] = $o->avalon_results_layout($out['aura']);
        $nophone = str_replace('<span class="slp_result_address slp_result_phone">[slp_location phone]</span> ', '', $aura);
        $out['nophone'] = $o->avalon_results_layout($nophone);
        $unknown = '<div class="x">[slp_location name] [slp_location phone]</div>';
        $out['unknown'] = array($unknown, $o->avalon_results_layout($unknown));
        $order = '<span class="slp_result_phone slp_result_address" data-x="1"> [slp_location phone] </span>';
        $out['order'] = $o->avalon_results_layout($order);
        $out['aura_in'] = $aura;
        $edge = '<span class="slp_result_address slp_result_street">[slp_location address]</span>';
        $e1 = $o->avalon_results_layout($edge);
        $out['edge'] = array($e1, $o->avalon_results_layout($e1));
        break;

    case 'jsopts':
        $o->t_wire();
        /* SLP's add_to_js_options() at 10 puts the filtered layout in; SLP
           Experience's modify_js_options() at 90 merges a results layout
           left in its stored settings over it; then whatever add_actions()
           registered on slp_js_options. Priority order, as apply_filters(). */
        $stored = '<span class="slp_result_address slp_result_street">[slp_location address]</span> '
                . '<span class="slp_result_address slp_result_phone">[slp_location phone]</span>';
        $chain = array(
            array(10, 0, function ($opt) use ($o, $stored) { $opt['resultslayout'] = $o->avalon_results_layout($stored); return $opt; }),
            array(90, 0, function ($opt) use ($stored) { $opt['resultslayout'] = $stored; return $opt; }),
        );
        $seq = 1;
        foreach ($GLOBALS['REC']['filters'] as $f) {
            if ($f[0] === 'slp_js_options' && $f[1] === 'SLP_Avalon->avalon_js_options_layout') {
                $chain[] = array($f[2], $seq++, function ($opt) use ($o) { return $o->avalon_js_options_layout($opt); });
            }
        }
        usort($chain, function ($a, $b) { return $a[0] === $b[0] ? $a[1] - $b[1] : $a[0] - $b[0]; });
        $v = array('map_region' => 'us', 'resultslayout' => 'x');
        foreach ($chain as $c) { $v = $c[2]($v); }
        $out['chain'] = $v;
        $out['chain_n'] = count($chain);
        $out['noop'] = array($o->avalon_js_options_layout(array('map_region' => 'us')),
                             $o->avalon_js_options_layout(array('resultslayout' => array('x'))),
                             $o->avalon_js_options_layout('x'));
        $good = $o->avalon_results_layout($stored);
        $out['again'] = array($good, $o->avalon_js_options_layout(array('resultslayout' => $good)));
        break;

    case 'tel':
        foreach (array(array('212-555-0101', 'US'), array('(212) 555-0101', 'US'), array('1-212-555-0101', 'US'),
                       array('2125550101', 'CA'), array('212.555.0101', 'ca'), array('(212)555-0101', 'US'),
                       array('', 'US'), array('555-0101', 'US'), array('012-555-0101', 'US'),
                       array('112-555-0101', 'US'), array('212-555-0101', 'MX'), array('212-555-0101', ''),
                       array('+1 (212) 555-0101', 'US'), array('212-555-0101 x12', 'US')) as $c) {
            $out['t'][] = SLP_Avalon::avalon_tel($c[0], $c[1]);
        }
        $out['default_country'] = SLP_Avalon::avalon_tel('212-555-0101');
        $out['fields'] = array(
            SLP_Avalon::avalon_card_fields(array('phone' => '212-555-0101', 'address' => '1 Test St',
                'data' => array('sl_phone' => '212-555-0101')), 'US'),
            SLP_Avalon::avalon_card_fields(array('phone' => '', 'address' => '', 'data' => array('sl_phone' => '')), 'US'),
            SLP_Avalon::avalon_card_fields(array('phone' => 'call &amp; ask', 'address' => '1 Test St',
                'data' => array('sl_phone' => 'call & ask')), 'US'),
            SLP_Avalon::avalon_card_fields(array('phone' => '212-555-0101', 'address' => '1 Test St'), 'US'),
            SLP_Avalon::avalon_card_fields(array('phone' => '<a href="tel:2125550101">212-555-0101</a>', 'address' => '1 Test St',
                'data' => array('sl_phone' => '212-555-0101')), 'US'),
        );
        break;

    case 'enqueue':
        $o->avalon_hours_enqueue();
        $out['front'] = $GLOBALS['REC'];
        $GLOBALS['REC']['styles'] = $GLOBALS['REC']['scripts'] = $GLOBALS['REC']['inline'] = array();
        $GLOBALS['IS_ADMIN'] = true;
        $o->avalon_hours_enqueue();
        $out['admin'] = $GLOBALS['REC'];
        $out['bp'] = SLP_Avalon::avalon_hours_breakpoint();
        break;

    case 'elementor':
        eval('namespace Elementor; class Plugin { public static $instance; }
              class T_BP { public $v; function __construct($v) { $this->v = $v; } function get_value() { return $this->v; } }
              class T_BM { public $v; function __construct($v) { $this->v = $v; }
                           function get_breakpoints($n = null) { if ($this->v === "throw") { throw new \RuntimeException("x"); }
                                                                 return $n === "mobile" ? new T_BP($this->v) : array(); } }');
        foreach (array(880, 600, 5000, 'throw', 767) as $v) {
            \Elementor\Plugin::$instance = (object) array('breakpoints' => new \Elementor\T_BM($v));
            $GLOBALS['REC']['inline'] = array();
            $out['bp'][] = SLP_Avalon::avalon_hours_breakpoint();
            $o->avalon_hours_enqueue();
            $out['inline'][] = $GLOBALS['REC']['inline'];
        }
        \Elementor\Plugin::$instance = null;
        $out['bp_null'] = SLP_Avalon::avalon_hours_breakpoint();
        break;

    case 'rocket':
        $out['d'] = array(SLP_Avalon::avalon_rocket_delay_exclusions(array('/existing/')),
                          SLP_Avalon::avalon_rocket_delay_exclusions(null));
        $out['r'] = array(SLP_Avalon::avalon_rocket_rucss_safelist(array('.keep')),
                          SLP_Avalon::avalon_rocket_rucss_safelist(''));
        break;

    case 'rocket_path':
        $out['d'] = SLP_Avalon::avalon_rocket_delay_exclusions(array());
        $out['r'] = SLP_Avalon::avalon_rocket_rucss_safelist(array());
        break;

    case 'wiring':
        $o->t_wire();
        $out = $GLOBALS['REC'];
        /* A real filter chain for the results layout: SLP Experience's
           modify_results_layout() at 90 starts from the stored layout and
           discards its input (s0.279); ours runs at whatever priority
           add_actions() registered. Callbacks run in priority order, as
           apply_filters() runs them. */
        $stored = '<span class="slp_result_address slp_result_street">[slp_location address]</span> '
                . '<span class="slp_result_address slp_result_phone">[slp_location phone]</span>';
        $chain = array(array(90, 0, function ($in) use ($stored) { return $stored; }));
        $seq = 1;
        foreach ($GLOBALS['REC']['filters'] as $f) {
            if ($f[0] === 'slp_javascript_results_string' && $f[1] === 'SLP_Avalon->avalon_results_layout') {
                $chain[] = array($f[2], $seq++, function ($in) use ($o) { return $o->avalon_results_layout($in); });
            }
        }
        usort($chain, function ($a, $b) { return $a[0] === $b[0] ? $a[1] - $b[1] : $a[0] - $b[0]; });
        $v = 'whatever SLP core handed in';
        foreach ($chain as $c) { $v = $c[2]($v); }
        $out['chain'] = $v;
        $out['chain_n'] = count($chain);
        break;

    default:
        $out['CRASH'] = 'unknown scenario ' . $s;
    }
} catch (Throwable $e) {
    $out['CRASH'] = get_class($e) . ': ' . $e->getMessage() . ' at line ' . $e->getLine();
}
echo json_encode($out);
PHPRUN;

$harness = $head . "\nrequire " . var_export($akCopy, true) . ";\n\nclass SLP_Avalon {\n"
         . "    private static \$instance;\n\n"
         . $LIFTS['consts'] . $LIFTS['filever'] . $LIFTS['wiring'] . $LIFTS['loader']
         . $LIFTS['geocode'] . $LIFTS['table'] . $LIFTS['fetch'] . $LIFTS['disputed'] . $LIFTS['display']
         . $classTail . $run;
$hfile = $tmp . DIRECTORY_SEPARATOR . 'harness.php';
file_put_contents($hfile, $harness);

$CRASHES = array();
function run($hfile, $scenario)
{
    global $CRASHES;
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($hfile) . ' ' . escapeshellarg($scenario) . ' 2>&1';
    $raw = shell_exec($cmd);
    $r = json_decode((string) $raw, true);
    if (! is_array($r)) {
        $CRASHES[$scenario] = trim(substr((string) $raw, 0, 400));
        return array();
    }
    if (isset($r['CRASH'])) {
        $CRASHES[$scenario] = $r['CRASH'];
    }
    return $r;
}
function has($hay, $needle) { return is_string($hay) && strpos($hay, $needle) !== false; }
/* ?? treats a real null as missing, so a null result is tested by key. */
function isnull($r, $k) { return is_array($r) && array_key_exists($k, $r) && $r[$k] === null; }

$lint = shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($hfile) . ' 2>&1');
if (strpos((string) $lint, 'No syntax errors') === false) {
    fwrite(STDERR, "the harness does not compile:\n{$lint}\n");
    exit(2);
}

$ND = "\xE2\x80\x93";

/* ------------------------------------------------------------- s0.273 */

echo "\n  s0.273 KEYS\n";
$r = run($hfile, 'keys');
$k = $r['k'] ?? array();
check(($k[0] ?? null) === array('B', 'G'), 'both set: the browser gets the Browser Key, the server the Geocoding Key');
check(($k[1] ?? null) === array('B', 'B') && ($k[2] ?? null) === array('B', 'B') && ($k[3] ?? null) === array('B', 'B'),
      'Geocoding Key empty, blank or absent: the server falls back to the Browser Key, as SLP does');
check(($k[4] ?? null) === array('', 'G') && ($k[5] ?? null) === array('', 'G'),
      'Browser Key empty or absent: the browser gets NOTHING - never the Geocoding Key');
check(($k[6] ?? null) === array('', '') && ($r['none'] ?? null) === array('', ''), 'no keys, or no SLP: both empty, no crash');

$r = run($hfile, 'loader');
$l = $r['l'] ?? array();
check(($l[0][1] ?? null) === 'B', 'the Maps loader prints the Browser Key');
check(isset($l[1]) && $l[1][1] === null && ! has($l[1][0], 'G'), 'with no Browser Key the loader prints no key, and not the Geocoding Key');
check(($l[2][1] ?? null) === 'B', 'Geocoding Key empty: the loader still prints the Browser Key');
check(isset($l[0][0]) && has($l[0][0], 'callback=avalon_init_gmaps') && has($l[0][0], 'v=quarterly')
      && has($l[0][0], 'libraries=geometry,places'), 'the rest of the loader URL is unchanged');

$r = run($hfile, 'geocode');
check(($r['g'] ?? null) === array('G', 'B'), 'import geocoding sends the Geocoding Key, else the Browser Key');
$r = run($hfile, 'details_legacy');
check(($r['d'][0][0] ?? null) === 'G' && ($r['d'][1][0] ?? null) === 'B',
      'the hours fetch (legacy) sends the Geocoding Key, else the Browser Key');
check(($r['d'][0][1] ?? null) === 'place_id,name,business_status,opening_hours,utc_offset',
      'the legacy field list is untouched');
$r = run($hfile, 'details_new');
check(($r['d'] ?? null) === array('G', 'B'), 'the hours fetch (Places New, X-Goog-Api-Key) sends the Geocoding Key, else the Browser Key');
check(substr_count($code, '$key = $this->avalon_google_server_key();') === 1,
      'the place resolver takes its key from avalon_google_server_key() (by identity, above, and by text)');

/* --------------------------------------------------------------- zone */

echo "\n  TIME ZONE\n";
$r = run($hfile, 'zone');
$z = $r['z'] ?? array();
$want = array('America/Detroit', 'America/Menominee', 'America/Chicago', 'America/New_York', 'America/Chicago',
              'America/Phoenix', 'America/Denver', 'America/Phoenix', 'America/Regina', 'America/Regina',
              'America/Edmonton', 'America/Los_Angeles', 'America/Boise', 'America/Denver', 'America/Vancouver',
              'America/Vancouver', 'America/Chicago', 'America/Indiana/Indianapolis', '', '', '', '', '',
              'America/Detroit', 'America/Chicago', 'America/New_York', 'America/Toronto', 'America/Winnipeg',
              'Pacific/Honolulu', '', '', '', '', 'America/Chicago', 'America/New_York', 'America/Phoenix',
              'America/Rankin_Inlet', 'America/Cambridge_Bay', 'America/Edmonton');
$labels = array('MI at EDT', 'MI Upper Peninsula at CDT', 'FL panhandle at CDT', 'FL at EDT', 'FL panhandle at CST (winter)',
                'AZ at MST in summer', 'AZ Navajo Nation at MDT', 'AZ in winter: Phoenix, first in order',
                'SK in summer', 'SK in winter', 'SK at MST in winter: Lloydminster', 'ID north at PDT',
                'ID Boise at MDT', 'NE west at MDT', 'BC at PDT', 'BC at PST (winter)', 'IN west at CDT', 'IN at EDT',
                'OH at a Central offset: no zone', 'no offset: no zone', 'no fetched_at: no zone',
                'fetched_at in another format: no zone', 'a country with no list: no zone', 'lower-case codes',
                'TN west at CDT', 'TN east at EDT', 'QC at EDT', 'ON north-west at CDT', 'HI',
                'TN at -05 in the hour clocks fall back (New York and Chicago crossing): no zone',
                'FL at -05, the same hour: no zone', 'NE at -06 as Central and Mountain cross: no zone',
                'ON at -05 as Toronto and Winnipeg cross: no zone', 'TN at -05 an hour before: Chicago',
                'TN at -05 an hour after: New York', 'AZ just after Denver falls back - agreeing, not crossing: Phoenix',
                'NU at Central time: Rankin Inlet', 'NU at Mountain time: Cambridge Bay, no longer Edmonton',
                'NT in winter 2026: Edmonton (Yellowknife), first in order');
foreach ($want as $i => $w) {
    check(array_key_exists($i, $z) && $z[$i] === $w, sprintf('%s -> %s', $labels[$i], $w === '' ? "''" : $w));
}
$names = $r['names'] ?? array(0, array('?'), 0, 0);
check($names[0] > 80 && $names[1] === array(), sprintf('all %d candidate zone names exist in PHP\'s zone database', $names[0]));
check($names[2] === 51 && $names[3] === 13, '51 US entries (50 states and DC), 13 Canadian provinces and territories');

echo "\n  s0.278 THE SERVER'S ZONE DATA\n";
$r = run($hfile, 'rules');
check(($r['stale'] ?? null) === array('', '', 'America/Regina', 'America/Toronto', 'America/New_York'),
      'data without the 2026 rules: BC and AB get no zone; SK, ON and NY are untouched');
$sp = $r['stale_payload'] ?? null;
check(is_array($sp) && $sp['tz'] === '' && $sp['periods'] === array() && $sp['o'] === array() && count($sp['days']) === 7,
      '  ... and a BC dealer still prints the week, with no status');
check(($r['half'] ?? null) === array('America/Vancouver', '', 'America/Regina', 'America/Toronto', 'America/New_York'),
      'data with 2026b but not 2026c: Vancouver back, Edmonton still withheld');
check(($r['current'] ?? null) === array('America/Vancouver', 'America/Edmonton', 'America/Regina', 'America/Toronto', 'America/New_York'),
      'current data: both zones resolve');
$mc = $r['machine'] ?? array(0, 0, '?');
check(($r['live'] ?? null) === array($mc[0] === -420, $mc[1] === -360, true)
      && ($r['cache'] ?? null) === array('America/Vancouver' => $mc[0] === -420, 'America/Edmonton' => $mc[1] === -360),
      "unprimed, the guard reads this machine's own data, once per zone; no other zone is looked up");
printf("    [INFO] this machine: zone data %s; on 2027-01-15 Vancouver %d, Edmonton %d - %s\n", $mc[2], $mc[0], $mc[1],
       ($mc[0] === -420 && $mc[1] === -360) ? 'the 2026 rules are present' : 'the 2026 rules are MISSING here');

echo "\n  s0.278 OFFSET SCHEDULE\n";
$r = run($hfile, 'offsets');
$ny = $r['ny'] ?? array();
$offs = array_map(function ($e) { return $e[1]; }, $ny['o'] ?? array());
check(isset($ny['o'][0]) && $ny['o'][0][0] === 1791079200 - 86400 && $ny['o'][0][1] === -240,
      'New York: the window opens a day before now, at EDT (-240)');
check(isset($ny['o'][1]) && $ny['o'][1] === array($r['nov1'], -300) && isset($ny['o'][2]) && $ny['o'][2] === array($r['mar14'], -240),
      'then EST from 2026-11-01 06:00 UTC, EDT again from 2027-03-14 07:00 UTC');
check($offs === array(-240, -300, -240, -300) && ($ny['u'] ?? 0) === 1791079200 + 400 * 86400,
      'four entries in 400 days; the window ends 400 days out');
check(($r['phx'] ?? null) === array('o' => array(array(1791079200 - 86400, -420)), 'u' => 1791079200 + 400 * 86400),
      'Phoenix: one entry, MST all window');
check(($r['bad'] ?? null) === array('o' => array(), 'u' => 0) && ($r['none'] ?? null) === array('o' => array(), 'u' => 0),
      'an unknown zone, or none: an empty schedule');

/* --------------------------------------------------------------- line */

echo "\n  WEEKDAY LINES\n";
$r = run($hfile, 'line');
$l = $r['l'] ?? array();
check(($l[0] ?? null) === array('Monday', '9 AM' . $ND . '5 PM'), "Google's thin and narrow spaces: Monday 9 AM-5 PM, unspaced en dash");
check(($l[1] ?? null) === array('Sunday', '10 AM' . $ND . '3 PM'), 'a plain hyphen with spaces becomes the en dash');
check(($l[2] ?? null) === array('Tuesday', '9 AM' . $ND . '12:30 PM, 1' . $ND . '5 PM'), 'a split day: 12:30 kept, :00 dropped only where a time ends');
check(($l[3] ?? null) === array('Wednesday', '9 AM' . $ND . '5 PM'), 'a no-break space counts as a space');
check(($l[4] ?? null) === array('Saturday', 'Closed') && ($l[5] ?? null) === array('Friday', 'Open 24 hours'),
      'Closed and Open 24 hours pass through');
check(($l[6] ?? null) === array('Thursday', '12 PM' . $ND . '12 AM'), 'an em dash becomes the en dash; noon to midnight');
check(($l[7] ?? null) === array('', '') && ($l[8] ?? null) === array('', '9 AM' . $ND . '5 PM'),
      'no separator: nothing; no day name: an empty name the payload refuses');

/* ------------------------------------------------- points, attributions */

echo "\n  PERIOD POINTS AND ATTRIBUTIONS\n";
$r = run($hfile, 'pt_attr');
$p = $r['p'] ?? array();
check(($p[0] ?? null) === array(1, 9, 30) && ($p[1] ?? null) === array(0, 0, 0) && ($p[2] ?? null) === array(6, 24, 0),
      'valid points become three integers; a missing minute is 0; hour 24 is allowed');
check(array_slice($p, 3) === array(null, null, null, null, null, null, null),
      'day 7, day -1, hour 25, minute 60, no day, a string, null: all refused');
$a = $r['a'] ?? array();
check(($a[0] ?? null) === array('Example & Co', 'https://example.com/x?a=1&b=2'), 'a legacy anchor: text and href, entities decoded');
check(($a[1] ?? null) === array('Provider', 'https://provider.example/'), 'a Places (New) attribution object');
check(($a[2] ?? null) === array('Bad link', ''), 'a javascript: URL is dropped; the text stays, unlinked');
check(($a[3] ?? null) === array('Bold name', 'http://single.example/'), 'single quotes; tags inside the anchor stripped');
check(($a[4] ?? null) === array('No link', '') && count($a) === 5, 'no anchor and empty provider skipped; at most five kept');
check(($r['a_bad'] ?? null) === array(array(), array()), 'not a list: nothing');

/* ------------------------------------------------------- gate, payload */

echo "\n  GATE AND PAYLOAD\n";
$r = run($hfile, 'payload');
$base = $r['base'] ?? null;
check(is_array($base) && array_keys($base) === array('days', 'periods', 'tz', 'o', 'u', 'attr'),
      'an ok row: days, periods, tz, o, u, attr - and nothing else');
check(is_array($base) && count($base['o']) >= 2 && $base['o'][0][1] === -240 && $base['u'] === 1791079200 + 400 * 86400,
      'the offset schedule rides along: EDT now, the November change ahead, a 400-day window');
check(is_array($base) && $base['tz'] === 'America/New_York' && count($base['days']) === 7 && count($base['periods']) === 6,
      'zone resolved; seven days; six periods (Sunday is closed and has none)');
check(is_array($base) && $base['days'][0] === array(1, 'Monday', '9 AM' . $ND . '5 PM')
      && $base['days'][5] === array(6, 'Saturday', '9 AM' . $ND . '1 PM') && $base['days'][6] === array(0, 'Sunday', 'Closed'),
      "days Monday first, each with Google's day number (Sunday 0)");
check(is_array($base) && $base['periods'][0] === array(array(1, 9, 0), array(1, 17, 0)), 'periods as [open, close] points');
$json = $r['json'] ?? '';
check($json !== '' && ! has($json, 'openNow') && ! has($json, 'open_now') && ! has($json, 'raw') && ! has($json, 'TESTPLACE'),
      'the payload carries no openNow, no raw and no place id');
$hs = $r['hs'] ?? array();
check(count($hs) === 6 && count(array_filter($hs)) === 0, 'hours_status none, blocked, failed, pending, empty, OK (case): nothing');
check(isset($r['bs']) && count(array_filter($r['bs'])) === 0, 'business_status closed (permanently or temporarily, any case): nothing');
check(isset($r['ps']) && count(array_filter($r['ps'])) === 0, 'the cached Place says closed: nothing, whatever the row says');
check(is_array($r['bs_null'] ?? null), 'business_status NULL counts as open');
check(isnull($r, 'six') && isnull($r, 'eight') && isnull($r, 'broken_line'),
      'six lines, eight lines, a line that does not split: nothing');
check(isnull($r, 'bad_json') && isnull($r, 'no_place') && isnull($r, 'not_array'),
      'bad JSON, no place, no row: nothing');
$m = $r['mismatch'] ?? null;
check(is_array($m) && $m['tz'] === '' && $m['periods'] === array() && $m['o'] === array() && $m['u'] === 0 && count($m['days']) === 7,
      "an offset matching none of the state's zones: the week, but no zone, no offsets, no periods");
$n = $r['no_offset'] ?? null;
check(is_array($n) && $n['tz'] === '' && $n['periods'] === array() && $n['o'] === array(), 'no offset: no zone, no offsets, no periods');
check(isnull($r, 'stale') && is_array($r['day29'] ?? null) && isnull($r, 'ttl7'),
      'fetched 31 days ago: nothing; 29 days: shown; 8 days against a 7-day TTL: nothing');
check(isnull($r, 'no_fetched') && isnull($r, 'bad_fetched') && isnull($r, 'future'),
      'no fetched_at, another format, or two days in the future: nothing');
$sf = $r['sunday_first'] ?? null;
check(is_array($sf) && $sf['days'][0] === array(0, 'Sunday', 'Closed') && $sf['days'][1][0] === 1,
      'a Sunday-first week keeps its order, each day numbered from its name');
check(isnull($r, 'dup_day') && isnull($r, 'not_english'), 'a repeated day, or a name that is not English: nothing');
check(isnull($r, 'disputed') && isnull($r, 'disputed_last') && ($r['disputed_n'] ?? 0) === 9,
      'a key on avalon_hours_disputed_keys() - the first or the last of nine: nothing, before any cron has blocked it');
check(($r['names'] ?? null) === array(0, 1, 2, 3, 4, 5, 6, -1, -1), 'day names: any case, trimmed; abbreviations and blanks refused');
$ad = $r['allday'] ?? null;
check(is_array($ad) && $ad['periods'] === array(array(array(0, 0, 0))), 'open 24 hours: the open point alone, no invented close');
$j = $r['junk'] ?? null;
check(is_array($j) && count($j['periods']) === 6, 'a bad point, a non-array, a period with no open: each dropped');

/* -------------------------------------------------------------- markup */

echo "\n  MARKUP\n";
$r = run($hfile, 'markup');
$st = $r['store'] ?? '';
$cd = $r['card'] ?? '';
check(substr_count($st, '<table class="avalon-hours__week">') === 2 && substr_count($cd, '<table class="avalon-hours__week">') === 1,
      'store page: two copies of the week; card: one');
check(has($st, '<h2>Hours</h2>') && ! has($cd, '<h2'), 'store page: the Hours heading; card: none');
check(has($st, 'class="storelocator_address_container avalon-hours avalon-hours--store"')
      && has($st, '<div class="store_locator_single_hours">'), "store page: inside the theme's own address-block classes");
check(has($st, '<div class="avalon-hours__wide"><p class="avalon-hours__status">&nbsp;</p>')
      && has($st, '<details class="avalon-hours__narrow"><summary class="avalon-hours__summary"><span class="avalon-hours__status">See hours</span></summary>'),
      'store page: an open table with a status line, and a closed <details> for phones');
check(strpos($st, 'avalon-hours__wide') < strpos($st, 'avalon-hours__narrow'), 'store page: the wide copy first, the fold second');
check(has($cd, '<div class="avalon-hours avalon-hours--card" data-avalon-hours=')
      && has($cd, '<summary class="avalon-hours__summary"><b class="avalon-label">Hours:</b> <span class="avalon-hours__status">See hours</span></summary>'),
      'card: a closed <details> labelled Hours:');
check(substr_count($st, '<tr data-day="1"><th scope="row">Monday</th><td>9 AM' . $ND . '5 PM</td></tr>') === 2
      && substr_count($st, '<tr data-day="0"><th scope="row">Sunday</th><td>Closed</td></tr>') === 2,
      'rows carry the day number and a row header');
foreach (array('store' => $st, 'card' => $cd) as $nm => $h) {
    check($h !== '' && preg_match('/<span[^>]*>\s*<\/span>/', $h) === 0, "{$nm}: no empty <span> for SLP to hide (s0.274)");
    check($h !== '' && preg_match('/\sid\s*=/i', $h) === 0, "{$nm}: no id attributes - two blocks on a page cannot collide");
    check($h !== '' && ! has($h, 'openNow') && ! has($h, 'open_now') && ! has($h, 'TESTPLACE'), "{$nm}: no openNow, no place id");
    preg_match('/data-avalon-hours="([^"]*)"/', $h, $mm);
    $d = isset($mm[1]) ? json_decode(html_entity_decode($mm[1], ENT_QUOTES, 'UTF-8'), true) : null;
    check(is_array($d) && array_keys($d) === array('tz', 'o', 'u', 'p') && $d['tz'] === 'America/New_York'
          && count($d['p']) === 6 && $d['o'][0][1] === -240 && $d['u'] > 0,
          "{$nm}: the data attribute is the zone, its offsets and the periods - nothing else");
}
check(substr_count($st, '<p class="avalon-hours__attr"><a href="https://example.com/a?x=1&amp;y=2" target="_blank" rel="nofollow noopener">Ex &amp; Co</a> &middot; Plain</p>') === 2,
      'attributions under each copy of the week: links escaped, rel nofollow noopener');
$ev = $r['evil'] ?? '';
check($ev !== '' && ! has($ev, '<img') && ! has($ev, '<script') && has($ev, '&lt;img src=x onerror=alert(1)&gt;'),
      'cached text is escaped - a day name or hours line cannot inject markup');
check($ev !== '' && ! has($ev, '"><script') && has($ev, 'data-avalon-hours="{&quot;tz&quot;:&quot;\&quot;&gt;&lt;script&gt;'),
      'the zone is JSON-encoded then attribute-escaped');
check(($r['empty'] ?? null) === array('', ''), 'no payload, no days: no markup at all');
preg_match('/data-avalon-hours="([^"]*)"/', $r['nozone'] ?? '', $mm);
check(isset($mm[1]) && html_entity_decode($mm[1], ENT_QUOTES, 'UTF-8') === '{"tz":"","o":[],"u":0,"p":[]}' && substr_count($r['nozone'], '<tr ') === 7,
      'no zone: the week still prints; the data attribute is empty');

/* ------------------------------------------------------------ store page */

echo "\n  [avalon_store_hours]\n";
$r = run($hfile, 'store');
check(has($r['ok'] ?? '', '<h2>Hours</h2>') && substr_count($r['ok'] ?? '', '<table') === 2, 'a store page with an ok row prints the block');
$lg = $r['ok_log'] ?? array();
$sqls = array_values(array_map(function ($e) { return $e[1]; }, array_filter($lg, function ($e) { return $e[0] === 'get_row'; })));
check(count($sqls) === 2, 'two reads, both get_row');
check(isset($sqls[0]) && $sqls[0] === "SELECT sl_id, sl_address, sl_city, sl_state, sl_zip, sl_country FROM wp_store_locator WHERE sl_linked_postid = 777 ORDER BY sl_id ASC LIMIT 1",
      'read 1: the locator row that links this post, lowest sl_id first');
check(isset($sqls[1]) && preg_match("/^SELECT address_key, hours_status, business_status, fetched_at, hours_json FROM wp_avalon_dealer_places WHERE address_key = '[0-9a-f]{12}'$/", $sqls[1]) === 1,
      'read 2: dealer-places by address key - never by sl_id');
check(count(array_filter($lg, function ($e) { return $e[0] === 'get_row' && $e[2] !== 'ARRAY_A'; })) === 0, 'both reads ask for ARRAY_A');
check(has($r['queried'] ?? '', '<h2>Hours</h2>') && has($r['queried_sql'] ?? '', 'WHERE sl_linked_postid = 777 '),
      'on a store page the queried post wins, even when get_the_ID() answers with the Theme Builder template');
check(($r['template_only'] ?? 'x') === '', 'the template alone, with no store page behind it: nothing');
check(($r['not_store'] ?? 'x') === '' && ($r['no_post'] ?? 'x') === '', 'not a store_page, or no post: nothing');
check(($r['no_locator'] ?? 'x') === '' && ($r['no_locator_log'] ?? 0) === 1, 'no locator row: nothing, and dealer-places is never read');
check(($r['no_place'] ?? 'x') === '' && ($r['none'] ?? 'x') === '', 'no dealer-places row, or a none row: nothing');
check(($r['stale'] ?? 'x') === '', 'an ok row fetched 31 days ago: nothing - the cap holds at render time');
$kl = $r['keyless_log'] ?? array();
check(($r['keyless'] ?? 'x') === '' && count(array_filter($kl, function ($e) { return $e[0] === 'get_row'; })) === 1,
      'a row with no street and no city: nothing, and no key read');
check(($r['refused'] ?? 'x') === '', 'a refused read: nothing, and no crash');
check(($r['unparsed'] ?? array('x')) === array(), 'every statement parsed; placeholders match arguments');

/* ---------------------------------------------------------- result cards */

echo "\n  RESULT CARDS\n";
$r = run($hfile, 'markers');
$res = $r['res']['response'] ?? array();
$lg = $r['log'] ?? array();
$gr = array_values(array_filter($lg, function ($e) { return $e[0] === 'get_results'; }));
check(count($gr) === 1 && count(array_filter($lg, function ($e) { return $e[0] === 'get_row'; })) === 0,
      'ONE read for the whole response, and no per-marker reads');
check(isset($gr[0]) && preg_match("/WHERE address_key IN \('[0-9a-f]{12}', '[0-9a-f]{12}', '[0-9a-f]{12}'\)$/", $gr[0][1]) === 1,
      'IN () lists each distinct key once - two markers for one dealer share it');
check(isset($gr[0]) && $gr[0][2] === 'ARRAY_A', 'the read asks for ARRAY_A');
check(count(array_filter($lg, function ($e) { return in_array($e[0], array('UNPARSED', 'MISMATCH'), true); })) === 0,
      'the statement parsed; placeholders match arguments');
check(has($res[0]['avalon_hours_html'] ?? '', 'avalon-hours--card') && has($res[3]['avalon_hours_html'] ?? '', 'avalon-hours--card'),
      'the ok dealer gets the hours block - on both of its markers');
check(! isset($res[1]['avalon_hours_html']) && ! isset($res[2]['avalon_hours_html']) && ! isset($res[4]['avalon_hours_html']),
      'a none row, a marker with no row data, a dealer with no row: no hours');
$in = $r['in']['response'] ?? array();
$same = count($res) === 5 && count($in) === 5;
foreach ($res as $i => $mm) { unset($mm['avalon_hours_html']); if ($mm !== ($in[$i] ?? null)) { $same = false; } }
check($same && ($r['res']['count'] ?? null) === 5,
      "apart from the hours every marker leaves as it came - the labels are slp_results_marker_data's job");
check(($r['refused_same'] ?? false) === true, 'a refused read: the response unchanged, no crash');
check(($r['empty'] ?? null) === array('count' => 0, 'response' => array()) && ($r['notarray'] ?? null) === 'x',
      'an empty response and a non-array pass through untouched');
check(($r['nokeys_log'] ?? array('x')) === array(), 'no marker with a key: no read at all');

/* ----------------------------------------------------------- the layout */

echo "\n  RESULTS LAYOUT\n";
$r = run($hfile, 'layout');
$ao = $r['aura'] ?? '';
$ai = $r['aura_in'] ?? '';
check(has($ao, '<span class="slp_result_address slp_result_street">[slp_location avalon_address_label][slp_location address]</span>'),
      'Address: at the start of the street line');
check(has($ao, '<span class="slp_result_address slp_result_phone">[slp_location avalon_phone_html]</span>[slp_location avalon_hours_html]'),
      'the phone becomes the labelled tel: field, and Hours: follows the phone line');
check(! has($ao, '[slp_location phone]') && substr_count($ao, 'avalon_hours_html') === 1 && substr_count($ao, 'avalon_address_label') === 1,
      'each field once; the bare phone field gone');
check(has($ao, '<span class="slp_result_address slp_result_street2">[slp_location address2]</span>'), 'street2 is not mistaken for street');
check(str_replace(array('[slp_location avalon_address_label]', '[slp_location avalon_phone_html]</span>[slp_location avalon_hours_html]'),
                  array('', '[slp_location phone]</span>'), $ao) === $ai, 'nothing else in the layout changes');
check(($r['again'] ?? '') === $ao, 'idempotent - a second pass changes nothing');
check(has($r['nophone'] ?? '', '[slp_location city_state_zip]</span>[slp_location avalon_hours_html]'),
      'no phone span: Hours: after the city line instead');
check(isset($r['unknown']) && $r['unknown'][0] === $r['unknown'][1], 'a layout it does not recognise is left as it was');
check(($r['order'] ?? '') === '<span class="slp_result_phone slp_result_address" data-x="1">[slp_location avalon_phone_html]</span>[slp_location avalon_hours_html]',
      'class order, extra attributes and spaces around the field do not matter');
check(isset($r['edge']) && substr_count($r['edge'][0], 'avalon_address_label') === 1 && $r['edge'][1] === $r['edge'][0],
      'a street line with neither a phone nor a city line: Address: once, and a second pass changes nothing');

echo "\n  SCRIPT OPTIONS (slp_js_options)\n";
$r = run($hfile, 'jsopts');
$cl = $r['chain']['resultslayout'] ?? '';
check(($r['chain_n'] ?? 0) === 3 && has($cl, '[slp_location avalon_address_label]')
      && has($cl, '[slp_location avalon_phone_html]</span>[slp_location avalon_hours_html]'),
      'a results layout SLP Experience merges over ours at 90 gets every field back at 100');
check(($r['chain']['map_region'] ?? null) === 'us', '  ... and no other option is touched');
check(($r['noop'] ?? null) === array(array('map_region' => 'us'), array('resultslayout' => array('x')), 'x'),
      'no layout, a layout that is not a string, options that are not an array: passed through');
check(isset($r['again'][1]['resultslayout']) && $r['again'][1]['resultslayout'] === $r['again'][0],
      'a layout that already has the fields: unchanged');

/* --------------------------------------------------------------- labels */

echo "\n  LABELS (slp_results_marker_data)\n";
$r = run($hfile, 'labels');
$m = $r['m'] ?? array();
check(($m[0]['avalon_phone_html'] ?? '') === '<b class="avalon-label">Phone:</b> <a class="avalon-tel" href="tel:+12125550101">212-555-0101</a>',
      'Phone: with a tel: link built from the raw number');
check(($m[1]['avalon_phone_html'] ?? '') === '<b class="avalon-label">Phone:</b> <a class="avalon-tel" href="tel:+12125550102">(212) 555-0102</a>',
      'the link text is the number as SLP shows it');
check(($m[2]['avalon_phone_html'] ?? '') === '<b class="avalon-label">Phone:</b> 212-555-0103', 'no row data to key on: the label, but no link');
check(! isset($m[3]['avalon_phone_html']) && isset($m[3]['avalon_address_label']), 'no phone: no Phone: label at all; Address: still');
check(($m[4]['avalon_phone_html'] ?? '') === '<b class="avalon-label">Phone:</b> 212-555-0105', 'Mexico as MX: no tel: link');
check(($m[5]['avalon_phone_html'] ?? '') === '<b class="avalon-label">Phone:</b> 212-555-0106',
      'Mexico written out, state BC, a five-digit postal code: no +1 - neither the state nor the code beats the written country');
check(($m[6]['avalon_phone_html'] ?? '') === '<b class="avalon-label">Phone:</b> <a class="avalon-tel" href="tel:+14165550107">416-555-0107</a>',
      'no country written, an Ontario row: the state decides, +1');
check(($m[7]['avalon_phone_html'] ?? '') === '<b class="avalon-label">Phone:</b> 212-555-0108',
      'Mexico and a five-digit postal code, which vector() would call US: no +1');
check(($m[0]['avalon_address_label'] ?? '') === '<b class="avalon-label">Address:</b> ' && isset($m[2]['avalon_address_label']),
      'Address: on every marker that has an address');
$kept = count($m) === 8;
foreach ($m as $i => $mm) { unset($mm['avalon_phone_html'], $mm['avalon_address_label']); if ($mm !== ($r['in'][$i] ?? null)) { $kept = false; } }
check($kept, 'nothing else on the marker changes');
check(($r['notarray'] ?? null) === 'x', 'a marker that is not an array passes through');
check(($r['tc'] ?? null) === array('US', 'CA', 'MEXICO', 'CA', 'US', '', 'US'),
      'tel: country: the written country wins over the state; a blank one falls back to the state');

/* ------------------------------------------------------------------ tel */

echo "\n  TEL\n";
$r = run($hfile, 'tel');
$t = $r['t'] ?? array();
check(array_slice($t, 0, 6) === array_fill(0, 6, '+12125550101'), 'all five feed punctuations, and a leading 1: +1 and ten digits');
check(array_slice($t, 6, 6) === array_fill(0, 6, ''), 'empty, seven digits, area code 0 or 1, Mexico, no country: no link');
check(($t[12] ?? null) === '+12125550101' && ($t[13] ?? null) === '', '+1 written out: fine; an extension: no link (none in the feeds)');
check(($r['default_country'] ?? null) === '+12125550101', 'country defaults to US');
$f = $r['fields'] ?? array();
check(($f[0]['avalon_phone_html'] ?? '') === '<b class="avalon-label">Phone:</b> <a class="avalon-tel" href="tel:+12125550101">212-555-0101</a>',
      'card fields: phone with link');
check(! isset($f[1]['avalon_phone_html']) && ! isset($f[1]['avalon_address_label']), 'card fields: nothing to label, no labels');
check(($f[2]['avalon_phone_html'] ?? '') === '<b class="avalon-label">Phone:</b> call &amp; ask', "card fields: SLP's escaped text kept as is, no link");
check(($f[3]['avalon_phone_html'] ?? '') === '<b class="avalon-label">Phone:</b> 212-555-0101', 'card fields: no raw row, no link');
check(($f[4]['avalon_phone_html'] ?? '') === '<b class="avalon-label">Phone:</b> <a href="tel:2125550101">212-555-0101</a>',
      'card fields: a phone that already carries a link is kept, not wrapped in a second one');

/* ------------------------------------------------- enqueue, breakpoint */

echo "\n  ENQUEUE\n";
$r = run($hfile, 'enqueue');
$fr = $r['front'] ?? array();
check(($fr['styles'] ?? null) === array(array('avalon-hours', 'https://example.test/wp-content/plugins/slp_avalon/assets/css/avalon-hours.css', array(), 1790000000, 'all')),
      'the stylesheet, from the plugin, versioned by file time');
check(($fr['scripts'] ?? null) === array(array('avalon-hours', 'https://example.test/wp-content/plugins/slp_avalon/assets/js/avalon-hours.js', array(), 1790000100, true)),
      'the script, no dependencies, in the footer');
check(($fr['inline'] ?? null) === array() && ($r['bp'] ?? null) === 767, "no Elementor: breakpoint 767 and no inline override");
$ad = $r['admin'] ?? array();
check(($ad['styles'] ?? null) === array() && ($ad['scripts'] ?? null) === array(), 'in wp-admin: nothing enqueued');

$r = run($hfile, 'elementor');
check(($r['bp'] ?? null) === array(880, 600, 767, 767, 767) && ($r['bp_null'] ?? null) === 767,
      "Elementor's mobile breakpoint is used; out of range, a throw, or no instance: 767");
$in = $r['inline'] ?? array();
check(isset($in[0][0][1]) && has($in[0][0][1], '@media (min-width:768px) and (max-width:880px){.avalon-hours--store .avalon-hours__wide{display:none}.avalon-hours--store .avalon-hours__narrow{display:block}}'),
      'breakpoint 880: 768-880 px fold');
check(isset($in[1][0][1]) && has($in[1][0][1], '@media (min-width:601px) and (max-width:767px){.avalon-hours--store .avalon-hours__wide{display:block}.avalon-hours--store .avalon-hours__narrow{display:none}}'),
      'breakpoint 600: 601-767 px open');
check(($in[2] ?? null) === array() && ($in[3] ?? null) === array() && ($in[4] ?? null) === array(), '767, or a fallback to it: no override');

$r = run($hfile, 'rocket');
check(($r['d'] ?? null) === array(array('/existing/', '/wp-content/plugins/slp_avalon/assets/js/avalon-hours'),
                                  array('/wp-content/plugins/slp_avalon/assets/js/avalon-hours')),
      'Delay JS: the hours script excluded, existing exclusions kept');
check(($r['r'][0] ?? null) === array('.keep', '/wp-content/plugins/slp_avalon/assets/css/avalon-hours.css',
                                     '(.*).avalon-hours(.*)', '(.*).avalon-label(.*)', '(.*).avalon-tel(.*)')
      && count($r['r'][1] ?? array()) === 4, 'Remove Unused CSS: the stylesheet and its selectors safelisted, existing entries kept');
$sel = array('.avalon-hours .avalon-hours__word--open', '.avalon-hours--store .avalon-hours__narrow', '.avalon-label',
             'a.avalon-tel', '.sl_contact__info a.avalon-tel:hover');
$hit = 0;
foreach ($sel as $one) {
    foreach (array_slice($r['r'][0] ?? array(), 2) as $pat) { if (preg_match('#^' . $pat . '#', $one)) { $hit++; break; } }
}
check($hit === count($sel), "every kind of selector in avalon-hours.css matches a pattern read from the selector's start, as WP Rocket 3.11.0.2+ reads them");
$r = run($hfile, 'rocket_path');
check(($r['d'] ?? null) === array('/sub/wp-content/plugins/slp_avalon-renamed/assets/js/avalon-hours')
      && ($r['r'][0] ?? null) === '/sub/wp-content/plugins/slp_avalon-renamed/assets/css/avalon-hours.css',
      "both patterns follow the plugin's own URL - a subdirectory site or a renamed folder still matches");

/* ------------------------------------------------------- registrations */

echo "\n  REGISTRATIONS\n";
$r = run($hfile, 'wiring');
$acts = $r['actions'] ?? array();
$fils = $r['filters'] ?? array();
$scs  = $r['shortcodes'] ?? array();
$find = function ($list, $row) { return count(array_filter($list, function ($e) use ($row) { return $e === $row; })); };
check($find($acts, array('wp_enqueue_scripts', 'SLP_Avalon->avalon_hours_enqueue', 20, 1)) === 1, 'wp_enqueue_scripts -> avalon_hours_enqueue, priority 20');
check($find($fils, array('slp_ajax_find_locations_complete', 'SLP_Avalon->avalon_hours_attach_markers', 30, 1)) === 1,
      'slp_ajax_find_locations_complete -> avalon_hours_attach_markers, priority 30 - after the backfill and territory_gate');
check($find($fils, array('slp_ajax_find_locations_complete', 'SLP_Avalon->territory_gate', 20, 1)) === 1
      && $find($acts, array('slp_ajax_find_locations_complete', 'SLP_Avalon->slp_ajax_find_locations_complete_filter', 10, 1)) === 1,
      '  ... and the two it must follow are still at 10 and 20');
check($find($fils, array('slp_javascript_results_string', 'SLP_Avalon->avalon_results_layout', 100, 1)) === 1,
      'slp_javascript_results_string -> avalon_results_layout, priority 100');
check($find($fils, array('slp_results_marker_data', 'SLP_Avalon->avalon_marker_labels', 20, 1)) === 1,
      "slp_results_marker_data -> avalon_marker_labels, priority 20 - after SLP Experience's marker filter at 15");
check($find($fils, array('slp_js_options', 'SLP_Avalon->avalon_js_options_layout', 100, 1)) === 1,
      'slp_js_options -> avalon_js_options_layout, priority 100 - after SLP Experience merges its settings at 90');
check(($r['chain_n'] ?? 0) === 2 && has($r['chain'] ?? '', '[slp_location avalon_phone_html]</span>[slp_location avalon_hours_html]')
      && has($r['chain'] ?? '', '[slp_location avalon_address_label]'),
      's0.279: through a real filter chain, the labels survive SLP Experience discarding its input at 90');
check($find($fils, array('rocket_delay_js_exclusions', 'SLP_Avalon::avalon_rocket_delay_exclusions', 10, 1)) === 1
      && $find($fils, array('rocket_rucss_safelist', 'SLP_Avalon::avalon_rocket_rucss_safelist', 10, 1)) === 1, 'the two WP Rocket filters');
check($find($scs, array('avalon_store_hours', 'SLP_Avalon->avalon_store_hours_sc_func')) === 1
      && $find($scs, array('avalon_map_location', 'SLP_Avalon->avalon_map_location_sc_func')) === 1 && count($scs) === 5,
      '[avalon_store_hours] registered beside [avalon_map_location]; five shortcodes');
check(count($acts) + count($fils) === 33, sprintf("%d registrations in all - Part 3b's %d plus these seven", 33, 33 - 7));

/* --------------------------------------------------------------- crashes */

echo "\n  HARNESS\n";
check($CRASHES === array(), 'no scenario crashed' . ($CRASHES ? ': ' . json_encode($CRASHES) : ''));

@unlink($hfile);
@unlink($akCopy);
@unlink($tmp . '/plugin/assets/css/avalon-hours.css');
@unlink($tmp . '/plugin/assets/js/avalon-hours.js');
@rmdir($tmp . '/plugin/assets/css');
@rmdir($tmp . '/plugin/assets/js');
@rmdir($tmp . '/plugin/assets');
@rmdir($tmp . '/plugin');
@rmdir($tmp);

printf("\n  %d passed, %d failed, %d total\n\n", $pass, $fail, $pass + $fail);
exit($fail ? 1 : 0);
