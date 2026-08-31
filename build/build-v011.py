#!/usr/bin/env python3
"""
SLP Dealer Guard - build v0.0.11 from the shipped v0.0.10 artefacts.

Scopes the transport hook to search requests.

The defect. slp.send_ajax has TWO live callers on the dealer page, not one:

  slp_core.js:1849            the location search
  slp-experience_userinterface.min.js
                              SLPEXP.email_form.send_email, which posts
                              {action:"email_form", formdata:...}

install_transport_hook() replaces slp.send_ajax globally and adds a .fail()
leg, so BOTH callers route through it. The only thing standing between an
email_form failure and a bogus search error was

    if (guard.state !== guard.SEARCHING) return;

which holds when no search is running and fails exactly when one is: an
email_form POST that errors WHILE a search is in flight passes that test and
finishes the search with ERROR and the transport message, killing a search
that was fine.

The fix is to ask which request failed. slp_core.js:1808 sets
action.action = "csl_ajax_search" by default and 1842 rewrites it to
"csl_ajax_onload" for the page-load search, so those two names are the search
path and nothing else is.

Unrecognised shapes deliberately fall through to the old state test rather
than being treated as not-a-search. A future caller that passes something
other than an object with a string .action should keep whatever error
reporting it has today; silently dropping it would be a worse bug than the one
being fixed.

v0.0.8's comment here promised this fix "in v0.0.9". It landed in v0.0.11
because the spinner corrective took v0.0.9 and Layer 0 took v0.0.10. The
comment is corrected rather than left pointing at a version that came and went.

Usage:  python3 build-v011.py <src_dir> <out_dir>
"""

import hashlib
import sys
from pathlib import Path

SRC_MD5 = {
    "slp_avalon.js": "7dd58edee4aef019563fabaa0b59d9ef",
    "slp_avalon.php": "bdf76246476ff8a4e93f38c56f464adf",
}


def md5(data: bytes) -> str:
    return hashlib.md5(data).hexdigest()


def sub_once(blob: bytes, old: bytes, new: bytes, label: str) -> bytes:
    n = blob.count(old)
    if n != 1:
        raise SystemExit(f"ANCHOR FAIL [{label}]: expected 1 occurrence, found {n}")
    return blob.replace(old, new, 1)


A1_OLD = (
    b"      slp.send_ajax = function (action, callback) {\r\n"
    b"        return jQuery\r\n"
    b"          .post(slplus.ajaxurl, action, function (response) {\r\n"
    b"            try {\r\n"
    b"              response = JSON.parse(response);\r\n"
    b"            } catch (ex) {}\r\n"
    b"            callback(response);\r\n"
    b"          })\r\n"
    b"          .fail(function () {\r\n"
    b"            // Correction, v0.0.8: send_ajax is NOT shared with\r\n"
    b"            // slp.option.get_from_server, which issues its own jQuery.getJSON\r\n"
    b"            // (slp_core.js:848). It IS shared with SLPEXP.email_form.send_email\r\n"
    b"            // in slp-experience_userinterface.min.js, which is enqueued on this\r\n"
    b"            // page - so this guard is load-bearing today, not insurance. It is\r\n"
    b"            // also not sufficient: an email_form POST that fails WHILE a search\r\n"
    b"            // is in flight passes this check and aborts a healthy search.\r\n"
    b"            // v0.0.9 replaces the state test with an action.action test.\r\n"
    b"            if (guard.state !== guard.SEARCHING) return;\r\n"
    b"            guard.finish(guard.ERROR, {\r\n"
    b"              message: AVALON_GUARD_MESSAGES.transport,\r\n"
    b"              focus_input: guard.user_initiated,\r\n"
    b"            });\r\n"
    b"          });\r\n"
    b"      };\r\n"
    b"    },\r\n"
)

