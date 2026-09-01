/**
 * SLP Dealer Guard - v0.0.14 suite. Issue 25, the truncated geocode.
 *
 * encodeURI() at slp_core.js:806 does not escape "#" or "?", and both are
 * structural in a URL: "#" opens a fragment the browser never sends, "?" opens
 * a query string that ends the path segment the proxy reads the address from.
 * A typed suite number therefore reached Google as a bare street name with no
 * city and no state.
 *
 * Two things this suite is deliberately strict about.
 *
 * The subscription, not the helper. The whole lesson of Issue 14 is that a
 * helper can be provably correct and never called - an earlier session's tests
 * proved a change handler worked while nothing on the page invoked it. So the
 * cases below install a working slp_Filter, call avalon_init_gmaps() the way
 * the page does, publish a geocoder_request object and assert on what came out
 * the other side. An implementation that defined avalon_geocode_safe() and
 * forgot to subscribe passes nothing here.
 *
 * The scope. This build must change the string sent to Google and NOTHING
 * else. The cases marked [GUARD] assert that #addressInput and the composed
 * place_address parameter still carry exactly what the visitor typed, "#"
 * included. They hold on both builds; they exist so nobody later "simplifies"
 * this by sanitising the field itself, which would silently rewrite what a
 * shared link says.
 *
 * NEGATIVE CONTROL, decision 20: run against the v0.0.13 artefact first and
 * confirm it FAILS. It should fail every case marked [DISCRIMINATOR] - there
 * is no subscriber there, so the address arrives unchanged - and pass every
 * [GUARD].
 *
 *   node test/suite-v014.js <path-to-artefact>
 */

"use strict";

const path = require("path");
const { load, ok, eq, report } = require("./harness");

const artefact = process.argv[2] || path.join(__dirname, "..", "out14", "slp_avalon.js");
const { ctx, state } = load(artefact);
const guard = ctx.avalon_guard;

const BASE = "https://example.test/find-a-dealer/";

/**
 * Identity stand-in when the helper is absent, so the negative control reports
 * every failed assertion instead of dying on the first missing function. Same
 * reason suite-v008 calls install_options_hook() through a wrapper and
 * suite-v012.php gives its shim a __call().
 */
const safe =
  typeof ctx.avalon_geocode_safe === "function"
    ? ctx.avalon_geocode_safe
    : function (a) {
        return a;
      };

/* ------------------------------------------------------------ structure */

ok(typeof ctx.avalon_geocode_safe === "function", "avalon_geocode_safe() exists");

/* ------------------------------ [DISCRIMINATOR] the helper's own behaviour */

[
  ["1200 Woodward Ave #4, Detroit, MI", "1200 Woodward Ave 4, Detroit, MI",
   "a suite number no longer truncates the proxy URL"],
  ["#4 Main St, Lowell MI", "4 Main St, Lowell MI",
   "a leading hash is cleaned and the result trimmed"],
  ["1 Elm St ?, Toronto ON", "1 Elm St , Toronto ON",
   "a question mark is handled too - it ends the path segment"],
  ["Apt #2 #3, Detroit", "Apt 2 3, Detroit",
   "several occurrences are all replaced"],
  ["100 King St W #1500, Toronto, ON M5X 1A9", "100 King St W 1500, Toronto, ON M5X 1A9",
   "a Canadian postal code survives the clean"],
].forEach(([input, want, label]) => {
  eq(safe(input), want, label);
});

/* ------------------------------------------- [GUARD] the no-op early return */

/* These hold on v0.0.13 as well, because the stand-in is the identity. They
   exist so the early return cannot be removed as redundant: without it every
   address would be whitespace-collapsed and trimmed, and a geocode that went
   wrong would be that much harder to reason about. */
[
  "Detroit, MI",
  "48127",
  "M5H 2N2",
  "Sault Ste. Marie",
  "St. John's, NL",
  "Trois-Rivi\u00e8res",
  "1200 Woodward Ave Suite 4, Detroit, MI",
].forEach((clean) => {
  eq(safe(clean), clean, `clean input is returned untouched: ${JSON.stringify(clean)}`);
});

eq(safe(null), null, "a null address is passed through, not coerced");
eq(safe(undefined), undefined, "so is undefined");
eq(safe(42), 42, "and a non-string is returned as it arrived");

/* --------------------------------- [GUARD] Layer 0 makes the empty case dead */

/* avalon_geocode_safe() can only be handed something that cleans to "" if the
   whole address is punctuation, and the floor rejects that first. Asserted so
   nobody adds an empty-string branch that can never run - or worse, removes
   the floor and makes one reachable. */
const floor = ctx.AVALON_SEARCHABLE || { test: () => false };
ok(floor.test("1200 Woodward Ave #4"), "a suite number PASSES the floor, so this path is real");
ok(!floor.test("#"), "a bare hash is rejected at Layer 0 and never reaches a geocode");
ok(!floor.test("?"), "so is a bare question mark");
ok(!floor.test("#?"), "and both together");

