<?php
/**
 * suite-v024.php - validates the Contact Dealer anchor and the modal handler.
 *
 * Two artefacts, two kinds of assertion.
 *
 * The PHP half extracts avalon_store_contact_dealer_button_sc_func from the
 * class file, executes it against stubbed SLP state and asserts on the HTML it
 * produces. The behaviour that matters is an attribute value, so the emitted
 * markup is parsed with DOMDocument rather than grepped: a substring match
 * would pass on a data-dealer-id that landed in the wrong element or carried
 * the wrong value.
 *
 * The JS half asserts on the artefact text of slp_avalon.js. The handler is a
 * delegated jQuery binding and cannot be executed here without a DOM and a
 * jQuery, so what is asserted is the shape that makes it correct: it binds the
 * anchor class and not an ancestor, it guards on the modal before calling
 * preventDefault, and it reads the id with attr() rather than data(). Each of
 * those is a decision this release exists to make, and each would be silently
 * lost by a careless later edit. The file is also handed to node --check, so a
 * syntax error in the appended block cannot pass.
 *
 * Usage: php suite-v024.php <path-to-class.slp_avalon.php> <path-to-slp_avalon.js>
 */

$clsPath = $argv[1] ?? 'build/out24/class.slp_avalon.php';
$jsPath  = $argv[2] ?? 'build/out24/slp_avalon.js';

$code = file_get_contents($clsPath);
if ($code === false) {
    fwrite(STDERR, "cannot read $clsPath\n");
    exit(2);
}
$js = file_get_contents($jsPath);
if ($js === false) {
    fwrite(STDERR, "cannot read $jsPath\n");
    exit(2);
}

// ---- extract the shortcode method, brace-matched --------------------------
$start = strpos($code, 'public function avalon_store_contact_dealer_button_sc_func');
if ($start === false) {
    fwrite(STDERR, "shortcode method not found\n");
    exit(2);
}
$i = strpos($code, '{', $start);
$depth = 0;
$end = null;
for ($p = $i; $p < strlen($code); $p++) {
    if ($code[$p] === '{') { $depth++; }
    elseif ($code[$p] === '}') {
        $depth--;
        if ($depth === 0) { $end = $p; break; }
    }
}
$method = substr($code, $start, $end - $start + 1);
$fn = 'function ' . substr($method, strlen('public function '));

// ---- stubs ---------------------------------------------------------------
if (!function_exists('shortcode_atts')) {
    function shortcode_atts($pairs, $atts, $sc = '') { return (array) $atts; }
}
if (!function_exists('get_site_url')) {
    function get_site_url($blog_id = null, $path = '') {
        return 'https://example.test/' . ltrim($path, '/');
    }
}
if (!function_exists('esc_url')) {
    // Close enough to WordPress for this purpose: entity-encode the ampersand
    // separators, which is what distinguishes escaped from unescaped output.
    function esc_url($u) { return str_replace('&', '&#038;', $u); }
}
if (!function_exists('esc_attr')) {
    function esc_attr($t) { return htmlspecialchars((string) $t, ENT_QUOTES); }
}

eval($fn);

/**
 * Parse the attributes of a single start tag into name => value.
 *
 * Values are entity-decoded so a comparison is against what a browser would
 * see, not against the wire form.
 */
function attrs_of($tag) {
    $out = [];
    if (preg_match_all('/([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*"([^"]*)"/', $tag, $m, PREG_SET_ORDER)) {
        foreach ($m as $pair) {
            $out[strtolower($pair[1])] = html_entity_decode($pair[2], ENT_QUOTES, 'UTF-8');
        }
    }
    return $out;
}

// ---- cases ---------------------------------------------------------------
$cases = [
    'dealer with an identifier (normal Aura row)' => [
        'id' => 104557,
        'exdata' => ['identifier' => 'FDNJCO007'],
    ],
    'dealer with no identifier' => [
        'id' => 99001,
        'exdata' => [],
    ],
    'identifier carrying an attribute-hostile character' => [
        'id' => 12345,
        'exdata' => ['identifier' => 'A"B&C'],
    ],
];

$pass = $fail = 0;
function check($ok, $label) {
    global $pass, $fail;
    if ($ok) { $pass++; printf("    [PASS] %s\n", $label); }
    else     { $fail++; printf("    [FAIL] %s\n", $label); }
}

