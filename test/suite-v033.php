<?php
/**
 * suite-v033.php - validates slp_avalon v0.0.27 Part 4b.
 *
 *   the bubble layout   avalon_bubble_layout() on Aura's live bubble layout
 *                       (read off DEV's page 2026-10-04) and on SLP's own
 *                       default (SLP 4.2.67's class.slplus.php, verbatim):
 *                       exact output, the order Distance, Address, Phone,
 *                       Email, Hours, each field once, idempotent; and on
 *                       layouts it must leave alone or only partly change
 *   the email field     avalon_email_fields(): is_email(), the characters a
 *                       mailto: URL reads as headers, escaping, the raw row
 *                       first, a no-break space raw or as SLP's &nbsp;,
 *                       invalid UTF-8
 *   the script options  avalon_js_options_bubble() through a real filter
 *                       chain: SLP at 10, SLP Experience at 90 replacing
 *                       the layout, then both callbacks at 100 - Part 4's
 *                       results layout and Part 4b's bubble layout
 *   the marker chain    Part 4's labels at 20, then the email at 25
 *   WP Rocket           the bubble's selectors on the safelist, matched
 *                       from the selector's start as 3.11.0.2+ reads them
 *   the registrations   36 in all: Part 4's 33 plus these three
 *
 * WHAT CARRIES FORWARD BY IDENTITY
 *
 * Part 4b is Part 4 plus one registration edit and one inserted block. The
 * first assertions take the block out, reverse the edit, and require the
 * result to be v0.0.27-part4 byte for byte - d9e4b7ed, 300,222 bytes. So
 * every region Part 4b did not touch carries suite-v032's 208/208 and its
 * 68 class controls forward unchanged, and an unlisted change anywhere in
 * the file fails that one assertion.
 *
 * WHAT IS EXECUTED
 *
 * The constants, add_actions() with register_shortcodes(), Part 4's block
 * and Part 4b's are lifted verbatim into a harness class and run against
 * recording doubles - is_email() is WordPress 6.8.3's, verbatim but for its
 * filter. ONE PROCESS PER SCENARIO; every warning and notice is an
 * exception, so a crash fails its scenario by name.
 *
 * The test data is synthetic: no dealer name, address, phone, email or
 * place id. Aura's bubble layout is a template, the site's own setting.
 *
 * Usage:
 *   php suite-v033.php <class.slp_avalon.php> [<class.slp_avalon_addresskey.php>]
 *
 * Exit 0 all passed, 1 any failed, 2 the suite could not run.
 */

$clsPath = $argv[1] ?? 'build/out-v027p4b/class.slp_avalon.php';
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

echo "suite-v033  slp_avalon 0.0.27 Part 4b\n";
printf("  class   %s\n          %s  %d bytes\n", $clsPath, md5($code), strlen($code));
printf("  addrkey %s\n          %s  %d bytes  (pinned)\n\n", $akPath, md5($ak), strlen($ak));

/* ------------------------------------------------------------------ */
/* IDENTITY: Part 4b minus its edits is Part 4, byte for byte.          */
/* ------------------------------------------------------------------ */

$P4 = array('d9e4b7eda90ead11e97211813847a42a', 300222);

$BLOCK_START = "        /**\r\n         * v0.0.27 Part 4b. The info bubble shows what the card shows.";
$BLOCK_END   = "        public function avalon_rest_protected_slugs(){";
$BLOCK_PIN   = array('b30b562412b58b2396ed51660c4fa628', 10933);
$P4_START    = "        /**\r\n         * v0.0.27 Part 4. Showing the hours.";

$crlf = function ($s) { return str_replace("\n", "\r\n", $s); };
$WIRE_OLD = $crlf(<<<'EOT'
            add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist'));

EOT);
$WIRE_NEW = $crlf(<<<'EOT'
            add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist'));
            //
            // v0.0.27 Part 4b. The info bubble shows what the card shows.
            //
            // The Email: field onto every marker at 25, after Part 4's
            // labels at 20. The bubble layout on slp_js_options at 100: SLP
            // puts its options in at 10 and SLP Experience merges its stored
            // settings over them at 90, so whichever bubble layout won is the
            // one given the fields. Same priority as Part 4's results-layout
            // callback, registered after it; the two touch different keys.
            // The bubble's selectors onto WP Rocket's safelist, beside Part
            // 4's; a no-op where WP Rocket is not installed.
            add_filter('slp_results_marker_data', array(self::$instance,'avalon_marker_email'), 25, 1);
            add_filter('slp_js_options', array(self::$instance,'avalon_js_options_bubble'), 100, 1);
            add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist_bubble'));

EOT);

echo "  IDENTITY\n";
$anchorsOk = substr_count($code, $BLOCK_START) === 1 && substr_count($code, $BLOCK_END) === 1
          && substr_count($code, $P4_START) === 1
          && strpos($code, $P4_START) < strpos($code, $BLOCK_START)
          && strpos($code, $BLOCK_START) < strpos($code, $BLOCK_END);
check($anchorsOk, "the Part 4b block is present once, after Part 4's, directly before avalon_rest_protected_slugs()");
if (! $anchorsOk) {
    fwrite(STDERR, "cannot find the Part 4b block; nothing else can be lifted\n");
    exit(2);
}
$a = strpos($code, $BLOCK_START);
$b = strpos($code, $BLOCK_END);
$block = substr($code, $a, $b - $a);
check(md5($block) === $BLOCK_PIN[0] && strlen($block) === $BLOCK_PIN[1],
      sprintf('the block is the one this suite was written against (%s, %d bytes)', $BLOCK_PIN[0], $BLOCK_PIN[1]));
