<?php
defined( 'SLPLUS_VERSION' ) || exit;

/**
 * WP REST API interface.
 *
 * Read [the REST API documentation](./api) for more details.
 *
 * @package StoreLocatorPlus\REST
 */
class SLP_REST_Handler extends SLPlus_BaseClass_Object {

	/**
	 * Things we do at the start.
	 */
	public function initialize() {
		defined( 'SLP_REST_SLUG' ) || define( 'SLP_REST_SLUG', 'store-locator-plus' );
		$this->set_rest_hooks();
	}

	/**
	 * Return rest path.
	 *
	 * @return string
	 */
	public function get_rest_path() {
		return get_rest_url( null, SLP_REST_SLUG . '/v2/' );
	}

	/**
	 * Set the rest hooks.
	 */
	private function set_rest_hooks() {
		add_action( 'rest_api_init', array( $this, 'setup_rest' ) );
	}

	/**
	 * Only if REST_REQUEST is defined.
	 */
	public function setup_rest() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$this->setup_rest_endpoints();
		}
	}

	/**
	 * Setup the REST endpoints for Store Locator Plus.
	 */
	private function setup_rest_endpoints() {
		$this->setup_rest_cross_version_endpoints( 'v1' );
		$this->setup_rest_cross_version_endpoints( 'v2' );
		do_action( 'slp_setup_rest_endpoints' );
	}

	/**
	 * Setup cross-version REST endpoints
	 *
	 * @param string $version
	 */
	private function setup_rest_cross_version_endpoints( $version ) {
		/**
		 * Get a single location.
		 *
		 * @uses \SLP_REST_Handler::get_location_by_id
		 *
		 * @route   wp-json/store-locator-plus/v2/locations/<id>
		 * @method  WP_REST_Server::READABLE ( GET )
		 *
		 * @returns WP_Error | WP_REST_Reponse
		 */
		register_rest_route( SLP_REST_SLUG . '/' . $version, '/locations/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_location_by_id' ),
			'permission_callback' => '__return_true',
		) );


		/**
		 * Add a single location.
		 *
		 * Requires authentication.
		 *
		 * @uses \SLP_REST_Handler::add_location
		 * @uses \SLP_REST_Handler::user_can_manage_slp
		 *
		 * @route   wp-json/store-locator-plus/v2/locations
		 * @method  WP_REST_Server::EDITABLE ( POST, PUT, PATCH )
		 *
		 * @params  string  sl_store        required , name of store
		 * @params  string  <field_slug>    optional, other store data. Field slugs can match base or extended data fields.
		 *
		 * @returns WP_Error | WP_REST_Reponse
		 */
		register_rest_route( SLP_REST_SLUG . '/' . $version, '/locations/', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'add_location' ),
			'permission_callback' => array( $this, 'user_can_manage_slp' ),
			'args'                => array(
				'sl_store' => array( 'required' => true ),
			)
		) );

		/**
		 * Get a list of locations.
		 *
		 * @uses \SLP_REST_Handler::get_locations
		 *
		 * @route   wp-json/store-locator-plus/v2/locations
		 * @method  WP_REST_Server::READABLE ( GET )
		 *
		 * @returns WP_Error | WP_REST_Reponse
		 */
		register_rest_route( SLP_REST_SLUG . '/' . $version, '/locations/', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_locations' ),
			'permission_callback' => '__return_true',
		) );

		/**
		 * Update a single location.
		 *
		 * Requires authentication.
		 *
		 * @uses \SLP_REST_Handler::update_location
		 * @uses \SLP_REST_Handler::user_can_manage_slp
		 *
		 * @route   wp-json/store-locator-plus/v2/locations
		 * @method  WP_REST_Server::EDITABLE ( POST, PUT, PATCH )
		 *
		 * @params  string  sl_store        required , name of store
		 * @params  string  <field_slug>    optional, other store data. Field slugs can match base or extended data fields.
		 *
		 * @returns WP_Error | WP_REST_Reponse
		 */
		register_rest_route( SLP_REST_SLUG . '/' . $version, '/locations/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'update_location' ),
			'permission_callback' => array( $this, 'user_can_manage_slp' ),
		) );

		/**
		 * Delete a single location.
		 *
		 * Requires authentication.
		 *
		 * @uses \SLP_REST_Handler::delete_location_by_id
		 * @uses \SLP_REST_Handler::user_can_manage_slp
		 *
		 * @route   wp-json/store-locator-plus/v2/locations/<id>
		 * @method  WP_REST_Server::DELETABLE ( DELETE )
		 *
		 * @returns WP_Error | WP_REST_Reponse
		 */
		register_rest_route( SLP_REST_SLUG . '/' . $version, '/locations/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'delete_location_by_id' ),
			'permission_callback' => array( $this, 'user_can_manage_slp' ),
		) );

		/**
		 * Get ALL smart options.
		 *
		 *
		 * @uses \SLP_REST_Handler::get_smart_option
		 *
		 * @route   wp-json/store-locator-plus/v2/options/all/
		 * @method  WP_REST_Server::READABLE ( GET )
		 *
		 * @returns WP_Error | WP_REST_Reponse
		 */
		register_rest_route( SLP_REST_SLUG . '/' . $version, '/options/all/', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_all_options' ),
			'permission_callback' => '__return_true',
		) );

		/**
		 * Import Options
		 *
		 * @route   wp-json/store-locator-plus/v2/options/import/
		 * @method  WP_REST_Server::EDITABLE ( GET )
		 *
		 * @returns WP_Error | WP_REST_Reponse
		 */
		register_rest_route( SLP_REST_SLUG . '/' . $version, '/options/import/', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'import_options' ),
			'permission_callback' => array( $this, 'user_can_manage_slp' ),
		) );

		/**
		 * Get the specified smart option.
		 *
		 *
		 * @uses \SLP_REST_Handler::get_smart_option
		 *
		 * @route   wp-json/store-locator-plus/v2/options/<slug>
		 * @method  WP_REST_Server::READABLE ( GET )
		 *
		 * @uses \SLP_REST_Handler::get_smart_option
		 *
		 * @returns WP_Error | WP_REST_Reponse
		 */
		register_rest_route( SLP_REST_SLUG . '/' . $version, '/options/(?P<slug>\w+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_smart_option' ),
			'permission_callback' => '__return_true',
		) );

		/**
		 * Get the specified smart option FILTERED.
		 *
		 * @uses \SLP_REST_Handler::get_smart_option_filtered
		 *
		 * @route   wp-json/store-locator-plus/v2/options/filtered/<slug>
		 * @method  WP_REST_Server::READABLE ( GET )
		 *
		 * @uses \SLP_REST_Handler::get_smart_option_filtered
		 *
		 * @returns WP_Error | WP_REST_Reponse
		 */
		register_rest_route( SLP_REST_SLUG . '/' . $version, '/options/filtered/(?P<slug>\w+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_smart_option_filtered' ),
			'permission_callback' => '__return_true',
		) );

		/**
		 * Update the specified smart option.
		 *
		 *
		 * @uses \SLP_REST_Handler::update_smart_option
		 *
		 * @route   wp-json/store-locator-plus/v2/options/<slug>/<value>
		 * @method  WP_REST_Server::EDITABLE ( GET )
		 *
		 * @uses \SLP_REST_Handler::get_smart_option
		 *
		 * @returns WP_Error | WP_REST_Reponse
		 */
		register_rest_route( SLP_REST_SLUG . '/' . $version, '/options/(?P<slug>\w+)/(?P<apikey>.+)', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'update_smart_option' ),
			'permission_callback' => array( $this, 'user_can_manage_slp' ),
			'args'                => array(
				'apikey' => array( 'required' => true, 'validate_callback' => array( $this, 'validate_apikey' ) ),
				'slug'   => array( 'required' => true )
			),
		) );


		/**
		 * Geocode an address.
		 *
		 * @uses \SLP_REST_Handler::geocode_address
		 * @uses \SLP_REST_Handler::valid_referer
		 * @uses \SLP_REST_Handler::validate_apikey
		 *
		 * @route   wp-json/store-locator-plus/v2/geocode/<address>
		 * @method  WP_REST_Server::READABLE ( GET )
		 *
		 * @returns WP_Error | WP_REST_Reponse
		 */
		register_rest_route( SLP_REST_SLUG . '/' . $version, '/geocode/(?P<apikey>.+)/(?P<region>.+)/(?P<address>.+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'geocode_address' ),
			'permission_callback' => array( $this, 'valid_referer' ),
			'args'                => array(
				'apikey'  => array( 'required' => true, 'validate_callback' => array( $this, 'validate_apikey' ) ),
				'region'  => array( 'required' => true ),
				'address' => array( 'required' => true )
			)
		) );

		/**
		 * @api {get} wp-json/store-locator-plus/v2/environment Get plugin environment info.
		 * @apiName GetEnvironment
		 * @apiGroup Environment
		 */
		register_rest_route( SLP_REST_SLUG . '/' . $version, '/environment/', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getEnvironment' ),
			'permission_callback' => array( $this, 'user_can_manage_slp' ),
		) );

		/**
		 * @api {get} wp-json/store-locator-plus/v2/schedule Get WordPress cron schedule info.
		 * @apiName GetSchedule
		 * @apiGroup Schedule
		 */
		register_rest_route( SLP_REST_SLUG . '/' . $version, '/schedule/', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'getSchedule' ),
			'permission_callback' => array( $this, 'user_can_manage_slp' ),
		) );
	}

	/**
	 * Get the environment info
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return void|WP_REST_Response
	 */
	public function getEnvironment( WP_REST_Request $request ) {
		return new WP_REST_Response( SLP_REST_Environment::get_instance()->get() );
	}

	/**
	 * Get the environment info
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return void|WP_REST_Response
	 */
	public function getSchedule( WP_REST_Request $request ) {
		return new WP_REST_Response( SLP_REST_Schedule::get_instance()->get() );
	}

	/**
	 * Validate the API key.
	 *
	 * @param $param
	 * @param $request
	 * @param $key
	 *
	 * @return bool
	 */
	public function validate_apikey( $param, $request, $key ) {
		$is_valid = ( $param === SLPlus::get_instance()->get_apikey() );
		if ( ! $is_valid ) {
			error_log( 'Supplied API key  ' . $param . ' is invalid.' );
		}

		return $is_valid;
	}

	/**
	 * Validate the referrer as coming from this site.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return boolean
	 */
	public function valid_referer( WP_REST_Request $request ) {
		$headers = $request->get_headers();
		if ( empty( $headers['referer'] ) ) {
			return false;
		}

		$referal = wp_get_raw_referer();

		$is_valid = ( strpos( $referal, get_site_url() ) === 0 ) || ( strpos( $referal, get_home_url() ) === 0 );
		if ( ! $is_valid ) {
			$is_valid = apply_filters( 'slp_rest_geocode_invalid_referer', $is_valid, $request );
		}

		return $is_valid;
	}


	/**
	 * Serves the change setting endpoint.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return void|WP_REST_Response
	 */
	public function geocode_address( WP_REST_Request $request ) {
		$params   = $request->get_params();
		$response = new WP_REST_Response( SLP_Location_Utilities::get_instance()->geocode( $params ) );
		$response->set_status( 200 );

		return $response;
	}

	/**
	 * Return a list of locations.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_locations( WP_REST_Request $request ) {
		$location_list = array();
		$offset        = 0;
		do {

			$location = $this->slplus->database->get_Record( array(
				'selectslid',
				'where_default'
			), array(), $offset ++ );

			if ( is_wp_error( $location ) ) {
				return $location;
			}

			if ( ! empty ( $location['sl_id'] ) ) {
				$location_list[] = array( 'sl_id' => $location['sl_id'] );
			}
		} while ( ! empty ( $location['sl_id'] ) );


		$response = new WP_REST_Response( $location_list );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Add a location.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function add_location( WP_REST_Request $request ) {

		// Set location data
		$location_data = $request->get_params();

		// Error During Prep
		//
		if ( empty( $location_data ) ) {
			return new WP_Error( 'slp_missing_location_data', $this->slplus->Text->get_text_string( array(
				'label',
				'slp_missing_location_data'
			) ), array( 'status' => 404 ) );
		}

		// Add Location
		//
		$result = $this->slplus->currentLocation->add_to_database( $location_data, 'add', false );

		// Error During Add
		//
		if ( $result == 'not_updated' ) {
			return new WP_Error( 'slp_location_not_updated', $this->slplus->Text->get_text_string( array(
				'label',
				'slp_location_not_updated'
			) ), array( 'status' => 404 ) );
		}

		$response_data = array(
			'message_slug' => 'location_added',
			'message'      => __( 'Location added. ', 'store-locator-le' ),
			'location_id'  => $this->slplus->currentLocation->id
		);
		$response      = new WP_REST_Response( $response_data );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Return a single location.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_location_by_id( WP_REST_Request $request ) {
		$result = $this->slplus->currentLocation->get_location( $request['id'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = new WP_REST_Response( $this->slplus->currentLocation->get( ARRAY_A ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Return the current value of a smart option.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_all_options( WP_REST_Request $request ) {
		$generic_options_array = array(
			'store-locator-le' => array(
				'settings' => array(
					'options' => array_merge( $this->slplus->options_nojs, $this->slplus->options )
				)
			)
		);
		$return_data           = $generic_options_array;

		$response = new WP_REST_Response( $return_data );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Import the options JSON file.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function import_options( WP_REST_Request $request ) {
		$return_data = array( 'message' => 'Options Imported', 'data' => array() );
		$params      = $request->get_params();
		$file_meta   = json_decode( $params['file-meta'] );

		// phpcs:ignore wp_remote_get can mangle remote file contents
		// phpcs:ignore
		$json_settings = file_get_contents( $file_meta->url );
		$json_settings = json_decode( $json_settings, true );
		$slp_options   = $json_settings['store-locator-le']['settings']['options'];

		$slp_smart_options = SLP_SmartOptions::get_instance();
		foreach ( $slp_options as $option => $value ) {
			$return_data['data'][ $option ] = $value;
			$slp_smart_options->set_valid_options( $value, $option );
			$slp_smart_options->set_valid_options_nojs( $value, $option );
		}

		$slp_smart_options->execute_change_callbacks();                                 // Anything changed?  Execute their callbacks.
		$this->slplus->WPOption_Manager->update_wp_option( 'js' );        // Change callbacks may interact with JS or NOJS, make sure both are saved after ALL callbacks
		$this->slplus->WPOption_Manager->update_wp_option( 'nojs' );

		$response = new WP_REST_Response( $return_data );
		$response->set_status( 201 );

		return $response;
	}


	/**
	 * Return the current value of a smart option.
	 *
	 * @param WP_REST_Request $request
	 * @param boolean filtered
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_smart_option( WP_REST_Request $request, $filtered = false ) {
		$property = $request['slug'];

		if ( property_exists( $this->slplus->SmartOptions, $property ) ) {
			$result = $this->slplus->SmartOptions->$property;

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( ! is_a( $result, 'SLP_Option' ) ) {
				return new WP_Error( 'invalid_option', __( 'Not a valid option slug.', 'store-locator-le' ) );
			}
		} else {
			return new WP_Error( 'invalid_option', __( 'Not a valid option slug.', 'store-locator-le' ) );
		}

		// phpcs:ignore do not call the wp version as we need a pure encode/decode here
		// phpcs:ignore
		$return_data = json_decode( json_encode( $result ) );

		// Blank out these things to lighten our load and prevent infinite recursion
		unset( $return_data->call_when_changed );
		unset( $return_data->slplus );

		// Set the values
		if ( $filtered ) {
			$return_data->value = apply_filters( 'slp_get_option_value_for_' . $property, $result->value );
		} else {
			$return_data->value = $result->value;
		}
		$return_data->initial_value = $result->initial_value;

		$response = new WP_REST_Response( $return_data );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Return the current value of a smart option.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_smart_option_filtered( WP_REST_Request $request ) {
		return $this->get_smart_option( $request, true );
	}

	/**
	 * Delete a single location
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function delete_location_by_id( WP_REST_Request $request ) {
		$result = $this->slplus->currentLocation->delete( $request['id'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response_data = array(
			'message_slug' => 'location_deleted',
			'message'      => __( 'Location deleted. ', 'store-locator-le' ),
			'location_id'  => $request['id']
		);
		$response      = new WP_REST_Response( $response_data );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Update a location.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_location( WP_REST_Request $request ) {

		// Get the location data
		//
		$result = $this->slplus->currentLocation->get_location( $request['id'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Set the incoming parameters array for the update
		//
		$location_data = $request->get_params();
		unset( $location_data['id'] );
		$location_data['sl_id'] = $this->slplus->currentLocation->id;
		foreach ( $location_data as $key => $value ) {
			if ( is_numeric( $key ) ) {
				unset( $location_data[ $key ] );
			}
		}

		// Error During Prep
		//
		if ( empty( $location_data ) ) {
			return new WP_Error( 'slp_missing_location_data', $this->slplus->Text->get_text_string( array(
				'label',
				'slp_missing_location_data'
			) ), array( 'status' => 404 ) );
		}

		// Update Location
		//
		$result = $this->slplus->currentLocation->add_to_database( $location_data, 'update', false );

		// Error During Update
		//
		if ( $result !== 'updated' ) {
			return new WP_Error( 'slp_location_not_updated', $this->slplus->Text->get_text_string( array(
				'label',
				'slp_location_not_updated'
			) ), array( 'status' => 404 ) );
		}

		$response_data = array(
			'message_slug' => 'location_updated',
			'message'      => __( 'Location updated. ', 'store-locator-le' ),
			'location_id'  => $this->slplus->currentLocation->id
		);
		$response      = new WP_REST_Response( $response_data );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Update a smart option.
	 *
	 * @param WP_REST_Request $request
	 * @param bool $filtered
	 *
	 * @return SLP_Option|WP_Error|WP_REST_Response|null
	 */
	public function update_smart_option( WP_REST_Request $request, $filtered = false ) {
		$property = $request['slug'];

		// post data
		$post_data = $request->get_params();
		$value     = urldecode( $post_data['value'] );

		if ( property_exists( $this->slplus->SmartOptions, $property ) ) {
			$result = $this->slplus->SmartOptions->$property;

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( ! is_a( $result, 'SLP_Option' ) ) {
				return new WP_Error( 'invalid_option', __( 'Not a valid option slug.', 'store-locator-le' ) );
			}
		} else {
			return new WP_Error( 'invalid_option', __( 'Not a valid option slug.', 'store-locator-le' ) );
		}

		$this->slplus->SmartOptions->set( $property, $value );

		$return_data = array(
			'action'   => 'set',
			'property' => $property,
			'to'       => $value,
			'value'    => $this->slplus->SmartOptions->{$property}->value
		);
		$this->slplus->SmartOptions->execute_change_callbacks();
		$this->slplus->WPOption_Manager->update_wp_option( 'js' );
		$this->slplus->WPOption_Manager->update_wp_option( 'nojs' );

		$response = new WP_REST_Response( $return_data );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Return true if user can manage SLP.
	 *
	 * @return bool
	 */
	public function user_can_manage_slp() {
		return current_user_can( 'manage_slp_user' );
	}
}
