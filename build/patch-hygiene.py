#!/usr/bin/env python3
"""
SLP Dealer Guard - repo-hygiene prose corrections.

Three files, five anchored replacements, no logic anywhere. Everything here is
Write-Host text, a docblock, a commit message or a comment.

Why this is a script and not a diff. The repo-root files are NOT covered by
`slp_avalon/** -text` in .gitattributes, so core.autocrlf rewrites them to CRLF
on checkout. Handoff rev 10 section 1 records that their md5s therefore cannot
be pinned. A patch that assumed either line ending would fail on the other, so
every anchor below is written with \n and re-encoded to whatever the file on
disk actually uses, per file, at load time.

The three corrections, and what measurement produced each:

  Publish-Step9.ps1  the -Mode Tag checklist. Rev 10 section 5 called this
                     "two one-line fixes". Comparing it line by line against
                     the corrected section 8 checklist shows three items wrong
                     and a fourth missing:
                       item 4  expects a fresh load to reproduce a manual
                               search's result count. Issue 22 makes that
                               impossible - the first load of any page goes out
                               as csl_ajax_onload and honours radius, every
                               later search goes as csl_ajax_search and does
                               not.
                       item 5  asks for a dealerless search. EMPTY is
                               unreachable: Decision 29's backfill tops any
                               in-territory response up to three with no
                               distance ceiling, and a zeroed count only ever
                               comes from the territory gate, which sets the
                               rejection flag and takes REJECTED instead.
                       item 6  says the bar ends up "clean" after a Tijuana
                               rejection. Constraint C1 requires every
                               attribution key to survive; only the three
                               place_* keys are removed.
                       missing the all-caps MICHIGAN case, section 8 item 6.
                     Plus the refusal, which printed "Not tagged." after a
                     lowercase yes with no diagnostic, three times running.

  Publish-RepoHygiene.ps1
                     its description and commit message both describe a gap
                     that has since been closed. A -Mode Verify run at v0.0.13
                     reports .gitignore and Publish-Step3..Step8 and
                     Publish-RepoHygiene.ps1 all tracked and clean, with only
                     Publish-Step9.ps1 untracked. The description also says the
                     artefact check uses the v0.0.11 md5 pins; it compares
                     against HEAD, which is what stopped it going stale at
                     v0.0.12, and the script's own $Frozen comment says so.
                     -NormalizeTrailingNewline describes a 5-byte .gitignore
                     with no trailing newline. It is 6 bytes with one - both
                     in the working tree and at HEAD - so the switch is a
                     no-op.

  test/suite-core.js the territory_boxes() line reference reads 1103-1122.
                     That was exact at v0.0.11; reversing build-v012.py's four
                     edits reproduces md5 defbb41312071472a7039da37651a0d4 and
                     puts territory_boxes() at 1103 there. The provinces and
                     helpers edit inserted 141 lines above it, so at v0.0.13 it
                     is 1244-1263. Lines 1103-1122 today are the
                     get_state_aliases() docblock.

Usage:  python3 patch-hygiene.py <repo_root>            # writes in place
        python3 patch-hygiene.py <repo_root> --dry-run  # reports only
"""

import hashlib
import sys
from pathlib import Path

# --------------------------------------------------------------- Step9

S9_OLD = """    Write-Host 'Client checklist - all of these on Aura DEV before tagging:' -ForegroundColor Cyan
    Write-Host '  1. Load ?place_lat=48.86&place_lng=2.35 -> territory message, and' -ForegroundColor Gray
    Write-Host '     the address bar drops to the bare page URL. Refresh: clean load,' -ForegroundColor Gray
    Write-Host '     no message, no Paris.' -ForegroundColor Gray
    Write-Host '  2. Load ?place_lat=48.86&place_lng=2.35&utm_source=test&gclid=abc' -ForegroundColor Gray
    Write-Host '     -> utm_source and gclid still on the bar afterwards. C1.' -ForegroundColor Gray
    Write-Host '  3. Search Detroit, MI -> results, and place_address IS on the bar.' -ForegroundColor Gray
    Write-Host '  4. Copy that URL, open it fresh -> the same results reload.' -ForegroundColor Gray
    Write-Host '  5. Search a valid but dealerless area from a URL carrying a previous' -ForegroundColor Gray
    Write-Host '     search -> bar clears rather than replaying the old search.' -ForegroundColor Gray
    Write-Host '  6. Type Tijuana -> territory message, bar unchanged and still clean.' -ForegroundColor Gray
    Write-Host '  7. Back button still leaves the page. replaceState does not stack.' -ForegroundColor Gray
    Write-Host ''
    $ok = Read-Host 'All seven passed? (type YES to tag)'
    if ($ok -cne 'YES') { Write-Host 'Not tagged.' -ForegroundColor Yellow; exit 1 }
"""

