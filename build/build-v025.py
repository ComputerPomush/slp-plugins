#!/usr/bin/env python3
"""
build-v025.py

slp_avalon v0.0.24 -> v0.0.25.

WHAT THIS RELEASE DOES
----------------------
1. /contact-dealer resolves. The anchor this plugin emits has always
   pointed at /contact-dealer?store_id=<id>&dealer_id=<identifier>, and
   that path has never resolved on any brand. Measured 2026-09-13:

       Aura DEV     404
       Aura LIVE    404
       Tahoe LIVE   404
       Avalon LIVE  301 -> homepage, both parameters discarded

   A template_redirect handler now resolves store_id to the dealer's own
   store page and sends the visitor there with a #contact-dealer
   fragment. Unresolvable goes to /find-a-dealer/.

2. Gravity Form 14's three hidden fields are populated from page state
   at render, at submit-button click, and at submit - not from the click
   that opened the modal. That click is the wrong source twice over: a
   visitor arriving on /store/<slug>/#contact-dealer never makes it, and
   Gravity Forms discards anything written once when it re-renders the
   form through gform_ajax_frame_14 after a failed validation.

   14_8  dealer id          - page constant, from the one anchor carrying it
   14_12 Aimbase UserUid    - from Aimbase.Analytics, guarded with typeof
   14_13 Aimbase SessionUid - from Aimbase.Analytics, guarded with typeof

   Entry 709 reached both Gravity Forms and Aimbase with 14_12 and 14_13
   empty, because contact_dealer_aimbase.php binds the same three
   selectors main.js does and none of them match a store page.

ENCODING
--------
ISO-8859-1 throughout with newline='' so CRLF and any high bytes survive
byte-for-byte. slp_avalon.js has no trailing newline and its final line
carries two trailing spaces; both are preserved deliberately.

Usage:  python build-v025.py <src_dir> <out_dir>

        src_dir must hold the three v0.0.24 files, flat:
            class.slp_avalon.php
            slp_avalon.js
            slp_avalon.php
"""

import hashlib
import io
import os
import sys

CRLF = "\r\n"

PINS = {
    'class.slp_avalon.php': ('efdedfd75f0ad15ed6ffde7fec0cd5e4', 117323),
    'slp_avalon.js':        ('461fb6b2e43decd9326f5d1014529b01', 73466),
    'slp_avalon.php':       ('fb2da0394b4540b1437d3c00f0283070', 1808),
}


def read_exact(path):
    raw = io.open(path, 'rb').read()
    return raw.decode('iso-8859-1'), hashlib.md5(raw).hexdigest(), len(raw)


def write_exact(path, text):
    raw = text.encode('iso-8859-1')
    io.open(path, 'wb').write(raw)
    return hashlib.md5(raw).hexdigest(), len(raw), raw.count(b'\r')


def sub_once(text, old, new, label):
    n = text.count(old)
    if n != 1:
        sys.exit("ABORT {}: anchor matched {} times, expected exactly 1".format(label, n))
    print("  patched   {}".format(label))
    return text.replace(old, new, 1)


def check(cond, label):
    if not cond:
        sys.exit("ABORT self-check: {}".format(label))
    print("  check OK  {}".format(label))


# ===========================================================================
# PHP - the redirect handler and its resolver
# ===========================================================================

PHP_HOOK_ANCHOR = (
    "            add_action('template_redirect', array(self::$instance,'avalon_orphan_redirect'), 1);" + CRLF
)

PHP_HOOK_NEW = PHP_HOOK_ANCHOR + (
    "            //v0.0.25. Same priority, different path. The two handlers" + CRLF +
    "            //cannot collide: one matches ^/store/<slug>/?$ and the" + CRLF +
    "            //other ^/contact-dealer/?$." + CRLF +
    "            add_action('template_redirect', array(self::$instance,'avalon_contact_dealer_redirect'), 1);" + CRLF
)

PHP_METHOD_ANCHOR = "        public function avalon_orphan_redirect_map()" + CRLF

