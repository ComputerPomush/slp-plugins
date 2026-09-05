/**
 * SLP Dealer Guard - v0.0.19 suite.
 *
 * Two halves, and they are deliberately different in kind.
 *
 * [DISCRIMINATOR] - Issue 35, the Autocomplete gate. These must FAIL against
 * the v0.0.18 tag blob, each for its own reason. They drive the real entry
 * point, initialize_autocomplete(), and assert on how many times the Places
 * widget was actually constructed - not on whether a constant exists.
 * Asserting the constant would pass for a build that defined 3 and then
 * ignored it.
 *
 * [GUARD] - decision 66. Issue 22 is CLOSED, working as designed:
 * initial_results_returned and max_results_returned are both registered
 * base-plugin options (slp_core.js:1720-1721) and the csl_ajax_onload latch
 * at 1841 is the mechanism by which the first search of a page load reads one
 * and every later search reads the other. These cases hold on BOTH builds and
 * exist so that a future release cannot quietly "fix" the latch and delete a
 * documented setting. They are inherited from the abandoned suite-v015.js,
 * whose discriminators asserted the opposite and have been dropped with it.
 *
 * The gate is observed by SPYING, not by inspection. harness.js leaves
 * jQuery's on() and off() as no-ops in its chain list, so the delegated
 * listener cannot be driven through the stub as shipped. Rather than change
 * harness.js - which six other JS suites load - this suite replaces
 * ctx.jQuery.fn.on, ctx.jQuery.fn.off and ctx.google.maps.places.Autocomplete
 * after load and before calling the entry point. That is early enough:
 * initialize_autocomplete() is invoked from avalon_init_gmaps() at line 1270,
 * never at load, so nothing has run by then.
 *
 * Each scenario gets a FRESH context. avalon_autocomplete_attached is a
 * one-way latch by design, so reusing one context would make every scenario
 * after the first assert against an already-spent gate.
 *
 * NEGATIVE CONTROL, decision 20: run against the v0.0.18 tag blob first and
 * confirm it fails. Expected there - the widget is constructed the moment
 * initialize_autocomplete() is called, no listener is ever registered, and
 * none of the three new globals exist. Every [GUARD] must still pass.
 *
 *   node test/suite-v019.js <path-to-artefact>
 */

"use strict";

const path = require("path");
const { load, ok, eq, report } = require("./harness");

const artefact =
  process.argv[2] ||
  path.join(__dirname, "..", "build", "out19", "slp_avalon.js");

const BASE = "https://example.test/find-a-dealer/";

/* ===================================================================== */
/* [DISCRIMINATOR] Issue 35 - the Autocomplete gate                      */
/* ===================================================================== */

/**
 * Fresh context with the widget constructor and jQuery's on/off spied.
 *
 * @param  {string} [seed]  value already in #addressInput before the gate runs
 * @param  {object} [opts]  {noGoogle:true} to simulate Maps failing to load
 */
function newGate(seed, opts) {
  const o = opts || {};
  const { ctx, state } = load(artefact);

  const built = [];
  const handlers = [];
  const offs = [];

  ctx.google.maps.places.Autocomplete = function (input) {
    built.push(input && input.id ? input.id : String(input));
    this.setFields = function () {};
    this.addListener = function () {};
  };

  ctx.jQuery.fn.on = function (events, selector, fn) {
    handlers.push({
      events: String(events),
      selector: String(selector),
      fn: fn,
    });
    return this;
  };
  // Actually removes the handler, because jQuery does. A recording-only off()
  // leaves the listener live in the spy, the artefact's own unbind looks like
  // it failed, and the suite reports a defect that exists only in the stub.
  ctx.jQuery.fn.off = function (events, selector) {
    offs.push(String(events) + " " + String(selector));
    for (let i = handlers.length - 1; i >= 0; i -= 1) {
      if (
        handlers[i].events === String(events) &&
        handlers[i].selector === String(selector)
      ) {
        handlers.splice(i, 1);
      }
    }
    return this;
  };

  if (o.noGoogle) delete ctx.google;
  if (seed !== undefined) state.inputValue = seed;

  let threw = false;
  try {
    ctx.initialize_autocomplete();
  } catch (e) {
    // The v0.0.18 build dereferences google unconditionally, so the
    // no-Google scenario throws there. Recorded, not propagated: a negative
    // control has to produce a score, not a stack trace.
    threw = true;
  }

  const el = ctx.document.getElementById("addressInput");

  /**
   * Type a value and fire the most recent delegated handler.
   * Returns false when no listener was ever registered, which is the
   * v0.0.18 case - the caller asserts on `built`, which is defined either way.
   */
  function fire(value) {
    state.inputValue = value;
    const h = handlers[handlers.length - 1];
    if (!h) return false;
    h.fn.call(el);
    return true;
  }

  return { ctx, state, built, handlers, offs, fire, el, threw };
}

