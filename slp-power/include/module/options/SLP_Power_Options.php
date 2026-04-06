<?php
defined( 'SLPLUS_VERSION' ) || exit;
require_once( SLPLUS_PLUGINDIR . '/include/base/SLP_AddOn_Options.php' );

/**
 * Manage the Power options, especially the Admin UI setting up the settings.
 *
 * @package Power\Options
 */
class SLP_Power_Options extends SLP_AddOn_Options {

	/**
	 * Create our options.
	 */
	protected function create_options() {
		SLP_Power_Text::get_instance();

		$this->augment_system_wide_options();

		$this->general_admin_messages();

		$this->augment_general_server_web_app();
		$this->augment_general_server_security();

		$this->augment_settings_map_markers();

		$this->augment_settings_results_appearance();
		$this->augment_settings_results_functionality();

		$this->augment_settings_search_appearance();
		$this->search_labels();

		$this->view_appearance();

		$this->pages_settings_appearance();
		$this->pages_settings_behavior();
	}

	/**
	 * Do this when the use contact fields checkbox has changed.
	 *
	 * @param $key
	 * @param $old_value
	 * @param $new_value
	 */
	public function activate_contact_fields( $key, $old_value, $new_value ) {
		if ( $new_value ) {
			global $slplus;
			require_once( SLPPOWER_REL_DIR . 'include/class.activation.php' );
			$activation = new SLPPower_Activation( array( 'addon' => $slplus->AddOns->instances['slp-power'] ) );
			$activation->add_data_extensions();
			$this->slplus->notifications->add_notice( 'info', __( 'Extended contact fields have been activated.', 'slp-power' ) );
		}
	}

	/**
	 * General | Admin | Messages
	 */
	private function general_admin_messages() {
		$new_options['log_import_messages'] = array( 'type' => 'checkbox', 'default' => '0' );
		$this->attach_to_slp( $new_options, array(
			'page'    => 'slp_general',
			'section' => 'admin',
			'group'   => 'messages'
		) );
	}

	/**
	 * General / Server / Security
	 */
	private function augment_general_server_security() {
		$new_options['use_nonces'] = array( 'type' => 'checkbox', 'default' > '1', 'use_in_javascript' => true );
		$this->attach_to_slp( $new_options, array(
			'page'    => 'slp_general',
			'section' => 'server',
			'group'   => 'security'
		) );
	}

	/**
	 * General | App | Features
	 */
	private function augment_general_server_web_app() {
		$new_options['use_sensor'] = array( 'type' => 'checkbox', 'default' > '1', 'use_in_javascript' => true );

		$new_options['use_pages']          = array( 'type' => 'checkbox', 'default' > '0' );
		$new_options['use_contact_fields'] = array(
			'type'              => 'checkbox',
			'default' > '0',
			'call_when_changed' => array( $this, 'activate_contact_fields' )
		);

		$related_to                                     = 'reporting_enabled,delete_history_before_this_date';
		$new_options['reporting_enabled']               = array(
			'type'        => 'checkbox',
			'label'       => __( 'Enable Reporting', 'slp-power' ),
			'description' => __( 'Enables tracking of searches and returned results for up to 13 months. ', 'slp-power' ) .
			                 __( 'The added overhead can increase how long it takes to return location search results.', 'slp-power' ),
			'related_to'  => $related_to
		);
		$new_options['delete_history_before_this_date'] = array(
			'label'       => __( 'Delete Report Data Before', 'slp-power' ),
			'description' => __( 'Delete historical search records older than the date entered. ', 'slp-power' ) .
			                 __( 'Any SQL date will work, usually YYYY-MM-DD will suffice.  ', 'slp-power' ) .
			                 __( 'You can be as specific as YYYY-MM-DD hh:mm:ss.  ', 'slp-power' ) .
			                 '<p>' .
			                 __( 'Save settings to perform the record clean up. ', 'slp-power' ) .
			                 __( 'Up to 10,000 records can be deleted in one operation. ', 'slp-power' ) .
			                 __( 'Make sure your server is up to the task. ', 'slp-power' ) .
			                 '</p><p>' .
			                 __( 'Default: blank (deletes history records older than 13 months on next search)', 'slp-power' ) .
			                 '</p>',
			'related_to'  => $related_to
		);

		$this->attach_to_slp( $new_options, array(
			'page'    => 'slp_general',
			'section' => 'server',
			'group'   => 'web_app_settings',
			'classes' => array( 'quick_save' )
		) );
	}

