/*!
 * avalon-hours.js
 * SLP Dealer Guard (slp_avalon) v0.0.27 Part 4d.
 *
 * Google-style opening hours on store pages and find-a-dealer result cards.
 *
 * The server prints the whole week and a data attribute that carries the
 * dealer's IANA time zone, that zone's UTC offsets for the next 400 days -
 * worked out on the server - and Google's opening periods. Pages are cached,
 * so only the browser knows what time it is. This script adds the offset in
 * force to the browser's clock and so knows the dealer's local time, then
 * adds what the cached markup cannot hold: the status line, coloured like
 * Google's, and the week reordered so today comes first, bold. Without this
 * script the week still shows.
 *
 * It does not ask the browser's own zone data. British Columbia and Alberta
 * stopped changing their clocks in 2026 (tzdata 2026b, 2026c), and a browser
 * whose data predates that would be an hour out. No Intl, either: older
 * Safari and Chromium ignored hourCycle and answered in a 12-hour clock.
 *
 * It never reads openNow. The server never sends it: openNow was true or
 * false at fetch time, up to 28 days earlier.
 *
 * No dependencies. Works on whatever [data-avalon-hours] blocks are in the
 * page when it starts (the store page), on those SLP inserts into
 * #map_sidebar after each search (the result cards), and on the one a map
 * pin's info bubble brings into #map (Part 4b). Recomputes on every
 * minute boundary while the page stays open, and at once when the page is
 * shown again, so "Closes soon" turns into "Closed" on time. Writes to the
 * page only when the day or the words change.
 *
 * Part 4d, the approved find-a-dealer design: the weekday in full ("Opens
 * 9 AM Tuesday"); the status's last word and the caret held on one line,
 * so the caret never starts a line alone - an element now, hidden from
 * screen readers; and on a card and in the bubble, where Hours: has moved
 * out of the <summary> into the label column, a click on it still opens
 * and shuts the week.
 */
