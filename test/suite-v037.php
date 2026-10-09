<?php
/**
 * suite-v037.php - validates slp_avalon v0.0.27 Part 4f: the class and the
 * stylesheet, which it both changes.
 *
 * PART 4F (the owner's decisions of 2026-10-07 and 2026-10-08). The class
 * is Part 4d's - Part 4e ran it unchanged - plus four edits: a note among
 * the registrations, the readers of the two numbered-pin options,
 * avalon_map_number_icon and avalon_map_number_hover_icon, and the check
 * they share, and the two options in avalon_js_options_map(), Part 4c's
 * callback on slp_js_options at 110. Nothing new is registered. The
 * stylesheet is Part 4e's plus four: the header twice; .avalon-hours
 * positioned, the box its hidden "Hours: " is placed in (the white bar
 * under the footer on DEV, 2026-10-08); and at the end, the numbered
 * cards' disc and the map's buttons - Reset, + and - in Google's look.
 * What the buttons and numbers do is slp_avalon.js's (test/suite-map.js).
 *
 * Everything below is suite-v036's, on the same evidence: the class's
 * identity now runs through Part 4f's edits to Part 4d's class first, the
 * stylesheet's through Part 4f's to Part 4e's, then on as before.
 * suite-v036.php stays in the repo as Part 4e's record; it fails on these
 * files, as it should. New here: the two readers, executed, on every kind
 * of value an option can hold; the script options with and without them;
 * the Part 4f block of the stylesheet, rule by rule, with the specificity
 * each state rule relies on over the theme's button:hover and
 * button:focus, its safelist, and the positioned box behind every
 * absolutely placed rule in the file.
 *
 * What Part 4d's suite covered, and this one still does:
 *
 *   the hours markup    avalon_hours_markup(): a card's Hours: in front of
 *                       its block, hidden from screen readers, and in the
 *                       summary for them alone; the caret an <i> held with
 *                       the status's last word; each day's hours in a span
 *                       of their own; no empty <span> anywhere - SLP hides
 *                       them; the store page's block as before but for the
 *                       caret and those spans
 *   the bubble frame    avalon_bubble_layout_frame() on Aura's bubble layout
 *                       as DEV serves it (2026-10-06): exactly the reviewed
 *                       output - Avalon's lines wrapped for the grid, SLP's
 *                       two button spans for their row, every id and class
 *                       kept - idempotent, and on the layouts it must leave
 *                       alone, SLP's own default among them
 *   the script options  avalon_js_options_frame() through a real filter
 *                       chain - SLP at 10, SLP Experience at 90 leaving or
 *                       replacing the layouts, Part 4 and 4b at 100, Part 4c
 *                       at 110, this at 120 - and through it twice
 *   WP Rocket           every selector of the Part 4d stylesheet block on
 *                       the safelist, matched from the selector's start;
 *                       Part 4c's .avalon-fa pattern gone with the icons
 *   the registrations   41 in all: Part 4c's 40 plus this one
 *   the stylesheet      by identity Part 4d's plus Part 4e's edits, and
 *                       Part 4c's plus Part 4d's, its icon block given way
 *                       to Part 4d's block, pinned; the owner's decisions
 *                       each rule carries, and the specificity each
 *                       override relies on
 *
 * WHAT CARRIES FORWARD BY IDENTITY
 *
 * Part 4d is Part 4c plus one registration, six edits and one inserted
 * block. The first assertions take the block out, reverse the edits, and
 * require the result to be v0.0.27-part4c byte for byte - 12cde985,
 * 330,544 bytes - so suite-v034's 94/94 and its controls carry forward for
 * every region Part 4d did not touch. The stylesheet the same way: Part
 * 4d's block out, Part 4c's icon block back, the seven edits reversed - Part
 * 4c's, d2426903, 15,316 bytes.
 *
 * WHAT IS EXECUTED
 *
 * The constants, add_actions() with register_shortcodes(), and the blocks
 * of Parts 4, 4b, 4c and 4d are lifted verbatim into a harness class with
 * the real address-key class beside it, and run against recording doubles.
 * ONE PROCESS PER SCENARIO; every warning and notice is an exception, so a
 * crash fails its scenario by name.
 *
 * The test data is synthetic: no dealer name, address, phone or email.
 * Aura's layouts are templates, the site's own settings.
 *
 * Usage:
 *   php suite-v037.php [<class.slp_avalon.php>] [<avalon-hours.css>] [<class.slp_avalon_addresskey.php>]
 *
 * The class and the stylesheet default to the repo's; the address-key class
 * to the repo's, which must be the pinned one.
 *
 * Exit 0 all passed, 1 any failed, 2 the suite could not run.
 */

$clsPath = $argv[1] ?? (__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'slp_avalon'
                       . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'class.slp_avalon.php');
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

echo "suite-v037  slp_avalon 0.0.27 Part 4f\n";
printf("  class   %s\n          %s  %d bytes\n", $clsPath, md5($code), strlen($code));
printf("  css     %s\n          %s  %d bytes\n", $cssPath, md5($css), strlen($css));
printf("  addrkey %s\n          %s  %d bytes  (pinned)\n\n", $akPath, md5($ak), strlen($ak));

/* ------------------------------------------------------------------ */
/* IDENTITY: the class is Part 4d's; Part 4d minus its edits is Part    */
/* 4c, byte for byte.                                                   */
/* ------------------------------------------------------------------ */

$P4D = array('778185553d663139707b9d23dff00407', 339614);
$P4C = array('12cde985f071d0e77d2ca1acf935d426', 330544);

$BLOCK_START = "        /**\r\n         * v0.0.27 Part 4d. The approved find-a-dealer design.";
$BLOCK_END   = "        public function avalon_rest_protected_slugs(){";
$BLOCK_PIN   = array('6c273b1f12fb18da8ce2bc8f94586e4e', 7553);
$P4C_START   = "        /**\r\n         * v0.0.27 Part 4c. Cards and bubbles laid out for phones.";
$P4B_START   = "        /**\r\n         * v0.0.27 Part 4b. The info bubble shows what the card shows.";
$P4_START    = "        /**\r\n         * v0.0.27 Part 4. Showing the hours.";

/* The registration, [what Part 4c had, what Part 4d wrote]. */
$WIRE_OLD = "            add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist_map'));\r\n";
$WIRE_NEW = "            add_filter('rocket_rucss_safelist', array('SLP_Avalon','avalon_rocket_rucss_safelist_map'));\r\n"
          . "            //\r\n"
          . "            // v0.0.27 Part 4d. The approved find-a-dealer design: the bubble\r\n"
          . "            // in three parts.\r\n"
          . "            //\r\n"
          . "            // The bubble layout on slp_js_options at 120, after Part 4c's\r\n"
          . "            // callback at 110 has made the address one field: Avalon's\r\n"
          . "            // fields wrapped for the grid, SLP's two button spans for the\r\n"
          . "            // row, the spans' ids kept. The cards need no new layout -\r\n"
          . "            // avalon-hours.css lays SLP's own cells out as one grid - and\r\n"
          . "            // nothing new goes to WP Rocket: every new selector names\r\n"
          . "            // #map_sidebar, .slp_info_bubble or .avalon-hours, safelisted\r\n"
          . "            // already.\r\n"
          . "            add_filter('slp_js_options', array(self::\$instance,'avalon_js_options_frame'), 120, 1);\r\n";
/* [what it is, what Part 4c had, what Part 4d wrote], in the order
   build-v027-part4d.py makes them; CRLF, as the class is. */
$EDITS = array(
    array("class: avalon_hours_markup() - the docblock says where Hours: is now",
          "         * CARD. The <details> only, labelled \"Hours:\", on every width.\r\n"
          . "         *\r\n"
          . "         * NO EMPTY <span>. SLP hides every empty span in a result as it\r\n"
          . "         * inserts it (slp_core.js 1424, s0.274), so a span this markup left\r\n"
          . "         * empty for the script to fill would stay hidden for good. Every\r\n"
          . "         * span below carries text from the start.\r\n",
          "         * CARD. The <details> only, on every width. From Part 4d its label\r\n"
          . "         * stands in front of the block, in the card's label column - on\r\n"
          . "         * screen only, aria-hidden; the summary keeps \"Hours:\" for screen\r\n"
          . "         * readers (avalon-hours__sr), and a click on the label still opens\r\n"
          . "         * the week (avalon-hours.js). Each day's hours sit in a span of\r\n"
          . "         * their own, which on a card never breaks, so TODAY wraps under\r\n"
          . "         * them instead (avalon-hours.css); the store page's may, as before.\r\n"
          . "         *\r\n"
          . "         * NO EMPTY <span>. SLP hides every empty span in a result as it\r\n"
          . "         * inserts it (slp_core.js 1424, s0.274), so a span this markup left\r\n"
          . "         * empty for the script to fill would stay hidden for good. Every\r\n"
          . "         * span below carries text from the start, and the caret - empty by\r\n"
          . "         * nature - is an <i> (Part 4d).\r\n"),
    array("class: avalon_hours_markup() - each day's hours in a span that never breaks",
          "                       . '</th><td>' . esc_html( \$d[2] ) . '</td></tr>';\r\n",
          "                       . '</th><td><span class=\"avalon-hours__time\">' . esc_html( \$d[2] ) . '</span></td></tr>';\r\n"),
    array("class: avalon_hours_markup() - the summary: Hours: for screen readers, the caret held to its word",
          "            \$summary = '<summary class=\"avalon-hours__summary\">'\r\n"
          . "                     . ( \$card ? '<b class=\"avalon-label avalon-label--hours\">Hours:</b> ' : '' )\r\n"
          . "                     . '<span class=\"avalon-hours__status\">See hours</span></summary>';\r\n",
          "            \$summary = '<summary class=\"avalon-hours__summary\">'\r\n"
          . "                     . ( \$card ? '<span class=\"avalon-hours__sr\">Hours: </span>' : '' )\r\n"
          . "                     . '<span class=\"avalon-hours__status\">See <span class=\"avalon-hours__nowrap\">hours'\r\n"
          . "                     . '<i class=\"avalon-hours__caret\" aria-hidden=\"true\"></i></span></span></summary>';\r\n"),
    array("class: avalon_hours_markup() - a card's Hours: in front of its block",
          "            if ( \$card ) {\r\n"
          . "                return '<div class=\"avalon-hours avalon-hours--card\" data-avalon-hours=\"' . \$data . '\">'\r\n"
          . "                     . \$narrow . '</div>';\r\n"
          . "            }\r\n",
          "            if ( \$card ) {\r\n"
          . "                return '<b class=\"avalon-label avalon-label--hours\" aria-hidden=\"true\">Hours:</b>'\r\n"
          . "                     . '<div class=\"avalon-hours avalon-hours--card\" data-avalon-hours=\"' . \$data . '\">'\r\n"
          . "                     . \$narrow . '</div>';\r\n"
          . "            }\r\n"),
    array("class: Part 4c's safelist no longer names the icons' class",
          "         * Cards, bubbles and the .avalon-fa class all appear only after a\r\n"
          . "         * search, a click or a script, so Remove Unused CSS never sees\r\n"
          . "         * them; written from the selector's start as WP Rocket 3.11.0.2\r\n"
          . "         * and later read them (s0.284). .gm-style covers the map's own\r\n"
          . "         * images, #map_sidebar the cards' type sizes on a phone.\r\n",
          "         * Cards and bubbles appear only after a search or a click, so\r\n"
          . "         * Remove Unused CSS never sees them; written from the selector's\r\n"
          . "         * start as WP Rocket 3.11.0.2 and later read them (s0.284).\r\n"
          . "         * .gm-style covers the map's own images, #map_sidebar the cards.\r\n"
          . "         * Part 4d took out .avalon-fa with the icons it named.\r\n"),
    array("class: Part 4c's safelist - the icons' pattern out",
          "            \$list[] = '(.*).avalon-address(.*)';\r\n"
          . "            \$list[] = '(.*).avalon-fa(.*)';\r\n",
          "            \$list[] = '(.*).avalon-address(.*)';\r\n")
);
/* Part 4f's edits, [what it is, what Part 4d had, what Part 4f wrote], in
   the order build-v027-part4f.py makes them; CRLF, as the class is. */
$CLASS_EDITS_4F = array(
    array("class: the Part 4f note among the registrations - nothing new registered",
          "            add_filter('slp_js_options', array(self::\$instance,'avalon_js_options_frame'), 120, 1);\r\n"
          . "            //\r\n",
          "            add_filter('slp_js_options', array(self::\$instance,'avalon_js_options_frame'), 120, 1);\r\n"
          . "            //\r\n"
          . "            // v0.0.27 Part 4f. Reset and numbered pins on the find-a-dealer\r\n"
          . "            // map, all slp_avalon.js's: the numbered pins' two URLs ride in\r\n"
          . "            // the script options beside the hover pin's, from Part 4c's\r\n"
          . "            // avalon_js_options_map() at 110, so nothing new is registered,\r\n"
          . "            // and every new selector names #map_sidebar or .gm-style, which\r\n"
          . "            // Part 4c put on WP Rocket's safelist.\r\n"
          . "            //\r\n"),
    array("class: avalon_map_number_icon(), avalon_map_number_hover_icon() and the check they share",
          "         * v0.0.27 Part 4c. The layouts and the hover pin, in the script options.\r\n",
          "         * v0.0.27 Part 4f. The numbered pin's URL, or ''.\r\n"
          . "         *\r\n"
          . "         * The option avalon_map_number_icon: the dealer's pin at rest on the\r\n"
          . "         * find-a-dealer map, with its number drawn on its head by\r\n"
          . "         * slp_avalon.js (30 x 40 art, the number at 15, 15). Set, the\r\n"
          . "         * dealers of each search are numbered 1 to n on the pins and the\r\n"
          . "         * cards; empty or not a URL, there are no numbers and the pins are\r\n"
          . "         * SLP's. Read as avalon_map_hover_icon() reads its own. Store\r\n"
          . "         * pages are untouched: their map prints map_end_icon.\r\n"
          . "         */\r\n"
          . "        public static function avalon_map_number_icon(){\r\n"
          . "            return self::avalon_map_icon_url( 'avalon_map_number_icon' );\r\n"
          . "        }\r\n"
          . "\r\n"
          . "        /**\r\n"
          . "         * v0.0.27 Part 4f. The numbered pin lit - hovered, chosen, its\r\n"
          . "         * bubble open - or ''. The option avalon_map_number_hover_icon,\r\n"
          . "         * read the same way. Without it a numbered pin lit is only raised.\r\n"
          . "         */\r\n"
          . "        public static function avalon_map_number_hover_icon(){\r\n"
          . "            return self::avalon_map_icon_url( 'avalon_map_number_hover_icon' );\r\n"
          . "        }\r\n"
          . "\r\n"
          . "        /**\r\n"
          . "         * v0.0.27 Part 4f. An icon option as a URL, or '': an http(s) URL,\r\n"
          . "         * or a path from the site's root, which slp_avalon.js resolves\r\n"
          . "         * against the page. Anything else, and an option that is not a\r\n"
          . "         * string, is none.\r\n"
          . "         */\r\n"
          . "        private static function avalon_map_icon_url( \$name ){\r\n"
          . "            \$v = get_option( \$name, '' );\r\n"
          . "            if ( ! is_string( \$v ) ) {\r\n"
          . "                return '';\r\n"
          . "            }\r\n"
          . "            \$v = trim( \$v );\r\n"
          . "            if ( '' === \$v || 0 === strpos( \$v, '//' )\r\n"
          . "                 || ( '/' !== \$v[0] && ! preg_match( '#^https?://#i', \$v ) ) ) {\r\n"
          . "                return '';\r\n"
          . "            }\r\n"
          . "            return (string) esc_url_raw( \$v, array( 'http', 'https' ) );\r\n"
          . "        }\r\n"
          . "\r\n"
          . "        /**\r\n"
          . "         * v0.0.27 Part 4c. The layouts and the hover pin, in the script options.\r\n"),
    array("class: avalon_js_options_map()'s docblock - the numbered pins ride along",
          "         * options that are not an array, pass through.\r\n"
          . "         */\r\n",
          "         * options that are not an array, pass through.\r\n"
          . "         *\r\n"
          . "         * Part 4f. The numbered pins ride along the same way, as\r\n"
          . "         * avalon_map_number_icon and avalon_map_number_hover_icon.\r\n"
          . "         */\r\n"),
    array("class: avalon_js_options_map() - the numbered pins in the script options",
          "            \$options['avalon_map_hover_icon'] = self::avalon_map_hover_icon();\r\n"
          . "            return \$options;\r\n",
          "            \$options['avalon_map_hover_icon'] = self::avalon_map_hover_icon();\r\n"
          . "            \$options['avalon_map_number_icon'] = self::avalon_map_number_icon();\r\n"
          . "            \$options['avalon_map_number_hover_icon'] = self::avalon_map_number_hover_icon();\r\n"
          . "            return \$options;\r\n")
);