foreach ($cases as $label => $row) {
    printf("\n  case: %s\n", $label);

    $GLOBALS['slplus'] = (object) [
        'options' => [],
        'currentLocation' => (object) ['id' => $row['id'], 'exdata' => $row['exdata']],
    ];
    $html = avalon_store_contact_dealer_button_sc_func([]);

    check($html !== '', 'shortcode produced output');

    $n = preg_match_all('/<a\b[^>]*>/i', $html, $tags);
    check($n === 1, 'exactly one anchor emitted');
    $rawTag = $tags[0][0] ?? '';
    $attr = attrs_of($rawTag);

    $classes = preg_split('/\s+/', trim($attr['class'] ?? ''));
    check(in_array('store_locator_contact_store_button', $classes, true),
        'anchor carries store_locator_contact_store_button');

    // The handler binds this class alone. If the locator class is ever added
    // here, main.js starts firing too and writes null into field 14_8.
    check(!in_array('store_locator_contact_store', $classes, true),
        'anchor does NOT carry the locator class (would double-bind main.js)');

    check(($attr['data-dealer-id'] ?? null) === (string) $row['id'],
        'data-dealer-id equals the SLP location id');

    check(strpos($attr['style'] ?? '', 'font-size') === false,
        'no inline font-size left to fight the stylesheet');

    $href = $attr['href'] ?? '';
    check(strpos($href, 'store_id=' . $row['id']) !== false,
        'href still carries store_id');

    $wantDealer = isset($row['exdata']['identifier']) ? $row['exdata']['identifier'] : null;
    $hasDealer = strpos($href, 'dealer_id=') !== false;
    check($hasDealer === ($wantDealer !== null),
        'dealer_id present only when the row has an identifier');

    // Asserted against the RAW tag, not the decoded attribute: attrs_of()
    // entity-decodes, which would turn a correctly escaped &#038; back into a
    // bare & and make this assertion pass on unescaped output. The suite
    // caught exactly that mistake on its first run.
    $needsSep = strpos($href, 'dealer_id=') !== false;
    check(!$needsSep || strpos($rawTag, '&#038;dealer_id=') !== false,
        'href separator was escaped at render time');
    check(strpos($html, '<?php') === false,
        'no unexecuted PHP left in the emitted markup');
}

// ---- JS artefact ---------------------------------------------------------
printf("\n  case: slp_avalon.js handler\n");

$tmp = sys_get_temp_dir() . '/suite-v024-artefact.js';
file_put_contents($tmp, $js);
$out = [];
exec('node --check ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
check($rc === 0, 'slp_avalon.js parses' . ($rc === 0 ? '' : ': ' . implode(' ', $out)));

check(substr_count($js, 'a.store_locator_contact_store_button') === 1,
    'exactly one binding on the anchor class');

check(preg_match('/jQuery\(document\)\.on\(\s*"click",\s*"a\.store_locator_contact_store_button"/', $js) === 1,
    'binding is delegated on document, not bound direct');

// The guard has to come before preventDefault or the link dies on any page
// without the modal. Assert the order, not just the presence of both.
$posGuard = strpos($js, '$modal.length === 0');
$posPrev  = strpos($js, 'event.preventDefault();');
$posPrevLast = strrpos($js, 'event.preventDefault();');
check($posGuard !== false && $posPrevLast !== false && $posGuard < $posPrevLast,
    'modal guard precedes preventDefault');

check(strpos($js, 'jQuery(this).attr("data-dealer-id")') !== false,
    'dealer id read with attr(), not data()');

check(substr_count($js, '#input_14_8') === 1,
    'Gravity Forms field 14_8 written exactly once');

foreach (['open-modal', 'show', 'overflow-hidden'] as $cls) {
    check(strpos($js, '"' . $cls . '"') !== false, "adds the $cls class");
}

// Closing is main.js's job. If this file ever grows a removeClass for the
// modal, the two files are fighting over the same state.
check(strpos($js, 'removeClass("open-modal")') === false,
    'does not also handle closing (that stays in main.js)');

printf("\n  score: %d pass / %d fail / %d total\n", $pass, $fail, $pass + $fail);
exit($fail > 0 ? 1 : 0);