/* ------------------------------------------- the three globals exist */

let g = newGate();

eq(
  g.ctx.avalon_autocomplete_min_chars,
  3,
  "[DISCRIMINATOR] the threshold is a constant in our own file and reads 3"
);
ok(
  typeof g.ctx.avalon_attach_autocomplete === "function",
  "[DISCRIMINATOR] the attach helper is a separate function"
);

/* --------------------------- nothing is constructed on the way in */

eq(
  g.built.length,
  0,
  "[DISCRIMINATOR] initialize_autocomplete() no longer constructs the widget"
);
eq(
  g.ctx.avalon_autocomplete_attached,
  false,
  "[DISCRIMINATOR] and the attached flag is still false"
);
eq(
  g.handlers.length,
  1,
  "[DISCRIMINATOR] it registers exactly one deferred listener instead"
);

const h0 = g.handlers[0] || {};
eq(
  h0.events,
  "input.avalon_ac",
  "[DISCRIMINATOR] on the input event, namespaced so it can unbind itself"
);
eq(
  h0.selector,
  "#addressInput",
  "[DISCRIMINATOR] delegated to #addressInput, so a re-rendered form still works"
);

/* ------------------------------------------- the threshold walk */

/**
 * Asserted as a SEQUENCE, not as four endpoints.
 *
 * "after the third character exactly one widget exists" is true of the
 * v0.0.18 build too - it built one before a key was ever pressed. An endpoint
 * that the unfixed build also reaches is not a discriminator however it is
 * labelled, which is the inverse of the trap in rev14 SS8. The transition
 * 0,0,1,1 is reachable only by a build that defers.
 */
const walk = [];
["4", "48", "488", "4884"].forEach(function (v) {
  g.fire(v);
  walk.push(g.built.length);
});
eq(
  walk,
  [0, 0, 1, 1],
  "[DISCRIMINATOR] nothing at one or two characters, one widget at three, none after"
);
eq(
  g.ctx.avalon_autocomplete_attached,
  true,
  "[DISCRIMINATOR] the flag records the attach"
);
eq(
  g.offs.length,
  1,
  "[DISCRIMINATOR] the listener unbinds itself on the crossing"
);
eq(
  g.built[0],
  "addressInput",
  "[GUARD] the widget is bound to the real field, as it was before"
);

/**
 * The idempotence guard, exercised directly.
 *
 * Once off() has really unbound the listener the walk above can no longer
 * reach avalon_attach_autocomplete() a second time, so the
 * `if (avalon_autocomplete_attached || !input) return;` line would go
 * untested. It matters: a handler can fire more than once before off() takes
 * effect, and avalon_init_gmaps() is not guaranteed to run exactly once.
 * Calling it directly is the right shape here - it is a property of the
 * function, not of the wiring, and the wiring is what the walk covers.
 */
