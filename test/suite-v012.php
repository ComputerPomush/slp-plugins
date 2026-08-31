<?php
/**
 * SLP Dealer Guard - v0.0.12 suite. State and province name search.
 *
 * The JS suites load the built artefact into a vm context so they exercise the
 * file that ships. class.slp_avalon.php cannot be loaded that way - it calls
 * add_action() at construction and needs WordPress - so this lifts the four
 * methods under test straight out of the artefact with PHP's own tokenizer and
 * runs them. Same principle: the code under test is the code that ships, not a
 * retyped copy of it.
 *
 * token_get_all() rather than brace counting. The first version of this
 * extractor matched the closing brace by indentation and cut v0.0.11's
 * get_state_initial() in half, because its `if ($key !== false){` block closes
 * at the same depth the function does. Braces inside strings and comments
 * arrive as part of a single token, so the tokenizer cannot make that mistake.
 *
 * NEGATIVE CONTROL, decision 20: run against the v0.0.11 artefact first and
 * confirm it FAILS. It should fail on every normalize_search_address case (the
 * method does not exist there), on every all-caps lookup, on all 13 provinces
 * and on the entry count. If it passes anything it should not, the extractor
 * is picking up the wrong methods.
 *
 *   php test/suite-v012.php <path-to-class.slp_avalon.php>
 */

declare(strict_types=1);

$artefact = $argv[1] ?? __DIR__ . '/../out12/class.slp_avalon.php';
if (!is_readable($artefact)) {
    fwrite(STDERR, "cannot read $artefact\n");
    exit(2);
}

/**
 * Lift whole method bodies out of the artefact by name.
 *
 * Missing methods are skipped rather than fatal, so the negative control
 * reports every failed assertion instead of dying on the first absent one.
 * The JS suites guard install_options_hook() the same way.
 */
function extract_methods(string $src, array $wanted): string
{
    $tokens = token_get_all($src);
    $n      = count($tokens);
    $out    = '';

    for ($i = 0; $i < $n; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }
        $name = null;
        for ($j = $i + 1; $j < $n; $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                $name = $tokens[$j][1];
                break;
            }
            if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE], true)) {
                continue;
            }
            break;
        }
        if ($name === null || !in_array($name, $wanted, true)) {
            continue;
        }

        $text = '';
        $depth = 0;
        $open  = false;
        for ($k = $i; $k < $n; $k++) {
            $s = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
            $text .= $s;
            if ($s === '{') {
                $depth++;
                $open = true;
            } elseif ($s === '}') {
                $depth--;
                if ($open && $depth === 0) {
                    break;
                }
            }
        }
        $out .= "    public " . $text . "\n\n";
    }
    return $out;
}

$methods = extract_methods(
    file_get_contents($artefact),
    ['get_states', 'get_state_aliases', 'get_state_lookup',
     'normalize_search_address', 'is_state', 'get_state_initial']
);

$shim = tempnam(sys_get_temp_dir(), 'slp') . '.php';
file_put_contents(
    $shim,
    "<?php\nclass Slp_State_Shim {\n" . $methods .
    "    // Absent methods return null instead of fataling, so the negative\n" .
    "    // control reports every assertion rather than the first one.\n" .
    "    public function __call(\$n, \$a) { return null; }\n}\n"
);
require $shim;
unlink($shim);

$s      = new Slp_State_Shim();
$passed = 0;
$failures = [];

function ck($got, $want, string $label): void
{
    global $passed, $failures;
    if ($got === $want) {
        $passed++;
        return;
    }
    $failures[] = sprintf('%s (expected %s, got %s)',
        $label, var_export($want, true), var_export($got, true));
}

/* ------------------------------------------- normalize_search_address */

/* The suffix strip is anchored to the END of the string. The old
   str_replace(" USA", ...) matched anywhere; harmless for USA, but once
   Canada joined it, "La Canada Flintridge" - a real city in California -
   would have become "La Flintridge". */