$rev = substr($code, 0, $a) . substr($code, $b);
$wireOk = substr_count($rev, $WIRE_NEW) === 1;
check($wireOk, 'the registration edit is present exactly once');
if ($wireOk) {
    $rev = implode($WIRE_OLD, explode($WIRE_NEW, $rev, 2));
}
check(md5($rev) === $P4[0] && strlen($rev) === $P4[1],
      'block out and the edit reversed, the file IS v0.0.27-part4 (d9e4b7ed, 300,222 bytes)');
check(substr_count($code, "\r\n") === substr_count($code, "\n") && substr_count($code, "\r") === substr_count($code, "\r\n"),
      'pure CRLF - no bare LF, no bare CR');
check(preg_match('/[^\x00-\x7f]/', $block . $WIRE_NEW) === 0, 'the new block and registrations are pure ASCII');

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
    'wiring'  => span($code, '        private function add_actions(){',
                             '        private function init_admin(){'),
    'display' => span($code, $P4_START, $BLOCK_START),
    'bubble'  => $block,
);
foreach ($LIFTS as $n => $v) {
    if ($v === false || $v === '') {
        fwrite(STDERR, "cannot lift {$n} from {$clsPath}\n");
        exit(2);
    }
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

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'slp33_' . getmypid();
@mkdir($tmp, 0777, true);
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
define('ASLP_URL', 'https://example.test/wp-content/plugins/slp_avalon/');

$GLOBALS['REC'] = array('actions' => array(), 'filters' => array(), 'shortcodes' => array());
function cbname($cb) {
    if (is_array($cb)) { return (is_object($cb[0]) ? get_class($cb[0]) . '->' : $cb[0] . '::') . $cb[1]; }
    return is_string($cb) ? $cb : 'closure';
}
function add_action($h, $cb, $p = 10, $a = 1) { $GLOBALS['REC']['actions'][] = array($h, cbname($cb), $p, $a); return true; }
function add_filter($h, $cb, $p = 10, $a = 1) { $GLOBALS['REC']['filters'][] = array($h, cbname($cb), $p, $a); return true; }
function add_shortcode($t, $cb) { $GLOBALS['REC']['shortcodes'][] = array($t, cbname($cb)); }
/* esc_html() as WordPress: invalid UTF-8 gives '', existing entities are
   not encoded twice. esc_url() for the strings it is handed here: an
   address that passed the plugin's checks is all URL-safe but for an
   apostrophe, which WordPress writes as &#039; on display. */
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8', false); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8', false); }
function esc_url($s, $protocols = null) {
    $GLOBALS['ESC_URL'][] = array($s, $protocols);
    return str_replace(array('&', "'"), array('&#038;', '&#039;'), (string) $s);
}
function wp_make_link_relative($url) { return preg_replace('|^(https?:)?//[^/]+(/?.*)|i', '$2', $url); }
/* WordPress 6.8.3, wp-includes/formatting.php, is_email() - verbatim, but
   for the apply_filters() each return passes through. */
function is_email( $email, $deprecated = false ) {
    if ( strlen( $email ) < 6 ) { return false; }
    if ( strpos( $email, '@', 1 ) === false ) { return false; }
    list( $local, $domain ) = explode( '@', $email, 2 );
    if ( ! preg_match( '/^[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.-]+$/', $local ) ) { return false; }
    if ( preg_match( '/\.{2,}/', $domain ) ) { return false; }
    if ( trim( $domain, " \t\n\r\0\x0B." ) !== $domain ) { return false; }
    $subs = explode( '.', $domain );
    if ( 2 > count( $subs ) ) { return false; }
    foreach ( $subs as $sub ) {
        if ( trim( $sub, " \t\n\r\0\x0B-" ) !== $sub ) { return false; }
        if ( ! preg_match( '/^[a-z0-9-]+$/i', $sub ) ) { return false; }
    }
    return $email;
}
PHPHEAD;

$classTail = <<<'PHPTAIL'
    public static function t_boot() { return self::$instance = new SLP_Avalon(); }
    public function t_wire() { $this->add_actions(); $this->register_shortcodes(); }
}
PHPTAIL;

$run = <<<'PHPRUN'

/* ---------------------------------------------------------------- data */