S9_NEW = """    Write-Host 'Client checklist - all of these on Aura DEV before tagging:' -ForegroundColor Cyan
    Write-Host '  1. Load ?place_lat=48.86&place_lng=2.35 -> territory message, and' -ForegroundColor Gray
    Write-Host '     the bar drops to the bare page URL. Refresh: clean load, no' -ForegroundColor Gray
    Write-Host '     message, no Paris pin, no AJAX.' -ForegroundColor Gray
    Write-Host '  2. Load the same plus &utm_source=test&utm_medium=cpc&gclid=abc123' -ForegroundColor Gray
    Write-Host '     -> after the rejection the bar reads /find-a-dealer/ with all' -ForegroundColor Gray
    Write-Host '     three attribution keys still on it. Constraint C1.' -ForegroundColor Gray
    Write-Host '  3. Search Detroit, MI -> results, and place_address IS on the bar.' -ForegroundColor Gray
    Write-Host '  4. Copy that URL, open it fresh -> the same PAGE STATE: field' -ForegroundColor Gray
    Write-Host '     populated, results rendered, no error. NOT the same count.' -ForegroundColor Gray
    Write-Host '     A fresh load and a manual re-search take different paths' -ForegroundColor Gray
    Write-Host '     through SLP by design - Issue 22.' -ForegroundColor Gray
    Write-Host '  5. From the URL left by 3, type Tijuana -> territory message, the' -ForegroundColor Gray
    Write-Host '     place_* keys drop and every attribution key stays.' -ForegroundColor Gray
    Write-Host '  6. Type MICHIGAN in caps -> Michigan dealers. The state branch is' -ForegroundColor Gray
    Write-Host '     live and case-insensitive since v0.0.12.' -ForegroundColor Gray
    Write-Host '  7. Back button leaves the page in one press. replaceState does not' -ForegroundColor Gray
    Write-Host '     stack.' -ForegroundColor Gray
    Write-Host ''
    Write-Host '  Do NOT ask for a dealerless search. EMPTY is unreachable: the' -ForegroundColor Yellow
    Write-Host '  Decision 29 backfill tops any in-territory response up to three' -ForegroundColor Yellow
    Write-Host '  with no distance ceiling, and a zeroed count only ever comes from' -ForegroundColor Yellow
    Write-Host '  the territory gate, which takes the REJECTED branch instead.' -ForegroundColor Yellow
    Write-Host ''
    Write-Host '  Expect two pins for three Detroit results. DONNIE MARCH and I-94' -ForegroundColor Yellow
    Write-Host '  Marine are stored half a metre apart, so their markers coincide.' -ForegroundColor Yellow
    Write-Host ''
    $ok = Read-Host 'All seven passed? (type YES to tag)'
    if ($ok -cne 'YES') {
        Write-Host ("Expected YES in capitals, got '{0}'. Not tagged." -f $ok) -ForegroundColor Yellow
        exit 1
    }
"""

# ---------------------------------------------------------- RepoHygiene

RH1_OLD = """    Publish-Step7.ps1 line 223 builds $toStage from two slp_avalon/ artefacts
    plus $TestFiles. Neither .gitignore nor Publish-Step<N>.ps1 has ever been
    in that list, in any Step script, so both have accumulated as working-tree
    noise across v0.0.8 through v0.0.11. Handoff rev 8 section 1 nevertheless
    lists "Publish-Step4..7.ps1" under "Repo root - committed", which the
    v0.0.11 transcript disproves: git status reports all four as untracked.

    This script closes that gap once, as its own commit, so it cannot be
    confused with a version bump.

    It refuses to run if the plugin artefacts differ from the v0.0.11 pins.
    A hygiene commit must not silently carry a code change.
"""

RH1_NEW = """    Publish-Step7.ps1 line 223 builds $toStage from two slp_avalon/ artefacts
    plus $TestFiles. Neither .gitignore nor Publish-Step<N>.ps1 has ever been
    in that list, in any Step script, so both accumulated as working-tree noise
    from v0.0.8 onward while handoff rev 8 section 1 listed the publish scripts
    as committed.

    Most of that gap is now closed. A -Mode Verify run at v0.0.13 reports
    .gitignore, Publish-Step3.ps1, Publish-Step3_1.ps1, Publish-Step4..8.ps1
    and this script all tracked and clean, with Publish-Step9.ps1 the only
    remaining untracked path. Expect this script to find little or nothing to
    do; that is the intended end state, not a fault.

    It refuses to run if the plugin artefacts differ from HEAD, NOT from a set
    of pinned md5s - see the $Frozen comment below for why that distinction
    matters. A hygiene commit must not silently carry a code change.
"""

