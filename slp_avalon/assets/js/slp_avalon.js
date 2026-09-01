(function ($) {
    // custom script goes here
    //Add back to top button
    let $btt = $('<a id="btt_button"></a>');
    $("body").find("a#btt_button").remove();
    $("body").append($btt);
    //Max screen width for btt button to show up, in pixels
    let max_width_for_btt = 768;
    $(window).scroll(function () {
      if (window.innerWidth <= max_width_for_btt) {
        if ($(window).scrollTop() > 300) {
          $btt.addClass("show");
        } else {
          $btt.removeClass("show");
        }
      } else {
        $btt.removeClass("show");
      }
    });
    $btt.on("click", function (e) {
      e.preventDefault();
      $("html, body").animate({ scrollTop: 0 }, "300");
    });
    //Enable on mouse over for markers
    jQuery("#map").on("markers_dropped", function (e) {
      enable_on_mouse_hover_for_markers();
      decrease_zoom_level_to_fit_infowindows();
    });
    //Remove bad inline CSS
    jQuery("#csl-slplus_user_header_css-inline-css").remove();
    //Get user Current Address
    jQuery(document).on("click", "#get_my_position", function (e) {
      e.preventDefault();
      get_user_current_address();
    });
    $(document).ready(function () {
      $(".store_locator_single_contact p br + br").remove();
      // Comment below on 3/17/2026, was hiding input text on mobile 
      // jQuery("#addressInput").css("padding-right",jQuery("#addressSubmit").width() + 35 + "px");
       //Add search placeholder
      $("#addressInput").attr('placeholder','Enter City, State, or Zip Code');
    });
  })(jQuery);
  function add_url_param(params, url) {
    if (url) {
      url = new URL(url);
    } else {
      url = new URL(window.location.href);
    }
    for (let i in params) {
      if (params[i]) {
        url.searchParams.set(i, params[i]);
      } else {
        url.searchParams.delete(i);
      }
    }
    return url;
  }
  /* ==================================================================
   * Avalon Search Guard - Layer 2 (state machine)
   * Phase 1, Step 1.
   *
   * One owner for the spinner, one 12s ceiling for the whole search, and a
   * terminal state on every path. Before this, the spinner was switched on
   * and off from four unrelated places and two failure paths reached none
   * of them:
   *
   *   Path A  process_geocode_response takes its else branch (slp_core.js
   *           1599-1616): writes message_bad_address, clears markers, never
   *           reaches load_markers(), so process_ajax_response never runs and
   *           location_search_processed never publishes.
   *   Path B  slp.send_ajax (wpslp.js:8) is a bare $.post with no .fail().
   *
   * Both used to be masked by the DOMSubtreeModified handler below, which
   * stopped firing when Mutation Events were removed from Chrome 135, Edge
   * 137, Firefox 140 and Safari 26. That browser change - not a code change -
   * is what surfaced Issue 1.
   *
   * States: IDLE -> RESOLVING -> SEARCHING -> { RESULTS | EMPTY | ERROR |
   * TIMEOUT | REJECTED }. REJECTED has two call sites: Layer 1 inside
   * install_geocode_hook(), and Layer 3 in on_search_processed(). VALIDATING
   * is still declared but unreachable - it is Layer 0's state (Step 4).
   * ================================================================== */
  var AVALON_GUARD_TIMEOUT_MS = 12000;

  var AVALON_GUARD_MESSAGES = {
    geocode_failed:
      "We couldn't find that location. Please check the spelling and try again.",
    timeout: "The search is taking longer than expected. Please try again.",
    transport: "We couldn't complete that search. Please try again.",
    territory:
      "We only have dealers in the United States and Canada. Please search " +
      "a U.S. or Canadian city, state, or ZIP.",
    //Layer 0 only. Deliberately not the territory copy: nothing about
    //"!!!" is out of territory, and telling the visitor it is would send
    //them looking for a problem that is not there.
    invalid_input: "Please enter a city, state, ZIP or postal code.",
  };

  /* ==================================================================
   * Territory bounding boxes - shared by Layers 0, 1 and 3.
   * Phase 1, Step 2.
   *
   * MIRROR of SLP_Avalon::territory_boxes() in
   * slp_avalon/inc/class.slp_avalon.php. The two must stay identical;
   * change one and change the other in the same commit.
   *
   * Territory is US + PR + VI + GU + MP + AS + CA.
   *
   * Layer 1 uses address_components.country where it has one, which is
   * precise. These boxes are the coarse fallback for the paths that have no
   * country at all: URL-supplied place_lat / place_lng read in
   * cslmap_build_map(), and any coords-spoof response built in
   * cslmap_searchLocations() for which no country was captured. Since
   * v0.0.6 that payload DOES carry address_components whenever the
   * autocomplete selection resolved a country.
   * ================================================================== */
  var AVALON_TERRITORY_BOXES = [
    //  name                   lat_min  lat_max   lng_min   lng_max
    ["CONUS + Canada",           24.4,    83.2,   -141.0,    -52.0],
    ["Alaska",                   51.0,    71.6,   -173.0,   -129.0],
    //Adak, Atka and Great Sitkin sit between -180 and -173 and fall
    //outside the Alaska box. Widening that box instead would admit
    //Wrangel Island (RU, 71.2N / -179.5); this one cannot.
    ["Western Aleutians",        51.0,    54.0,   -180.0,   -173.0],
    ["Aleutian wrap",            51.0,    54.0,    172.0,    180.0],
    ["Hawaii",                   18.5,    22.5,   -160.6,   -154.6],
    ["Puerto Rico + USVI",       17.6,    18.6,    -67.5,    -64.5],
    ["Guam + CNMI",              13.2,    20.6,    144.5,    146.1],
    //Swains Island sits 380 km north of Tutuila at -11.06, which is why
    //this box reaches so far north. The -171.2 western edge clears Cape
    //Tapaga, the eastern tip of Upolu (independent Samoa, WS), by ~18 km.
    ["American Samoa",          -14.6,   -11.0,   -171.2,   -168.0],
  ];

  /**
   * Is a coordinate pair inside the served territory?
   *
   * Bounds are inclusive: the Yukon/Alaska border is exactly -141.0 and
   * the antimeridian is exactly 180.0, so both must pass. Arguments may
   * arrive as strings - URLSearchParams.get() always returns a string.
   *
   * NOTE: no caller as of v0.0.6. Layer 1 is country-based, deliberately -
   * the boxes admit Tijuana, Nassau and Road Town, so a box check inside
   * Layer 1 would catch nothing Layer 3 does not already catch while adding
   * a second rejection rule with different precision to one layer. This is
   * Layer 0's helper (Step 4), where the coordinates are read straight off
   * #addressInput rather than inferred from a payload. Do not delete.
   *
   * @param  {number|string}  lat
   * @param  {number|string}  lng
   * @return {boolean}
   */
  function avalon_in_territory(lat, lng) {
    var la = parseFloat(lat);
    var ln = parseFloat(lng);
    if (!isFinite(la) || !isFinite(ln)) return false;
    if (la < -90 || la > 90 || ln < -180 || ln > 180) return false;
    for (var i = 0; i < AVALON_TERRITORY_BOXES.length; i++) {
      var b = AVALON_TERRITORY_BOXES[i];
      if (la >= b[1] && la <= b[2] && ln >= b[3] && ln <= b[4]) return true;
    }
    return false;
  }

  /* ==================================================================
   * Layer 1 allow-list. Country codes, not boxes: a country component is
   * exact where a bounding box is coarse.
   *
   * ISO 3166-1 alpha-2, as Google returns short_name:
   *   US United States    PR Puerto Rico   VI U.S. Virgin Islands
   *   GU Guam             MP N. Marianas   AS American Samoa
   *   CA Canada
   *
   * Same seven-entry set as territory_boxes(); change one, change both.
   * ================================================================== */
  var AVALON_ALLOWED_COUNTRIES = ["US", "PR", "VI", "GU", "MP", "AS", "CA"];

  /**
   * Layer 0's syntactic floor: does this string contain anything that
   * could possibly be part of a place name, a street number, a ZIP or a
   * postal code?
   *
   * The Latin-1 Supplement and Latin Extended-A ranges are included so
   * that accented input cannot be rejected. No realistic Quebec place
   * name is composed entirely of accented characters, but the cost of
   * covering it is one range and the cost of getting it wrong is a
   * customer who cannot search for where they live.
   *
   * This is the whole test, on purpose. See build-v010.py for why an
   * address-shape validator was rejected.
   */
  var AVALON_SEARCHABLE = /[A-Za-z0-9\u00C0-\u024F]/;

  /**
   * Issue 25. Remove the two characters that would truncate the geocode
   * proxy URL before it leaves the browser.
   *
   * slp_core.js:806 builds that URL with encodeURI(), which does not
   * escape "#" or "?" - both are in the reserved set it leaves alone on
   * purpose. Both are structural in a URL: "#" opens a fragment, which
   * the browser never sends, and "?" opens a query string, which ends the
   * path segment the proxy reads the address out of. So
   * "1200 Woodward Ave #4, Detroit, MI" reaches Google as
   * "1200 Woodward Ave " - no suite, no city, no state.
   *
   * Replaced with a space rather than deleted, so "#4" becomes "4"
   * instead of vanishing. Google geocodes to the building and ignores a
   * suite number either way; the space is simply the smaller change.
   *
   * The early return is not tidiness. Almost every search contains
   * neither character, and a function that rewrote every address would be
   * much harder to reason about the next time a geocode returns something
   * unexpected. Same shape as clean_url()'s no-op test, same reason.
   *
   * Cannot be handed a string that cleans down to nothing: a bare "#" or
   * "?" has no letter or digit, so AVALON_SEARCHABLE rejects it at Layer
   * 0 and it never reaches a geocode.
   */
  function avalon_geocode_safe(address) {
    if (typeof address !== "string") return address;
    if (address.indexOf("#") === -1 && address.indexOf("?") === -1) {
      return address;
    }
    return address
      .replace(/[#?]/g, " ")
      .replace(/\s{2,}/g, " ")
      .trim();
  }

  /**
   * Read the ISO country code out of a geocoder result or a Places result.
   *
   * Returns null when there is no usable country component. That null is the
   * no-op signal for Layer 1, and it is load-bearing: URL-supplied
   * coordinates and any autocomplete selection Google did not resolve a
   * country for arrive with address_components absent or empty, and those
   * must fall through to Layer 3 rather than be rejected on a guess.
   *
   * @param  {object}       result  results[0], or a Places PlaceResult.
   * @return {string|null}          upper-case alpha-2, or null.
   */
  function avalon_country_of(result) {
    if (!result || !result.address_components) return null;
    var parts = result.address_components;
    if (!parts.length) return null;
    for (var i = 0; i < parts.length; i++) {
      var part = parts[i];
      if (!part || !part.types || !part.types.length) continue;
      for (var j = 0; j < part.types.length; j++) {
        if (part.types[j] !== "country") continue;
        if (typeof part.short_name !== "string") return null;
        var code = part.short_name.replace(/^\s+|\s+$/g, "").toUpperCase();
        return code === "" ? null : code;
      }
    }
    return null;
  }

  var avalon_guard = {
    IDLE: "IDLE",
    VALIDATING: "VALIDATING",
    RESOLVING: "RESOLVING",
    SEARCHING: "SEARCHING",
    RESULTS: "RESULTS",
    EMPTY: "EMPTY",
    REJECTED: "REJECTED",
    ERROR: "ERROR",
    TIMEOUT: "TIMEOUT",

    state: "IDLE",
    last_state: null,
    generation: 0,
    timer: null,
    pending_url: null,
    last_response: null,
    user_initiated: false,
    original_geocode_response: null,
    original_send_ajax: null,
    original_get_from_server: null,
    layout_normalized: false,
    sidebar_default: null,

    is_busy: function () {
      return (
        this.state === this.VALIDATING ||
        this.state === this.RESOLVING ||
        this.state === this.SEARCHING
      );
    },

    is_stale: function (generation) {
      return generation !== this.generation;
    },

    /**
     * Open a new search cycle. Bumps the generation so that any callback
     * still in flight from a previous cycle is ignored on arrival.
     *
     * @param  {boolean}  user_initiated  false for the page-load bootstrap.
     * @return {number}                   the generation token for this cycle.
     */
    start: function (user_initiated) {
      this.generation++;
      this.user_initiated = !!user_initiated;
      this.pending_url = null;
      this.last_response = null;
      this.clear_notification();
      this.state = this.RESOLVING;
      this.show_spinner(true);
      this.arm_timer();
      return this.generation;
    },

    /**
     * Move between busy states. Deliberately does NOT re-arm the timer:
     * decision 11 puts one ceiling on the whole search, not one per leg.
     */
    enter: function (busy_state) {
      if (!this.is_busy()) return;
      this.state = busy_state;
      this.show_spinner(true);
    },

    /**
     * Terminal transition. No-ops unless a cycle is actually open, so a
     * double-finish (late callback after a timeout) cannot fire twice.
     */
    finish: function (terminal_state, options) {
      if (!this.is_busy()) return;
      options = options || {};

      this.clear_timer();
      this.last_state = terminal_state;
      this.state = this.IDLE;
      this.show_spinner(false);

      // Issue 1, sticky bad URL: rule (c) - the address bar is rewritten only
      // once a search has actually succeeded, so a refresh cannot replay a
      // URL that hung. add_url_param() stays a remove-list (constraint C1);
      // the UTM parameters on the URL are untouched either way.
      //
      // Issue 16, v0.0.13. Rule (c) stopped a failure from DIRTYING the bar
      // but never CLEANED one the visitor arrived on, so a refresh replayed
      // it: land on ?place_lat=48.86&place_lng=2.35, get the territory
      // message, refresh, get it again, forever.
      //
      // Write on RESULTS, clean on every other terminal state. Decision 35.
      // Any narrower rule leaves a case where the parameters on the bar do
      // not describe what is on the screen: land on ?place_address=Detroit,
      // search somewhere with no dealers, and EMPTY would keep replaying
      // Detroit. Two costs were accepted with it - a genuine no-dealers
      // result stops being shareable as a link, and a TIMEOUT on a slow but
      // valid search loses the query on refresh rather than retrying it.
      if (terminal_state === this.RESULTS) {
        //Null on the get_user_current_address() bootstrap, which reaches
        //load_markers() directly and never composes a URL. Nothing to write
        //and nothing to clean - there were no place_ parameters to begin
        //with - so this case falls through both branches on purpose.
        if (this.pending_url) {
          window.history.replaceState(null, "", this.pending_url);
        }
      } else {
        this.clean_url();
      }
      this.pending_url = null;

      //Both rejection layers land here, so they share one presentation:
      //markers already cleared, sidebar reset to the neutral prompt, and the
      //territory message written above the field by options.message.
      this.set_no_results(
        terminal_state === this.ERROR ||
          terminal_state === this.TIMEOUT ||
          terminal_state === this.EMPTY ||
          terminal_state === this.REJECTED
      );
      if (options.message) {
        this.notify(options.message);
      }
      if (options.focus_input) {
        this.focus_input();
      }
    },

    /**
     * Issue 16. Strip the three search parameters from the address bar and
     * leave everything else on it alone.
     *
     * A remove-list, not a keep-list. Constraint C1: UTM parameters arrive
     * on a first-touch URL before any cookie exists, so a keep-list would
     * discard attribution for exactly the visitors it is there to measure.
     * add_url_param() already deletes on a falsy value, and these are the
     * same three keys cslmap_searchLocations() composes. place_country is
     * deliberately not among them: it never reaches the URL, it lives only
     * in jQuery .data() on #addressInput.
     *
     * Measured from window.location.href, NEVER from pending_url. On the
     * path this exists to fix, pending_url is always null - a Layer 0
     * rejection returns before pending_url is assigned, and start() nulls
     * it at the top of every cycle.
     *
     * Safe to run mid-cycle because nothing else reads these parameters or
     * touches history. cslmap_build_map() reads them once at init, before
     * any search can finish, and slp_core.min.js - the build that actually
     * runs - has no reference to place_address, place_lat or place_lng and
     * no history call at all.
     *
     * The no-op test is load-bearing, not tidiness. Without it every
     * rejection from an already-clean URL issues a pointless replaceState,
     * and suite-core's assertion that a rejected search does not rewrite
     * the URL - the regression net for rule (c) - would have to be weakened
     * to let this feature through.
     */
    clean_url: function () {
      var cleaned = add_url_param({
        place_address: null,
        place_lat: null,
        place_lng: null,
      });
      if (cleaned.href === window.location.href) return;
      window.history.replaceState(null, "", cleaned.href);
    },

    arm_timer: function () {
      var guard = this;
      var generation = this.generation;
      this.clear_timer();
      this.timer = window.setTimeout(function () {
        if (guard.is_stale(generation) || !guard.is_busy()) return;
        // A bootstrap timeout clears the spinner silently - the visitor has
        // not asked for anything yet, so there is nothing to report. A
        // user-initiated timeout says so and hands the field back.
        guard.finish(guard.TIMEOUT, {
          message: guard.user_initiated ? AVALON_GUARD_MESSAGES.timeout : null,
          focus_input: guard.user_initiated,
        });
      }, AVALON_GUARD_TIMEOUT_MS);
    },

    clear_timer: function () {
      if (this.timer !== null) {
        window.clearTimeout(this.timer);
        this.timer = null;
      }
    },

    /* -------------------------------------------------- spinner ownership */

    show_spinner: function (show) {
      this.ensure_spinner();
      sl_show_loading(show);
      //Only on the way in, and only after sl_show_loading(true): while
      //.sl_hidden is applied the indicator is display:none, so every
      //measurement below would read zero.
      if (show) this.center_spinner();
    },

    /**
     * Put the spinner icon in the middle of the map.
     *
     * The scrim covers all of #sl_div - search column, results panel and
     * map - which is correct, because it has to block interaction with all
     * three. Its geometric centre is not a good place for the icon, though:
     * the search column occupies the left third, so 50%/50% lands near the
     * map's LEFT EDGE. Measured on Aura DEV at a 1920 viewport, scrim
     * 338..1918 against map 907..1918, which put the icon about 260px left
     * of where the eye looks for it.
     *
     * Measured rather than expressed in CSS because CSS cannot centre an
     * element on a sibling without hardcoding the column widths, and
     * style.css is per-site with four distinct md5s across the six
     * environments. Decision 6.
     *
     * Offsets go in left/top, NEVER in transform. v0.0.8 shipped
     * `transform: translate(-50%,-50%)` on this icon and it was discarded
     * on every frame: .fa-spin declares
     * `animation: fa-spin 2s linear infinite` and its keyframes set
     * `transform: rotate(...)`, which outranks an author normal
     * declaration. Font Awesome 5.15.3, loaded by Elementor.
     */
    center_spinner: function () {
      var indicator = document.getElementById("sl_loading_indicator");
      if (!indicator) return;
      var icon = indicator.querySelector("i");
      if (!icon) return;

      var host = indicator.getBoundingClientRect();
      var map_box = document.getElementById("map_box");
      var target = map_box ? map_box.getBoundingClientRect() : null;

      //No map yet at the page-load bootstrap, and the stacked layout at
      //<=768px can leave it zero-sized. Either way the scrim is the right
      //thing to centre on.
      if (!target || !target.width || !target.height) {
        target = host;
      }

      //offsetWidth is 0 if the webfont has not resolved yet; fa-3x is 48px.
      var w = icon.offsetWidth || 48;
      var h = icon.offsetHeight || 48;

      icon.style.left =
        target.left - host.left + target.width / 2 - w / 2 + "px";
      icon.style.top =
        target.top - host.top + target.height / 2 - h / 2 + "px";
    },

    /**
     * Constraint C3: the spinner markup lives in the database
     * (csl-slplus-options_nojs['layout']), not in any version-controlled file.
     * Feature-detect it so a code deploy never depends on a settings export.
     */
    ensure_spinner: function () {
      if (
        jQuery("#sl_loading_indicator").length === 0 &&
        jQuery("#sl_div").length > 0
      ) {
        jQuery("#sl_div").append(
          '<div id="sl_loading_indicator" class="sl_hidden sl_loading">' +
            '<i class="fa fa fa-compass fa-spin fa-3x"></i></div>'
        );
      }
      this.ensure_guard_css();
    },

    /* ------------------------------------------------ injected stylesheet */

    /**
     * One injected style block for every rule the Guard owns. Injected
     * rather than added to style.css because slp_avalon.js is repo-managed
     * and identical on all three sites, while style.css is per-site and
     * diverges - four distinct md5s across the six environments as of
     * v0.0.8. That is decision 6.
     *
     * The notification is deliberately a separate channel from
     * .get_my_position_notification, which is owned by
     * handle_geolocation_error(): that function removes the node wholesale
     * on its success path and would silently wipe ours.
     *
     * Called from ensure_spinner(), notify() and set_no_results(), so it is
     * in place before any of the three surfaces is first painted. One
     * guarded injector rather than three near-identical ones, which would
     * be three places to forget.
     */
    ensure_guard_css: function () {
      if (jQuery("style.avalon_guard_css").length > 0) return;
      jQuery("head").append(
        jQuery(
          "<style class='avalon_guard_css'>" +
            //Territory and error copy above the field. #c00 measures about
            //3.4:1 against the #090909 section background that Elementor
            //sets on the locator (post-28743.css), which fails WCAG AA for
            //13px text. #E7167C is about 4.55:1 and is already this page's
            //focus colour, so the palette does not grow.
            ".avalon_search_notification{color:#E7167C;font-size:13px;" +
            "display:block;margin:0 0 8px;padding-top:30px;" +
            "line-height:1.35;}" +
            //Issue 12. The neutral prompt re-emitted into #map_sidebar by
            //set_no_results(). Every other visible element in that panel
            //sets #FFFFFF explicitly; a bare div inherits the theme body
            //colour and is unreadable on #090909. Visible on Layer 1
            //rejections since v0.0.6, and on Layer 3 rejections from
            //v0.0.8, because install_options_hook() stops SLP painting
            //over it.
            ".avalon_sidebar_prompt{color:#FFFFFF;font-size:16px;" +
            "line-height:24px;font-family:var(--body-font-family);" +
            "padding:16px 0;}" +
            //NOTE there is deliberately no rule here for the spinner icon.
            //v0.0.8 tried `#sl_loading_indicator i{transform:translate(
            //-50%,-50%)}` and it never applied: .fa-spin runs
            //`animation: fa-spin 2s linear infinite` whose keyframes set
            //`transform: rotate(...)`, and an animation beats an author
            //normal declaration in the cascade. The icon is positioned by
            //center_spinner() instead, in left/top, at show time.
            "</style>"
        )
      );
    },

    notify: function (message) {
      if (!message) return;
      this.ensure_guard_css();
      var $existing = jQuery(".avalon_search_notification");
      if ($existing.length > 0) {
        $existing.text(message);
        return;
      }
      var $notification = jQuery(
        "<span class='avalon_search_notification' role='alert'></span>"
      ).text(message);
      //Above the field, outside #addy_in_address: Google's .pac-container
      //renders directly below #addressInput and was covering this.
      if (jQuery("#addy_in_address").length > 0) {
        $notification.insertBefore("#addy_in_address");
      } else {
        $notification.insertAfter("#addressInput");
      }
    },

    clear_notification: function () {
      jQuery(".avalon_search_notification").remove();
    },

    /**
     * Snapshot the sidebar's neutral prompt before any search has run, so a
     * failed search can restore it instead of leaving stale results behind.
     * Captured from the page rather than hardcoded, so the three sites keep
     * their own wording.
     */
    capture_sidebar_default: function () {
      if (this.sidebar_default !== null) return;
      var sidebar = document.getElementById("map_sidebar");
      if (!sidebar) return;
      //Text, not markup. SLP wraps this in .text_below_map, which style.css
      //hides so it cannot flash during the page-load search; re-emitting it
      //under our own class is what keeps the desktop panel populated when a
      //search fails.
      var prompt_text = (sidebar.textContent || "").trim();
      if (prompt_text === "") return;
      this.sidebar_default = prompt_text;
    },

    /**
     * No-results presentation.
     *
     * Desktop keeps the results panel, reset to that neutral prompt - the
     * message is already under the field, so repeating it there is redundant,
     * and leaving the previous search's results would contradict a map whose
     * markers we just cleared.
     *
     * Stacked layouts hide the column outright (CSS, <=768px): #results_box
     * carries a hard height:670px, so an empty panel is dead space under the
     * map rather than beside it.
     *
     * This also keeps SLP's own failure text out of view. Its else branch
     * injects the raw Google API error plus a link to the plugin vendor's
     * competing SaaS product into that panel.
     */
    set_no_results: function (on) {
      jQuery("#sl_div").toggleClass("avalon_no_results", !!on);
      if (!on) return;
      this.ensure_guard_css();
      var sidebar = document.getElementById("map_sidebar");
      if (!sidebar || this.sidebar_default === null) return;
      var prompt = document.createElement("div");
      prompt.className = "avalon_sidebar_prompt";
      prompt.textContent = this.sidebar_default;
      sidebar.textContent = "";
      sidebar.appendChild(prompt);
    },

    /**
     * "Get My Position" sits below the field in SLP's markup, where the Places
     * autocomplete dropdown covers it. Moved once, at map-ready. The submit
     * button is re-anchored to the bottom of #address_search in style.css so
     * it stays over the field rather than over this link.
     */
    normalize_search_layout: function () {
      if (this.layout_normalized) return;
      var $link = jQuery("#get_my_position");
      var $wrapper = jQuery("#addy_in_address");
      if ($link.length === 0 || $wrapper.length === 0) return;
      $link.insertBefore($wrapper);
      this.layout_normalized = true;
    },

    focus_input: function () {
      var input = document.getElementById("addressInput");
      if (!input) return;
      input.focus();
      if (typeof input.select === "function") {
        input.select();
      }
    },

    /* ------------------------------------------------------------- hooks */

    /**
     * Issue 1 Path A, and the home of Layer 1.
     *
     * process_geocode_response is an instance property (slp_core.js:1526) and
     * doGeocode resolves it at call time (slp_core.js:1651), so a single
     * assignment here catches the real geocode path AND the coords-spoof
     * path in cslmap_searchLocations(), which calls the same property on the
     * same instance. One override, both paths - decision 10.
     */
    install_geocode_hook: function (cslmap) {
      if (this.original_geocode_response !== null) return;
      var guard = this;
      var original = cslmap.process_geocode_response;
      this.original_geocode_response = original;

      cslmap.process_geocode_response = function (results, status, message) {
        var ok =
          status === google.maps.GeocoderStatus.OK &&
          results &&
          results.length > 0;

        if (ok) {
          //Layer 1. The country component is authoritative where it exists.
          //Where it does not, avalon_country_of() returns null and this
          //no-ops by design: the decision falls to Layer 3 on the server.
          var country = avalon_country_of(results[0]);

          if (
            country !== null &&
            AVALON_ALLOWED_COUNTRIES.indexOf(country) === -1
          ) {
            var reject_was_busy = guard.is_busy();

            //DO NOT mirror the ERROR path's `gmap === null -> delegate`
            //branch below. That branch is safe there only because status is
            //not OK, so the original takes its FAILURE branch
            //(slp_core.js:1578) and builds a fallback-centred map without
            //searching. Status IS OK here, so delegating would take the
            //SUCCESS branch at slp_core.js:1527 -> 1555 and call build_map()
            //with the location we just rejected. build_map is overridden to
            //cslmap_build_map(), whose bootstrap then either re-submits
            //#searchForm or calls load_markers() around that same rejected
            //point - and the coarse boxes pass Tijuana, so Layer 3 would not
            //catch it either. The visitor would get the territory message
            //above the field and Mexican dealers below it.
            //
            //Consequence, accepted: on a site where gmap can still be null
            //at the first geocode (map_center_lat unset), a rejected first
            //search leaves the map unbuilt. Unreachable on Aura -
            //map_center_lat is set, so slp_core.js:715 takes the build_map()
            //branch and cslmap_build_map() assigns gmap before the bootstrap
            //submit. Tahoe/Avalon portability item, tracked in the handoff.
            if (cslmap.gmap !== null && reject_was_busy) {
              cslmap.clearMarkers();
            }

            guard.finish(guard.REJECTED, {
              message: AVALON_GUARD_MESSAGES.territory,
              focus_input: guard.user_initiated,
            });
            return;
          }

          guard.enter(guard.SEARCHING);
          return original.call(cslmap, results, status, message);
        }

        // Path A. slp.geocoder.geocode's own .fail() (slp_core.js:830-832)
        // calls back with GeocoderStatus.ERROR, so a transport failure and a
        // genuine ZERO_RESULTS both land here.
        var was_busy = guard.is_busy();

        if (cslmap.gmap === null) {
          // No map yet: delegate so SLP still builds the fallback-centred map
          // and the page is not left blank.
          original.call(cslmap, results, status, message);
        } else if (was_busy) {
          cslmap.clearMarkers();
        }

        guard.finish(guard.ERROR, {
          message: AVALON_GUARD_MESSAGES.geocode_failed,
          focus_input: guard.user_initiated,
        });
      };
    },

    /**
     * Issue 1 Path B. slp.send_ajax (wpslp.js:8) is a bare $.post with no
     * .fail() and does not return the jqXHR, so it cannot be decorated - it
     * has to be reissued. The JSON.parse-inside-try/catch semantics are
     * reproduced exactly; the only addition is the failure leg.
     */
    install_transport_hook: function () {
      if (this.original_send_ajax !== null) return;
      if (typeof slp === "undefined" || typeof slp.send_ajax !== "function") {
        return;
      }
      var guard = this;
      this.original_send_ajax = slp.send_ajax;

      slp.send_ajax = function (action, callback) {
        // Which request is this? Decided BEFORE the post goes out, because
        // the failure leg below has no other way to tell.
        //
        // send_ajax has two live callers on this page, not one:
        //   slp_core.js:1849      the location search
        //   slp-experience        SLPEXP.email_form.send_email, posting
        //                         {action:"email_form", formdata:...}
        // and this wrapper is global, so both route through it.
        //
        // slp_core.js:1808 defaults action.action to csl_ajax_search and
        // 1842 rewrites it to csl_ajax_onload for the page-load search.
        // Those two names are the search path and nothing else is.
        //
        // An unrecognised shape is treated AS a search, so it keeps the
        // v0.0.10 behaviour. A future caller quietly losing its error
        // reporting would be a worse bug than the one this fixes.
        var named =
          !!action && typeof action === "object" &&
          typeof action.action === "string";
        var is_search = named
          ? action.action === "csl_ajax_search" ||
            action.action === "csl_ajax_onload"
          : true;

        return jQuery
          .post(slplus.ajaxurl, action, function (response) {
            try {
              response = JSON.parse(response);
            } catch (ex) {}
            callback(response);
          })
          .fail(function () {
            // v0.0.11. Was a bare state test, which held whenever no
            // search was running and failed exactly when one was: an
            // email_form POST erroring mid-search finished that search
            // with ERROR and the transport message. Narrow window, but it
            // reported a failure that had not happened.
            if (!is_search) return;
            // Kept as the cycle guard: a failure arriving after the 12s
            // ceiling has already fired must not reopen anything. finish()
            // would no-op anyway; this says so out loud.
            if (guard.state !== guard.SEARCHING) return;
            guard.finish(guard.ERROR, {
              message: AVALON_GUARD_MESSAGES.transport,
              focus_input: guard.user_initiated,
            });
          });
      };
    },

    /**
     * Layer 3 presentation, handoff s7.4.
     *
     * putMarkers() blanks #map_sidebar (slp_core.js:1317) and then, when the
     * marker count is zero, fetches message_no_results at 1328 and writes it
     * at 1330. That fetch is a jQuery.getJSON, so it always resolves AFTER
     * location_search_processed publishes at 1911 - which means it lands on
     * top of the neutral prompt that finish() -> set_no_results(true) has
     * just written, and the visitor reads 'No Dealers found in this area,
     * please try again!' underneath a message saying we do not serve that
     * area at all. Issue 4.
     *
     * Suppressed at source rather than with a CSS class: a class would
     * reintroduce the three-site style.css deliverable decision 6 exists to
     * avoid, and would leave a 670px void beside the map at desktop.
     *
     * Scoped to message_no_results ONLY. slp_core.js:1600 routes
     * message_bad_address through this same function and must keep working.
     * Do not broaden it.
     *
     * Scoped to territory rejections ONLY, not to EMPTY. On a genuine
     * in-territory empty result SLP's copy is the more useful of the two,
     * and the uncapped backfill (decision 13) makes that case near
     * unreachable anyway. Decision 21.
     *
     * The short-circuit is synchronous, mirroring SLP's own
     * shortcode_attributes shortcut at slp_core.js:842-845. Deferring it
     * would put the write back after set_no_results() and restore the race.
     *
     * NOTE: the file the browser executes is slp_core.min.js, not
     * slp_core.js. Every property wrapped here was verified present and
     * semantically identical in the minified build before this was written.
     */
    install_options_hook: function () {
      if (this.original_get_from_server !== null) return;
      if (
        typeof slp === "undefined" ||
        typeof slp.option === "undefined" ||
        typeof slp.option.get_from_server !== "function"
      ) {
        return;
      }
      var guard = this;
      var original = slp.option.get_from_server;
      this.original_get_from_server = original;

      slp.option.get_from_server = function (option_name, callback) {
        if (
          option_name === "message_no_results" &&
          guard.last_response &&
          guard.last_response.avalon_territory_rejected &&
          typeof callback === "function"
        ) {
          //slp_core.js:1334 - a falsy value takes the else branch, which
          //logs and writes nothing. The prompt survives.
          callback({ value: "" });
          return;
        }
        return original.call(slp.option, option_name, callback);
      };
    },

    /**
     * location_search_processed publishes at slp_core.js:1911, outside and
     * after the if/else that closes at 1904, so it fires on valid and invalid
     * responses alike. It is the success terminal for both the geocoded and
     * the map-centre search paths.
     */
    on_search_processed: function () {
      if (!this.is_busy()) return;
      var response = this.last_response;

      //Layer 3 rejection. The server gate zeroes count and response, so
      //without this branch the payload is indistinguishable from a genuine
      //EMPTY - which finishes with no options, therefore no message, and
      //the visitor gets a blank panel with no explanation.
      if (response && response.avalon_territory_rejected) {
        this.finish(this.REJECTED, {
          message: AVALON_GUARD_MESSAGES.territory,
          focus_input: this.user_initiated,
        });
        return;
      }

      var count =
        response && typeof response.count !== "undefined"
          ? parseInt(response.count, 10)
          : null;
      this.finish(count === 0 ? this.EMPTY : this.RESULTS);
    },
  };

  //Run as soon as the markup is parsed rather than waiting for the Google
  //Maps callback. Declared here, after avalon_guard exists, rather than in the
  //IIFE at the top of this file: that block runs before this object is
  //assigned, and relying on jQuery deferring the callback long enough would be
  //a latent ordering trap. Both calls are idempotent and both re-run at
  //map-ready if the elements were not present yet.
  jQuery(document).ready(function () {
    avalon_guard.normalize_search_layout();
    avalon_guard.capture_sidebar_default();
  });

  var avalon_cslmap = null;
  function cslmap_searchLocations() {
    //Layer 2: the search owns the spinner from here to its terminal state.
    avalon_guard.start(true);
    let new_url = window.location.href;
    let append_this =
      typeof slplus.options.append_to_search !== "undefined" &&
      slplus.options.append_to_search
        ? " " + slplus.options.append_to_search.trim()
        : "";
  
    //Held separately from `address` because append_to_search is a
    //settings-driven suffix: if it is ever set, it would smuggle letters
    //into the string Layer 0's floor is supposed to judge, and the floor
    //would silently stop working. saneValue() already trims.
    let raw_address = avalon_cslmap.saneValue("addressInput", "");
    let address = raw_address + append_this;
    //Do we already have coordinates?
    let coords = null;
    if (
      jQuery("#addressInput").data("place_lat") &&
      jQuery("#addressInput").data("place_lng")
    ) {
      coords = {
        lat: jQuery("#addressInput").data("place_lat"),
        lng: jQuery("#addressInput").data("place_lng"),
      };
      // coords = new google.maps.LatLng(
      //   jQuery("#addressInput").data("place_lat"),
      //   jQuery("#addressInput").data("place_lng")
      // );
    }
  
    /* ---------------------------------------------------------- Layer 0
     * Synchronous pre-flight. Runs before unhide_map(), before the URL is
     * composed and before either branch below, so a rejection touches
     * nothing: no geocode, no AJAX, no map movement, no marker.
     *
     * Safe to reject synchronously only because #searchForm has no
     * jQuery-bound submit handler. It carries an inline onsubmit attribute
     * registered at parse time, so any jQuery handler would run AFTER this
     * function returns and would switch the spinner back on with nothing
     * left to switch it off. The one that used to exist was removed in
     * v0.0.6; see the note in avalon_init_gmaps(). Do not reintroduce it.
     * ------------------------------------------------------------------ */
    avalon_guard.enter(avalon_guard.VALIDATING);

    //(a) Syntactic floor. Only meaningful when the visitor actually typed
    //something: an empty field with no coordinates is a legitimate search
    //from the map centre and is handled in the else branch below.
    if (!coords && raw_address && !AVALON_SEARCHABLE.test(raw_address)) {
      avalon_guard.finish(avalon_guard.REJECTED, {
        message: AVALON_GUARD_MESSAGES.invalid_input,
        focus_input: avalon_guard.user_initiated,
      });
      return;
    }

    //(b) Decision 16, and the reason Layer 0 exists. Coordinates arrive
    //here from three places: an autocomplete selection, the URL bootstrap
    //in cslmap_build_map(), and Get My Position. Only the first carries a
    //country, so Layer 1 no-ops on the other two and SLP would otherwise
    //pin and pan the map to a location we are about to reject. Issue 15.
    //
    //The boxes are coarse and admit Tijuana, Nassau and Road Town. That is
    //deliberate and unchanged: those carry a country, so Layer 1 catches
    //them precisely. This check only has to agree with Layer 3, which uses
    //the same eight boxes, so it rejects nothing the server would accept.
    //
    //avalon_in_territory() parseFloats, so the strings that
    //URLSearchParams hands back need no conversion here.
    if (coords && !avalon_in_territory(coords.lat, coords.lng)) {
      avalon_guard.finish(avalon_guard.REJECTED, {
        message: AVALON_GUARD_MESSAGES.territory,
        focus_input: avalon_guard.user_initiated,
      });
      return;
    }

    //Past the gate. Everything from here is the pre-existing flow.
    avalon_guard.enter(avalon_guard.RESOLVING);
  
    avalon_cslmap.unhide_map();
  
    google.maps.event.trigger(avalon_cslmap.gmap, "resize");
    //Manipulate URL
    let new_url_params = {
      place_address: null,
      place_lat: null,
      place_lng: null,
    };
    if (coords) {
      new_url_params.place_lat = coords.lat;
      new_url_params.place_lng = coords.lng;
    }
    if (address) {
      new_url_params.place_address = address;
    }
    new_url = add_url_param(new_url_params, new_url).href;
    //Issue 1, sticky bad URL: hold the rewrite until the search succeeds.
    //Written by avalon_guard.finish() on entry to RESULTS, and only there.
    avalon_guard.pending_url = new_url;
    if (coords) {
      //Just skip geocode and search for locations
      //We need to spoof the process_geocode_response function with a fake geocode response,status and message
      let fake_status = google.maps.GeocoderStatus.OK;
      let fake_message = "";
      let place_country = jQuery("#addressInput").data("place_country");
      let fake_geocode_response = [
        {
          geometry: {
            location: coords,
          },
          //Shaped like a real geocode result so Layer 1 needs no special
          //case. Empty when no country was captured, which is the documented
          //no-op signal - Layer 3 decides those.
          address_components: place_country
            ? [
                {
                  short_name: place_country,
                  long_name: place_country,
                  types: ["country"],
                },
              ]
            : [],
        },
      ];
      avalon_cslmap.process_geocode_response(
        fake_geocode_response,
        fake_status,
        fake_message
      );
    } else {
      // Address was given, use it...
      //
      if (address) {
        avalon_cslmap.address = address;
        avalon_cslmap.doGeocode();
        // Otherwise use the current map center as the center location
        //
      } else {
        //No address and no coords: straight to AJAX, so skip RESOLVING.
        avalon_guard.enter(avalon_guard.SEARCHING);
        avalon_cslmap.load_markers(
          null,
          avalon_cslmap.saneValue("radiusSelect", "40000")
        );
      }
    }
  }
  // jQuery;
  //resizeMap() and its window.resize binding were deleted in v0.0.6.
  //Every value the function computed was NaN and it had been a no-op for as
  //long as this page has existed:
  //  * jQuery("header#header") matched nothing - there is no <header> on
  //    find-a-dealer - so window_height - undefined = NaN, and both
  //    .height(NaN) and .css("min-height","NaNpx") are invalid and ignored.
  //  * "#sl_bottom_left #search_box" was a descendant selector, but
  //    #search_box is a SIBLING of #sl_bottom_left in the live DOM.
  //Layout comes from style.css. Do not reinstate this; making it "work"
  //would change the layout for the first time in the file's history.
  var boundary_circle = null;
  var markers_list_natural = null;
  //Reset location data when address changes
  jQuery(document).on("change", "#addressInput", function () {
    jQuery(this).data("place_lat", null);
    jQuery(this).data("place_lng", null);
    //All three reset together. Nulling only the coordinates would leave a
    //stale country to be validated against a fresh location.
    jQuery(this).data("place_country", null);
  });
  function initialize_autocomplete() {
    let input = document.getElementById("addressInput");
    if (!input) return;
    let places_autocomplete = new google.maps.places.Autocomplete(input);
    //Layer 1 needs the country component; geometry is what the handler below
    //already reads. Nothing else is used, and naming the field set also drops
    //this call from the Places Details SKU to Basic Data. Must live inside
    //this function - places_autocomplete is a local.
    places_autocomplete.setFields(["address_components", "geometry"]);
    places_autocomplete.addListener("place_changed", function () {
      let selected = places_autocomplete.getPlace();
      if (typeof selected.geometry != "undefined") {
        jQuery(input).data("place_lat", selected.geometry.location.lat());
        jQuery(input).data("place_lng", selected.geometry.location.lng());
        //Carried onto the spoofed payload in cslmap_searchLocations() so the
        //coords branch reaches Layer 1 looking like a real geocode. This is
        //what closes the autocomplete bypass: before v0.0.6 an autocomplete
        //selection was protected by nothing on the client at all.
        jQuery(input).data("place_country", avalon_country_of(selected));
        jQuery("#searchForm").find("input[type=submit]").trigger("click");
      }
    });
  }
  String.prototype.isNumber = function () {
    return /^\d+$/.test(this);
  };
  function cslmap_build_map(center, map_div_id) {
    if (!map_div_id) {
      map_div_id = document.getElementById("map");
    }
    if (avalon_cslmap.gmap !== null) {
      return;
    }
  
    avalon_cslmap.options = {
      center: center,
      mapTypeId: avalon_cslmap.mapType,
      minZoom: 1,
      zoom: parseInt(slplus.options.zoom_level),
    };
    if (slplus.options.google_map_style) {
      jQuery.extend(avalon_cslmap.options, {
        styles: JSON.parse(slplus.options.google_map_style),
      });
    }
  
    /**
     * Manipulate the Google map options.
     *
     * @filter  map_options
     *
     * @param   {object}   avalon_cslmap.options   The current map options for google.maps.Map
     * @return  {object}                  Modified map options.
     */
    slp_Filter("map_options").publish(avalon_cslmap.options);
  
    avalon_cslmap.gmap = new google.maps.Map(map_div_id, avalon_cslmap.options);
  
    google.maps.event.addListener(
      avalon_cslmap.gmap,
      "bounds_changed",
      function () {
        avalon_cslmap.__waitForTileLoad.call(avalon_cslmap);
      }
    );
  
    // Location Sensor Is Enabled
    // Or immediate mode and home marker is enabled
    //
    if (avalon_cslmap.show_home_marker()) {
      avalon_cslmap.homePoint = center; // Set the home marker location to center
      // lat/long sent in to build_map
      avalon_cslmap.addMarkerAtCenter();
    }
  
    // If immediately show locations is enabled.
    //
    if (slplus.options.immediately_show_locations !== "0") {
      //First check if we have params in url
      let curr_url = new URL(window.location.href);
      if (
        curr_url.searchParams.get("place_address") ||
        (curr_url.searchParams.get("place_lat") &&
          curr_url.searchParams.get("place_lng"))
      ) {
        if (curr_url.searchParams.get("place_address")) {
          jQuery("#addressInput").val(curr_url.searchParams.get("place_address"));
        }
        jQuery("#addressInput").data(
          "place_lat",
          curr_url.searchParams.get("place_lat")
        );
        jQuery("#addressInput").data(
          "place_lng",
          curr_url.searchParams.get("place_lng")
        );
        jQuery("#searchForm").find("input[type=submit]").trigger("click");
      } else {
        get_user_current_address(true, function () {
          //If we can't get current address
          // 4.6 initial load radius is empty (load first X nearest map center)
          // default is null if main initial_radius is empty
          var radius = null;
          slplus.options.initial_radius = slplus.options.initial_radius.replace(
            /\D/g,
            ""
          );
          if (/^[0-9]+$/.test(slplus.options.initial_radius)) {
            radius = slplus.options.initial_radius;
          }
          avalon_cslmap.search_options = null;
          avalon_cslmap.load_markers(center, radius);
        });
      }
    }
  
    /**
     * Map has been built trigger.
     *
     * @filter  map_built
     */
    slp_Filter("map_built").publish();
  }
  function avalon_init_gmaps() {
    //Issue 25. SLP publishes the geocoder request at slp_core.js:1647 and
    //geocodes it at 1649, so a subscriber that mutates .address here is
    //the last thing to touch the string before it goes out. This is SLP's
    //own extension point; the alternative was wrapping
    //slp.geocoder.geocode the way install_transport_hook wraps
    //slp.send_ajax, which is more machinery for the same result.
    //
    //store-locator-le/js/slp_core.js is the hard constraint and is never
    //edited, so the encodeURI() call at 806 cannot be corrected in place.
    //
    //Only the string sent to Google changes. #addressInput keeps what the
    //visitor typed and the place_address parameter composed in
    //cslmap_searchLocations() is untouched, so a successful search still
    //shares as the address they actually entered.
    //
    //A commented-out ZIP/Romania example subscribed to this same filter
    //here for years without ever being enabled. Removed in v0.0.14 rather
    //than left beside this, where it would read as a second attempt at
    //the same job.
    slp_Filter("geocoder_request").subscribe(function (request) {
      if (!request) return;
      request.address = avalon_geocode_safe(request.address);
    });
    //Overwrite search function
    slp_Filter("slp_map_ready").subscribe(function (cslmap) {
      avalon_cslmap = cslmap;
      avalon_cslmap.build_map = cslmap_build_map;
      avalon_cslmap.searchLocations = cslmap_searchLocations;
      //Issue 1 Path A + Layer 1. One override serves both.
      avalon_guard.install_geocode_hook(cslmap);
      //Issue 1 Path B. slp.run has already executed by map-ready, so
      //slp.send_ajax is defined and safe to replace here.
      avalon_guard.install_transport_hook();
      //Issue 4 / Layer 3 presentation. slp.option is a plain property on
      //the slp object literal (slp_core.js:839) and exists well before
      //map-ready, so this is safe here beside the transport hook.
      avalon_guard.install_options_hook();
      //Fallback only - both ran at DOM-ready and both are guarded, so this
      //pair is a no-op in the normal case.
      avalon_guard.capture_sidebar_default();
      avalon_guard.normalize_search_layout();
    });
    initialize_autocomplete();
    //Set map options
    slp_Filter("map_options").subscribe(function (options) {
      options.gestureHandling = "cooperative";
      return options;
    });
    //Hook to results processed, in order to show the radius
    slp_Filter("location_search_responded").subscribe(
      avalon_slp_on_location_search_responded
    );
    //Display loading - routed through the Guard, which is now the only
    //caller of sl_show_loading().
    slp_Filter("slp_map_ready").subscribe(function (cslmap) {
      //Bootstrap. build_map may auto-search from URL params, and
      //get_user_current_address() can sit on an unanswered permission
      //prompt indefinitely - the 12s ceiling now covers that too.
      avalon_guard.start(false);
    });
    slp_Filter("location_search_processed").subscribe(function () {
      avalon_guard.on_search_processed();
    });
    //REMOVED - jQuery("#searchForm").on("submit") -> sl_show_loading(true).
    //  #searchForm carries an inline onsubmit attribute, registered at
    //  parse time, so it runs BEFORE any jQuery-bound submit handler. Once
    //  Layer 0 (Step 4) can reject synchronously inside searchLocations(),
    //  this handler would switch the spinner back on with nothing left to
    //  switch it off - trading a geocode hang for a validation hang. Layer 1
    //  is not exposed to this: it rejects from inside an async geocode
    //  callback, long after the inline onsubmit has returned.
    //REMOVED - DOMSubtreeModified on #map_sidebar -> sl_show_loading(false).
    //  Mutation Events were removed in Chrome 135, Edge 137, Firefox 140
    //  and Safari 26, so this stopped firing in every shipping browser.
    //  It was the fallback that used to mask Issue 1 Path A.
  }
  function avalon_slp_on_location_search_responded(response) {
    //Handed to the Guard so on_search_processed() can tell RESULTS from
    //EMPTY. Layer 3 reports itself through avalon_territory_rejected on this
    //same payload. A Layer 1 rejection never gets here - it returns before
    //any AJAX is issued - which is the whole point of moving the check
    //client-side.
    avalon_guard.last_response = response;
    if (response && parseInt(response.count, 10) > 0) {
      avalon_guard.set_no_results(false);
    }
    let map = avalon_cslmap.gmap;
    let valid_response =
      typeof response.response !== "undefined" && response.success;
    if (valid_response) {
      markers_list_natural = response.response;
    } else {
      markers_list_natural = null;
    }
    //Clear circle
    if (boundary_circle !== null) {
      boundary_circle.setMap(null);
    }
  
    //Check if there are results outside the radius
    if (response.outside_radius) {
      return;
    }
    if (valid_response) {
      //Get Radius and map center
      let radius = response.http_query.radius;
      let lat = response.http_query.lat;
      let lng = response.http_query.lng;
      let radiuds_m = radius * 1609.34;
      //Boundary Circle
      //Uncomment the following code block to enable the boundary circle (radius)
      /*
      boundary_circle = new google.maps.Circle({
        center: new google.maps.LatLng(lat, lng),
        clickable: true,
        draggable: false,
        editable: false,
        fillColor: "#004de8",
        fillOpacity: 0.27,
        map,
        radius: radiuds_m,
        strokeColor: "#004de8",
        strokeOpacity: 0.62,
        strokeWeight: 1,
      });
      */
    }
  }
  function sl_show_loading(show) {
    if (typeof show == "undefined") {
      show = true;
    }
    if (show) {
      jQuery("#sl_loading_indicator").removeClass("sl_hidden");
    } else {
      jQuery("#sl_loading_indicator").addClass("sl_hidden");
    }
  }
  function decrease_zoom_level_to_fit_infowindows() {
    let map = avalon_cslmap.gmap;
    //Zoom map out by one, to fit infowindows
    let current_zoom = map.getZoom();
    map.setZoom(current_zoom - 1);
    // map.setOptions({
    //   gestureHandling: "greedy",
    // });
  }
  function enable_on_mouse_hover_for_markers() {
    // Delegated so the handler survives SLP replacing the results markup on
    // every search. Namespaced and cleared first: without the .off() these
    // accumulate on document, one generation per search, and all of them fire.
    jQuery(document).off("mouseenter.avalonHover");
    for (let i in avalon_cslmap.markers) {
      let marker = avalon_cslmap.markers[i];
      marker.__gmarker.addListener("mouseover", function () {
        avalon_cslmap.handle_location_result_click({
          data: {
            info: markers_list_natural[i],
            marker: marker,
          },
        });
      });
      //Also add on mouse hover for the sidebar list
      jQuery(document).on(
        "mouseenter.avalonHover",
        "#slp_results_wrapper_" + markers_list_natural[i].id,
        {
          info: markers_list_natural[i],
          marker: marker,
        },
        avalon_cslmap.handle_location_result_click
      );
    }
  }
  
  function get_short_address_from_geocode(address_components) {
    let street_number = ""; //street_number
    let street = ""; //route
    let city = ""; //locality
    let state = ""; //administrative_area_level_1
    let country = ""; //country
    let zip_code = ""; //postal_code
    for (let i of address_components) {
      if (i.types.includes("street_number")) {
        street_number = i.long_name;
      }
      if (i.types.includes("route")) {
        street = i.long_name;
      }
      if (i.types.includes("locality")) {
        city = i.long_name;
      }
      if (i.types.includes("administrative_area_level_1")) {
        state = i.long_name;
      }
      if (i.types.includes("country")) {
        country = i.long_name;
      }
      if (i.types.includes("postal_code")) {
        zip_code = i.long_name;
      }
    }
    let final_elements = [
      //street,
      //street_number,
      city,
      state,
      //country,
      zip_code,
    ].filter((e) => e);
    return final_elements.join(", ");
  }
  
  function get_user_current_address(initial_load = false, fail_cb = null) {
    // Try HTML5 geolocation.
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        (position) => {
          const pos = {
            lat: position.coords.latitude,
            lng: position.coords.longitude,
          };
          //Get Address
          const geocoder = new google.maps.Geocoder();
          geocoder.geocode(
            { location: pos },
            function (results, status, error_message) {
              if (status == google.maps.GeocoderStatus.OK) {
                let address = results[0].formatted_address;
                address = get_short_address_from_geocode(results[0].address_components);
                //Write the position, not just the label for it.
                //
                //Until v0.0.7 this set only .val(). A programmatic .val()
                //fires no change event, so the reset handler on #addressInput
                //never ran and any place_lat / place_lng already on the field
                //survived - the URL bootstrap's coordinates from
                //cslmap_build_map, or an earlier autocomplete selection.
                //cslmap_searchLocations() then took the coords branch and
                //searched THAT location while showing this address.
                //
                //Populating is better than clearing: `pos` is the GPS fix and
                //results[0] is its reverse geocode, so this avoids
                //re-geocoding a truncated display string, saves a call, and
                //gives Layer 1 a real country - which is what lets an
                //out-of-territory position be rejected client-side.
                //
                //All three move together. Setting coordinates without the
                //country would leave Layer 1 to no-op on a stale value.
                jQuery("#addressInput")
                  .val(address)
                  .data("place_lat", pos.lat)
                  .data("place_lng", pos.lng)
                  .data("place_country", avalon_country_of(results[0]));
                jQuery("#searchForm").find("input[type=submit]").trigger("click");
                handle_geolocation_error({}, true);
              } else {
                if (initial_load) {
                  if (fail_cb) {
                    fail_cb();
                  } else {
                    return false;
                  }
                } else {
                  handle_geolocation_error({
                    browser_support: true,
                    permission_granted: true,
                    google_status: status,
                    google_error_message: error_message,
                  });
                }
  
                //handleLocationError(true);
              }
            }
          );
        },
        () => {
          if (initial_load) {
            if (fail_cb) {
              fail_cb();
            } else {
              return false;
            }
          } else {
            handle_geolocation_error({
              browser_support: true,
              permission_granted: false,
            });
          }
  
          //handleLocationError(true);
        }
      );
    } else {
      // Browser doesn't support Geolocation
      if (initial_load) {
        if (fail_cb !== null) {
          fail_cb();
        } else {
          return false;
        }
      } else {
        handle_geolocation_error({
          browser_support: false,
        });
      }
    }
  }
  function handle_geolocation_error(params, success) {
    let alert_message = null;
    let notification_message = null;
    if (!success) {
      if (params.browser_support) {
        if (params.permission_granted) {
          console.log("Geocoding Error. Geocoder Status Below");
          console.log(params.google_status);
          if (params.google_error_message) {
            alert_message = `Error : ${params.google_error_message}`;
            notification_message = params.google_error_message;
          } else {
            switch (params.google_status) {
              case google.maps.GeocoderStatus.ZERO_RESULTS:
                alert_message =
                  "Error : The Geolocation service could not determine your location.";
                notification_message =
                  "The Geolocation service could not determine your location.";
                break;
              default:
                alert_message = `Error : The Geolocation service has failed.`;
                notification_message = "The Geolocation service has failed.";
            }
          }
        } else {
          alert_message =
            "Error: Please allow the Geolocation service access to your location.";
          notification_message =
            "Geolocation was not allowed access to your location.";
        }
      } else {
        alert_message = "Error: Your browser does not support Geolocation.";
        notification_message = "Your browser does not support Geolocation.";
      }
    }
    if (alert_message) {
      alert(alert_message);
    }
    if (notification_message) {
      //Creat the styling
      if (jQuery("style.get_my_position_notification_css").length == 0) {
        let $style = jQuery(`<style class='get_my_position_notification_css'>
          .get_my_position_notification{
            color:red;
            font-size:13px;
            display:block;
          }
        </style>`);
        jQuery("head").append($style);
      }
      if (jQuery(".get_my_position_notification").length > 0) {
        jQuery(".get_my_position_notification").html(notification_message);
      } else {
        let $notification = jQuery(
          `<span class='get_my_position_notification'>${notification_message}</span>`
        );
        $notification.insertAfter("#get_my_position");
      }
    } else {
      jQuery(".get_my_position_notification").remove();
    }
  }
  function handleLocationError(browserHasGeolocation) {
    if (browserHasGeolocation) {
      alert("Error: The Geolocation service failed.");
    } else {
      alert("Error: Your browser doesn't support geolocation.");
    }
  }
  