let secondAttach = null;
if (typeof g.ctx.avalon_attach_autocomplete === "function") {
  g.ctx.avalon_attach_autocomplete(g.el);
  secondAttach = g.built.length;
}
eq(
  secondAttach,
  1,
  "[DISCRIMINATOR] a second attach call is a no-op - the helper is idempotent"
);

/* ------------------------------- whitespace is not a character */

g = newGate();
const pad = [];
["  a  ", "  abc  "].forEach(function (v) {
  g.fire(v);
  pad.push(g.built.length);
});
eq(
  pad,
  [0, 1],
  "[DISCRIMINATOR] a padded single character is one character, not five"
);

/* ------------------- a field seeded by the URL bootstrap attaches at once */

g = newGate("48843");
eq(
  [g.built.length, g.handlers.length],
  [1, 0],
  "[GUARD] a field already holding an address attaches at once, with no listener left behind"
);

/* --------------- a seeded field below the threshold still defers */

g = newGate("48");
eq(
  [g.built.length, g.handlers.length],
  [0, 1],
  "[DISCRIMINATOR] a seeded value below the threshold defers, like an empty field"
);

/* ------------------------------- Maps failed to load */

/**
 * Paired for the same reason as the walk. The v0.0.18 build constructs
 * nothing here either - because it throws a ReferenceError on the way. Zero
 * widgets is the right answer reached the wrong way, so the absence of the
 * throw is asserted with it.
 */
g = newGate(undefined, { noGoogle: true });
eq(
  [g.threw, g.built.length],
  [false, 0],
  "[DISCRIMINATOR] a page where Maps never loaded constructs nothing and does not throw"
);

/* ===================================================================== */
/* [GUARD] decision 66 - Issue 22 is closed, and must stay closed         */
/* ===================================================================== */

const gc = load(artefact);
const ctx = gc.ctx;
const state = gc.state;
const guard = ctx.avalon_guard;

eq(
  ctx.slplus.options.immediately_show_locations,
  "1",
  "[GUARD] loading the file does not touch SLP's onload latch"
);

/**
 * slp_core.js:1841-1845, reproduced by behaviour rather than by import.
 * store-locator-le/ is never edited and is not loaded by the harness, so the
 * branch has to be modelled here. It is four lines and it is quoted in
 * build-v019.py's docblock, so the two can be diffed by eye.
 */
function makeLoadMarkers(seen) {
  return function () {
    // Sampled on ENTRY. Reading the flag afterwards proves nothing: the
    // branch below sets it itself.
    seen.flagOnEntry.push(ctx.slplus.options.immediately_show_locations);

    const action = { action: "csl_ajax_search" };
    if (ctx.slplus.options.immediately_show_locations !== "0") {
      action.action = "csl_ajax_onload";
      ctx.slplus.options.immediately_show_locations = "0";
    }
    seen.actions.push(action.action);
  };
}

function harnessSearch(seen, fieldValue, placeLat, placeLng, placeCountry) {
  ctx.window.location.href = BASE;
  state.inputValue = fieldValue;
  state.data.place_lat = placeLat;
  state.data.place_lng = placeLng;
  state.data.place_country = placeCountry;

  const load_markers = makeLoadMarkers(seen);

  ctx.avalon_cslmap = {
    gmap: {},
    saneValue: (id, dflt) =>
      id === "addressInput" ? String(fieldValue).trim() : dflt,
    unhide_map: () => {},
    doGeocode: function () {
      this.process_geocode_response();
    },
    process_geocode_response: function () {
      load_markers();
    },
    load_markers: load_markers,
    address: null,
    search_options: null,
  };

  guard.state = guard.IDLE;
  guard.last_state = null;
  guard.pending_url = null;
  ctx.cslmap_searchLocations();
}

/* ------- the two searches are still two different kinds of search */

const seen = { actions: [], radius: [], flagOnEntry: [] };
harnessSearch(seen, "48843", null, null, null);
harnessSearch(seen, "48843", null, null, null);

