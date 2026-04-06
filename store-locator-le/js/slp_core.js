/**
 * @name Store Locator Plus® Core
 * @version version 2301.14.01
 * @author Lance Cleveland
 * @preserve
 * @see {@link https://storelocatorplus.com|Store Locator Plus®}
 * @see {@link https://lancecleveland.com/ Lance Cleveland}
 * @fileoverview
 * Part of the Store Locator Plus® main plugin for the WordPress application framework that runs the
 * Store Locator Plus® SaaS map location platform
 */
/**
 * Definitions
 *
 * @typedef slp_Filter        Our jQuery Observer Pub/Sub class goes in here.
 *
 * @typedef WP_Localized_SLPlus
 * @type {object}
 * @property    {object}    options
 * @property    {object}    environment
 * @property    string      plugin_url
 * @property    string      ajaxurl
 * @property    string      nonce
 *
 * @typedef Google
 * @type {object}
 * @property {object} maps
 *
 * @typedef Google.maps
 * @type {object}
 * @property {object} LatLng
 * @property {function} GeocoderRequest
 * @property {function} InfoWindow
 *
 * @typedef Generic_DOM_Things
 * @type {object}
 * @property Location               a                   An anchor tag
 *
 * @typedef Our_SLP_Object
 * @type {object}
 * @property Generic_DOM_Things     generic_object      Generic DOM objects
 * @property {object}               important_object    keep track of jQuery objects we use often
 * @property {object}               topics              our pub_sub topics , they work like WordPress filters
 * @property {slp_LocationServices} sensor              the GPS sensor interface
 *
 * @var     slp_Map                 cslmap              The SLP to Map interface
 * @var     WP_Localized_SLPlus     slplus              The SLP environment passed from WP
 * @var     Our_SLP_Object          slp                 Map manager object
 *
 */

/** jsLint - these are defined by WP localize or at main script invocation */
/* globals jQuery, google, slplus, slp, cslmap */

/**
 * jQuery Observer (pub/sub) class.
 *
 * @param id
 * @returns {*}
 * @constructor
 */
slp_Filter = function (id) {
    let callbacks, topic;

    topic = id && slp.topics[id];

    if (!topic) {
        // Valid jQuery 1.7+
        //
        if (typeof jQuery.Callbacks !== "undefined") {
            callbacks = jQuery.Callbacks();
            topic = {
                publish: callbacks.fire,
                subscribe: callbacks.add,
                unsubscribe: callbacks.remove,
            };

            // No jQuery Callbacks?  Why are you NOT using jQuery 1.7+??
            //
        } else {
            slp.log(
                "jQuery 1.7.0+ required, " + jQuery.fn.version + " used instead."
            );
            topic = {
                publish: function (data) {
                    return data;
                },
            };
        }

        if (id) {
            slp.topics[id] = topic;
        }
    }

    return topic;
};

/**
 * SLP Object.
 * @type {{notification: slp.notification, important_object: {address_input: {}, find_button: {}, slp_div: {}, address_div: {}, map: {}, results: {}, search_form: {}}, log: slp.log, start_map: slp.start_map, topics: {}, sensor: {}, run: slp.run, geocoder: {geocode: slp.geocoder.geocode}, generic_object: {a: HTMLAnchorElement}, option: {get_from_server: slp.option.get_from_server}}}
 */
