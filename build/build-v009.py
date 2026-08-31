#!/usr/bin/env python3
"""
SLP Dealer Guard - build v0.0.9 from the shipped v0.0.8 artefacts.

Corrective. v0.0.8 tried to centre the spinner icon with

    #sl_loading_indicator i { transform: translate(-50%, -50%); }

That cannot work. Font Awesome 5.15.3, loaded on this page by Elementor,
declares `.fa-spin{animation:fa-spin 2s linear infinite}` with keyframes that
set `transform: rotate(...)`. A running animation beats an author normal
declaration in the cascade, so the translate was discarded on every frame.
Confirmed on Aura DEV: the icon's top-left sat at exactly the scrim's centre,
which is `left:50%; top:50%` with no transform applied.

Measured there at a 1920 viewport:

    scrim (#sl_div)  338 .. 1918   centre x 1128
    map  (#map_box)  ~907 .. 1918  centre x ~1412
    icon top-left    1128, 585.4   46.5 x 48

So even a working translate would only have moved it 23px. The visible problem
is that the scrim covers the search column too, so its centre lands near the
map's LEFT EDGE - about 260px left of where the eye expects the spinner.

v0.0.9 centres the icon on the map box, measured at show time, using left/top
rather than transform. Falls back to the scrim when the map is missing or has
no size, which covers the page-load bootstrap and the stacked mobile layout.

Usage:  python3 build-v009.py <src_dir> <out_dir>
"""

import hashlib
import sys
from pathlib import Path

SRC_MD5 = {
    "slp_avalon.js": "b78688d0fbf62500baa186fb865e84fe",
    "slp_avalon.php": "98601fbd3bba3d50f2d951663875aecd",
}


def md5(data: bytes) -> str:
    return hashlib.md5(data).hexdigest()


def sub_once(blob: bytes, old: bytes, new: bytes, label: str) -> bytes:
    n = blob.count(old)
    if n != 1:
        raise SystemExit(f"ANCHOR FAIL [{label}]: expected 1 occurrence, found {n}")
    return blob.replace(old, new, 1)


# --------------------------------------------------------------- edit 1
# Retire the rule that never applied. Leaving it would tell the next reader
# that the icon is centred by CSS, which it is not.
A1_OLD = (
    b"            //The spinner icon is placed at left/top 50% with no transform,\r\n"
    b"            //so it hangs below and right of true centre by half its own\r\n"
    b"            //size. The offending rule is in all four theme stylesheets and\r\n"
    b"            //SLP ships none, so it is corrected here rather than in four\r\n"
    b"            //divergent files. ID selector, so this wins on specificity\r\n"
    b"            //rather than on source order.\r\n"
    b'            "#sl_loading_indicator i{transform:translate(-50%,-50%);}" +\r\n'
)
A1_NEW = (
    b"            //NOTE there is deliberately no rule here for the spinner icon.\r\n"
    b"            //v0.0.8 tried `#sl_loading_indicator i{transform:translate(\r\n"
    b"            //-50%,-50%)}` and it never applied: .fa-spin runs\r\n"
    b"            //`animation: fa-spin 2s linear infinite` whose keyframes set\r\n"
    b"            //`transform: rotate(...)`, and an animation beats an author\r\n"
    b"            //normal declaration in the cascade. The icon is positioned by\r\n"
    b"            //center_spinner() instead, in left/top, at show time.\r\n"
)

