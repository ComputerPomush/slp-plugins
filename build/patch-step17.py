#!/usr/bin/env python3
"""
patch-step17.py  -  derive Publish-Step17.ps1 from Publish-Step16.ps1

Run from the repo root with no arguments:

    cd D:\\Temp\\Projects\\GitHub\\slp-plugins
    python build\\patch-step17.py

FIVE fixes. The first three are carried out of rev22; the last two were
measured on 2026-09-06 while testing the first three.

    s0.94   byte length asserted alongside md5 in $ToolFiles
    s0.95   repo directory name checked once, up front, with the cause
    s0.96   the Verify failure epilogue made conditional
    s0.106  the resolved target path printed in the banner
    s0.107  [System.Environment]::CurrentDirectory restored on every exit

Plus one defect found in passing: the $Self fallback at line 148 named
Publish-Step16.ps1, which is the s0.75 failure one level down.

This regenerates from Publish-Step16.ps1 rather than patching an earlier
Publish-Step17.ps1, so there is one input md5 and one auditable chain.

It does NOT touch the release constants block. Publish-Step17.ps1 comes out
of here still carrying v0.0.20's tag, hashes, suite totals and control
triples, because v0.0.21 does not exist yet. The closing notice lists every
constant that must be updated when it does.
"""

import hashlib
import os
import sys

SRC_NAME = 'Publish-Step16.ps1'
DST_NAME = 'Publish-Step17.ps1'

SRC_MD5   = '17c2ee97b2dd2b6c0f73534c9d2d15ed'
SRC_BYTES = 42028

ENC = 'iso-8859-1'   # round-trips every byte; the file is ASCII today and
                     # this keeps it that way if a comment ever gains one


def die(msg):
    print('FAIL  ' + msg)
    sys.exit(1)


def md5_of(b):
    return hashlib.md5(b).hexdigest()


SUBS = []

# --------------------------------------------------------------------- header
SUBS.append((
    'header changelog',
    """       the regression it was written for. It asserts the pair now.
""",
    """       the regression it was written for. It asserts the pair now.

    FIVE differences from Publish-Step16.ps1. Items 5-7 are carried out of
    rev22; items 8 and 9 were measured while testing them.

    5. $ToolFiles asserts BYTE LENGTH as well as md5. s0.94: rev21 recorded
       build-v020.py as 27,987 bytes when it is 25,729. The md5 was correct
       and identical in the document and in AllinlocalGithub.csv, so the
       wrong number sat there unchallenged - nothing checked it. Anything
       typed that nothing checks will drift again. Confirmed on 2026-09-06:
       a one-byte append to suite-v017.php failed on length, before the md5
       line was reached.

    6. The repo directory NAME is a precondition. s0.95: every row in
       release-pins.csv begins 'slp-plugins\\', and Test-PinFile resolves
       those rows against the PARENT of the repo root. A verification clone
       at ...\\Temp\\slp-v020-check produced six 'pinned path does not exist'
       FAILs while everything of substance passed. Checked once now, at the
       top, naming the cause. Confirmed both ways on 2026-09-06: rejected
       'wrongname', and passed 434/434 from D:\\Temp\\slp-plugins. Only the
       LEAF name matters - that directory is nowhere near the GitHub root,
       and all six pins still resolved.

    7. The Verify failure epilogue is CONDITIONAL. s0.96: it printed the
       suite-total explanation on every failure, including the s0.95 one
       where all thirteen suites read full and no total had moved. It now
       fires only when a suite actually reported a different assertion
       count, and says how many did. Confirmed on 2026-09-06: a corrupted
       .gitattributes failed two checks and printed no suite-total text.

    8. The banner prints the RESOLVED TARGET. s0.106: on 2026-09-06 this
       script was run from D:\\Temp\\wrongname without -PluginRepo, took the
       hardcoded default, and verified the real repo while every visual cue
       named the copy. $Self fixed the stale FILENAME at s0.75; the target
       was still invisible. A Commit typed after that run would have
       committed somewhere the operator was not looking.

    9. CurrentDirectory is RESTORED on exit. s0.107: the line below that
       sets [System.Environment]::CurrentDirectory is load-bearing - node
       and php are native processes and inherit the process CWD, not the
       PowerShell location set by Push-Location. But it outlives the script,
       and on Windows the process CWD holds an open handle. On 2026-09-06 a
       Rename-Item on the verification clone failed with "being used by
       another process" after this script exited. In the release chain that
       becomes Remove-VerifyClone.ps1 failing at step 14, at the end of a
       release rather than during a test.

       The body is wrapped in try/finally WITHOUT reindentation, so the diff
       against Publish-Step16.ps1 shows only the inserted lines and every
       other line stays byte-identical. PowerShell does not care about the
       indentation; a reviewer comparing the two files does. exit inside a
       try still runs the finally, so all eight exits and all ten throws are
       covered.
""",
))