slp = {
    initMap: function () {
    },
    generic_object: {
        a: document.createElement("a"),
    },
    important_object: {
        address_div: {},
        address_input: {},
        find_button: {},
        map: {},
        results: {},
        search_form: {},
        slp_div: {},
    },
    topics: {},
    sensor: {},

    // -- SLP simple functions
    filter: function (filterID) {
        slp_Filter(filterID);
    },

    /**
     * Initialize the slp methods an properties.
     */
    init: function () {
        if (typeof slplus !== "undefined") {
            if (
                window.location.protocol !==
                slplus.ajaxurl.substring(0, slplus.ajaxurl.indexOf(":") + 1)
            ) {
                slplus.ajaxurl = slplus.ajaxurl.replace(
                    slplus.ajaxurl.substring(0, slplus.ajaxurl.indexOf(":") + 1),
                    window.location.protocol
                );
            }
        }

        // Regular Expression Test Patterns
        //
        const radioCheck = /radio|checkbox/i,
            keyBreaker = /[^\[\]]+/g,
            numberMatcher = /^[\-+]?[0-9]*\.?[0-9]+([eE][\-+]?[0-9]+)?$/;

        /**
         * Is the value a number?
         * @param value
         * @returns {boolean|RegExpMatchArray}
         */
        const isNumber = (value) => {
            if (typeof value === "number") {
                return true;
            }

            if (typeof value !== "string") {
                return false;
            }

            return value.match(numberMatcher);
        };

        // Form Parameters Processor
        //
        jQuery.fn.extend({
            // Get the form parameters
            //
            formParams: function (convert) {
                if (this[0].nodeName.toLowerCase() === 'form' && this[0].elements) {
                    return jQuery(jQuery.makeArray(this[0].elements)).getParams(convert);
                }
                return jQuery(
                    "input[name], textarea[name], select[name]",
                    this[0]
                ).getParams(convert);
            },
            // Get a specific form element
            //
            getParams: function (convert) {
                var data = {},
                    current;

                convert = convert === undefined ? true : convert;

                this.each(function () {
                    var el = this,
                        type = el.type && el.type.toLowerCase();
                    //if we are submit, ignore
                    if (type === 'submit' || !el.name) {
                        return;
                    }

                    var key = el.name,
                        value = jQuery.data(el, "value") || jQuery.fn.val.call([el]),
                        isRadioCheck = radioCheck.test(el.type),
                        parts = key.match(keyBreaker),
                        write = !isRadioCheck || !!el.checked,
                        //make an array of values
                        lastPart;

                    if (convert) {
                        if (isNumber(value)) {
                            value = parseFloat(value);
                        } else if (value === "true" || value === "false") {
                            value = Boolean(value);
                        }
                    }

                    // go through and create nested objects
                    current = data;
                    for (var i = 0; i < parts.length - 1; i++) {
                        if (!current[parts[i]]) {
                            current[parts[i]] = {};
                        }
                        current = current[parts[i]];
                    }
                    lastPart = parts[parts.length - 1];

                    //now we are on the last part, set the value
                    if (lastPart in current && type === "checkbox") {
                        if (!Array.isArray(current[lastPart])) {
                            current[lastPart] =
                                current[lastPart] === undefined ? [] : [current[lastPart]];
                        }
                        if (write) {
                            current[lastPart].push(value);
                        }
                    } else if (write || !current[lastPart]) {
                        current[lastPart] = write ? value : undefined;
                    }
                });
                return data;
            },
        });
    },

    /**
     * Run the JS locator.
     */
    run: function () {
        slp_Filter('ready').publish(this);
        slp.init();
        slp.start_map();
    },

    /**
     * Log a message if the console window is active.
     *
     * @param message
     */
    log: function (message) {
        if (window.console) {
            console.log(message);
        }
    },

    /**
     * SLP Notifications
     */
    notification: function () {
        /**
         * Get HTML for inline message box. (Vuetify style)
         *
         * @param header
         * @param message
         * @returns {string}
         */
        this.inline = function (header, message) {
            return '<div class="container fluid grid-list-lg" style="min-height: 0px;">' +
                '<div class="layout row wrap">' +
                '<div class="flex xs12">' +
                '<div class="white--text card blue-grey darken-2" data-ripple="false" style="height: auto;">' +
                '<div class="card__title card__title--primary">' +
                '<div class="headline">' +
                header +
                "</div>" +
                "<div>" +
                message +
                "</div>" +
                "</div>" +
                "</div>" +
                "</div>" +
                "</div>" +
                "</div>";
        };
    },

    /**
     * Set various functions and methods to help manage the UX.
     *
     * @returns {undefined}
     */
    setup_helpers: function () {
        // Add pointers to objects we want to reference frequently.
        //
        slp.important_object = {
            address_row: jQuery("#addy_in_address"),
            address_input: jQuery("#addressInput"),
            map: jQuery("#map"),
            results: jQuery("#map_sidebar"),
            search_form: jQuery("#searchForm"),
            slp_div: jQuery("div.store_locator_plus"),
        };

        /**
         * Replace shortcodes in a string with current marker data as appropriate.
         *
         * The "new form" shortcode placeholders.
         *
         * Shortcode format:
         *    [<shortcode> <attribute> <modifier> <modifier argument>]
         *
         *    [slp_location <field_slug> <modifier>]
         *
         * Marker data is expected to be passed in the first argument as an object.
         *
         * @returns {string}
         */
        String.prototype.replace_shortcodes = function () {
            var args = arguments;
            var currentLocation = args[0];
            var shortcode_complex_regex = /\[(\w+)\s+([\w\.]+)\s*(\w*)(?:[\s="]*)([\w|\.]*)(?:[\s"]*)\s*(\w*)(?:[\s="]*)([\w|\.]*)(?:[\s"]*)\]/g;

            return this.replace(
                shortcode_complex_regex,
                function (
                    match,
                    shortcode,
                    attribute,
                    modifier,
                    modarg,
                    modifier2,
                    modarg2
                ) {
                    let
                        field_name = '',
                        value = '',
                        output = '';

                    switch (shortcode) {
                        // SHORTCODE: slp_location
                        // processes the location data
                        //
                        case "slp_location":
                            if (attribute === "latitude") {
                                attribute = "lat";
                            }
                            if (attribute === "longitude") {
                                attribute = "lng";
                            }

                            value = "";
                            field_name = "";

                            // Normal Marker data
                            if (currentLocation[attribute]) {
                                value = currentLocation[attribute];
                                field_name = attribute;

                                // Dot notation (array) attribute (marker data)
                                //
                            } else if (attribute.indexOf(".") > 1) {
                                var dot_notation_regex = /(\w+)/gi;
                                var data_parts = attribute.match(dot_notation_regex);
                                var marker_array_name = data_parts[0];
                                if (!currentLocation[marker_array_name]) {
                                    return "";
                                }
                                var marker_array_field = data_parts[1];
                                value = currentLocation[marker_array_name][marker_array_field];
                                if (!value) {
                                    return "";
                                }
                                field_name = marker_array_name + "_" + marker_array_field;

                                // Output NOTHING if attribute is empty
                                //
                            } else {
                                return "";
                            }

                            output = value.shortcode_modifier({
                                modifier: modifier,
                                modarg: modarg,
                                field_name: field_name,
                                marker: currentLocation,
                            });
                            if (modifier2) {
                                output = output.shortcode_modifier({
                                    modifier: modifier2,
                                    modarg: modarg2,
                                    field_name: field_name,
                                    marker: currentLocation,
                                });
                            }
                            return output;

                        // SHORTCODE: slp_option - processes the option settings
                        //
                        case "slp_option":
                            /**
                             * Change the option set for shortcodes.
                             *
                             * @filter  replace_shortcodes_options
                             *
                             * @param   object   options     slplus options as passed in
                             * @return  object               options modifications
                             */
                            var options = slplus.options;
                            slp_Filter("replace_shortcodes_options").publish(options);
                            if (attribute === "name") {
                                attribute = modarg;
                                modarg = "";
                            }

                            if (!options.hasOwnProperty(attribute)) {
                                jQuery.ajax({
                                    url: slplus.rest_url + "options/" + attribute,
                                    dataType: "json",
                                    success: function (json_data) {
                                        var response;

                                        if (typeof json_data === "string") {
                                            response = jQuery.parseJSON(json_data);
                                        } else {
                                            response = json_data;
                                        }
                                        options[attribute] = response.value;
                                        output = slp.shortcode_output(
                                            currentLocation,
                                            field_name,
                                            options[attribute],
                                            modifier,
                                            modarg,
                                            modifier2,
                                            modarg2
                                        );
                                        jQuery(
                                            'span[data-option="' +
                                            attribute +
                                            '"][data-locationid="' +
                                            currentLocation.id +
                                            '"]'
                                        )
                                            .html(output)
                                            .show();
                                    },
                                    error: function () {
                                        return '';
                                    },
                                });

                                return (
                                    '<span data-option="' +
                                    attribute +
                                    '" data-locationid="' +
                                    currentLocation.id +
                                    '"></span>'
                                );

                                // If the option already exists, process it...
                            } else {
                                return slp.shortcode_output(
                                    currentLocation,
                                    field_name,
                                    options[attribute],
                                    modifier,
                                    modarg,
                                    modifier2,
                                    modarg2
                                );
                            }

                        // SHORTCODE: HTML
                        //
                        case "html":
                            output = '';
                            switch (attribute) {
                                case "br":
                                    output = "<br/>";
                                    break;
                                case "closing_anchor":
                                    output = "</a>";
                                    break;
                                default:
                                    break;
                            }
                            output = output.shortcode_modifier({
                                modifier: modifier,
                                modarg: modarg,
                                field_name: "raw_html",
                                marker: currentLocation,
                            });
                            if (modifier2) {
                                output = output.shortcode_modifier({
                                    modifier: modifier2,
                                    modarg: modarg2,
                                    field_name: "raw_html",
                                    marker: currentLocation,
                                });
                            }
                            return output;

                        // SLP_ADDON gets stripped out
                        case "slp_addon":
                            return '';

                        // Unknown Shortcode
                        //
                        default:
                            return match + " not supported";
                    }
                }
            );
        };

        /**
         * Modify shortcodes.
         *
         * @returns string
         */
        String.prototype.shortcode_modifier = function () {
            var args = arguments;
            var full_mod = args[0];

            var modifier = full_mod.modifier; // i.e. : ifset
            var modarg = full_mod.modarg; // i.e. : phone
            var field_name = full_mod.field_name;

            var raw_output = true;

            var get_marker_data = function (element, marker) {
                var split_element = element.split(".");
                if (split_element.length === 1) {
                    if (marker[element]) return marker[element];
                    return marker.data[element];
                } else {
                    return marker.data[split_element[1]];
                }
            };

            if (field_name === "hours") {
                raw_output = false;
            }

            var value = this;

            var prefix = "";
            var suffix = "";

            // Modifier Processing
            //
            if (modifier) {
                switch (modifier) {
                    // MODIFIER: ifset
                    // if the marker attribute specified by modarg is empty, don't output
                    // anything.
                    case "ifset":
                        if (!get_marker_data(modarg, full_mod.marker)) {
                            return "";
                        }
                        break;

                    // MODIFIER: ifnotset
                    // if the marker attribute specified by modarg is empty, don't output
                    // anything.
                    case "ifnotset":
                        if (get_marker_data(modarg, full_mod.marker)) {
                            return "";
                        }
                        break;

                    // MODIFIER: suffix
                    //
                    case "suffix":
                        switch (modarg) {
                            case "br":
                                suffix = "<br/>";
                                break;
                            case "comma":
                                suffix = ",";
                                break;
                            case "comma_space":
                                suffix = ", ";
                                break;
                            case "space":
                                suffix = " ";
                                break;
                            default:
                                break;
                        }
                        break;

                    // MODIFIER: wrap
                    //
                    case "wrap":
                        switch (modarg) {
                            case "directions":
                                prefix =
                                    '<a href="https://' +
                                    slplus.options.map_domain +
                                    "/maps?saddr=" +
                                    encodeURIComponent(cslmap.getSearchAddress(cslmap.address)) +
                                    "&daddr=" +
                                    encodeURIComponent(full_mod.marker["fullAddress"]) +
                                    '" target="_blank" class="storelocatorlink">';
                                suffix = "</a> ";
                                break;

                            case "img":
                                prefix = '<img src="';
                                suffix = '" class="sl_info_bubble_main_image">';
                                break;

                            case "mailto":
                                prefix = '<a href="mailto:';
                                suffix = '" target="_blank" class="storelocatorlink">';
                                break;

                            case "website":
                                prefix = '<a href="';
                                suffix =
                                    '" ' +
                                    'target="' +
                                    (slplus.options.use_same_window !== "0"
                                        ? "_self"
                                        : "_blank") +
                                    '" ' +
                                    'id="slp_marker_website" ' +
                                    'class="storelocatorlink" ' +
                                    ">";
                                break;

                            case "fullspan":
                                prefix =
                                    '<span class="results_line location_' + field_name + '">';
                                suffix = "</span>";
                                break;

                            default:
                                break;
                        }
                        break;

                    // MODIFIER: format
                    //
                    case "format":
                        switch (modarg) {
                            case "decimal1":
                                value = parseFloat(value).toFixed(1);
                                break;
                            case "decimal2":
                                value = parseFloat(value).toFixed(2);
                                break;
                            case "sanitize":
                                value = value.replace(/\W/g, "_");
                                break;
                            case "text":
                                value = jQuery("<div/>").html(value).text();
                                break;
                            default:
                                break;
                        }
                        break;

                    // MODIFIER: raw
                    //
                    case "raw":
                        raw_output = true;
                        break;

                    // MODIFIER: Unknown, do nothing
                    //
                    default:
                        break;
                }
            }

            var newOutput = raw_output ? value : jQuery("<div/>").html(value).text();

            return prefix + newOutput + suffix;
        };
    },

    /**
     * Setup the map settings and get it rendered.
     *
     * @returns {undefined}
     */
    setup_map: function () {
        cslmap = new slp_Map();
        slp_Filter('slp_map_ready').publish(cslmap);
        if (!slplus.options.use_sensor) {
            cslmap.usingSensor = false;

            // If the page uses [slplus id="<location_id>"]
            // use that location ID lat/long as the center of the map.
            //
            // TODO: since this is already a lat/long, this should call LoadMarkers()
            // directly and bypass the GeoCoder.
            if (slplus.options.id_addr != null) {
                cslmap.address = slplus.options.id_addr;
            }

            // If the address is blank, use the center map at address.
            //
            if (!cslmap.address && slplus.options.map_center) {
                cslmap.address = slplus.options.map_center;
            }

            // Map Center Lat/Lng Is Set...
            //
            if (slplus.options.map_center_lat && slplus.options.map_center_lng) {
                if (typeof cslmap.build_map === "function") {
                    var map_center = new google.maps.LatLng(
                        slplus.options.map_center_lat,
                        slplus.options.map_center_lng
                    );
                    cslmap.build_map(map_center);
                }

                // Map Center Lat/Lng empty -- go find it from Google
            } else {
                cslmap.doGeocode();
            }
        }
    },

    /**
     * Generate shortcode output.
     *
     * @param currentLocation
     * @param field_name
     * @param value
     * @param modifier
     * @param modarg
     * @param modifier2
     * @param modarg2
     * @returns {string}
     */
    shortcode_output: function (
        currentLocation,
        field_name,
        value,
        modifier,
        modarg,
        modifier2,
        modarg2
    ) {
        var output;

        output = value.shortcode_modifier({
            modifier: modifier,
            modarg: modarg,
            field_name: field_name,
            marker: currentLocation,
        });
        if (modifier2) {
            output = output.shortcode_modifier({
                modifier: modifier2,
                modarg: modarg2,
                field_name: field_name,
                marker: currentLocation,
            });
        }
        return output;
    },

    /**
     * Start the map.
     */
    start_map: function () {
        if (jQuery("#map").length) {
            if (typeof slplus !== "undefined") {
                if (typeof google !== "undefined") {
                    slp.setup_helpers();
                    slp.setup_map();
                    slp.subscriptions.initalize();
                    slplus.notifications = new slp.notification();
                } else {
                    jQuery("#map").html(
                        "Looks like you turned off Store Locator Plus® Maps under General Settings but need them here."
                    );
                }
            } else {
                jQuery("#map").html("Store Locator Plus® did not initialize properly.");
            }
        }
    },

    subscriptions: {},

    // -- Complex objects with properties and methods

    /**
     * Geocoder
     *
     * We support address, region, and bounds as valid request properties
     */
    geocoder: {
        geocode: function (request, callback) {
            jQuery
                .getJSON(
                    encodeURI(
                        slplus.rest_url +
                        "geocode/" +
                        slplus.apikey +
                        "/" +
                        request.region +
                        "/" +
                        request.address
                    )
                )
                .done(function (resp) {
                    if (resp.error_message) {
                        var ref_phrase = ", with referer";
                        if (resp.error_message.includes(ref_phrase)) {
                            resp.error_message = resp.error_message.substring(
                                0,
                                resp.error_message.indexOf(ref_phrase)
                            );
                        }
                    } else {
                        resp.error_message = "";
                    }
                    callback(resp.results, resp.status, resp.error_message);
                })
                .fail(function (resp) {
                    callback(resp, google.maps.GeocoderStatus.ERROR);
                });
        },
    },

    /**
     * OPTIONS interface
     */
    option: {
        get_from_server: function (option_name, callback) {
            // Shortcode attribute override
            if (typeof slplus.shortcode_attributes[option_name] !== "undefined") {
                callback({value: slplus.shortcode_attributes[option_name]});
                return;
            }

            // Fetch from server
            jQuery
                .getJSON(encodeURI(slplus.rest_url + "options/filtered/" + option_name))
                .done(function (resp) {
                    if (resp.error_message) {
                        console.log(resp.error_message);
                    } else {
                        resp.error_message = "";
                    }
                    callback(resp);
                })
                .fail(function () {
                    console.log("get_from_server failed");
                });
        },
    },
};

