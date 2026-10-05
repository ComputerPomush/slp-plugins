<?php
/**
 * suite-v034.php - validates slp_avalon v0.0.27 Part 4c: the class and the
 * stylesheet.
 *
 *   the address field   avalon_address_fields(): two lines, "City, ST ZIP"
 *                       in SLP's own punctuation, the state or province as
 *                       its two-letter code, no country for the US or
 *                       Canada, any other country after the postal code,
 *                       escaping, no-break spaces, invalid UTF-8, nothing
 *                       else on the marker touched
 *   the layouts         avalon_results_layout_address() and
 *                       avalon_bubble_layout_address() on Aura's layouts as
 *                       DEV served them (2026-10-04) and on SLP's own
 *                       defaults: exact output, idempotent, the full chain
 *                       from the stored layouts through Part 4 and 4b; and
 *                       on layouts they must leave alone
 *   the labels          Phone:, Address:, Email: and Hours: each name their
 *                       kind; the store page's hours have no label
 *   the script options  avalon_js_options_map() through a real filter chain
 *                       - SLP at 10, which builds the results layout through
 *                       slp_javascript_results_string (Part 4 at 100, this
 *                       at 110), SLP Experience at 90 leaving or replacing
 *                       the layouts, Part 4 and 4b at 100, this at 110 -
 *                       and through it twice; Address: once every way; the
 *                       hover pin's URL
 *   WP Rocket           every selector of the Part 4c stylesheet block on
 *                       the safelist, matched from the selector's start
 *   the registrations   40 in all: Part 4b's 36 plus these four
 *   the stylesheet      by identity Part 4b's plus Part 4c's edits; the
 *                       block it appends, pinned; the specificity each
 *                       override relies on
 *
 * WHAT CARRIES FORWARD BY IDENTITY
 *
 * Part 4c is Part 4b plus one registration edit, four label edits and one
 * inserted block. The first assertions take the block out, reverse the
 * edits, and require the result to be v0.0.27-part4b byte for byte -
 * da3bd46a, 312,186 bytes - so suite-v033's 72/72, suite-v032's 208/208
 * and their controls carry forward for every region Part 4c did not touch.
 * The stylesheet the same way: Part 4c minus its three edits is Part 4b's,
 * 1fd40832, 8,374 bytes.
 *
 * WHAT IS EXECUTED
 *
 * The constants, add_actions() with register_shortcodes(), Part 4's block,
 * Part 4b's block and Part 4c's are lifted verbatim into a harness class
 * with the real address-key class beside it, and run against recording
 * doubles. ONE PROCESS PER SCENARIO; every warning and notice is an
 * exception, so a crash fails its scenario by name.
 *
 * The test data is synthetic: no dealer name, address, phone or email.
 * Aura's layouts are templates, the site's own settings.
 *
 * Usage:
 *   php suite-v034.php <class.slp_avalon.php> [<avalon-hours.css>] [<class.slp_avalon_addresskey.php>]
 *
 * The stylesheet defaults to the repo's, beside the class's folder; the
 * address-key class to the repo's, which must be the pinned one.
 *
 * Exit 0 all passed, 1 any failed, 2 the suite could not run.
 */

$clsPath = $argv[1] ?? 'build/out-v027p4c/class.slp_avalon.php';
$cssPath = $argv[2] ?? (__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'slp_avalon'
                       . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'avalon-hours.css');
$akPath  = $argv[3] ?? (__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'slp_avalon'
                       . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'class.slp_avalon_addresskey.php');

$code = @file_get_contents($clsPath);
if ($code === false) {
    fwrite(STDERR, "cannot read {$clsPath}\n");
    exit(2);
}
$css = @file_get_contents($cssPath);
if ($css === false) {
    fwrite(STDERR, "cannot read {$cssPath}\n");
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

echo "suite-v034  slp_avalon 0.0.27 Part 4c\n";
printf("  class   %s\n          %s  %d bytes\n", $clsPath, md5($code), strlen($code));
printf("  css     %s\n          %s  %d bytes\n", $cssPath, md5($css), strlen($css));
printf("  addrkey %s\n          %s  %d bytes  (pinned)\n\n", $akPath, md5($ak), strlen($ak));

/* ------------------------------------------------------------------ */
/* IDENTITY: Part 4c minus its edits is Part 4b, byte for byte.         */
/* ------------------------------------------------------------------ */

$P4B = array('da3bd46a6af3b34a9552f6b1119dc13f', 312186);

$BLOCK_START = "        /**\r\n         * v0.0.27 Part 4c. Cards and bubbles laid out for phones.";
$BLOCK_END   = "        public function avalon_rest_protected_slugs(){";
$BLOCK_PIN   = array('90389f945be46c281a4c8c873be355ac', 17145);
$P4B_START   = "        /**\r\n         * v0.0.27 Part 4b. The info bubble shows what the card shows.";
$P4_START    = "        /**\r\n         * v0.0.27 Part 4. Showing the hours.";

$crlf = function ($s) { return str_replace("\n", "\r\n", $s); };
$WIRE_OLD = $crlf(<<<'EOT'
            add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist_bubble'));

EOT);
$WIRE_NEW = $crlf(<<<'EOT'
            add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist_bubble'));
            //
            // v0.0.27 Part 4c. The address on two lines; labels that can be
            // icons; the hover pin's URL for slp_avalon.js.
            //
            // The address field onto every marker at 30, after Part 4's
            // labels at 20 and Part 4b's email at 25: it reads the marker's
            // own address values, as SLP Experience left them at 15. The
            // layouts at 110, after Part 4's and 4b's callbacks at 100 have
            // put their fields in: the address run becomes the one field,
            // and "Distance:" a label. The new selectors onto WP Rocket's
            // safelist; a no-op where WP Rocket is not installed.
            add_filter('slp_results_marker_data', array(self::$instance,'avalon_marker_address'), 30, 1);
            add_filter('slp_javascript_results_string', array(self::$instance,'avalon_results_layout_address'), 110, 1);
            add_filter('slp_js_options', array(self::$instance,'avalon_js_options_map'), 110, 1);
            add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist_map'));

EOT);
/* [what Part 4c wrote, what Part 4b had] */
$LABELS = array(
    array('<b class="avalon-label avalon-label--phone">Phone:</b>', '<b class="avalon-label">Phone:</b>'),
    array('<b class="avalon-label avalon-label--address">Address:</b> \';', '<b class="avalon-label">Address:</b> \';'),
    array('( $card ? \'<b class="avalon-label avalon-label--hours">Hours:</b> \' : \'\' )', '( $card ? \'<b class="avalon-label">Hours:</b> \' : \'\' )'),
    array('$marker[\'avalon_email_html\'] = \'<b class="avalon-label avalon-label--email">Email:</b> \'',
          '$marker[\'avalon_email_html\'] = \'<b class="avalon-label">Email:</b> \''),
);

echo "  IDENTITY - the class\n";
$anchorsOk = substr_count($code, $BLOCK_START) === 1 && substr_count($code, $BLOCK_END) === 1
          && substr_count($code, $P4B_START) === 1 && substr_count($code, $P4_START) === 1
          && strpos($code, $P4_START) < strpos($code, $P4B_START)
          && strpos($code, $P4B_START) < strpos($code, $BLOCK_START)
          && strpos($code, $BLOCK_START) < strpos($code, $BLOCK_END);
check($anchorsOk, "the Part 4c block is present once, after Part 4b's, directly before avalon_rest_protected_slugs()");
if (! $anchorsOk) {
    fwrite(STDERR, "cannot find the Part 4c block; nothing else can be lifted\n");
    exit(2);
}
$a = strpos($code, $BLOCK_START);
$b = strpos($code, $BLOCK_END);
$block = substr($code, $a, $b - $a);
check(md5($block) === $BLOCK_PIN[0] && strlen($block) === $BLOCK_PIN[1],
      sprintf('the block is the one this suite was written against (%s, %d bytes)', $BLOCK_PIN[0], $BLOCK_PIN[1]));
$rev = substr($code, 0, $a) . substr($code, $b);
$editsOk = substr_count($rev, $WIRE_NEW) === 1;
if ($editsOk) {
    $rev = implode($WIRE_OLD, explode($WIRE_NEW, $rev, 2));
}
foreach ($LABELS as $e) {
    if (substr_count($rev, $e[0]) !== 1) { $editsOk = false; continue; }
    $rev = implode($e[1], explode($e[0], $rev, 2));
}
check($editsOk, 'the registration edit and the four label edits are each present exactly once');
check(md5($rev) === $P4B[0] && strlen($rev) === $P4B[1],
      'block out and the five edits reversed, the file IS v0.0.27-part4b (da3bd46a, 312,186 bytes)');
check(substr_count($code, "\r\n") === substr_count($code, "\n") && substr_count($code, "\r") === substr_count($code, "\r\n"),
      'pure CRLF - no bare LF, no bare CR');
check(preg_match('/[^\x00-\x7f]/', $block . $WIRE_NEW) === 0 && strpos($block, "\t") === false,
      'the new block and registrations are pure ASCII, no tabs');
check(substr_count($code, '<b class="avalon-label">') === 0, 'no label is left without its kind');

/* ------------------------------------------------------------------ */
/* IDENTITY: the stylesheet.                                           */
/* ------------------------------------------------------------------ */

$CSS_P4B = array('1fd4083289b335140a0196bf62e54605', 8374);
$CSS_BLOCK_START = "\n/* --------------------------------------------------- Part 4c: the address */";
$CSS_BLOCK_PIN   = array('9039904c399cc7317fb515caf9789e9e', 6598);
$CSS_EDITS = array(
    array(" * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4c.\n", " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4b.\n"),
    array(" * up, so they win without !important.\n *\n * PART 4c, at the end of the file. The address on two lines on the cards\n"
          . " * and in the bubble; on a phone, 15 px text, 20 px names and the labels as\n"
          . " * Font Awesome icons; the map's own images - the Street View Pegman - at\n"
          . " * their own sizes; a keyboard focus ring on the bubble's buttons; the\n"
          . " * Hours: line balanced where it has to wrap.\n */\n",
          " * up, so they win without !important.\n */\n"),
);

echo "\n  IDENTITY - the stylesheet\n";
$cb = strpos($css, $CSS_BLOCK_START);
$cssBlock = ($cb !== false && substr_count($css, $CSS_BLOCK_START) === 1) ? substr($css, $cb) : '';
check($cssBlock !== '' && md5($cssBlock) === $CSS_BLOCK_PIN[0] && strlen($cssBlock) === $CSS_BLOCK_PIN[1],
      sprintf('the Part 4c block ends the file, once, and is the one this suite was written against (%s, %d bytes)',
              $CSS_BLOCK_PIN[0], $CSS_BLOCK_PIN[1]));
$crev = $cssBlock !== '' ? substr($css, 0, $cb) : $css;
$cssOk = true;
foreach ($CSS_EDITS as $e) {
    if (substr_count($crev, $e[0]) !== 1) { $cssOk = false; continue; }
    $crev = implode($e[1], explode($e[0], $crev, 2));
}
check($cssOk && md5($crev) === $CSS_P4B[0] && strlen($crev) === $CSS_P4B[1],
      "block out and the two header edits reversed, the stylesheet IS Part 4b's (1fd40832, 8,374 bytes)");
check(strpos($css, "\r") === false && preg_match('/[^\x00-\x7f]/', $css) === 0, 'LF and ASCII');

/* Specificity, as the cascade counts it: ids, then classes, attributes and
   pseudo-classes, then elements and pseudo-elements. For the plain
   selectors this stylesheet and the theme write. */
function spec($sel)
{
    $s = preg_replace('/::?(before|after)\b/', ' x', $sel);          /* pseudo-elements count as elements */
    $ids = preg_match_all('/#[\w-]+/', $s);
    $cls = preg_match_all('/\.[\w-]+|\[[^\]]*\]|:(?!:)[\w-]+(?:\([^)]*\))?/', $s);
    $s2  = preg_replace('/#[\w-]+|\.[\w-]+|\[[^\]]*\]|:[\w-]+(?:\([^)]*\))?/', ' ', $s);
    $els = preg_match_all('/(?:^|[\s>+~])([a-z][\w-]*)/i', $s2);
    return array($ids, $cls, $els);
}
function beats($a, $b) { $x = spec($a); $y = spec($b); return $x > $y; }

/* Every rule whose selector list names $selector, comments taken out
   first - a comment may hold braces of its own: the declaration blocks. */
function rules_of($css, $selector)
{
    $out = array();
    $css = preg_replace('/\/\*.*?\*\//s', '', $css);
    if (! preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER)) { return $out; }
    foreach ($m as $r) {
        $sels = array_map('trim', explode(',', $r[1]));
        if (in_array($selector, $sels, true)) { $out[] = $r[2]; }
    }
    return $out;
}
$decl = function ($sel, $prop, $val) use ($cssBlock) {
    foreach (rules_of($cssBlock, $sel) as $r) {
        if (preg_match('/(^|;)\s*' . preg_quote($prop, '/') . '\s*:\s*' . preg_quote($val, '/') . '\s*;/', $r) === 1) { return true; }
    }
    return false;
};