# ------------------------------------------------------- s0.75 fallback name
SUBS.append((
    's0.75 stale fallback script name',
    """if (-not $Self) { $Self = 'Publish-Step16.ps1' }
""",
    """if (-not $Self) { $Self = 'Publish-Step17.ps1' }
""",
))

# --------------------------------------------------- s0.107 capture original
SUBS.append((
    's0.107 capture original CurrentDirectory',
    """$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
""",
    """$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

# s0.107. Captured before anything can change it, restored in the finally at
# the foot of the script. Setting CurrentDirectory to an empty string throws,
# so the restore is guarded rather than assumed.
$OrigCwd = [System.Environment]::CurrentDirectory
""",
))

# ------------------------------------------------------- s0.94, $ToolFiles
SUBS.append((
    's0.94 $ToolFiles table',
    """$ToolFiles = @{
    'build/build-v020.py' = 'ddd1ba655b61ec8fce219305ae106a32'
    'test/suite-v020.php' = '6186c6182e774f01432467567358f5c3'
    'test/suite-v019.js'  = 'e4f1cc3021c6cda1275727e5aa2fb869'
    'test/suite-v017.php' = '0882d4e4e7bd275b521e3dfd68b04db3'
    'test/suite-v018.php' = 'fb03fd6d95fb6997c35e6419634559a6'
}
""",
    """#
# s0.94: byte length is asserted beside every md5. rev21 carried
# build-v020.py at 27,987 bytes when it is 25,729 - the md5 was right in
# both the handoff and AllinlocalGithub.csv, so identical bytes were
# certain and the length was simply never read by anything. Two
# independent quantities catch a truncated download that a single one can
# still miss on a partial write.
$ToolFiles = @{
    'build/build-v020.py' = @{ Md5 = 'ddd1ba655b61ec8fce219305ae106a32'; Bytes = 25729 }
    'test/suite-v020.php' = @{ Md5 = '6186c6182e774f01432467567358f5c3'; Bytes = 26390 }
    'test/suite-v019.js'  = @{ Md5 = 'e4f1cc3021c6cda1275727e5aa2fb869'; Bytes = 18721 }
    'test/suite-v017.php' = @{ Md5 = '0882d4e4e7bd275b521e3dfd68b04db3'; Bytes = 15987 }
    'test/suite-v018.php' = @{ Md5 = 'fb03fd6d95fb6997c35e6419634559a6'; Bytes = 20265 }
}
""",
))

SUBS.append((
    's0.94 Assert-Md5Only signature',
    """function Assert-Md5Only {
    param([string]$Root, [string]$RelPath, [string]$Want)
""",
    """function Assert-Md5Only {
    # $WantBytes defaults to -1, meaning "do not check". $Upstream calls this
    # with md5 only and is unchanged; $ToolFiles passes a length as well.
    param([string]$Root, [string]$RelPath, [string]$Want, [int]$WantBytes = -1)
""",
))

SUBS.append((
    's0.94 byte check before md5 compare',
    """    $md5 = (Get-FileHash -LiteralPath $full -Algorithm MD5).Hash.ToLower()
    if ($md5 -ne $Want) {
""",
    """    if ($WantBytes -ge 0) {
        $len = (Get-Item -LiteralPath $full).Length
        if ($len -ne $WantBytes) {
            Write-Host ("  FAIL  {0}" -f $RelPath) -ForegroundColor Red
            Write-Host ("          {0} bytes != {1}" -f $len, $WantBytes) -ForegroundColor Red
            return $false
        }
    }
    $md5 = (Get-FileHash -LiteralPath $full -Algorithm MD5).Hash.ToLower()
    if ($md5 -ne $Want) {
""",
))

SUBS.append((
    's0.94 $ToolFiles consumer',
    """    if (-not (Assert-Md5Only -Root $PluginRepo -RelPath $rel -Want $ToolFiles[$rel])) { $toolsOk = $false }
""",
    """    if (-not (Assert-Md5Only -Root $PluginRepo -RelPath $rel `
            -Want $ToolFiles[$rel].Md5 -WantBytes $ToolFiles[$rel].Bytes)) { $toolsOk = $false }
""",
))