/**
 * Setup pub/sub subscriptions for ourselves.
 */
slp.subscriptions = (function () {
    var loading_class = "loading_locations";

    /**
     * Initialize
     */
    this.initialize = function () {
        slp_Filter("load_marker_action").subscribe(show_locations_processing);
        slp_Filter("location_search_responded").subscribe(
            remove_locations_processing
        );
    };

    /**
     * Add loading class to address input box and find button.
     */
    this.show_locations_processing = function () {
        slp.important_object.slp_div.addClass(loading_class);
    };

    /**
     * Remove loading class to address input box and find button.
     */
    this.remove_locations_processing = function () {
        slp.important_object.slp_div.removeClass(loading_class);
    };

    /**
     * Public methods and properties.
     */
    return {
        initalize: initialize,
    };
})();

/**
 * Catch all Google Maps Authentication Failures
 */
function gm_authFailure() {
    const html = slplus.notifications.inline(
        slplus.messages.slp_system_message,
        slplus.messages.no_api_key,
    );
    jQuery("#map").prepend(html);
}

/**
 * @class Creates a SLP-managed Google Map Marker.
 *
 * @param {google.maps.Map}     map               the slp_Map type to put it on
 * @param {string}              title             the title of the marker for mouse over
 * @param {google.maps.LatLng}  position          the lat/long to put the marker at
 * @param {string}              markerImage       markerImage todo: load a custom icon, null for default
 * @param {int}                 location_id       location_id
 */