A1_NEW = (
    b"      slp.send_ajax = function (action, callback) {\r\n"
    b"        // Which request is this? Decided BEFORE the post goes out, because\r\n"
    b"        // the failure leg below has no other way to tell.\r\n"
    b"        //\r\n"
    b"        // send_ajax has two live callers on this page, not one:\r\n"
    b"        //   slp_core.js:1849      the location search\r\n"
    b"        //   slp-experience        SLPEXP.email_form.send_email, posting\r\n"
    b"        //                         {action:\"email_form\", formdata:...}\r\n"
    b"        // and this wrapper is global, so both route through it.\r\n"
    b"        //\r\n"
    b"        // slp_core.js:1808 defaults action.action to csl_ajax_search and\r\n"
    b"        // 1842 rewrites it to csl_ajax_onload for the page-load search.\r\n"
    b"        // Those two names are the search path and nothing else is.\r\n"
    b"        //\r\n"
    b"        // An unrecognised shape is treated AS a search, so it keeps the\r\n"
    b"        // v0.0.10 behaviour. A future caller quietly losing its error\r\n"
    b"        // reporting would be a worse bug than the one this fixes.\r\n"
    b"        var named =\r\n"
    b'          !!action && typeof action === "object" &&\r\n'
    b'          typeof action.action === "string";\r\n'
    b"        var is_search = named\r\n"
    b'          ? action.action === "csl_ajax_search" ||\r\n'
    b'            action.action === "csl_ajax_onload"\r\n'
    b"          : true;\r\n"
    b"\r\n"
    b"        return jQuery\r\n"
    b"          .post(slplus.ajaxurl, action, function (response) {\r\n"
    b"            try {\r\n"
    b"              response = JSON.parse(response);\r\n"
    b"            } catch (ex) {}\r\n"
    b"            callback(response);\r\n"
    b"          })\r\n"
    b"          .fail(function () {\r\n"
    b"            // v0.0.11. Was a bare state test, which held whenever no\r\n"
    b"            // search was running and failed exactly when one was: an\r\n"
    b"            // email_form POST erroring mid-search finished that search\r\n"
    b"            // with ERROR and the transport message. Narrow window, but it\r\n"
    b"            // reported a failure that had not happened.\r\n"
    b"            if (!is_search) return;\r\n"
    b"            // Kept as the cycle guard: a failure arriving after the 12s\r\n"
    b"            // ceiling has already fired must not reopen anything. finish()\r\n"
    b"            // would no-op anyway; this says so out loud.\r\n"
    b"            if (guard.state !== guard.SEARCHING) return;\r\n"
    b"            guard.finish(guard.ERROR, {\r\n"
    b"              message: AVALON_GUARD_MESSAGES.transport,\r\n"
    b"              focus_input: guard.user_initiated,\r\n"
    b"            });\r\n"
    b"          });\r\n"
    b"      };\r\n"
    b"    },\r\n"
)

EDITS = [("transport action scoping", A1_OLD, A1_NEW)]

PHP_OLD = b" * Version: 0.0.10\r\n"
PHP_NEW = b" * Version: 0.0.11\r\n"


def main() -> None:
    src = Path(sys.argv[1] if len(sys.argv) > 1 else ".")
    out = Path(sys.argv[2] if len(sys.argv) > 2 else "out11")
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
    print("  edit   ok  version header 0.0.10 -> 0.0.11")

    # Exact CODE forms only. Bare identifiers match the surrounding comments,
    # which is how three earlier builds tripped their own self-checks.
    assert js.count(b"\r\n") == js.count(b"\n"), "stray bare LF introduced"
    assert not js.endswith(b"\n"), "trailing newline introduced"
    assert js.count(b"var is_search = named") == 1, "the discriminator"
    assert js.count(b"if (!is_search) return;") == 1, "applied once, in .fail"
    assert js.count(b"if (guard.state !== guard.SEARCHING) return;") == 1, \
        "the cycle guard is KEPT, not replaced"
    assert js.count(b'action.action === "csl_ajax_search"') == 1
    assert js.count(b'action.action === "csl_ajax_onload"') == 1
    assert js.count(b"// v0.0.9 replaces the state test") == 0, \
        "the stale promise pointing at v0.0.9 is gone"
    assert php.count(b"0.0.11") == 1
    print("  self-check ok")

    (out / "slp_avalon.js").write_bytes(js)
    (out / "slp_avalon.php").write_bytes(php)

    for name, blob in (("slp_avalon.js", js), ("slp_avalon.php", php)):
        crlf = blob.count(b"\r\n")
        print(f"  output     {name:16} {md5(blob)}  {len(blob):>6} bytes  "
              f"CRLF={crlf}  lines={crlf + 1}")


if __name__ == "__main__":
    main()