echo "\n  THE STYLESHEET - what each override relies on\n";
check($decl('.avalon-address', 'display', 'flex') && $decl('.avalon-address__lines', 'min-width', '0')
      && $decl('.avalon-address__line', 'display', 'block') && $decl('.avalon-address > .avalon-label', 'flex', 'none'),
      'the address: a flex row, label then lines, the lines a column that may shrink, one line each');
check($decl('#map .gm-style img', 'max-width', 'none') && beats('#map .gm-style img', '.elementor img')
      && $decl('#map .gm-style .slp_info_bubble img', 'max-width', '100%'),
      "the map's images at their own sizes, over Elementor's .elementor img (0-1-1); the bubble's images held to its width");
check($decl('.slp_info_bubble #slp_bubble_website a:focus-visible', 'outline', '2px solid #fff')
      && $decl('.slp_info_bubble #slp_bubble_directions a:focus-visible', 'outline', '2px solid #fff')
      && beats('.slp_info_bubble #slp_bubble_website a:focus-visible', '#slp_bubble_website a:focus'),
      "a white ring on the bubble's buttons on :focus-visible, over the theme's outline: none (1-1-1)");
check(beats('#map_sidebar .results_wrapper .sl_contact__info', '.store_locator_plus .slp_results_container .results_wrapper .sl_contact__info')
      && beats('.slp_info_bubble .sl_popup_contact_info', '.sl_popup_contact_info')
      && beats('.slp_info_bubble #slp_bubble_name', '#slp_bubble_name')
      && beats('#map_sidebar .results_wrapper .store_locator_name', '.store_locator_plus .slp_results_container .results_wrapper .store_locator_name'),
      "the phone sizes win over the theme's 16 px and 24 px by specificity, not by order");
check(preg_match('/@media \(max-width: 767px\) \{\s*#map_sidebar \.results_wrapper \.location_distance,\s*#map_sidebar \.results_wrapper \.sl_contact__info,\s*'
                 . '\.slp_info_bubble \.sl_popup_contact_info \{\s*font-size: 15px;\s*line-height: 1\.5;\s*\}\s*#map_sidebar \.results_wrapper \.store_locator_name,\s*'
                 . '\.slp_info_bubble #slp_bubble_name \{\s*font-size: 20px;\s*line-height: 1\.2;\s*\}\s*\}/', $cssBlock) === 1,
      'on a phone (767 px and under): 15 px text, 20 px names, on the cards and in the bubble');
$icons = array('distance' => 'f4d7', 'address' => 'f3c5', 'phone' => 'f879', 'email' => 'f0e0', 'hours' => 'f017');
$iconOk = true;
foreach ($icons as $k => $g) {
    $one = false;
    foreach (rules_of($cssBlock, '.avalon-fa .avalon-label--' . $k . '::before') as $r) {
        if (strpos($r, 'content: "\\' . $g . '";') !== false && strpos($r, 'content: "\\' . $g . '" / "";') !== false) { $one = true; }
    }
    if (! $one) { $iconOk = false; }
}
check($iconOk, 'five icons - route, map-marker-alt, phone-alt, envelope, clock - each with empty alternative text');
$ic = strpos($cssBlock, '.avalon-fa .avalon-label--distance,');
$pre = $ic !== false ? substr($cssBlock, 0, $ic) : '';
$lm = strrpos($pre, '@media');
check($ic !== false && $lm !== false && substr($pre, $lm, 27) === '@media (max-width: 767px) {' && strpos(substr($pre, $lm), '}') === false
      && substr_count(preg_replace('/\/\*.*?\*\//s', '', $cssBlock), '.avalon-fa ') === 15
      && $decl('.avalon-fa .avalon-label--hours', 'font-size', '0') && $decl('.avalon-fa .avalon-label--hours', 'width', '24px')
      && $decl('.avalon-fa .avalon-label--hours::before', 'font', '900 15px/1 "Font Awesome 5 Free"'),
      'icons only on a phone and only under .avalon-fa; the word kept at font-size 0; Font Awesome 5 solid at 15 px');
check($decl('.avalon-label--distance', 'font-weight', 'inherit') && $decl('.avalon-hours--card .avalon-hours__summary', 'text-wrap', 'balance')
      && preg_match('/white-space\s*:\s*nowrap/', preg_replace('/\/\*.*?\*\//s', '', $cssBlock)) === 0,
      "Distance: keeps the card's plain weight; the Hours: line balanced where it wraps, so the caret never stands alone - and nothing held to one line, to run past a narrow card");
check(preg_match('/@media \(max-width: 374px\) \{\s*\.slp_info_bubble #slp_bubble_email a\.avalon-email \{\s*font-size: 14px;\s*\}\s*\}/', $cssBlock) === 1
      && count(rules_of($cssBlock, '.slp_info_bubble #slp_bubble_email a.avalon-email')) === 1
      && count(rules_of($cssBlock, '.slp_info_bubble #slp_bubble_email')) === 0,
      "under 375 px the bubble's email address is 14 px, set on the link itself in that one rule - its label stays as the others");