slp_Marker = function (map, title, position, markerImage, location_id) {
    this.__map = map;
    this.__title = title;
    this.__position = position;
    this.__gmarker = null;
    this.__markerImage = markerImage;
    this.__location_id = location_id;

    /**
     * Constructor.
     */
    this.__init = function () {
        /**
         * Change the marker
         *
         * @filter  marker
         *
         * @param   object   this     The current slp_Marker object from JavaScript.
         * @return  object            A modified slp_Marker object
         */
        slp_Filter("marker").publish(this);

        this.__gmarker = new google.maps.Marker({
            position: this.__position,
            map: this.__map.gmap,
            title: this.__title,
            icon: this.protocol_tweaker(this.__markerImage),
        });
    };

    /**
     * If the icon_url is on the same host force the protocol to match our window.
     *
     * @param icon_url
     * @returns string
     */
    this.protocol_tweaker = function (icon_url) {
        slp.generic_object.a.href = icon_url;
        if (slp.generic_object.a.hostname !== window.location.hostname)
            return icon_url;
        slp.generic_object.a.protocol = window.location.protocol;
        return slp.generic_object.a.href;
    };

    this.__init();
};

/**
 * @class   Manages the Google Map interface.
 *
 * @property  {boolean}                    auto_bound          Automatically bound and zoom the location list coming back from the server (default : true)
 *
 * @property  {google.maps.Map}            gmap
 * @property  {google.maps.GeocoderRequest} geocoder_request
 * @property  {google.maps.LatLng}        homePoint           The user-entered home point.
 * @property  {google.maps.InfoWindow}    infoWindow
 *
 * @property  {object}                    options            Options passed along to {google.maps.Map}
 * @property  {google.maps.LatLng}        options.center     The map center point.
 */