	/**
	 * Settings / Map / Markers
	 *
	 */
	private function augment_settings_map_markers() {

		$related_to                   = 'map_end_icon,default_icons';
		$new_options['default_icons'] = array( 'related_to' => $related_to, 'type' => 'checkbox' );

		$this->attach_to_slp( $new_options, array(
			'page'    => 'slp_experience',
			'section' => 'map',
			'group'   => 'markers'
		) );
	}

	/**
	 * Settings / Results / Appearance
	 *
	 */
	private function augment_settings_results_appearance() {

		$new_options['show_icon_array'] = array(
			'type'       => 'checkbox',
			'related_to' => 'show_icon_array'
		);

		$new_options['tag_output_processing'] = array(
			'type'    => 'dropdown',
			'default' => 'as_entered',
			'options' => array(
				array( 'label' => __( 'As Entered', 'slp-power' ), 'value' => 'as_entered' ),
				array( 'label' => __( 'Hide Tags', 'slp-power' ), 'value' => 'hide' ),
				array( 'label' => __( 'On Separate Lines', 'slp-power' ), 'value' => 'replace_with_br' ),
			)
		);

		$this->attach_to_slp( $new_options, array(
			'page'    => 'slp_experience',
			'section' => 'results',
			'group'   => 'appearance'
		) );
	}

	/**
	 * Settings / Results / Functionality
	 *
	 */
	private function augment_settings_results_functionality() {

		$related_to                           = 'ajax_orderby_catcount,orderby';
		$new_options['ajax_orderby_catcount'] = array( 'related_to' => $related_to, 'type' => 'checkbox' );

		$this->attach_to_slp( $new_options, array(
			'page'    => 'slp_experience',
			'section' => 'results',
			'group'   => 'functionality'
		) );
	}

	/**
	 * Settings / Search / Appearance
	 *
	 */
	private function augment_settings_search_appearance() {

		// Category Selector Options For Front End
		$related_to                                       = 'search_appearance_category_header,label_category,show_cats_on_search,show_option_all,hide_empty';
		$new_options['search_appearance_category_header'] = array(
			'related_to'  => $related_to,
			'type'        => 'subheader',
			'description' => ''
		);
		$new_options['show_cats_on_search']               = array(
			'related_to'         => $related_to,
			'type'               => 'dropdown',
			'default'            => 'none',
			'get_items_callback' => array(
				$this,
				'get_show_cats_on_search_items'
			)
		);
		$new_options['label_category']                    = array(
			'related_to'  => $related_to,
			'default'     => __( 'Category', 'slp-power' ),
			'allow_empty' => true
		);
		$new_options['show_option_all']                   = array(
			'related_to'  => $related_to,
			'default'     => __( 'Any', 'slp-power' ),
			'allow_empty' => true
		);
		$new_options['hide_empty']                        = array( 'related_to' => $related_to, 'type' => 'checkbox' );

		$related_to = 'search_appearance_tag_header,tag_selections,tag_dropdown_first_entry,tag_selector,tag_autosubmit,tag_label';

		$new_options['search_appearance_tag_header'] = array(
			'related_to'  => $related_to,
			'type'        => 'subheader',
			'label'       => __( 'Tag Selector', 'slp-power' ),
			'description' => ''
		);

		$new_options['tag_selections'] = array(
			'label'       => __( 'Default Tag Selections', 'slp-power' ),
			'description' =>
				__( 'For Hidden or Text tag input enter a default value to be used in the field, if any. ', 'slp-power' ) .
				__( 'For Drop Down tag input enter a comma (,) separated list of tags to show in the search pulldown, mark the default selection with parenthesis (). ', 'slp-power' ) .
				__( 'This is a default setting that can be overriden on each page within the shortcode.', 'slp-power' ),
			'related_to'  => $related_to
		);

		$new_options['tag_dropdown_first_entry'] = array(
			'label'       => __( 'Tag Select All Text', 'slp-power' ),
			'description' => __( 'What should the "any" tag say? ', 'slp-power' ) .
			                 __( 'The first entry on the search by tag pulldown. ', 'slp-power' ) .
			                 __( 'If this is left blank there will be no option to "select all locations regardless of tag" (aka select any) option. ', 'slp-power' ),
			'related_to'  => $related_to

		);

		$new_options['tag_selector'] = array(
			'type'        => 'dropdown',
			'label'       => __( 'Search Form Tag Input', 'slp-power' ),
			'description' => __( 'Select the type of tag input that you would like to see on the search form.', 'slp-power' ),
			'related_to'  => $related_to,
			'options'     => array(
				array( 'label' => __( 'None', 'slp-power' ), 'value' => 'none' ),
				array( 'label' => __( 'Hidden', 'slp-power' ), 'value' => 'hidden' ),
				array( 'label' => __( 'Drop Down', 'slp-power' ), 'value' => 'dropdown' ),
				array( 'label' => __( 'Radio Button', 'slp-power' ), 'value' => 'radiobutton' ),
				array( 'label' => __( 'Text Input', 'slp-power' ), 'value' => 'textinput' ),
			)
		);

		$new_options['tag_autosubmit'] = array(
			'option'      => 'tag_autosubmit',
			'type'        => 'checkbox',
			'label'       => __( 'Tag Autosubmit', 'slp-power' ),
			'description' => __( 'Force the form to auto-submit when the tag is selected with a radio button.', 'slp-power' ),
			'related_to'  => $related_to

		);


		$this->attach_to_slp( $new_options, array(
			'page'    => 'slp_experience',
			'section' => 'search',
			'group'   => 'appearance'
		) );
	}

