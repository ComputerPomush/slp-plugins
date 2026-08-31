/**
 * SLP Dealer Guard - v0.0.9 suite.
 *
 * Covers center_spinner(), the corrective for the spinner-centring fix that
 * v0.0.8 shipped and that never applied.
 *
 * Why the v0.0.8 approach could not work, restated so this is not re-tried:
 * Font Awesome 5.15.3 declares `.fa-spin{animation:fa-spin 2s linear infinite}`
 * with keyframes that set `transform: rotate(...)`. A running animation beats
 * an author normal declaration in the cascade, so a static
 * `transform: translate(-50%,-50%)` on the same element is discarded on every
 * frame. Offsets must go in left/top.
 *
 * Geometry in the harness is what was measured on Aura DEV at a 1920 viewport:
 *
 *   scrim  #sl_div    left 338  width 1580   ->  centre x 1128
 *   map    #map_box   left 907  width 1011   ->  centre x 1412.5
 *   icon              46.5 x 48
 *
 * The v0.0.8 build put the icon's top-left at 1128 - the scrim's centre, with
 * no transform in effect - which is about 260px left of the map's centre.
 *
 * NEGATIVE CONTROL, decision 20: run against the v0.0.8 artefact first and
 * confirm it FAILS.
 *
 *   node test/suite-v009.js <path-to-artefact>
 */

"use strict";

const path = require("path");
const { load, ok, eq, report } = require("./harness");

const artefact = process.argv[2] || path.join(__dirname, "..", "out9", "slp_avalon.js");
const { ctx, state } = load(artefact);
const guard = ctx.avalon_guard;

const SCRIM = state.rects.sl_loading_indicator;
const MAP = state.rects.map_box;
const ICON_W = 46.5;
const ICON_H = 48;

function centreOn(target) {
  return {
    left: target.left - SCRIM.left + target.width / 2 - ICON_W / 2,
    top: target.top - SCRIM.top + target.height / 2 - ICON_H / 2,
  };
}

function px(v) {
  return Math.round(parseFloat(v));
}

function resetIcon() {
  state.spinnerIcon.style = {};
}

/* ---------------------------------------------------- structure */

ok(typeof guard.center_spinner === "function", "center_spinner() exists");

/* --------------------------------------- centres on the map, not the scrim */

resetIcon();
if (typeof guard.center_spinner === "function") guard.center_spinner();

const want = centreOn(MAP);
eq(px(state.spinnerIcon.style.left), Math.round(want.left),
  "icon left puts its centre on the map's centre");
eq(px(state.spinnerIcon.style.top), Math.round(want.top),
  "icon top puts its centre on the map's centre");

/* The whole point of the change: this must NOT be the scrim's centre, which
   is where v0.0.8 left it. */
ok(
  px(state.spinnerIcon.style.left) !== Math.round(SCRIM.width / 2),
  "not left at the scrim's centre, which is ~260px off the map"
);

/* Offsets are in left/top. A transform here would be eaten by .fa-spin. */
ok(
  !state.spinnerIcon.style.transform,
  "no transform is set - .fa-spin animates that property"
);

/* --------------------------------------------- fallback when there is no map */

resetIcon();
state.rects.map_box = { left: 0, top: 0, width: 0, height: 0 };
if (typeof guard.center_spinner === "function") guard.center_spinner();

const wantScrim = centreOn(SCRIM);
eq(px(state.spinnerIcon.style.left), Math.round(wantScrim.left),
  "zero-sized map falls back to centring on the scrim");
eq(px(state.spinnerIcon.style.top), Math.round(wantScrim.top),
  "zero-sized map falls back vertically too");

state.rects.map_box = MAP;

/* ------------------------------------------ show_spinner drives it, once */

resetIcon();
guard.state = guard.IDLE;
guard.show_spinner(true);
ok(state.spinnerIcon.style.left !== undefined, "show_spinner(true) positions the icon");

resetIcon();
guard.show_spinner(false);
eq(state.spinnerIcon.style.left, undefined,
  "show_spinner(false) does not measure - the indicator is display:none by then");

/* ------------------------------------------------- the CSS rule is gone */

const css = state.appends.join("");
ok(
  css.indexOf("#sl_loading_indicator i{") === -1,
  "the v0.0.8 CSS rule for the icon is retired, not left to mislead"
);
ok(css.indexOf("transform:translate") === -1, "no translate anywhere in the stylesheet");

/* The other two rules from v0.0.8 must survive this corrective. */
ok(css.indexOf(".avalon_search_notification{color:#E7167C") !== -1,
  "v0.0.8 notification colour still present");
ok(css.indexOf(".avalon_sidebar_prompt{color:#FFFFFF") !== -1,
  "v0.0.8 sidebar prompt colour still present");

process.exit(report(path.basename(artefact) + " :: suite-v009"));