slp_Map = function () {
    const _this = this;

    this.auto_bound = true;

    this.infowindow = new google.maps.InfoWindow();
    this.geocoder = slp.geocoder;
    this.geocoder_request = {};
    this.gmap = null;

    // other variables
    this.map_hidden = true; // map div may be hidden at first, assume it was
    this.default_radius = "40000"; // default radius if not set

    //php passed vars set in init
    this.address = null;
    this.draggable = true;
    this.markers = null;

    //slplus options
    this.usingSensor = false;
    this.mapHomeIconUrl = null;
    this.mapType = null;

    //gmap set variables
    this.options = null;
    this.centerMarker = null;
    this.active_marker = null; // the active marker
    this.marker = null;
    this.bounds = null;
    this.homePoint = null;
    this.lastCenter = null;
    this.lastRadius = null;

    // AJAX communication
    this.latest_response = null;
    this.search_options = null;

    /**
     * Auto-run when this class is invoked as an object.
     *
     * If slplus is defined...
     * Set mapType, mapHomeIconURL, and addressInput properties.
     * Add the domready listener to the maps.
     */
    this.__init = function () {
        // Missing critical slplus info, leave.
        //
        if (typeof slplus === "undefined") {
            var html = slplus.notifications.inline(
                slplus.messages.slp_system_message,
                slplus.messages.script_not_loaded
            );
            jQuery(".store_locator_plus").prepend(html);
            return;
        }

        this.mapType = slplus.options.map_type;
        this.mapHomeIconUrl = slplus.options.map_home_icon;

        // Setup address - use the entry form value if set, otherwise use the country
        //
        var addressInput = this.getSearchAddress();
        if (typeof addressInput === "undefined") {
            this.address = slplus.options.map_center;
        } else {
            this.address = addressInput;
        }

        // When the Google Map domready fires, add a bunch of class to the info bubbles it builds.
        google.maps.event.addListener(this.infowindow, "domready", function () {
            jQuery(".slp_info_bubble").parent().addClass("slp_bubble_level_1");
            jQuery(".slp_info_bubble")
                .parent()
                .parent()
                .addClass("slp_bubble_level_2");

            jQuery(".slp_info_bubble")
                .parent()
                .parent()
                .parent()
                .addClass("slp_bubble_level_3");

            jQuery(".slp_info_bubble")
                .parent()
                .parent()
                .parent()
                .parent()
                .addClass("slp_bubble_level_4");
            jQuery(".slp_bubble_level_4")
                .children("div:first-child")
                .addClass("slp_bubble_elements");
            jQuery(".slp_bubble_level_4")
                .children("div:last-child")
                .addClass("slp_bubble_closer");
            jQuery(".slp_bubble_elements")
                .children("div:first-child")
                .addClass("slp_bubble_tail_behind");
            jQuery(".slp_bubble_elements")
                .children("div:nth-child(2)")
                .addClass("slp_bubble_behind");
            jQuery(".slp_bubble_elements")
                .children("div:nth-child(3)")
                .addClass("slp_bubble_tail_pieces");
            jQuery(".slp_bubble_tail_pieces")
                .children("div:first-child")
                .addClass("slp_bubble_left_tail");
            jQuery(".slp_bubble_left_tail")
                .children("div:first-child")
                .addClass("slp_bubble_left_tail_back");
            jQuery(".slp_bubble_tail_pieces")
                .children("div:last-child")
                .addClass("slp_bubble_right_tail");
            jQuery(".slp_bubble_right_tail")
                .children("div:first-child")
                .addClass("slp_bubble_right_tail_back");
            jQuery(".slp_bubble_elements")
                .children("div:nth-child(4)")
                .addClass("slp_bubble_background");

            jQuery(".slp_info_bubble")
                .parent()
                .parent()
                .parent()
                .parent()
                .parent()
                .addClass("slp_bubble_level_5");
            jQuery(".slp_info_bubble")
                .parent()
                .parent()
                .parent()
                .parent()
                .parent()
                .parent()
                .addClass("slp_bubble_level_6");
        });
    };

    /**
     * Builds the map with the specified center
     *
     * @method build_map
     *
     * @param {google.maps.LatLng}  center          the lat/long for the center of the map
     * @param map_div_id
     **/
    if (
        typeof slplus.options.hide_map === "undefined" ||
        slplus.options.hide_map !== "1"
    ) {
        this.build_map = function (center, map_div_id) {
            if (!map_div_id) {
                map_div_id = document.getElementById("map");
            }
            if (this.gmap !== null) {
                return;
            }

            this.options = {
                center: center,
                mapTypeId: this.mapType,
                minZoom: 1,
                zoom: parseInt(slplus.options.zoom_level),
            };
            if (slplus.options.google_map_style) {
                jQuery.extend(this.options, {
                    styles: JSON.parse(slplus.options.google_map_style),
                });
            }

            /**
             * Manipulate the Google map options.
             *
             * @filter  map_options
             *
             * @param   {object}   this.options   The current map options for google.maps.Map
             * @return  {object}                  Modified map options.
             */
            slp_Filter("map_options").publish(this.options);

            this.gmap = new google.maps.Map(map_div_id, this.options);

            google.maps.event.addListener(this.gmap, "bounds_changed", function () {
                _this.__waitForTileLoad.call(_this);
            });

            // Location Sensor Is Enabled
            // Or immediate mode and home marker is enabled
            //
            if (this.show_home_marker()) {
                this.homePoint = center; // Set the home marker location to center
                // lat/long sent in to build_map
                this.addMarkerAtCenter();
            }

            // If immediately show locations is enabled.
            //
            if (slplus.options.immediately_show_locations !== "0") {
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
                this.search_options = null;
                this.load_markers(center, radius);
            }

            /**
             * Map has been built trigger.
             *
             * @filter  map_built
             */
            slp_Filter("map_built").publish();
        };
    }

    /**
     * Set the active marker to the specified location ID.
     *
     * @param location_id
     */
    this.set_active_marker_to_location = function (location_id) {
        jQuery.each(this.markers, function (index, marker) {
            if (parseInt(marker.__location_id, 10) === parseInt(location_id, 10)) {
                cslmap.active_marker = marker;
                return false;
            }
        });
    };

    /**
     * Should I show the home marker on the map or not?
     *
     * @returns {boolean|*}
     */
    this.show_home_marker = function () {
        return (
            this.usingSensor ||
            (slplus.options.immediately_show_locations !== "0" &&
                slplus.options.no_homeicon_at_start !== "1")
        );
    };

    /**
     * Notifies as the map changes that we'd like to be notified when the tiles
     * are completely loaded parameters: none returns: none
     */
    this.__waitForTileLoad = function () {
        if (this.__tilesLoaded === null) {
            this.__tilesLoaded = google.maps.event.addListener(
                this.gmap,
                "tilesloaded",
                function () {
                    _this.__tilesAreLoaded.call(_this);
                }
            );
        }
    };

    /**
     * All the tiles are loaded, so fix their css
     * parameters:
     *    none
     * returns: none
     */
    this.__tilesAreLoaded = function () {
        jQuery("#map").find("img").css({"max-width": "none"});
        google.maps.event.removeListener(this.__tilesLoaded);
        this.__tilesLoaded = null;
    };

    /**
     * Puts a marker at the map center
     * parameters:
     *    none
     * returns: none
     */
    this.addMarkerAtCenter = function () {
        if (this.centerMarker) {
            this.centerMarker.__gmarker.setMap(null);
        }
        if (this.homePoint) {
            this.centerMarker = new slp_Marker(
                this,
                "",
                this.homePoint,
                this.mapHomeIconUrl,
                0
            );
        }
    };

    /**
     * Clears all the markers from the map
     */
    this.clearMarkers = function () {
        if (this.markers) {
            for (markerNumber in this.markers) {
                if (typeof this.markers[markerNumber] !== "undefined") {
                    if (typeof this.markers[markerNumber].__gmarker !== "undefined") {
                        this.markers[markerNumber].__gmarker.setMap(null);
                    }
                }
            }
            this.markers.length = 0;

            // Clear the home marker if the address is blank
            // only if we are not on the first map drawing
            //
            if (!this.saneValue("addressInput", "")) {
                this.centerMarker = null;
                this.homePoint = null;
            }
        }
    };

    /**
     * Puts an array of markers on the map
     * parameters:
     *        markerList:
     *            a list of slp_Markers
     */
    this.putMarkers = function (markerListNatural) {
        var location_data, markerCount, sidebar;

        // Reset map marker list and the results output HTML
        //
        this.markers = [];
        sidebar = document.getElementById("map_sidebar");
        sidebar.innerHTML = "";

        // No Results
        //
        markerCount = markerListNatural ? markerListNatural.length : 0;
        if (markerCount === 0) {
            if (this.homePoint && this.auto_bound) {
                this.gmap.panTo(this.homePoint);
            }

            // Get the no results message from the server.
            slp.option.get_from_server("message_no_results", function (response) {
                if (response.value) {
                    document.getElementById("map_sidebar").innerHTML =
                        '<div class="no_results_found"><h2>' +
                        response.value +
                        "</h2></div>";
                } else {
                    console.log("No results found.  No message set.");
                }
            });

            // Results Processing
            //
        } else {
            var results_html = {
                content: "",
                insert_rows_at: "#map_sidebar",
            };

            /**
             * Add a header to the location results.
             *
             * @filter  location_results_header
             *
             * @param   string   results_html   The results HTML string.
             * @return  object                  A modified results HTML string.
             */
            slp_Filter("location_results_header").publish(results_html);
            if (results_html.content.trim()) {
                slp.important_object.results.append(results_html.content);
            }

            // Set the initial bounds to default (1,180)/(-1,180), include home
            // marker if shown.
            var bounds = new google.maps.LatLngBounds();
            if (this.homePoint) {
                bounds.extend(this.homePoint);
            }

            for (var markerNumber = 0; markerNumber < markerCount; ++markerNumber) {
                if (markerListNatural[markerNumber] == null) {
                    slp.log("SLP marker number " + markerNumber + " is null. ");
                    continue;
                }

                // Use response data elements (native field names) -- SLP AJAX response style
                //
                if (typeof markerListNatural[markerNumber].data !== "undefined") {
                    location_data = markerListNatural[markerNumber].data;

                    // Use direct elements as they ARE using native field names -- mySLP REST response style
                } else {
                    location_data = markerListNatural[markerNumber];
                }

                if (!markerListNatural[markerNumber].position) {
                    markerListNatural[markerNumber].position = new google.maps.LatLng(
                        markerListNatural[markerNumber].lat,
                        markerListNatural[markerNumber].lng
                    );
                }
                bounds.extend(markerListNatural[markerNumber].position);

                this.markers.push(
                    new slp_Marker(
                        this,
                        location_data.sl_store,
                        markerListNatural[markerNumber].position,
                        _this.is_valid_icon(markerListNatural[markerNumber].icon)
                            ? markerListNatural[markerNumber].icon
                            : slplus.options.map_end_icon,
                        location_data.sl_id
                    )
                );

                // Marker Click Action - Show Info Window
                //
                google.maps.event.addListener(
                    this.markers[markerNumber].__gmarker,
                    "click",
                    (function (infoData, marker) {
                        return function () {
                            _this.show_map_bubble.call(_this, infoData, marker);
                        };
                    })(markerListNatural[markerNumber], this.markers[markerNumber])
                );

                //create a sidebar entry
                //
                if (sidebar) {
                    jQuery(results_html.insert_rows_at).append(
                        this.createSidebar(markerListNatural[markerNumber])
                    );

                    // Hide empty spans
                    //
                    jQuery(results_html.insert_rows_at + " span:empty").hide();

                    // Whenever the location result entry is <clicked> do this...
                    //
                    jQuery(
                        "#slp_results_wrapper_" + markerListNatural[markerNumber].id
                    ).on(
                        "click",
                        null,
                        {
                            info: markerListNatural[markerNumber],
                            marker: this.markers[markerNumber],
                        },
                        _this.handle_location_result_click
                    );
                }
            }

            // Get AlL Markers
            //
            if (this.gmap) {
                this.bounds = bounds;
                if (this.auto_bound) {
                    this.gmap.fitBounds(this.bounds);

                    // Do not auto-zoom, use the setting in WP Ux
                    //
                    if (slplus.options.no_autozoom === "1") {
                        this.gmap.setZoom(parseInt(slplus.options.zoom_level));

                        // Autozoom
                        // Finds the current zoom level for the map.  Adjust by the zoom_tweak
                        // setting.
                    } else {
                        var current_zoom = this.gmap.getZoom();
                        var zoom_tweak = parseInt(slplus.options.zoom_tweak);
                        var new_zoom = current_zoom - zoom_tweak;
                        if (markerCount < 2) {
                            new_zoom = Math.min(new_zoom, 15);
                        } // 1 Marker don't zoom in closer than 15.
                        this.gmap.setZoom(new_zoom);
                    }
                }
            }
        }

        // Fire results output changed trigger
        //
        slp.important_object.results.trigger("contentchanged");
    };

    /**
     * Handle location results clicks.
     */
    this.handle_location_result_click = function (event) {
        jQuery(this).trigger("result_clicked", event);
        _this.show_map_bubble(event.data.info, event.data.marker);
    };

    /**
     * Return true if icon is valid.
     * @param icon
     * @returns boolean
     */
    this.is_valid_icon = function (icon) {
        return icon !== null && typeof icon !== "undefined" && icon.length > 4;
    };

    /**
     * Show the map info bubble for the marker.
     *
     * @param infoData  the information to build the info window from (ajax
     *   result)
     * @param marker    the marker on the map where the bubble will be anchored
     */
    this.show_map_bubble = function (infoData, marker) {
        this.options = {
            show_bubble: slplus.options.hide_bubble !== "1",
        };

        /**
         * Manipulate the Google map options and SLP info bubble option.
         *
         * @filter  map_options
         *
         * @param   object   this.options   A google.maps info bubble option array
         * @return  object                  A modified option array.
         */
        slp_Filter("map_options").publish(this.options);

        if (this.options.show_bubble) {
            this.infowindow.setContent(this.createMarkerContent(infoData));
            this.infowindow.open(this.gmap, marker.__gmarker);
        }
    };

    /**
     * Process geocode results if OK.
     * @param results
     * @param status
     * @param message
     */
    this.process_geocode_response = function (results, status, message) {
        if (status === google.maps.GeocoderStatus.OK && results.length > 0) {
            var geocode_results = {
                all: results,
                request: _this.geocoder_request,
                best: results[0],
            };

            /**
             * Manipulate the returned Google address guesses and best result.
             *
             * @filter  geocode_results
             *
             * @param   object   geocoder_results   The return array from a geocoder.geocode request.
             * @return  object                      A modified result.
             */
            slp_Filter("geocode_results").publish(geocode_results);

            if (
                typeof geocode_results.best.geometry.location !== google.maps.LatLng
            ) {
                geocode_results.best.geometry.location = new google.maps.LatLng(
                    geocode_results.best.geometry.location.lat,
                    geocode_results.best.geometry.location.lng
                );
            }

            // if the map hasn't been created, then create one
            //
            if (_this.gmap === null) {
                if (typeof _this.build_map === "function") {
                    _this.build_map(geocode_results.best.geometry.location);
                }
            }

                // the map has been created so shift the center of the map
            //
            else {
                //move the center of the map
                _this.homePoint = geocode_results.best.geometry.location;
                _this.addMarkerAtCenter();

                //do a search based on settings
                _this.load_markers(
                    geocode_results.best.geometry.location,
                    _this.saneValue("radiusSelect", "40000")
                );
            }

            // Geocoder Results Failed
            // If no map, create one centered at the Fallback lat/long noted in
            // the admin settings.
        } else {
            // Map not present -- go to fallback center map at
            if (_this.gmap === null) {
                if (typeof _this.build_map === "function") {
                    slp.log(
                        "Map set to fallback lat/lng: " +
                        slplus.options.map_center_lat +
                        " , " +
                        slplus.options.map_center_lng
                    );

                    _this.homePoint = new google.maps.LatLng(
                        slplus.options.map_center_lat,
                        slplus.options.map_center_lng
                    );

                    _this.build_map(_this.homePoint);
                    _this.addMarkerAtCenter();
                }

                // Map present -- show error in results
            } else {
                slp.option.get_from_server("message_bad_address", function (response) {
                    if (response.value) {
                        document.getElementById("map_sidebar").innerHTML =
                            response.value +
                            "<br/>" +
                            '<p class="geocode_message">' +
                            "Google Maps API error: <br/>" +
                            message +
                            "<br/>" +
                            'Tired of fighting the Google Maps API?  Try <a href="https://storelocatorplus.com/">our SaaS offering</a>.';
                        ("</p>");
                    } else {
                        console.log("Bad address.  No message set.");
                    }
                });
                _this.clearMarkers();
            }
        }
    };

    /**
     * Geocode an address on the search input field and display on map.
     */
    this.doGeocode = function () {
        _this.geocoder_request["address"] = _this.address;
        _this.geocoder_request["region"] =
            typeof slplus.options.map_region !== "undefined" &&
            slplus.options.map_region
                ? slplus.options.map_region
                : "us";

        // TODO: EXP move this into the observer pattern
        //
        if (slplus.options.searchnear === "currentmap") {
            if (_this.gmap) {
                _this.geocoder_request["bounds"] = _this.gmap.getBounds();
            }
        }

        /**
         * Modifies the geocoder_request object via jQuery pub/sub model.
         *
         * @filter  geocoder_request
         *
         * @param   object   geocoder_request   A google.geocoder.geocode option array.
         * @return  object                      A modified option array.
         */
        slp_Filter("geocoder_request").publish(_this.geocoder_request);

        _this.geocoder.geocode(
            _this.geocoder_request,
            _this.process_geocode_response
        );
    };

    /**
     * Build a formatted address string
     * parameters:
     *        aMarker:
     *            the ajax result to build the information from
     * returns: a formatted address string
     */
    this.__createAddress = function (aMarker) {
        var address = "";
        if (aMarker.address !== "") {
            address += aMarker.address;
        }

        if (aMarker.address2 !== "") {
            address += ", " + aMarker.address2;
        }

        if (aMarker.city !== "") {
            address += ", " + aMarker.city;
        }

        if (aMarker.state !== "") {
            address += ", " + aMarker.state;
        }

        if (aMarker.zip !== "") {
            address += ", " + aMarker.zip;
        }

        if (aMarker.country !== "") {
            address += ", " + aMarker.country;
        }

        return address;
    };

    /**
     * Create the info bubble for a map location.
     *
     * @param thisMarker - a map marker object.
     */
    this.createMarkerContent = function (thisMarker) {
        thisMarker["fullAddress"] = this.__createAddress(thisMarker);
        return slplus.options.bubblelayout.replace_shortcodes(thisMarker);
    };

    /**
     * Only send along important slplus options to the load/search request.
     *
     * Appears on AJAX request in REQUEST['options']
     * which is processed by set_QueryParams in base_class.ajax.php
     * which in turn sets the SLPLUS options array in runtime memory
     *
     * Can be used by these filters:
     * 'slp_ajax_location_queries'
     *     @used-by Premier add_ajax_hooks()
     * 'slp_ajax_find_locations_complete'
     *    @used-by Power add_ajax_hooks()
     *
     * @returns {null|any}
     */
    const getImportantSearchOptions = () => {
        if (this.search_options === null) {
            const jsonSearchOptions = JSON.parse(JSON.stringify(slplus.options));
            const importantOptions = [
                'initial_results_returned', // base plugin
                'max_results_returned', // base plugin
                'map_center_lat', // base plugin
                'map_center_lng', // base plugin
                'active_locations',
                'city',
                'country',
                'distance_unit',
                'ignore_radius',
                'initial_radius',
                'map_domain',
                'map_end_icon',
                'state',
                'territory',
                // 'sl_id', // premier url control
                // 'location',
                // 'map_center',
                // 'limit',
                // 'active_location',
            ];
            this.search_options = {};
            importantOptions.forEach(key => {
                this.search_options[key] = jsonSearchOptions[key];
            });
        }
        return this.search_options;
    };

    /**
     * Return a proper search address for directions.
     * Use the address entered if provided.
     * Use the GPS coordinates if not and use location is on and coords available.
     * Otherwise use the center of the country.
     */
    this.getSearchAddress = function (defaultAddress) {
        var searchAddress = jQuery("#addressInput").val();
        if (!searchAddress) {
            if (
                typeof cslmap !== "undefined" &&
                cslmap.usingSensor &&
                slp.sensor.lat !== 0.0 &&
                slp.sensor.lng !== 0.0
            ) {
                searchAddress = slp.sensor.lat + "," + slp.sensor.lng;
            } else {
                searchAddress = defaultAddress;
            }
        }

        return searchAddress;
    };

    /**
     * Get a sane value from the HTML document.
     *
     * @param id of control to look at
     * @param defaultValue to return
     * @return {undef}
     */
    this.saneValue = function (id, defaultValue) {
        let name = document.getElementById(id);
        if (name === null) {
            name = defaultValue.trim();
        } else {
            name = name.value.trim();
        }
        return name;
    };

    /**
     * Sends an ajax request and drops the markers on the map
     *
     * @method load_markers
     *
     * @param {google.maps.LatLng}  center    the center of the map (where to center to)
     * @param {number}              radius
     */
    this.load_markers = function (center, radius) {
        var action, store_name, tags;

        if (center === null) {
            center = this.gmap.getCenter();
        }
        this.lastCenter = center;
        this.lastRadius = radius;

        // Setup our variables sent to the AJAX listener.
        action = {
            action: "csl_ajax_search", // default action is search
            address: this.saneValue("addressInput", "no address entered"),
            formdata: jQuery("#searchForm").serialize(),
            lat: center.lat(),
            lng: center.lng(),
            options: getImportantSearchOptions(),
            radius: radius,
            nonce: slplus.nonce,
        };

        // Only add store_name to the action properties if set
        store_name = this.saneValue("nameSearch", "");
        if (store_name !== "") {
            action["store_name"] = store_name;
        }

        // Only add tags to the action properties if set
        tags = this.saneValue("tag_to_search_for", "");
        if (tags !== "") {
            action["tags"] = tags;
        }

        /**
         * Modify the SLP location request action.
         *
         * @filter  load_marker_action
         *
         * @param   array   action   The current action options.
         * @return  array            A modified action array.
         */
        slp_Filter("load_marker_action").publish(action);

        // Change Action To On Load
        if (slplus.options.immediately_show_locations !== "0") {
            action.action = "csl_ajax_onload";
            slplus.options.immediately_show_locations = "0";
            this.search_options = null;
        }

        // Send AJAX call
        //
        slp.send_ajax(action, _this.process_ajax_response);
    };

    /**
     * Process the AJAX responses for locations.
     */
    this.process_ajax_response = function (response) {
        valid_response = typeof response.response !== "undefined";
        if (valid_response) {
            valid_response = response.success;
        }

        /**
         * Fires when the location search responds.
         *
         * @filter  location_search_responded
         *
         * @param   object   response
         *
         * @return  object
         */
        slp_Filter("location_search_responded").publish(response);

        if (valid_response) {
            cslmap.latest_response = response;
            cslmap.clearMarkers();

            /**
             * Manipulate the marker list returned from the backend.
             *
             * @filter  locations_found
             *
             * @param   object   response
             *
             * @return  object                       the modified response
             */
            slp_Filter("locations_found").publish(response);

            cslmap.putMarkers(response.response);

            /**
             * Fire JavaScript action 'markers_dropped' after markers are dropped.
             */
            jQuery("#map").trigger("markers_dropped");
        } else {
            if (window.console) {
                slp.log("SLP server did not send back a valid JSONP response.");
                if (typeof response !== "undefined") {
                    slp.log("Response: " + response);
                }
                if (typeof response.message !== "undefined") {
                    var sidebar = document.getElementById("map_sidebar");
                    sidebar.innerHTML = response.message;
                }
            }
        }

        /**
         * Fires when the location search finished processing.
         *
         * @filter  location_search_responded
         */
        slp_Filter("location_search_processed").publish();
    };

    /**
     * begins the process of returning search results
     * parameters:
     *        none
     * returns: none
     */
    this.searchLocations = function () {
        var append_this =
            typeof slplus.options.append_to_search !== "undefined" &&
            slplus.options.append_to_search
                ? " " + slplus.options.append_to_search.trim()
                : "";

        var address = this.saneValue("addressInput", "") + append_this;

        this.unhide_map();

        google.maps.event.trigger(this.gmap, "resize");

        // Address was given, use it...
        //
        if (address) {
            this.address = address;
            this.doGeocode();

            // Otherwise use the current map center as the center location
            //
        } else {
            this.load_markers(null, this.saneValue("radiusSelect", "40000"));
        }
    };

    /**
     * Render a marker in the results section
     *
     * @param {object} currentLocation marker data for a single location
     * @returns {string} a html div with the data properly displayed
     */
    this.createSidebar = function (currentLocation) {
        //if we are showing tags in the table
        //
        currentLocation.pro_tags = "";
        if (currentLocation.tags.trim() !== "") {
            var tagclass = currentLocation.tags.replace(/\W/g, "_");
            currentLocation.pro_tags =
                '<br/><div class="' +
                tagclass +
                ' slp_result_table_tags"><span class="tagtext">' +
                currentLocation.tags +
                "</span></div>";
        }

        // Phone and Fax with Labels
        //
        currentLocation.phone_with_label =
            currentLocation.phone.trim() !== ""
                ? slplus.options.label_phone + currentLocation.phone
                : "";
        currentLocation.fax_with_label =
            currentLocation.fax.trim() !== ""
                ? slplus.options.label_fax + currentLocation.fax
                : "";

        // Search address and formatted location address
        //
        var address = this.__createAddress(currentLocation);
        currentLocation.location_address = encodeURIComponent(address);
        currentLocation.search_address = encodeURIComponent(
            this.getSearchAddress(this.address)
        );
        currentLocation.hours_sanitized = jQuery("<div/>")
            .html(currentLocation.hours)
            .text();

        /**
         * Create and entry in the results table for this location.
         */
        var inner_html = {
            content: slplus.options.resultslayout.replace_shortcodes(currentLocation),

            finished: false,
        };

        /**
         * Wrap the location results string.
         *
         * @filter  wrap_location_results
         *
         * @param   object   inner_html     The wrapper HTML string.
         * @return  object                  The modified HTML string.
         */
        slp_Filter("wrap_location_results").publish(inner_html);

        if (!inner_html.finished) {
            inner_html.content =
                '<div class="results_wrapper" id="slp_results_wrapper_' +
                currentLocation.id +
                '">' +
                inner_html.content +
                "</div>";
        }

        return inner_html.content;
    };

    /**
     * Unhide the map div.
     */
    this.unhide_map = function () {
        if (this.map_hidden) {
            jQuery("#map_box_image").hide();
            jQuery("#map_box_map").show();
            this.map_hidden = false;
        }
    };

    this.__init();
};