check(preg_match('/!important/', preg_replace('/\/\*.*?\*\//s', '', $cssBlock)) === 0, 'no !important');

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
    'display' => span($code, $P4_START, $P4B_START),
    'bubble'  => span($code, $P4B_START, $BLOCK_START),
    'map'     => $block,
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

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'slp34_' . getmypid();
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
$GLOBALS['OPTIONS'] = array();
function cbname($cb) {
    if (is_array($cb)) { return (is_object($cb[0]) ? get_class($cb[0]) . '->' : $cb[0] . '::') . $cb[1]; }
    return is_string($cb) ? $cb : 'closure';
}
function add_action($h, $cb, $p = 10, $a = 1) { $GLOBALS['REC']['actions'][] = array($h, cbname($cb), $p, $a); return true; }
function add_filter($h, $cb, $p = 10, $a = 1) { $GLOBALS['REC']['filters'][] = array($h, cbname($cb), $p, $a); return true; }
function add_shortcode($t, $cb) { $GLOBALS['REC']['shortcodes'][] = array($t, cbname($cb)); }
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['OPTIONS']) ? $GLOBALS['OPTIONS'][$k] : $d; }
/* esc_html() as WordPress: invalid UTF-8 gives '', existing entities are
   not encoded twice. */
function esc_html($s) {
    $s = (string) $s;
    if ($s !== '' && ! preg_match('//u', $s)) { return ''; }
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8', false);
}
function esc_attr($s) { return esc_html($s); }
function esc_url($s, $protocols = null) {
    $GLOBALS['ESC_URL'][] = array($s, $protocols);
    return str_replace(array('&', "'"), array('&#038;', '&#039;'), (string) $s);
}
/* esc_url_raw() for what avalon_map_hover_icon() hands it: a root path or
   an http(s) URL. As WordPress, a scheme outside $protocols gives ''. */
function esc_url_raw($s, $protocols = null) {
    $GLOBALS['ESC_URL_RAW'][] = array($s, $protocols);
    $s = (string) $s;
    if (preg_match('#^([a-z][a-z0-9+.-]*):#i', $s, $m) && is_array($protocols)
        && ! in_array(strtolower($m[1]), $protocols, true)) {
        return '';
    }
    return str_replace(' ', '%20', $s);
}
function wp_make_link_relative($url) { return preg_replace('|^(https?:)?//[^/]+(/?.*)|i', '$2', $url); }
function wp_json_encode($v) { return json_encode($v); }
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

/* Aura's results and bubble layouts, as DEV served them in slplus.options
   on 2026-10-04 - after Part 4's and Part 4b's callbacks at 100. */
$AURA_R = <<<'EOT'
<div id="slp_results_[slp_location id]" class="results_entry [slp_location featured]"> <div class="results_row_full_column" id="slp_left_cell_[slp_location id]" > <h3 class="store_locator_name"><a href="[html ifset url][slp_location url][html ifset url]">[slp_location name]</a></h3> <span class="location_distance">Distance: [slp_location distance format="decimal1"] [slp_option distance_unit]</span> </div> <div class="results_row_full_column sl_contact__info" id="slp_center_cell_[slp_location id]" > <span class="slp_result_address slp_result_street">[slp_location avalon_address_label][slp_location address]</span> <span class="slp_result_address slp_result_street2">[slp_location address2]</span> <span class="slp_result_address slp_result_citystatezip">[slp_location city_state_zip]</span> <span class="slp_result_address slp_result_country">[slp_location country]</span> <span class="slp_result_address slp_result_phone">[slp_location avalon_phone_html]</span>[slp_location avalon_hours_html] <span class="slp_result_address slp_result_fax">[slp_location fax]</span> </div> <div class="results_row_full_column" id="slp_right_cell_[slp_location id]" > <span class="slp_result_contact slp_result_directions"><a href="http://[slp_option map_domain]/maps?saddr=[slp_location search_address]&amp;daddr=[slp_location location_address]" target="_blank" class="btn button btn-primary btn-lg store_locator_get_direction">[slp_option label_directions]</a></span> <span class="slp_result_contact slp_result_directions"><a href="/contact-dealer/?store_id=[slp_location id]&amp;dealer_id=[slp_location identifier]&amp;search=[slp_location search_address]&amp;address=[slp_location location_address]" class="btn button btn-primary btn-lg store_locator_contact_store"> Contact Dealer</a></span> <span class="slp_result_contact slp_result_hours">[slp_location hours format text]</span> [slp_location iconarray wrap="fullspan"] </div> </div>
EOT;
$AURA_B = <<<'EOT'
<div id="slp_info_bubble_[slp_location id]" class="slp_info_bubble [slp_location featured]"><span id="slp_bubble_name">[slp_location name suffix] </span><div class="sl_popup_contact_info"><span class="avalon-bubble-distance">Distance: [slp_location distance format="decimal1"] [slp_option distance_unit]</span><span id="slp_bubble_address">[slp_location avalon_address_label][slp_location address suffix]</span> <span id="slp_bubble_address2">[slp_location address2 suffix]</span> <span id="slp_bubble_city">[slp_location city suffix comma]</span> <span id="slp_bubble_state">[slp_location state suffix space]</span> <span id="slp_bubble_zip">[slp_location zip suffix]</span> <span id="slp_bubble_country"><span id="slp_bubble_country">[slp_location country suffix ]</span> </span> <span id="slp_bubble_phone">[slp_location avalon_phone_html]</span><span id="slp_bubble_email">[slp_location avalon_email_html]</span>[slp_location avalon_hours_html] <span id="slp_bubble_fax"><span class="location_detail_label">[slp_option label_fax ifset fax ]</span>[slp_location fax suffix ]</span> <span id="slp_bubble_description"><span id="slp_bubble_description">[html ifset description] [slp_location description raw]</span>[html ifset description]</span> <span id="slp_bubble_hours">[html ifset hours] <span class="location_detail_label">[slp_option label_hours ifset hours]</span> <span class="location_detail_hours">[slp_location hours suffix]</span> </span> <span id="slp_bubble_img">[html ifset img] [slp_location image wrap img]</span> <span id="slp_tags">[slp_location tags]</span></div><span id="slp_bubble_directions">[html ifset directions] [slp_option label_directions wrap directions]</span> <span id="slp_bubble_website">[html ifset url][slp_location web_link][html ifset url]</span> </div>
EOT;
/* Aura's bubble layout as stored, before Part 4b - suite-v033's fixture,
   read off DEV's page; Part 4b's output of it is $AURA_B, byte for byte. */
$AURA_B_STORED = <<<'EOT'
<div id="slp_info_bubble_[slp_location id]" class="slp_info_bubble [slp_location featured]"><span id="slp_bubble_name">[slp_location name suffix] </span><div class="sl_popup_contact_info"><span id="slp_bubble_address">[slp_location address suffix]</span> <span id="slp_bubble_address2">[slp_location address2 suffix]</span> <span id="slp_bubble_city">[slp_location city suffix comma]</span> <span id="slp_bubble_state">[slp_location state suffix space]</span> <span id="slp_bubble_zip">[slp_location zip suffix]</span> <span id="slp_bubble_country"><span id="slp_bubble_country">[slp_location country suffix ]</span> </span> <span id="slp_bubble_email">[slp_location email wrap mailto ][slp_option label_email ifset email][html ifset email][html closing_anchor ifset email]</span><span id="slp_bubble_phone">[html ifset phone] <span class="location_detail_label">[slp_option label_phone ifset phone]</span> [slp_location phone suffix ] </span> <span id="slp_bubble_fax"><span class="location_detail_label">[slp_option label_fax ifset fax ]</span>[slp_location fax suffix ]</span> <span id="slp_bubble_description"><span id="slp_bubble_description">[html ifset description] [slp_location description raw]</span>[html ifset description]</span> <span id="slp_bubble_hours">[html ifset hours] <span class="location_detail_label">[slp_option label_hours ifset hours]</span> <span class="location_detail_hours">[slp_location hours suffix]</span> </span> <span id="slp_bubble_img">[html ifset img] [slp_location image wrap img]</span> <span id="slp_tags">[slp_location tags]</span></div><span id="slp_bubble_directions">[html ifset directions] [slp_option label_directions wrap directions]</span> <span id="slp_bubble_website">[html ifset url][slp_location web_link][html ifset url]</span> </div>
EOT;
/* What Part 4c gives them - reviewed by hand, piece by piece. */
$AURA_R_OUT = str_replace(
    array('<span class="location_distance">Distance: ',
          '<span class="slp_result_address slp_result_street">[slp_location avalon_address_label][slp_location address]</span> <span class="slp_result_address slp_result_street2">[slp_location address2]</span> <span class="slp_result_address slp_result_citystatezip">[slp_location city_state_zip]</span> <span class="slp_result_address slp_result_country">[slp_location country]</span> '),
    array('<span class="location_distance"><span class="avalon-label avalon-label--distance">Distance:</span> ',
          '<span class="slp_result_address slp_result_street avalon-address">[slp_location avalon_address_html]</span> '),
    $AURA_R);
$AURA_B_OUT = str_replace(
    array('<span class="avalon-bubble-distance">Distance: ',
          '<span id="slp_bubble_address">[slp_location avalon_address_label][slp_location address suffix]</span> <span id="slp_bubble_address2">[slp_location address2 suffix]</span> <span id="slp_bubble_city">[slp_location city suffix comma]</span> <span id="slp_bubble_state">[slp_location state suffix space]</span> <span id="slp_bubble_zip">[slp_location zip suffix]</span> <span id="slp_bubble_country"><span id="slp_bubble_country">[slp_location country suffix ]</span> </span> '),
    array('<span class="avalon-bubble-distance"><span class="avalon-label avalon-label--distance">Distance:</span> ',
          '<span id="slp_bubble_address" class="avalon-address">[slp_location avalon_address_html]</span> '),
    $AURA_B);
