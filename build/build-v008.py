#!/usr/bin/env python3
"""
SLP Dealer Guard - build v0.0.8 from the shipped v0.0.7 artefacts.

Rejection presentation, completed:

  1. install_options_hook()  - wraps slp.option.get_from_server so SLP's
     "No Dealers found in this area, please try again!" cannot overwrite the
     neutral sidebar prompt on a Layer 3 territory rejection. Handoff s7.4.
  2. ensure_guard_css()      - replaces ensure_notification_css(). One injected
     style block now carries three rules: the notification colour (contrast
     fix), .avalon_sidebar_prompt (Issue 12), and the spinner icon transform.
  3. Comment correction at the transport hook - a second live slp.send_ajax
     caller exists in slp-experience_userinterface.min.js.

Every anchor is asserted to occur exactly once. Input md5s are asserted before
any edit, and the output md5 is printed for the SFTP round-trip check.

Operates on BYTES throughout. slp_avalon/** is `-text` in .gitattributes, so
CRLF must survive byte-identically and there is no trailing newline.

Usage:  python3 build-v008.py <src_dir> <out_dir>
"""

import hashlib
import sys
from pathlib import Path

SRC_MD5 = {
    "slp_avalon.js": "50a18f58088564ec8adc2c2ce5684732",
    "slp_avalon.php": "58a632ec05e11906c44f70854c0c800c",
}


def md5(data: bytes) -> str:
    return hashlib.md5(data).hexdigest()


def sub_once(blob: bytes, old: bytes, new: bytes, label: str) -> bytes:
    n = blob.count(old)
    if n != 1:
        raise SystemExit(f"ANCHOR FAIL [{label}]: expected 1 occurrence, found {n}")
    return blob.replace(old, new, 1)


# --------------------------------------------------------------- edit 1
# New guard field. Sits with the other original_* slots so the idempotency
# check for the options hook reads the same as the transport hook's.
A1_OLD = b"    original_send_ajax: null,\r\n"
A1_NEW = (
    b"    original_send_ajax: null,\r\n"
    b"    original_get_from_server: null,\r\n"
)

# --------------------------------------------------------------- edit 2
# ensure_notification_css -> ensure_guard_css. One block, three rules.
A2_OLD = (
    b'    /**\r\n'
    b'     * Deliberately a separate channel from .get_my_position_notification,\r\n'
    b'     * which is owned by handle_geolocation_error(): that function removes the\r\n'
    b'     * node wholesale on its success path and would silently wipe ours.\r\n'
    b'     * Injected rather than added to style.css because slp_avalon.js is\r\n'
    b'     * repo-managed and identical on all three sites, while style.css is\r\n'
    b'     * per-site and diverges.\r\n'
    b'     */\r\n'
    b'    ensure_notification_css: function () {\r\n'
    b'      if (jQuery("style.avalon_search_notification_css").length === 0) {\r\n'
    b'        jQuery("head").append(\r\n'
    b'          jQuery(\r\n'
    b'            "<style class=\'avalon_search_notification_css\'>" +\r\n'
    b'              ".avalon_search_notification{color:#c00;font-size:13px;" +\r\n'
    b'              "display:block;margin:0 0 8px;padding-top:30px;" +\r\n'
    b'              "line-height:1.35;}" +\r\n'
    b'              "</style>"\r\n'
    b'          )\r\n'
    b'        );\r\n'
    b'      }\r\n'
    b'    },\r\n'
)

