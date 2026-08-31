/**
 * SLP Dealer Guard - v0.0.13 suite. Issue 16, the sticky URL.
 *
 * finish() has always written pending_url on RESULTS and never written it on
 * anything else, which stops a failure from dirtying the address bar. It never
 * cleaned one the visitor arrived on, so a bad URL survived a refresh and was
 * re-rejected forever. v0.0.13 adds the other half: write on RESULTS, clean on
 * every other terminal state.
 *
 * Two things this suite is deliberately strict about.
 *
 * The remove-list. Constraint C1 says UTM parameters land on a first-touch URL
 * before any cookie exists, so anything that discards them loses attribution
 * for exactly the visitors it is meant to measure. Several cases below carry
 * utm_source, utm_medium and gclid through a rejection and assert they came
 * out the other side, because a keep-list implementation would pass every
 * other assertion here.
 *
 * The no-op. suite-core asserts that a rejected search issues no replaceState.
 * That is the regression net for Issue 1's rule (c) and it must not be
 * weakened to accommodate this feature, so clean_url() returns early when the
 * URL is already clean. The cases marked [GUARD] below are the ones that hold
 * on both builds; they exist so nobody "simplifies" the early return away.
 *
 * NEGATIVE CONTROL, decision 20: run against the v0.0.12 artefact first and
 * confirm it FAILS. It should fail every case marked [DISCRIMINATOR] - the
 * clean never happens there - and pass every [GUARD].
 *
 *   node test/suite-v013.js <path-to-artefact>
 */

"use strict";

const path = require("path");
const { load, ok, eq, report } = require("./harness");

const artefact = process.argv[2] || path.join(__dirname, "..", "out13", "slp_avalon.js");
const { ctx, state } = load(artefact);
const guard = ctx.avalon_guard;

const BASE = "https://example.test/find-a-dealer/";

/**
 * Put the browser at `href`, open a cycle, and reach `terminal`.
 *
 * Goes through start() rather than assigning state directly, so the cycle is
 * real: finish() no-ops unless is_busy(), and a test that skipped start()
 * would pass against any build by doing nothing at all.
 */
function finishAt(href, terminal, pending) {
  ctx.window.location.href = href;
  state.replaceState.length = 0;
  guard.state = guard.IDLE;
  guard.last_state = null;
  guard.start(true);
  guard.pending_url = pending || null;
  guard.finish(terminal);
  const last = state.replaceState[state.replaceState.length - 1];
  return { calls: state.replaceState.length, url: last ? last[2] : null };
}

/**
 * Query parameters of `href`, or a stand-in that answers null to everything.
 *
 * The stand-in matters for the negative control: against a build with no
 * clean_url() these reads would otherwise throw on null and the suite would
 * die partway through, reporting a handful of failures instead of all of
 * them. Same reason the JS suites call install_options_hook() through a
 * wrapper and suite-v012.php gives its shim a __call().
 */
function params(href) {
  return href === null ? { get: () => null } : new URL(href).searchParams;
}

/* ------------------------------------------------------------ structure */

ok(typeof guard.clean_url === "function", "clean_url() exists");

/* ------------------------------- [DISCRIMINATOR] the defect, both shapes */

/* The reproduction from the handoff: a coordinate pair that Layer 0 rejects.
   Before v0.0.13 this left the URL alone and a refresh replayed Paris. */
let r = finishAt(BASE + "?place_lat=48.86&place_lng=2.35", guard.REJECTED);
eq(r.calls, 1, "a rejected coordinate URL is rewritten");
eq(r.url, BASE, "and the coordinates are gone");

r = finishAt(BASE + "?place_address=Paris%2C+France", guard.REJECTED);
eq(r.calls, 1, "a rejected address URL is rewritten");
eq(r.url, BASE, "and the address is gone");

/* All three keys at once, which is what an autocomplete selection leaves. */
r = finishAt(BASE + "?place_address=Paris&place_lat=48.86&place_lng=2.35", guard.REJECTED);
eq(r.url, BASE, "all three keys are removed together");

/* ------------------------- [DISCRIMINATOR] every non-RESULTS terminal state */

/* Decision 35. s7.7 listed REJECTED, ERROR and TIMEOUT; EMPTY was added
   because without it, landing on ?place_address=Detroit and then searching
   somewhere with no dealers leaves the bar replaying Detroit. */
[
  [guard.REJECTED, "REJECTED"],
  [guard.ERROR, "ERROR"],
  [guard.TIMEOUT, "TIMEOUT"],
  [guard.EMPTY, "EMPTY"],
].forEach(([terminal, name]) => {
  const got = finishAt(BASE + "?place_address=Detroit%2C+MI", terminal);
  eq(got.url, BASE, `${name} cleans the URL`);
});

/* --------------------------------------- [DISCRIMINATOR] constraint C1 */

/* A keep-list implementation passes everything above and fails here. */
r = finishAt(
  BASE + "?place_address=Paris&utm_source=facebook&utm_medium=cpc&utm_campaign=spring&gclid=abc123",
  guard.REJECTED
);
eq(
  r.url,
  BASE + "?utm_source=facebook&utm_medium=cpc&utm_campaign=spring&gclid=abc123",
  "C1: the search key is removed and every attribution key is kept, in order"
);
const kept = params(r.url);
eq(kept.get("utm_source"), "facebook", "C1: utm_source survives a rejection");
eq(kept.get("utm_medium"), "cpc", "C1: utm_medium survives");
eq(kept.get("utm_campaign"), "spring", "C1: utm_campaign survives");
eq(kept.get("gclid"), "abc123", "C1: gclid survives");

