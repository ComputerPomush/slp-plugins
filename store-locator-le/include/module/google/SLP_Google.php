<?php
defined( 'ABSPATH' ) || exit;
class SLP_Google extends SLPlus_BaseClass_Object {
	const MAPS_API_URL = 'https://maps.googleapis.com/maps/api/geocode/json?';

	private $geocodeURL;
	public $region;
	public $url_key;
	public $uses_slplus = false;

	/**
	 * Geocode and address via Google API
	 *
	 * @param string $region
	 * @param string $address
	 *
	 * @return mixed
	 */
	public function geocode( $region, $address ) {
		$this->region = $region;
		$this->get_geocoding_url();
		if ( empty( $this->geocodeURL ) ) {
			return wp_json_encode( array( 'status' => 'NO_GOOGLE_CONNECTION' ) );
		}
		$response     = wp_remote_get( $this->geocodeURL . urlencode( $address ) , array( 'timeout' => SLPlus::web_timeout,  'http_version' => '1.1'  ) );
		return is_wp_error( $response ) ? null : $response['body'];
	}

	/**
	 * Set the geocoding base URL.
	 */
	public function get_geocoding_url( $region = null ) {
		if ( ! isset( $this->geocodeURL ) ) {
			if ( ! is_null( $region ) ) {
				$this->region = $region;
			}
			$this->geocodeURL = $this->get_google_geocoding_url();
			$this->url_key    = str_replace( self::MAPS_API_URL, '', $this->geocodeURL );
			$this->url_key    = preg_replace( '/&key=(.*?)&/', '&', $this->url_key );
		}
		return $this->geocodeURL;
	}

	/**
	 * Get the google maps javascript API URL
	 *
	 * Use the geocoding (server to server) key if set, otherwise fall back to the browser key.
	 *
	 * @return string
	 */
	public function get_google_geocoding_url() {
		global $slplus;
		$the_key = ! empty ( $slplus->SmartOptions->google_geocode_key->value ) ? $slplus->SmartOptions->google_geocode_key->value : '';
		if ( empty( $the_key ) ) {
			$the_key = ! empty ( $slplus->SmartOptions->google_server_key->value ) ? $slplus->SmartOptions->google_server_key->value : '';
		}
		$server_key = ! empty ( $the_key ) ? '&key=' . $the_key : '';

		if ( empty( $this->region ) ) {
			$this->region = SLP_Country_Manager::get_instance()->get_country_code();
		}

		$extra_params = apply_filters( 'slp_google_geocoding_params' , '' );

		return
			self::MAPS_API_URL .
			'language=' . $slplus->options_nojs['map_language'] .
			'&region=' . $this->region .
			$server_key .
			$extra_params .
			'&address=';
	}

}