A2_NEW = (
    b'    /**\r\n'
    b'     * One injected style block for every rule the Guard owns. Injected\r\n'
    b'     * rather than added to style.css because slp_avalon.js is repo-managed\r\n'
    b'     * and identical on all three sites, while style.css is per-site and\r\n'
    b'     * diverges - four distinct md5s across the six environments as of\r\n'
    b'     * v0.0.8. That is decision 6.\r\n'
    b'     *\r\n'
    b'     * The notification is deliberately a separate channel from\r\n'
    b'     * .get_my_position_notification, which is owned by\r\n'
    b'     * handle_geolocation_error(): that function removes the node wholesale\r\n'
    b'     * on its success path and would silently wipe ours.\r\n'
    b'     *\r\n'
    b'     * Called from ensure_spinner(), notify() and set_no_results(), so it is\r\n'
    b'     * in place before any of the three surfaces is first painted. One\r\n'
    b'     * guarded injector rather than three near-identical ones, which would\r\n'
    b'     * be three places to forget.\r\n'
    b'     */\r\n'
    b'    ensure_guard_css: function () {\r\n'
    b'      if (jQuery("style.avalon_guard_css").length > 0) return;\r\n'
    b'      jQuery("head").append(\r\n'
    b'        jQuery(\r\n'
    b'          "<style class=\'avalon_guard_css\'>" +\r\n'
    b'            //Territory and error copy above the field. #c00 measures about\r\n'
    b'            //3.4:1 against the #090909 section background that Elementor\r\n'
    b'            //sets on the locator (post-28743.css), which fails WCAG AA for\r\n'
    b'            //13px text. #E7167C is about 4.55:1 and is already this page\'s\r\n'
    b'            //focus colour, so the palette does not grow.\r\n'
    b'            ".avalon_search_notification{color:#E7167C;font-size:13px;" +\r\n'
    b'            "display:block;margin:0 0 8px;padding-top:30px;" +\r\n'
    b'            "line-height:1.35;}" +\r\n'
    b'            //Issue 12. The neutral prompt re-emitted into #map_sidebar by\r\n'
    b'            //set_no_results(). Every other visible element in that panel\r\n'
    b'            //sets #FFFFFF explicitly; a bare div inherits the theme body\r\n'
    b'            //colour and is unreadable on #090909. Visible on Layer 1\r\n'
    b'            //rejections since v0.0.6, and on Layer 3 rejections from\r\n'
    b'            //v0.0.8, because install_options_hook() stops SLP painting\r\n'
    b'            //over it.\r\n'
    b'            ".avalon_sidebar_prompt{color:#FFFFFF;font-size:16px;" +\r\n'
    b'            "line-height:24px;font-family:var(--body-font-family);" +\r\n'
    b'            "padding:16px 0;}" +\r\n'
    b'            //The spinner icon is placed at left/top 50% with no transform,\r\n'
    b'            //so it hangs below and right of true centre by half its own\r\n'
    b'            //size. The offending rule is in all four theme stylesheets and\r\n'
    b'            //SLP ships none, so it is corrected here rather than in four\r\n'
    b'            //divergent files. ID selector, so this wins on specificity\r\n'
    b'            //rather than on source order.\r\n'
    b'            "#sl_loading_indicator i{transform:translate(-50%,-50%);}" +\r\n'
    b'            "</style>"\r\n'
    b'        )\r\n'
    b'      );\r\n'
    b'    },\r\n'
)

# --------------------------------------------------------------- edit 3
A3_OLD = b"      this.ensure_notification_css();\r\n"
A3_NEW = b"      this.ensure_guard_css();\r\n"

# --------------------------------------------------------------- edit 4
# The spinner is feature-detected; its CSS has to be ensured on the same path.
A4_OLD = (
    b'            \'<i class="fa fa fa-compass fa-spin fa-3x"></i></div>\'\r\n'
    b"        );\r\n"
    b"      }\r\n"
    b"    },\r\n"
)
A4_NEW = (
    b'            \'<i class="fa fa fa-compass fa-spin fa-3x"></i></div>\'\r\n'
    b"        );\r\n"
    b"      }\r\n"
    b"      this.ensure_guard_css();\r\n"
    b"    },\r\n"
)

# --------------------------------------------------------------- edit 5
A5_OLD = (
    b'    set_no_results: function (on) {\r\n'
    b'      jQuery("#sl_div").toggleClass("avalon_no_results", !!on);\r\n'
    b'      if (!on) return;\r\n'
)
A5_NEW = (
    b'    set_no_results: function (on) {\r\n'
    b'      jQuery("#sl_div").toggleClass("avalon_no_results", !!on);\r\n'
    b'      if (!on) return;\r\n'
    b'      this.ensure_guard_css();\r\n'
)

