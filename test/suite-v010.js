/**
 * SLP Dealer Guard - v0.0.10 suite. Layer 0.
 *
 * Drives cslmap_searchLocations() end to end rather than testing the checks in
 * isolation. That distinction is the whole lesson of handoff s0.9 / Issue 14:
 * an earlier session's suite proved a change handler worked and proved nothing
 * about whether anything called it, and a live bug shipped underneath it.
 *
 * So every case here goes in through the real entry point, with the same
 * inputs the four live paths produce:
 *
 *   Find Locations button  -> #addressInput has text, no coords
 *   autocomplete selection -> text + place_lat/lng + place_country
 *   URL bootstrap          -> text + place_lat/lng as STRINGS, no country
 *   Get My Position        -> text + place_lat/lng as numbers + country
 *
 * and asserts on what escaped: whether a geocode was issued, whether the
 * coords branch delegated, and what terminal state the Guard reached.
 *
 * NEGATIVE CONTROL, decision 20: run against v0.0.9 first and confirm it
 * FAILS.
 *
 *   node test/suite-v010.js <path-to-artefact>
 */

"use strict";

const path = require("path");
const { load, ok, eq, report } = require("./harness");

const artefact = process.argv[2] || path.join(__dirname, "..", "out10", "slp_avalon.js");
const { ctx, state } = load(artefact);
const guard = ctx.avalon_guard;

/* ------------------------------------------------------------ structure */

ok(!!ctx.AVALON_SEARCHABLE, "AVALON_SEARCHABLE is declared");
ok(
  !!ctx.AVALON_GUARD_MESSAGES && !!ctx.AVALON_GUARD_MESSAGES.invalid_input,
  "a distinct message exists for syntactic rejection"
);
ok(
  ctx.AVALON_GUARD_MESSAGES.invalid_input !== ctx.AVALON_GUARD_MESSAGES.territory,
  "syntactic rejection does not reuse the territory copy"
);

/* --------------------------------------------------- the floor in isolation */

/* Absent in any build before v0.0.10; a stand-in that matches nothing keeps
   the negative control reporting every assertion instead of dying here. */
const floor = ctx.AVALON_SEARCHABLE || { test: () => false };
[
  ["Detroit, MI", true],
  ["48127", true],
  ["M5H 2N2", true],
  ["Sault Ste. Marie", true],
  ["St. John's, NL", true],
  ["Trois-Rivieres", true],
  ["Trois-Rivi\u00e8res", true],
  ["1200 Woodward Ave #4", true],
  ["cuewx#1z", true],
  ["\u00c9\u00e9", true],
  ["!!!", false],
  ["###", false],
  ["@@@ ---", false],
  ["...", false],
].forEach(([input, want]) => {
  eq(floor.test(input), want, `floor accepts/rejects ${JSON.stringify(input)}`);
});

/* ------------------------------------------- driving the real entry point */

/**
 * Stand in for the SLP map instance and record everything Layer 0 is supposed
 * to prevent: a geocode round trip, and the delegate that makes SLP pin and
 * pan the map to a location we are rejecting.
 */
function harnessSearch(fieldValue, placeLat, placeLng, placeCountry) {
  const seen = { geocoded: 0, spoofed: 0, unhidden: 0, load_markers: 0 };

  state.inputValue = fieldValue;
  state.data.place_lat = placeLat;
  state.data.place_lng = placeLng;
  state.data.place_country = placeCountry;

  ctx.avalon_cslmap = {
    gmap: {},
    saneValue: (id, dflt) => (id === "addressInput" ? String(fieldValue).trim() : dflt),
    unhide_map: () => (seen.unhidden += 1),
    doGeocode: () => (seen.geocoded += 1),
    process_geocode_response: () => (seen.spoofed += 1),
    load_markers: () => (seen.load_markers += 1),
    address: null,
  };

  guard.state = guard.IDLE;
  guard.last_state = null;
  ctx.cslmap_searchLocations();
  return seen;
}

