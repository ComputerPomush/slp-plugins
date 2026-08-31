/**
 * SLP Dealer Guard - core regression suite.
 *
 * Covers the invariants shipped in v0.0.5 through v0.0.7: the country reader,
 * the allow-list, the territory boxes and their agreement with the PHP mirror,
 * the guard state machine, and the injected stylesheet.
 *
 * This REPLACES rather than reproduces test/suite-v006.js and
 * test/suite-v007.js. Those were written in a previous session's container and
 * were never committed, so the "98 assertions, already green" claim in the
 * rev 7 handoff s8 described artefacts that no longer existed. Everything here
 * is committed at the repo root, outside slp_avalon/, so it is neither
 * uploaded to a server nor covered by `slp_avalon/** -text`.
 *
 *   node test/suite-core.js <path-to-artefact>
 */

"use strict";

const path = require("path");
const { load, ok, eq, report } = require("./harness");

const artefact = process.argv[2] || path.join(__dirname, "..", "out", "slp_avalon.js");
const { ctx, state } = load(artefact);
const guard = ctx.avalon_guard;

/* ------------------------------------------------------- the allow-list */

eq(
  ctx.AVALON_ALLOWED_COUNTRIES,
  ["US", "PR", "VI", "GU", "MP", "AS", "CA"],
  "allow-list is the seven documented codes, in order"
);

/* ------------------------------------------ avalon_country_of, decision 18 */

const co = ctx.avalon_country_of;

function result(components) {
  return { address_components: components };
}

eq(
  co(result([{ short_name: "us", types: ["country", "political"] }])),
  "US",
  "country short_name is upper-cased"
);
eq(
  co(result([{ short_name: "  ca  ", types: ["country"] }])),
  "CA",
  "country short_name is trimmed"
);
eq(
  co(result([
    { short_name: "Paris", types: ["locality"] },
    { short_name: "FR", types: ["country"] },
  ])),
  "FR",
  "picks the component whose types contains country, not the first"
);

/* null is the no-op signal: rejecting on a guess is worse than a round trip */
eq(co(result([])), null, "empty components yield null");
eq(co(result([{ short_name: "MI", types: ["administrative_area_level_1"] }])), null,
  "no country component yields null");
eq(co(result([{ short_name: 42, types: ["country"] }])), null,
  "non-string short_name yields null");
eq(co(result([{ short_name: "", types: ["country"] }])), null,
  "empty short_name yields null");
eq(co(result([{ short_name: "US" }])), null, "component with no types yields null");
eq(co({}), null, "result with no address_components yields null");
eq(co(null), null, "null result yields null");
eq(co(undefined), null, "undefined result yields null");

/* --------------------------------------------- boxes mirror the PHP source */

/* Values transcribed from SLP_Avalon::territory_boxes(), at
   slp_avalon/inc/class.slp_avalon.php 1244-1263 as of v0.0.13.

   The method name is the anchor, not the range. This comment read 1103-1122
   until v0.0.12, which was exact at v0.0.11 and went stale the moment
   build-v012.py inserted the provinces and their helpers 141 lines above the
   method. Re-measure by grep after any edit; never adjust the offset by hand.

   If this fails, one side was edited without the other. */
const PHP_BOXES = [
  ["CONUS + Canada", 24.4, 83.2, -141.0, -52.0],
  ["Alaska", 51.0, 71.6, -173.0, -129.0],
  ["Western Aleutians", 51.0, 54.0, -180.0, -173.0],
  ["Aleutian wrap", 51.0, 54.0, 172.0, 180.0],
  ["Hawaii", 18.5, 22.5, -160.6, -154.6],
  ["Puerto Rico + USVI", 17.6, 18.6, -67.5, -64.5],
  ["Guam + CNMI", 13.2, 20.6, 144.5, 146.1],
  ["American Samoa", -14.6, -11.0, -171.2, -168.0],
];

const jsBoxes = ctx.AVALON_TERRITORY_BOXES;
eq(jsBoxes.length, 8, "eight boxes, matching the PHP mirror");
PHP_BOXES.forEach((box, i) => {
  const j = jsBoxes[i] || [];
  eq(
    [Number(j[1]), Number(j[2]), Number(j[3]), Number(j[4])],
    [box[1], box[2], box[3], box[4]],
    `box ${i} (${box[0]}) matches territory_boxes()`
  );
});

/* ---------------------------------- avalon_in_territory, the s8 coordinates */