PHP_METHOD_NEW = (
    "        /**" + CRLF +
    "         * v0.0.25. /contact-dealer -> the dealer's own store page." + CRLF +
    "         *" + CRLF +
    "         * A page is not the answer here. Gravity Form 14 is already" + CRLF +
    "         * rendered on every store page, and a second render of the same" + CRLF +
    "         * form takes a Gravity Forms instance suffix, which would break" + CRLF +
    "         * the element ids find-a-dealer-focus-trap.js hardcodes. So the" + CRLF +
    "         * request is sent back to the page that already holds the form." + CRLF +
    "         *" + CRLF +
    "         * A fragment, not a query argument. Store pages are cached;" + CRLF +
    "         * ?contact=1 would either miss the cache or fragment it into" + CRLF +
    "         * variants. A fragment never reaches the server at all." + CRLF +
    "         *" + CRLF +
    "         * Not keyed on the referrer. It is stripped by privacy settings" + CRLF +
    "         * and absent when a link is pasted or mailed - and it is not" + CRLF +
    "         * needed, because store_id is in the URL and this plugin owns" + CRLF +
    "         * the map from it to the page." + CRLF +
    "         */" + CRLF +
    "        public function avalon_contact_dealer_redirect()" + CRLF +
    "        {" + CRLF +
    "            if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {" + CRLF +
    "                return;" + CRLF +
    "            }" + CRLF +
    "            if (empty($_SERVER['REQUEST_URI'])) {" + CRLF +
    "                return;" + CRLF +
    "            }" + CRLF +
    "" + CRLF +
    "            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);" + CRLF +
    "            if (! is_string($path)) {" + CRLF +
    "                return;" + CRLF +
    "            }" + CRLF +
    "            if (! preg_match('#^/contact-dealer/?$#', $path)) {" + CRLF +
    "                return;" + CRLF +
    "            }" + CRLF +
    "" + CRLF +
    "            $store_id = isset($_GET['store_id']) ? (int) $_GET['store_id'] : 0;" + CRLF +
    "            $post_id  = $this->avalon_contact_dealer_resolve($store_id);" + CRLF +
    "" + CRLF +
    "            nocache_headers();" + CRLF +
    "" + CRLF +
    "            if ($post_id > 0) {" + CRLF +
    "                $permalink = get_permalink($post_id);" + CRLF +
    "                if (is_string($permalink) && $permalink !== '') {" + CRLF +
    "                    //302, not 301. The destination is derived from a query" + CRLF +
    "                    //argument, and a permanently cached redirect would" + CRLF +
    "                    //outlive any correction to the row behind it." + CRLF +
    "                    wp_safe_redirect($permalink . '#contact-dealer', 302);" + CRLF +
    "                    exit;" + CRLF +
    "                }" + CRLF +
    "            }" + CRLF +
    "" + CRLF +
    "            //Unresolvable is not worth an error page. The locator is the" + CRLF +
    "            //honest destination for \"which dealer is not known\"." + CRLF +
    "            wp_safe_redirect(home_url('/find-a-dealer/'), 302);" + CRLF +
    "            exit;" + CRLF +
    "        }" + CRLF +
    "" + CRLF +
    "        /**" + CRLF +
    "         * v0.0.25. store_id -> published store_page ID, or 0." + CRLF +
    "         *" + CRLF +
    "         * Two independent signals, in order of authority." + CRLF +
    "         * sl_linked_postid is what SLP itself maintains and what the" + CRLF +
    "         * 308/308 reconcile is measured against. slp_location_id is this" + CRLF +
    "         * plugin's own meta, written when the page is created, and still" + CRLF +
    "         * names the right page when an SLP link has been broken after" + CRLF +
    "         * the fact." + CRLF +
    "         *" + CRLF +
    "         * dealer_id travels in the URL and is deliberately not used for" + CRLF +
    "         * resolution. It lives in the extended-data table under a shape" + CRLF +
    "         * this method has not measured, and store_id is present in every" + CRLF +
    "         * anchor this plugin has ever emitted." + CRLF +
    "         */" + CRLF +
    "        private function avalon_contact_dealer_resolve($store_id)" + CRLF +
    "        {" + CRLF +
    "            $store_id = (int) $store_id;" + CRLF +
    "            if ($store_id <= 0) {" + CRLF +
    "                return 0;" + CRLF +
    "            }" + CRLF +
    "" + CRLF +
    "            global $wpdb;" + CRLF +
    "            $table = $wpdb->prefix . 'store_locator';" + CRLF +
    "" + CRLF +
    "            $id = (int) $wpdb->get_var($wpdb->prepare(" + CRLF +
    "                \"SELECT p.ID" + CRLF +
    "                   FROM {$table} s" + CRLF +
    "                   JOIN {$wpdb->posts} p ON p.ID = s.sl_linked_postid" + CRLF +
    "                  WHERE s.sl_id       = %d" + CRLF +
    "                    AND p.post_type   = 'store_page'" + CRLF +
    "                    AND p.post_status = 'publish'" + CRLF +
    "                  LIMIT 1\"," + CRLF +
    "                $store_id" + CRLF +
    "            ));" + CRLF +
    "            if ($id > 0) {" + CRLF +
    "                return $id;" + CRLF +
    "            }" + CRLF +
    "" + CRLF +
    "            $id = (int) $wpdb->get_var($wpdb->prepare(" + CRLF +
    "                \"SELECT p.ID" + CRLF +
    "                   FROM {$wpdb->postmeta} pm" + CRLF +
    "                   JOIN {$wpdb->posts} p ON p.ID = pm.post_id" + CRLF +
    "                  WHERE pm.meta_key   = 'slp_location_id'" + CRLF +
    "                    AND pm.meta_value = %s" + CRLF +
    "                    AND p.post_type   = 'store_page'" + CRLF +
    "                    AND p.post_status = 'publish'" + CRLF +
    "                  LIMIT 1\"," + CRLF +
    "                (string) $store_id" + CRLF +
    "            ));" + CRLF +
    "            return ($id > 0) ? $id : 0;" + CRLF +
    "        }" + CRLF +
    "" + CRLF
) + PHP_METHOD_ANCHOR