echo "  IDENTITY - the class\n";
/* Part 4f's edits out, the last made first: Part 4d's class, which Part 4e
   ran unchanged. */
$code4d = $code;
$f4Ok = true;
for ($i = count($CLASS_EDITS_4F) - 1; $i >= 0; $i--) {
    $e = $CLASS_EDITS_4F[$i];
    if (substr_count($code4d, $e[2]) !== 1) { $f4Ok = false; continue; }
    $code4d = implode($e[1], explode($e[2], $code4d, 2));
}
check($f4Ok && md5($code4d) === $P4D[0] && strlen($code4d) === $P4D[1],
      sprintf("Part 4f's %d edits, each present once, reversed: the class IS v0.0.27-part4d's, byte for byte (77818555, 339,614 bytes)", count($CLASS_EDITS_4F)));
$anchorsOk = substr_count($code, $BLOCK_START) === 1 && substr_count($code, $BLOCK_END) === 1
          && substr_count($code, $P4C_START) === 1 && substr_count($code, $P4B_START) === 1
          && substr_count($code, $P4_START) === 1
          && strpos($code, $P4_START) < strpos($code, $P4B_START)
          && strpos($code, $P4B_START) < strpos($code, $P4C_START)
          && strpos($code, $P4C_START) < strpos($code, $BLOCK_START)
          && strpos($code, $BLOCK_START) < strpos($code, $BLOCK_END);
check($anchorsOk, "the Part 4d block is present once, after Part 4c's, directly before avalon_rest_protected_slugs()");
if (! $anchorsOk) {
    fwrite(STDERR, "cannot find the Part 4d block; nothing else can be lifted\n");
    exit(2);
}
$a = strpos($code, $BLOCK_START);
$b = strpos($code, $BLOCK_END);
$block = substr($code, $a, $b - $a);
check(md5($block) === $BLOCK_PIN[0] && strlen($block) === $BLOCK_PIN[1],
      sprintf('the block is the one this suite was written against (%s, %d bytes)', $BLOCK_PIN[0], $BLOCK_PIN[1]));
/* The block out of Part 4d's class - Part 4f's edits already reversed. */
$a4 = strpos($code4d, $BLOCK_START);
$b4 = strpos($code4d, $BLOCK_END);
$rev = ($a4 !== false && $b4 !== false && $b4 > $a4) ? substr($code4d, 0, $a4) . substr($code4d, $b4) : $code4d;
$editsOk = substr_count($rev, $WIRE_NEW) === 1;
if ($editsOk) {
    $rev = implode($WIRE_OLD, explode($WIRE_NEW, $rev, 2));
}
for ($i = count($EDITS) - 1; $i >= 0; $i--) {
    $e = $EDITS[$i];
    if (substr_count($rev, $e[2]) !== 1) { $editsOk = false; continue; }
    $rev = implode($e[1], explode($e[2], $rev, 2));
}
check($editsOk, sprintf('the registration and the %d edits are each present exactly once', count($EDITS)));
check(md5($rev) === $P4C[0] && strlen($rev) === $P4C[1],
      'block out and the edits reversed, the file IS v0.0.27-part4c (12cde985, 330,544 bytes)');
check(substr_count($code, "\r\n") === substr_count($code, "\n") && substr_count($code, "\r") === substr_count($code, "\r\n"),
      'pure CRLF - no bare LF, no bare CR');
check(preg_match('/[^\x00-\x7f]/', $block . $WIRE_NEW) === 0 && strpos($block, "\t") === false,
      'the new block and its registration are pure ASCII, no tabs');
$bare = '';
foreach (token_get_all($code) as $tok) {
    if (! is_array($tok)) { $bare .= $tok; continue; }
    if ($tok[0] !== T_COMMENT && $tok[0] !== T_DOC_COMMENT) { $bare .= $tok[1]; }
}
check(strpos($bare, 'avalon-fa') === false
      && strpos($bare, "'<summary class=\"avalon-hours__summary\">'\r\n                     . ( \$card ? '<b") === false,
      'no icon class left in the code, comments aside; no label left inside a card\'s summary');

/* ------------------------------------------------------------------ */
/* IDENTITY: the stylesheet.                                           */
/* ------------------------------------------------------------------ */

$CSS_P4D = array('ffe117b6c77e2dfa3d082b5bb7c8ca10', 26810);
$CSS_P4C = array('d24269039747a7d04c7ce931ef02c2ec', 15316);
$CSS_BLOCK_START = "/* ------------------------------------------- Part 4d: the cards, one grid */";
/* Part 4d's block as Part 4d shipped it, and as Part 4e leaves it. */
$CSS_BLOCK_PIN   = array('8dbd5829c066a178d35c9b65a95b5ede', 13174);
$CSS_BLOCK_4E    = array('97032542b7c877f4853991a50fb3d305', 12719);
/* Part 4e's edits, [what it is, what Part 4d had, what Part 4e wrote], in
   the order build-v027-part4e.py makes them. */
$CSS_EDITS_4E = array(
    array("css: the header names Part 4e",
          " * avalon-hours.css\n"
          . " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4d.\n"
          . " *\n",
          " * avalon-hours.css\n"
          . " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4e.\n"
          . " *\n"),
    array("css: the header says what Part 4e changes",
          " * 2026-10-07).\n"
          . " */\n",
          " * 2026-10-07).\n"
          . " *\n"
          . " * PART 4e, in Part 4d's blocks and the last block of the file: the\n"
          . " * owner's decisions of 2026-10-07, with Part 4d on Aura DEV. TODAY under\n"
          . " * its hours at every width, on the cards and in the bubble, with more\n"
          . " * room under it than over it. No bubble on a phone - slp_avalon.js brings\n"
          . " * the dealer's card into view instead, and the card flashes - so the\n"
          . " * bubble's body no longer scrolls anywhere: Part 4d's cap on it, its\n"
          . " * scrollbar and the fade at its foot are gone, and Part 4c's 14 px email\n"
          . " * with them. The bubble is as wide as its content (the theme), so it is no\n"
          . " * longer a size container. Open stays green.\n"
          . " */\n"),
    array("css: Part 4c's 14 px email under 375 px goes with the phone's bubble",
          "\n"
          . "/* Part 4c's 15 px text and 20 px names are in Part 4d's block, at the end\n"
          . "   of the file: at 1024 px and under on the cards, at every width in the\n"
          . "   bubble.\n"
          . "\n"
          . "   Part 4c. Under 375 px the email address in the bubble is 14 px; its\n"
          . "   label stays as the others. Measured again with Part 4d's labels in a\n"
          . "   column of their own, at the bubble's width on each phone (2026-10-07):\n"
          . "   none of Aura's 97 emails wraps, from 320 px up; one that did would\n"
          . "   break inside the address (Part 4b) rather than be cut. 1-2-1: the\n"
          . "   link sets its own size, under the 15 px it would inherit. */\n"
          . "@media (max-width: 374px) {\n"
          . "  .slp_info_bubble #slp_bubble_email a.avalon-email {\n"
          . "    font-size: 14px;\n"
          . "  }\n"
          . "}\n"
          . "\n",
          "\n"
          . "/* Part 4c's 15 px text and 20 px names are in Part 4d's block, further\n"
          . "   down: at 1024 px and under on the cards, at every width in the bubble.\n"
          . "   Its 14 px email under 375 px went with Part 4e: a phone has no bubble\n"
          . "   to show it in. */\n"
          . "\n"),
    array("css: the week's comment - TODAY under its hours at every width",
          "   days, today first (avalon-hours.js) with a dot before it and TODAY\n"
          . "   after its hours, both in the primary colour; the other days indented by\n"
          . "   the dot's width, so every name starts in one column. A table still -\n"
          . "   its row headers are what a screen reader reads - pulled 15 px left, so\n"
          . "   the dot sits in the gap and the day names line up with the status\n"
          . "   above. TODAY is generated text with empty alternative text: the row\n"
          . "   already says aria-current=\"date\". The hours never break here; TODAY\n"
          . "   drops under them where a card is too narrow for both. None of this\n"
          . "   reaches the store page: its week is as Part 4 drew it. */\n"
          . "#map_sidebar .avalon-hours--card .avalon-hours__week,\n",
          "   days, today first (avalon-hours.js) with a dot before it and TODAY\n"
          . "   under its hours, both in the primary colour; the other days indented by\n"
          . "   the dot's width, so every name starts in one column. A table still -\n"
          . "   its row headers are what a screen reader reads - pulled 15 px left, so\n"
          . "   the dot sits in the gap and the day names line up with the status\n"
          . "   above. TODAY is generated text with empty alternative text: the row\n"
          . "   already says aria-current=\"date\". The hours never break here. None of\n"
          . "   this reaches the store page: its week is as Part 4 drew it.\n"
          . "\n"
          . "   Part 4e. TODAY sits under its hours at every width (the owner,\n"
          . "   2026-10-07): Part 4d put it beside them wherever both fitted, which\n"
          . "   made the bubble wider than anything else in it needed. 3 px over it\n"
          . "   and 4 px under - 2 px more under than Part 4d's narrow cards gave it,\n"
          . "   so that it reads with its own hours and not with the next day's. */\n"
          . "#map_sidebar .avalon-hours--card .avalon-hours__week,\n"),
    array("css: TODAY a block under its hours, 3 px over it and 4 px under",
          "  content: \"TODAY\" / \"\";\n"
          . "  display: inline-block;\n"
          . "  margin-left: 12px;\n"
          . "  color: var(--avalon-hours-today);\n",
          "  content: \"TODAY\" / \"\";\n"
          . "  display: block;\n"
          . "  margin: 3px 0 4px;\n"
          . "  color: var(--avalon-hours-today);\n"),
    array("css: the cards alone are size containers; the 320 px rule gone",
          "\n"
          . "/* Narrow cards and bubbles. The card's grid and the bubble's are size\n"
          . "   containers for these two rules alone; a browser without container\n"
          . "   queries keeps the week as above, TODAY wrapping where it must.\n"
          . "\n"
          . "   Under 320 px of content - a phone upright, a phone held sideways, the\n"
          . "   bubble on a phone - TODAY does not fit beside the hours, so it takes\n"
          . "   the line under them, flush with them, rather than wrap with a 12 px\n"
          . "   indent.\n"
          . "\n"
          . "   Under 250 px - the results column of a phone held sideways, 769 to\n"
          . "   about 880 px wide, a 320 px phone upright, the bubble on one - the week\n"
          . "   does not fit beside the labels either, so the opened week takes the\n"
          . "   whole width under the Hours: line, its day names 15 px in, rather than\n"
          . "   run into the padding or past the edge. Measured on Aura 2026-10-07: the\n"
          . "   week needs 193 px, and has the content less 50 to 56 px beside the\n"
          . "   labels. */\n"
          . "#map_sidebar .results_wrapper .results_entry,\n"
          . ".slp_info_bubble .avalon-bubble__info {\n"
          . "  container-type: inline-size;\n"
          . "}\n"
          . "\n"
          . "@container (max-width: 320px) {\n"
          . "  #map_sidebar .avalon-hours--card .avalon-hours__week tbody > tr.is-today > td::after,\n"
          . "  .slp_info_bubble .avalon-hours--card .avalon-hours__week tbody > tr.is-today > td::after {\n"
          . "    display: block;\n"
          . "    margin: 3px 0 2px;\n"
          . "  }\n"
          . "}\n"
          . "\n"
          . "@container (max-width: 250px) {\n"
          . "  #map_sidebar .avalon-hours--card .avalon-hours__week,\n"
          . "  .slp_info_bubble .avalon-hours--card .avalon-hours__week {\n"
          . "    width: 100cqw;\n",
          "\n"
          . "/* Narrow cards. The card's grid is a size container for this one rule; a\n"
          . "   browser without container queries keeps the week as above.\n"
          . "\n"
          . "   Under 250 px of content - the results column of a phone held sideways,\n"
          . "   769 to about 880 px wide, a 320 px phone upright - the week does not\n"
          . "   fit beside the labels, so the opened week takes the whole width under\n"
          . "   the Hours: line, its day names 15 px in, rather than run into the\n"
          . "   padding or past the edge. Measured on Aura 2026-10-07, with TODAY\n"
          . "   beside its hours as Part 4d had it: the week needed 193 px, and has\n"
          . "   the content less 50 to 56 px beside the labels.\n"
          . "\n"
          . "   Part 4e. The cards alone. The bubble is as wide as its content now\n"
          . "   (the theme), and what is sized by its content cannot be a size\n"
          . "   container as well: contained, its lines would no longer count towards\n"
          . "   the bubble's width. Nor does it need to be: the theme keeps the bubble\n"
          . "   280 px wide at least, and a phone has none. Part 4d's 320 px rule went\n"
          . "   when TODAY moved under its hours for good. */\n"
          . "#map_sidebar .results_wrapper .results_entry {\n"
          . "  container-type: inline-size;\n"
          . "}\n"
          . "\n"
          . "@container (max-width: 250px) {\n"
          . "  #map_sidebar .avalon-hours--card .avalon-hours__week {\n"
          . "    width: 100cqw;\n"),
    array("css: the bubble's comment - no body that scrolls, no fade",
          "\n"
          . "/* The name; the dealer's lines - the card's grid - in a body that\n"
          . "   scrolls on a phone, with a fade at its foot while there is more below\n"
          . "   (slp_avalon.js); then the two buttons in a row of their own under the\n"
          . "   body, where they stay while it scrolls. slp_avalon wraps Avalon's fields\n"
          . "   in .avalon-bubble__info and SLP's two button spans in\n"
          . "   .avalon-bubble__actions (slp_js_options at 120), so SLP's own lines -\n"
          . "   fax, description, its hours, image, tags, all empty on Aura - stay out\n"
          . "   of the grid, and the spans keep the ids main.js and slp_avalon.js read.\n"
          . "   15 px text and a 20 px name at every width; a long address takes a\n"
          . "   third line 105 times of Aura's 313 at 320 px, 12 at 360, 7 at 375, 4\n"
          . "   at 390 and once from 414 up, where Part 4c's icons gave 13, 10, 1 and\n"
          . "   1 (2026-10-07). The frame - its colour, corners, width, shadow and\n"
          . "   tail - is the theme's. */\n"
          . ".slp_info_bubble #slp_bubble_name {\n",
          "\n"
          . "/* The name; the dealer's lines - the card's grid - in the body; then the\n"
          . "   two buttons in a row of their own under it. slp_avalon wraps Avalon's\n"
          . "   fields in .avalon-bubble__info and SLP's two button spans in\n"
          . "   .avalon-bubble__actions (slp_js_options at 120), so SLP's own lines -\n"
          . "   fax, description, its hours, image, tags, all empty on Aura - stay out\n"
          . "   of the grid, and the spans keep the ids main.js and slp_avalon.js read.\n"
          . "   15 px text and a 20 px name at every width. The frame - its colour,\n"
          . "   corners, width, shadow and tail - is the theme's.\n"
          . "\n"
          . "   Part 4e. A phone has no bubble (slp_avalon.js), so the body that\n"
          . "   scrolled there, its scrollbar and the fade at its foot are gone, and\n"
          . "   the count Part 4d took of long addresses in a phone's bubble no longer\n"
          . "   describes anything. On a larger screen the bubble grows as it always\n"
          . "   did, and Google's own box scrolls it where a map is too short. */\n"
          . ".slp_info_bubble #slp_bubble_name {\n"),
    array("css: the fade and the phone's cap give way to the chosen card's flash",
          "\n"
          . "/* The fade: the bubble's black over the body's last 28 px while there is\n"
          . "   more below. slp_avalon.js sets .is-more; at the end of the scroll, and\n"
          . "   wherever nothing scrolls, it is not drawn. */\n"
          . ".slp_info_bubble .sl_popup_contact_info::after {\n"
          . "  content: \"\";\n"
          . "  display: block;\n"
          . "  position: sticky;\n"
          . "  bottom: 0;\n"
          . "  height: 28px;\n"
          . "  margin-top: -28px;\n"
          . "  background: linear-gradient(transparent, var(--avalon-bubble-bg, #080808));\n"
          . "  pointer-events: none;\n"
          . "  visibility: hidden;\n"
          . "}\n"
          . "\n"
          . ".slp_info_bubble .sl_popup_contact_info.is-more::after {\n"
          . "  visibility: visible;\n"
          . "}\n"
          . "\n"
          . "/* On a phone - upright, or sideways and short - the body is at most\n"
          . "   250 px or 45% of the screen's height, and scrolls, with a thin visible\n"
          . "   scrollbar; the buttons stay under it. On a larger screen the bubble\n"
          . "   grows as it did. */\n"
          . "@media (max-width: 767px), (max-height: 500px) {\n"
          . "  .slp_info_bubble .sl_popup_contact_info {\n"
          . "    max-height: min(250px, 45vh);\n"
          . "    overflow-y: auto;\n"
          . "    overscroll-behavior: contain;\n"
          . "    scrollbar-width: thin;\n"
          . "    scrollbar-color: rgba(255, 255, 255, 0.45) transparent;\n"
          . "  }\n"
          . "\n"
          . "  .slp_info_bubble .sl_popup_contact_info::-webkit-scrollbar {\n"
          . "    width: 5px;\n"
          . "  }\n"
          . "\n"
          . "  .slp_info_bubble .sl_popup_contact_info::-webkit-scrollbar-thumb {\n"
          . "    background: rgba(255, 255, 255, 0.45);\n"
          . "    border-radius: 3px;\n"
          . "  }\n"
          . "\n"
          . "  .slp_info_bubble .sl_popup_contact_info::-webkit-scrollbar-track {\n"
          . "    background: transparent;\n"
          . "  }\n"
          . "}\n",
          "\n"
          . "/* ------------------------------------------ Part 4e: the chosen card */\n"
          . "\n"
          . "/* Part 4e. On a phone - upright, or sideways and short - a pin opens no\n"
          . "   bubble: slp_avalon.js brings the dealer's card into view, marked\n"
          . "   .active - the theme's ring - and puts .avalon-flash on it for a\n"
          . "   second, so the eye finds it: the card's own background reached from a\n"
          . "   lighter one, twice. The lighter one is a custom property, for a site\n"
          . "   on a light background to set. Where the visitor has asked for less\n"
          . "   motion, no flash: the ring alone. */\n"
          . "@keyframes avalon-flash {\n"
          . "  from {\n"
          . "    background-color: var(--avalon-flash, rgba(255, 255, 255, 0.34));\n"
          . "  }\n"
          . "}\n"
          . "\n"
          . "#map_sidebar .results_wrapper.avalon-flash {\n"
          . "  animation: avalon-flash 0.5s ease-out 2;\n"
          . "}\n"
          . "\n"
          . "@media (prefers-reduced-motion: reduce) {\n"
          . "  #map_sidebar .results_wrapper.avalon-flash {\n"
          . "    animation: none;\n"
          . "  }\n"
          . "}\n")
);
/* Part 4f's edits, [what it is, what Part 4e had, what Part 4f wrote], in
   the order build-v027-part4f.py makes them. */