foreach ([
    ['Michigan, USA',            'Michigan'],
    ['Michigan,USA',             'Michigan'],
    ['Michigan USA',             'Michigan'],
    ['michigan, usa',            'michigan'],
    ['Ontario, Canada',          'Ontario'],
    ['ONTARIO, CANADA',          'ONTARIO'],
    ['Ontario Canada',           'Ontario'],
    ['Detroit, MI',              'Detroit MI'],
    ['  Ontario  ',              'Ontario'],
    ['La Canada Flintridge, CA', 'La Canada Flintridge CA'],
    ['no address entered',       'no address entered'],
] as [$in, $want]) {
    ck($s->normalize_search_address($in), $want, "normalize \"$in\"");
}
/* 25 of 308 records carry a malformed state; PHP 8.4 deprecates passing null
   to string functions and error.log is publicly reachable (s10.6). */
ck($s->normalize_search_address(null), '', 'normalize null casts rather than warning');

/* --------------------------------- the defect: case-insensitive lookup */

/* Measured on Aura DEV before this build: address=Michigan returned 35,
   address=MICHIGAN returned 3. The field renders in caps, so all-caps input
   is what the UI invites. */
foreach ([
    'Michigan' => 'MI', 'michigan' => 'MI', 'MICHIGAN' => 'MI', 'MiChIgAn' => 'MI',
    'New Hampshire' => 'NH', 'NEW YORK' => 'NY', 'District of Columbia' => 'DC',
    'Ontario' => 'ON', 'ONTARIO' => 'ON', 'ontario' => 'ON',
    'Quebec' => 'QC', "Qu\u{e9}bec" => 'QC',
    'Newfoundland' => 'NL', 'Newfoundland and Labrador' => 'NL',
    'Yukon' => 'YT', 'Yukon Territory' => 'YT',
    'British Columbia' => 'BC', 'Nunavut' => 'NU', 'Saskatchewan' => 'SK',
] as $in => $want) {
    ck($s->get_state_initial($in), $want, "get_state_initial \"$in\"");
}

/* Bare two-letter codes must NOT resolve. IN, OR, OK, HI, ME, DE, LA, MA, MS,
   MT and CO are ordinary English words. Asserted so nobody "improves" this
   later and sends a visitor searching "or" to Oregon. */
foreach (['OR', 'IN', 'OK', 'HI', 'ME', 'DE', 'LA', 'MI', 'ON', 'QC'] as $c) {
    ck($s->get_state_initial($c), false, "bare code \"$c\" is not a state name");
}

/* The five US territories are deliberately absent - s0.9, no dealers in any of
   them, so adding them changes no output and cannot be regression-tested. */
foreach (['Puerto Rico', 'Guam', 'American Samoa'] as $t) {
    ck($s->get_state_initial($t), false, "territory \"$t\" deliberately not added");
}

foreach (['Detroit MI', '48127', 'M5H 2N2', '', 'no address entered', 'Zzqq Notastate'] as $t) {
    ck($s->is_state($t), false, "is_state(\"$t\") is false");
}
ck($s->is_state('MICHIGAN'), true, 'is_state("MICHIGAN") is true');
ck($s->is_state($s->normalize_search_address('Ontario, Canada')), true,
    'autocomplete text "Ontario, Canada" survives normalisation and resolves');

/* ------------------------------------------------------ table integrity */

$states = $s->get_states();
ck(is_array($states) ? count($states) : 0, 64, '51 US + DC + 13 CA = 64 entries');
if (is_array($states)) {
    ck(count($states), count(array_unique($states)), 'no duplicate names');
    ck(count(array_keys($states)), count(array_unique(array_keys($states))), 'no duplicate codes');
    foreach (['AB', 'BC', 'MB', 'NB', 'NL', 'NS', 'NT', 'NU', 'ON', 'PE', 'QC', 'SK', 'YT'] as $p) {
        ck(isset($states[$p]), true, "province $p present");
    }
}

$total = $passed + count($failures);
$name  = basename($artefact);
if (!$failures) {
    echo "  $name :: suite-v012: $passed/$total assertions PASS\n";
    exit(0);
}
echo "  $name :: suite-v012: $passed/$total PASS, " . count($failures) . " FAIL\n";
foreach ($failures as $f) {
    echo "      FAIL  $f\n";
}
exit(1);
