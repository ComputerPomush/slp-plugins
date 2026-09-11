<?php
/**
 * suite-v023.php - validates the emitted output of avalon_map_location_sc_func.
 *
 * Extracts the patched shortcode from build/out23, runs it against stubbed
 * SLP state, and asserts on the JavaScript it produces. The failure this
 * release fixes is a runtime ReferenceError, so parsing the generated script
 * is the assertion that counts - php -l cannot see it.
 *
 * Usage: php suite-v023.php <path-to-class.slp_avalon.php>
 */

$src = $argv[1] ?? 'build/out23/class.slp_avalon.php';
$code = file_get_contents($src);
if ($code === false) {
    fwrite(STDERR, "cannot read $src\n");
    exit(2);
}

// ---- extract the shortcode method, brace-matched -------------------------
$start = strpos($code, 'public function avalon_map_location_sc_func');
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
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($d) { return json_encode($d, JSON_UNESCAPED_SLASHES); }
}
if (!function_exists('esc_url_raw')) {
    function esc_url_raw($u) { return $u; }
}
if (!function_exists('shortcode_atts')) {
    function shortcode_atts($pairs, $atts, $sc = '') { return (array) $atts; }
}

eval($fn);

// ---- cases ---------------------------------------------------------------
$cases = [
    'icon set, style empty (current Aura state)' => [
        'map_end_icon' => 'https://example.test/wp-content/uploads/2026/08/mail-marker-recolored.png',
        'google_map_style' => '',
        'zoom_level' => '12',
    ],
    'icon empty' => [
        'map_end_icon' => '',
        'google_map_style' => '',
        'zoom_level' => '12',
    ],
    'valid style JSON' => [
        'map_end_icon' => 'https://example.test/pin.png',
        'google_map_style' => '[{"featureType":"poi","stylers":[{"visibility":"off"}]}]',
        'zoom_level' => '9',
    ],
    'malformed style JSON (must degrade, not break)' => [
        'map_end_icon' => 'https://example.test/pin.png',
        'google_map_style' => '[{"featureType":,,,}',
        'zoom_level' => '12',
    ],
    'out-of-range zoom (must clamp to 12)' => [
        'map_end_icon' => '',
        'google_map_style' => '',
        'zoom_level' => '99',
    ],
];

$pass = $fail = 0;
function check($ok, $label) {
    global $pass, $fail;
    if ($ok) { $pass++; printf("    [PASS] %s\n", $label); }
    else     { $fail++; printf("    [FAIL] %s\n", $label); }
}

foreach ($cases as $label => $opts) {
    printf("\n  case: %s\n", $label);

    $GLOBALS['slplus'] = (object) [
        'options' => $opts,
        'currentLocation' => (object) ['latitude' => '39.994238000', 'longitude' => '-74.149361000'],
    ];
    $html = avalon_map_location_sc_func([]);

    check($html !== '', 'shortcode produced output');
    check(strpos($html, 'slplus.') === false, 'no slplus.* reference remains');
    check(substr_count($html, 'id="avalon_location_map"') === 1, 'exactly one map container');

    preg_match('/<script>(.*?)<\/script>/s', $html, $m);
    check(!empty($m[1]), 'script block present');
    file_put_contents('/tmp/emitted.js', $m[1] ?? '');
    exec('node --check /tmp/emitted.js 2>&1', $out, $rc);
    check($rc === 0, 'emitted JavaScript parses' . ($rc === 0 ? '' : ': ' . implode(' ', $out)));
    $out = [];

    check(strpos($m[1], 'cameraControl: false') !== false, 'cameraControl disabled');
    check(strpos($m[1], 'zoomControl: true') !== false, 'zoomControl enabled');

    $wantZoom = ((int) $opts['zoom_level'] >= 1 && (int) $opts['zoom_level'] <= 21)
        ? (int) $opts['zoom_level'] : 12;
    check(strpos($m[1], "zoom: $wantZoom,") !== false, "zoom resolves to $wantZoom");

    $hasIcon = strpos($m[1], 'marker_options.icon') !== false;
    check($hasIcon === ($opts['map_end_icon'] !== ''), 'icon emitted only when configured');

    $styleValid = is_array(json_decode($opts['google_map_style'], true));
    $hasStyle = strpos($m[1], 'map_options.styles') !== false;
    check($hasStyle === $styleValid, 'styles emitted only when the JSON is valid');
}

printf("\n  score: %d pass / %d fail / %d total\n", $pass, $fail, $pass + $fail);
exit($fail > 0 ? 1 : 0);