/* Aura's bubble layout, as DEV served it in slplus.options on 2026-10-04. */
$AURA = <<<'EOT'
<div id="slp_info_bubble_[slp_location id]" class="slp_info_bubble [slp_location featured]"><span id="slp_bubble_name">[slp_location name suffix] </span><div class="sl_popup_contact_info"><span id="slp_bubble_address">[slp_location address suffix]</span> <span id="slp_bubble_address2">[slp_location address2 suffix]</span> <span id="slp_bubble_city">[slp_location city suffix comma]</span> <span id="slp_bubble_state">[slp_location state suffix space]</span> <span id="slp_bubble_zip">[slp_location zip suffix]</span> <span id="slp_bubble_country"><span id="slp_bubble_country">[slp_location country suffix ]</span> </span> <span id="slp_bubble_email">[slp_location email wrap mailto ][slp_option label_email ifset email][html ifset email][html closing_anchor ifset email]</span><span id="slp_bubble_phone">[html ifset phone] <span class="location_detail_label">[slp_option label_phone ifset phone]</span> [slp_location phone suffix ] </span> <span id="slp_bubble_fax"><span class="location_detail_label">[slp_option label_fax ifset fax ]</span>[slp_location fax suffix ]</span> <span id="slp_bubble_description"><span id="slp_bubble_description">[html ifset description] [slp_location description raw]</span>[html ifset description]</span> <span id="slp_bubble_hours">[html ifset hours] <span class="location_detail_label">[slp_option label_hours ifset hours]</span> <span class="location_detail_hours">[slp_location hours suffix]</span> </span> <span id="slp_bubble_img">[html ifset img] [slp_location image wrap img]</span> <span id="slp_tags">[slp_location tags]</span></div><span id="slp_bubble_directions">[html ifset directions] [slp_option label_directions wrap directions]</span> <span id="slp_bubble_website">[html ifset url][slp_location web_link][html ifset url]</span> </div>
EOT;
/* What Part 4b gives it - reviewed by hand, line by line. */
$AURA_OUT = <<<'EOT'
<div id="slp_info_bubble_[slp_location id]" class="slp_info_bubble [slp_location featured]"><span id="slp_bubble_name">[slp_location name suffix] </span><div class="sl_popup_contact_info"><span class="avalon-bubble-distance">Distance: [slp_location distance format="decimal1"] [slp_option distance_unit]</span><span id="slp_bubble_address">[slp_location avalon_address_label][slp_location address suffix]</span> <span id="slp_bubble_address2">[slp_location address2 suffix]</span> <span id="slp_bubble_city">[slp_location city suffix comma]</span> <span id="slp_bubble_state">[slp_location state suffix space]</span> <span id="slp_bubble_zip">[slp_location zip suffix]</span> <span id="slp_bubble_country"><span id="slp_bubble_country">[slp_location country suffix ]</span> </span> <span id="slp_bubble_phone">[slp_location avalon_phone_html]</span><span id="slp_bubble_email">[slp_location avalon_email_html]</span>[slp_location avalon_hours_html] <span id="slp_bubble_fax"><span class="location_detail_label">[slp_option label_fax ifset fax ]</span>[slp_location fax suffix ]</span> <span id="slp_bubble_description"><span id="slp_bubble_description">[html ifset description] [slp_location description raw]</span>[html ifset description]</span> <span id="slp_bubble_hours">[html ifset hours] <span class="location_detail_label">[slp_option label_hours ifset hours]</span> <span class="location_detail_hours">[slp_location hours suffix]</span> </span> <span id="slp_bubble_img">[html ifset img] [slp_location image wrap img]</span> <span id="slp_tags">[slp_location tags]</span></div><span id="slp_bubble_directions">[html ifset directions] [slp_option label_directions wrap directions]</span> <span id="slp_bubble_website">[html ifset url][slp_location web_link][html ifset url]</span> </div>
EOT;
/* SLP's own default, as SLP 4.2.67 shipped it (class.slplus.php) - its
   unclosed fax <span> included. */
$SLPDEF = <<<'EOT'
<div id="sl_info_bubble" class="[slp_location featured]">
    <span id="slp_bubble_name"><strong>[slp_location name  suffix  br]</strong></span>
    <span id="slp_bubble_address">[slp_location address       suffix  br]</span>
    <span id="slp_bubble_address2">[slp_location address2      suffix  br]</span>
    <span id="slp_bubble_city">[slp_location city          suffix  comma]</span>
    <span id="slp_bubble_state">[slp_location state suffix    space]</span>
    <span id="slp_bubble_zip">[slp_location zip suffix  br]</span>
    <span id="slp_bubble_country"><span id="slp_bubble_country">[slp_location country       suffix  br]</span></span>
    <span id="slp_bubble_directions">[html br ifset directions]
    [slp_option label_directions wrap directions]</span>
    <span id="slp_bubble_website">[html br ifset url]
    [slp_location url           wrap    website][slp_option label_website ifset url][html closing_anchor ifset url][html br ifset url]</span>
    <span id="slp_bubble_email">[slp_location email         wrap    mailto ][slp_option label_email ifset email][html closing_anchor ifset email][html br ifset email]</span>
    <span id="slp_bubble_phone">[html br ifset phone]
    <span class="location_detail_label">[slp_option   label_phone   ifset   phone]</span>[slp_location phone         suffix    br]</span>
    <span id="slp_bubble_fax"><span class="location_detail_label">[slp_option   label_fax     ifset   fax  ]</span>[slp_location fax           suffix    br]<span>
    <span id="slp_bubble_description"><span id="slp_bubble_description">[html br ifset description]
    [slp_location description raw]</span>[html br ifset description]</span>
    <span id="slp_bubble_hours">[html br ifset hours]
    <span class="location_detail_label">[slp_option   label_hours   ifset   hours]</span>
    <span class="location_detail_hours">[slp_location hours         suffix    br]</span></span>
    <span id="slp_bubble_img">[html br ifset img]
    [slp_location image         wrap    img]</span>
    <span id="slp_tags">[slp_location tags]</span>
    </div>
EOT;
$SLPDEF_OUT = str_replace(
    array('    <span id="slp_bubble_address">[slp_location address ',
          "    <span id=\"slp_bubble_email\">[slp_location email         wrap    mailto ][slp_option label_email ifset email][html closing_anchor ifset email][html br ifset email]</span>\n",
          "<span id=\"slp_bubble_phone\">[html br ifset phone]\n    <span class=\"location_detail_label\">[slp_option   label_phone   ifset   phone]</span>[slp_location phone         suffix    br]</span>\n"),
    array('    <span class="avalon-bubble-distance">Distance: [slp_location distance format="decimal1"] [slp_option distance_unit]</span><span id="slp_bubble_address">[slp_location avalon_address_label][slp_location address ',
          "    \n",
          "<span id=\"slp_bubble_phone\">[slp_location avalon_phone_html]</span><span id=\"slp_bubble_email\">[slp_location avalon_email_html]</span>[slp_location avalon_hours_html]\n"),
    $SLPDEF);

function sp($id, $inner, $attrs = '') { return '<span ' . ($attrs ? $attrs . ' ' : '') . 'id="' . $id . '">' . $inner . '</span>'; }
function eml() { return sp('slp_bubble_email', '[slp_location email wrap mailto ][slp_option label_email ifset email][html closing_anchor ifset email]'); }
function phn() { return sp('slp_bubble_phone', '[html ifset phone] <span class="location_detail_label">[slp_option label_phone ifset phone]</span> [slp_location phone suffix ] '); }
function adr() { return sp('slp_bubble_address', '[slp_location address suffix]'); }
function wrapb($s) { return '<div id="slp_info_bubble_[slp_location id]" class="slp_info_bubble">' . $s . '</div>'; }