(function (root, doc) {
  "use strict";

  var DAY = 1440;
  var WEEK = 10080;
  var DAYS = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
  var DOT = " \u00b7 ";

  /** 24-hour clock -> "9 AM", "9:30 PM", "12 PM" (noon), "12 AM" (midnight). */
  function clock(h, m) {
    h = h % 24;
    var hh = h % 12 === 0 ? 12 : h % 12;
    return hh + (m ? ":" + (m < 10 ? "0" + m : m) : "") + (h < 12 ? " AM" : " PM");
  }

  /**
   * The dealer's local weekday (0 = Sunday) and minute of the day, or null.
   * `o` is [[utc seconds, offset minutes], ...] from the server, oldest
   * first; `u` is the end of the window it covers. Outside the window: null.
   */
  function localNow(o, u, date) {
    if (!o || !o.length || typeof u !== "number") {
      return null;
    }
    var t = Math.floor((date ? date.getTime() : new Date().getTime()) / 1000);
    if (t < o[0][0] || t >= u) {
      return null;
    }
    var off = null;
    for (var i = 0; i < o.length; i++) {
      if (o[i][0] <= t) {
        off = o[i][1];
      } else {
        break;
      }
    }
    if (typeof off !== "number") {
      return null;
    }
    var local = new Date((t + off * 60) * 1000);
    return { d: local.getUTCDay(), m: local.getUTCHours() * 60 + local.getUTCMinutes() };
  }

  /**
   * Periods -> [open, close] in minutes of the week, Sunday 00:00 = 0.
   * A close before its open wraps past Saturday night. A period with no
   * close, or a close equal to its open, is the whole week only in Google's
   * own "open 24 hours" shape - opening Sunday 00:00 - and is dropped
   * otherwise. Spans that touch or overlap are joined, Saturday night into
   * Sunday morning included, so a week of day-by-day midnight-to-midnight
   * periods reads as one span, not a "Closes soon" at every midnight.
   */
  function spans(periods) {
    var raw = [];
    var i;
    for (i = 0; periods && i < periods.length; i++) {
      var p = periods[i];
      if (!p || !p[0] || p[0].length !== 3) {
        continue;
      }
      var o = p[0][0] * DAY + p[0][1] * 60 + p[0][2];
      var c = p[1] && p[1].length === 3 ? p[1][0] * DAY + p[1][1] * 60 + p[1][2] : o;
      if (c === o) {
        if (o === 0) {
          raw.push([0, WEEK]);
        }
        continue;
      }
      if (c < o) {
        c += WEEK;
      }
      raw.push([o, c]);
    }
    raw.sort(function (a, b) {
      return a[0] - b[0];
    });
    var out = [];
    for (i = 0; i < raw.length; i++) {
      var last = out.length ? out[out.length - 1] : null;
      if (last && raw[i][0] <= last[1]) {
        if (raw[i][1] > last[1]) {
          last[1] = raw[i][1];
        }
      } else {
        out.push([raw[i][0], raw[i][1]]);
      }
    }
    while (out.length > 1 && out[out.length - 1][1] >= out[0][0] + WEEK) {
      var tail = out[out.length - 1];
      tail[1] = Math.max(tail[1], out[0][1] + WEEK);
      out.shift();
    }
    return out;
  }

  /**
   * Where the dealer stands at `now`, or null when there are no periods.
   *   allday   open round the clock
   *   open     closes at `at`, more than an hour away
   *   closing  closes at `at`, within the hour
   *   opening  opens at `at`, within the hour
   *   closed   opens at `at`, more than an hour away
   * `at` is a minute of the week; `left` is minutes until then.
   */
  function status(periods, now) {
    var s = spans(periods);
    if (!now || !s.length) {
      return null;
    }
    var w = now.d * DAY + now.m;
    var i;
    var x;
    for (i = 0; i < s.length; i++) {
      if (s[i][1] - s[i][0] >= WEEK) {
        return { kind: "allday", at: 0, left: 0 };
      }
    }
    for (i = 0; i < s.length; i++) {
      x = w < s[i][0] ? w + WEEK : w;
      if (x >= s[i][0] && x < s[i][1]) {
        return { kind: s[i][1] - x <= 60 ? "closing" : "open", at: s[i][1] % WEEK, left: s[i][1] - x };
      }
    }
    var at = -1;
    var wait = 0;
    for (i = 0; i < s.length; i++) {
      x = s[i][0] - w;
      if (x <= 0) {
        x += WEEK;
      }
      if (at < 0 || x < wait) {
        at = s[i][0] % WEEK;
        wait = x;
      }
    }
    return { kind: wait <= 60 ? "opening" : "closed", at: at, left: wait };
  }

  /**
   * A status -> [word, tone, rest] the way Google words it:
   *   Open \u00b7 Closes 5 PM        Closes soon \u00b7 5 PM      Open 24 hours
   *   Closed \u00b7 Opens 9 AM       Opens soon \u00b7 9 AM
   *   Closed \u00b7 Opens 10 AM Sunday
   * The weekday - in full from Part 4d, as the design has it - is added to
   * an opening that is not later today, and to a closing a day or more
   * away. Tone is open, closed or soon.
   */
  function words(st, now) {
    if (!st || !now) {
      return null;
    }
    var day = Math.floor(st.at / DAY);
    var mins = st.at % DAY;
    var t = clock(Math.floor(mins / 60), mins % 60);
    switch (st.kind) {
      case "allday":
        return ["Open 24 hours", "open", ""];
      case "open":
        return ["Open", "open", DOT + "Closes " + t + (st.left >= DAY ? " " + DAYS[day] : "")];
      case "closing":
        return ["Closes soon", "soon", DOT + t];
      case "opening":
        return ["Opens soon", "soon", DOT + t];
      default:
        return ["Closed", "closed", DOT + "Opens " + t + (day === now.d && st.left < DAY ? "" : " " + DAYS[day])];
    }
  }

  /** Today's row first, then the rest of the week in order; today bold. */
  function order(el, now) {
    var bodies = el.querySelectorAll(".avalon-hours__week tbody");
    for (var i = 0; i < bodies.length; i++) {
      var tb = bodies[i];
      var rows = [];
      var j;
      for (j = 0; j < tb.rows.length; j++) {
        rows.push(tb.rows[j]);
      }
      rows.sort(function (a, b) {
        return ((+a.getAttribute("data-day") - now.d + 7) % 7) - ((+b.getAttribute("data-day") - now.d + 7) % 7);
      });
      for (j = 0; j < rows.length; j++) {
        var today = +rows[j].getAttribute("data-day") === now.d;
        rows[j].className = today ? "is-today" : "";
        if (today) {
          rows[j].setAttribute("aria-current", "date");
        } else {
          rows[j].removeAttribute("aria-current");
        }
        tb.appendChild(rows[j]);
      }
    }
  }

  /**
   * Part 4d. A status's rest split before its last word: [what comes
   * before it, the word], or null when the rest is empty. A time keeps
   * its AM or PM - "5 PM" is one word here - so the line never ends
   * "Closes 5" with "PM" and the caret under it.
   */
  function last(rest) {
    var m = /^([\s\S]*?)(\S+(?: [AP]M)?)\s*$/.exec(rest || "");
    return m ? [m[1], m[2]] : null;
  }

  /**
   * Part 4d. The caret: drawn by avalon-hours.css, unseen by screen
   * readers. An <i>, as the PHP writes it: SLP hides a card's empty spans.
   */
  function caret() {
    var c = doc.createElement("i");
    c.className = "avalon-hours__caret";
    c.setAttribute("aria-hidden", "true");
    return c;
  }

  /**
   * The status into every status slot of a block. No status, no change.
   * Part 4d: the last word and the caret after it in one
   * avalon-hours__nowrap span - the weekday, "5 PM", or a status with no
   * rest ("Open 24 hours") whole - so the caret never wraps alone. The
   * caret is never inside the coloured word: it keeps the line's colour.
   */
  function paint(el, w) {
    if (!w) {
      return;
    }
    var slots = el.querySelectorAll(".avalon-hours__status");
    for (var i = 0; i < slots.length; i++) {
      var slot = slots[i];
      while (slot.firstChild) {
        slot.removeChild(slot.firstChild);
      }
      var word = doc.createElement("span");
      word.className = "avalon-hours__word avalon-hours__word--" + w[1];
      word.textContent = w[0];
      var held = doc.createElement("span");
      held.className = "avalon-hours__nowrap";
      var tail = last(w[2]);
      if (tail) {
        slot.appendChild(word);
        if (tail[0]) {
          slot.appendChild(doc.createTextNode(tail[0]));
        }
        held.appendChild(doc.createTextNode(tail[1]));
      } else {
        held.appendChild(word);
      }
      held.appendChild(caret());
      slot.appendChild(held);
    }
  }

  /**
   * On a result card, a click anywhere in the hours block - the Hours line,
   * the opened week, an attribution link - does what it does there and
   * nothing else. SLP binds a click on every result card that recentres
   * the map and opens its bubble, and the theme marks the card active;
   * neither should fire because a visitor wanted the hours. The native
   * toggle is the click's default action on the summary, so it still
   * happens. The store page has no such handler and is left alone.
   */
  function keep(e) {
    e.stopPropagation();
  }

  /**
   * Part 4d. On a card and in the bubble, Hours: sits in the label
   * column, just before the block and outside the <summary> it used to
   * be part of. A click on it still opens and shuts the week, and - like
   * a click in the block - goes no further. Mouse and touch only: the
   * label is hidden from screen readers, and the summary, which says
   * "Hours:" to them, is the control a keyboard reaches.
   */
  function label(el) {
    var l = el.previousElementSibling;
    if (!l || (" " + l.className + " ").indexOf(" avalon-label--hours ") < 0) {
      return;
    }
    l.addEventListener("click", function (e) {
      e.stopPropagation();
      var d = el.querySelector(".avalon-hours__narrow");
      if (d) {
        d.open = !d.open;
      }
    }, false);
  }

  /**
   * One block: guard a card's clicks once, then reorder and paint - but
   * only when the day or the words differ from what the block already
   * shows, so a text selection or a screen reader's place survives the
   * minute timer.
   */
  function enhance(el, date) {
    if (el.getAttribute("data-avalon-ready") !== "1") {
      el.setAttribute("data-avalon-ready", "1");
      if ((" " + el.className + " ").indexOf(" avalon-hours--card ") >= 0) {
        el.addEventListener("click", keep, false);
        label(el);
      }
    }
    var data = null;
    try {
      data = JSON.parse(el.getAttribute("data-avalon-hours") || "null");
    } catch (e) {
      data = null;
    }
    var tz = data && typeof data.tz === "string" ? data.tz : "";
    var now = tz ? localNow(data.o, data.u, date) : null;
    if (now) {
      var w = words(status(data.p || [], now), now);
      var state = now.d + (w ? "|" + w.join("|") : "");
      if (el.getAttribute("data-avalon-state") !== state) {
        order(el, now);
        paint(el, w);
        el.setAttribute("data-avalon-state", state);
      }
    }
  }

  /** One block's failure is that block's alone. */
  function safe(el, date) {
    try {
      enhance(el, date);
    } catch (e) {
      /* The block keeps the week as printed. */
    }
  }

  var timer = 0;

  function refresh() {
    var list = doc.querySelectorAll("[data-avalon-ready]");
    for (var i = 0; i < list.length; i++) {
      safe(list[i]);
    }
  }

  /** The next minute boundary, and every one after it. */
  function arm() {
    timer = root.setTimeout(function () {
      refresh();
      arm();
    }, 60000 - (new Date().getTime() % 60000) + 20);
  }

  /** Shown again - back from another tab, or from the back/forward cache. */
  function wake() {
    refresh();
    if (timer) {
      root.clearTimeout(timer);
      arm();
    }
  }

  /** Enhance every block in `scope` not yet done; start the timer once. */
  function scan(scope, date) {
    var list = (scope || doc).querySelectorAll("[data-avalon-hours]");
    var found = false;
    for (var i = 0; i < list.length; i++) {
      found = true;
      if (list[i].getAttribute("data-avalon-ready") !== "1") {
        safe(list[i], date);
      }
    }
    if (found && !timer && root.setTimeout) {
      arm();
    }
  }

  /**
   * Part 4b. Whether a batch of DOM changes brought in an hours block.
   * Google redraws map tiles inside #map all the time; only a batch that
   * added an hours block - the info bubble opening - is worth a scan.
   */
  function added(records) {
    for (var i = 0; records && i < records.length; i++) {
      var nodes = records[i].addedNodes;
      for (var j = 0; nodes && j < nodes.length; j++) {
        var n = nodes[j];
        if (n && n.nodeType === 1 &&
            (n.hasAttribute("data-avalon-hours") || n.querySelector("[data-avalon-hours]"))) {
          return true;
        }
      }
    }
    return false;
  }

  /** `el` or its nearest ancestor carrying class `name`, or null. */
  function up(el, name) {
    for (var n = el; n && n.nodeType === 1; n = n.parentNode) {
      if ((" " + n.className + " ").indexOf(" " + name + " ") >= 0) {
        return n;
      }
    }
    return null;
  }

  /**
   * Part 4b. An hours block's week opened inside the map's info bubble.
   * Google pans a bubble into view when it opens, not when its content
   * grows, and the bubble grows upward from its pin: the opened week can
   * reach past the map's top edge. Pan the map down by that much and 8 px
   * more. Only on opening, only for an hours block in a bubble, and only
   * when SLP's map is there to pan (cslmap.gmap, Google's panBy).
   */
  function lift(e) {
    var d = e && e.target;
    if (!d || d.open !== true || !up(d, "avalon-hours")) {
      return;
    }
    var iw = up(d, "gm-style-iw-c");
    var box = iw ? up(iw, "gm-style") : null;
    var map = root.cslmap && root.cslmap.gmap;
    if (!box || !map || typeof map.panBy !== "function") {
      return;
    }
    var gap = iw.getBoundingClientRect().top - box.getBoundingClientRect().top - 8;
    if (gap < 0) {
      map.panBy(0, Math.floor(gap));
    }
  }

  function boot() {
    scan(doc);
    var side = doc.getElementById("map_sidebar");
    if (side && root.MutationObserver) {
      new root.MutationObserver(function () {
        scan(side);
      }).observe(side, { childList: true, subtree: true });
    } else if (typeof root.slp_Filter === "function") {
      try {
        root.slp_Filter("location_search_processed").subscribe(function () {
          scan(doc);
        });
      } catch (e) {
        /* SLP absent or changed: cards keep their week, without a status. */
      }
    }
    var map = doc.getElementById("map");
    if (map && root.MutationObserver) {
      new root.MutationObserver(function (records) {
        if (added(records)) {
          scan(map);
        }
      }).observe(map, { childList: true, subtree: true });
    }
    doc.addEventListener("toggle", function (e) {
      try {
        lift(e);
      } catch (x) {
        /* The week stays open; the map just does not move. */
      }
    }, true);
    if (root.addEventListener) {
      root.addEventListener("pageshow", wake, false);
    }
    doc.addEventListener("visibilitychange", function () {
      if (doc.visibilityState === "visible") {
        wake();
      }
    }, false);
  }

  root.AvalonHours = {
    clock: clock,
    localNow: localNow,
    spans: spans,
    status: status,
    words: words,
    last: last,
    enhance: enhance,
    scan: scan,
    added: added,
    lift: lift
  };

  if (doc.readyState === "loading") {
    doc.addEventListener("DOMContentLoaded", boot, false);
  } else {
    boot();
  }
})(window, document);
