<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'SLP_BaseClass_AJAX' ) ) {

	/**
	 * A base class that helps add-on packs separate AJAX functionality.
	 *
	 * Add on packs should include and extend this class.
	 *
	 * This allows the main plugin to only include this file in AJAX mode.
	 *
	 * @property        SLP_BaseClass_Addon $addon                        This addon pack.
	 * @property        array $formdata                    Form data that comes into the AJAX request in the formdata variable.
	 * @property        array $formdata_defaults            The formdata default values.
	 * @property-read    bool $formdata_set                Has the formdata been set already?
	 * @property        array $query_params
	 * @property        string[] $query_params_valid        Array of valid AJAX query parameters
	 * @property-read    string $short_action                The shortened (csl_ajax prefix dropped) AJAX action.
	 * @property        string[] $valid_actions                What AJAX actions are valid for this add on to process?
	 *                    Override in the extended class if not serving the default SLP actions:
	 *                        csl_ajax_onload
	 *                        csl_ajax_search
	 *
	 */
	class SLP_BaseClass_AJAX extends SLPlus_BaseClass_Object {
		public $addon;
		protected $formdata = array();
		protected $formdata_defaults = array();
		private $formdata_set = false;
		public $query_params = array();
		protected $query_params_valid = array();
		private $set_query_params_done = false;
		private $short_action;
		protected $valid_actions = array(
			'csl_ajax_onload',
			'csl_ajax_search'
		);

		/**
		 * Instantiate the admin panel object.
		 *
		 * Sets short_action property.
		 * Calls do_ajax_startup.
		 * - sets Query Params (formdata)
		 * - Calls process_{short_action} if method exists.
		 *
		 */
		public function initialize() {
			if ( empty( $this->slplus->clean['action'] ) ) {
				return;
			}
			$this->short_action = str_replace( 'csl_ajax_', '', $this->slplus->clean['action'] );
			$this->do_ajax_startup();
		}

		/**
		 * Override this with the WordPress AJAX hooks you want to invoke.
		 *
		 * example:
		 *        add_action('wp_ajax_csl_ajax_search' , array( $this,'csl_ajax_search' ))        // For logged in users
		 *      add_action('wp_ajax_nopriv_csl_ajax_search' , array( $this,'csl_ajax_search' ))  // Not logged-in users
		 */
		function add_ajax_hooks() {

		}

		/**
		 * Things we want our add on packs to do when they start in AJAX mode.
		 *
		 * Add methods named process_{short_action_name} to the extended class,
		 * or override this method.
		 *
		 * @uses SLP_AJAX::process_location_manager
		 *
		 * NOTE: If you name something with process_{short_action_name} this will bypass the WordPress AJAX hooks and will run IMMEDIATELY when this class is instantiated.
		 */
		function do_ajax_startup() {
			if ( ! $this->is_valid_ajax_action() ) {
				return;
			}
			$this->set_QueryParams();
			$action_name = 'process_' . $this->short_action;
			if ( method_exists( $this, $action_name ) ) {
				$this->$action_name();
			}
			$this->add_ajax_hooks();
		}

		/**
		 * Return true if the AJAX action is one we process.
		 *
		 * TODO: add a "source" parameter as well and set to "slp" then check that to make sure we only process SLP requests
		 */
		function is_valid_ajax_action() {
			if ( empty( $this->slplus->clean['action'] ) ) {
				return false;
			}

			return in_array( $this->slplus->clean['action'], $this->valid_actions );
		}

		/**
		 * Output a JSON response based on the incoming data and die.
		 *
		 * Used for AJAX processing in WordPress where a remote listener expects JSON data.
		 *
		 * @param mixed[] $data named array of keys and values to turn into JSON data
		 */
		function send_JSON_response( $data ) {

			// What do you mean we didn't get an array?
			//
			if ( ! is_array( $data ) ) {
				$data = array(
					'success' => false,
					'count'   => 0,
					'message' => __( 'renderJSON_Response did not get an array()', 'store-locator-le' )
				);
			}

			// Add our SLP Version and DB Query to the output
			//
			$data = array_merge(
				array(
					'success' => true,
				),
				$data
			);

			// Tell them what is coming...
			//
			header( 'Content-Type: application/json' );

			// Go forth and spew data
			//
			echo wp_json_encode( $data );

			// Then die.
			//
			wp_die();
		}

		/**
		 * A less aggressive query param processor.
		 *
		 * We need to keep & as & for query strings.
		 * We also need to allow some form posts to send allowed HTML tags (layout settings).
		 *
		 * @param $str
		 *
		 * @return mixed|string
		 */
		public function sanitize_query_params( $str ) {
			if ( is_object( $str ) || is_array( $str ) ) {
				return '';
			}
			$str = (string) $str;

			$filtered = wp_check_invalid_utf8( $str );
			$filtered = trim( $filtered );

			return $filtered;
		}

		/**
		 * Set incoming query and request parameters into object properties.
		 */
		public function set_QueryParams() {
			if ( $this->set_query_params_done ) {
				return;
			}
			if ( ! $this->formdata_set ) {
				$hasFormData   = isset( $_REQUEST['formdata'] );
				$slpNonceOK    = check_ajax_referer( 'slp_ajax', 'security', false );
				$wpRESTNonceOK = ! empty( $_REQUEST['nonce'] ) && wp_verify_nonce( $_REQUEST['nonce'], 'wp_rest' );
				// Verify the SLP script nonce pushed via localize_script to the JS environ on the FE
				if ( $hasFormData && ( $slpNonceOK || $wpRESTNonceOK ) ) {
					// using sanitize_textarea_field will strip any HTML tags and some form data uses that like layout blocks
					// phpcs:ignore
					$cleanFData     = map_deep( $_REQUEST['formdata'], array( $this, 'sanitize_query_params' ) );
					$this->formdata = apply_filters( 'slp_modify_ajax_formdata', wp_parse_args( $cleanFData ), $this->formdata_defaults );
				}
				$this->formdata_set = true;
			}

			// Incoming Query Params
			//
			$this->query_params_valid = apply_filters( 'slp_valid_ajax_query_params', $this->query_params_valid );

			// unslash false positive, No need to unslash since sanitized
			// note: uncomment $mungedQueryParams if shit goes sideways processing AJAX query vars... few more lines below
			// phpcs:ignore
			$this->query_params['QUERY_STRING'] = isset( $_SERVER['QUERY_STRING'] ) ? esc_url_raw( $_SERVER['QUERY_STRING'] ) : '';
			foreach ( $this->query_params_valid as $key ) {
				$this->query_params[ $key ] = isset( $_REQUEST[ $key ] ) ? sanitize_text_field( ( $_REQUEST[ $key ] ) ) : '';
			}

			// Incoming options - set them in SLPLUS for options or options_nojs.
			//
			if ( isset( $_REQUEST['options'] ) && is_array( $_REQUEST['options'] ) ) {
				$unslashedOptions = array_map( 'wp_kses_post', wp_unslash( $_REQUEST['options'] ) );
				if ( isset( $this->addon ) ) {
					array_walk( $unslashedOptions, array( $this->addon, 'set_ValidOptions' ) );
				}
				array_walk( $unslashedOptions, array( $this->slplus, 'set_ValidOptions' ) );
				array_walk( $unslashedOptions, array( $this->slplus, 'set_ValidOptionsNoJS' ) );
			}

			$this->set_query_params_done = true;
		}

	}
}
