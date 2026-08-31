/**
 * SLP Dealer Guard - test harness.
 *
 * Runs the BUILT slp_avalon.js in a vm context with mocked globals, so the
 * suites exercise the artefact that ships rather than a reimplementation of
 * it. That distinction has already caught one bug: an earlier session's unit
 * tests proved a change handler worked while nothing on the live page called
 * it (handoff s0.9, Issue 14).
 *
 * The file's structure makes this workable. Lines 1-43 are an IIFE that closes
 * at 43; everything after it - add_url_param, avalon_guard, avalon_init_gmaps,
 * sl_show_loading and the rest - is declared at script top level, so in a vm
 * context those land as properties of the context object.
 *
 * TRAP, carried forward from the handoff s9: sl_show_loading is a function
 * DECLARATION in the file, so it shadows any same-named context property set
 * before load. Spy on it after load, never before.
 *
 * These files live at the repo root, outside slp_avalon/, so they are never
 * uploaded to a server and are not covered by the `slp_avalon/** -text` rule
 * in .gitattributes.
 */

"use strict";

const fs = require("fs");
const vm = require("vm");

/* --------------------------------------------------------------- jQuery */

/**
 * Minimal chainable jQuery stand-in. Every method the artefact calls at load
 * time or during the paths under test returns `this`, so chains do not throw.
 * Selector traffic is recorded so a suite can assert what was touched.
 */
function makeJQuery(state) {
  const noop = function () {
    return this;
  };

  function Fake(selector) {
    if (!(this instanceof Fake)) return new Fake(selector);
    this.selector = selector;
    this.length = state.present.has(String(selector)) ? 1 : 0;
    state.selectors.push(String(selector));
  }

  Fake.prototype.append = function (arg) {
    // Records injected markup so a suite can assert on the stylesheet the
    // Guard builds, rather than grepping the artefact as text.
    const html =
      arg && typeof arg.selector === "string"
        ? arg.selector
        : typeof arg === "string"
        ? arg
        : null;
    if (html === null) return this;
    state.appends.push(html);
    // Model the DOM well enough that the injectors' own idempotency guards
    // work: a <style class='x'> now makes jQuery("style.x").length truthy.
    const m = /<style class='([^']+)'>/.exec(html);
    if (m) state.present.add("style." + m[1]);
    return this;
  };

  const chain = [
    "find", "remove", "appendTo", "scroll", "on", "off",
    "addClass", "removeClass", "toggleClass", "animate", "attr", "prop",
    "insertBefore", "insertAfter", "css", "trigger", "each", "hide", "show",
    "focus", "select", "html", "empty", "closest", "parent", "children",
    "scrollTop", "height", "width", "removeAttr", "not", "first", "last",
  ];
  chain.forEach((m) => {
    Fake.prototype[m] = noop;
  });

  Fake.prototype.text = function (v) {
    if (v === undefined) return state.sidebarText;
    return this;
  };
  Fake.prototype.val = function (v) {
    if (v === undefined) return state.inputValue;
    state.inputValue = v;
    return this;
  };
  Fake.prototype.data = function (k, v) {
    if (v === undefined) return state.data[k];
    state.data[k] = v;
    return this;
  };
  Fake.prototype.ready = function (fn) {
    state.readyCallbacks.push(fn);
    return this;
  };

  const jq = function (selector) {
    return new Fake(selector);
  };
  jq.fn = Fake.prototype;
  jq.extend = Object.assign;
  jq.post = function () {
    const d = { fail: () => d, done: () => d };
    state.posts.push([].slice.call(arguments));
    return d;
  };
  jq.getJSON = function (url) {
    const d = { done: () => d, fail: () => d };
    state.getJSON.push(url);
    return d;
  };
  return jq;
}

/* -------------------------------------------------------------- context */

