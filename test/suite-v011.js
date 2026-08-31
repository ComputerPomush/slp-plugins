/**
 * SLP Dealer Guard - v0.0.11 suite. Transport hook action scoping.
 *
 * The window this closes is narrow and easy to dismiss, so the suite
 * reproduces it exactly rather than testing the discriminator in isolation:
 * a search is put in flight, an email_form POST is failed underneath it, and
 * the suite asserts the search was NOT killed.
 *
 * Against v0.0.10 that assertion fails, which is the whole point.
 *
 * The two live callers of slp.send_ajax on the dealer page:
 *   slp_core.js:1849                 {action:"csl_ajax_search", ...}
 *   slp-experience, send_email       {action:"email_form", formdata:...}
 *
 * NEGATIVE CONTROL, decision 20: run against v0.0.10 first and confirm FAIL.
 *
 *   node test/suite-v011.js <path-to-artefact>
 */

"use strict";

const path = require("path");
const { load, ok, eq, report } = require("./harness");

const artefact = process.argv[2] || path.join(__dirname, "..", "out11", "slp_avalon.js");
const { ctx } = load(artefact);
const guard = ctx.avalon_guard;

/**
 * Install the hook over a jQuery.post stand-in whose failure leg we control,
 * so a request can be put in flight and failed on demand.
 */
const inflight = [];
ctx.jQuery.post = function (url, action, done) {
  const d = {
    action: action,
    done: () => d,
    fail(fn) {
      d._fail = fn;
      return d;
    },
  };
  inflight.push(d);
  return d;
};

ctx.slp = { send_ajax: function () {} };
guard.original_send_ajax = null;
guard.install_transport_hook();
ok(guard.original_send_ajax !== null, "transport hook installed");

function send(action) {
  inflight.length = 0;
  ctx.slp.send_ajax(action, function () {});
  return inflight[inflight.length - 1];
}

function openSearch() {
  guard.state = guard.IDLE;
  guard.last_state = null;
  guard.start(true);
  guard.enter(guard.SEARCHING);
}

/* ------------------------------------- the defect, reproduced end to end */

/* A search is running. An email_form POST fails underneath it. Before
   v0.0.11 the state test passed - state IS SEARCHING - and the search was
   finished with ERROR and the transport message. */
openSearch();
const email = send({ action: "email_form", formdata: "sl_id=42" });
email._fail();

eq(guard.last_state, null, "an email_form failure does NOT terminate a live search");
eq(guard.state, "SEARCHING", "the search is left in flight, untouched");

/* And the search's own failure still reports, which is the thing that must
   not be broken while fixing the above. */
const search = send({ action: "csl_ajax_search", lat: 42.33, lng: -83.04 });
search._fail();
eq(guard.last_state, "ERROR", "a genuine search failure still reports ERROR");
eq(guard.state, "IDLE", "and closes the cycle");

/* -------------------------------------------- both search action names */

openSearch();
send({ action: "csl_ajax_onload", lat: 42.33, lng: -83.04 })._fail();
eq(guard.last_state, "ERROR", "csl_ajax_onload is a search too - slp_core.js:1842");

/* ------------------------------------------- other actions are ignored */

["email_form", "some_future_addon_action", "heartbeat"].forEach((name) => {
  openSearch();
  send({ action: name })._fail();
  eq(guard.last_state, null, `a failing ${name} POST leaves the search alone`);
});

/* ------------------------------- unrecognised shapes keep the old behaviour */

/* Deliberate: a caller that passes something other than an object with a
   string .action should not silently lose its error reporting. Treated as a
   search, exactly as in v0.0.10. */
openSearch();
send("action=csl_ajax_search&lat=42")._fail();
eq(guard.last_state, "ERROR", "a string action falls through to the state test");

openSearch();
send(undefined)._fail();
eq(guard.last_state, "ERROR", "an undefined action falls through to the state test");

openSearch();
send({ lat: 42.33 })._fail();
eq(guard.last_state, "ERROR", "an object with no .action falls through too");

/* ------------------------------------------- the cycle guard is retained */

/* A failure arriving after the ceiling has fired must not reopen anything. */
guard.state = guard.IDLE;
guard.last_state = null;
send({ action: "csl_ajax_search" })._fail();
eq(guard.last_state, null, "a search failure with no cycle open is a no-op");

/* ------------------------------------------------ the success leg is intact */

let delivered = null;
inflight.length = 0;
ctx.slp.send_ajax({ action: "csl_ajax_search" }, function (r) {
  delivered = r;
});
ok(inflight.length === 1, "the request is still issued");

/* --------------------------------------------------- idempotency retained */

const wrapper = ctx.slp.send_ajax;
guard.install_transport_hook();
ok(ctx.slp.send_ajax === wrapper, "second install is a no-op");

process.exit(report(path.basename(artefact) + " :: suite-v011"));