/* ----------------------------------------------------------- scenarios */

$s = $argv[1] ?? '';
$out = array();
try {
    $o = SLP_Avalon::t_boot();
    switch ($s) {

    case 'aura':
        $r1 = $o->avalon_bubble_layout($AURA);
        $out['out'] = $r1;
        $out['want'] = $AURA_OUT;
        $out['again'] = $o->avalon_bubble_layout($r1);
        $out['third'] = $o->avalon_bubble_layout($out['again']);
        $out['in'] = $AURA;
        break;

    case 'slpdefault':
        $r1 = $o->avalon_bubble_layout($SLPDEF);
        $out['out'] = $r1;
        $out['want'] = $SLPDEF_OUT;
        $out['again'] = $o->avalon_bubble_layout($r1);
        $out['in'] = $SLPDEF;
        $crlfIn = str_replace("\n", "\r\n", $SLPDEF);
        $out['crlf'] = $o->avalon_bubble_layout($crlfIn) === str_replace("\n", "\r\n", $SLPDEF_OUT);
        break;

    case 'edges':
        $L = function ($x) use ($o) { return $o->avalon_bubble_layout($x); };
        $out['nophone_in'] = wrapb(adr() . ' ' . eml());
        $out['nophone'] = $L($out['nophone_in']);
        $out['noemail_in'] = wrapb(adr() . ' ' . phn());
        $out['noemail'] = $L($out['noemail_in']);
        $out['after_in'] = wrapb(adr() . ' ' . phn() . ' <span id="slp_bubble_fax"></span> ' . eml());
        $out['after'] = $L($out['after_in']);
        $out['nested_in'] = wrapb(adr() . ' ' . sp('slp_bubble_email', '<span class="x">[slp_location email wrap mailto ]</span>') . phn());
        $out['nested'] = $L($out['nested_in']);
        $out['noaddr_in'] = wrapb(eml() . phn());
        $out['noaddr'] = $L($out['noaddr_in']);
        $out['hasdist_in'] = wrapb('<span>[slp_location distance_1] miles</span>' . adr() . eml() . phn());
        $out['hasdist'] = $L($out['hasdist_in']);
        $out['decoy_in'] = wrapb('<span data-id="slp_bubble_address">decoy</span>' . adr() . eml() . phn());
        $out['decoy'] = $L($out['decoy_in']);
        /* A phone span with no number of its own, and a later [slp_location
           phone] elsewhere: the step must not reach past its own span. */
        $out['runaway_in'] = '<div class="slp_info_bubble"><span id="slp_bubble_phone"><span class="location_detail_label">[slp_option label_phone ifset phone]</span></span></div>'
                           . '<div class="buttons"><span class="call">[slp_location phone]</span></div>';
        $out['runaway'] = $L($out['runaway_in']);
        $out['unknown_in'] = '<div class="x">[slp_location name] [slp_location phone] [slp_location email]</div>';
        $out['unknown'] = $L($out['unknown_in']);
        $out['dollar_in'] = wrapb(sp('slp_bubble_address', '[slp_location address suffix] $1 \\1 ${0}') . eml() . phn()
                                  . '<span class="z">$2 \\\\ ${1}</span>');
        $out['dollar'] = $L($out['dollar_in']);
        $out['attrs_in'] = wrapb(sp('slp_bubble_address', '[slp_location address suffix]', 'class="a" data-x="1"') . ' '
                         . sp('slp_bubble_email', '[slp_location   email  wrap mailto ][slp_option label_email ifset email]', 'class="e"') . ' '
                         . sp('slp_bubble_phone', '[slp_location phone suffix br]', 'class="p"'));
        $out['attrs'] = $L($out['attrs_in']);
        $out['done'] = $L($out['attrs']);
        $out['empty'] = $L('');
        $out['notstring'] = array($L(null), $L(42));
        break;

    case 'email':
        $E = function ($m) { return SLP_Avalon::avalon_email_fields($m); };
        $mk = function ($shown, $raw = null) {
            $m = array('id' => '7', 'name' => 'Test Dealer', 'email' => $shown, 'phone' => '212-555-0101');
            if ($raw !== false) { $m['data'] = array('sl_id' => '7', 'sl_email' => ($raw === null ? $shown : $raw)); }
            return $m;
        };
        $cases = array(
            'plain'      => $mk('sales@dealer.example'),
            'plus'       => $mk('first.last+boats@dealer.example'),
            'apostrophe' => $mk("o'brien@dealer.example"),
            'upper'      => $mk('SALES@DEALER.EXAMPLE'),
            'na'         => $mk('N/A'),
            'query'      => $mk('sales?bcc=other@else.example'),
            'brace'      => $mk('a{b}@dealer.example'),
            'percent'    => $mk('a%20b@dealer.example'),
            'html'       => $mk('<b>x</b>@dealer.example'),
            'two'        => $mk('a@dealer.example, b@dealer.example'),
            'nodata'     => $mk('sales@dealer.example', false),
            'rawempty'   => $mk('sales@dealer.example', ''),
            'rawwins'    => $mk('shown@dealer.example', 'raw@dealer.example'),
            'nbsp'       => $mk("\xC2\xA0sales@dealer.example\xC2\xA0"),
            'nbspshown'  => $mk('&nbsp;sales@dealer.example&nbsp;', false),
            'badutf8'    => $mk("sales\xFF@dealer.example", "sales\xFF@dealer.example"),
            'entity'     => $mk('a&amp;b@dealer.example'),
            'empty'      => $mk(''),
            'spaces'     => $mk('   '),
            'noemailkey' => array('id' => '8', 'phone' => '1', 'data' => array('sl_email' => 'x@dealer.example')),
        );
        foreach ($cases as $k => $m) { $out['r'][$k] = $E($m); }
        $out['in'] = $cases;
        $out['esc_url'] = $GLOBALS['ESC_URL'] ?? array();
        $out['marker_notarray'] = array($o->avalon_marker_email('x'), $o->avalon_marker_email(null));
        $out['marker_array'] = $o->avalon_marker_email($cases['plain']);
        break;

    case 'jsopts':
        $o->t_wire();
        $stored_b = '<div id="slp_info_bubble_[slp_location id]" class="slp_info_bubble">'
                  . '<span id="slp_bubble_address">[slp_location address]</span>'
                  . '<span id="slp_bubble_email">[slp_location email wrap mailto ]</span>'
                  . '<span id="slp_bubble_phone">[slp_location phone]</span></div>';
        $stored_r = '<span class="slp_result_address slp_result_street">[slp_location address]</span> '
                  . '<span class="slp_result_address slp_result_phone">[slp_location phone]</span>';
        $chain = array(
            array(10, 0, function ($opt) use ($o, $stored_r) { $opt['resultslayout'] = $o->avalon_results_layout($stored_r); $opt['bubblelayout'] = 'whatever SLP had'; return $opt; }),
            array(90, 0, function ($opt) use ($stored_b, $stored_r) { $opt['bubblelayout'] = $stored_b; $opt['resultslayout'] = $stored_r; return $opt; }),
        );
        $seq = 1;
        $names = array();
        foreach ($GLOBALS['REC']['filters'] as $f) {
            if ($f[0] !== 'slp_js_options') { continue; }
            $names[] = array($f[1], $f[2]);
            if ($f[1] === 'SLP_Avalon->avalon_js_options_layout') {
                $chain[] = array($f[2], $seq++, function ($opt) use ($o) { return $o->avalon_js_options_layout($opt); });
            } elseif ($f[1] === 'SLP_Avalon->avalon_js_options_bubble') {
                $chain[] = array($f[2], $seq++, function ($opt) use ($o) { return $o->avalon_js_options_bubble($opt); });
            }
        }
        usort($chain, function ($a, $b) { return $a[0] === $b[0] ? $a[1] - $b[1] : $a[0] - $b[0]; });
        $v = array('map_region' => 'us', 'bubblelayout' => 'x', 'resultslayout' => 'x');
        foreach ($chain as $c) { $v = $c[2]($v); }
        $out['chain'] = $v;
        $out['chain_n'] = count($chain);
        $out['names'] = $names;
        $out['noop'] = array($o->avalon_js_options_bubble(array('map_region' => 'us')),
                             $o->avalon_js_options_bubble(array('bubblelayout' => array('x'))),
                             $o->avalon_js_options_bubble(array('bubblelayout' => null)),
                             $o->avalon_js_options_bubble('x'));
        $done = $o->avalon_bubble_layout($stored_b);
        $out['again'] = array($done, $o->avalon_js_options_bubble(array('bubblelayout' => $done)));
        break;

    case 'markers':
        $o->t_wire();
        $row = array('sl_id' => '1', 'sl_address' => '100 Test Street', 'sl_city' => 'Testville', 'sl_state' => 'NY',
                     'sl_zip' => '10001', 'sl_country' => 'US', 'sl_phone' => '212-555-0101', 'sl_email' => 'sales@dealer.example');
        $m = array('id' => '1', 'name' => 'Test Dealer', 'address' => '100 Test Street', 'phone' => '212-555-0101',
                   'email' => 'sales@dealer.example', 'data' => $row);
        $m2 = $m; $m2['email'] = ''; $m2['data']['sl_email'] = '';
        $chain = array();
        $seq = 0;
        foreach ($GLOBALS['REC']['filters'] as $f) {
            if ($f[0] !== 'slp_results_marker_data') { continue; }
            $meth = substr($f[1], strlen('SLP_Avalon->'));
            $chain[] = array($f[2], $seq++, $meth);
        }
        usort($chain, function ($a, $b) { return $a[0] === $b[0] ? $a[1] - $b[1] : $a[0] - $b[0]; });
        $out['chain'] = array_map(function ($c) { return array($c[0], $c[2]); }, $chain);
        foreach (array('with' => $m, 'without' => $m2) as $k => $x) {
            foreach ($chain as $c) { $x = $o->{$c[2]}($x); }
            $out[$k] = $x;
        }
        $out['in'] = array('with' => $m, 'without' => $m2);
        break;

    case 'rocket':
        $out['b'] = array(SLP_Avalon::avalon_rocket_rucss_safelist_bubble(array('.keep')),
                          SLP_Avalon::avalon_rocket_rucss_safelist_bubble(null),
                          SLP_Avalon::avalon_rocket_rucss_safelist_bubble('x'));
        $out['both'] = SLP_Avalon::avalon_rocket_rucss_safelist_bubble(SLP_Avalon::avalon_rocket_rucss_safelist(array()));
        break;

    case 'wiring':
        $o->t_wire();
        $out = $GLOBALS['REC'];
        break;

    default:
        $out['CRASH'] = 'unknown scenario ' . $s;
    }
} catch (Throwable $e) {
    $out['CRASH'] = get_class($e) . ': ' . $e->getMessage() . ' at line ' . $e->getLine();
}
echo json_encode($out, JSON_INVALID_UTF8_SUBSTITUTE);
PHPRUN;