$CSS_P4E = array('8e862ce347b040f329b77969c226165b', 26533);
$CSS_P4F_MARK = "/* ----------------------------------------- Part 4f: the numbered cards */";
$CSS_EDITS_4F = array(
    array("css: the header names Part 4f",
          " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4e.\n",
          " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4f.\n"),
    array("css: the header says what Part 4f changes",
          " * longer a size container. Open stays green.\n"
          . " */\n",
          " * longer a size container. Open stays green.\n"
          . " *\n"
          . " * PART 4f, after the first rule and at the end of the file: the owner's\n"
          . " * decisions of 2026-10-07 and 2026-10-08. .avalon-hours made the box its\n"
          . " * hidden \"Hours: \" is placed in - left to the page, the span sat where an\n"
          . " * unscrolled list would put it and stretched the page below its footer\n"
          . " * (the white bar on DEV, 2026-10-08). The numbered cards' disc, in the\n"
          . " * primary colour with a black number, as on the map's pins. Reset and\n"
          . " * the map's own + and -, in Google's look: slp_avalon.js turns Google's\n"
          . " * zoom off and puts these where it stood.\n"
          . " */\n"),
    array("css: .avalon-hours the box its hidden Hours: is placed in - the white bar under the footer",
          "  --avalon-hours-today: var(--e-global-color-primary, var(--primary-color, #e7167c));\n"
          . "}\n",
          "  --avalon-hours-today: var(--e-global-color-primary, var(--primary-color, #e7167c));\n"
          . "}\n"
          . "\n"
          . "/* Part 4f. The box the hidden \"Hours: \" (.avalon-hours__sr, in Part 4d's\n"
          . "   block) is placed in. Without it the nearest was the theme's #sl_div, and\n"
          . "   for a card below what the results box shows, the span sat outside the\n"
          . "   box's clipping, where the unscrolled list would put it - up to 211 px\n"
          . "   below the footer on DEV (1707 x 839, 2026-10-08), where nothing paints.\n"
          . "   A box of its own for every surface: a card, the bubble, a store page. */\n"
          . ".avalon-hours {\n"
          . "  position: relative;\n"
          . "}\n"),
    array("css: the numbered cards' disc and the map's buttons, at the end of the file",
          "    animation: none;\n"
          . "  }\n"
          . "}\n",
          "    animation: none;\n"
          . "  }\n"
          . "}\n"
          . "\n"
          . "/* ----------------------------------------- Part 4f: the numbered cards */\n"
          . "\n"
          . "/* Part 4f. Where the map's pins are numbered (slp_avalon.js, NUMBERS),\n"
          . "   each card's heading starts with the same number, in a disc of the\n"
          . "   site's primary colour with the number in black, bold - 4.80:1 on Aura's\n"
          . "   pink, as on the pins (the owner, 2026-10-08) - round, and a pill for\n"
          . "   two digits; before the name, on its first line. Screen readers hear\n"
          . "   \"Number 4, \" before the name: the word and the comma are in the\n"
          . "   markup, hidden on screen as the hidden \"Hours: \" is, and the disc is\n"
          . "   the box they are placed in, so that they cannot stretch the page\n"
          . "   either. Two custom properties for a site on another background.\n"
          . "\n"
          . "   The disc's middle on the middle of the name's capitals, and the line\n"
          . "   no taller for it: measured on DEV's cards in Chromium (2026-10-08),\n"
          . "   with the page's Figtree - the capitals' middle 13.6 px under the\n"
          . "   line's top at 24 px, 12 px at 20 px (1024 px and under), and the\n"
          . "   disc's middle 14 and 12; the heading 28.8 and 24 px high with the\n"
          . "   disc or without it. The gap after it is the card's column gap. */\n"
          . "#map_sidebar .results_wrapper .avalon-num {\n"
          . "  position: relative;\n"
          . "  display: inline-block;\n"
          . "  box-sizing: border-box;\n"
          . "  min-width: 26px;\n"
          . "  height: 26px;\n"
          . "  margin: 1px 10px -1px 0;\n"
          . "  padding: 0 7px;\n"
          . "  border-radius: 13px;\n"
          . "  background-color: var(--avalon-num-bg, var(--e-global-color-primary, var(--primary-color, #e7167c)));\n"
          . "  color: var(--avalon-num-text, #000);\n"
          . "  font-size: 14px;\n"
          . "  font-weight: 700;\n"
          . "  line-height: 26px;\n"
          . "  text-align: center;\n"
          . "  vertical-align: top;\n"
          . "}\n"
          . "\n"
          . "@media (max-width: 1024px) {\n"
          . "  #map_sidebar .results_wrapper .avalon-num {\n"
          . "    margin: -1px 8px -3px 0;\n"
          . "  }\n"
          . "}\n"
          . "\n"
          . "#map_sidebar .results_wrapper .avalon-num__sr {\n"
          . "  position: absolute;\n"
          . "  width: 1px;\n"
          . "  height: 1px;\n"
          . "  margin: -1px;\n"
          . "  padding: 0;\n"
          . "  overflow: hidden;\n"
          . "  clip: rect(0 0 0 0);\n"
          . "  clip-path: inset(50%);\n"
          . "  white-space: nowrap;\n"
          . "  border: 0;\n"
          . "}\n"
          . "\n"
          . "/* ----------------------------------------- Part 4f: the map's buttons */\n"
          . "\n"
          . "/* Part 4f. Reset, and + and -, where Google's zoom stood (slp_avalon.js,\n"
          . "   THE MAP'S BUTTONS), in Google's look as the probe read it on DEV on\n"
          . "   2026-10-08: white, 2 px corners, Google's shadow, 40 px buttons, a 1 px\n"
          . "   rule 30 px wide between + and -, Reset in the Map and Satellite\n"
          . "   buttons' type - Roboto 500, 18 px - and 10 px from the map's edges and\n"
          . "   from each other, as Google's own controls stand. Each button sets its\n"
          . "   colours for every state: the theme's button:hover and button:focus\n"
          . "   (0-1-1) paint any button pink, and these are 1-3-1 and up. A keyboard\n"
          . "   ring, black, inside the white button, so that it shows over any map;\n"
          . "   none for a pointer. + and - dimmed at either end of the zoom. */\n"
          . "#map .gm-style .avalon-mapctl {\n"
          . "  display: flex;\n"
          . "  align-items: flex-end;\n"
          . "  gap: 10px;\n"
          . "  margin: 10px;\n"
          . "}\n"
          . "\n"
          . "#map .gm-style .avalon-mapctl__zoom {\n"
          . "  display: flex;\n"
          . "  flex-direction: column;\n"
          . "  align-items: center;\n"
          . "  border-radius: 2px;\n"
          . "  background-color: #fff;\n"
          . "  box-shadow: rgba(0, 0, 0, 0.3) 0 1px 4px -1px;\n"
          . "}\n"
          . "\n"
          . "#map .gm-style .avalon-mapctl__rule {\n"
          . "  width: 30px;\n"
          . "  height: 1px;\n"
          . "  background-color: #e6e6e6;\n"
          . "}\n"
          . "\n"
          . "#map .gm-style .avalon-mapctl button {\n"
          . "  position: relative;\n"
          . "  display: block;\n"
          . "  box-sizing: border-box;\n"
          . "  width: 40px;\n"
          . "  height: 40px;\n"
          . "  margin: 0;\n"
          . "  padding: 0;\n"
          . "  border: 0;\n"
          . "  border-radius: 2px;\n"
          . "  background-color: #fff;\n"
          . "  box-shadow: none;\n"
          . "  color: #666;\n"
          . "  cursor: pointer;\n"
          . "  font-family: Roboto, Arial, sans-serif;\n"
          . "  font-size: 18px;\n"
          . "  font-weight: 500;\n"
          . "  line-height: 40px;\n"
          . "  letter-spacing: normal;\n"
          . "  text-align: center;\n"
          . "  text-transform: none;\n"
          . "  white-space: nowrap;\n"
          . "  user-select: none;\n"
          . "  transition: none;\n"
          . "}\n"
          . "\n"
          . "#map .gm-style .avalon-mapctl .avalon-mapctl__reset {\n"
          . "  width: auto;\n"
          . "  padding: 0 17px;\n"
          . "  box-shadow: rgba(0, 0, 0, 0.3) 0 1px 4px -1px;\n"
          . "  color: #565656;\n"
          . "}\n"
          . "\n"
          . "/* On a phone, Reset alone in the top right corner: a little narrower, so\n"
          . "   that it clears Map and Satellite on the narrowest maps. */\n"
          . "#map .gm-style .avalon-mapctl--corner .avalon-mapctl__reset {\n"
          . "  padding: 0 12px;\n"
          . "}\n"
          . "\n"
          . "#map .gm-style .avalon-mapctl button:focus {\n"
          . "  outline: none;\n"
          . "  background-color: #fff;\n"
          . "}\n"
          . "\n"
          . "#map .gm-style .avalon-mapctl button:hover {\n"
          . "  background-color: #fff;\n"
          . "  color: #333;\n"
          . "}\n"
          . "\n"
          . "#map .gm-style .avalon-mapctl .avalon-mapctl__reset:hover {\n"
          . "  background-color: #ebebeb;\n"
          . "  color: #000;\n"
          . "}\n"
          . "\n"
          . "#map .gm-style .avalon-mapctl button:active {\n"
          . "  color: #111;\n"
          . "}\n"
          . "\n"
          . "#map .gm-style .avalon-mapctl button[aria-disabled=\"true\"],\n"
          . "#map .gm-style .avalon-mapctl button[aria-disabled=\"true\"]:hover {\n"
          . "  background-color: #fff;\n"
          . "  color: #d1d1d1;\n"
          . "  cursor: default;\n"
          . "}\n"
          . "\n"
          . "#map .gm-style .avalon-mapctl button:focus-visible {\n"
          . "  outline: 2px solid #000;\n"
          . "  outline-offset: -4px;\n"
          . "}\n"
          . "\n"
          . "/* + and -, drawn: 16 px bars, 2 px thick, in the button's colour. */\n"
          . "#map .gm-style .avalon-mapctl__in::before,\n"
          . "#map .gm-style .avalon-mapctl__in::after,\n"
          . "#map .gm-style .avalon-mapctl__out::before {\n"
          . "  content: \"\";\n"
          . "  position: absolute;\n"
          . "  top: 50%;\n"
          . "  left: 50%;\n"
          . "  width: 16px;\n"
          . "  height: 2px;\n"
          . "  margin: -1px 0 0 -8px;\n"
          . "  background-color: currentColor;\n"
          . "}\n"
          . "\n"
          . "#map .gm-style .avalon-mapctl__in::after {\n"
          . "  transform: rotate(90deg);\n"
          . "}\n")
);
/* Part 4c's icon block, the end of its file, which Part 4d's block replaced. */
$CSS_ICONS_PIN = array('cda8466115c1854b525e640346f43beb', 1932);
$CSS_ICONS = "/* Part 4c. On a phone, Font Awesome icons in place of the five labels, the\n"
           . "   owner's request of 2026-10-05: map-marker-alt for Address:, phone-alt\n"
           . "   for Phone:, envelope for Email:, with route for Distance: and clock for\n"
           . "   Hours:. Only under .avalon-fa, which slp_avalon.js puts on <html> once\n"
           . "   the page's own Font Awesome 5 (Elementor's, solid) has loaded; without\n"
           . "   it the words stay. The word stays in the page at font-size 0, for screen\n"
           . "   readers; the icon is generated content with empty alternative text\n"
           . "   (/ \"\"), and a browser that does not know that syntax drops the second\n"
           . "   content declaration and keeps the first. */\n"
           . "@media (max-width: 767px) {\n"
           . "  .avalon-fa .avalon-label--distance,\n"
           . "  .avalon-fa .avalon-label--address,\n"
           . "  .avalon-fa .avalon-label--phone,\n"
           . "  .avalon-fa .avalon-label--email,\n"
           . "  .avalon-fa .avalon-label--hours {\n"
           . "    display: inline-block;\n"
           . "    width: 24px;\n"
           . "    font-size: 0;\n"
           . "    vertical-align: baseline;\n"
           . "  }\n"
           . "\n"
           . "  .avalon-fa .avalon-label--distance::before,\n"
           . "  .avalon-fa .avalon-label--address::before,\n"
           . "  .avalon-fa .avalon-label--phone::before,\n"
           . "  .avalon-fa .avalon-label--email::before,\n"
           . "  .avalon-fa .avalon-label--hours::before {\n"
           . "    display: inline-block;\n"
           . "    width: 24px;\n"
           . "    color: var(--e-global-color-primary, var(--primary-color, #e7167c));\n"
           . "    font: 900 15px/1 \"Font Awesome 5 Free\";\n"
           . "    text-align: center;\n"
           . "    -webkit-font-smoothing: antialiased;\n"
           . "    -moz-osx-font-smoothing: grayscale;\n"
           . "  }\n"
           . "\n"
           . "  .avalon-fa .avalon-label--distance::before {\n"
           . "    content: \"\\f4d7\";\n"
           . "    content: \"\\f4d7\" / \"\";\n"
           . "  }\n"
           . "\n"
           . "  .avalon-fa .avalon-label--address::before {\n"
           . "    content: \"\\f3c5\";\n"
           . "    content: \"\\f3c5\" / \"\";\n"
           . "  }\n"
           . "\n"
           . "  .avalon-fa .avalon-label--phone::before {\n"
           . "    content: \"\\f879\";\n"
           . "    content: \"\\f879\" / \"\";\n"
           . "  }\n"
           . "\n"
           . "  .avalon-fa .avalon-label--email::before {\n"
           . "    content: \"\\f0e0\";\n"
           . "    content: \"\\f0e0\" / \"\";\n"
           . "  }\n"
           . "\n"
           . "  .avalon-fa .avalon-label--hours::before {\n"
           . "    content: \"\\f017\";\n"
           . "    content: \"\\f017\" / \"\";\n"
           . "  }\n"
           . "}\n";
