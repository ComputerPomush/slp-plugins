#!/usr/bin/env python3
"""
build-v024.py  -  slp_avalon v0.0.23 -> v0.0.24

Refuses to run unless all three inputs match their release pins byte-for-byte.
Every replacement asserts a unique anchor match before applying.
CRLF line endings are preserved throughout.

Changes
-------
class.slp_avalon.php
  1. avalon_store_contact_dealer_button_sc_func(): the Contact Dealer anchor
     gains data-dealer-id carrying the SLP location id, loses the inline
     style="font-size:18px !important;", and has its href run through
     esc_url().

     data-dealer-id is what lets the click handler populate Gravity Forms
     field 14_8 ("Dealer id"). On /find-a-dealer/ main.js derives that id from
     a slp_results_wrapper_<id> or slp_info_bubble_<id> ancestor; a store page
     has neither, so the id has to travel on the anchor itself.

     The inline font-size carried !important, which meant no stylesheet could
     match the locator button's measured 12px without escalating to !important
     of its own. Removing it here keeps that escalation out of the theme.

     The href is assembled from get_site_url() plus the location id and the
     feed-supplied identifier. esc_url() is correct for a URL in an attribute
     and closes a small injection surface on data this plugin does not author.

slp_avalon.js
  2. Delegated click handler on a.store_locator_contact_store_button that
     opens the shared .contact-dealer--pop-up modal.

     Bound on the anchor's own class, not on an ancestor: the markup around
     this button is frozen in post_content at page-creation time and has
     already drifted from the page_template option, so an ancestor-keyed
     selector is not stable. This also cannot collide with main.js, whose
     selectors all require ancestors a store page does not have.

     It returns early when the modal is absent, so the href stays a working
     link on any environment without the popup markup, and with JS disabled.

     Closing is not handled here. main.js binds .btn-cancel and .modal-overlay
     directly at ready against server-rendered markup, so it already works.

slp_avalon.php
  3. Version header 0.0.23 -> 0.0.24.

Usage:  python build-v024.py <src_dir> <out_dir>
"""

import hashlib
import io
import os
import sys

PINS = {
    'class.slp_avalon.php': ('960c8e0c91e5a22f417b417ec8b5835f', 116594),
    'slp_avalon.js':        ('61921f1feca574f9f4ba4b36b362d4a0', 71758),
    'slp_avalon.php':       ('6ac762b02006ee3274da98aa7c107277', 1808),
}

# Project knowledge flattens the dotted filename; accept either on input.
SRC_ALIASES = {
    'class.slp_avalon.php': ('class.slp_avalon.php', 'class_slp_avalon.php'),
    'slp_avalon.js':        ('slp_avalon.js',),
    'slp_avalon.php':       ('slp_avalon.php',),
}


def read_pinned(src_dir, canonical):
    for name in SRC_ALIASES[canonical]:
        path = os.path.join(src_dir, name)
        if os.path.exists(path):
            raw = io.open(path, 'rb').read()
            md5, size = hashlib.md5(raw).hexdigest(), len(raw)
            want_md5, want_size = PINS[canonical]
            if md5 != want_md5 or size != want_size:
                sys.exit(
                    f"ABORT {canonical}: got {md5} / {size} bytes, "
                    f"expected {want_md5} / {want_size} bytes"
                )
            print(f"  input OK  {canonical:24} {md5} {size} bytes")
            return raw.decode('utf-8')
    sys.exit(f"ABORT: none of {SRC_ALIASES[canonical]} found in {src_dir}")


def sub_once(text, old, new, label):
    n = text.count(old)
    if n != 1:
        sys.exit(f"ABORT {label}: anchor matched {n} times, expected exactly 1")
    print(f"  patched   {label}")
    return text.replace(old, new, 1)


def check(condition, label):
    if not condition:
        sys.exit(f"ABORT self-check: {label}")
    print(f"  check OK  {label}")