/* Nothing outside the three keys is the cleaner's business. */
r = finishAt(BASE + "?place_lat=1&place_lng=2&radius=50&tag=pontoon", guard.REJECTED);
eq(r.url, BASE + "?radius=50&tag=pontoon", "only the three search keys are removed");
const other = params(r.url);
eq(other.get("radius"), "50", "an unrelated parameter survives");
eq(other.get("tag"), "pontoon", "so does another");

/* The fragment is not a query parameter and must come through untouched. */
r = finishAt(BASE + "?place_lat=48.86&place_lng=2.35#results", guard.REJECTED);
eq(r.url, BASE + "#results", "the fragment survives");

/* ------------------------------------------- [GUARD] RESULTS is unchanged */

/* Rule (c) from Issue 1. A successful search still writes its composed URL,
   and must NOT be cleaned on the way past. */
r = finishAt(BASE, guard.RESULTS, BASE + "?place_address=Detroit%2C+MI");
eq(r.calls, 1, "RESULTS still writes pending_url");
eq(r.url, BASE + "?place_address=Detroit%2C+MI", "and writes it verbatim");

r = finishAt(
  BASE + "?place_address=Detroit",
  guard.RESULTS,
  BASE + "?place_address=Detroit&place_lat=42.33&place_lng=-83.05"
);
eq(
  r.url,
  BASE + "?place_address=Detroit&place_lat=42.33&place_lng=-83.05",
  "a successful search keeps its parameters - they describe what is on screen"
);

/* get_user_current_address() reaches load_markers() directly without going
   through cslmap_searchLocations(), so no URL is composed. RESULTS with a null
   pending_url must fall through both branches and touch nothing. */
r = finishAt(BASE, guard.RESULTS, null);
eq(r.calls, 0, "RESULTS with no pending_url writes nothing");

/* ------------------------------------------------------ [GUARD] the no-op */

/* These hold on v0.0.12 as well. They exist so the early return in
   clean_url() cannot be removed as redundant: without it, suite-core's
   "a rejected search does not rewrite the URL" goes red. */
r = finishAt(BASE, guard.REJECTED);
eq(r.calls, 0, "a rejection from an already-clean URL rewrites nothing");

r = finishAt(BASE + "?utm_source=facebook", guard.REJECTED);
eq(r.calls, 0, "a URL carrying only UTMs is already clean - no rewrite");

r = finishAt(BASE, guard.ERROR);
eq(r.calls, 0, "same for ERROR");

/* --------------------------------------------- [GUARD] the cycle is intact */

/* A late callback after the 12s ceiling must not reopen anything, and must
   certainly not clean the URL a second time. */
ctx.window.location.href = BASE + "?place_address=Paris";
state.replaceState.length = 0;
guard.state = guard.IDLE;
guard.last_state = null;
guard.finish(guard.REJECTED);
eq(state.replaceState.length, 0, "finish() with no cycle open still no-ops");

/* ------------------------- [DISCRIMINATOR] through the real entry point */

/**
 * Same shape as suite-v010: drive cslmap_searchLocations() rather than poking
 * finish() directly, because the whole lesson of Issue 14 is that a helper can
 * be provably correct and never called.
 */
function harnessSearch(href, fieldValue, placeLat, placeLng, placeCountry) {
  ctx.window.location.href = href;
  state.replaceState.length = 0;
  state.inputValue = fieldValue;
  state.data.place_lat = placeLat;
  state.data.place_lng = placeLng;
  state.data.place_country = placeCountry;

  ctx.avalon_cslmap = {
    gmap: {},
    saneValue: (id, dflt) => (id === "addressInput" ? String(fieldValue).trim() : dflt),
    unhide_map: () => {},
    doGeocode: () => {},
    process_geocode_response: () => {},
    load_markers: () => {},
    address: null,
  };

  guard.state = guard.IDLE;
  guard.last_state = null;
  ctx.cslmap_searchLocations();
  const last = state.replaceState[state.replaceState.length - 1];
  return { calls: state.replaceState.length, url: last ? last[2] : null };
}

/* The exact scenario in the handoff: the visitor arrives on Paris coordinates,
   Layer 0 rejects before any geocode, and the URL must not survive to be
   replayed. Layer 0 returns before pending_url is assigned, so this also
   proves the clean is measured from window.location.href rather than from
   pending_url - an implementation that used pending_url would do nothing. */
r = harnessSearch(BASE + "?place_lat=48.86&place_lng=2.35", "Paris, France", "48.86", "2.35", null);
eq(guard.last_state, "REJECTED", "Layer 0 still rejects the Paris bootstrap");
eq(r.calls, 1, "and the URL is cleaned on the way out");
eq(r.url, BASE, "leaving nothing to replay on refresh");

/* Same path, with attribution attached, because this is how a paid landing
   arrives and it is the case C1 was written for. */
r = harnessSearch(
  BASE + "?place_lat=48.86&place_lng=2.35&utm_source=facebook&gclid=xyz789",
  "Paris, France", "48.86", "2.35", null
);
eq(
  r.url,
  BASE + "?utm_source=facebook&gclid=xyz789",
  "C1 holds through the real entry point, not just through finish()"
);
const live = params(r.url);
eq(live.get("utm_source"), "facebook", "utm_source survives a live Layer 0 rejection");
eq(live.get("gclid"), "xyz789", "including the click ID");

/* A search that is still in flight has reached no terminal state, so nothing
   should have been written yet. Catches an implementation that cleaned at
   start() instead of at finish(). */
r = harnessSearch(BASE + "?place_address=Detroit%2C+MI", "Detroit, MI", null, null, null);
eq(guard.state, "RESOLVING", "an in-territory search is left in RESOLVING");
eq(r.calls, 0, "and the URL is untouched while the search is still running");

process.exit(report(path.basename(artefact) + " :: suite-v013"));
