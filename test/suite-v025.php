<?php
/**
 * suite-v025.php - validates the /contact-dealer redirect and the form-14
 * populator.
 *
 * Two artefacts, two kinds of assertion.
 *
 * The PHP half EXECUTES the handler. avalon_contact_dealer_redirect and
 * avalon_contact_dealer_resolve are lifted out of the class file verbatim and
 * wrapped in a test class, so $this-> resolves exactly as it does in
 * production and no source is rewritten to make it testable. wp_safe_redirect
 * is stubbed to record its arguments and throw; the throw unwinds before the
 * real exit; is reached, so the exit; stays in the code under test rather than
 * being edited out. $wpdb is scripted per signal, which is what makes the
 * two-signal fallback observable: signal 1 empty and signal 2 populated is a
 * case the production database cannot easily be forced into.
 *
 * The JS half asserts on the artefact text of slp_avalon.js. The populator is
 * a jQuery binding and cannot run here without a DOM, so what is asserted is
 * the shape that makes it correct: Aimbase reached through typeof rather than
 * a bare truthiness test, field 14_8 written only into an empty field so the
 * locator's per-click value survives, and all three binding points present
 * because no one of them fires reliably on its own. The file is also handed to
 * node --check, so a syntax error in the appended block cannot pass.
 *
 * Usage: php suite-v025.php <path-to-class.slp_avalon.php> <path-to-slp_avalon.js>
 */

$clsPath = $argv[1] ?? 'build/out25/class.slp_avalon.php';
$jsPath  = $argv[2] ?? 'build/out25/slp_avalon.js';