/* ------------------- [DISCRIMINATOR] the subscription, through the real path */

/**
 * Working pub/sub in place of the harness's no-op, installed before
 * avalon_init_gmaps() runs so the subscription is real. slp_Filter is looked
 * up from the context at call time, so replacing it after load is enough.
 */
const subscribers = {};
ctx.slp_Filter = function (name) {
  return {
    subscribe: function (fn) {
      (subscribers[name] = subscribers[name] || []).push(fn);
    },
    publish: function (arg) {
      (subscribers[name] || []).forEach(function (fn) {
        fn(arg);
      });
      return arg;
    },
  };
};

ctx.avalon_init_gmaps();

ok(
  Array.isArray(subscribers["geocoder_request"]) &&
    subscribers["geocoder_request"].length === 1,
  "avalon_init_gmaps() subscribes to geocoder_request exactly once"
);

/**
 * Publish the same object shape slp_core.js:1624-1647 builds, and read back
 * what geocoder.geocode() would have received at 1649.
 */
function publishRequest(address) {
  const request = { address: address, region: "us" };
  ctx.slp_Filter("geocoder_request").publish(request);
  return request;
}

eq(
  publishRequest("1200 Woodward Ave #4, Detroit, MI").address,
  "1200 Woodward Ave 4, Detroit, MI",
  "the live subscriber cleans the address on its way to the geocoder"
);
eq(
  publishRequest("Detroit, MI").address,
  "Detroit, MI",
  "and leaves a clean address exactly as it was"
);
eq(
  publishRequest("1 Elm St ?, Toronto ON").address,
  "1 Elm St , Toronto ON",
  "the question-mark case goes through the live path too"
);

/* The region must not be collateral damage - it is a separate path segment. */
eq(publishRequest("Anywhere #1").region, "us", "region is untouched");

/* A caller publishing nothing must not throw. slp_core always passes the
   request object, but the subscriber is global and this costs one line. */
let threw = false;
try {
  ctx.slp_Filter("geocoder_request").publish(undefined);
} catch (e) {
  threw = true;
}
ok(!threw, "publishing an absent request is survivable, not a TypeError");

/* --------------------- [GUARD] the visitor's own text is NOT rewritten */

/**
 * Same shape as suite-v010 and suite-v013: drive the real entry point rather
 * than poking helpers, and assert on what escaped.
 */
function harnessSearch(fieldValue) {
  ctx.window.location.href = BASE;
  state.replaceState.length = 0;
  state.inputValue = fieldValue;
  state.data.place_lat = null;
  state.data.place_lng = null;
  state.data.place_country = null;

  const seen = { geocoded: 0 };
  ctx.avalon_cslmap = {
    gmap: {},
    saneValue: (id, dflt) => (id === "addressInput" ? String(fieldValue).trim() : dflt),
    unhide_map: () => {},
    doGeocode: () => (seen.geocoded += 1),
    process_geocode_response: () => {},
    load_markers: () => {},
    address: null,
  };

  guard.state = guard.IDLE;
  guard.last_state = null;
  guard.pending_url = null;
  ctx.cslmap_searchLocations();
  return { seen: seen, address: ctx.avalon_cslmap.address, pending: guard.pending_url };
}

const typed = "1200 Woodward Ave #4, Detroit, MI";
const r = harnessSearch(typed);

eq(r.seen.geocoded, 1, "a typed suite number still reaches the geocoder");
eq(guard.state, "RESOLVING", "and is left in RESOLVING, not rejected by Layer 0");

/* cslmap.address is what doGeocode() copies into the request, and the
   subscriber cleans it afterwards - so at THIS point it still has the hash.
   Sanitising here instead would rewrite the field and the shared URL. */
eq(r.address, typed, "cslmap.address still holds what the visitor typed");

ok(
  r.pending !== null && r.pending.indexOf("%234") !== -1,
  "the composed URL keeps the hash, percent-encoded - a shared link is faithful"
);
ok(
  r.pending !== null && r.pending.indexOf("place_address=") !== -1,
  "and still carries place_address at all"
);

/* ------------------------------------- [GUARD] nothing from v0.0.13 moved */

ok(typeof guard.clean_url === "function", "clean_url() is still present");

guard.start(true);
guard.pending_url = BASE + "?place_address=Detroit";
guard.finish(guard.RESULTS);
eq(
  state.replaceState[state.replaceState.length - 1][2],
  BASE + "?place_address=Detroit",
  "RESULTS still writes pending_url verbatim"
);

ctx.window.location.href = BASE + "?place_lat=48.86&place_lng=2.35&utm_source=fb";
state.replaceState.length = 0;
guard.state = guard.IDLE;
guard.last_state = null;
guard.start(true);
guard.finish(guard.REJECTED);
eq(
  state.replaceState[state.replaceState.length - 1][2],
  BASE + "?utm_source=fb",
  "C1: a rejection still drops the search keys and keeps attribution"
);

process.exit(report(path.basename(artefact) + " :: suite-v014"));