/* --- a real search still works. If Layer 0 breaks this, nothing else matters */

let r = harnessSearch("Detroit, MI", null, null, null);
eq(r.geocoded, 1, "a typed US address still reaches the geocoder");
eq(guard.last_state, null, "and does not reach a terminal state yet");
eq(guard.state, "RESOLVING", "the Guard is left in RESOLVING for the geocode");

r = harnessSearch("48127", null, null, null);
eq(r.geocoded, 1, "a bare ZIP still reaches the geocoder");

r = harnessSearch("Toronto, ON", 43.6532, -79.3832, "CA");
eq(r.spoofed, 1, "an in-territory autocomplete selection still reaches Layer 1");
eq(r.geocoded, 0, "and does not re-geocode");

r = harnessSearch("", null, null, null);
eq(r.load_markers, 1, "an empty field still searches from the map centre");
eq(guard.last_state, null, "empty is not a rejection");

/* ------------------------------------ (a) the syntactic floor, end to end */

r = harnessSearch("!!!", null, null, null);
eq(r.geocoded, 0, "punctuation-only input issues NO geocode");
eq(guard.last_state, "REJECTED", "and is rejected");

r = harnessSearch("###", null, null, null);
eq(r.geocoded, 0, "### issues no geocode");

/* Junk that CAN geocode is deliberately let through: the proxy returns
   ZERO_RESULTS and the Guard already reports it correctly. Rejecting it here
   would mean guessing at address shape, which is how real addresses get
   refused. */
r = harnessSearch("cuewx#1z", null, null, null);
eq(r.geocoded, 1, "cuewx#1z is NOT rejected by the floor - it goes to the geocoder");

/* ------------------------------- (b) decision 16, the reason Layer 0 exists */

/* The URL bootstrap hands back strings, not numbers. */
r = harnessSearch("Paris, France", "48.86", "2.35", null);
eq(r.spoofed, 0, "Issue 15: out-of-territory URL coords never reach the delegate");
eq(r.geocoded, 0, "and never reach the geocoder");
eq(guard.last_state, "REJECTED", "the Guard reports a rejection");

/* This is the specific chain the fix exists to break. If spoofed were 1, SLP
   would set homePoint to Paris at slp_core.js:1565, drop a marker at 1566 and
   pan there at 1323 - the map in the v0.0.9 screenshot. */
ok(r.spoofed === 0, "process_geocode_response is not called, so homePoint is never set");

r = harnessSearch("Detroit, MI", "42.3314", "-83.0458", null);
eq(r.spoofed, 1, "in-territory URL coords pass Layer 0 and reach Layer 1");

/* Get My Position hands back numbers. */
r = harnessSearch("Paris, 75001", 48.8566, 2.3522, "FR");
eq(r.spoofed, 0, "an out-of-territory geolocated position is rejected before delegating");

/* The boxes admit Tijuana and Nassau on purpose - Layer 1 catches those on the
   country component. Layer 0 must NOT start rejecting them, or the two layers
   would disagree about precision. */
r = harnessSearch("Tijuana, Mexico", 32.5149, -117.0382, "MX");
eq(r.spoofed, 1, "Tijuana passes Layer 0 by design and is left to Layer 1");

r = harnessSearch("Nassau, Bahamas", 25.0343, -77.3963, "BS");
eq(r.spoofed, 1, "Nassau passes Layer 0 by design and is left to Layer 1");

/* ---------------------------------------------- the VALIDATING state is real */

/* Declared since v0.0.5 and unreachable until now. A rejection has to pass
   through a busy state or finish() would no-op and nothing would be reported. */
ok(guard.VALIDATING === "VALIDATING", "the state constant still exists");
r = harnessSearch("!!!", null, null, null);
eq(guard.last_state, "REJECTED", "a Layer 0 rejection reaches a terminal state");
eq(guard.state, "IDLE", "and closes the cycle rather than leaving it open");

process.exit(report(path.basename(artefact) + " :: suite-v010"));