# --------------------------------------------------------------- edit 6
# The v0.0.6 comment asserted send_ajax has no other caller. It does.
A6_OLD = (
    b"            // Correction, v0.0.6: send_ajax is NOT shared with\r\n"
    b"            // slp.option.get_from_server, which issues its own jQuery.getJSON\r\n"
    b"            // (slp_core.js:848). slp_core.js:1849 is its only caller in the\r\n"
    b"            // file. The state guard stays regardless: it costs nothing and it\r\n"
    b"            // holds if a future SLP release routes anything else through here.\r\n"
)
A6_NEW = (
    b"            // Correction, v0.0.8: send_ajax is NOT shared with\r\n"
    b"            // slp.option.get_from_server, which issues its own jQuery.getJSON\r\n"
    b"            // (slp_core.js:848). It IS shared with SLPEXP.email_form.send_email\r\n"
    b"            // in slp-experience_userinterface.min.js, which is enqueued on this\r\n"
    b"            // page - so this guard is load-bearing today, not insurance. It is\r\n"
    b"            // also not sufficient: an email_form POST that fails WHILE a search\r\n"
    b"            // is in flight passes this check and aborts a healthy search.\r\n"
    b"            // v0.0.9 replaces the state test with an action.action test.\r\n"
)

# --------------------------------------------------------------- edit 7
A7_OLD = b"          });\r\n      };\r\n    },\r\n"
A7_NEW = (
    b"          });\r\n      };\r\n    },\r\n"
    b"\r\n"
    b"    /**\r\n"
    b"     * Layer 3 presentation, handoff s7.4.\r\n"
    b"     *\r\n"
    b"     * putMarkers() blanks #map_sidebar (slp_core.js:1317) and then, when the\r\n"
    b"     * marker count is zero, fetches message_no_results at 1328 and writes it\r\n"
    b"     * at 1330. That fetch is a jQuery.getJSON, so it always resolves AFTER\r\n"
    b"     * location_search_processed publishes at 1911 - which means it lands on\r\n"
    b"     * top of the neutral prompt that finish() -> set_no_results(true) has\r\n"
    b"     * just written, and the visitor reads 'No Dealers found in this area,\r\n"
    b"     * please try again!' underneath a message saying we do not serve that\r\n"
    b"     * area at all. Issue 4.\r\n"
    b"     *\r\n"
    b"     * Suppressed at source rather than with a CSS class: a class would\r\n"
    b"     * reintroduce the three-site style.css deliverable decision 6 exists to\r\n"
    b"     * avoid, and would leave a 670px void beside the map at desktop.\r\n"
    b"     *\r\n"
    b"     * Scoped to message_no_results ONLY. slp_core.js:1600 routes\r\n"
    b"     * message_bad_address through this same function and must keep working.\r\n"
    b"     * Do not broaden it.\r\n"
    b"     *\r\n"
    b"     * Scoped to territory rejections ONLY, not to EMPTY. On a genuine\r\n"
    b"     * in-territory empty result SLP's copy is the more useful of the two,\r\n"
    b"     * and the uncapped backfill (decision 13) makes that case near\r\n"
    b"     * unreachable anyway. Decision 21.\r\n"
    b"     *\r\n"
    b"     * The short-circuit is synchronous, mirroring SLP's own\r\n"
    b"     * shortcode_attributes shortcut at slp_core.js:842-845. Deferring it\r\n"
    b"     * would put the write back after set_no_results() and restore the race.\r\n"
    b"     *\r\n"
    b"     * NOTE: the file the browser executes is slp_core.min.js, not\r\n"
    b"     * slp_core.js. Every property wrapped here was verified present and\r\n"
    b"     * semantically identical in the minified build before this was written.\r\n"
    b"     */\r\n"
    b"    install_options_hook: function () {\r\n"
    b"      if (this.original_get_from_server !== null) return;\r\n"
    b"      if (\r\n"
    b'        typeof slp === "undefined" ||\r\n'
    b'        typeof slp.option === "undefined" ||\r\n'
    b'        typeof slp.option.get_from_server !== "function"\r\n'
    b"      ) {\r\n"
    b"        return;\r\n"
    b"      }\r\n"
    b"      var guard = this;\r\n"
    b"      var original = slp.option.get_from_server;\r\n"
    b"      this.original_get_from_server = original;\r\n"
    b"\r\n"
    b"      slp.option.get_from_server = function (option_name, callback) {\r\n"
    b"        if (\r\n"
    b'          option_name === "message_no_results" &&\r\n'
    b"          guard.last_response &&\r\n"
    b"          guard.last_response.avalon_territory_rejected &&\r\n"
    b'          typeof callback === "function"\r\n'
    b"        ) {\r\n"
    b"          //slp_core.js:1334 - a falsy value takes the else branch, which\r\n"
    b"          //logs and writes nothing. The prompt survives.\r\n"
    b'          callback({ value: "" });\r\n'
    b"          return;\r\n"
    b"        }\r\n"
    b"        return original.call(slp.option, option_name, callback);\r\n"
    b"      };\r\n"
    b"    },\r\n"
)