$harness = $head . "\nrequire " . var_export($akCopy, true) . ";\n\nclass SLP_Avalon {\n"
         . "    private static \$instance;\n\n"
         . $LIFTS['consts'] . $LIFTS['wiring'] . $LIFTS['display'] . $LIFTS['bubble']
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
function cnt($hay, $needle) { return is_string($hay) ? substr_count($hay, $needle) : -1; }
/* Positions, in order, of each needle - false when any is missing. */
function inorder($hay, $needles)
{
    $last = -1;
    foreach ($needles as $n) {
        $p = is_string($hay) ? strpos($hay, $n) : false;
        if ($p === false || $p <= $last) { return false; }
        $last = $p;
    }
    return true;
}
$F = array('dist' => '<span class="avalon-bubble-distance">Distance: [slp_location distance format="decimal1"] [slp_option distance_unit]</span>',
           'addr' => '[slp_location avalon_address_label]', 'phone' => '[slp_location avalon_phone_html]',
           'email' => '[slp_location avalon_email_html]', 'hours' => '[slp_location avalon_hours_html]');
$once = function ($h) use ($F) {
    foreach ($F as $k => $v) { if (cnt($h, $v) !== 1) { return false; } }
    return true;
};

$lint = shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($hfile) . ' 2>&1');
if (strpos((string) $lint, 'No syntax errors') === false) {
    fwrite(STDERR, "the harness does not compile:\n{$lint}\n");
    exit(2);
}