/* The stored layouts the served ones came from: Part 4's and Part 4b's
   fields taken back out. */
$AURA_R_STORED = str_replace(
    array('[slp_location avalon_address_label]', '[slp_location avalon_phone_html]</span>[slp_location avalon_hours_html]'),
    array('', '[slp_location phone]</span>'), $AURA_R);

/* SLP's own defaults, as SLP 4.2.67 shipped them (class.slplus.php and
   SLP_SmartOptions.php) - the bubble's unclosed fax <span> included. */
$SLPDEF_B = <<<'EOT'
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
$SLPDEF_R = <<<'EOT'
<div id="slp_results_[slp_location id]" class="results_entry location_primary [slp_location featured]">
    <div class="results_row_left_column"   id="slp_left_cell_[slp_location id]"   >
        <span class="location_name">[slp_location name] [slp_location uml_buttons] [slp_location gfi_buttons]</span>
        <span class="location_distance">[slp_location distance_1] [slp_location distance_unit]</span>
    </div>
    <div class="results_row_center_column location_secondary" id="slp_center_cell_[slp_location id]" >
        <span class="slp_result_address slp_result_street">[slp_location address]</span>
        <span class="slp_result_address slp_result_street2">[slp_location address2]</span>
        <span class="slp_result_address slp_result_citystatezip">[slp_location city_state_zip]</span>
        <span class="slp_result_address slp_result_country">[slp_location country]</span>
        <span class="slp_result_address slp_result_phone">[slp_location phone_with_label]</span>
        <span class="slp_result_address slp_result_fax">[slp_location fax]</span>
    </div>
</div>
EOT;
/* SLP's default results layout through Part 4 and then 4c: the address
   field in the street span, Part 4's hours slot (after the city line,
   since there is no bare phone field) kept, after it. */
$SLPDEF_R_OUT = str_replace(
    "        <span class=\"slp_result_address slp_result_street\">[slp_location address]</span>\n        <span class=\"slp_result_address slp_result_street2\">[slp_location address2]</span>\n        <span class=\"slp_result_address slp_result_citystatezip\">[slp_location city_state_zip]</span>\n        <span class=\"slp_result_address slp_result_country\">[slp_location country]</span>\n",
    "        <span class=\"slp_result_address slp_result_street avalon-address\">[slp_location avalon_address_html]</span>[slp_location avalon_hours_html]\n",
    $SLPDEF_R);
/* SLP's default bubble through Part 4b and then 4c. */
$SLPDEF_B_OUT = str_replace(
    array("    <span id=\"slp_bubble_address\">[slp_location address       suffix  br]</span>\n    <span id=\"slp_bubble_address2\">[slp_location address2      suffix  br]</span>\n    <span id=\"slp_bubble_city\">[slp_location city          suffix  comma]</span>\n    <span id=\"slp_bubble_state\">[slp_location state suffix    space]</span>\n    <span id=\"slp_bubble_zip\">[slp_location zip suffix  br]</span>\n    <span id=\"slp_bubble_country\"><span id=\"slp_bubble_country\">[slp_location country       suffix  br]</span></span>\n",
          "    <span id=\"slp_bubble_email\">[slp_location email         wrap    mailto ][slp_option label_email ifset email][html closing_anchor ifset email][html br ifset email]</span>\n",
          "<span id=\"slp_bubble_phone\">[html br ifset phone]\n    <span class=\"location_detail_label\">[slp_option   label_phone   ifset   phone]</span>[slp_location phone         suffix    br]</span>\n"),
    array("    <span class=\"avalon-bubble-distance\"><span class=\"avalon-label avalon-label--distance\">Distance:</span> [slp_location distance format=\"decimal1\"] [slp_option distance_unit]</span><span id=\"slp_bubble_address\" class=\"avalon-address\">[slp_location avalon_address_html]</span>\n",
          "    \n",
          "<span id=\"slp_bubble_phone\">[slp_location avalon_phone_html]</span><span id=\"slp_bubble_email\">[slp_location avalon_email_html]</span>[slp_location avalon_hours_html]\n"),
    $SLPDEF_B);

function mk($a) {
    return array_merge(array('id' => '7', 'name' => 'Test Dealer', 'address' => '', 'address2' => '', 'city' => '',
                             'state' => '', 'zip' => '', 'country' => '', 'phone' => '212-555-0101'), $a);
}

/* ----------------------------------------------------------- scenarios */