$CSS_EDITS = array(
    array("css: the header names Part 4d",
          " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4c.\n",
          " * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4d.\n"),
    array("css: the header's card hover is Part 4d's grey",
          " * card rest = rgba(255,255,255,.1) over #000; card hover = rgba(222,139,13,.2)\n"
          . " * over #000. On a card the pink is under 4.5 - the owner's choice; the word\n"
          . " * is bold. #2a2a2a, a worst case for the store page's background image,\n",
          " * card rest = rgba(255,255,255,.1) over #000; card hover, and the chosen\n"
          . " * card, = rgba(255,255,255,.12) over #000 from Part 4d (the warm\n"
          . " * rgba(222,139,13,.2) before it measured the same to 0.01). On a card the\n"
          . " * pink is under 4.5 - the owner's choice; the word is bold, and Part 4d's\n"
          . " * TODAY, 11 px, is the same pink at the same 3.98 and 3.77 (4.58 in the\n"
          . " * bubble). #2a2a2a, a worst case for the store page's background image,\n"),
    array("css: the header says what Part 4c and Part 4d add",
          " * PART 4c, at the end of the file. The address on two lines on the cards\n"
          . " * and in the bubble; on a phone, 15 px text, 20 px names and the labels as\n"
          . " * Font Awesome icons; the map's own images - the Street View Pegman - at\n"
          . " * their own sizes; a keyboard focus ring on the bubble's buttons; the\n"
          . " * Hours: line balanced where it has to wrap.\n"
          . " */\n",
          " * PART 4c, after Part 4b's bubble rules. The address on two lines on the\n"
          . " * cards and in the bubble; the map's own images - the Street View Pegman -\n"
          . " * at their own sizes; a keyboard focus ring on the bubble's buttons; the\n"
          . " * Hours: line balanced where it has to wrap; under 375 px a 14 px email.\n"
          . " *\n"
          . " * PART 4d, at the end of the file. The approved find-a-dealer design: a\n"
          . " * card's lines in one grid, its buttons side by side, with the bubble's\n"
          . " * keyboard ring; the bubble in three parts - the name, a body that scrolls\n"
          . " * on a phone, the buttons; Hours: in the label column; the opened week\n"
          . " * with a rule, a dot and TODAY; 15 px text and 20 px names at 1024 px and\n"
          . " * under on the cards, at every width in the bubble. Words for the labels\n"
          . " * on every screen: Part 4c's Font Awesome icons are gone (the owner,\n"
          . " * 2026-10-07).\n"
          . " */\n"),
    array("css: TODAY's colour, beside the status colours",
          "  --avalon-hours-soon: #fcad70;\n"
          . "}\n",
          "  --avalon-hours-soon: #fcad70;\n"
          . "  --avalon-hours-today: var(--e-global-color-primary, var(--primary-color, #e7167c));\n"
          . "}\n"),
    array("css: the caret is an element, held to the last word",
          "/* The caret is drawn, not typed: a character in generated content becomes\n"
          . "   part of the summary's spoken name in some screen readers. */\n"
          . ".avalon-hours .avalon-hours__summary::after {\n"
          . "  content: \"\";\n"
          . "  display: inline-block;\n"
          . "  width: 0.4em;\n"
          . "  height: 0.4em;\n"
          . "  margin: 0 0 0.2em 0.5em;\n"
          . "  border: solid currentColor;\n"
          . "  border-width: 0 2px 2px 0;\n"
          . "  vertical-align: middle;\n"
          . "  transform: rotate(45deg);\n"
          . "  transition: transform 0.2s;\n"
          . "}\n"
          . "\n"
          . ".avalon-hours .avalon-hours__narrow[open] > .avalon-hours__summary::after {\n"
          . "  margin-bottom: -0.1em;\n"
          . "  transform: rotate(-135deg);\n"
          . "}\n",
          "/* The caret is drawn, not typed: a character in generated content becomes\n"
          . "   part of the summary's spoken name in some screen readers. From Part 4d\n"
          . "   an element of its own, the last thing in the status, hidden from screen\n"
          . "   readers; avalon-hours.js holds it on one line with the status's last\n"
          . "   word, so it never starts a line alone. 7 px, as the design draws it.\n"
          . "   Only a summary shows it: the store page's wide status line has none. */\n"
          . ".avalon-hours .avalon-hours__caret {\n"
          . "  display: none;\n"
          . "}\n"
          . "\n"
          . ".avalon-hours .avalon-hours__summary .avalon-hours__caret {\n"
          . "  display: inline-block;\n"
          . "  width: 7px;\n"
          . "  height: 7px;\n"
          . "  margin: 0 2px 2px 8px;\n"
          . "  border: solid currentColor;\n"
          . "  border-width: 0 2px 2px 0;\n"
          . "  transform: rotate(45deg);\n"
          . "  transition: transform 0.2s;\n"
          . "}\n"
          . "\n"
          . ".avalon-hours .avalon-hours__narrow[open] > .avalon-hours__summary .avalon-hours__caret {\n"
          . "  margin-bottom: -2px;\n"
          . "  transform: rotate(225deg);\n"
          . "}\n"
          . "\n"
          . ".avalon-hours .avalon-hours__summary .avalon-hours__nowrap {\n"
          . "  white-space: nowrap;\n"
          . "}\n"),
    array("css: reduced motion stills the element caret",
          "@media (prefers-reduced-motion: reduce) {\n"
          . "  .avalon-hours .avalon-hours__summary::after {\n"
          . "    transition: none;\n"
          . "  }\n"
          . "}\n",
          "@media (prefers-reduced-motion: reduce) {\n"
          . "  .avalon-hours .avalon-hours__summary .avalon-hours__caret {\n"
          . "    transition: none;\n"
          . "  }\n"
          . "}\n"),
    array("css: Part 4c's phone sizes move into Part 4d's block",
          "/* ------------------------------------------ Part 4c: phones, portrait */\n"
          . "\n"
          . "/* At Elementor's mobile breakpoint the card's and the bubble's text is\n"
          . "   15 px and the dealer's name 20 px. slp_avalon.js widens the bubble there\n"
          . "   to the map's width less 24 px, and at 15 px every one of Aura's 313\n"
          . "   addresses fits its two lines from 320 px up, on the cards and in the\n"
          . "   bubble, and every one of its 97 emails its one line from 375 px up\n"
          . "   (measured 2026-10-05, icons and words). The theme sets 16 px and 24 px\n"
          . "   at 0-4-0 and 1-0-0; these are 1-2-0 and 1-1-0.\n"
          . "\n"
          . "   767 px is Elementor's default mobile breakpoint, and Aura's: Part 4's\n"
          . "   hours fold follows a site that moves it (avalon_hours_breakpoint());\n"
          . "   Part 4c's two 767 px blocks here, and slp_avalon.js's, do not. Check the\n"
          . "   breakpoint before Tahoe or Avalon take Part 4c. */\n"
          . "@media (max-width: 767px) {\n"
          . "  #map_sidebar .results_wrapper .location_distance,\n"
          . "  #map_sidebar .results_wrapper .sl_contact__info,\n"
          . "  .slp_info_bubble .sl_popup_contact_info {\n"
          . "    font-size: 15px;\n"
          . "    line-height: 1.5;\n"
          . "  }\n"
          . "\n"
          . "  #map_sidebar .results_wrapper .store_locator_name,\n"
          . "  .slp_info_bubble #slp_bubble_name {\n"
          . "    font-size: 20px;\n"
          . "    line-height: 1.2;\n"
          . "  }\n"
          . "}\n"
          . "\n"
          . "/* Part 4c. Under 375 px the email address in the bubble is 14 px; its\n"
          . "   label, icon or word, stays as the others. At 15 px on a 360 px phone\n"
          . "   one of Aura's 97 emails wraps with the icons and five with the words;\n"
          . "   at 14 px none do. At 320 px six still wrap, and break inside the\n"
          . "   address (Part 4b) rather than being cut. Measured 2026-10-05. 1-2-1:\n"
          . "   the link sets its own size, under the 15 px it would inherit. */\n",
          "/* ------------------------------------------ Part 4c: phones, portrait */\n"
          . "\n"
          . "/* Part 4c's 15 px text and 20 px names are in Part 4d's block, at the end\n"
          . "   of the file: at 1024 px and under on the cards, at every width in the\n"
          . "   bubble.\n"
          . "\n"
          . "   Part 4c. Under 375 px the email address in the bubble is 14 px; its\n"
          . "   label stays as the others. Measured again with Part 4d's labels in a\n"
          . "   column of their own, at the bubble's width on each phone (2026-10-07):\n"
          . "   none of Aura's 97 emails wraps, from 320 px up; one that did would\n"
          . "   break inside the address (Part 4b) rather than be cut. 1-2-1: the\n"
          . "   link sets its own size, under the 15 px it would inherit. */\n")
);