# ===========================================================================
# JS - populate from page state, and open on arrival
# ===========================================================================

JS_ANCHOR = (
    'jQuery("body").addClass("overflow-hidden");' + CRLF +
    '  });' + CRLF +
    '  '
)

JS_NEW = JS_ANCHOR + (
    CRLF +
    "  /**" + CRLF +
    "   * v0.0.25. Populate Gravity Form 14's three hidden fields from page" + CRLF +
    "   * state rather than from the click that opened the modal." + CRLF +
    "   *" + CRLF +
    "   * The click is the wrong source twice over. A visitor arriving on" + CRLF +
    "   * /store/<slug>/#contact-dealer never makes it, and Gravity Forms" + CRLF +
    "   * discards anything written once when it re-renders the form through" + CRLF +
    "   * gform_ajax_frame_14 after a failed validation." + CRLF +
    "   *" + CRLF +
    "   *   14_8  dealer id - a page constant on a store page, read from the" + CRLF +
    "   *         one anchor that carries it. Written only into an empty" + CRLF +
    "   *         field, so the locator's per-click value is never" + CRLF +
    "   *         overwritten. The locator has no anchor of this class, so" + CRLF +
    "   *         this is inert there." + CRLF +
    "   *   14_12 Aimbase UserUid" + CRLF +
    "   *   14_13 Aimbase SessionUid" + CRLF +
    "   *" + CRLF +
    "   * typeof, not a bare reference. WP Rocket delays awa.js on every" + CRLF +
    "   * cached page, so Aimbase is undeclared - not merely falsy - until" + CRLF +
    "   * the visitor's first interaction. A bare truthiness test on that" + CRLF +
    "   * identifier throws a ReferenceError there rather than reading as" + CRLF +
    "   * false, which is Fault B3 in contact_dealer_aimbase.php." + CRLF +
    "   *" + CRLF +
    "   * Called from three places because no one of them is sufficient:" + CRLF +
    "   * gform_post_render fires at first render and after every AJAX" + CRLF +
    "   * re-render but can precede awa.js; submit is authoritative but does" + CRLF +
    "   * not fire when Gravity Forms submits the form programmatically; the" + CRLF +
    "   * submit button's own click precedes both. The function is" + CRLF +
    "   * idempotent, so running three times costs nothing." + CRLF +
    "   */" + CRLF +
    "  function avalonPopulateContactForm() {" + CRLF +
    '    var $dealer = jQuery("#input_14_8");' + CRLF +
    '    if ($dealer.length > 0 && jQuery.trim($dealer.val()) === "") {' + CRLF +
    '      var $anchor = jQuery("a.store_locator_contact_store_button[data-dealer-id]").first();' + CRLF +
    "      if ($anchor.length > 0) {" + CRLF +
    '        $dealer.val($anchor.attr("data-dealer-id") || "");' + CRLF +
    "      }" + CRLF +
    "    }" + CRLF +
    "" + CRLF +
    '    if (typeof Aimbase === "undefined" || !Aimbase || !Aimbase.Analytics) {' + CRLF +
    "      return;" + CRLF +
    "    }" + CRLF +
    "" + CRLF +
    '    var uid = "";' + CRLF +
    '    var sid = "";' + CRLF +
    '    try { uid = Aimbase.Analytics.GetUserUid() || ""; } catch (e) { uid = ""; }' + CRLF +
    '    try { sid = Aimbase.Analytics.GetSessionUid() || ""; } catch (e) { sid = ""; }' + CRLF +
    "" + CRLF +
    '    if (uid !== "") { jQuery("#input_14_12").val(uid); }' + CRLF +
    '    if (sid !== "") { jQuery("#input_14_13").val(sid); }' + CRLF +
    "  }" + CRLF +
    "" + CRLF +
    '  jQuery(document).on("gform_post_render", function () {' + CRLF +
    "    avalonPopulateContactForm();" + CRLF +
    "  });" + CRLF +
    '  jQuery(document).on("click", "#gform_submit_button_14", function () {' + CRLF +
    "    avalonPopulateContactForm();" + CRLF +
    "  });" + CRLF +
    '  jQuery(document).on("submit", "#gform_14", function () {' + CRLF +
    "    avalonPopulateContactForm();" + CRLF +
    "  });" + CRLF +
    "" + CRLF +
    "  /**" + CRLF +
    "   * v0.0.25. Open the modal for a visitor who arrived at" + CRLF +
    "   * /store/<slug>/#contact-dealer - the destination" + CRLF +
    "   * avalon_contact_dealer_redirect() sends /contact-dealer to." + CRLF +
    "   *" + CRLF +
    "   * Mirrors the click handler's guard: no modal, no action. The page" + CRLF +
    "   * then degrades to the dealer's address block and tel: link, both" + CRLF +
    "   * already rendered server-side, which is the whole point of having a" + CRLF +
    "   * destination that is a real page rather than an error." + CRLF +
    "   */" + CRLF +
    "  jQuery(function () {" + CRLF +
    '    if (window.location.hash !== "#contact-dealer") {' + CRLF +
    "      return;" + CRLF +
    "    }" + CRLF +
    '    var $modal = jQuery(".contact-dealer--pop-up");' + CRLF +
    "    if ($modal.length === 0) {" + CRLF +
    "      return;" + CRLF +
    "    }" + CRLF +
    '    $modal.addClass("open-modal");' + CRLF +
    '    jQuery(".modal-overlay").addClass("show");' + CRLF +
    '    jQuery("body").addClass("overflow-hidden");' + CRLF +
    "    avalonPopulateContactForm();" + CRLF +
    "  });"
)


