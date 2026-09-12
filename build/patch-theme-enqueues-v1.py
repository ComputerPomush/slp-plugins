#!/usr/bin/env python3
"""
patch-theme-enqueues-v1.py

hello-elementor-child/functions.php: load the Contact Dealer modal's two
accessibility scripts on single store_page posts as well as /find-a-dealer/.

Refuses to run unless the input matches its pin byte-for-byte. Every
replacement asserts a unique anchor before applying. LF line endings are
preserved - functions.php is LF throughout, unlike the plugin sources.

Why this is safe
----------------
Both scripts hardcode Gravity Forms form 14 element ids. Measured on
/store/coty-marine-llc/ against /find-a-dealer/, every id they touch is
present on the store page and unsuffixed, because the form renders once per
page and GF only appends an instance suffix from the second render onward:

    gform_wrapper_14, gform_14, gform_ajax_frame_14, gform_submit_button_14,
    input_14_16_3, input_14_8, input_14_10, input_14_12, input_14_13,
    field_14_11, choice_14_11_1

The close control the two scripts depend on is there too: exactly one
.btn-cancel.last-element inside .contact-dealer--pop-up.

The one element that is NOT there is #addressInput, the locator's search
field, which dealer-popup-focus.js uses as a focus fallback when the element
that opened the modal has been removed from the DOM. restoreFocus() returns
silently when that fallback is absent, and on a store page the trigger is a
server-rendered anchor that never leaves the DOM, so the fallback is never
reached. No code change is needed for it.

This is a theme file, version-controlled nowhere, so this script lives
outside both repos. Keep the output alongside the rest of the theme handoff.

Usage:  python patch-theme-enqueues-v1.py <src functions.php> <out functions.php>
"""

import hashlib
import io
import os
import sys

PIN_MD5 = 'b99cf6f6a1ee4ba0be13a2e5145c564f'
PIN_BYTES = 60463


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
        sys.exit("usage: patch-theme-enqueues-v1.py <src functions.php> <out functions.php>")
    src, out = sys.argv[1], sys.argv[2]

    raw = io.open(src, 'rb').read()
    md5, size = hashlib.md5(raw).hexdigest(), len(raw)
    if md5 != PIN_MD5 or size != PIN_BYTES:
        sys.exit(
            f"ABORT functions.php: got {md5} / {size} bytes, "
            f"expected {PIN_MD5} / {PIN_BYTES} bytes.\n"
            "       The server copy has moved since this script was written. "
            "Re-pin before patching."
        )
    print(f"  input OK  functions.php            {md5} {size} bytes")
    text = raw.decode('utf-8')

    LF = '\n'

    # ---- edit 1: focus trap ----------------------------------------------
    old1 = (
        'function enqueue_find_a_dealer_focus_trap() {' + LF +
        "    if ( is_page( 'find-a-dealer' ) ) {" + LF
    )
    new1 = (
        'function enqueue_find_a_dealer_focus_trap() {' + LF +
        '    // Single store_page posts render the same Contact Dealer modal and the' + LF +
        '    // same Gravity Form 14, with identical element ids - the form appears' + LF +
        '    // once per page, so GF adds no instance suffix. The trap runs there' + LF +
        '    // verbatim; nothing in the file needed changing.' + LF +
        "    if ( is_page( 'find-a-dealer' ) || is_singular( 'store_page' ) ) {" + LF
    )
    text = sub_once(text, old1, new1, 'edit 1  focus trap on store pages')

    # ---- edit 2: popup focus ---------------------------------------------
    old2 = (
        '    // Target only the /find-a-dealer/ page by slug.' + LF +
        "    if ( ! is_page( 'find-a-dealer' ) ) {" + LF +
        '        return;' + LF +
        '    }' + LF
    )
    new2 = (
        '    // /find-a-dealer/ and every single store_page - both render the modal.' + LF +
        '    //' + LF +
        '    // dealer-popup-focus.js falls back to #addressInput when the element that' + LF +
        '    // opened the modal has gone, and that field exists only on the locator.' + LF +
        '    // restoreFocus() returns silently when the fallback is missing, and on a' + LF +
        '    // store page the trigger is a server-rendered anchor that never leaves the' + LF +
        '    // DOM, so the fallback is never reached.' + LF +
        "    if ( ! is_page( 'find-a-dealer' ) && ! is_singular( 'store_page' ) ) {" + LF +
        '        return;' + LF +
        '    }' + LF
    )
    text = sub_once(text, old2, new2, 'edit 2  popup focus on store pages')

    # ---- edit 3: the docblock that edit 2 just made untrue ----------------
    old3 = (
        ' * Loads dealer-popup-focus.js only on the /find-a-dealer/ page.' + LF +
        ' * Uses is_page() to target by slug, keeping the script off all other pages' + LF +
        ' * for performance. Loaded in the footer to avoid blocking render.' + LF
    )
    new3 = (
        ' * Loads dealer-popup-focus.js on /find-a-dealer/ and on single store_page' + LF +
        ' * posts - the two places the Contact Dealer modal is rendered. Kept off' + LF +
        ' * every other page for performance. Loaded in the footer to avoid blocking' + LF +
        ' * render.' + LF
    )
    text = sub_once(text, old3, new3, 'edit 3  docblock no longer says "only"')

    # ---- structural self-checks ------------------------------------------
    check(text.count("is_singular( 'store_page' )") == 3,
          'store_page appears in both enqueues plus the existing CSS block')
    check(text.count("if ( ! is_page( 'find-a-dealer' ) ) {") == 0,
          'the old single-page guard is gone')
    check(text.count('only on the /find-a-dealer/ page') == 0,
          'no docblock still claims find-a-dealer only')
    check(text.count('<?php') == raw.decode('utf-8').count('<?php'),
          'no PHP open tags added or lost')
    check('\r' not in text, 'still LF throughout')

    io.open(out, 'w', encoding='utf-8', newline='').write(text)
    o = io.open(out, 'rb').read()
    print(f"  output    functions.php            {hashlib.md5(o).hexdigest()} {len(o)} bytes"
          f"  CR={o.count(bytes([13]))}")
    print("self-check ok")
    print("done")


if __name__ == '__main__':
    main()
