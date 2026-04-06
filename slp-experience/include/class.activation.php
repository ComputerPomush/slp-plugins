<?php
defined( 'SLPLUS_VERSION' ) || exit;
if ( ! class_exists( 'SLP_Experience_Activation' ) ) {
	require_once( SLPLUS_PLUGINDIR . 'include/base_class.activation.php' );

	/**
	 * Manage plugin activation.
	 */
	class SLP_Experience_Activation extends SLP_BaseClass_Activation {
		/**
		 * Going to be SLP Smart Options now
		 */
		protected $smart_options = array(
			'add_tel_to_phone',
			'address_placeholder',
			'append_to_search',
			'bubblelayout',
			'first_entry_for_radius_selector',
			'google_map_style',
			'hide_bubble',
			'hide_distance',
			'hide_map',
			'hide_address_entry', // to copy value to main SLP Smart Options, moved from Experience
			'hide_radius_selector', // to copy value to main SLP Smart Options, moved from Experience
			'hide_search_form',
			'label_for_find_button',
			'label_for_map_toggle',
			'label_for_name',
			'layout',
			'map_controls_subheader',
			'map_options_scaleControl',
			'map_options_mapTypeControl',
			'map_initial_display',
			'maplayout',
			'name_placeholder',
			'no_autozoom',
			'no_homeicon_at_start',
			'results_box_title',
			'resultslayout',
			'search_box_title',
			'searchlayout',
			'show_maptoggle',
			'show_country',
			'show_hours',
			'starting_image',
			'url_allow_address',
		);

		/**
		 * Add extended data fields used by this plugin.
		 */
		private function add_extended_data_fields() {
			$this->slplus->database->extension->add_field(
				__( 'Featured', 'slp-experience' ), 'boolean',
				array(
					'slug'         => 'featured',
					'addon'        => $this->addon->short_slug,
					'display_type' => 'checkbox',
					'help_text'    =>
						__( 'If checked the location will be marked as featured. ', 'slp-experience' ) .
						__( 'Featured locations may display differently depending on the plugin style selected. ', 'slp-experience' )
				),
				'wait'
			);

			$this->slplus->database->extension->add_field(
				__( 'Rank', 'slp-experience' ), 'int',
				array(
					'slug'      => 'rank',
					'addon'     => $this->addon->short_slug,
					'help_text' =>
						__( 'Determine the sort order for this location, lower numbers are displayed first. ', 'slp-experience' ) .
						__( 'Sort order is determined by the order by settings.', 'slp-experience' )
				),
				'wait'
			);

			$this->slplus->database->extension->add_field(
				__( 'Map Marker', 'slp-experience' ), 'varchar',
				array(
					'slug'         => 'marker',
					'addon'        => $this->addon->short_slug,
					'display_type' => 'icon',
					'help_text'    => __( 'This image will be the map marker at all times regardless of assigned category or other map marker settings.', 'slp-experience' )
				),
				'wait'
			);

			$this->slplus->database->extension->update_data_table( array( 'mode' => 'force' ) );
		}

		/**
		 * Add extended data fields when updating.
		 */
		public function update() {
			parent::update();
			$this->add_extended_data_fields();
		}
	}
}