SUBS.append((
    's0.94 hardcoded file count in the failure message',
    """    Write-Host 'One of this session''s five files is missing or stale.' -ForegroundColor Red
""",
    """    Write-Host ("One of this session's {0} files is missing or stale." -f $ToolFiles.Count) -ForegroundColor Red
""",
))

# ------------------------------------------- s0.107 open the try, s0.106 banner
SUBS.append((
    's0.107 open try before the banner',
    """Write-Host "SLP Dealer Guard - Publish $Tag  [$Mode]   ($Self)" -ForegroundColor Cyan
Write-Host ('-' * 78)
""",
    """# s0.107. Body wrapped without reindentation, deliberately - see item 9 in
# the header. The matching finally is the last block in this file.
try {
Write-Host "SLP Dealer Guard - Publish $Tag  [$Mode]   ($Self)" -ForegroundColor Cyan
Write-Host ('-' * 78)
""",
))

SUBS.append((
    's0.106 banner target + s0.95 repo name precondition',
    """[System.Environment]::CurrentDirectory = $PluginRepo   # PS location != .NET CWD
""",
    """[System.Environment]::CurrentDirectory = $PluginRepo   # PS location != .NET CWD

# s0.106. The banner names the SCRIPT. This names the REPOSITORY it is about
# to read, write, commit and tag. Printed after Resolve-Path so it is the
# real target and not the argument, and before the name check below so a
# rejected run still shows what it resolved to.
Write-Host ("target  {0}" -f $PluginRepo) -ForegroundColor Cyan

# s0.95. Every row in release-pins.csv begins 'slp-plugins\\', because the
# file is GitHub-root-relative - Inventory-LocalGitHub.ps1 -PinFile runs
# from there. Test-PinFile therefore resolves each row against the PARENT
# of the repo root, and a clone in a directory named anything else fails
# all six pin checks for a reason that has nothing to do with the release.
# That happened at ...\\Temp\\slp-v020-check: six FAILs, 434 assertions and
# both control triples clean.
#
# Measured 2026-09-06: only the LEAF name matters. D:\\Temp\\slp-plugins is
# nowhere near the GitHub root and passed 434/434 with all six pins
# resolving, because $ghRoot is simply the parent of wherever this sits.
#
# Fail once here, naming the cause, rather than six times naming symptoms.
$repoLeaf = Split-Path -Leaf $PluginRepo
if ($repoLeaf -ne 'slp-plugins') {
    Write-Host ''
    Write-Host ("FAIL  repo directory is named '{0}', not 'slp-plugins'." -f $repoLeaf) -ForegroundColor Red
    Write-Host '      release-pins.csv rows are GitHub-root-relative and resolve against' -ForegroundColor Red
    Write-Host '      the PARENT of this directory, so all six pin checks would fail for' -ForegroundColor Red
    Write-Host '      a reason unrelated to the release. Rename the clone and re-run.' -ForegroundColor Red
    exit 1
}
""",
))

SUBS.append((
    's0.95 Test-PinFile comment',
    """    $ok = $true
    $ghRoot = Split-Path -Parent $Root
""",
    """    $ok = $true
    # GitHub-root-relative by design - see s0.95 at the top of the script,
    # where the repo directory name is checked as a precondition so that a
    # misnamed clone cannot surface here as six unexplained FAILs.
    $ghRoot = Split-Path -Parent $Root
""",
))

# --------------------------------------------- s0.96, conditional epilogue
SUBS.append((
    's0.96 counter init',
    """$allOk = $true
""",
    """$allOk = $true

# s0.96. Bumped by Invoke-Suite when a suite reports a different ASSERTION
# COUNT from the one this script expects. That is the only condition the
# failure epilogue at the foot of the script should speak to. A dropped
# score, a missed negative control and a wrong pin path are three other
# failures that were all being handed the suite-total explanation.
$script:SuiteTotalMoved = 0
""",
))

SUBS.append((
    's0.96 counter bump in Invoke-Suite',
    """    if ($tot -ne $ExpectTotal) { $problems += "suite has $tot assertions, expected $ExpectTotal" }
""",
    """    if ($tot -ne $ExpectTotal) {
        $problems += "suite has $tot assertions, expected $ExpectTotal"
        # Explicit assignment rather than $script:X++ : scoped increment is
        # valid PowerShell but was not parse-checkable where this was written,
        # and this form cannot be wrong.
        $script:SuiteTotalMoved = $script:SuiteTotalMoved + 1
    }
""",
))