/* ------------------------------------------------------- Aura's layout */

echo "\n  THE BUBBLE LAYOUT - Aura's, as DEV serves it\n";
$r = run($hfile, 'aura');
$ao = $r['out'] ?? '';
check($ao !== '' && $ao === ($r['want'] ?? null), "Aura's layout becomes exactly the reviewed output");
check($once($ao), 'Distance, Address:, Phone:, Email:, Hours: - each exactly once');
check(inorder($ao, array($F['dist'], '<span id="slp_bubble_address">' . $F['addr'], '<span id="slp_bubble_phone">' . $F['phone'] . '</span>',
                         '<span id="slp_bubble_email">' . $F['email'] . '</span>', $F['hours'])),
      'in that order: Distance, Address, Phone, Email, Hours');
check(cnt($ao, '[slp_location phone') === 0 && cnt($ao, 'wrap mailto') === 0 && cnt($ao, 'label_phone') === 0 && cnt($ao, 'label_email') === 0,
      "SLP's own phone, label and mailto wrap are gone - nothing prints twice");
check(cnt($ao, '<span id="slp_bubble_email">') === 1 && cnt($ao, '<span id="slp_bubble_phone">') === 1,
      'the email span was moved, not copied');
check(has($ao, '<div id="slp_info_bubble_[slp_location id]" class="slp_info_bubble [slp_location featured]"><span id="slp_bubble_name">[slp_location name suffix] </span>')
      && has($ao, '<span id="slp_bubble_directions">[html ifset directions] [slp_option label_directions wrap directions]</span> <span id="slp_bubble_website">[html ifset url][slp_location web_link][html ifset url]</span> </div>'),
      "the outer div (main.js reads its id), the name, Directions and Contact Dealer are untouched");
check(($r['again'] ?? '') === $ao && ($r['third'] ?? '') === $ao, 'idempotent - a second and a third pass change nothing');
check(cnt($ao, '<span') === cnt($r['in'] ?? '', '<span') && cnt($ao, '</span>') === cnt($r['in'] ?? '', '</span>')
      && cnt($ao, '<div') === cnt($r['in'] ?? '', '<div') && cnt($ao, '</div>') === cnt($r['in'] ?? '', '</div>'),
      'the spans and divs balance as before: the distance span added, the old label span gone - no tag lost');

/* --------------------------------------------------- SLP's own default */

echo "\n  THE BUBBLE LAYOUT - SLP's default (4.2.67)\n";
$r = run($hfile, 'slpdefault');
$so = $r['out'] ?? '';
check($so !== '' && $so === ($r['want'] ?? null), "SLP's default becomes exactly the reviewed output");
check($once($so) && inorder($so, array($F['dist'], $F['addr'], $F['phone'], $F['email'], $F['hours'])),
      'all five fields, once each, Distance before Address before Phone before Email before Hours');
check(inorder($so, array('slp_bubble_address', 'slp_bubble_directions', 'slp_bubble_website', 'slp_bubble_phone')),
      'Directions and Website stay where SLP put them - between the address and the phone');
check(($r['again'] ?? '') === $so, 'idempotent');
check(($r['crlf'] ?? false) === true, 'the same with CRLF line endings');

/* -------------------------------------------- layouts it must respect */

echo "\n  THE BUBBLE LAYOUT - layouts it must leave alone, or change in part\n";
$r = run($hfile, 'edges');
$np = $r['nophone'] ?? '';
check(cnt($np, $F['email']) === 1 && cnt($np, $F['phone']) === 0 && has($np, $F['email'] . '</span>' . $F['hours']),
      'no phone span: Email: in place, Hours: after it');
$ne = $r['noemail'] ?? '';
check(cnt($ne, $F['phone']) === 1 && cnt($ne, $F['email']) === 0 && has($ne, $F['phone'] . '</span>' . $F['hours']),
      'no email span: Hours: after the phone line');
$af = $r['after'] ?? '';
check(cnt($af, '<span id="slp_bubble_email">') === 1 && has($af, $F['phone'] . '</span><span id="slp_bubble_email">' . $F['email'] . '</span>' . $F['hours'])
      && has($af, '<span id="slp_bubble_fax"></span>'),
      'the email already after the phone: moved up to it, once, the span between kept');
$ns = $r['nested'] ?? '';
check(cnt($ns, $F['email']) === 0 && has($ns, '<span class="x">[slp_location email wrap mailto ]</span>') && has($ns, $F['phone'] . '</span>' . $F['hours']),
      'an email span with a span inside: not recognised, left as it was; Hours: after the phone');
$na = $r['noaddr'] ?? '';
check(cnt($na, 'avalon-bubble-distance') === 0 && cnt($na, $F['addr']) === 0 && cnt($na, $F['phone']) === 1 && cnt($na, $F['email']) === 1,
      'no address span: no Distance, no Address: - the other steps still apply');