const inTerr = ctx.avalon_in_territory;
const CASES = [
  ["Detroit", 42.3314, -83.0458, true],
  ["Honolulu", 21.3069, -157.8583, true],
  ["San Juan PR", 18.4655, -66.1057, true],
  ["Hagatna GU", 13.4745, 144.7504, true],
  ["Pago Pago AS", -14.2756, -170.702, true],
  ["Adak AK", 51.88, -176.63, true],
  ["Toronto ON", 43.6532, -79.3832, true],
  ["Paris FR", 48.8566, 2.3522, false],
  ["Apia WS", -13.833, -171.765, false],
  ["Mexico City", 19.43, -99.13, false],
  ["Gulf of Guinea 0,0", 0, 0, false],
];
CASES.forEach(([name, lat, lng, expected]) => {
  eq(inTerr(lat, lng), expected, `avalon_in_territory: ${name}`);
});

/* Tijuana and Nassau pass the coarse boxes on purpose - that is why Layer 1
   exists and why decision 16 keeps boxes out of it. Asserted so nobody
   "fixes" the boxes and quietly changes the layer model. */
eq(inTerr(32.5149, -117.0382), true, "Tijuana passes the boxes by design");
eq(inTerr(25.0343, -77.3963), true, "Nassau passes the boxes by design");

eq(inTerr("abc", -83), false, "non-numeric latitude is out of territory");
eq(inTerr(null, null), false, "null coordinates are out of territory");
eq(inTerr(95, -83), false, "impossible latitude is out of territory");

/* --------------------------------------------------- the state machine */

eq(guard.state, "IDLE", "starts IDLE");
ok(!guard.is_busy(), "IDLE is not busy");

const gen = guard.start(true);
eq(guard.state, "RESOLVING", "start() enters RESOLVING");
ok(guard.is_busy(), "RESOLVING is busy");
eq(guard.user_initiated, true, "start(true) records user intent");
eq(guard.last_response, null, "start() clears last_response");

guard.enter(guard.SEARCHING);
eq(guard.state, "SEARCHING", "enter() moves between busy states");

ok(!guard.is_stale(gen), "the live generation is not stale");
ok(guard.is_stale(gen - 1), "a previous generation is stale");

guard.finish(guard.REJECTED, { message: ctx.AVALON_GUARD_MESSAGES.territory });
eq(guard.state, "IDLE", "finish() returns to IDLE");
eq(guard.last_state, "REJECTED", "finish() records the terminal state");

/* A late callback arriving after a timeout must not fire a second terminal
   transition. */
guard.last_state = null;
guard.finish(guard.ERROR);
eq(guard.last_state, null, "double-finish is a no-op when no cycle is open");

/* Decision 11: one ceiling for the whole search, not one per leg. */
eq(ctx.AVALON_GUARD_TIMEOUT_MS, 12000, "the ceiling is 12 seconds");

/* Issue 1 rule (c): the address bar is rewritten only on success, so a
   refresh cannot replay a URL that failed. */
guard.start(true);
guard.pending_url = "https://example.test/find-a-dealer/?place_address=Paris";
guard.finish(guard.REJECTED);
eq(state.replaceState.length, 0, "a rejected search does not rewrite the URL");

guard.start(true);
guard.pending_url = "https://example.test/find-a-dealer/?place_address=Detroit";
guard.finish(guard.RESULTS);
eq(state.replaceState.length, 1, "a successful search does rewrite the URL");

/* ------------------------------------------------- the injected stylesheet */

const css = state.appends.join("");
ok(css.indexOf("avalon_guard_css") !== -1, "the guard stylesheet is injected");
ok(
  css.indexOf(".avalon_search_notification{color:#E7167C") !== -1,
  "notification uses the 4.55:1 brand pink, not the 3.4:1 #c00"
);
ok(css.indexOf("#c00") === -1, "the failing contrast value is gone");
ok(
  css.indexOf(".avalon_sidebar_prompt{color:#FFFFFF") !== -1,
  "Issue 12: the sidebar prompt is legible on the #090909 panel"
);
/* v0.0.9. The transform rule is deliberately absent: .fa-spin animates the
   same property and an animation outranks an author normal declaration, so
   it never applied. center_spinner() positions the icon in left/top instead. */
ok(
  css.indexOf("transform:translate") === -1,
  "no transform rule on the spinner icon - .fa-spin would discard it"
);
ok(
  typeof guard.center_spinner === "function",
  "center_spinner() exists to position the icon at show time"
);

/* One block, injected once, however many surfaces ask for it. */
const before = state.appends.length;
if (typeof guard.ensure_guard_css === "function") {
  guard.ensure_guard_css();
  guard.ensure_guard_css();
}
eq(state.appends.length, before, "ensure_guard_css is idempotent");

/* ------------------------------------------------------------- messages */

const M = ctx.AVALON_GUARD_MESSAGES;
["geocode_failed", "timeout", "transport", "territory"].forEach((k) => {
  ok(typeof M[k] === "string" && M[k].length > 0, `message ${k} is present`);
});

process.exit(report(path.basename(artefact) + " :: suite-core"));