SUBS.append((
    's0.96 conditional epilogue',
    """if (-not $allOk) {
    Write-Host 'VERIFY FAILED. Do not commit.' -ForegroundColor Red
    Write-Host 'A PHP suite total that moved is a FINDING, not a number to edit here:' -ForegroundColor Red
    Write-Host 'all five carried suites read the class, and the class grew 8,394 bytes.' -ForegroundColor Red
    exit 1
}
""",
    """if (-not $allOk) {
    Write-Host 'VERIFY FAILED. Do not commit.' -ForegroundColor Red
    if ($script:SuiteTotalMoved -gt 0) {
        Write-Host ("{0} suite(s) reported a different assertion count." -f $script:SuiteTotalMoved) -ForegroundColor Red
        Write-Host 'A suite total that moved is a FINDING, not a number to edit here.' -ForegroundColor Red
        Write-Host 'Every carried suite reads an artefact that this release changes.' -ForegroundColor Red
    }
    exit 1
}
""",
))

# --------------------------------------------------- s0.107 close the finally
SUBS.append((
    's0.107 close try with finally at end of file',
    """    Write-Host '    Rows are still reconciled; no post is trashed. That is exactly'
    Write-Host '    v0.0.19 behaviour, and suite-v020 D14a-d assert it.'
    Write-Host ''
    exit 0
}
""",
    """    Write-Host '    Rows are still reconciled; no post is trashed. That is exactly'
    Write-Host '    v0.0.19 behaviour, and suite-v020 D14a-d assert it.'
    Write-Host ''
    exit 0
}

}
finally {
    # s0.107. Runs on every path out of the body: all eight exit statements,
    # all ten throws, and the fall-through. Without this the process keeps an
    # open handle on $PluginRepo after the script ends, which blocks a rename
    # or a delete of a verification clone - measured 2026-09-06.
    #
    # Guarded because assigning an empty string to CurrentDirectory throws,
    # and a throw inside finally would mask whatever sent us here.
    if ($OrigCwd) { [System.Environment]::CurrentDirectory = $OrigCwd }
}
""",
))


# ------------------------------------------------------------- self-checks
PRESENT = [
    ('byte pin, build-v020.py', "Bytes = 25729", 1),
    ('byte pin, suite-v020.php', "Bytes = 26390", 1),
    ('byte pin, suite-v019.js', "Bytes = 18721", 1),
    ('byte pin, suite-v017.php', "Bytes = 15987", 1),
    ('byte pin, suite-v018.php', "Bytes = 20265", 1),
    ('WantBytes parameter', "[int]$WantBytes = -1", 1),
    ('WantBytes guard', "if ($WantBytes -ge 0) {", 1),
    ('WantBytes passed by consumer', "-WantBytes $ToolFiles[$rel].Bytes", 1),
    ('ToolFiles.Count in message', "$ToolFiles.Count", 1),
    ('repo leaf read', "$repoLeaf = Split-Path -Leaf $PluginRepo", 1),
    ('repo leaf compared', "if ($repoLeaf -ne 'slp-plugins') {", 1),
    ('counter initialised', "$script:SuiteTotalMoved = 0", 1),
    ('counter bumped', "$script:SuiteTotalMoved = $script:SuiteTotalMoved + 1", 1),
    ('epilogue guarded', "if ($script:SuiteTotalMoved -gt 0) {", 1),
    ('banner target line', 'Write-Host ("target  {0}" -f $PluginRepo)', 1),
    ('OrigCwd captured', "$OrigCwd = [System.Environment]::CurrentDirectory", 1),
    ('try opened', "\ntry {\nWrite-Host \"SLP Dealer Guard", 1),
    ('finally block', "\nfinally {\n", 1),
    ('guarded restore', "if ($OrigCwd) { [System.Environment]::CurrentDirectory = $OrigCwd }", 1),
    ('self fallback corrected', "$Self = 'Publish-Step17.ps1'", 1),
]

ABSENT = [
    ('old bare ToolFiles value', "'build/build-v020.py' = 'ddd1ba65"),
    ('old md5-only consumer', "-Want $ToolFiles[$rel])"),
    ('old unconditional epilogue line 1', "A PHP suite total that moved is a FINDING"),
    ('old unconditional epilogue line 2', "the class grew 8,394 bytes"),
    ('old hardcoded five files', "session''s five files"),
    ('old single-line tot compare', "{ $problems += \"suite has $tot assertions"),
    ('old stale fallback name', "$Self = 'Publish-Step16.ps1'"),
]