$hd = $r['hasdist'] ?? '';
check(cnt($hd, 'avalon-bubble-distance') === 0 && cnt($hd, '[slp_location distance_1]') === 1, 'a layout that already shows a distance: no second one');
$dc = $r['decoy'] ?? '';
check(has($dc, '<span data-id="slp_bubble_address">decoy</span><span class="avalon-bubble-distance">') && cnt($dc, $F['addr']) === 1
      && has($dc, '<span id="slp_bubble_address">' . $F['addr']),
      'data-id="slp_bubble_address" is not the address span');
check(($r['runaway'] ?? '') === ($r['runaway_in'] ?? 'x'),
      'a phone span with no number of its own is left alone - the step never reads past its own span into what follows');
check(($r['unknown'] ?? '') === ($r['unknown_in'] ?? 'x'), 'a layout with none of the anchors: unchanged');
$dl = $r['dollar'] ?? '';
check(has($dl, '<span id="slp_bubble_address">[slp_location avalon_address_label][slp_location address suffix] $1 \\1 ${0}</span>')
      && has($dl, '<span class="z">$2 \\\\ ${1}</span>') && $once($dl),
      '$1, \\1, ${0} and \\\\ in the layout stay literal text through every insertion and the move');
$at = $r['attrs'] ?? '';
check(has($at, '<span class="a" data-x="1" id="slp_bubble_address">' . $F['addr']) && has($at, '<span class="p" id="slp_bubble_phone">' . $F['phone'] . '</span><span class="e" id="slp_bubble_email">' . $F['email'] . '</span>' . $F['hours'])
      && $once($at),
      'attributes on the spans are kept; [slp_location   email] with extra spaces is recognised');
check(($r['done'] ?? '') === $at, '  ... and a second pass changes nothing');
check(($r['empty'] ?? 'x') === '' && ($r['notstring'] ?? null) === array('', '42'), 'empty or non-string input: no crash');

/* ---------------------------------------------------------- the email */

echo "\n  THE EMAIL FIELD\n";
$r = run($hfile, 'email');
$e = $r['r'] ?? array();
$EM = function ($k) use ($e) { return $e[$k]['avalon_email_html'] ?? null; };
check($EM('plain') === '<b class="avalon-label">Email:</b> <a class="avalon-email" href="mailto:sales@dealer.example" target="_blank" rel="noopener">sales@dealer.example</a>',
      'Email: bold, then the address as a mailto: link opening beside the map');
check($EM('plus') === '<b class="avalon-label">Email:</b> <a class="avalon-email" href="mailto:first.last+boats@dealer.example" target="_blank" rel="noopener">first.last+boats@dealer.example</a>',
      'a + and a dot in the address: linked');
check($EM('apostrophe') === '<b class="avalon-label">Email:</b> <a class="avalon-email" href="mailto:o&#039;brien@dealer.example" target="_blank" rel="noopener">o&#039;brien@dealer.example</a>',
      'an apostrophe: linked, escaped in the href and the text');
check(has($EM('upper'), 'href="mailto:SALES@DEALER.EXAMPLE"'), 'capitals: linked as written');
check($EM('na') === '<b class="avalon-label">Email:</b> N/A', 'N/A: the text, no link');
foreach (array('query' => 'sales?bcc=other@else.example', 'brace' => 'a{b}@dealer.example', 'percent' => 'a%20b@dealer.example') as $k => $txt) {
    check($EM($k) === '<b class="avalon-label">Email:</b> ' . htmlspecialchars($txt, ENT_QUOTES) && ! has($EM($k), 'href'),
          "{$k}: is_email() would pass it, but a mailto: URL would read it differently - the text, no link");
}
check($EM('html') === '<b class="avalon-label">Email:</b> &lt;b&gt;x&lt;/b&gt;@dealer.example', 'markup in the value: escaped, no link');
check($EM('two') === '<b class="avalon-label">Email:</b> a@dealer.example, b@dealer.example', 'two addresses: the text, no link');
check(has($EM('nodata'), 'href="mailto:sales@dealer.example"'), "no raw row: the marker's own value, linked");
check(has($EM('rawempty'), 'href="mailto:sales@dealer.example"'), "the raw row's email empty: the marker's own value, linked");
check(has($EM('rawwins'), 'href="mailto:raw@dealer.example"') && has($EM('rawwins'), '>raw@dealer.example</a>') && ! has($EM('rawwins'), 'shown@'),
      'the raw row wins over the displayed value, for the link and its text');
check(has($EM('nbsp'), 'href="mailto:sales@dealer.example"') && has($EM('nbspshown'), 'href="mailto:sales@dealer.example"'),
      'no-break spaces round the address, in the raw row or as SLP shows it (&nbsp;): trimmed, linked');
check(! isset($e['badutf8']['avalon_email_html']), 'invalid UTF-8: no field at all - never "Email:" over nothing');
check($EM('entity') === '<b class="avalon-label">Email:</b> a&amp;b@dealer.example', "SLP's &amp;: decoded, then escaped once; & is never linked");
check(! isset($e['empty']['avalon_email_html']) && ! isset($e['spaces']['avalon_email_html']) && ! isset($e['noemailkey']['avalon_email_html']),
      'no email - empty, spaces, or no email key: no Email: label');
$kept = true;
foreach ($e as $k => $m) { unset($m['avalon_email_html']); if ($m !== ($r['in'][$k] ?? null)) { $kept = false; } }
check($kept && count($e) === 20, 'nothing else on the marker changes');
$eu = $r['esc_url'] ?? array();
check(count($eu) > 0 && count(array_filter($eu, function ($c) { return $c[1] !== array('mailto'); })) === 0,
      "every link goes through esc_url() with the mailto protocol only");
check(($r['marker_notarray'] ?? null) === array('x', null) && isset($r['marker_array']['avalon_email_html']),
      'the filter callback: a marker that is not an array passes through; an array gets the field');

/* ---------------------------------------------------- script options */

echo "\n  SCRIPT OPTIONS (slp_js_options)\n";
$r = run($hfile, 'jsopts');
$cb = $r['chain']['bubblelayout'] ?? '';
check(($r['chain_n'] ?? 0) === 4 && $once($cb),
      'a bubble layout SLP Experience merges in at 90 gets every field at 100');