echo "\n  IDENTITY - the stylesheet\n";
/* Part 4f's edits out, the last made first: Part 4e's stylesheet. */
$css4e = $css;
$f4cOk = true;
for ($i = count($CSS_EDITS_4F) - 1; $i >= 0; $i--) {
    $e = $CSS_EDITS_4F[$i];
    if (substr_count($css4e, $e[2]) !== 1) { $f4cOk = false; continue; }
    $css4e = implode($e[1], explode($e[2], $css4e, 2));
}
check($f4cOk && md5($css4e) === $CSS_P4E[0] && strlen($css4e) === $CSS_P4E[1],
      sprintf("Part 4f's %d edits, each present once, reversed: the stylesheet IS Part 4e's (8e862ce3, 26,533 bytes)", count($CSS_EDITS_4F)));
/* Part 4e's edits out, the last made first: Part 4d's stylesheet. */
$cssD = $css4e;
$e4Ok = true;
for ($i = count($CSS_EDITS_4E) - 1; $i >= 0; $i--) {
    $e = $CSS_EDITS_4E[$i];
    if (substr_count($cssD, $e[2]) !== 1) { $e4Ok = false; continue; }
    $cssD = implode($e[1], explode($e[2], $cssD, 2));
}
check($e4Ok, sprintf("each of Part 4e's %d edits is present exactly once", count($CSS_EDITS_4E)));
check($e4Ok && md5($cssD) === $CSS_P4D[0] && strlen($cssD) === $CSS_P4D[1],
      "Part 4e's edits reversed, the stylesheet IS Part 4d's (ffe117b6, 26,810 bytes)");
/* From Part 4d's, as suite-v035 went on. */
$cbD = strpos($cssD, "\n" . $CSS_BLOCK_START);
$blockD = ($cbD !== false && substr_count($cssD, $CSS_BLOCK_START) === 1) ? substr($cssD, $cbD + 1) : '';
check($blockD !== '' && md5($blockD) === $CSS_BLOCK_PIN[0] && strlen($blockD) === $CSS_BLOCK_PIN[1],
      sprintf("  ... in which the Part 4d block ends the file, once, as suite-v035 knew it (%s, %d bytes)",
              $CSS_BLOCK_PIN[0], $CSS_BLOCK_PIN[1]));
/* The block as shipped - Part 4d's with Part 4e's edits in it, which Part
   4f leaves as it is - is what the rules below are read from: up to Part
   4f's own block, which follows it. */
$cb = strpos($css4e, "\n" . $CSS_BLOCK_START);
$cssBlock = ($cb !== false && substr_count($css4e, $CSS_BLOCK_START) === 1) ? substr($css4e, $cb + 1) : '';
check($cssBlock !== '' && md5($cssBlock) === $CSS_BLOCK_4E[0] && strlen($cssBlock) === $CSS_BLOCK_4E[1],
      sprintf("the block as Part 4e shipped it - Part 4f's own taken off the end - ends that file, once, and is the one this suite was written against (%s, %d bytes)",
              $CSS_BLOCK_4E[0], $CSS_BLOCK_4E[1]));
$crev = $blockD !== '' ? substr($cssD, 0, $cbD + 1) . $CSS_ICONS : $cssD;
$cssOk = md5($CSS_ICONS) === $CSS_ICONS_PIN[0] && strlen($CSS_ICONS) === $CSS_ICONS_PIN[1];
for ($i = count($CSS_EDITS) - 1; $i >= 0; $i--) {
    $e = $CSS_EDITS[$i];
    if (substr_count($crev, $e[2]) !== 1) { $cssOk = false; continue; }
    $crev = implode($e[1], explode($e[2], $crev, 2));
}
check($cssOk && md5($crev) === $CSS_P4C[0] && strlen($crev) === $CSS_P4C[1],
      sprintf("  ... and its block out, Part 4c's icon block back, Part 4d's %d edits reversed: the stylesheet IS Part 4c's (d2426903, 15,316 bytes)", count($CSS_EDITS)));
check(strpos($css, "\r") === false && preg_match('/[^\x00-\x7f]/', $css) === 0, 'LF and ASCII');

/* Specificity, as the cascade counts it: ids, then classes, attributes and
   pseudo-classes, then elements and pseudo-elements. For the plain
   selectors this stylesheet and the theme write. */