def brace_balance(text):
    """
    Counts braces outside single-quoted strings, double-quoted strings and
    comments. Crude, but it is the only structural check available without a
    PowerShell parser, and it is enough to catch an unbalanced try/finally.
    """
    depth = 0
    i = 0
    n = len(text)
    while i < n:
        c = text[i]
        if c == '#':
            while i < n and text[i] != '\n':
                i += 1
            continue
        if c == "'":
            i += 1
            while i < n:
                if text[i] == "'":
                    if i + 1 < n and text[i + 1] == "'":
                        i += 2
                        continue
                    break
                i += 1
            i += 1
            continue
        if c == '"':
            i += 1
            while i < n:
                if text[i] == '`':
                    i += 2
                    continue
                if text[i] == '"':
                    break
                i += 1
            i += 1
            continue
        if c == '{':
            depth += 1
        elif c == '}':
            depth -= 1
        i += 1
    return depth


def main():
    root = os.getcwd()
    src = os.path.join(root, SRC_NAME)
    dst = os.path.join(root, DST_NAME)

    if not os.path.isfile(src):
        die('%s not found in %s - run this from the repo root' % (SRC_NAME, root))

    raw = open(src, 'rb').read()
    got_md5 = md5_of(raw)

    print('input   %s' % SRC_NAME)
    print('        %d bytes, md5 %s' % (len(raw), got_md5))
    if len(raw) != SRC_BYTES:
        die('input is %d bytes, expected %d' % (len(raw), SRC_BYTES))
    if got_md5 != SRC_MD5:
        die('input md5 %s, expected %s' % (got_md5, SRC_MD5))
    print('        md5 and length both match the v0.0.20 pin')
    print()

    text = raw.decode(ENC)
    base_balance = brace_balance(text)

    print('substitutions')
    for label, old, new in SUBS:
        n = text.count(old)
        if n != 1:
            die('anchor "%s" occurs %d times, expected exactly 1' % (label, n))
        text = text.replace(old, new, 1)
        print('  ok    %s' % label)
    print()

    out = text.encode(ENC)

    print('self-checks')
    for label, needle, want in PRESENT:
        n = text.count(needle)
        if n != want:
            die('%s: found %d, expected %d' % (label, n, want))
        print('  ok    present  %s' % label)
    for label, needle in ABSENT:
        n = text.count(needle)
        if n != 0:
            die('%s: still present %d time(s) - a substitution did not apply' % (label, n))
        print('  ok    absent   %s' % label)

    bal = brace_balance(text)
    if bal != base_balance:
        die('brace depth %d, input was %d - try/finally is unbalanced' % (bal, base_balance))
    print('  ok    braces        balanced, depth %d, same as input' % bal)

    i_try = text.find('\ntry {\n')
    i_fin = text.find('\nfinally {\n')
    i_first_exit = text.find('\n    exit 1\n')
    i_last_exit = text.rfind('    exit 0\n')
    if not (0 < i_try < i_first_exit):
        die('try does not open before the first exit')
    if not (i_last_exit < i_fin):
        die('finally does not close after the last exit')
    print('  ok    wrap          try before first exit, finally after last')

    cr = out.count(b'\r')
    lf = out.count(b'\n')
    hi = sum(1 for b in out if b > 127)
    if cr != 0:
        die('output carries %d CR bytes; Publish-Step16 is LF only' % cr)
    if hi != 0:
        die('output carries %d bytes above 0x7f; keep it ASCII' % hi)
    print('  ok    line endings   %d LF, 0 CR' % lf)
    print('  ok    encoding       ASCII, 0 high bytes')
    print()

    open(dst, 'wb').write(out)

    print('output  %s' % DST_NAME)
    print('        %d bytes, md5 %s' % (len(out), md5_of(out)))
    print('        %d LF  (was %d)' % (lf, raw.count(b'\n')))
    print()
    print('self-check ok')
    print()
    print('-' * 70)
    print('NOT YET RELEASE-READY. This file still carries v0.0.20 constants.')
    print('When v0.0.21 is built, update in Publish-Step17.ps1:')
    print('  $Tag $PrevTag $CtlJsTag        the three tag names')
    print('  $Expected                      3 x md5 / bytes / CRLF')
    print('  $PrevClassMd5 $CtlJsMd5        both control blob hashes')
    print('  $BuildOutput $StageMap         build/out21 paths')
    print('  $ToolFiles                     md5 AND bytes, both measured')
    print('  $PinRows                       6 rows, backslashes load-bearing')
    print('  $TestFiles                     add the v021 suite and build script')
    print('  $JsSuites $PhpSuites           per-suite totals, re-measured')
    print('  both negative control triples  and the grand total')
    print('-' * 70)


if __name__ == '__main__':
    main()
