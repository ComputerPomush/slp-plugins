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
    //Resize map to fit screen
    jQuery(window).resize(function () {
      resizeMap();
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
   * TIMEOUT }. VALIDATING and REJECTED are declared but unreachable until
   * Step 2 adds Layers 0, 1 and 3.
   * ================================================================== */
  var AVALON_GUARD_TIMEOUT_MS = 12000;

  var AVALON_GUARD_MESSAGES = {
    geocode_failed:
      "We couldn't find that location. Please check the spelling and try again.",
    timeout: "The search is taking longer than expected. Please try again.",
    transport: "We couldn't complete that search. Please try again.",
  };

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
      if (terminal_state === this.RESULTS && this.pending_url) {
        window.history.replaceState(null, "", this.pending_url);
      }
      this.pending_url = null;

      //REJECTED is unreachable until Step 2 but is wired here so the Layer 1
      //and Layer 3 rejections inherit this presentation for free.
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
    },

    /* ---------------------------------------------- notification channel */

    /**
     * Deliberately a separate channel from .get_my_position_notification,
     * which is owned by handle_geolocation_error(): that function removes the
     * node wholesale on its success path and would silently wipe ours.
     * Injected rather than added to style.css because slp_avalon.js is
     * repo-managed and identical on all three sites, while style.css is
     * per-site and diverges.
     */
    ensure_notification_css: function () {
      if (jQuery("style.avalon_search_notification_css").length === 0) {
        jQuery("head").append(
          jQuery(
            "<style class='avalon_search_notification_css'>" +
              ".avalon_search_notification{color:#c00;font-size:13px;" +
              "display:block;margin:0 0 8px;line-height:1.35;}" +
              "</style>"
          )
        );
      }
    },

    notify: function (message) {
      if (!message) return;
      this.ensure_notification_css();
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
      if (sidebar) {
        this.sidebar_default = sidebar.innerHTML;
      }
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
      var sidebar = document.getElementById("map_sidebar");
      if (sidebar && this.sidebar_default !== null) {
        sidebar.innerHTML = this.sidebar_default;
      }
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
      if ($link.length > 0 && $wrapper.length > 0) {
        $link.insertBefore($wrapper);
      }
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
     * Issue 1 Path A, and the seam Step 2 uses for Layer 1.
     *
     * process_geocode_response is an instance property (slp_core.js:1526) and
     * doGeocode resolves it at call time (slp_core.js:1651), so a single
     * assignment here catches the real geocode path AND the coords-spoof path
     * at slp_avalon.js:119, which calls the same property on the instance.
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
          // Step 2 inserts the Layer 1 country check here, before delegating.
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
        return jQuery
          .post(slplus.ajaxurl, action, function (response) {
            try {
              response = JSON.parse(response);
            } catch (ex) {}
            callback(response);
          })
          .fail(function () {
            // send_ajax is shared with slp.option.get_from_server and others.
            // Only a failure during the search leg is a search failure.
            if (guard.state !== guard.SEARCHING) return;
            guard.finish(guard.ERROR, {
              message: AVALON_GUARD_MESSAGES.transport,
              focus_input: guard.user_initiated,
            });
          });
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
      var count =
        response && typeof response.count !== "undefined"
          ? parseInt(response.count, 10)
          : null;
      this.finish(count === 0 ? this.EMPTY : this.RESULTS);
    },
  };

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
  
    let address = avalon_cslmap.saneValue("addressInput", "") + append_this;
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
      let fake_geocode_response = [
        {
          geometry: {
            location: coords,
          },
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
  function resizeMap() {
    let window_height = jQuery(window).height();
    let header_height = jQuery("header#header").outerHeight();
    let new_height = window_height - header_height;
    jQuery("#sl_bottom_right").height(new_height);
    jQuery("#sl_bottom_left").height(new_height);
    jQuery("#sl_bottom_left").css("min-height", new_height + "px");
    jQuery("#sl_bottom_left #results_box").css(
      "max-height",
      new_height - jQuery("#sl_bottom_left #search_box").outerHeight() - 0.01 + "px"
    );
  }
  var boundary_circle = null;
  var markers_list_natural = null;
  //Reset location data when address changes
  jQuery(document).on("change", "#addressInput", function () {
    jQuery(this).data("place_lat", null);
    jQuery(this).data("place_lng", null);
  });
  function initialize_autocomplete() {
    let input = document.getElementById("addressInput");
    if (!input) return;
    let places_autocomplete = new google.maps.places.Autocomplete(input);
    places_autocomplete.addListener("place_changed", function () {
      let selected = places_autocomplete.getPlace();
      if (typeof selected.geometry != "undefined") {
        jQuery(input).data("place_lat", selected.geometry.location.lat());
        jQuery(input).data("place_lng", selected.geometry.location.lng());
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
    //Search by ZIP if search text is only numbers
    // slp_Filter("geocoder_request").subscribe(function (request) {
    //   console.log({ request });
    //   if (request["address"].isNumber()) {
    //     let zip = request["address"];
    //     request["address"] = `${zip}&components=country:RO`;
    //     // request["components"] = `postal_code:${zip}`;
    //   }
    // });
    //Overwrite search function
    slp_Filter("slp_map_ready").subscribe(function (cslmap) {
      avalon_cslmap = cslmap;
      avalon_cslmap.build_map = cslmap_build_map;
      avalon_cslmap.searchLocations = cslmap_searchLocations;
      //Issue 1 Path A + the Step 2 seam for Layer 1.
      avalon_guard.install_geocode_hook(cslmap);
      //Issue 1 Path B. slp.run has already executed by map-ready, so
      //slp.send_ajax is defined and safe to replace here.
      avalon_guard.install_transport_hook();
      //Presentation: runs before the first search response lands.
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
    //  Step 2 can reject synchronously inside searchLocations(), this
    //  handler would switch the spinner back on with nothing left to
    //  switch it off - trading a geocode hang for a validation hang.
    //REMOVED - DOMSubtreeModified on #map_sidebar -> sl_show_loading(false).
    //  Mutation Events were removed in Chrome 135, Edge 137, Firefox 140
    //  and Safari 26, so this stopped firing in every shipping browser.
    //  It was the fallback that used to mask Issue 1 Path A.
  }
  function avalon_slp_on_location_search_responded(response) {
    //Handed to the Guard so on_search_processed() can tell RESULTS from
    //EMPTY. Step 2 reads avalon_territory_rejected off the same payload.
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
                jQuery("#addressInput").val(address);
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
  