def main():
    if len(sys.argv) != 3:
        sys.exit("usage: build-v024.py <src_dir> <out_dir>")
    src_dir, out_dir = sys.argv[1], sys.argv[2]
    os.makedirs(out_dir, exist_ok=True)

    print("build-v024  slp_avalon 0.0.23 -> 0.0.24")
    print("verifying inputs against release pins")

    cls = read_pinned(src_dir, 'class.slp_avalon.php')
    js = read_pinned(src_dir, 'slp_avalon.js')
    boot = read_pinned(src_dir, 'slp_avalon.php')

    CR = '\r\n'

    # ---- edit 1: the Contact Dealer anchor --------------------------------
    old1 = (
        '                <div class="store_locator_single_contact_store">'
        '<a href="<?php echo $url; ?>" '
        'class="store_locator_contact_store_button btn button et_pb_button '
        'btn-primary theme-button btn-lg center" '
        'style="font-size:18px !important;">Contact Dealer</a></div>' + CR
    )
    new1 = (
        '                <?php' + CR +
        '                // data-dealer-id carries the SLP location id to the click' + CR +
        '                // handler in slp_avalon.js, which writes it into Gravity Forms' + CR +
        '                // field 14_8. On /find-a-dealer/ main.js reads that id from a' + CR +
        '                // slp_results_wrapper_<id> or slp_info_bubble_<id> ancestor. A' + CR +
        '                // store page has neither, so the id travels on the anchor.' + CR +
        '                //' + CR +
        '                // No inline font-size here. It carried !important, which forced' + CR +
        '                // any stylesheet trying to match the locator button to escalate' + CR +
        '                // to !important as well. Presentation belongs in the theme.' + CR +
        '                ?>' + CR +
        '                <div class="store_locator_single_contact_store">'
        '<a href="<?php echo esc_url($url); ?>" '
        'class="store_locator_contact_store_button btn button et_pb_button '
        'btn-primary theme-button btn-lg center" '
        'data-dealer-id="<?php echo esc_attr($location_id); ?>">Contact Dealer</a></div>' + CR
    )
    cls = sub_once(cls, old1, new1, 'edit 1  anchor: data-dealer-id, no inline font-size, esc_url')

    # ---- edit 2: the modal click handler ----------------------------------
    old2 = (
        '  function handleLocationError(browserHasGeolocation) {' + CR +
        '    if (browserHasGeolocation) {' + CR +
        '      alert("Error: The Geolocation service failed.");' + CR +
        '    } else {' + CR +
        '      alert("Error: Your browser doesn\'t support geolocation.");' + CR +
        '    }' + CR +
        '  }' + CR
    )
    new2 = old2 + (
        '' + CR +
        '  /**' + CR +
        '   * Store page Contact Dealer -> the shared contact-dealer modal.' + CR +
        '   *' + CR +
        '   * The modal, Gravity Form 14 and .modal-overlay are already rendered on' + CR +
        '   * store pages; only the wiring was missing. main.js binds the locator\'s' + CR +
        '   * own triggers beneath .results_wrapper and .slp_info_bubble ancestors' + CR +
        '   * that a store page does not have, and its third selector expects a class' + CR +
        '   * this anchor has never carried, so none of it fires here.' + CR +
        '   *' + CR +
        '   * Bound on the anchor\'s own class rather than on an ancestor: the markup' + CR +
        '   * around this button is frozen in post_content when the page is created' + CR +
        '   * and has already drifted from the page_template option, so an' + CR +
        '   * ancestor-keyed selector is not a stable thing to depend on. Keying on' + CR +
        '   * the class also guarantees no overlap with main.js.' + CR +
        '   *' + CR +
        '   * Returns early when the modal is absent, so the href stays a working' + CR +
        '   * link anywhere the popup markup is not rendered, and with JS disabled.' + CR +
        '   *' + CR +
        '   * Closing is not handled here. main.js binds .btn-cancel and' + CR +
        '   * .modal-overlay directly at ready against server-rendered markup.' + CR +
        '   *' + CR +
        '   * attr() not data(): jQuery data() coerces a numeric id to a Number and' + CR +
        '   * caches it. The form field wants the string exactly as emitted.' + CR +
        '   */' + CR +
        '  jQuery(document).on("click", "a.store_locator_contact_store_button", function (event) {' + CR +
        '    var $modal = jQuery(".contact-dealer--pop-up");' + CR +
        '    if ($modal.length === 0) {' + CR +
        '      return;' + CR +
        '    }' + CR +
        '    event.preventDefault();' + CR +
        '    jQuery("#input_14_8").val(jQuery(this).attr("data-dealer-id") || "");' + CR +
        '    $modal.addClass("open-modal");' + CR +
        '    jQuery(".modal-overlay").addClass("show");' + CR +
        '    jQuery("body").addClass("overflow-hidden");' + CR +
        '  });' + CR
    )
    js = sub_once(js, old2, new2, 'edit 2  delegated contact-dealer modal handler')

    # ---- edit 3: version bump --------------------------------------------
    old3 = ' * Version: 0.0.23' + CR
    new3 = ' * Version: 0.0.24' + CR
    boot = sub_once(boot, old3, new3, 'edit 3  version 0.0.23 -> 0.0.24')

    # ---- structural self-checks ------------------------------------------
    check(cls.count('data-dealer-id="<?php echo esc_attr($location_id); ?>"') == 1,
          'exactly one data-dealer-id emitted')
    check('style="font-size:18px !important;"' not in cls,
          'inline font-size !important is gone')
    check(cls.count('<?php echo $url; ?>') == 0 and cls.count('esc_url($url)') == 1,
          'href is escaped exactly once')
    check(js.count('a.store_locator_contact_store_button') == 1,
          'exactly one handler bound to the anchor class')
    check(js.count('.contact-dealer--pop-up') == 1,
          'modal selector appears once')
    check(js.count('#input_14_8') == 1,
          'dealer id field written once')
    check(js.count('{') == js.count('}') and js.count('(') == js.count(')'),
          'slp_avalon.js braces and parens still balance')
    check(cls.count('{') == cls.count('}'),
          'class.slp_avalon.php braces still balance')

    for name, text in (('class.slp_avalon.php', cls),
                       ('slp_avalon.js', js),
                       ('slp_avalon.php', boot)):
        path = os.path.join(out_dir, name)
        io.open(path, 'w', encoding='utf-8', newline='').write(text)
        raw = io.open(path, 'rb').read()
        print(f"  output    {name:24} {hashlib.md5(raw).hexdigest()} {len(raw)} bytes"
              f"  CR={raw.count(bytes([13]))}")

    print("self-check ok")
    print("done")


if __name__ == '__main__':
    main()