$s = $argv[1] ?? '';
$out = array();
try {
    $o = SLP_Avalon::t_boot();
    switch ($s) {

    case 'address':
        $cases = array(
            'us'        => mk(array('address' => '100 Test Street', 'city' => 'Testville', 'state' => 'NY', 'zip' => '10001', 'country' => 'USA')),
            'ca'        => mk(array('address' => '1 Test Road', 'city' => 'Testford', 'state' => 'ONTARIO', 'zip' => 'K0G 1W0', 'country' => 'CANADA')),
            'qc'        => mk(array('address' => '1 Rue Test', 'city' => 'St-Test de Testville', 'state' => 'QUEBEC', 'zip' => 'J0B 2P0', 'country' => 'CANADA')),
            'ont'       => mk(array('address' => '2 Test Road', 'city' => 'Testford', 'state' => 'Ont', 'zip' => 'N0M 2L0', 'country' => 'Canada')),
            'nh'        => mk(array('address' => '3 Test Lane', 'city' => 'Testham', 'state' => 'NEW HAMPSHIRE', 'zip' => '03000', 'country' => 'USA')),
            'lower'     => mk(array('address' => '4 Test Lane', 'city' => 'Testham', 'state' => 'texas', 'zip' => '75000', 'country' => 'United States')),
            'dots'      => mk(array('address' => '5 Test Lane', 'city' => 'Testham', 'state' => 'N.Y.', 'zip' => '10001', 'country' => 'U.S.A.')),
            'mx'        => mk(array('address' => 'Calle Prueba 1', 'city' => 'Pruebas', 'state' => 'Sonora', 'zip' => '83000', 'country' => 'Mexico')),
            'mxbc'      => mk(array('address' => 'Calle Prueba 2', 'city' => 'Pruebas', 'state' => 'BC', 'zip' => '22000', 'country' => 'Mexico')),
            'unknown2'  => mk(array('address' => '6 Test Lane', 'city' => 'Testham', 'state' => 'Xx', 'zip' => '1', 'country' => '')),
            'unit'      => mk(array('address' => '100 Test Street', 'address2' => 'Suite 5', 'city' => 'Testville', 'state' => 'NY', 'zip' => '10001')),
            'unitonly'  => mk(array('address2' => 'Unit 9', 'city' => 'Testville', 'state' => 'NY', 'zip' => '10001')),
            'nostate'   => mk(array('address' => '7 Test Lane', 'city' => 'Testville', 'zip' => '10001')),
            'nocity'    => mk(array('address' => '8 Test Lane', 'state' => 'NY', 'zip' => '10001')),
            'nozip'     => mk(array('address' => '9 Test Lane', 'city' => 'Testville', 'state' => 'NY')),
            'cityonly'  => mk(array('address' => '10 Test Lane', 'city' => 'Testville')),
            'nostreet'  => mk(array('city' => 'Testville', 'state' => 'NY', 'zip' => '10001')),
            'none'      => mk(array()),
            'blank'     => mk(array('address' => '  ', 'city' => "\xC2\xA0", 'state' => '&nbsp;')),
            'escape'    => mk(array('address' => 'A &amp; B &lt;b&gt;Dock&lt;/b&gt;', 'city' => "O&#039;Testville", 'state' => 'NY', 'zip' => '10001')),
            'raw'       => mk(array('address' => '<script>x</script> & Co', 'city' => 'Testville', 'state' => 'NY', 'zip' => '10001')),
            'nbsp'      => mk(array('address' => "\xC2\xA0100 Test Street\xC2\xA0", 'city' => '&nbsp;Testville&nbsp;', 'state' => 'NY', 'zip' => '10001')),
            'badutf8'   => mk(array('address' => '100 Test Street', 'city' => "Test\xFFville", 'state' => 'NY', 'zip' => '10001')),
            'notscalar' => mk(array('address' => array('x'), 'city' => 'Testville', 'state' => null, 'zip' => '10001')),
            'hidecc'    => mk(array('address' => '11 Test Lane', 'city' => 'Testville', 'state' => 'NY', 'zip' => '10001', 'country' => '')),
            'nokeys'    => array('id' => '9', 'name' => 'Test Dealer'),
        );
        foreach ($cases as $k => $m) { $out['r'][$k] = SLP_Avalon::avalon_address_fields($m); }
        $out['in'] = $cases;
        $out['state'] = array(SLP_Avalon::avalon_display_state('Ontario'), SLP_Avalon::avalon_display_state('QU' . "\xC3\x89" . 'BEC'),
                              SLP_Avalon::avalon_display_state(''), SLP_Avalon::avalon_display_state('Baja California'),
                              SLP_Avalon::avalon_display_state('ny'), SLP_Avalon::avalon_display_state(' TX '));
        $out['marker_notarray'] = array($o->avalon_marker_address('x'), $o->avalon_marker_address(null));
        $out['marker_array'] = $o->avalon_marker_address($cases['us']);
        break;

    case 'cards':
        $L = function ($x) use ($o) { return $o->avalon_results_layout_address($x); };
        $out['aura'] = $L($AURA_R);
        $out['aura_want'] = $AURA_R_OUT;
        $out['aura_again'] = $L($out['aura']);
        $out['aura_third'] = $L($out['aura_again']);
        $out['aura_in'] = $AURA_R;
        $out['chain'] = $L($o->avalon_results_layout($AURA_R_STORED));
        $out['slpdef'] = $L($o->avalon_results_layout($SLPDEF_R));
        $out['slpdef_want'] = $SLPDEF_R_OUT;
        $out['slpdef_again'] = $L($out['slpdef']);
        $out['crlf'] = $L($o->avalon_results_layout(str_replace("\n", "\r\n", $SLPDEF_R))) === str_replace("\n", "\r\n", $SLPDEF_R_OUT);
        /* A street span with more than the street in it: not recognised,
           and the city line therefore kept. */
        $out['extra_in'] = '<span class="slp_result_address slp_result_street">[slp_location avalon_address_label][slp_location address] (rear)</span> '
                         . '<span class="slp_result_address slp_result_citystatezip">[slp_location city_state_zip]</span>';
        $out['extra'] = $L($out['extra_in']);
        $out['decoy_in'] = '<span data-class="slp_result_street">[slp_location address]</span>'
                         . '<span class="slp_result_address slp_result_street">[slp_location address]</span>'
                         . '<span class="slp_result_address slp_result_street2">[slp_location address2]</span>';
        $out['decoy'] = $L($out['decoy_in']);
        $out['street2_in'] = '<span class="slp_result_address slp_result_street2">[slp_location address2]</span>'
                           . '<span class="slp_result_address slp_result_citystatezip">[slp_location city_state_zip]</span>';
        $out['street2'] = $L($out['street2_in']);
        $out['busy_in'] = '<span class="slp_result_address slp_result_street">[slp_location address]</span> '
                        . '<span class="slp_result_address slp_result_citystatezip">[slp_location city_state_zip] <b>x</b></span>';
        $out['busy'] = $L($out['busy_in']);
        $out['dist_in'] = '<span class="location_distance x">  Distance: 4 mi</span><span class="location_distance">Distance: 5 mi</span>';
        $out['dist'] = $L($out['dist_in']);
        $out['dist_again'] = $L($out['dist']);
        $out['addr2_in'] = '<span class="slp_result_address slp_result_street">[slp_location address2]</span>'
                         . '<span class="slp_result_address slp_result_citystatezip">[slp_location city_state_zip]</span>';
        $out['addr2'] = $L($out['addr2_in']);
        $out['nodist_in'] = '<span class="location_distance">[slp_location distance_1] mi</span><span class="location_distance2">Distance: 5</span>';
        $out['nodist'] = $L($out['nodist_in']);
        $out['dollar_in'] = '<span class="slp_result_address slp_result_street">[slp_location address]</span> <span class="z">$1 \\1 ${0}</span>'
                          . '<span class="slp_result_address slp_result_country">[slp_location country]</span>';
        $out['dollar'] = $L($out['dollar_in']);
        $out['noclass_attr_in'] = '<span class="slp_result_street" title="t">[slp_location address suffix br]</span>';
        $out['noclass_attr'] = $L($out['noclass_attr_in']);
        /* A stored layout with the field on a line of its own: Part 4 puts
           its label straight after the opening tag, before the line break. */
        $out['spaced_in'] = "<span class=\"slp_result_address slp_result_street\">\n    [slp_location address]\n  </span>\n"
                          . "  <span class=\"slp_result_address slp_result_citystatezip\">[slp_location city_state_zip]</span>";
        $out['spaced'] = $L($o->avalon_results_layout($out['spaced_in']));
        $out['empty'] = array($L(''), $L(null));
        break;

    case 'bubble':
        $L = function ($x) use ($o) { return $o->avalon_bubble_layout_address($x); };
        $out['aura'] = $L($AURA_B);
        $out['aura_want'] = $AURA_B_OUT;
        $out['aura_again'] = $L($out['aura']);
        $out['aura_third'] = $L($out['aura_again']);
        $out['aura_in'] = $AURA_B;
        $out['slpdef'] = $L($o->avalon_bubble_layout($SLPDEF_B));
        $out['slpdef_want'] = $SLPDEF_B_OUT;
        $out['slpdef_again'] = $L($out['slpdef']);
        $out['flat_in'] = '<span id="slp_bubble_address" class="a b">[slp_location address]</span> <span id="slp_bubble_country">[slp_location country suffix br]</span><span id="slp_bubble_phone">p</span>';
        $out['flat'] = $L($out['flat_in']);
        $out['busy_in'] = '<span id="slp_bubble_address">[slp_location address] <i>x</i></span> <span id="slp_bubble_city">[slp_location city suffix comma]</span>';
        $out['busy'] = $L($out['busy_in']);
        $out['decoy_in'] = '<span data-id="slp_bubble_address">[slp_location address]</span><span id="slp_bubble_address">[slp_location address]</span>'
                         . '<span id="slp_bubble_city">[slp_location city]</span><span id="slp_bubble_citystate">[slp_location city]</span>';
        $out['decoy'] = $L($out['decoy_in']);
        $out['partial_in'] = '<span id="slp_bubble_address">[slp_location address]</span><span id="slp_bubble_city"><b>[slp_location city]</b></span>'
                           . '<span id="slp_bubble_zip">[slp_location zip]</span>';
        $out['partial'] = $L($out['partial_in']);
        $out['nodist_in'] = '<span class="avalon-bubble-distance">Distance 5 mi</span>';
        $out['nodist'] = $L($out['nodist_in']);
        $out['empty'] = array($L(''), $L(null));
        break;

    case 'jsopts':
        $o->t_wire();
        $stored_b = $AURA_B_STORED;
        $chain = array(
            array(10, 0, function ($opt) use ($o) { $opt['resultslayout'] = $o->avalon_results_layout($GLOBALS['R_STORED']); $opt['bubblelayout'] = 'whatever SLP had'; return $opt; }),
            array(90, 0, function ($opt) use ($stored_b) { $opt['bubblelayout'] = $stored_b; $opt['resultslayout'] = $GLOBALS['R_STORED']; return $opt; }),
        );
        $GLOBALS['R_STORED'] = $AURA_R_STORED;
        $seq = 1;
        $names = array();
        foreach ($GLOBALS['REC']['filters'] as $f) {
            if ($f[0] !== 'slp_js_options') { continue; }
            $names[] = array($f[1], $f[2]);
            $meth = substr($f[1], strlen('SLP_Avalon->'));
            $chain[] = array($f[2], $seq++, function ($opt) use ($o, $meth) { return $o->{$meth}($opt); });
        }
        usort($chain, function ($a, $b) { return $a[0] === $b[0] ? $a[1] - $b[1] : $a[0] - $b[0]; });
        $out['names'] = $names;
        $out['chain_n'] = count($chain);
        $out['stored_b_ok'] = ($o->avalon_bubble_layout($stored_b) === $AURA_B);
        foreach (array('unset' => null, 'path' => '/wp-content/uploads/2026/10/hover-pin.png',
                       'url' => 'https://example.test/wp-content/uploads/hover-pin.png', 'spaced' => "  /wp-content/x.png \n",
                       'js' => 'javascript:alert(1)', 'proto' => '//cdn.example.test/x.png', 'relative' => 'x.png',
                       'ftp' => 'ftp://example.test/x.png', 'data' => 'data:image/png;base64,AAAA', 'upper' => 'HTTPS://EXAMPLE.TEST/X.PNG') as $k => $val) {
            if ($val === null) { unset($GLOBALS['OPTIONS']['avalon_map_hover_icon']); } else { $GLOBALS['OPTIONS']['avalon_map_hover_icon'] = $val; }
            $v = array('map_region' => 'us', 'bubblelayout' => 'x', 'resultslayout' => 'x');
            foreach ($chain as $c) { $v = $c[2]($v); }
            $out['icon'][$k] = $v['avalon_map_hover_icon'] ?? 'MISSING';
            if ($k === 'path') { $out['chain'] = $v; }
        }
        $out['want_r'] = $AURA_R_OUT;
        $out['want_b'] = $AURA_B_OUT;
        $GLOBALS['OPTIONS'] = array();
        $out['noop'] = array($o->avalon_js_options_map('x'), $o->avalon_js_options_map(null),
                             $o->avalon_js_options_map(array('bubblelayout' => array('x'), 'resultslayout' => 7)));
        break;

    case 'jsfull':
        /* As SLP runs it: add_to_js_options() at 10 builds the results
           layout with set_ResultsLayout( false, true ), which applies
           slp_javascript_results_string - SLP Experience at 90 starting
           again from the stored layout, then every slp_avalon callback - and
           hands the stored bubble layout on as it is. */
        $o->t_wire();
        $rs = array(array(90, 0, function ($l) { return $GLOBALS['R_STORED']; }));
        $js = array();
        $seq = 1;
        $out['rs_names'] = array();
        foreach ($GLOBALS['REC']['filters'] as $f) {
            $meth = substr($f[1], strlen('SLP_Avalon->'));
            if ($f[0] === 'slp_javascript_results_string') {
                $out['rs_names'][] = array($f[1], $f[2]);
                $rs[] = array($f[2], $seq++, function ($l) use ($o, $meth) { return $o->{$meth}($l); });
            } elseif ($f[0] === 'slp_js_options') {
                $js[] = array($f[2], $seq++, function ($opt) use ($o, $meth) { return $o->{$meth}($opt); });
            }
        }
        $order = function ($a, $b) { return $a[0] === $b[0] ? $a[1] - $b[1] : $a[0] - $b[0]; };
        usort($rs, $order);
        $results_string = function () use ($rs) { $l = 'never read'; foreach ($rs as $c) { $l = $c[2]($l); } return $l; };
        $through = function ($v, $chain) { foreach ($chain as $c) { $v = $c[2]($v); } return $v; };
        $slp10 = array(10, 0, function ($opt) use ($results_string) {
            $opt['resultslayout'] = $results_string();
            $opt['bubblelayout'] = $GLOBALS['B_STORED'];
            return $opt;
        });
        $leave = array(90, 0, function ($opt) { return $opt; });
        $merge = array(90, 0, function ($opt) { $opt['resultslayout'] = $GLOBALS['R_STORED']; $opt['bubblelayout'] = $GLOBALS['B_STORED']; return $opt; });
        $GLOBALS['R_STORED'] = $AURA_R_STORED;
        $GLOBALS['B_STORED'] = $AURA_B_STORED;
        foreach (array('pass' => $leave, 'merge' => $merge) as $k => $exp) {
            $chain = array_merge(array($slp10, $exp), $js);
            usort($chain, $order);
            $out[$k] = $through(array('map_region' => 'us'), $chain);
        }
        usort($js, $order);
        $out['again'] = $through($out['pass'], $js);
        $out['want_r'] = $AURA_R_OUT;
        $out['want_b'] = $AURA_B_OUT;
        $GLOBALS['R_STORED'] = $SLPDEF_R;
        $GLOBALS['B_STORED'] = $SLPDEF_B;
        $chain = array_merge(array($slp10, $leave), $js);
        usort($chain, $order);
        $def = $through(array(), $chain);
        $out['slpdef_r'] = $def['resultslayout'];
        $out['slpdef_b'] = $def['bubblelayout'];
        $out['slpdef_want_r'] = $SLPDEF_R_OUT;
        $out['slpdef_want_b'] = $SLPDEF_B_OUT;
        break;

    case 'markers':
        $o->t_wire();
        $row = array('sl_id' => '1', 'sl_address' => '100 Test Street', 'sl_city' => 'Testville', 'sl_state' => 'ONTARIO',
                     'sl_zip' => 'K0G 1W0', 'sl_country' => 'CANADA', 'sl_phone' => '613-555-0101', 'sl_email' => 'sales@dealer.example');
        $m = array('id' => '1', 'name' => 'Test Dealer', 'address' => '100 Test Street', 'address2' => '', 'city' => 'Testville',
                   'state' => 'ONTARIO', 'zip' => 'K0G 1W0', 'country' => 'CANADA', 'phone' => '613-555-0101',
                   'email' => 'sales@dealer.example', 'data' => $row);
        $chain = array();
        $seq = 0;
        foreach ($GLOBALS['REC']['filters'] as $f) {
            if ($f[0] !== 'slp_results_marker_data') { continue; }
            $chain[] = array($f[2], $seq++, substr($f[1], strlen('SLP_Avalon->')));
        }
        usort($chain, function ($a, $b) { return $a[0] === $b[0] ? $a[1] - $b[1] : $a[0] - $b[0]; });
        $out['chain'] = array_map(function ($c) { return array($c[0], $c[2]); }, $chain);
        $x = $m;
        foreach ($chain as $c) { $x = $o->{$c[2]}($x); }
        $out['with'] = $x;
        $out['in'] = $m;
        $out['hours_card'] = SLP_Avalon::avalon_hours_markup(array('days' => array(array(1, 'Monday', '9 AM-5 PM')), 'periods' => array(),
                                                                   'tz' => 'America/New_York', 'o' => array(), 'u' => 0, 'attr' => array()), 'card');
        $out['hours_store'] = SLP_Avalon::avalon_hours_markup(array('days' => array(array(1, 'Monday', '9 AM-5 PM')), 'periods' => array(),
                                                                    'tz' => 'America/New_York', 'o' => array(), 'u' => 0, 'attr' => array()), 'store');
        break;

    case 'rocket':
        $out['m'] = array(SLP_Avalon::avalon_rocket_rucss_safelist_map(array('.keep')),
                          SLP_Avalon::avalon_rocket_rucss_safelist_map(null),
                          SLP_Avalon::avalon_rocket_rucss_safelist_map('x'));
        $out['all'] = SLP_Avalon::avalon_rocket_rucss_safelist_map(
                          SLP_Avalon::avalon_rocket_rucss_safelist_bubble(SLP_Avalon::avalon_rocket_rucss_safelist(array())));
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
         . $LIFTS['consts'] . $LIFTS['wiring'] . $LIFTS['display'] . $LIFTS['bubble'] . $LIFTS['map']
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
$A = function ($l1, $l2 = null) {
    $lines = '';
    foreach (array($l1, $l2) as $l) { if ($l !== null) { $lines .= '<span class="avalon-address__line">' . $l . '</span>'; } }
    return '<b class="avalon-label avalon-label--address">Address:</b> <span class="avalon-address__lines">' . $lines . '</span>';
};

$lint = shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($hfile) . ' 2>&1');
if (strpos((string) $lint, 'No syntax errors') === false) {
    fwrite(STDERR, "the harness does not compile:\n{$lint}\n");
    exit(2);
}

/* ------------------------------------------------------- the address */

echo "\n  THE ADDRESS FIELD\n";
$r = run($hfile, 'address');
$x = $r['r'] ?? array();
$F = function ($k) use ($x) { return $x[$k]['avalon_address_html'] ?? null; };
check($F('us') === $A('100 Test Street', 'Testville, NY 10001'),
      'Address: bold, then the street and "City, ST ZIP" as two lines - USA not shown');
check($F('ca') === $A('1 Test Road', 'Testford, ON K0G 1W0') && $F('qc') === $A('1 Rue Test', 'St-Test de Testville, QC J0B 2P0')
      && $F('ont') === $A('2 Test Road', 'Testford, ON N0M 2L0'),
      'Canada in the same order; ONTARIO, QUEBEC and Ont shown as ON and QC; CANADA and Canada not shown');
check($F('nh') === $A('3 Test Lane', 'Testham, NH 03000') && $F('lower') === $A('4 Test Lane', 'Testham, TX 75000')
      && $F('dots') === $A('5 Test Lane', 'Testham, NY 10001'),
      'NEW HAMPSHIRE, texas and N.Y. shown as NH, TX and NY; United States and U.S.A. not shown');
check($F('mx') === $A('Calle Prueba 1', 'Pruebas, Sonora 83000 Mexico') && $F('mxbc') === $A('Calle Prueba 2', 'Pruebas, BC 22000 Mexico'),
      'another country: shown after the postal code, the state as the feed wrote it');
check($F('unknown2') === $A('6 Test Lane', 'Testham, XX 1'), 'an unknown two-letter state: its letters, upper-cased; an empty country: nothing');
check($F('unit') === $A('100 Test Street, Suite 5', 'Testville, NY 10001') && $F('unitonly') === $A('Unit 9', 'Testville, NY 10001'),
      'address2 after the street and a comma; address2 alone stands as the first line');
check($F('nostate') === $A('7 Test Lane', 'Testville 10001') && $F('nocity') === $A('8 Test Lane', 'NY 10001')
      && $F('nozip') === $A('9 Test Lane', 'Testville, NY') && $F('cityonly') === $A('10 Test Lane', 'Testville'),
      "the second line in SLP's own punctuation when a part is missing (create_city_state_zip())");
check($F('nostreet') === $A('Testville, NY 10001'), 'no street: one line, the city line');
check(! isset($x['none']['avalon_address_html']) && ! isset($x['blank']['avalon_address_html']) && ! isset($x['nokeys']['avalon_address_html']),
      'no address at all - empty, spaces, no-break spaces, no keys: no field, never "Address:" over nothing');
check($F('escape') === $A('A &amp; B &lt;b&gt;Dock&lt;/b&gt;', 'O&#039;Testville, NY 10001')
      && $F('raw') === $A('&lt;script&gt;x&lt;/script&gt; &amp; Co', 'Testville, NY 10001'),
      "SLP's escaped values decoded and escaped once again; raw markup escaped");
check($F('nbsp') === $A('100 Test Street', 'Testville, NY 10001'), 'no-break spaces round a value, raw or as &nbsp;: trimmed');
check($F('badutf8') === $A('100 Test Street'), 'invalid UTF-8 in a line: that line left out, the other kept');
check($F('notscalar') === $A('Testville 10001'), 'a value that is not a string - an array, a null - counts as empty');
check($F('hidecc') === $A('11 Test Lane', 'Testville, NY 10001'), "a country SLP Experience emptied (show_country off): nothing");
$kept = true;
foreach ($x as $k => $m) { unset($m['avalon_address_html']); if ($m !== ($r['in'][$k] ?? null)) { $kept = false; } }
check($kept && count($x) === 26, 'nothing else on the marker changes');
check(($r['state'] ?? null) === array('ON', 'QC', '', 'Baja California', 'NY', 'TX'),
      'the state shown: a name the address key knows - accented QUEBEC too - as its code, lower case and stray spaces too; anything else as given');
check(($r['marker_notarray'] ?? null) === array('x', null) && isset($r['marker_array']['avalon_address_html']),
      'the filter callback: a marker that is not an array passes through; an array gets the field');

/* --------------------------------------------------------- the cards */

echo "\n  THE CARDS - the results layout\n";
$r = run($hfile, 'cards');
$ao = $r['aura'] ?? '';
check($ao !== '' && $ao === ($r['aura_want'] ?? null) && $ao !== ($r['aura_in'] ?? null), "Aura's results layout becomes exactly the reviewed output");
check(cnt($ao, '[slp_location avalon_address_html]') === 1 && cnt($ao, 'avalon_address_label') === 0 && cnt($ao, 'city_state_zip') === 0
      && cnt($ao, 'slp_result_street2') === 0 && cnt($ao, '[slp_location country]') === 0,
      'one address field; the label, street2, city line and country gone');
check(cnt($ao, '<span class="avalon-label avalon-label--distance">Distance:</span> [slp_location distance format="decimal1"]') === 1,
      'Distance: a label, once');
check(has($ao, '<span class="slp_result_address slp_result_phone">[slp_location avalon_phone_html]</span>[slp_location avalon_hours_html]')
      && has($ao, '<h3 class="store_locator_name">') && has($ao, 'store_locator_contact_store'),
      "Part 4's phone and hours, the name and the Contact Dealer button untouched");
check(($r['aura_again'] ?? '') === $ao && ($r['aura_third'] ?? '') === $ao, 'idempotent - a second and a third pass change nothing');
check(($r['chain'] ?? '') === $ao, "the full chain from Aura's stored layout - Part 4 at 100, then 4c - comes to the same");
check(($r['slpdef'] ?? '') !== '' && ($r['slpdef'] ?? '') === ($r['slpdef_want'] ?? null) && ($r['slpdef_again'] ?? '') === ($r['slpdef'] ?? 'x'),
      "SLP's default layout, through Part 4 and 4c: exactly the reviewed output - Part 4's hours slot kept - and idempotent");
check(($r['crlf'] ?? false) === true, 'the same with CRLF line endings');
check(($r['extra'] ?? '') === ($r['extra_in'] ?? 'x'), 'a street span holding more than the street: left alone, and so is the city line');
check(($r['decoy'] ?? '') === '<span data-class="slp_result_street">[slp_location address]</span>'
      . '<span class="slp_result_address slp_result_street avalon-address">[slp_location avalon_address_html]</span>',
      'data-class="slp_result_street" is not the street span; street2 is not street, and goes once the field is in');
check(($r['street2'] ?? '') === ($r['street2_in'] ?? 'x'), 'no street span: nothing else removed');
check(($r['busy'] ?? '') === '<span class="slp_result_address slp_result_street avalon-address">[slp_location avalon_address_html]</span> '
      . '<span class="slp_result_address slp_result_citystatezip">[slp_location city_state_zip] <b>x</b></span>',
      'a city span holding more than its field: kept');
check(($r['dist'] ?? '') === '<span class="location_distance x">  <span class="avalon-label avalon-label--distance">Distance:</span> 4 mi</span><span class="location_distance">Distance: 5 mi</span>'
      && ($r['dist_again'] ?? '') === ($r['dist'] ?? 'x') && ($r['nodist'] ?? '') === ($r['nodist_in'] ?? 'x'),
      'Distance: wrapped in the first location_distance span only, and a second pass does not reach the next; no "Distance:" or another class: nothing');
check(($r['addr2'] ?? '') === ($r['addr2_in'] ?? 'x'), 'a street span holding address2 is not the street: left alone, the city line kept');
check(($r['dollar'] ?? '') === '<span class="slp_result_address slp_result_street avalon-address">[slp_location avalon_address_html]</span> <span class="z">$1 \\1 ${0}</span>',
      '$1, \\1 and ${0} in the layout stay literal text');
check(($r['noclass_attr'] ?? '') === '<span class="slp_result_street avalon-address" title="t">[slp_location avalon_address_html]</span>',
      "the span's other attributes kept; [slp_location address suffix br] recognised");
check(($r['spaced'] ?? '') === '<span class="slp_result_address slp_result_street avalon-address">[slp_location avalon_address_html]</span>[slp_location avalon_hours_html]',
      "the field on a line of its own, Part 4's label before the line break: recognised; the city line gone, Part 4's hours slot after it kept");
check(($r['empty'] ?? null) === array('', ''), 'empty or null input: an empty string, no crash');

/* -------------------------------------------------------- the bubble */

echo "\n  THE BUBBLE - the bubble layout\n";
$r = run($hfile, 'bubble');
$bo = $r['aura'] ?? '';
check($bo !== '' && $bo === ($r['aura_want'] ?? null) && $bo !== ($r['aura_in'] ?? null), "Aura's bubble layout becomes exactly the reviewed output");
check(cnt($bo, '[slp_location avalon_address_html]') === 1 && cnt($bo, 'slp_bubble_city') === 0 && cnt($bo, 'slp_bubble_state') === 0
      && cnt($bo, 'slp_bubble_zip') === 0 && cnt($bo, 'slp_bubble_country') === 0 && cnt($bo, 'slp_bubble_address2') === 0,
      'one address field; address2, city, state, zip and both country spans gone');
check(has($bo, '<span id="slp_bubble_phone">[slp_location avalon_phone_html]</span><span id="slp_bubble_email">[slp_location avalon_email_html]</span>[slp_location avalon_hours_html]')
      && has($bo, '<span id="slp_bubble_website">[html ifset url][slp_location web_link][html ifset url]</span>')
      && has($bo, '<div id="slp_info_bubble_[slp_location id]" class="slp_info_bubble [slp_location featured]">'),
      "Part 4b's lines, Contact Dealer and the outer div (main.js reads its id) untouched");
check(cnt($bo, '<span') === cnt($r['aura_in'] ?? '', '<span') - 5 && cnt($bo, '</span>') === cnt($r['aura_in'] ?? '', '</span>') - 5
      && cnt($bo, '<div') === cnt($r['aura_in'] ?? '', '<div') && cnt($bo, '</div>') === cnt($r['aura_in'] ?? '', '</div>'),
      'six spans out - address2, city, state, zip, the country pair - and one in, the Distance: label: the tags still balance');
check(($r['aura_again'] ?? '') === $bo && ($r['aura_third'] ?? '') === $bo, 'idempotent');
check(($r['slpdef'] ?? '') !== '' && ($r['slpdef'] ?? '') === ($r['slpdef_want'] ?? null) && ($r['slpdef_again'] ?? '') === ($r['slpdef'] ?? 'x'),
      "SLP's default bubble, through Part 4b and 4c: exactly the reviewed output, idempotent");
check(($r['flat'] ?? '') === '<span id="slp_bubble_address" class="a b avalon-address">[slp_location avalon_address_html]</span><span id="slp_bubble_phone">p</span>',
      "a single country span goes too; a class already on the address span is kept and extended");
check(($r['busy'] ?? '') === ($r['busy_in'] ?? 'x'), 'an address span holding more than the street: left alone, and so is the city');
check(($r['decoy'] ?? '') === '<span data-id="slp_bubble_address">[slp_location address]</span><span id="slp_bubble_address" class="avalon-address">[slp_location avalon_address_html]</span>'
      . '<span id="slp_bubble_citystate">[slp_location city]</span>',
      'data-id is not the address span; slp_bubble_citystate is not slp_bubble_city');
check(($r['partial'] ?? '') === '<span id="slp_bubble_address" class="avalon-address">[slp_location avalon_address_html]</span><span id="slp_bubble_city"><b>[slp_location city]</b></span>',
      'a city span holding markup round its field is kept; the zip span goes');
check(($r['nodist'] ?? '') === ($r['nodist_in'] ?? 'x') && ($r['empty'] ?? null) === array('', ''),
      'a distance line without "Distance:": nothing; empty or null input: an empty string');

/* -------------------------------------------------- script options */

echo "\n  SCRIPT OPTIONS (slp_js_options)\n";
$r = run($hfile, 'jsopts');
check(($r['names'] ?? null) === array(array('SLP_Avalon->avalon_js_options_layout', 100), array('SLP_Avalon->avalon_js_options_bubble', 100),
                                      array('SLP_Avalon->avalon_js_options_map', 110)),
      "Part 4's and 4b's callbacks at 100, then this one at 110");
check(($r['stored_b_ok'] ?? false) === true, "the stored bubble layout this scenario starts from is the one Part 4b turns into Aura's served layout");
check(($r['chain_n'] ?? 0) === 5 && ($r['chain']['resultslayout'] ?? '') === ($r['want_r'] ?? 'x') && ($r['chain']['bubblelayout'] ?? '') === ($r['want_b'] ?? 'x'),
      "layouts SLP Experience merges in at 90 come out of 110 exactly as Aura's reviewed outputs");
check(($r['chain']['map_region'] ?? null) === 'us', '  ... and no other option is touched');
$ic = $r['icon'] ?? array();
check(($ic['unset'] ?? null) === '' && ($ic['path'] ?? null) === '/wp-content/uploads/2026/10/hover-pin.png'
      && ($ic['url'] ?? null) === 'https://example.test/wp-content/uploads/hover-pin.png' && ($ic['spaced'] ?? null) === '/wp-content/x.png'
      && ($ic['upper'] ?? null) === 'HTTPS://EXAMPLE.TEST/X.PNG',
      'avalon_map_hover_icon: always set; a path from the root or an http(s) URL, trimmed; unset gives ""');
check(($ic['js'] ?? null) === '' && ($ic['proto'] ?? null) === '' && ($ic['relative'] ?? null) === '' && ($ic['ftp'] ?? null) === ''
      && ($ic['data'] ?? null) === '',
      'javascript:, protocol-relative, a bare file name, ftp: and data: give no hover pin');
check(($r['noop'] ?? null) === array('x', null, array('bubblelayout' => array('x'), 'resultslayout' => 7, 'avalon_map_hover_icon' => '')),
      'options that are not an array pass through; layouts that are not strings are left alone');
$r = run($hfile, 'jsfull');
check(($r['rs_names'] ?? null) === array(array('SLP_Avalon->avalon_results_layout', 100), array('SLP_Avalon->avalon_results_layout_address', 110)),
      "slp_javascript_results_string: Part 4 at 100, then this at 110");
$pr = $r['pass']['resultslayout'] ?? '';
check($pr !== '' && $pr === ($r['want_r'] ?? 'x') && cnt($pr, 'avalon_address_label') === 0 && cnt($pr, '[slp_location avalon_address_html]') === 1,
      "SLP at 10 builds the results layout through that chain, the field already in; SLP Experience at 90 leaves it; Part 4 at 100 puts its Address: label back - and at 110 it is gone again: Address: once, exactly Aura's reviewed output");
check(($r['pass']['bubblelayout'] ?? '') === ($r['want_b'] ?? 'x') && ($r['pass']['map_region'] ?? null) === 'us',
      "  ... the bubble, from the stored layout SLP hands on, exactly Aura's reviewed output; no other option touched");
check(($r['merge']['resultslayout'] ?? '') === ($r['want_r'] ?? 'x') && ($r['merge']['bubblelayout'] ?? '') === ($r['want_b'] ?? 'x'),
      "  ... and the same when SLP Experience merges its stored layouts in at 90");
check(($r['again']['resultslayout'] ?? '') === ($r['want_r'] ?? 'x') && ($r['again']['bubblelayout'] ?? '') === ($r['want_b'] ?? 'x'),
      "the script options through Part 4, 4b and 4c a second time: Address: still once, on the cards and in the bubble");
check(($r['slpdef_r'] ?? '') === ($r['slpdef_want_r'] ?? 'x') && ($r['slpdef_b'] ?? '') === ($r['slpdef_want_b'] ?? 'x'),
      "SLP's default layouts through the same chain: exactly the reviewed outputs");

/* ------------------------------------------------------ marker chain */

echo "\n  THE MARKER CHAIN (slp_results_marker_data) AND THE LABELS\n";
$r = run($hfile, 'markers');
check(($r['chain'] ?? null) === array(array(20, 'avalon_marker_labels'), array(25, 'avalon_marker_email'), array(30, 'avalon_marker_address')),
      "Part 4's labels at 20, Part 4b's email at 25, the address at 30");
$w = $r['with'] ?? array();
check(($w['avalon_address_html'] ?? null) === $A('100 Test Street', 'Testville, ON K0G 1W0'),
      'a Canadian marker leaves with the two-line address, ON for ONTARIO, no CANADA');
check(has($w['avalon_phone_html'] ?? '', '<b class="avalon-label avalon-label--phone">Phone:</b> <a class="avalon-tel" href="tel:+16135550101">')
      && has($w['avalon_email_html'] ?? '', '<b class="avalon-label avalon-label--email">Email:</b> <a class="avalon-email" href="mailto:sales@dealer.example"')
      && ($w['avalon_address_label'] ?? null) === '<b class="avalon-label avalon-label--address">Address:</b> ',
      'Phone:, Email: and Part 4\'s Address: each name their kind');
check(has($r['hours_card'] ?? '', '<summary class="avalon-hours__summary"><b class="avalon-label avalon-label--hours">Hours:</b> <span class="avalon-hours__status">See hours</span></summary>')
      && ! has($r['hours_store'] ?? 'x', 'avalon-label'),
      "Hours: on a card names its kind; the store page's hours have no label, as before");
$k1 = $w; unset($k1['avalon_phone_html'], $k1['avalon_address_label'], $k1['avalon_email_html'], $k1['avalon_address_html']);
check($k1 === ($r['in'] ?? null), 'nothing else on the marker changes');

/* ---------------------------------------------------------- WP Rocket */

echo "\n  WP ROCKET\n";
$r = run($hfile, 'rocket');
check(($r['m'][0] ?? null) === array('.keep', '(.*).avalon-address(.*)', '(.*).avalon-fa(.*)', '(.*)#map_sidebar(.*)', '(.*).gm-style(.*)')
      && count($r['m'][1] ?? array()) === 4 && count($r['m'][2] ?? array()) === 4,
      'four patterns appended; existing entries kept; a list that is not an array starts fresh');
$all = $r['all'] ?? array();
check(count($all) === 11 && ($all[0] ?? '') === '/wp-content/plugins/slp_avalon/assets/css/avalon-hours.css',
      "after Part 4's and 4b's: the file, their six selectors, then these four");
$sels = array();
$body = preg_replace('/\/\*.*?\*\//s', '', $cssBlock);
if (preg_match_all('/([^{}@]+)\{[^{}]*\}/', $body, $mm)) {
    foreach ($mm[1] as $group) {
        foreach (explode(',', $group) as $one) { $one = trim($one); if ($one !== '') { $sels[] = $one; } }
    }
}
$miss = array();
foreach ($sels as $one) {
    $hit = false;
    foreach (array_slice($all, 1) as $pat) { if (preg_match('~^' . $pat . '~', $one)) { $hit = true; break; } }
    if (! $hit) { $miss[] = $one; }
}
check(count($sels) >= 30 && $miss === array(),
      sprintf("every selector of the Part 4c block (%d) matches a pattern read from the selector's start%s", count($sels),
              $miss ? ': missing ' . implode(' | ', $miss) : ''));

/* ------------------------------------------------------ registrations */

echo "\n  REGISTRATIONS\n";
$r = run($hfile, 'wiring');
$acts = $r['actions'] ?? array();
$fils = $r['filters'] ?? array();
$find = function ($list, $row) { return count(array_filter($list, function ($e) use ($row) { return $e === $row; })); };
check($find($fils, array('slp_results_marker_data', 'SLP_Avalon->avalon_marker_address', 30, 1)) === 1,
      'slp_results_marker_data -> avalon_marker_address, priority 30');
check($find($fils, array('slp_javascript_results_string', 'SLP_Avalon->avalon_results_layout_address', 110, 1)) === 1
      && $find($fils, array('slp_js_options', 'SLP_Avalon->avalon_js_options_map', 110, 1)) === 1,
      'slp_javascript_results_string and slp_js_options, both at 110');
check($find($fils, array('rocket_rucss_safelist', 'SLP_Avalon::avalon_rocket_rucss_safelist_map', 10, 1)) === 1,
      'rocket_rucss_safelist -> avalon_rocket_rucss_safelist_map');
check($find($fils, array('slp_results_marker_data', 'SLP_Avalon->avalon_marker_labels', 20, 1)) === 1
      && $find($fils, array('slp_results_marker_data', 'SLP_Avalon->avalon_marker_email', 25, 1)) === 1
      && $find($fils, array('slp_js_options', 'SLP_Avalon->avalon_js_options_layout', 100, 1)) === 1
      && $find($fils, array('slp_js_options', 'SLP_Avalon->avalon_js_options_bubble', 100, 1)) === 1
      && $find($fils, array('slp_javascript_results_string', 'SLP_Avalon->avalon_results_layout', 100, 1)) === 1,
      "Part 4's and 4b's on the same hooks are still there");
check(count($acts) + count($fils) === 40, sprintf("%d registrations in all - Part 4b's %d plus these four", 40, 36));
check(count($r['shortcodes'] ?? array()) === 5, 'five shortcodes, as before');

/* ---------------------------------------------------------- crashes */

echo "\n  HARNESS\n";
check($CRASHES === array(), 'no scenario crashed' . ($CRASHES ? ': ' . json_encode($CRASHES) : ''));

@unlink($hfile);
@unlink($akCopy);
@rmdir($tmp);

printf("\n  %d passed, %d failed, %d total\n\n", $pass, $fail, $pass + $fail);
exit($fail ? 1 : 0);
