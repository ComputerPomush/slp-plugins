<?php
defined( 'SLPLUS_VERSION' ) || exit;

/**
 * Class SLP_Admin_Upgrade
 *
 * Converts settings and moves files around when necessary between SLP version changes.
 */
class SLP_Admin_Upgrade extends SLPlus_BaseClass_Object {
	/**
	 * Switch things from NOJS to JS or vice-versa.
	 */
	private function convert_serial_settings() {
		$options_to_move = array(
			'label_email',
			'label_website'
		);
		foreach ( $options_to_move as $key ) {
			if ( isset( $this->slplus->options_nojs[ $key ] ) ) {
				$this->slplus->options[ $key ] = $this->slplus->options_nojs[ $key ];
				unset( $this->slplus->options_nojs[ $key ] );
			}
		}

		$move_from_js_to_nojs = array(
			'hide_search_form',
			'initial_results_returned',
			'maplayout',
			'message_no_results',
			'radii',
			'radius_behavior',
			'searchlayout',
		);
		foreach ( $move_from_js_to_nojs as $key ) {
			if ( isset( $this->slplus->options[ $key ] ) ) {
				$this->slplus->options_nojs[ $key ] = $this->slplus->options[ $key ];
				unset( $this->slplus->options[ $key ] );
			}
		}

		$remove = array(
			'admin_locations_per_page',
			'message_no_api_key',
		);
		foreach ( $remove as $key ) {
			if ( isset( $this->slplus->options[ $key ] ) ) {
				unset( $this->slplus->options[ $key ] );
			}
			if ( isset( $this->slplus->options_nojs[ $key ] ) ) {
				unset( $this->slplus->options_nojs[ $key ] );
			}
		}

	}

	/**
	 * Fix the active style.
	 */
	private function fix_active_style() {
		if ( empty( $this->slplus->SmartOptions->active_style_css->value ) ) {
			$this->slplus->SmartOptions->set( 'active_style_css', $this->slplus->SmartOptions->active_style_css->default );
		}
	}

	/**
	 * Migrate the settings from older releases to their new serialized home.
	 */
	public function migrate_settings() {

		// Always re-load theme details data.
		//
		delete_option( SLPLUS_PREFIX . '-api_key' );
		delete_option( SLPLUS_PREFIX . '-theme_details' );
		delete_option( SLPLUS_PREFIX . '-theme_array' );
		delete_option( SLPLUS_PREFIX . '-theme_lastupdated' );

		// Migrate singular options to serialized options
		//
		$this->convert_serial_settings();

		// Fix map domain
		//
		if ( $this->slplus->options['map_domain'] === 'maps.googleapis.com' ) {
			$this->slplus->options['map_domain'] = 'maps.google.com';
		}

		// Fix map center
		//
		if ( isset( $this->slplus->options_nojs['map_center'] ) ) {
			if ( empty( $this->slplus->options['map_center'] ) && ! empty ( $this->slplus->options_nojs['map_center'] ) ) {
				$this->slplus->options['map_center'] = $this->slplus->options_nojs['map_center'];
			}
			unset( $this->slplus->options_nojs['map_center'] );
		}

		// Save Serialized Options
		//
		update_option( SLPLUS_PREFIX . '-options_nojs', $this->slplus->options_nojs );
		update_option( SLPLUS_PREFIX . '-options', $this->slplus->options );
	}
}