# --------------------------------------------------------------- edit 8
A8_OLD = b"      avalon_guard.install_transport_hook();\r\n"
A8_NEW = (
    b"      avalon_guard.install_transport_hook();\r\n"
    b"      //Issue 4 / Layer 3 presentation. slp.option is a plain property on\r\n"
    b"      //the slp object literal (slp_core.js:839) and exists well before\r\n"
    b"      //map-ready, so this is safe here beside the transport hook.\r\n"
    b"      avalon_guard.install_options_hook();\r\n"
)

# --------------------------------------------------------------- edit 9
A9_OLD = b"    /* ---------------------------------------------- notification channel */\r\n"
A9_NEW = b"    /* ------------------------------------------------ injected stylesheet */\r\n"

EDITS = [
    ("field",           A1_OLD, A1_NEW),
    ("section divider", A9_OLD, A9_NEW),
    ("ensure_guard_css", A2_OLD, A2_NEW),
    ("notify call",     A3_OLD, A3_NEW),
    ("ensure_spinner",  A4_OLD, A4_NEW),
    ("set_no_results",  A5_OLD, A5_NEW),
    ("comment 0.4",     A6_OLD, A6_NEW),
    ("install_options_hook", A7_OLD, A7_NEW),
    ("map_ready call",  A8_OLD, A8_NEW),
]

PHP_OLD = b" * Version: 0.0.7\r\n"
PHP_NEW = b" * Version: 0.0.8\r\n"


def main() -> None:
    src = Path(sys.argv[1] if len(sys.argv) > 1 else ".")
    out = Path(sys.argv[2] if len(sys.argv) > 2 else "out")
    out.mkdir(parents=True, exist_ok=True)

    js = (src / "slp_avalon.js").read_bytes()
    php = (src / "slp_avalon.php").read_bytes()

    for name, blob in (("slp_avalon.js", js), ("slp_avalon.php", php)):
        got = md5(blob)
        if got != SRC_MD5[name]:
            raise SystemExit(f"INPUT MD5 FAIL {name}: {got} != {SRC_MD5[name]}")
        print(f"  input  ok  {name:16} {got}")

    for label, old, new in EDITS:
        js = sub_once(js, old, new, label)
        print(f"  edit   ok  {label}")

    php = sub_once(php, PHP_OLD, PHP_NEW, "version header")
    print("  edit   ok  version header 0.0.7 -> 0.0.8")

    # Structural self-checks before anything is written.
    assert js.count(b"\r\n") == js.count(b"\n"), "stray bare LF introduced"
    assert not js.endswith(b"\n"), "trailing newline introduced"
    assert js.count(b"ensure_notification_css:") == 0, "old css fn definition survives"
    assert js.count(b"this.ensure_notification_css") == 0, "old css call survives"
    assert js.count(b"avalon_search_notification_css") == 0, "old style class survives"
    assert js.count(b"ensure_guard_css: function") == 1, "css injector def"
    assert js.count(b"this.ensure_guard_css();") == 3, "3 call sites"
    assert js.count(b"install_options_hook: function") == 1, "options hook def"
    assert js.count(b"avalon_guard.install_options_hook();") == 1, "options hook call"
    assert js.count(b"original_get_from_server") == 3, "1 field + 2 uses"
    assert js.count(b'callback({ value: "" });') == 1, "the short-circuit"
    assert php.count(b"0.0.8") == 1
    print("  self-check ok")

    (out / "slp_avalon.js").write_bytes(js)
    (out / "slp_avalon.php").write_bytes(php)

    for name, blob in (("slp_avalon.js", js), ("slp_avalon.php", php)):
        crlf = blob.count(b"\r\n")
        print(f"  output     {name:16} {md5(blob)}  {len(blob):>6} bytes  "
              f"CRLF={crlf}  lines={crlf + 1}")


if __name__ == "__main__":
    main()
