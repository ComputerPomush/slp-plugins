/**
 * SLP Dealer Guard - v0.0.8 suite.
 *
 * Covers the rejection-presentation work: the slp.option.get_from_server
 * wrapper (handoff s7.4), the consolidated injected stylesheet, and the three
 * call sites that ensure it.
 *
 * NEGATIVE CONTROL, decision 20: this suite must FAIL against out/../
 * slp_avalon.js at v0.0.7 before it is allowed to pass against v0.0.8. A
 * regression test that passes against the broken build is worthless. Run:
 *
 *   node test/suite-v008.js <path-to-artefact>
 *
 * with the v0.0.7 file first, then with the v0.0.8 file.
 */

"use strict";

const path = require("path");
const { load, ok, eq, report } = require("./harness");

const artefact = process.argv[2] || path.join(__dirname, "..", "out", "slp_avalon.js");
const { ctx } = load(artefact);
const guard = ctx.avalon_guard;

ok(!!guard, "avalon_guard reachable at script top level");

/* Calling through this keeps the suite running against a build that has no
   hook at all, so the negative control reports every failed assertion rather
   than dying on the first missing method. */
function install() {
  if (typeof guard.install_options_hook === "function") {
    guard.install_options_hook();
  }
}

/* ------------------------------------------------- structure and guards */

ok(typeof guard.install_options_hook === "function", "install_options_hook exists");
ok("original_get_from_server" in guard, "original_get_from_server slot declared");
eq(guard.original_get_from_server, null, "slot starts null");
ok(typeof guard.ensure_guard_css === "function", "ensure_guard_css exists");
ok(
  typeof guard.ensure_notification_css === "undefined",
  "ensure_notification_css retired, not left as a second injector"
);

/* -------------------------------------------- absent slp is a clean no-op */

ctx.slp = undefined;
install();
eq(guard.original_get_from_server, null, "no-op when slp is undefined");

ctx.slp = {};
install();
eq(guard.original_get_from_server, null, "no-op when slp.option is undefined");

ctx.slp = { option: {} };
install();
eq(guard.original_get_from_server, null, "no-op when get_from_server is missing");

/* ------------------------------------------------------------ wrapping */

const calls = [];
function originalGetFromServer(option_name, callback) {
  calls.push(option_name);
  // SLP always passes one; tolerate its absence so the assertion below tests
  // the wrapper's fall-through rather than this mock's strictness.
  if (typeof callback === "function") {
    callback({ value: "No Dealers found in this area, please try again!" });
  }
}

ctx.slp = { option: { get_from_server: originalGetFromServer } };
install();

ok(guard.original_get_from_server === originalGetFromServer, "original captured");
ok(ctx.slp.option.get_from_server !== originalGetFromServer, "property replaced");

const wrapper = ctx.slp.option.get_from_server;
install();
ok(ctx.slp.option.get_from_server === wrapper, "idempotent: second install is a no-op");

/* --------------------------------------------------- delegation, no flag */

function GFS(name, cb) {
  return ctx.slp.option.get_from_server(name, cb);
}

function collect() {
  const seen = [];
  return { seen, cb: (r) => seen.push(r) };
}

guard.last_response = null;
let c = collect();
GFS("message_no_results", c.cb);
eq(calls, ["message_no_results"], "delegates when last_response is null");
eq(c.seen.length, 1, "callback still fires on the delegated path");

calls.length = 0;
guard.last_response = { count: 0 };
c = collect();
GFS("message_no_results", c.cb);
eq(calls, ["message_no_results"], "delegates when the rejection flag is absent");

calls.length = 0;
guard.last_response = { count: 3, avalon_territory_rejected: false };
GFS("message_no_results", collect().cb);
eq(calls, ["message_no_results"], "delegates when the flag is falsy");

/* ------------------------------------------------- the short-circuit */

calls.length = 0;
guard.last_response = { count: 0, response: [], avalon_territory_rejected: true };
c = collect();
GFS("message_no_results", c.cb);
eq(calls, [], "does NOT reach the original on a territory rejection");
eq(c.seen, [{ value: "" }], "short-circuits with an empty value");
ok(
  c.seen[0].value === "" || c.seen[0].value === 0 || !c.seen[0].value,
  "value is falsy, so slp_core.js:1334 takes the else branch and writes nothing"
);

/* ---- synchronous, mirroring slp_core.js:842-845. If this ever defers, the
       write lands after set_no_results() and Issue 4's race comes back. ---- */
let fired = false;
guard.last_response = { avalon_territory_rejected: true };
GFS("message_no_results", () => {
  fired = true;
});
ok(fired, "short-circuit is synchronous, not deferred to a microtask");

/* -------------------------------------------------------- scope: s0.5 */

calls.length = 0;
guard.last_response = { avalon_territory_rejected: true };
GFS("message_bad_address", collect().cb);
eq(
  calls,
  ["message_bad_address"],
  "message_bad_address delegates even during a rejection (slp_core.js:1600)"
);

calls.length = 0;
GFS("map_center_lat", collect().cb);
eq(calls, ["map_center_lat"], "unrelated options delegate untouched");

/* ---- a missing callback must not be swallowed by the short-circuit ---- */
calls.length = 0;
guard.last_response = { avalon_territory_rejected: true };
GFS("message_no_results", undefined);
eq(
  calls,
  ["message_no_results"],
  "a missing callback falls through to the original rather than being swallowed"
);

/* -------------------------------------------- start() clears the flag */

guard.state = guard.IDLE;
guard.last_response = { avalon_territory_rejected: true };
if (typeof guard.start === "function") guard.start(true);
eq(guard.last_response, null, "start() clears last_response so the flag cannot go stale");

process.exit(report(path.basename(artefact) + " :: suite-v008"));
