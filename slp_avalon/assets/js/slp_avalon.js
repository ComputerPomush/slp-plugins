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
  var avalon_cslmap = null;
  function cslmap_searchLocations() {
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
    window.history.replaceState(null, "", new_url);
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
    //Various hooks for display loading
    slp_Filter("slp_map_ready").subscribe(function (cslmap) {
      sl_show_loading(true);
    });
    slp_Filter("location_search_processed").subscribe(function () {
      sl_show_loading(false);
    });
    jQuery("#searchForm").on("submit", function () {
      sl_show_loading(true);
    });
    jQuery("body").on("DOMSubtreeModified", "#map_sidebar", function () {
      sl_show_loading(false);
    });
  }
  function avalon_slp_on_location_search_responded(response) {
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
            alert_message = `Error : ${google_error_message}`;
            notification_message = google_error_message;
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
  