#!/usr/bin/env python3
# -*- coding: utf-8 -*-
r"""
probe-fold-drift.py  --  SLP Dealer Guard, v0.0.26 Part 2 diagnostic.

WHY THIS EXISTS.  build-addresskey.py derives its NFKD fold table from the
Unicode data bundled with whatever Python runs it.  On 2026-09-14 the same
script, from the same pinned resolver, produced two different artefacts:

    unicodedata 15.0.0   md5 4a6c08b86d079f0336656a6063127687   36,540 bytes
    unicodedata 16.0.0   md5 da7b17de89788bd656b5912f2a770eea   36,544 bytes

Both passed test/diff-address-key.php 749/749, and keyvectors.csv was byte
identical on both machines, so the two Unicode versions agree on every
codepoint any vector exercises.  The drift lies entirely in the untested
region of the table: 4 bytes across 1,125 entries, with the entry count and
the combining-mark count unchanged on both builds.  So no codepoint was added
or removed -- one or more mapping VALUES changed length.

"Somewhere in 1,125 entries" is not a measurement.  This names them.

HOW IT WORKS.  It does NOT parse the generated PHP.  build-addresskey.py is
deterministic given a Unicode version, so the probe recomputes the table with
this machine's unicodedata, using the same ranges and the same composition,
and diffs it against the 15.0.0 reference in fold15.json.  No regex, no escape
layers, nothing to get wrong between a Python literal and another language.

THIS IS A DIAGNOSTIC.  It writes nothing, deploys nothing, and is not a gate.

Destination:
    local   D:\Temp\Projects\GitHub\slp-plugins\build\probe-fold-drift.py
            D:\Temp\Projects\GitHub\slp-plugins\build\fold15.json

NOTE ON .gitignore: line 43 covers build/patch-*.py, which does NOT match this
name.  build/**/*.json DOES cover fold15.json.  Keep the probe out of a blanket
`git add -A`, or rename it patch-fold-drift.py.

Run:
    python build/probe-fold-drift.py
"""

import json
import os
import sys
import unicodedata

# Identical to build-addresskey.py.  Kept literal rather than imported, so the
# probe reports on the ranges as they were when the artefacts were built and
# not as they may be after a later edit to the generator.
FOLD_RANGES = [
    (0x00A0, 0x024F),
    (0x0300, 0x036F),
    (0x1E00, 0x1EFF),
    (0x2000, 0x206F),
    (0x2100, 0x218F),
    (0xFB00, 0xFB4F),
    (0xFF01, 0xFF5E),
]

PY_WHITESPACE = (
    [0x001C, 0x001D, 0x001E, 0x001F, 0x0085, 0x00A0, 0x1680,
     0x2028, 0x2029, 0x202F, 0x205F, 0x3000]
    + list(range(0x2000, 0x200B))
)

REF_MD5 = "4a6c08b86d079f0336656a6063127687"
REF_BYTES = 36540
HERE_BYTES = 36544
EXPECT_DELTA = HERE_BYTES - REF_BYTES


def build_fold():
    fold, combining = {}, []
    for lo, hi in FOLD_RANGES:
        for cp in range(lo, hi + 1):
            ch = chr(cp)
            if unicodedata.combining(ch):
                combining.append(cp)
                continue
            if cp in PY_WHITESPACE:
                fold[cp] = " "
                continue
            kd = unicodedata.normalize("NFKD", ch)
            kd = "".join(c for c in kd if not unicodedata.combining(c))
            fold[cp] = kd.upper()
    for cp in PY_WHITESPACE:
        if cp not in fold and cp not in combining:
            fold[cp] = " "
    return fold, sorted(combining)


def php_len(value):
    """Bytes this value occupies in the emitted PHP literal.

    The generator writes any byte outside printable ASCII as a four-character
    \\xNN escape, so a value's contribution is not simply its UTF-8 length.
    """
    n = 0
    for b in value.encode("utf-8"):
        n += 1 if 0x20 <= b < 0x7F else 4
    return n


def main():
    here_dir = os.path.dirname(os.path.abspath(__file__))
    ref_path = os.path.join(here_dir, "fold15.json")
    if not os.path.exists(ref_path):
        print("FAIL: fold15.json not found beside this script: " + ref_path)
        return 2

    ref = {int(k): v for k, v in json.load(open(ref_path, encoding="ascii")).items()}
    here, combining = build_fold()

    print("probe-fold-drift")
    print("=" * 72)
    print("this machine   unicodedata %s" % unicodedata.unidata_version)
    print("reference      unicodedata 15.0.0   (container build, md5 %s)" % REF_MD5)
    print("entries        here %d   reference %d   combining here %d"
          % (len(here), len(ref), len(combining)))
    print()

    only_here = sorted(set(here) - set(ref))
    only_ref = sorted(set(ref) - set(here))
    changed = sorted(cp for cp in set(here) & set(ref) if here[cp] != ref[cp])

    for label, cps, src in (("CODEPOINTS ONLY IN THIS BUILD", only_here, here),
                            ("CODEPOINTS ONLY IN THE REFERENCE", only_ref, ref)):
        if cps:
            print("%s (%d)" % (label, len(cps)))
            for cp in cps:
                print("  U+%04X %-44s -> %r"
                      % (cp, unicodedata.name(chr(cp), "?"), src[cp]))
            print()

    print("MAPPING VALUES THAT CHANGED (%d)" % len(changed))
    if not changed:
        print("  none")
    delta = 0
    for cp in changed:
        a, b = ref[cp], here[cp]
        delta += php_len(b) - php_len(a)
        print("  U+%04X %s" % (cp, unicodedata.name(chr(cp), "?")))
        print("         15.0.0 %-16r   this build %r" % (a, b))

    print()
    print("net PHP byte delta across changed VALUES: %+d" % delta)
    print("artefact size delta measured:             %+d  (%d -> %d)"
          % (EXPECT_DELTA, REF_BYTES, HERE_BYTES))
    print()
    if not changed and not only_here and not only_ref:
        print("VERDICT: the fold tables are IDENTICAL.  The 4-byte difference is")
        print("         not in $fold.  Look at $combining, or at the docblock --")
        print("         though '15.0.0' and '16.0.0' are the same length, so the")
        print("         version string alone does not explain it either.")
    elif delta == EXPECT_DELTA:
        print("VERDICT: accounted for.  The drift is fully explained above.")
    else:
        print("VERDICT: NOT fully accounted for -- %+d byte(s) unexplained."
              % (EXPECT_DELTA - delta))
        print("         Do not pin the artefact until the remainder has a name.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