	/**
	 * Settings | Search | Labels
	 */
	private function search_labels() {
		$related_to               = 'search_appearance_tag_header,tag_selections,tag_dropdown_first_entry,tag_selector,tag_autosubmit,tag_label';
		$new_options['tag_label'] = array(
			'group_params' => $this->group_params,
			'label'        => __( 'Tags Label', 'slp-power' ),
			'description'  => __( 'Search form label to prefix the tag selector.', 'slp-power' ),
			'related_to'   => $related_to
		);
		$this->attach_to_slp( $new_options, array(
			'page'    => 'slp_experience',
			'section' => 'search',
			'group'   => 'labels'
		) );
	}

	/**
	 * Settings | View | Appearance
	 */
	private function view_appearance() {
		$related_to                      = 'show_legend_text';
		$new_options['show_legend_text'] = array( 'related_to' => $related_to, 'type' => 'checkbox', 'default' => '1' );

		$this->attach_to_slp( $new_options, array(
			'page'    => 'slp_experience',
			'section' => 'view',
			'group'   => 'appearance'
		) );
	}

	/**
	 * System wide (not directly settable) options.
	 */
	private function augment_system_wide_options() {
		$new_options['last_geocoded_location'] = array();
		$this->attach_to_slp( $new_options );
	}

	/**
	 * Get the dropdown selections for the category selector.
	 *
	 * @return mixed
	 */
	public function get_show_cats_on_search_items() {
		return SLP_Power_Category_Selector_Manager::get_instance()->get_selectors();
	}

	/**
	 * Pages | Settings | Appearance
	 */
	private function pages_settings_appearance() {
		$new_options['pages_directory_wrapper_css_class'] = array(
			'default'     => 'slp_pages_list',
			'allow_empty' => true
		);
		$new_options['pages_directory_entry_css_class']   = array(
			'default'     => 'slp_page location_details',
			'allow_empty' => true
		);
		$this->attach_to_slp( $new_options, array(
			'page'    => 'slp-pages',
			'section' => 'settings',
			'group'   => 'appearance'
		) );
	}

	/**
	 * Pages | Settings | Behavior
	 */
	private function pages_settings_behavior() {
		$new_options['permalink_flush_needed'] = array(
			'default'             => '0',
			'type'                => 'checkbox',
			'add_to_settings_tab' => false,
			'call_when_changed'   => array( $this, 'flush_permalinks' ),
		);
		$this->attach_to_slp( $new_options, array(
			'page'    => 'slp-pages',
			'section' => 'settings',
			'group'   => 'behavior'
		) );
	}

	/**
	 * Flush the permalinks.
	 */
	public function flush_permalinks() {
		if ( $this->slplus->SmartOptions->permalink_flush_needed->is_true ) {
			$this->slplus->SmartOptions->set( 'permalink_flush_needed', false, true );
			flush_rewrite_rules();
			$this->update_location_pages_urls();
		}
	}

	/**
	 * Update ALL the pages_url in the locations table when permalink changed.
	 *
	 * This makes daily operations MUCH faster but this can be a VERY SLOW process on sites with lots of locations.
	 */
	public function update_location_pages_urls() {
		$sqlCommand = array( 'selectall', 'limit_one', 'manual_offset' );
		$offset     = 0;
		$sqlParams  = array( $offset );
		while ( $location = $this->slplus->database->get_Record( $sqlCommand, $sqlParams, 0 ) ) {
			$this->slplus->currentLocation->set_PropertiesViaArray( $location );
			$original_pages_url                       = $this->slplus->currentLocation->pages_url;
			$this->slplus->currentLocation->pages_url = get_permalink( $this->slplus->currentLocation->linked_postid );
			if ( $original_pages_url !== $this->slplus->currentLocation->pages_url ) {
				$this->slplus->currentLocation->dataChanged = true;
			}
			$this->slplus->currentLocation->MakePersistentIfChanged();
			$sqlParams = array( ++ $offset );
		}
	}

}