$code = file_get_contents($clsPath);
if ($code === false) {
    fwrite(STDERR, "cannot read {$clsPath}\n");
    exit(2);
}
$js = file_get_contents($jsPath);
if ($js === false) {
    fwrite(STDERR, "cannot read {$jsPath}\n");
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
/* Lift the two methods out of the artefact, verbatim.                 */
/* ------------------------------------------------------------------ */

$start = strpos($code, 'public function avalon_contact_dealer_redirect()');
$end   = strpos($code, 'public function avalon_orphan_redirect_map()');

/* A build without the methods is not an abort. It is a build that fails every
   PHP assertion, which is what a negative control has to be able to show. */
$phpOk   = ($start !== false && $end !== false && $end > $start);
$methods = $phpOk ? substr($code, $start, $end - $start) : '';
if (! $phpOk) {
    echo "\n  NOTE: avalon_contact_dealer_redirect absent from this build.\n";
    echo "        Every PHP assertion below fails by construction.\n";
}

/* ------------------------------------------------------------------ */
/* Stubs.                                                              */
/* ------------------------------------------------------------------ */

class RedirectHappened extends Exception
{
    public $location;
    public $status;
    public function __construct($location, $status)
    {
        parent::__construct('redirect');
        $this->location = $location;
        $this->status   = $status;
    }
}

function is_admin() { return false; }
function nocache_headers() { }
function home_url($path = '') { return 'https://example.test' . $path; }
function get_permalink($id)
{
    $map = array(
        140017 => 'https://example.test/store/coty-marine-llc/',
        140018 => 'https://example.test/store/second-dealer/',
        140099 => '',                      // a post that has no permalink
    );
    return $map[$id] ?? false;
}
function wp_safe_redirect($location, $status = 302)
{
    throw new RedirectHappened($location, $status);
}

class WpdbStub
{
    public $prefix   = 'wp_';
    public $posts    = 'wp_posts';
    public $postmeta = 'wp_postmeta';
    public $signal1  = null;   // sl_linked_postid join
    public $signal2  = null;   // slp_location_id meta join
    public $queries  = array();

    public function prepare($sql, ...$args)
    {
        foreach ($args as $a) {
            $sql = preg_replace('/%[ds]/', (string) $a, $sql, 1);
        }
        return $sql;
    }
    public function get_var($sql)
    {
        $this->queries[] = $sql;
        if (strpos($sql, 'sl_linked_postid') !== false) { return $this->signal1; }
        if (strpos($sql, 'slp_location_id')  !== false) { return $this->signal2; }
        return null;
    }
}

if ($phpOk) {
    eval('class SLPAvalonUnderTest { ' . $methods . ' }');
}

/**
 * Drive one request. Returns array(location, status, queries) or null when
 * the handler declined to act.
 */
function drive($requestUri, $get, $signal1 = null, $signal2 = null)
{
    global $wpdb, $phpOk;
    if (! $phpOk) { return null; }
    $wpdb = new WpdbStub();
    $wpdb->signal1 = $signal1;
    $wpdb->signal2 = $signal2;

    $_SERVER['REQUEST_URI'] = $requestUri;
    $_GET = $get;

    $obj = new SLPAvalonUnderTest();
    try {
        $obj->avalon_contact_dealer_redirect();
    } catch (RedirectHappened $e) {
        return array($e->location, $e->status, $wpdb->queries);
    }
    return null;
}

echo "\n  PHP - avalon_contact_dealer_redirect, executed\n";

/* 1. the happy path: signal 1 resolves */
$r = drive('/contact-dealer?store_id=104557&dealer_id=FDNJCO007',
           array('store_id' => '104557', 'dealer_id' => 'FDNJCO007'), 140017, null);
check($r !== null, 'a /contact-dealer request redirects');
check($r && $r[0] === 'https://example.test/store/coty-marine-llc/#contact-dealer',
      'lands on the dealer page carrying the #contact-dealer fragment');
check($r && $r[1] === 302, 'status is 302, not 301');
check($r && count($r[2]) === 1,
      'signal 1 answering stops the resolver - signal 2 is never queried');
check($r && strpos($r[2][0], '104557') !== false,
      'the store_id from the URL reaches the query');
check($r && strpos($r[2][0], "post_status = 'publish'") !== false,
      'only published pages are resolvable');

/* 2. signal 1 empty, signal 2 answers - the case production cannot force */
$r = drive('/contact-dealer?store_id=104557', array('store_id' => '104557'), null, 140018);
check($r && $r[0] === 'https://example.test/store/second-dealer/#contact-dealer',
      'signal 2 answers when the SLP link is broken');
check($r && count($r[2]) === 2, 'both signals were consulted, in order');
check($r && strpos($r[2][1], 'slp_location_id') !== false,
      'the second query is the postmeta join');

/* 3. neither signal resolves */
$r = drive('/contact-dealer?store_id=999999', array('store_id' => '999999'), null, null);
check($r && $r[0] === 'https://example.test/find-a-dealer/',
      'an unresolvable store_id falls back to the locator');
check($r && $r[1] === 302, 'the fallback is also 302');
check($r && strpos($r[0], '#contact-dealer') === false,
      'the fallback carries no fragment - there is no modal to open');

/* 4. no store_id at all */
$r = drive('/contact-dealer', array(), 140017, 140018);
check($r && $r[0] === 'https://example.test/find-a-dealer/',
      'a missing store_id falls back to the locator');
check($r && count($r[2]) === 0,
      'a missing store_id short-circuits before any query runs');

/* 5. store_id values that must never reach a query */
foreach (array('0', '-5', 'abc', '') as $bad) {
    $r = drive('/contact-dealer', array('store_id' => $bad), 140017, 140018);
    $ok = ($r && $r[0] === 'https://example.test/find-a-dealer/' && count($r[2]) === 0);
    check($ok, "store_id " . var_export($bad, true) . " is rejected before any query");
}

/* 5b. A mangled store_id with a valid leading integer is not rejected - it
   resolves to that integer. The (int) cast is the sanitiser, and asserting
   that it strips the tail is worth more than asserting a refusal that the
   code does not and should not make. */
$r = drive('/contact-dealer', array('store_id' => '104557; DROP TABLE wp_posts'), 140017, null);
check($r && $r[0] === 'https://example.test/store/coty-marine-llc/#contact-dealer',
      'a mangled store_id with a valid leading integer still resolves');
check($r && count($r[2]) === 1 && stripos($r[2][0], 'DROP') === false,
      'the (int) cast strips the tail before the query is prepared');

/* 6. trailing slash, and a permalink that comes back empty */
$r = drive('/contact-dealer/?store_id=104557', array('store_id' => '104557'), 140017, null);
check($r && $r[0] === 'https://example.test/store/coty-marine-llc/#contact-dealer',
      'the trailing-slash form matches too');
$r = drive('/contact-dealer', array('store_id' => '104557'), 140099, null);
check($r && $r[0] === 'https://example.test/find-a-dealer/',
      'a resolved post with no permalink still falls back rather than emitting a bare fragment');

/* 7. paths the handler must not touch */
foreach (array('/store/coty-marine-llc/', '/find-a-dealer/', '/', '/contact-dealer-x/',
               '/x/contact-dealer') as $path) {
    $r = drive($path, array('store_id' => '104557'), 140017, null);
    check($phpOk && $r === null, "declines {$path}");
}

/* ------------------------------------------------------------------ */
/* JS half                                                             */
/* ------------------------------------------------------------------ */

echo "\n  JS - slp_avalon.js artefact shape\n";

check(substr_count($js, 'function avalonPopulateContactForm()') === 1,
      'the populator is declared exactly once');
check(substr_count($js, 'avalonPopulateContactForm();') === 4,
      'it is called from all four sites');
check(substr_count($js, 'typeof Aimbase === "undefined"') === 1,
      'Aimbase is reached through typeof');
check(strpos($js, 'if (Aimbase)') === false,
      'no bare truthiness test on Aimbase survives anywhere in the file');
check(substr_count($js, 'jQuery.trim($dealer.val()) === ""') === 1,
      'field 14_8 is written only into an empty field');
check(substr_count($js, '"#input_14_12"') === 1 && substr_count($js, '"#input_14_13"') === 1,
      'both UID fields are written exactly once');
check(substr_count($js, 'Aimbase.Analytics.GetUserUid()') === 1 &&
      substr_count($js, 'Aimbase.Analytics.GetSessionUid()') === 1,
      'both UIDs come from Aimbase.Analytics, one call each');
check(substr_count($js, 'catch (e)') >= 2,
      'both UID reads are wrapped against a throwing accessor');
check(substr_count($js, 'jQuery(document).on("gform_post_render"') === 1,
      'bound to gform_post_render, which fires after every AJAX re-render');
check(substr_count($js, '"click", "#gform_submit_button_14"') === 1,
      'bound to the submit button click, which precedes a programmatic submit');
check(substr_count($js, '"submit", "#gform_14"') === 1,
      'bound to the form submit');
check(substr_count($js, 'window.location.hash !== "#contact-dealer"') === 1,
      'the hash opener is present exactly once');
check(strpos($js, 'a.store_locator_contact_store_button[data-dealer-id]') !== false,
      'the dealer id is read from the anchor class, never from an ancestor');
check(strpos($js, '.results_wrapper .store_locator_contact_store') === false,
      'none of the selectors that miss on a store page have crept back in');
check(substr_count($js, 'jQuery(document).on("click", "a.store_locator_contact_store_button"') === 1,
      'the v0.0.24 open handler is still present and unduplicated');

/* the hash opener must guard on the modal before touching classes */
$hashPos  = strpos($js, 'window.location.hash !== "#contact-dealer"');
$tail     = $hashPos === false ? '' : substr($js, $hashPos);
$guardPos = strpos($tail, '$modal.length === 0');
$classPos = strpos($tail, '$modal.addClass("open-modal")');
check($guardPos !== false && $classPos !== false && $guardPos < $classPos,
      'the hash opener checks the modal exists before adding the open class');

/* node --check: a syntax error in the appended block cannot pass */
$tmp = tempnam(sys_get_temp_dir(), 'slpjs') . '.js';
file_put_contents($tmp, $js);
$out = array();
$rc  = 0;
exec('node --check ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
@unlink($tmp);
check($rc === 0, 'node --check parses slp_avalon.js' . ($rc === 0 ? '' : ': ' . implode(' ', $out)));

/* ------------------------------------------------------------------ */

printf("\n  %d passed, %d failed, %d total\n\n", $pass, $fail, $pass + $fail);
exit($fail === 0 ? 0 : 1);