eq(
  seen.actions,
  ["csl_ajax_onload", "csl_ajax_search"],
  "[GUARD] decision 66: first search reads initial_results_returned, second reads max_results_returned"
);
eq(
  seen.flagOnEntry,
  ["1", "0"],
  "[GUARD] and nothing in our code spent the latch before SLP did"
);

/* --------------------- the page-load bootstrap still bootstraps */

const clicks = [];
ctx.jQuery.fn.trigger = function (ev) {
  if (ev === "click") clicks.push(String(this.selector));
  return this;
};

function harnessBuildMap(href, flag) {
  ctx.window.location.href = href;
  ctx.slplus.options.immediately_show_locations = flag;
  ctx.slplus.options.initial_radius = "100";
  clicks.length = 0;

  const seenLocal = { actions: [], radius: [], flagOnEntry: [] };
  const load_markers = makeLoadMarkers(seenLocal);

  ctx.avalon_cslmap = {
    gmap: null,
    mapType: "roadmap",
    options: null,
    homePoint: null,
    search_options: {},
    show_home_marker: () => false,
    addMarkerAtCenter: () => {},
    __waitForTileLoad: () => {},
    load_markers: function (center, radius) {
      seenLocal.radius.push(radius);
      load_markers();
    },
  };

  ctx.cslmap_build_map({ lat: () => 43.38, lng: () => -84.64 }, { id: "map" });
  return seenLocal;
}

let b = harnessBuildMap(
  BASE + "?place_address=New+York%2C+11224&place_lat=40.58&place_lng=-73.96",
  "1"
);
eq(clicks.length, 1, "[GUARD] a URL with place_* still fires the bootstrap click");
eq(
  state.inputValue,
  "New York, 11224",
  "[GUARD] and still fills #addressInput from the URL"
);
eq(state.data.place_lat, "40.58", "[GUARD] and still writes place_lat to the field");
eq(b.actions.length, 0, "the click is recorded, not dispatched, so no request here");

b = harnessBuildMap(BASE, "0");
eq(clicks.length, 0, "[GUARD] a spent latch still suppresses the bootstrap entirely");
eq(b.actions.length, 0, "and issues nothing");

/* ------- the one path that is an onload on purpose and stays one */

ctx.navigator.geolocation.getCurrentPosition = function (_okCb, errCb) {
  errCb({ code: 1, message: "User denied Geolocation" });
};

b = harnessBuildMap(BASE, "1");
eq(clicks.length, 0, "no URL parameters, so no bootstrap click");
eq(b.actions.length, 1, "the denied-geolocation fallback still issues a request");
eq(
  b.actions[0],
  "csl_ajax_onload",
  "[GUARD] and it is still an onload - initial_results_returned still applies"
);
eq(b.radius[0], "100", "[GUARD] still sent with initial_radius, not the 40000 default");

/* ----------------------------------- nothing earlier moved */

ok(typeof ctx.cslmap_searchLocations === "function", "[GUARD] the entry point survives");
ok(typeof ctx.cslmap_build_map === "function", "[GUARD] build_map survives");
ok(typeof ctx.initialize_autocomplete === "function", "[GUARD] the autocomplete entry point survives");
ok(typeof guard.clean_url === "function", "[GUARD] clean_url() is still present");
ok(typeof ctx.avalon_geocode_safe === "function", "[GUARD] v0.0.14's helper survives");

// C1. A rejection still drops only the three search keys.
ctx.window.location.href = BASE + "?place_lat=48.86&place_lng=2.35&utm_source=fb";
state.replaceState.length = 0;
guard.state = guard.IDLE;
guard.last_state = null;
guard.start(true);
guard.finish(guard.REJECTED);
eq(
  state.replaceState[state.replaceState.length - 1][2],
  BASE + "?utm_source=fb",
  "[GUARD] C1: a rejection still keeps attribution and drops the search keys"
);

process.exit(report(path.basename(artefact) + " :: suite-v019"));