RH2_OLD = """    OFF by default, so bytes are preserved. The working-tree .gitignore is
    5 bytes, "*.log", with NO trailing newline. That works, but any later
    Add-Content or `echo >>` concatenates onto the *.log line instead of
    starting a new one, silently producing a pattern like "*.logbuild/out".
    Pass this switch to append a single LF first.
"""

RH2_NEW = """    OFF by default, so bytes are preserved. Now a no-op in practice: as of
    v0.0.13 the working-tree .gitignore is 6 bytes, "*.log" plus a trailing
    LF, and HEAD's copy matches. It was 5 bytes with no trailing newline when
    this switch was written.

    Kept because the hazard is real and recurs. Without a trailing newline any
    later Add-Content or `echo >>` concatenates onto the *.log line instead of
    starting a new one, silently producing a pattern like "*.logbuild/out".
    style.css has exactly this shape today - 218,328 bytes ending in a closing
    brace - so the same care applies during the cosmetic pass.
"""

RH3_OLD = """    & git commit -m 'repo: commit .gitignore and the Publish-Step scripts' -m @'
Neither path has ever appeared in a Publish-Step script's $toStage list, so
both have sat in the working tree since v0.0.8 while handoff rev 8 section 1
listed Publish-Step4..7.ps1 as committed. The v0.0.11 transcript shows all
four untracked and .gitignore modified.

No slp_avalon/ artefact is touched: the three v0.0.11 md5s are asserted
unchanged before staging.

Publish-Step<N>.ps1 is the tooling that produces every md5, CRLF and suite
claim in the handoff. Decision 28 requires those claims to be reproducible,
which they are not from a fresh clone while the scripts are local-only.
'@
"""

RH3_NEW = """    & git commit -m 'repo: track Publish-Step9.ps1, correct four stale claims in the tooling' -m @'
Publish-Step9.ps1 is the last repo-root script still untracked. .gitignore and
Publish-Step3..Step8 were committed earlier; a -Mode Verify run at v0.0.13
reports every one of them clean and Step9 as untracked.

Four prose corrections ride with it. No logic changes anywhere.

Publish-Step9.ps1 - the -Mode Tag client checklist had three items that could
not pass as written and was missing a fourth. Item 4 expected a fresh load to
reproduce a manual search's result count, which Issue 22 makes impossible: the
first load of a page goes out as csl_ajax_onload and honours radius, every
later search goes as csl_ajax_search and does not. Item 5 asked for a
dealerless search, which Decision 29's uncapped backfill makes unreachable.
Item 6 called the address bar "clean" after a Tijuana rejection, when
constraint C1 requires every attribution key to survive. The all-caps MICHIGAN
case was absent. Replaced with the seven items from handoff rev 10 section 8.

Publish-Step9.ps1 - the tag refusal printed "Not tagged." with no diagnostic
after a lowercase yes, three times in a row. It now prints what it expected
and what it received.

Publish-RepoHygiene.ps1 - the description claimed .gitignore and every
Publish-Step script were untracked, and that the artefact check compares
against the v0.0.11 md5 pins. It compares against HEAD, which is what stopped
it going stale at v0.0.12. -NormalizeTrailingNewline described a 5-byte
.gitignore with no trailing newline; it is 6 bytes with one, so the switch is
now a no-op.

test/suite-core.js - the territory_boxes() line reference read 1103-1122,
exact at v0.0.11 and stale since build-v012.py inserted 141 lines above the
method. It is 1244-1263 at v0.0.13, and the comment now says the method name
is the anchor rather than the range.

No slp_avalon/ artefact is touched: all three are asserted byte-identical to
HEAD before staging.
'@
"""

# ------------------------------------------------------------ suite-core

SC_OLD = """/* Values transcribed from SLP_Avalon::territory_boxes(),
   slp_avalon/inc/class.slp_avalon.php 1103-1122. If this fails, one side was
   edited without the other. */
"""

SC_NEW = """/* Values transcribed from SLP_Avalon::territory_boxes(), at
   slp_avalon/inc/class.slp_avalon.php 1244-1263 as of v0.0.13.

   The method name is the anchor, not the range. This comment read 1103-1122
   until v0.0.12, which was exact at v0.0.11 and went stale the moment
   build-v012.py inserted the provinces and their helpers 141 lines above the
   method. Re-measure by grep after any edit; never adjust the offset by hand.

   If this fails, one side was edited without the other. */
"""