function spec($sel)
{
    $s = preg_replace('/::?(before|after)\b/', ' x', $sel);
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
/* What the at-rules with this exact prelude hold, every one of them: the
   text between their braces, joined; '' when there is none. */
function at_rule($css, $prelude)
{
    $css = preg_replace('/\/\*.*?\*\//s', '', $css);
    $all = '';
    for ($p = strpos($css, $prelude . ' {'); $p !== false; $p = strpos($css, $prelude . ' {', $p + 1)) {
        $i = $p + strlen($prelude) + 2;
        $d = 1;
        for ($j = $i; $j < strlen($css); $j++) {
            if ($css[$j] === '{') { $d++; }
            if ($css[$j] === '}') { $d--; if ($d === 0) { $all .= substr($css, $i, $j - $i) . "\n"; break; } }
        }
    }
    return $all;
}
function has_decl($css, $sel, $prop, $val)
{
    foreach (rules_of($css, $sel) as $r) {
        if (preg_match('/(^|;)\s*' . preg_quote($prop, '/') . '\s*:\s*' . preg_quote($val, '/') . '\s*;/', $r) === 1) { return true; }
    }
    return false;
}
/* Declared at the block's top level, outside every at-rule - Part 4e's
   keyframes among them. */
$top = preg_replace('/@(media|container|keyframes)[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/', '', preg_replace('/\/\*.*?\*\//s', '', $cssBlock));
$D = function ($sel, $decls, $in = null) use ($top) {
    $where = $in === null ? $top : $in;
    foreach ($decls as $p => $v) { if (! has_decl($where, $sel, $p, $v)) { return false; } }
    return true;
};
$CARD_WEEK = '#map_sidebar .avalon-hours--card .avalon-hours__week';
$BUB_WEEK  = '.slp_info_bubble .avalon-hours--card .avalon-hours__week';
$m1024  = at_rule($cssBlock, '@media (max-width: 1024px)');
$m769   = at_rule($cssBlock, '@media (min-width: 769px) and (max-width: 1024px)');
$m576   = at_rule($cssBlock, '@media (min-width: 576px) and (max-width: 768px)');
$c320   = at_rule($cssBlock, '@container (max-width: 320px)');
$c250   = at_rule($cssBlock, '@container (max-width: 250px)');
$mphone = at_rule($cssBlock, '@media (max-width: 767px), (max-height: 500px)');
$mcalm  = at_rule($cssBlock, '@media (prefers-reduced-motion: reduce)');
$kflash = at_rule($cssBlock, '@keyframes avalon-flash');

echo "\n  THE STYLESHEET - the design, rule by rule\n";
check($D('#map_sidebar .results_wrapper .results_entry', array('display' => 'grid', 'grid-template-columns' => 'auto minmax(0, 1fr)',
                                                               'column-gap' => '10px', 'row-gap' => '4px', 'align-items' => 'baseline'))
      && $D('#map_sidebar .results_wrapper .results_entry', array('column-gap' => '8px', 'row-gap' => '3px'), $m1024),
      "the card a two-column grid - the labels' column, the values' - on the text's baseline: 10 and 4 px apart, 8 and 3 at 1024 px and under");
$contents = array('#map_sidebar .results_entry > [id^="slp_left_cell_"]', '#map_sidebar .results_entry > .sl_contact__info',
                  '#map_sidebar .results_entry .location_distance', '#map_sidebar .results_entry .slp_result_street',
                  '#map_sidebar .results_entry .slp_result_phone');
$cOk = true;
foreach ($contents as $s) { if (! $D($s, array('display' => 'contents'))) { $cOk = false; } }
check($cOk && beats('#map_sidebar .results_entry .slp_result_street', '.avalon-address')
      && beats('#map_sidebar .results_entry .slp_result_phone', '.store_locator_plus .slp_results_container .results_wrapper .sl_contact__info .slp_result_phone'),
      "SLP's two cells and the three field spans step aside (display: contents), over Part 4c's flex address and the theme's block phone (0-5-0)");
check($D('#map_sidebar .results_entry .store_locator_name', array('grid-column' => '1 / -1', 'margin' => '0 0 8px', 'font-size' => '24px', 'line-height' => '1.2'))
      && $D('#map_sidebar .results_entry .store_locator_name', array('margin-bottom' => '5px', 'font-size' => '20px'), $m1024)
      && beats('#map_sidebar .results_entry .store_locator_name', '.store_locator_plus .slp_results_container .results_wrapper .store_locator_name'),
      "the name across both columns: 24 px, 12 px above the lines with the gap; 20 px and 8 at 1024 px and under - over the theme's 0-4-0");
check($D('#map_sidebar .results_entry > [id^="slp_right_cell_"]', array('grid-column' => '1 / -1', 'display' => 'flex', 'flex-wrap' => 'wrap',
                                                                        'gap' => '8px', 'margin-top' => '12px'))
      && $D('#map_sidebar .results_entry > [id^="slp_right_cell_"]', array('margin-top' => '11px'), $m1024)
      && $D('#map_sidebar .results_entry .slp_result_contact.slp_result_directions', array('display' => 'flex', 'flex' => '1 1 0', 'min-width' => '0'))
      && $D('#map_sidebar .results_entry .slp_result_contact', array('flex' => '1 1 100%', 'margin' => '0'))
      && $D('#map_sidebar .results_entry > [id^="slp_right_cell_"]', array('max-width' => '456px'), $m576)
      && beats('#map_sidebar .results_entry .slp_result_contact', '.store_locator_plus .slp_results_container .results_wrapper .slp_result_contact + .slp_result_contact'),
      'the two buttons side by side at equal widths, 8 px apart, 16 px under the lines (14 at 1024 and under); held to 456 px on a stacked tablet; SLP\'s own hours span a row of its own');
$code_css = preg_replace('/\/\*.*?\*\//s', '', $cssBlock);
check($D('#map_sidebar .results_wrapper .slp_result_contact a:focus-visible', array('outline' => '2px solid #fff', 'outline-offset' => '2px'))
      && beats('#map_sidebar .results_wrapper .slp_result_contact a:focus-visible', '.store_locator_plus .slp_results_container .results_wrapper .slp_result_contact a:focus')
      && preg_match('/:hover|:focus(?!-visible)|:active/', $code_css) === 0,
      "the card's buttons get the bubble's white ring on keyboard focus alone, over the theme's outline: none (0-5-1); nothing here on :hover, :focus or :active");
check($D('#map_sidebar .results_entry .location_distance', array('font-size' => '15px', 'line-height' => '1.5'), $m1024)
      && $D('#map_sidebar .results_entry > .sl_contact__info', array('font-size' => '15px', 'line-height' => '1.5'), $m1024)
      && beats('#map_sidebar .results_entry > .sl_contact__info', '.store_locator_plus .slp_results_container .results_wrapper .sl_contact__info'),
      "the cards' text 15 px at 1024 px and under, over the theme's 16 px (0-4-0)");
check($D('.avalon-label--hours', array('cursor' => 'pointer'))
      && $D('.avalon-hours .avalon-hours__sr', array('position' => 'absolute', 'width' => '1px', 'height' => '1px', 'overflow' => 'hidden',
                                                     'clip-path' => 'inset(50%)', 'white-space' => 'nowrap')),
      'Hours: in the label column takes a click; the summary\'s own "Hours: " is for screen readers alone');
check($D($CARD_WEEK, array('width' => 'calc(100% + 15px)', 'margin' => '8px 0 0 -15px', 'padding-top' => '8px',
                           'border-top' => '1px solid var(--avalon-hours-rule, rgba(255, 255, 255, 0.15))'))
      && $D($BUB_WEEK, array('width' => 'calc(100% + 15px)', 'margin' => '8px 0 0 -15px'))
      && $D($CARD_WEEK . ' tbody > tr > th', array('width' => '1%', 'padding-right' => '20px', 'padding-left' => '15px', 'white-space' => 'nowrap'))
      && $D($BUB_WEEK . ' tbody > tr > th', array('padding-right' => '16px'))
      && $D($CARD_WEEK . ' tbody > tr > th', array('padding-right' => '16px'), $m1024)
      && beats($CARD_WEEK . ' tbody > tr > th', '.avalon-hours .avalon-hours__week tbody > tr > th')
      && beats($BUB_WEEK . ' tbody > tr > th', '.avalon-hours .avalon-hours__week tbody > tr > th'),
      "the opened week: a rule across, 8 px each side of it, pulled 15 px left so its dot sits in the gap; 20 px between day and hours, 16 in the bubble and at 1024 and under - over Part 4's week (0-2-3)");
check($D($CARD_WEEK . ' tbody > tr.is-today > th', array('padding-left' => '0', 'font-weight' => '800'))
      && $D($CARD_WEEK . ' tbody > tr.is-today > td', array('font-weight' => '800'))
      && $D($CARD_WEEK . ' tbody > tr.is-today > th::before', array('width' => '7px', 'height' => '7px', 'margin-right' => '8px',
                                                                      'border-radius' => '50%', 'background' => 'var(--avalon-hours-today)'))
      && $D($CARD_WEEK . ' tbody > tr.is-today > td::after', array('content' => '"TODAY" / ""', 'color' => 'var(--avalon-hours-today)',
                                                                     'font-size' => '11px', 'font-weight' => '600', 'line-height' => '1', 'letter-spacing' => '1px'))
      && $D('.avalon-hours', array('--avalon-hours-today' => 'var(--e-global-color-primary, var(--primary-color, #e7167c))'), preg_replace('/\/\*.*?\*\//s', '', $css)),
      "today: 800, a 7 px dot in the primary colour before its name, TODAY - 11 px, 600, 1 px tracking - with empty alternative text");
check($D($CARD_WEEK . ' tbody > tr.is-today > td::after', array('display' => 'block', 'margin' => '3px 0 4px'))
      && $D($BUB_WEEK . ' tbody > tr.is-today > td::after', array('display' => 'block', 'margin' => '3px 0 4px'))
      && ! $D($CARD_WEEK . ' tbody > tr.is-today > td::after', array('display' => 'inline-block'))
      && strpos($code_css, 'margin-left: 12px') === false
      && count(rules_of($cssBlock, $CARD_WEEK . ' tbody > tr.is-today > td::after')) === 1
      && count(rules_of($cssBlock, $BUB_WEEK . ' tbody > tr.is-today > td::after')) === 1,
      "Part 4e: TODAY a block under its hours on the cards and in the bubble, at every width - one rule, no query - 3 px over it and 4 px under, nowhere beside them");
check($D('#map_sidebar .avalon-hours--card .avalon-hours__time', array('white-space' => 'nowrap'))
      && $D('.slp_info_bubble .avalon-hours--card .avalon-hours__time', array('white-space' => 'nowrap'))
      && count(rules_of($css, '.avalon-hours .avalon-hours__time')) === 0,
      "a day's hours never break on a card or in the bubble; on the store page they may, as before");
check($D($CARD_WEEK, array('width' => 'calc(100% + 20px)', 'margin-left' => '-20px'), $m769),
      'a phone held sideways, a tablet: the week 20 px left, as approved');
check($D('#map_sidebar .results_wrapper .results_entry', array('container-type' => 'inline-size'))
      && $D($CARD_WEEK, array('width' => '100cqw', 'margin-left' => 'calc(100% - 100cqw)'), $c250)
      && strpos($cssBlock, '@container (max-width: 250px)') > strpos($cssBlock, '@media (min-width: 769px) and (max-width: 1024px)'),
      "narrow cards, by a container query: below 250 px of content the week the whole width - after the sideways rule, so it wins");
check(! $D('.slp_info_bubble .avalon-bubble__info', array('container-type' => 'inline-size'))
      && substr_count($code_css, 'container-type') === 1 && substr_count($code_css, '@container') === 1
      && $c320 === '' && ! $D($BUB_WEEK, array('width' => '100cqw'), $c250),
      "Part 4e: the bubble is no size container - sized by its content, it cannot be one - and no query names it; the 320 px rule is gone");
check(has_decl(preg_replace('/\/\*.*?\*\//s', '', $css), '.avalon-hours .avalon-hours__caret', 'display', 'none')
      && has_decl($css, '.avalon-hours .avalon-hours__summary .avalon-hours__caret', 'width', '7px')
      && has_decl($css, '.avalon-hours .avalon-hours__summary .avalon-hours__caret', 'border-width', '0 2px 2px 0')
      && has_decl($css, '.avalon-hours .avalon-hours__summary .avalon-hours__caret', 'transform', 'rotate(45deg)')
      && has_decl($css, '.avalon-hours .avalon-hours__narrow[open] > .avalon-hours__summary .avalon-hours__caret', 'transform', 'rotate(225deg)')
      && has_decl($css, '.avalon-hours .avalon-hours__summary .avalon-hours__nowrap', 'white-space', 'nowrap')
      && strpos($css, 'avalon-hours__summary::after') === false
      && preg_match('/@media \(prefers-reduced-motion: reduce\) \{\s*\.avalon-hours \.avalon-hours__summary \.avalon-hours__caret \{\s*transition: none;/', $css) === 1,
      "the caret an element, 7 px as drawn, shown in a summary only, turned on opening, held to the last word; no generated caret left; still under reduced motion");
check($D('.slp_info_bubble #slp_bubble_name', array('display' => 'block', 'margin' => '0', 'padding' => '16px 16px 6px', 'font-size' => '20px', 'line-height' => '1.2'))
      && $D('.slp_info_bubble .sl_popup_contact_info', array('margin' => '0', 'padding' => '0 12px 16px 16px', 'font-size' => '15px', 'line-height' => '1.5'))
      && $D('.slp_info_bubble .avalon-bubble__info', array('display' => 'grid', 'grid-template-columns' => 'auto minmax(0, 1fr)', 'column-gap' => '8px', 'row-gap' => '3px'))
      && $D('.slp_info_bubble .avalon-bubble__info > #slp_bubble_phone', array('display' => 'contents'))
      && $D('.slp_info_bubble .avalon-bubble__actions', array('display' => 'flex', 'gap' => '8px', 'padding' => '12px 16px 16px',
                                                              'border-top' => '1px solid var(--avalon-bubble-rule, rgba(255, 255, 255, 0.12))'))
      && $D('.slp_info_bubble .avalon-bubble__actions > #slp_bubble_directions', array('display' => 'flex', 'flex' => '1 1 0', 'margin' => '0'))
      && $D('.slp_info_bubble .avalon-bubble__actions br', array('display' => 'none'))
      && beats('.slp_info_bubble #slp_bubble_name', '#slp_bubble_name')
      && beats('.slp_info_bubble .avalon-bubble__info > #slp_bubble_phone', '.sl_popup_contact_info #slp_bubble_phone')
      && beats('.slp_info_bubble .avalon-bubble__actions > #slp_bubble_directions', '#slp_bubble_directions'),
      "the bubble in three parts: the name, 20 px; the body, the card's grid at 15 px; the buttons' row under a rule - over the theme's bubble rules");
check(count(rules_of($css, '.slp_info_bubble .sl_popup_contact_info::after')) === 0
      && count(rules_of($css, '.slp_info_bubble .sl_popup_contact_info.is-more::after')) === 0
      && strpos($css, 'is-more') === false && $mphone === ''
      && preg_match('/max-height|overflow-y|overscroll-behavior|scrollbar|position:\s*sticky|linear-gradient/', $code_css) === 0
      && count(rules_of($cssBlock, '.slp_info_bubble .sl_popup_contact_info')) === 1,
      "Part 4e: the shade is gone - no fade at the foot of the bubble's body, no cap, no scroller, no scrollbar of ours; one rule for the body, its type and padding");
check($kflash !== '' && preg_match('/^\s*from\s*\{\s*background-color:\s*var\(--avalon-flash, rgba\(255, 255, 255, 0\.34\)\);\s*\}\s*$/', $kflash) === 1
      && $D('#map_sidebar .results_wrapper.avalon-flash', array('animation' => 'avalon-flash 0.5s ease-out 2'))
      && has_decl($mcalm, '#map_sidebar .results_wrapper.avalon-flash', 'animation', 'none')
      && strpos($cssBlock, '@media (prefers-reduced-motion: reduce)') > strpos($cssBlock, 'animation: avalon-flash 0.5s ease-out 2;')
      && substr_count($code_css, 'avalon-flash') === 5 && preg_match('/\bto\s*\{|100%\s*\{/', $kflash) === 0,
      "Part 4e: the chosen card's flash - from a lighter background to the card's own, whatever that is, twice in a second; a custom property for the colour; none where less motion is asked for");
check(preg_match('/!important/', $code_css) === 0 && strpos($css, 'avalon-fa') === false
      && preg_match('/@media \(max-width: 374px\)/', $css) === 0
      && count(rules_of($css, '.slp_info_bubble #slp_bubble_email a.avalon-email')) === 0
      && preg_match('/@media \(max-width: 767px\) \{\s*#map_sidebar \.results_wrapper \.location_distance/', $css) === 0,
      "no !important; no icon rule left; Part 4c's 14 px email under 375 px gone with the phone's bubble (Part 4e), its 767 px type sizes in this block since Part 4d");

/* ------------------------------------------------------------------ */
/* THE STYLESHEET - Part 4f.                                           */
/* ------------------------------------------------------------------ */

echo "\n  THE STYLESHEET - Part 4f: the white bar, the numbered cards, the map's buttons\n";
$CSS_P4F_BLOCK = array('e2246be3e511b009babe12d3a6a7ce50', 5124);
$p4 = strpos($css, "\n" . $CSS_P4F_MARK);
$blk4f = ($p4 !== false && substr_count($css, $CSS_P4F_MARK) === 1) ? substr($css, $p4 + 1) : '';
check($blk4f !== '' && $p4 > strpos($css, '/* ------------------------------------------ Part 4e: the chosen card */')
      && md5($blk4f) === $CSS_P4F_BLOCK[0] && strlen($blk4f) === $CSS_P4F_BLOCK[1],
      sprintf("the Part 4f block ends the file, once, after Part 4e's, and is the one this suite was written against (%s, %d bytes)",
              $CSS_P4F_BLOCK[0], $CSS_P4F_BLOCK[1]));
$bare_all = preg_replace('/\/\*.*?\*\//s', '', $css);
$top_all  = preg_replace('/@(media|container|keyframes)[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/', '', $bare_all);
$top4f    = preg_replace('/@(media|container|keyframes)[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/', '', preg_replace('/\/\*.*?\*\//s', '', $blk4f));
$F = function ($sel, $decls, $in = null) use ($top4f) {
    $where = $in === null ? $top4f : $in;
    foreach ($decls as $p => $v) { if (! has_decl($where, $sel, $p, $v)) { return false; } }
    return true;
};
$m4f1024 = at_rule($blk4f, '@media (max-width: 1024px)');
/* What a declaration block says, property by property, comments and order
   aside. */
$decls_of = function ($block) {
    $out = array();
    foreach (explode(';', $block) as $d) {
        $kv = explode(':', $d, 2);
        if (count($kv) === 2) { $out[trim($kv[0])] = trim(preg_replace('/\s+/', ' ', $kv[1])); }
    }
    ksort($out);
    return $out;
};

check(has_decl($top_all, '.avalon-hours', 'position', 'relative') && count(rules_of($css, '.avalon-hours')) === 2
      && strpos($css, ".avalon-hours {\n  position: relative;\n}\n") < strpos($css, '.avalon-hours .avalon-hours__sr {')
      && has_decl($top_all, '.avalon-hours .avalon-hours__sr', 'position', 'absolute'),
      "the white bar: .avalon-hours positioned - the box its hidden Hours: is placed in, so a card the results box has scrolled away takes the span with it");
/* Every absolutely placed rule in the file, and the positioned box its
   element is placed in: the hidden Hours: in .avalon-hours; the hidden
   words of a number in its disc (slp_avalon.js puts them there); the bars
   of + and - in their button. */
$abs = array();
if (preg_match_all('/([^{}]+)\{([^{}]*)\}/', $bare_all, $mm, PREG_SET_ORDER)) {
    foreach ($mm as $rr) { if (preg_match('/(^|;)\s*position\s*:\s*absolute\s*;/', $rr[2])) { $abs[] = trim(preg_replace('/\s+/', ' ', $rr[1])); } }
}
check($abs === array('.avalon-hours .avalon-hours__sr', '#map_sidebar .results_wrapper .avalon-num__sr',
                     '#map .gm-style .avalon-mapctl__in::before, #map .gm-style .avalon-mapctl__in::after, #map .gm-style .avalon-mapctl__out::before')
      && has_decl($top_all, '#map_sidebar .results_wrapper .avalon-num', 'position', 'relative')
      && has_decl($top_all, '#map .gm-style .avalon-mapctl button', 'position', 'relative'),
      "three absolutely placed rules in the whole file, each inside a box positioned for it - .avalon-hours, the disc, the button - none of them able to reach past the page's foot");
check($F('#map_sidebar .results_wrapper .avalon-num', array('position' => 'relative', 'display' => 'inline-block', 'box-sizing' => 'border-box',
          'min-width' => '26px', 'height' => '26px', 'padding' => '0 7px', 'border-radius' => '13px', 'line-height' => '26px',
          'text-align' => 'center', 'font-size' => '14px', 'font-weight' => '700'))
      && $F('#map_sidebar .results_wrapper .avalon-num', array(
          'background-color' => 'var(--avalon-num-bg, var(--e-global-color-primary, var(--primary-color, #e7167c)))',
          'color' => 'var(--avalon-num-text, #000)')),
      "the card's number: a disc 26 px round, a pill for two digits, in the site's primary colour, the number black and bold - each a custom property a site can set");
check($F('#map_sidebar .results_wrapper .avalon-num', array('margin' => '1px 10px -1px 0', 'vertical-align' => 'top'))
      && $F('#map_sidebar .results_wrapper .avalon-num', array('margin' => '-1px 8px -3px 0'), $m4f1024),
      "  ... its middle on the name's capitals and the line no taller - 1 px down at 24 px, 1 px up at 20 px (1024 and under), its margins taking back what it adds; the column gap after it, 10 then 8");
$sr4  = rules_of($blk4f, '#map_sidebar .results_wrapper .avalon-num__sr');
$srH  = rules_of($css, '.avalon-hours .avalon-hours__sr');
check(count($sr4) === 1 && count($srH) === 1 && $decls_of($sr4[0]) === $decls_of($srH[0])
      && ($decls_of($sr4[0])['clip-path'] ?? '') === 'inset(50%)',
      "  ... \"Number \" and the comma hidden on screen exactly as the hidden Hours: is - for screen readers alone");
check($F('#map .gm-style .avalon-mapctl', array('display' => 'flex', 'align-items' => 'flex-end', 'gap' => '10px', 'margin' => '10px'))
      && $F('#map .gm-style .avalon-mapctl__zoom', array('display' => 'flex', 'flex-direction' => 'column', 'align-items' => 'center',
            'border-radius' => '2px', 'background-color' => '#fff', 'box-shadow' => 'rgba(0, 0, 0, 0.3) 0 1px 4px -1px'))
      && $F('#map .gm-style .avalon-mapctl__rule', array('width' => '30px', 'height' => '1px', 'background-color' => '#e6e6e6')),
      "the map's buttons: 10 px from the map's edges and from each other, their feet in line; + and - in one white box, 2 px corners, Google's shadow, a 30 px rule between - as the probe read Google's on DEV");
check($F('#map .gm-style .avalon-mapctl button', array('position' => 'relative', 'display' => 'block', 'box-sizing' => 'border-box',
          'width' => '40px', 'height' => '40px', 'margin' => '0', 'padding' => '0', 'border' => '0', 'border-radius' => '2px',
          'background-color' => '#fff', 'box-shadow' => 'none', 'color' => '#666', 'cursor' => 'pointer',
          'font-family' => 'Roboto, Arial, sans-serif', 'font-size' => '18px', 'font-weight' => '500', 'line-height' => '40px',
          'text-transform' => 'none', 'letter-spacing' => 'normal')),
      "  ... each button 40 px, no border, Google's grey, in Google's type - everything the theme or a reset could have set, set");
check($F('#map .gm-style .avalon-mapctl .avalon-mapctl__reset', array('width' => 'auto', 'padding' => '0 17px', 'color' => '#565656',
          'box-shadow' => 'rgba(0, 0, 0, 0.3) 0 1px 4px -1px'))
      && $F('#map .gm-style .avalon-mapctl--corner .avalon-mapctl__reset', array('padding' => '0 12px'))
      && spec('#map .gm-style .avalon-mapctl--corner .avalon-mapctl__reset') === spec('#map .gm-style .avalon-mapctl .avalon-mapctl__reset')
      && strpos($blk4f, '#map .gm-style .avalon-mapctl--corner .avalon-mapctl__reset {') > strpos($blk4f, '#map .gm-style .avalon-mapctl .avalon-mapctl__reset {'),
      "Reset: as wide as its word, 17 px either side - the Map and Satellite buttons' - with its own shadow; alone in a phone's corner, 12 px, to clear Map and Satellite on the narrowest maps - the same weight, the later rule");
$ord = function ($s) use ($blk4f) { $p = strpos($blk4f, $s . " {"); return $p === false ? -1 : $p; };
check($F('#map .gm-style .avalon-mapctl button:focus', array('outline' => 'none', 'background-color' => '#fff'))
      && $F('#map .gm-style .avalon-mapctl button:focus-visible', array('outline' => '2px solid #000', 'outline-offset' => '-4px'))
      && $ord('#map .gm-style .avalon-mapctl button:focus') >= 0
      && $ord('#map .gm-style .avalon-mapctl button:focus') < $ord('#map .gm-style .avalon-mapctl button:focus-visible')
      && spec('#map .gm-style .avalon-mapctl button:focus') === spec('#map .gm-style .avalon-mapctl button:focus-visible'),
      "focus: none for a pointer; for a keyboard a black ring inside the white button, which shows over any map - the same weight, so the later rule wins when both apply");
check($F('#map .gm-style .avalon-mapctl button:hover', array('background-color' => '#fff', 'color' => '#333'))
      && $F('#map .gm-style .avalon-mapctl .avalon-mapctl__reset:hover', array('background-color' => '#ebebeb', 'color' => '#000'))
      && $F('#map .gm-style .avalon-mapctl button:active', array('color' => '#111'))
      && $ord('#map .gm-style .avalon-mapctl button:focus') < $ord('#map .gm-style .avalon-mapctl button:hover')
      && $ord('#map .gm-style .avalon-mapctl button:hover') < $ord('#map .gm-style .avalon-mapctl button:active'),
      "hover: + and - darker; Reset grey behind its word, as Map and Satellite go; pressed, darker still - in that order, after focus");
check($F('#map .gm-style .avalon-mapctl button[aria-disabled="true"]', array('background-color' => '#fff', 'color' => '#d1d1d1', 'cursor' => 'default'))
      && $F('#map .gm-style .avalon-mapctl button[aria-disabled="true"]:hover', array('color' => '#d1d1d1'))
      && beats('#map .gm-style .avalon-mapctl button[aria-disabled="true"]:hover', '#map .gm-style .avalon-mapctl button:hover')
      && beats('#map .gm-style .avalon-mapctl button[aria-disabled="true"]:hover', '#map .gm-style .avalon-mapctl button:active')
      && strpos($blk4f, '#map .gm-style .avalon-mapctl button[aria-disabled="true"],') > strpos($blk4f, '#map .gm-style .avalon-mapctl button:active {'),
      "+ or - at either end of the zoom, aria-disabled: dimmed, no pointer, and neither hover nor press brightens it - by weight, and by coming after them");
$theme = array('button:hover', 'button:focus', '[type=button]:hover', '[type=button]:focus', 'button', '[type=button]');
$ok = true;
foreach (array('#map .gm-style .avalon-mapctl button', '#map .gm-style .avalon-mapctl button:focus', '#map .gm-style .avalon-mapctl button:hover',
               '#map .gm-style .avalon-mapctl .avalon-mapctl__reset:hover', '#map .gm-style .avalon-mapctl button:active') as $ours) {
    foreach ($theme as $t) { if (! beats($ours, $t)) { $ok = false; } }
}
check($ok, "every state of every button beats the theme's button:hover and button:focus and a reset's [type=button] - which would paint them pink - without !important");
check($F('#map .gm-style .avalon-mapctl__in::before', array('content' => '""', 'position' => 'absolute', 'top' => '50%', 'left' => '50%',
          'width' => '16px', 'height' => '2px', 'margin' => '-1px 0 0 -8px', 'background-color' => 'currentColor'))
      && $F('#map .gm-style .avalon-mapctl__out::before', array('width' => '16px', 'height' => '2px'))
      && $F('#map .gm-style .avalon-mapctl__in::after', array('transform' => 'rotate(90deg)'))
      && count(rules_of($blk4f, '#map .gm-style .avalon-mapctl__out::after')) === 0,
      "+ and - drawn: 16 px bars, 2 px thick, centred, in the button's own colour - + two, crossed; - one");
check(preg_match('/!important/', $bare_all) === 0,
      'no !important anywhere in the file: a site can restyle all of it');

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
    'bubble'  => span($code, $P4B_START, $P4C_START),
    'map'     => span($code, $P4C_START, $BLOCK_START),
    'frame'   => $block,
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
/* Harness - suite-v034's, verbatim.                                   */
/* ------------------------------------------------------------------ */

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'slp37_' . getmypid();
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

/* Part 4d's output for Aura's served bubble layout - reviewed by hand:
   the grid's wrapper from Distance: to the hours field, inside the contact
   block; the row's round the two button spans; nothing else. */
$AURA_B_FRAMED = str_replace(
    array('<div class="sl_popup_contact_info"><span class="avalon-bubble-distance">',
          '[slp_location avalon_hours_html] <span id="slp_bubble_fax">',
          '<span id="slp_bubble_directions">',
          '[slp_location web_link][html ifset url]</span> </div>'),
    array('<div class="sl_popup_contact_info"><div class="avalon-bubble__info"><span class="avalon-bubble-distance">',
          '[slp_location avalon_hours_html]</div> <span id="slp_bubble_fax">',
          '<div class="avalon-bubble__actions"><span id="slp_bubble_directions">',
          '[slp_location web_link][html ifset url]</span></div> </div>'),
    $AURA_B_OUT);

/* ----------------------------------------------------------- scenarios */

$s = $argv[1] ?? '';
$out = array();
try {
    $o = SLP_Avalon::t_boot();
    switch ($s) {

    case 'hours':
        $p = array('days' => array(array(1, 'Monday', '9 AM-5 PM'), array(0, 'Sunday', 'Closed & <b>x</b>')),
                   'periods' => array(array(array(1, 9, 0), array(1, 17, 0))), 'tz' => 'America/New_York',
                   'o' => array(array(1790992800, -240)), 'u' => 1825639200,
                   'attr' => array(array('Test Source', 'https://example.test/attr')));
        $out['card'] = SLP_Avalon::avalon_hours_markup($p, 'card');
        $out['store'] = SLP_Avalon::avalon_hours_markup($p, 'store');
        $out['none'] = array(SLP_Avalon::avalon_hours_markup(null, 'card'), SLP_Avalon::avalon_hours_markup(array('days' => array()), 'store'));
        break;

    case 'frame':
        $L = function ($x) use ($o) { return $o->avalon_bubble_layout_frame($x); };
        $out['aura'] = $L($AURA_B_OUT);
        $out['aura_want'] = $AURA_B_FRAMED;
        $out['aura_in'] = $AURA_B_OUT;
        $out['aura_again'] = $L($out['aura']);
        $out['aura_third'] = $L($out['aura_again']);
        $out['slpdef_in'] = $SLPDEF_B_OUT;
        $out['slpdef'] = $L($SLPDEF_B_OUT);
        $noinfo = str_replace('[slp_location avalon_hours_html]', '', $AURA_B_OUT);
        $out['noinfo'] = $L($noinfo);
        $out['noinfo_want'] = str_replace(array('<span id="slp_bubble_directions">', '[slp_location web_link][html ifset url]</span> </div>'),
                                          array('<div class="avalon-bubble__actions"><span id="slp_bubble_directions">', '[slp_location web_link][html ifset url]</span></div> </div>'),
                                          $noinfo);
        $out['outside_in'] = '<span class="avalon-bubble-distance">d</span><div class="sl_popup_contact_info"><span id="x">[slp_location avalon_hours_html]</span></div>';
        $out['outside'] = $L($out['outside_in']);
        $out['after_in'] = '<div class="sl_popup_contact_info"><span class="avalon-bubble-distance">d</span></div>[slp_location avalon_hours_html]';
        $out['after'] = $L($out['after_in']);
        $out['beyond_in'] = '<div class="sl_popup_contact_info">c</div><span class="avalon-bubble-distance">d</span>[slp_location avalon_hours_html]';
        $out['beyond'] = $L($out['beyond_in']);
        $out['unwhole_in'] = '<div class="sl_popup_contact_info"><span class="avalon-bubble-distance">d<span>[slp_location avalon_hours_html]</div>';
        $out['unwhole'] = $L($out['unwhole_in']);
        $out['unclosed_in'] = '<div class="sl_popup_contact_info"><span class="avalon-bubble-distance">d</span>[slp_location avalon_hours_html]'
                            . '<span id="slp_bubble_directions">a</span> <span id="slp_bubble_website">b</span>';
        $out['unclosed'] = $L($out['unclosed_in']);
        $out['nosite_in'] = '<div class="sl_popup_contact_info">c</div><span id="slp_bubble_directions">a</span> <span id="slp_bubble_x">b</span>';
        $out['between_in'] = '<div class="sl_popup_contact_info">c</div><span id="slp_bubble_directions">a</span> <b>x</b> <span id="slp_bubble_website">b</span>';
        $out['sitefirst_in'] = '<div class="sl_popup_contact_info">c</div><span id="slp_bubble_website">b</span> <span id="slp_bubble_directions">a</span>';
        $out['nosite'] = $L($out['nosite_in']);
        $out['between'] = $L($out['between_in']);
        $out['sitefirst'] = $L($out['sitefirst_in']);
        $out['nested'] = $L('<div class="sl_popup_contact_info"><div class="x">y</div><span class="avalon-bubble-distance">d</span>[slp_location avalon_hours_html]</div>');
        $out['dollar'] = $L('<div class="sl_popup_contact_info"><span class="avalon-bubble-distance">$1 \\1 ${0}</span>[slp_location avalon_hours_html]</div>'
                          . '<span id="slp_bubble_directions">$2</span> <span id="slp_bubble_website">\\0</span>');
        $nl = function ($x) { return str_replace('</span> <span id="slp_bubble_website">', "</span>\r\n<span id=\"slp_bubble_website\">", $x); };
        $out['crlf'] = $L($nl($AURA_B_OUT)) === $nl($AURA_B_FRAMED) && $nl($AURA_B_FRAMED) !== $AURA_B_FRAMED;
        $out['other_in'] = '<div class="sl_popup_contact_info x"><span class="avalon-bubble-distance">d</span>[slp_location avalon_hours_html]</div>';
        $out['other'] = $L($out['other_in']);
        $out['empty'] = array($L(''), $L(null));
        break;

    case 'jsopts':
        /* As SLP runs it, from the stored layouts: add_to_js_options() at 10
           builds the results layout through slp_javascript_results_string and
           hands the stored bubble layout on; SLP Experience at 90 leaves them
           or merges its own in; then every slp_avalon callback in order. */
        $o->t_wire();
        $rs = array(array(90, 0, function ($l) { return $GLOBALS['R_STORED']; }));
        $js = array();
        $seq = 1;
        $out['names'] = array();
        foreach ($GLOBALS['REC']['filters'] as $f) {
            $meth = substr($f[1], strlen('SLP_Avalon->'));
            if ($f[0] === 'slp_javascript_results_string') {
                $rs[] = array($f[2], $seq++, function ($l) use ($o, $meth) { return $o->{$meth}($l); });
            } elseif ($f[0] === 'slp_js_options') {
                $out['names'][] = array($f[1], $f[2]);
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
        $out['want_b'] = $AURA_B_FRAMED;
        $GLOBALS['R_STORED'] = $SLPDEF_R;
        $GLOBALS['B_STORED'] = $SLPDEF_B;
        $chain = array_merge(array($slp10, $leave), $js);
        usort($chain, $order);
        $def = $through(array(), $chain);
        $out['slpdef_r'] = $def['resultslayout'];
        $out['slpdef_b'] = $def['bubblelayout'];
        $out['slpdef_want_r'] = $SLPDEF_R_OUT;
        $out['slpdef_want_b'] = $SLPDEF_B_OUT;
        $out['noop'] = array($o->avalon_js_options_frame('x'), $o->avalon_js_options_frame(null),
                             $o->avalon_js_options_frame(array('bubblelayout' => array('x'), 'resultslayout' => 7)),
                             $o->avalon_js_options_frame(array('map_region' => 'us')));
        break;

    case 'numbers':
        /* Every kind of value an option can hold, in both options at once. */
        $GLOBALS['ESC_URL_RAW'] = array();
        $vals = array('unset' => null, 'empty' => '', 'root' => '  /wp-content/uploads/2026/10/pink-marker.png  ',
                      'https' => 'https://cdn.example.test/p.png', 'HTTP' => 'HTTP://cdn.example.test/p.png',
                      'protocol' => '//evil.test/p.png', 'js' => 'javascript:alert(1)', 'relative' => 'pink-marker.png',
                      'ftp' => 'ftp://cdn.example.test/p.png', 'data' => 'data:image/png;base64,AAAA',
                      'array' => array('/x.png'), 'space' => '/wp-content/uploads/a b.png', 'int' => 5, 'false' => false);
        foreach ($vals as $k => $v) {
            $GLOBALS['OPTIONS'] = $v === null ? array() : array('avalon_map_number_icon' => $v, 'avalon_map_number_hover_icon' => $v);
            $out['n'][$k] = array(SLP_Avalon::avalon_map_number_icon(), SLP_Avalon::avalon_map_number_hover_icon());
        }
        $out['esc'] = $GLOBALS['ESC_URL_RAW'];
        $GLOBALS['OPTIONS'] = array('avalon_map_number_icon' => '/n.png');
        $out['one'] = array(SLP_Avalon::avalon_map_number_icon(), SLP_Avalon::avalon_map_number_hover_icon());
        $GLOBALS['OPTIONS'] = array('avalon_map_hover_icon' => '/h.png', 'avalon_map_number_icon' => '/n.png',
                                    'avalon_map_number_hover_icon' => 'https://cdn.example.test/nh.png');
        $out['opts'] = $o->avalon_js_options_map(array('map_region' => 'us'));
        $GLOBALS['OPTIONS'] = array();
        $out['none'] = $o->avalon_js_options_map(array());
        $out['pass'] = array($o->avalon_js_options_map('x'), $o->avalon_js_options_map(null));
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
         . $LIFTS['consts'] . $LIFTS['wiring'] . $LIFTS['display'] . $LIFTS['bubble'] . $LIFTS['map'] . $LIFTS['frame']
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

/* ------------------------------------------------------- the hours */

echo "\n  THE HOURS MARKUP - avalon_hours_markup()\n";
$r = run($hfile, 'hours');
$c = $r['card'] ?? '';
$st = $r['store'] ?? '';
check(strpos($c, '<b class="avalon-label avalon-label--hours" aria-hidden="true">Hours:</b><div class="avalon-hours avalon-hours--card" data-avalon-hours="') === 0,
      "a card: Hours: in front of its block, in the label column, hidden from screen readers");
check(has($c, '<details class="avalon-hours__narrow"><summary class="avalon-hours__summary"><span class="avalon-hours__sr">Hours: </span>'
              . '<span class="avalon-hours__status">See <span class="avalon-hours__nowrap">hours<i class="avalon-hours__caret" aria-hidden="true"></i></span></span></summary>'),
      '  ... the summary says "Hours: " to screen readers alone, then See hours, its caret an <i> held to the last word');
check(has($c, '<tr data-day="1"><th scope="row">Monday</th><td><span class="avalon-hours__time">9 AM-5 PM</span></td></tr>')
      && has($c, '<td><span class="avalon-hours__time">Closed &amp; &lt;b&gt;x&lt;/b&gt;</span></td>')
      && cnt($c, 'avalon-hours__time') === 2 && cnt($st, 'avalon-hours__time') === 4,
      "each day's hours in a span of their own, escaped once - on the card and in both copies of the store page's week");
check(preg_match('/<span\b[^>]*>\s*<\/span>/', $c . $st) === 0 && cnt($c . $st, '<i class="avalon-hours__caret" aria-hidden="true"></i>') === 2,
      'no empty <span> anywhere - SLP hides them as it inserts a card - and the caret, empty by nature, an <i>');
check(strpos($st, '<div class="storelocator_address_container avalon-hours avalon-hours--store" data-avalon-hours="') === 0
      && ! has($st, 'avalon-label') && ! has($st, 'avalon-hours__sr')
      && has($st, '<h2>Hours</h2><div class="avalon-hours__wide"><p class="avalon-hours__status">&nbsp;</p><table class="avalon-hours__week">')
      && has($st, '<summary class="avalon-hours__summary"><span class="avalon-hours__status">See <span class="avalon-hours__nowrap">hours<i class="avalon-hours__caret" aria-hidden="true"></i></span></span></summary>'),
      "the store page: no label and no screen-reader copy, as before; its fold's summary with the caret held to its word");
check(($r['none'] ?? null) === array('', ''), 'no payload, or no days: nothing at all');

/* ------------------------------------------------------- the frame */

echo "\n  THE BUBBLE FRAME - avalon_bubble_layout_frame()\n";
$r = run($hfile, 'frame');
$fo = $r['aura'] ?? '';
check($fo !== '' && $fo === ($r['aura_want'] ?? null) && $fo !== ($r['aura_in'] ?? null),
      "Aura's bubble layout, as DEV serves it, becomes exactly the reviewed output");
check(($r['aura_again'] ?? '') === $fo && ($r['aura_third'] ?? '') === $fo, 'idempotent - a second and a third pass change nothing');
$in = $r['aura_in'] ?? '';
check(cnt($fo, '<div') === cnt($in, '<div') + 2 && cnt($fo, '</div>') === cnt($in, '</div>') + 2
      && cnt($fo, '<span') === cnt($in, '<span') && cnt($fo, '</span>') === cnt($in, '</span>')
      && has($fo, '<div id="slp_info_bubble_[slp_location id]" class="slp_info_bubble [slp_location featured]">')
      && has($fo, '<span id="slp_bubble_website">[html ifset url][slp_location web_link][html ifset url]</span>')
      && has($fo, '<span id="slp_bubble_directions">[html ifset directions] [slp_option label_directions wrap directions]</span>')
      && has($fo, '<div class="sl_popup_contact_info">'),
      "two wrappers in, nothing out: the outer div's id, the spans main.js and slp_avalon.js read, the contact block - all as they were");
check(($r['slpdef'] ?? 'x') === ($r['slpdef_in'] ?? 'y'), "SLP's own default bubble - no contact block: left as it was");
check(($r['noinfo'] ?? 'x') === ($r['noinfo_want'] ?? 'y'), 'no hours field in the contact block: its lines left unwrapped, the buttons still given their row');
check(($r['outside'] ?? 'x') === ($r['outside_in'] ?? 'y') && ($r['after'] ?? 'x') === ($r['after_in'] ?? 'y')
      && ($r['beyond'] ?? 'x') === ($r['beyond_in'] ?? 'y'),
      'Distance: before the contact block, the hours field after it, or both after it: no grid wrapper');
check(($r['unwhole'] ?? 'x') === ($r['unwhole_in'] ?? 'y'), 'a run of lines that does not close what it opens: not wrapped');
check(($r['unclosed'] ?? 'x') === ($r['unclosed_in'] ?? 'y'), 'a contact block that never closes: the layout as it was, buttons and all');
check(($r['nosite'] ?? 'x') === ($r['nosite_in'] ?? 'y') && ($r['between'] ?? 'x') === ($r['between_in'] ?? 'y')
      && ($r['sitefirst'] ?? 'x') === ($r['sitefirst_in'] ?? 'y'),
      'Directions without Website, something between them, or Website first: no row');
check(($r['nested'] ?? '') === '<div class="sl_popup_contact_info"><div class="x">y</div><div class="avalon-bubble__info"><span class="avalon-bubble-distance">d</span>[slp_location avalon_hours_html]</div></div>',
      "a div inside the contact block: the block's own end found past it, the lines wrapped inside");
check(($r['dollar'] ?? '') === '<div class="sl_popup_contact_info"><div class="avalon-bubble__info"><span class="avalon-bubble-distance">$1 \\1 ${0}</span>[slp_location avalon_hours_html]</div></div>'
      . '<div class="avalon-bubble__actions"><span id="slp_bubble_directions">$2</span> <span id="slp_bubble_website">\\0</span></div>'
      && ($r['crlf'] ?? false) === true,
      '$1, \\1 and ${0} stay literal text; a CRLF between the two buttons\' spans: the same row');
check(($r['other'] ?? 'x') === ($r['other_in'] ?? 'y') && ($r['empty'] ?? null) === array('', ''),
      'a contact div with another class beside: not recognised; empty or null: an empty string');

/* ----------------------------------------------- the script options */

echo "\n  SCRIPT OPTIONS (slp_js_options)\n";
$r = run($hfile, 'jsopts');
check(($r['names'] ?? null) === array(array('SLP_Avalon->avalon_js_options_layout', 100), array('SLP_Avalon->avalon_js_options_bubble', 100),
                                      array('SLP_Avalon->avalon_js_options_map', 110), array('SLP_Avalon->avalon_js_options_frame', 120)),
      "Part 4's and 4b's callbacks at 100, Part 4c's at 110, then this one at 120");
check(($r['pass']['bubblelayout'] ?? '') === ($r['want_b'] ?? 'x') && ($r['merge']['bubblelayout'] ?? '') === ($r['want_b'] ?? 'x')
      && ($r['pass']['resultslayout'] ?? '') === ($r['want_r'] ?? 'x') && ($r['merge']['resultslayout'] ?? '') === ($r['want_r'] ?? 'x')
      && ($r['pass']['map_region'] ?? null) === 'us',
      "from Aura's stored layouts, SLP Experience leaving or merging them: the bubble exactly the reviewed output, the cards' layout Part 4c's, no other option touched");
check(($r['again']['bubblelayout'] ?? '') === ($r['want_b'] ?? 'x') && ($r['again']['resultslayout'] ?? '') === ($r['want_r'] ?? 'x'),
      'the script options through every callback a second time: unchanged');
check(($r['slpdef_b'] ?? '') === ($r['slpdef_want_b'] ?? 'x') && ($r['slpdef_r'] ?? '') === ($r['slpdef_want_r'] ?? 'x'),
      "SLP's default layouts through the same chain: Part 4c's outputs, untouched here");
check(($r['noop'] ?? null) === array('x', null, array('bubblelayout' => array('x'), 'resultslayout' => 7), array('map_region' => 'us')),
      'options that are not an array, a bubble layout that is not a string, or none: passed through');

/* ----------------------------------------- the numbered pins' options */

echo "\n  THE NUMBERED PINS' OPTIONS (Part 4f)\n";
$r = run($hfile, 'numbers');
$n = $r['n'] ?? array();
$same2 = function ($k, $v) use ($n) { return ($n[$k] ?? null) === array($v, $v); };
check($same2('unset', '') && $same2('empty', ''), "neither option set, or set empty: '' from both readers - no numbers");
check($same2('root', '/wp-content/uploads/2026/10/pink-marker.png') && $same2('https', 'https://cdn.example.test/p.png')
      && $same2('HTTP', 'HTTP://cdn.example.test/p.png') && $same2('space', '/wp-content/uploads/a%20b.png'),
      "a path from the site's root, trimmed, and an http(s) URL in any case: through esc_url_raw(), as given");
check($same2('protocol', '') && $same2('js', '') && $same2('relative', '') && $same2('ftp', '') && $same2('data', ''),
      "protocol-relative, javascript:, a relative name, ftp: and data: - none of them a pin: ''");
check($same2('array', '') && $same2('int', '') && $same2('false', ''),
      "an option that is not a string - an array, a number, false: '', and no warning");
$esc = $r['esc'] ?? array();
check(count($esc) === 8 && count(array_filter($esc, function ($c) { return $c[1] === array('http', 'https'); })) === 8,
      "esc_url_raw() called for the four kept values, in both readers - eight times, with http and https alone");
check(($r['one'] ?? null) === array('/n.png', ''), "each reader reads its own option: the numbered pin set, the one lit not");
check(($r['opts'] ?? null) === array('map_region' => 'us', 'avalon_map_hover_icon' => '/h.png', 'avalon_map_number_icon' => '/n.png',
                                     'avalon_map_number_hover_icon' => 'https://cdn.example.test/nh.png'),
      "avalon_js_options_map(): the hover pin, then the numbered pin and the numbered pin lit, after what was there");
check(($r['none'] ?? null) === array('avalon_map_hover_icon' => '', 'avalon_map_number_icon' => '', 'avalon_map_number_hover_icon' => '')
      && ($r['pass'] ?? null) === array('x', null),
      "  ... always set, '' when there is none; options that are not an array passed through");

/* ---------------------------------------------------------- WP Rocket */

echo "\n  WP ROCKET\n";
$r = run($hfile, 'rocket');
check(($r['m'][0] ?? null) === array('.keep', '(.*).avalon-address(.*)', '(.*)#map_sidebar(.*)', '(.*).gm-style(.*)')
      && count($r['m'][1] ?? array()) === 3 && count($r['m'][2] ?? array()) === 3,
      "Part 4c's safelist: three patterns now - .avalon-fa gone with the icons; existing entries kept");
$all = $r['all'] ?? array();
check(count($all) === 10 && ($all[0] ?? '') === '/wp-content/plugins/slp_avalon/assets/css/avalon-hours.css',
      "after Part 4's and 4b's: the file, their six selectors, then these three");
/* Part 4e's keyframes hold no selector: "from" is not one. The stylesheet
   itself is the safelist's first entry, so WP Rocket keeps the file whole,
   keyframes and all; the patterns are for a Used CSS built without it. */
$sels = array();
if (preg_match_all('/([^{}@]+)\{[^{}]*\}/', preg_replace('/@keyframes[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/', '', $code_css), $mm)) {
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
check(count($sels) >= 55 && $miss === array() && in_array('#map_sidebar .results_wrapper.avalon-flash', $sels, true),
      sprintf("every selector of the block as shipped (%d), Part 4e's flash among them, matches a pattern read from the selector's start%s", count($sels),
              $miss ? ': missing ' . implode(' | ', $miss) : ''));
$sels4 = array();
if (preg_match_all('/([^{}@]+)\{[^{}]*\}/', preg_replace('/@media[^{]*\{/', '', preg_replace('/\/\*.*?\*\//s', '', $blk4f)), $mm)) {
    foreach ($mm[1] as $group) {
        foreach (explode(',', $group) as $one) { $one = trim($one); if ($one !== '') { $sels4[] = $one; } }
    }
}
$miss4 = array();
foreach ($sels4 as $one) {
    $hit = false;
    foreach (array_slice($all, 1) as $pat) { if (preg_match('~^' . $pat . '~', $one)) { $hit = true; break; } }
    if (! $hit) { $miss4[] = $one; }
}
check(count($sels4) === 20 && $miss4 === array(),
      sprintf("Part 4f: every selector of its block (%d) - the cards' #map_sidebar, the map's .gm-style - matches a pattern Part 4c put on the safelist%s",
              count($sels4), $miss4 ? ': missing ' . implode(' | ', $miss4) : ''));

/* ------------------------------------------------------ registrations */

echo "\n  REGISTRATIONS\n";
$r = run($hfile, 'wiring');
$acts = $r['actions'] ?? array();
$fils = $r['filters'] ?? array();
$find = function ($list, $row) { return count(array_filter($list, function ($e) use ($row) { return $e === $row; })); };
check($find($fils, array('slp_js_options', 'SLP_Avalon->avalon_js_options_frame', 120, 1)) === 1,
      'slp_js_options -> avalon_js_options_frame, priority 120, once');
check($find($fils, array('slp_results_marker_data', 'SLP_Avalon->avalon_marker_address', 30, 1)) === 1
      && $find($fils, array('slp_javascript_results_string', 'SLP_Avalon->avalon_results_layout_address', 110, 1)) === 1
      && $find($fils, array('slp_js_options', 'SLP_Avalon->avalon_js_options_map', 110, 1)) === 1
      && $find($fils, array('rocket_rucss_safelist', 'SLP_Avalon::avalon_rocket_rucss_safelist_map', 10, 1)) === 1,
      "Part 4c's four are still there");
check(count($acts) + count($fils) === 41 && count($r['shortcodes'] ?? array()) === 5,
      sprintf("%d registrations in all, as Part 4d left them - Part 4f adds none; five shortcodes, as before", 41));

/* ---------------------------------------------------------- crashes */

echo "\n  HARNESS\n";
check($CRASHES === array(), 'no scenario crashed' . ($CRASHES ? ': ' . json_encode($CRASHES) : ''));

@unlink($hfile);
@unlink($akCopy);
@rmdir($tmp);

printf("\n  %d passed, %d failed, %d total\n\n", $pass, $fail, $pass + $fail);
exit($fail ? 1 : 0);
