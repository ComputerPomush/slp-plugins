/**
 * Store Locator Plus® Power User Interface
 * 2209.15.01
 */
/* globals jQuery, google, slp_Filter, cslmap, slplus, slp, slppower_settings */
const SLPPOWER = {
	ready: function() {
		slp_Filter( 'slp_map_ready' ).subscribe( function( cslmap ) {
			SLPPOWER.map.initialize();
			if ( slplus.options.use_sensor ) {
				slp.sensor = new slp_LocationServices();
				slp.sensor.initialize();
			}
		} );
	},
};
slp_Filter('ready').subscribe(SLPPOWER.ready);

/**
 *
 * Class slp_LocationServices
 *
 * Handle the location sensor (or not)
 */
var slp_LocationServices = function () {
    this.service = null;
    this.location_timeout = null;
    this.lat = 0.00;
    this.lng = 0.00;
    this.timeout = 30000;

    /**
     * Set the current location.
     *
     * When setting get_user_location the callback and errorCallback functions must
     * be defined. See the sensor.get_user_location call down below for the
     * return-to place for these two functions passed as variables
     *
     * @param callback
     * @param errorCallback
     */
    this.get_user_location = function ( callback , errorCallback ) {

        // Old school support
        if ( typeof navigator.geolocation === 'undefined' ) {
            if ( google.gears ) {
                this.service = google.gears.factory.create( 'beta.geolocation' );
            }

        // HTML 5 support
        } else {
            this.service = navigator.geolocation;
        }

        // If this browser supports location services, use them
        //
        if ( this.service ) {

            // In 30 seconds run errorCallback
            //
			slp.sensor.location_timeout = setTimeout( errorCallback , this.timeout, { name: 'location_timeout' , message: 'Location sensor timed out' } );

            // Run the browser location service to get the current position
            //
            // on success run callback
            // on failure run errorCallback
            //
            this.service.getCurrentPosition( callback , errorCallback , {
                maximumAge: 60000 ,
                timeout: this.timeout ,
                enableHighAccuracy: true
            } );

        // No location support, direct geocoding.
        //
        } else {
            slp.sensor.geocode_with_no_sensor();
        }

    };

    /**
     * Initialize GPS services.
     */
    this.initialize = function() {
        cslmap.usingSensor = true;
        if ( typeof cslmap.build_map !== 'function' ) {
			slp_Filter( 'slp_map_ready' ).subscribe( this.initialize );
			return;
		}

        slp.sensor.get_user_location(

            // Success
            //
            function ( loc ) {
                clearTimeout( slp.sensor.location_timeout );
                slp.sensor.lat = loc.coords.latitude;
                slp.sensor.lng = loc.coords.longitude;

	            if ( typeof cslmap.build_map === 'function' ) {
		            cslmap.build_map(new google.maps.LatLng(loc.coords.latitude,
			            loc.coords.longitude));
	            }
            } ,

            // Error
            //
            function ( err ) {
	            clearTimeout( slp.sensor.location_timeout );
	            slp.sensor.geocode_with_no_sensor();
            }
        );

    };

    // Turn off all sensor settings and geocode.
    //
    this.geocode_with_no_sensor = function() {
        slplus.options.use_sensor = false;
        cslmap.usingSensor = false;
        cslmap.doGeocode();
    };
};

/**
 * The Directory List Class
 */
SLPPOWER.directory = ( function () {

	/**
	 * Show locations on map.
	 */
	this.show_locations_on_map = function( text_value ) {
		jQuery.ajax({
			method : 'GET',
			url : mySLP.protocol + mySLP.rest_base + 'locations/',
			data: {
				api_key: mySLP.api_key,
				filter: [
						{ 'filter_by': 'sl_state',  'filter_value': text_value }
						]
			},
			dataType: 'json'

		}).fail(function( jqXHR, textStatus, errorThrown ) {
			console.log( jqXHR, textStatus, errorThrown  );
			if( jqXHR.responseJSON && jqXHR.responseJSON.message ){
				jQuery( "#" + mySLP.divID + " .map-messages" ).addClass('error').prepend( jqXHR.responseJSON.message ).show();
			}

		}).done(function( result ){
			var ajax_formatted_result = {},
				valid_response
				;

			if  ( typeof result.data !== 'undefined' ) {
				var idx, key, new_key, store;

				ajax_formatted_result.response = [];

				result.data.forEach( function( store ) {
					new_element = {};
					for ( key in store ) {
						new_key = key.replace('sl_', '');
						if ( new_key === 'store' ) {
							new_key = 'name';
						}
						new_element[ new_key ] = store[ key ];
					}
					new_element.data = store;
					ajax_formatted_result.response.push( new_element );
				});

				ajax_formatted_result.count = ajax_formatted_result.response.length;
				ajax_formatted_result.success = true;
				ajax_formatted_result.type = 'search';

			} else {
				ajax_formatted_result = {
					'success' : false
				}
			}
			cslmap.process_ajax_response( ajax_formatted_result );
		});


		jQuery( 'div.store_locator_plus' ).removeClass( 'hidden' );
	};

	/**
	 * Public Methods
	 */
	return {
		show_locations_on_map: show_locations_on_map
	}

} )();

/**
 * The Location List Class
 */
SLPPOWER.map = ( function () {
	var center,
		location_id;

    /**
     * Initialize the map.
     */
    this.initialize = function () {
        jQuery("[title='location_map']").each(function () {
            location_id = jQuery(this).attr('data-location_id');
            get_location_data(location_id);
        });
    };

    /**
     * Add a location to the map.
	 *
	 * @typedef {Object} data
	 * @property {number} sl_id
	 * @property {number} sl_latitude
	 * @property {number} sl_longitude
     */
    this.add_this_location = function ( data ) {
		var add_map,
        	map_div_id,
			marker_settings
        	;

		map_div_id = document.getElementById('map-canvas-'+data.sl_id);

		center = new google.maps.LatLng( data.sl_latitude , data.sl_longitude );
		add_map = new slp_Map();

		slplus.options.immediately_show_locations = '0';

		slp_Filter('map_options').subscribe( this.modify_add_map_options );
		if ( typeof add_map.build_map === 'function' ) {
			add_map.build_map(center, map_div_id);
			marker_settings = {
				position: center,
				map: add_map.gmap,
				icon: slppower_settings.map_end_icon
			};
			new google.maps.Marker(marker_settings);
		}
		slp_Filter('map_options').unsubscribe( this.modify_add_map_options );

    };

    /**
     * Request location data from the SLP REST service.
     *
     * @param location_id
     */
    this.get_location_data = function ( location_id ) {
        jQuery.ajax(
            slppower_settings.resturl + 'locations/' + location_id ,
            {
                success: add_this_location
            }
        );
    };

	/**
	 * Set Map options
	 *
	 * @param map_options
	 * 				zoom	force to 14
	 */
	this.modify_add_map_options = function( map_options ) {
		map_options.zoom = 14;
	};

	/**
	 * Public Methods
	 */
	return {
		initialize: initialize,
		modify_add_map_options: modify_add_map_options
	}

} )();