PATCHES = [
    ("Publish-Step9.ps1", [
        ("Mode Tag client checklist + refusal diagnostic", S9_OLD, S9_NEW),
    ]),
    ("Publish-RepoHygiene.ps1", [
        ("description: the gap is mostly closed; HEAD not pins", RH1_OLD, RH1_NEW),
        ("NormalizeTrailingNewline: 6 bytes with an LF",        RH2_OLD, RH2_NEW),
        ("commit message rewritten for the real situation",     RH3_OLD, RH3_NEW),
    ]),
    ("test/suite-core.js", [
        ("territory_boxes() line reference 1103-1122 -> 1244-1263", SC_OLD, SC_NEW),
    ]),
]


def eol_of(blob: bytes) -> bytes:
    """CRLF if the file uses it at all, else LF. Mixed endings are a failure."""
    crlf = blob.count(b"\r\n")
    bare = blob.count(b"\n") - crlf
    if crlf and bare:
        raise SystemExit(f"MIXED EOL: {crlf} CRLF and {bare} bare LF - refusing to patch")
    return b"\r\n" if crlf else b"\n"


def encode(text: str, eol: bytes) -> bytes:
    return text.replace("\n", eol.decode()).encode("utf-8")


def main() -> None:
    root = Path(sys.argv[1] if len(sys.argv) > 1 else ".")
    dry = "--dry-run" in sys.argv

    if not (root / ".git").exists():
        raise SystemExit(f"not a git repo root: {root}")

    for rel, edits in PATCHES:
        path = root / rel
        if not path.is_file():
            raise SystemExit(f"MISSING: {path}")

        blob = path.read_bytes()
        eol = eol_of(blob)
        eol_name = "CRLF" if len(eol) == 2 else "LF"
        before = hashlib.md5(blob).hexdigest()
        print(f"\n{rel}")
        print(f"  before  {before}  {len(blob):>6} bytes  EOL={eol_name}")

        for label, old, new in edits:
            o, n = encode(old, eol), encode(new, eol)
            count = blob.count(o)
            if count != 1:
                raise SystemExit(f"  ANCHOR FAIL [{label}]: expected 1 occurrence, found {count}")
            if blob.count(n):
                raise SystemExit(f"  ALREADY APPLIED [{label}]: replacement text is present")
            blob = blob.replace(o, n, 1)
            print(f"  edit ok {label}")

        # Nothing here may change the line-ending convention or the trailing byte.
        if eol_of(blob) != eol:
            raise SystemExit("  EOL convention changed - refusing to write")

        after = hashlib.md5(blob).hexdigest()
        print(f"  after   {after}  {len(blob):>6} bytes")

        if dry:
            print("  DRY RUN - not written")
        else:
            path.write_bytes(blob)
            print(f"  written {path}")

    print("\nself-check")
    s9 = (root / "Publish-Step9.ps1").read_bytes()
    rh = (root / "Publish-RepoHygiene.ps1").read_bytes()
    sc = (root / "test/suite-core.js").read_bytes()
    if dry:
        print("  skipped on --dry-run")
        return

    checks = [
        (s9.count(b"Expected YES in capitals") == 1, "Step9: refusal prints what it wanted"),
        (s9.count(b"Not tagged.") == 1, "Step9: exactly one refusal message left"),
        (s9.count(b"dealerless") == 1, "Step9: dealerless appears only in the do-not-ask note"),
        (s9.count(b"MICHIGAN in caps") == 1, "Step9: the state-branch item is present"),
        (s9.count(b"the same results reload") == 0, "Step9: the impossible item 4 is gone"),
        (s9.count(b"bar unchanged and still clean") == 0, "Step9: the C1-violating item 6 is gone"),
        # Exact constructs, never a bare substring. "6 bytes" occurs twice by
        # design - once in the docblock, once in the commit message - and an
        # == 1 count on it false-positived on the first run of this script,
        # which is the same trap build-v009 through v012 each fell into.
        (rh.count(b"the working-tree .gitignore is 6 bytes") == 1,
         "RepoHygiene: the docblock states the real byte count"),
        (rh.count(b'5 bytes, "*.log", with NO trailing newline') == 0,
         "RepoHygiene: the old 5-byte claim is gone"),
        (rh.count(b"v0.0.11 pins") == 0, "RepoHygiene: the stale pin claim is gone"),
        (rh.count(b"the three v0.0.11 md5s are asserted") == 0, "RepoHygiene: so is its echo in the message"),
        (sc.count(b"1244-1263") == 1, "suite-core: the corrected range"),
        (sc.count(b"1103-1122") == 1, "suite-core: the old range survives only as history"),
    ]
    bad = 0
    for good, label in checks:
        print(f"  {'ok  ' if good else 'FAIL'} {label}")
        bad += 0 if good else 1
    if bad:
        raise SystemExit(f"{bad} self-check failure(s)")
    print("  self-check ok")


if __name__ == "__main__":
    main()