def main():
    if len(sys.argv) != 3:
        sys.exit("usage: build-v025.py <src_dir> <out_dir>")
    src_dir, out_dir = sys.argv[1], sys.argv[2]
    if not os.path.isdir(out_dir):
        os.makedirs(out_dir)

    print("build-v025  slp_avalon 0.0.24 -> 0.0.25")
    print("")

    # ---- inputs -----------------------------------------------------------
    blobs = {}
    for name, (md5, size) in PINS.items():
        text, got_md5, got_size = read_exact(os.path.join(src_dir, name))
        if got_md5 != md5 or got_size != size:
            sys.exit("ABORT {}: got {} / {} bytes, expected {} / {} bytes".format(
                name, got_md5, got_size, md5, size))
        print("  input OK  {:<24} {} {} bytes".format(name, got_md5, got_size))
        blobs[name] = text
    print("")

    php  = blobs['class.slp_avalon.php']
    js   = blobs['slp_avalon.js']
    boot = blobs['slp_avalon.php']

    # ---- edits ------------------------------------------------------------
    php = sub_once(php, PHP_HOOK_ANCHOR, PHP_HOOK_NEW,
                   'php 1  register avalon_contact_dealer_redirect')
    php = sub_once(php, PHP_METHOD_ANCHOR, PHP_METHOD_NEW,
                   'php 2  redirect handler + resolver')
    js = sub_once(js, JS_ANCHOR, JS_NEW,
                  'js 1   populate form 14 + open on #contact-dealer')
    boot = sub_once(boot, " * Version: 0.0.24" + CRLF, " * Version: 0.0.25" + CRLF,
                    'php 3  version 0.0.24 -> 0.0.25')
    print("")

    # ---- structural self-checks ------------------------------------------
    check(php.count('avalon_contact_dealer_redirect') == 2,
          'redirect handler declared once and registered once')
    check(php.count('private function avalon_contact_dealer_resolve($store_id)') == 1,
          'resolver declared exactly once')
    check(php.count('$this->avalon_contact_dealer_resolve(') == 1,
          'resolver called exactly once')
    check(php.count("preg_match('#^/contact-dealer/?$#', $path)") == 1,
          'path guard present and singular')
    #Deltas, not censuses. v0.0.24 already carries one wp_safe_redirect and
    #one ", 301)" in the orphan map, and one 'slp_location_id' in the
    #relink. Counting bare literals would have asserted against those too.
    base_php = blobs['class.slp_avalon.php']
    check(php.count('wp_safe_redirect') - base_php.count('wp_safe_redirect') == 2,
          'exactly two new wp_safe_redirect exits')
    check(php.count(', 302)') - base_php.count(', 302)') == 2,
          'both new exits are 302')
    check(php.count(', 301)') == base_php.count(', 301)'),
          "the orphan map's own 301 is untouched")
    check(php.count("'slp_location_id'") - base_php.count("'slp_location_id'") == 1,
          'second signal joins the same meta key the relink uses')
    check(php.count('<?php') == blobs['class.slp_avalon.php'].count('<?php'),
          'no PHP open tags added or lost')
    check(php.count('{') - php.count('}') ==
          blobs['class.slp_avalon.php'].count('{') - blobs['class.slp_avalon.php'].count('}'),
          'php brace delta unchanged')

    check(js.count('function avalonPopulateContactForm()') == 1,
          'populator declared exactly once')
    check(js.count('avalonPopulateContactForm();') == 4,
          'populator called from post_render, button click, submit, hash open')
    check(js.count('typeof Aimbase === "undefined"') == 1,
          'Aimbase guarded with typeof')
    check(js.count('if (Aimbase)') == 0,
          'no bare Aimbase reference anywhere')
    check(js.count('"#input_14_12"') == 1 and js.count('"#input_14_13"') == 1,
          'both UID fields written exactly once')
    check(js.count('window.location.hash !== "#contact-dealer"') == 1,
          'hash opener present')
    check(js.count('{') == js.count('}'),
          'js braces balance')
    check(not js.endswith('\n'),
          'js still has no trailing newline')

    check(boot.count('Version: 0.0.25') == 1 and boot.count('Version: 0.0.24') == 0,
          'version arrived and the old one departed')
    print("")

    # ---- outputs ----------------------------------------------------------
    for name, text in (('class.slp_avalon.php', php),
                       ('slp_avalon.js', js),
                       ('slp_avalon.php', boot)):
        md5, size, crs = write_exact(os.path.join(out_dir, name), text)
        print("  output    {:<24} {} {} bytes  CR={}".format(name, md5, size, crs))

    print("")
    print("self-check ok")
    print("done")


if __name__ == '__main__':
    main()