check(has($r['chain']['resultslayout'] ?? '', '[slp_location avalon_phone_html]</span>[slp_location avalon_hours_html]'),
      "  ... and Part 4's results layout still gets its fields at 100");
check(($r['chain']['map_region'] ?? null) === 'us', '  ... and no other option is touched');
check(($r['names'] ?? null) === array(array('SLP_Avalon->avalon_js_options_layout', 100), array('SLP_Avalon->avalon_js_options_bubble', 100)),
      "both on slp_js_options at 100, Part 4's registered first");
check(($r['noop'] ?? null) === array(array('map_region' => 'us'), array('bubblelayout' => array('x')), array('bubblelayout' => null), 'x'),
      'no layout, a layout that is not a string, options that are not an array: passed through');
check(isset($r['again'][1]['bubblelayout']) && $r['again'][1]['bubblelayout'] === $r['again'][0], 'a bubble layout that already has the fields: unchanged');

/* ------------------------------------------------------ marker chain */

echo "\n  THE MARKER CHAIN (slp_results_marker_data)\n";
$r = run($hfile, 'markers');
check(($r['chain'] ?? null) === array(array(20, 'avalon_marker_labels'), array(25, 'avalon_marker_email')),
      "Part 4's labels at 20, then the email at 25");
$w = $r['with'] ?? array();
check(isset($w['avalon_phone_html'], $w['avalon_address_label'], $w['avalon_email_html'])
      && has($w['avalon_email_html'], 'href="mailto:sales@dealer.example"') && has($w['avalon_phone_html'], 'href="tel:+12125550101"'),
      'a marker with an email leaves with Address:, Phone: and Email:');
$wo = $r['without'] ?? array();
check(isset($wo['avalon_phone_html']) && ! isset($wo['avalon_email_html']), 'a marker with no email: Phone:, and no Email:');
$k1 = $w; unset($k1['avalon_phone_html'], $k1['avalon_address_label'], $k1['avalon_email_html']);
check($k1 === ($r['in']['with'] ?? null), 'nothing else on the marker changes');

/* ---------------------------------------------------------- WP Rocket */

echo "\n  WP ROCKET\n";
$r = run($hfile, 'rocket');
check(($r['b'][0] ?? null) === array('.keep', '(.*).avalon-email(.*)', '(.*).avalon-bubble-distance(.*)', '(.*).slp_info_bubble(.*)')
      && count($r['b'][1] ?? array()) === 3 && count($r['b'][2] ?? array()) === 3,
      "the bubble's three selectors appended; existing entries kept; a list that is not an array starts fresh");
$both = $r['both'] ?? array();
check(count($both) === 7 && ($both[0] ?? '') === '/wp-content/plugins/slp_avalon/assets/css/avalon-hours.css',
      "after Part 4's filter: the file, Part 4's three selectors, then these three");
$sel = array('.slp_info_bubble a.avalon-email', 'a.avalon-email:hover', '.slp_info_bubble a.avalon-email:focus-visible',
             '.slp_info_bubble .avalon-bubble-distance', '.slp_info_bubble #slp_bubble_phone', '.slp_info_bubble #slp_bubble_email',
             '.slp_info_bubble', '.slp_info_bubble a.avalon-tel:hover');
$hit = 0;
foreach ($sel as $one) {
    foreach (array_slice($both, 1) as $pat) { if (preg_match('#^' . $pat . '#', $one)) { $hit++; break; } }
}
check($hit === count($sel), "every bubble selector in avalon-hours.css matches a pattern read from the selector's start");

/* ------------------------------------------------------ registrations */

echo "\n  REGISTRATIONS\n";
$r = run($hfile, 'wiring');
$acts = $r['actions'] ?? array();
$fils = $r['filters'] ?? array();
$find = function ($list, $row) { return count(array_filter($list, function ($e) use ($row) { return $e === $row; })); };
check($find($fils, array('slp_results_marker_data', 'SLP_Avalon->avalon_marker_email', 25, 1)) === 1,
      'slp_results_marker_data -> avalon_marker_email, priority 25');
check($find($fils, array('slp_js_options', 'SLP_Avalon->avalon_js_options_bubble', 100, 1)) === 1,
      'slp_js_options -> avalon_js_options_bubble, priority 100');
check($find($fils, array('rocket_rucss_safelist', 'SLP_Avalon::avalon_rocket_rucss_safelist_bubble', 10, 1)) === 1,
      'rocket_rucss_safelist -> avalon_rocket_rucss_safelist_bubble');
check($find($fils, array('slp_results_marker_data', 'SLP_Avalon->avalon_marker_labels', 20, 1)) === 1
      && $find($fils, array('slp_js_options', 'SLP_Avalon->avalon_js_options_layout', 100, 1)) === 1
      && $find($fils, array('rocket_rucss_safelist', 'SLP_Avalon::avalon_rocket_rucss_safelist', 10, 1)) === 1
      && $find($fils, array('slp_javascript_results_string', 'SLP_Avalon->avalon_results_layout', 100, 1)) === 1,
      "Part 4's four on the same hooks are still there");
check(count($acts) + count($fils) === 36, sprintf("%d registrations in all - Part 4's %d plus these three", 36, 33));
check(count($r['shortcodes'] ?? array()) === 5, 'five shortcodes, as before');

/* ---------------------------------------------------------- crashes */

echo "\n  HARNESS\n";
check($CRASHES === array(), 'no scenario crashed' . ($CRASHES ? ': ' . json_encode($CRASHES) : ''));

@unlink($hfile);
@unlink($akCopy);
@rmdir($tmp);

printf("\n  %d passed, %d failed, %d total\n\n", $pass, $fail, $pass + $fail);
exit($fail ? 1 : 0);