# --------------------------------------------------------------- edit 2
A2_OLD = (
    b"    show_spinner: function (show) {\r\n"
    b"      this.ensure_spinner();\r\n"
    b"      sl_show_loading(show);\r\n"
    b"    },\r\n"
)
A2_NEW = (
    b"    show_spinner: function (show) {\r\n"
    b"      this.ensure_spinner();\r\n"
    b"      sl_show_loading(show);\r\n"
    b"      //Only on the way in, and only after sl_show_loading(true): while\r\n"
    b"      //.sl_hidden is applied the indicator is display:none, so every\r\n"
    b"      //measurement below would read zero.\r\n"
    b"      if (show) this.center_spinner();\r\n"
    b"    },\r\n"
    b"\r\n"
    b"    /**\r\n"
    b"     * Put the spinner icon in the middle of the map.\r\n"
    b"     *\r\n"
    b"     * The scrim covers all of #sl_div - search column, results panel and\r\n"
    b"     * map - which is correct, because it has to block interaction with all\r\n"
    b"     * three. Its geometric centre is not a good place for the icon, though:\r\n"
    b"     * the search column occupies the left third, so 50%/50% lands near the\r\n"
    b"     * map's LEFT EDGE. Measured on Aura DEV at a 1920 viewport, scrim\r\n"
    b"     * 338..1918 against map 907..1918, which put the icon about 260px left\r\n"
    b"     * of where the eye looks for it.\r\n"
    b"     *\r\n"
    b"     * Measured rather than expressed in CSS because CSS cannot centre an\r\n"
    b"     * element on a sibling without hardcoding the column widths, and\r\n"
    b"     * style.css is per-site with four distinct md5s across the six\r\n"
    b"     * environments. Decision 6.\r\n"
    b"     *\r\n"
    b"     * Offsets go in left/top, NEVER in transform. v0.0.8 shipped\r\n"
    b"     * `transform: translate(-50%,-50%)` on this icon and it was discarded\r\n"
    b"     * on every frame: .fa-spin declares\r\n"
    b"     * `animation: fa-spin 2s linear infinite` and its keyframes set\r\n"
    b"     * `transform: rotate(...)`, which outranks an author normal\r\n"
    b"     * declaration. Font Awesome 5.15.3, loaded by Elementor.\r\n"
    b"     */\r\n"
    b"    center_spinner: function () {\r\n"
    b'      var indicator = document.getElementById("sl_loading_indicator");\r\n'
    b"      if (!indicator) return;\r\n"
    b'      var icon = indicator.querySelector("i");\r\n'
    b"      if (!icon) return;\r\n"
    b"\r\n"
    b"      var host = indicator.getBoundingClientRect();\r\n"
    b'      var map_box = document.getElementById("map_box");\r\n'
    b"      var target = map_box ? map_box.getBoundingClientRect() : null;\r\n"
    b"\r\n"
    b"      //No map yet at the page-load bootstrap, and the stacked layout at\r\n"
    b"      //<=768px can leave it zero-sized. Either way the scrim is the right\r\n"
    b"      //thing to centre on.\r\n"
    b"      if (!target || !target.width || !target.height) {\r\n"
    b"        target = host;\r\n"
    b"      }\r\n"
    b"\r\n"
    b"      //offsetWidth is 0 if the webfont has not resolved yet; fa-3x is 48px.\r\n"
    b"      var w = icon.offsetWidth || 48;\r\n"
    b"      var h = icon.offsetHeight || 48;\r\n"
    b"\r\n"
    b"      icon.style.left =\r\n"
    b'        target.left - host.left + target.width / 2 - w / 2 + "px";\r\n'
    b"      icon.style.top =\r\n"
    b'        target.top - host.top + target.height / 2 - h / 2 + "px";\r\n'
    b"    },\r\n"
)

EDITS = [
    ("retire the transform rule", A1_OLD, A1_NEW),
    ("center_spinner",            A2_OLD, A2_NEW),
]

PHP_OLD = b" * Version: 0.0.8\r\n"
PHP_NEW = b" * Version: 0.0.9\r\n"


def main() -> None:
    src = Path(sys.argv[1] if len(sys.argv) > 1 else ".")
    out = Path(sys.argv[2] if len(sys.argv) > 2 else "out9")
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
    print("  edit   ok  version header 0.0.8 -> 0.0.9")

    assert js.count(b"\r\n") == js.count(b"\n"), "stray bare LF introduced"
    assert not js.endswith(b"\n"), "trailing newline introduced"
    # Target the EMITTED CSS string, not prose: the replacement comment
    # quotes the old rule on purpose so the next reader knows why it went.
    assert js.count(b'"#sl_loading_indicator i{') == 0, "the broken CSS rule survives"
    assert js.count(b"avalon_guard_css") == 2, "1 idempotency guard + 1 class attribute"
    assert js.count(b"center_spinner: function") == 1, "centring helper def"
    assert js.count(b"this.center_spinner();") == 1, "one call site"
    assert js.count(b"ensure_guard_css: function") == 1
    assert js.count(b"install_options_hook: function") == 1
    assert php.count(b"0.0.9") == 1
    print("  self-check ok")

    (out / "slp_avalon.js").write_bytes(js)
    (out / "slp_avalon.php").write_bytes(php)

    for name, blob in (("slp_avalon.js", js), ("slp_avalon.php", php)):
        crlf = blob.count(b"\r\n")
        print(f"  output     {name:16} {md5(blob)}  {len(blob):>6} bytes  "
              f"CRLF={crlf}  lines={crlf + 1}")


if __name__ == "__main__":
    main()