function makeContext(state) {
  const jq = makeJQuery(state);

  const elements = {};
  function el(id) {
    if (!elements[id]) {
      elements[id] = {
        id,
        className: "",
        // Geometry measured on Aura DEV at a 1920 viewport, so
        // center_spinner() is exercised against real numbers rather than
        // zeroes. Overridable per-test via state.rects.
        getBoundingClientRect() {
          return (
            state.rects[id] || { left: 0, top: 0, width: 0, height: 0 }
          );
        },
        offsetWidth: id === "sl_loading_icon" ? 46.5 : 0,
        offsetHeight: id === "sl_loading_icon" ? 48 : 0,
        querySelector() {
          return state.spinnerIcon;
        },
        textContent: id === "map_sidebar" ? state.sidebarText : "",
        children: [],
        style: {},
        focus() {
          state.focused = id;
        },
        select() {},
        appendChild(c) {
          this.children.push(c);
          state.sidebarWrites.push(c.textContent);
        },
      };
    }
    return elements[id];
  }

  const ctx = {
    jQuery: jq,
    $: jq,
    console: { log: (...a) => state.logs.push(a.join(" ")) },
    window: {
      innerWidth: 1440,
      setTimeout: (fn, ms) => {
        const id = state.timers.length;
        state.timers.push({ fn, ms, cleared: false });
        return id;
      },
      clearTimeout: (id) => {
        if (state.timers[id]) state.timers[id].cleared = true;
      },
      history: { replaceState: (...a) => state.replaceState.push(a) },
      location: { href: "https://example.test/find-a-dealer/" },
      console: { log: (...a) => state.logs.push(a.join(" ")) },
    },
    document: {
      readyState: "complete",
      getElementById: (id) => (state.present.has("#" + id) ? el(id) : null),
      createElement: (tag) => ({
        tagName: tag,
        className: "",
        textContent: "",
        appendChild() {},
      }),
      addEventListener: () => {},
    },
    navigator: { geolocation: { getCurrentPosition: () => {} } },
    URL,
    google: {
      maps: {
        GeocoderStatus: { OK: "OK", ERROR: "ERROR", ZERO_RESULTS: "ZERO_RESULTS" },
        event: { trigger: () => {}, addListener: () => {} },
        places: { Autocomplete: function () { this.setFields = () => {}; this.addListener = () => {}; } },
        Map: function () {},
        LatLng: function (a, b) { this.lat = a; this.lng = b; },
        Circle: function () {},
        Marker: function () {},
      },
    },
    slplus: {
      options: {
        zoom_level: "12",
        map_center_lat: "43.38",
        map_center_lng: "-84.64",
        initial_radius: "100",
        immediately_show_locations: "1",
        append_to_search: "",
      },
      shortcode_attributes: {},
      rest_url: "https://example.test/wp-json/store-locator-plus/v2/",
      ajaxurl: "https://example.test/wp-admin/admin-ajax.php",
      apikey: "testkey",
    },
    slp_Filter: () => ({ subscribe: () => {}, publish: () => {} }),
  };
  ctx.window.window = ctx.window;
  ctx.globalThis = ctx;
  return ctx;
}

/**
 * Load a built slp_avalon.js into a fresh context.
 *
 * @param  {string} file  path to the artefact under test
 * @return {{ctx:object, state:object}}
 */
function load(file) {
  const state = {
    selectors: [],
    present: new Set([
      "#sl_div",
      "#addressInput",
      "#map_sidebar",
      "#addy_in_address",
      "#sl_loading_indicator",
      "#map_box",
    ]),
    data: {},
    inputValue: "",
    sidebarText: "Enter an address or zip code and click the find locations button.",
    sidebarWrites: [],
    readyCallbacks: [],
    timers: [],
    posts: [],
    getJSON: [],
    replaceState: [],
    logs: [],
    appends: [],
    rects: {
      sl_loading_indicator: { left: 338, top: 115.8, width: 1580, height: 939.3 },
      map_box: { left: 907, top: 115.8, width: 1011, height: 939.3 },
    },
    spinnerIcon: {
      offsetWidth: 46.5,
      offsetHeight: 48,
      style: {},
      focus() {},
    },
    focused: null,
  };
  const ctx = vm.createContext(makeContext(state));
  vm.runInContext(fs.readFileSync(file, "utf8"), ctx, { filename: file });
  return { ctx, state };
}

/* ---------------------------------------------------------- assertions */

let passed = 0;
const failures = [];

function ok(cond, label) {
  if (cond) {
    passed += 1;
  } else {
    failures.push(label);
  }
}

function eq(actual, expected, label) {
  ok(
    JSON.stringify(actual) === JSON.stringify(expected),
    `${label} (expected ${JSON.stringify(expected)}, got ${JSON.stringify(actual)})`
  );
}

function report(name) {
  const total = passed + failures.length;
  if (failures.length === 0) {
    console.log(`  ${name}: ${passed}/${total} assertions PASS`);
    return 0;
  }
  console.log(`  ${name}: ${passed}/${total} PASS, ${failures.length} FAIL`);
  failures.forEach((f) => console.log(`      FAIL  ${f}`));
  return 1;
}

module.exports = { load, ok, eq, report };
