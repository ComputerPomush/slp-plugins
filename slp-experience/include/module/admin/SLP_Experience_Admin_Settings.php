<?php
defined( 'SLPLUS_VERSION' ) || exit;

/**
 * Class SLP_Experience_Admin_UXSettings
 *
 * @property-read   string $class_prefix
 * @property-read   array $group_params
 * @property-read   string $slug           The WP short slug for this add on.
 * @property-read   SLP_Settings $Settings
 * @property-read   boolean $uses_slplus
 */
class SLP_Experience_Admin_Settings extends SLPlus_BaseClass_Object {
	private $class_prefix = 'SLP_Experience_';
	private $group_params;
	private $Settings;
	protected $slug = 'slp-experience';

	protected $uses_slplus = false;

	/**
	 * Things we do at the start.
	 */
	public function initialize() {
		$this->__get( 'addon' ); // set our addon property, force magic method as this property seems to be in scope here
		add_action( 'slp_build_map_settings_panels', array( $this, 'modify_tab' ), 95 );
		add_filter( 'slp_radius_behavior_description', array( $this, 'extend_radius_behavior_description' ) );
		add_filter( 'slp_radius_behavior_selections', array( $this, 'extend_radius_behavior_selections' ) );
	}

	/**
	 * Modify entries on the tab.
	 *
	 * @param SLPlus_Settings $Settings
	 */
	public function modify_tab( $Settings ) {
		$this->group_params = array( 'plugin' => $this->addon, 'section_slug' => null, 'group_slug' => null );
		$this->Settings     = $Settings;
		$this->modify_map();
		$this->modify_results();
		$this->modify_search();
		$this->modify_view();
	}

	/**
	 * Map
	 */
	private function modify_map() {
		$this->group_params['section_slug'] = 'map';
		$this->modify_map_functionality();
	}

	/**
	 * Map / Functionality
	 */
	private function modify_map_functionality() {
		$this->group_params['group_slug'] = 'functionality';

		$this->Settings->add_ItemToGroup( array(
			'group_params' => $this->group_params,
			'type'         => 'checkbox',
			'label'        => __( 'Use Scrollwheel To Zoom', 'slp-experience' ),
			'description'  => __( 'When checked, using the scrollwheel will zoom the map.', 'slp-experience' ),
			'option'       => 'map_options_scrollwheel',
		) );
	}

	/**
	 * Results
	 */
	private function modify_results() {
		$this->group_params['section_slug'] = 'results';
		$this->modify_results_functionality();
		$this->modify_results_labels();
		$this->modify_results_startup();
	}

	/**
	 * Add Experience / Results / Functionality
	 */
	private function modify_results_functionality() {
		$this->group_params['group_slug'] = 'functionality';

		$this->Settings->add_ItemToGroup( array(
			'group_params' => $this->group_params,
			'type'         => 'dropdown',
			'label'        => __( 'Order Results By', 'slp-experience' ),
			'description'  => __( 'Select a sort order for the results.  Default is Closest..Furthest.', 'slp-experience' ),
			'custom'       => array(
				array(
					'label' => __( 'Featured, Rank, Closest', 'slp-experience' ),
					'value' => 'featured DESC,rank ASC,sl_distance ASC',
				),
				array(
					'label' => __( 'Featured, Rank, A..Z', 'slp-experience' ),
					'value' => 'featured DESC,rank ASC,sl_store ASC',
				),
				array(
					'label' => __( 'Featured Then Closest', 'slp-experience' ),
					'value' => 'featured DESC,sl_distance ASC',
				),
				array(
					'label' => __( 'Featured Then A..Z', 'slp-experience' ),
					'value' => 'featured DESC,sl_store ASC',
				),
				array(
					'label' => __( 'Rank Then Closest', 'slp-experience' ),
					'value' => 'rank ASC,sl_distance ASC',
				),
				array(
					'label' => __( 'Rank Then A..Z', 'slp-experience' ),
					'value' => 'rank ASC,sl_store ASC',
				),
				array(
					'label' => __( 'Closest..Furthest', 'slp-experience' ),
					'value' => 'sl_distance ASC',
				),
				array(
					'label' => __( 'Name A..Z', 'slp-experience' ),
					'value' => 'sl_store ASC',
				),
				array(
					'label' => __( 'Random', 'slp-experience' ),
					'value' => 'random',
				),
			),
			'option'       => 'orderby'
		) );

		$this->Settings->add_ItemToGroup( array(
			'group_params' => $this->group_params,
			'type'         => 'dropdown',
			'label'        => __( 'Featured Locations', 'slp-experience' ),
			'description'  => __( 'When to show feature locations.  ', 'slp-experience' ) .
			                  '<br/>' .
			                  __( 'Show If In Radius - Only when the locations are in radius. ', 'slp-experience' ) .
			                  '<br/>' .
			                  __( 'Always Show - Featured locations always appear in results regardless of distance.', 'slp-experience' ),
			'custom'       => array(
				array( 'label' => __( 'Show If In Radius', 'slp-experience' ), 'value' => 'show_within_radius' ),
				array( 'label' => __( 'Always Show', 'slp-experience' ), 'value' => 'show_always' ),
			),
			'option'       => 'featured_location_display_type'
		) );


	}

	/**
	 * Experience / Results / Labels
	 */
	private function modify_results_labels() {
		$this->group_params['group_slug'] = 'labels';

		$this->Settings->add_ItemToGroup( array(
			'group_params' => $this->group_params,
			'label'        => __( 'Popup Email Title', 'slp-experience' ),
			'description'  => __( 'The title on the popup email dialogue form.', 'slp-experience' ),
			'option'       => 'popup_email_title'
		) );

		$this->Settings->add_ItemToGroup( array(
			'group_params' => $this->group_params,
			'label'        => __( 'Popup From Placeholder', 'slp-experience' ),
			'description'  => __( 'The placeholder for the from field on the popup email dialogue form.', 'slp-experience' ),
			'option'       => 'popup_email_from_placeholder'
		) );

		$this->Settings->add_ItemToGroup( array(
			'group_params' => $this->group_params,
			'label'        => __( 'Popup Subject Placeholder', 'slp-experience' ),
			'description'  => __( 'The placeholder for the subject field on the popup email dialogue form.', 'slp-experience' ),
			'option'       => 'popup_email_subject_placeholder'
		) );

		$this->Settings->add_ItemToGroup( array(
			'group_params' => $this->group_params,
			'label'        => __( 'Popup Message Placeholder', 'slp-experience' ),
			'description'  => __( 'The placeholder for the message box on the popup email dialogue form.', 'slp-experience' ),
			'option'       => 'popup_email_message_placeholder'
		) );
	}

	/**
	 * Add Experience / Results / Startup
	 */
	private function modify_results_startup() {
		$this->group_params['group_slug'] = 'at_startup';

		$this->Settings->add_ItemToGroup( array(
			'group_params' => $this->group_params,
			'type'         => 'checkbox',
			'label'        => __( 'Disable Initial Directory', 'slp-experience' ),
			'description'  => __( 'Do not display the listings under the map when show locations at startup is checked.', 'slp-experience' ),
			'option'       => 'disable_initial_directory'
		) );
	}

	/**
	 * Search
	 */
	private function modify_search() {
		$this->group_params['section_slug'] = 'search';
		$this->modify_search_appearance();
		$this->modify_search_functionality();
		$this->modify_search_labels();
	}

	/**
	 * @return array
	 */
	public function get_search_element_selector_options() {
		return array(
			array(
				'label' => __( 'Hidden', 'slp-experience' ),
				'value' => 'hidden',
			),
			array(
				'label' => __( 'Dropdown, Address Input', 'slp-experience' ),
				'value' => 'dropdown_addressinput',
			),
			array(
				'label' => __( 'Dropdown, Discrete Filter', 'slp-experience' ),
				'value' => 'dropdown_discretefilter',
			),
			array(
				'label' => __( 'Dropdown, Discrete + Address Input', 'slp-experience' ),
				'value' => 'dropdown_discretefilteraddress',
			),
		);
	}

	/**
	 * Search / Appearance
	 */
	private function modify_search_appearance() {
		$this->group_params['group_slug'] = 'appearance';

		$ignore_radius_hint = __( 'Consider setting Radius Behavior to "Use only when address is entered" when using discrete settings here.', 'slp-experience' );

		$csc_selector_options = $this->get_search_element_selector_options();

		$this->Settings->add_ItemToGroup( array(
			'group_params' => $this->group_params,
			'type'         => 'dropdown',
			'label'        => sprintf( __( '%s Selector', 'slp-experience' ), $this->addon->admin->get_value( 'label_for_city_selector' ) ),
			'description'  => __( 'How to display the city selector on the search form. ', 'slp-experience' ) . $ignore_radius_hint,
			'custom'       => $csc_selector_options,
			'option'       => 'city_selector',
			'related_to'   => 'first_entry_for_city_selector'
		) );    // city_selector

		$this->Settings->add_ItemToGroup( array(
			'group_params' => $this->group_params,
			'type'         => 'dropdown',
			'label'        => sprintf( __( '%s Selector', 'slp-experience' ), $this->addon->admin->get_value( 'label_for_state_selector' ) ),
			'description'  => __( 'How to display the state selector on the search form. ', 'slp-experience' ) . $ignore_radius_hint,
			'custom'       => $csc_selector_options,
			'option'       => 'state_selector',
			'related_to'   => 'first_entry_for_state_selector'
		) );    // state_selector

		$this->Settings->add_ItemToGroup( array(
			'group_params' => $this->group_params,
			'type'         => 'dropdown',
			'label'        => sprintf( __( '%s Selector', 'slp-experience' ), $this->addon->admin->get_value( 'label_for_country_selector' ) ),
			'description'  => __( 'How to display the country selector on the search form. ', 'slp-experience' ) . $ignore_radius_hint,
			'custom'       => $csc_selector_options,
			'option'       => 'country_selector',
			'related_to'   => 'first_entry_for_country_selector'
		) );    // country_selector
	}

	/**
	 * Search / Functionality
	 */
	private function modify_search_functionality() {
		$this->group_params['group_slug'] = 'functionality';

		$this->Settings->add_ItemToGroup( array(
			'group_params' => $this->group_params,
			'type'         => 'dropdown',
			'label'        => __( 'Address Autocomplete', 'slp-experience' ),
			'description'  => __( 'When 2 or more characters are typed in the address input, show input suggestions based on location data. ', 'slp-experience' ) . '<br/>' .
			                  __( 'None (default) - do not suggest address input. ', 'slp-experience' ) . '<br/>' .
			                  __( 'Zipcode - suggest matching zip codes. ', 'slp-experience' ) . '<br/>' .
			                  '<br/>' .
			                  __( 'Formatting of the autocomplete not to your liking?  Set the Search Form Style under appearance. ', 'slp-experience' )
		,
			'custom'       => array(
				array(
					'label' => __( 'None', 'slp-experience' ),
					'value' => 'none',
				),
				array(
					'label' => __( 'Zipcode', 'slp-experience' ),
					'value' => 'zipcode',
				),
			),
			'option'       => 'address_autocomplete',
		) );    // address_autocomplete

		$this->Settings->add_ItemToGroup( array(
			'group_params' => $this->group_params,
			'type'         => 'dropdown',
			'label'        => __( 'Selector Behavior', 'slp-experience' ),
			'description'  => __( 'Should the address input be disabled when city, state, or country selectors are used? ', 'slp-experience' ) .
			                  __( 'Allow means address and city/state/zip selectors are both left active at all times. ', 'slp-experience' ) .
			                  __( 'disable means users can interact with either the city/state/zip OR the address but not both at the same time. ', 'slp-experience' ),
			'custom'       => array(
				array(
					'label' => __( 'Allow Selector and Address Input', 'slp-experience' ),
					'value' => 'use_both',
				),
				array(
					'label' => __( 'Disable Address When Using Selector', 'slp-experience' ),
					'value' => 'either_or',
				),
			),
			'option'       => 'selector_behavior',
		) );    // selector_behavior

		$this->Settings->add_ItemToGroup( array(
			'group_params' => $this->group_params,
			'type'         => 'dropdown',
			'label'        => __( 'Search Address Nearest', 'slp-experience' ),
			'description'  => __( 'Worldwide is the default search, letting Google make the best guess which addres the user wants. ', 'slp-experience' ) .
			                  __( 'Current Map will find the best matching address nearest the current area shown on the map.', 'slp-experience' ),
			'custom'       => array(
				array(
					'label' => __( 'Worldwide', 'slp-experience' ),
					'value' => 'world',
				),
				array(
					'label' => __( 'Current Map', 'slp-experience' ),
					'value' => 'currentmap',
				),
			),
			'option'       => 'searchnear',
		) );    // searchnear

	}

	/**
	 * Search / Labels
	 */
	private function modify_search_labels() {
		$this->group_params['group_slug'] = 'labels';

		$this->Settings->add_ItemToGroup( array(
			'group_params' => $this->group_params,
			'label'        => sprintf( __( '%s Selector Label ', 'slp-experience' ), $this->addon->admin->get_value( 'label_for_city_selector' ) ),
			'description'  => __( 'The label that precedes the city selector.', 'slp-experience' ),
			'option'       => 'label_for_city_selector',
			'related_to'   => 'first_entry_for_city_selector'
		) );    // label_for_city_selector

		$this->Settings->add_ItemToGroup( array(
			'group_params' => $this->group_params,
			'label'        => sprintf( __( '%s Selector First Entry ', 'slp-experience' ), $this->addon->admin->get_value( 'label_for_city_selector' ) ),
			'description'  => __( 'The first entry on the search by city selector.', 'slp-experience' ),
			'option'       => 'first_entry_for_city_selector',
			'related_to'   => 'label_for_city_selector'
		) );    // first_entry_for_city_selector

		$this->Settings->add_ItemToGroup( array(
			'group_params' => $this->group_params,
			'label'        => sprintf( __( '%s Selector Label ', 'slp-experience' ), $this->addon->admin->get_value( 'label_for_state_selector' ) ),
			'description'  => __( 'The label that precedes the state selector.', 'slp-experience' ),
			'option'       => 'label_for_state_selector',
			'related_to'   => 'first_entry_for_state_selector'

		) );    // label_for_state_selector

		$this->Settings->add_ItemToGroup( array(
			'group_params' => $this->group_params,
			'label'        => sprintf( __( '%s Selector First Entry ', 'slp-experience' ), $this->addon->admin->get_value( 'label_for_state_selector' ) ),
			'description'  => __( 'The first entry on the search by state selector.', 'slp-experience' ),
			'option'       => 'first_entry_for_state_selector',
			'related_to'   => 'label_for_state_selector'
		) );    // first_entry_for_state_selector

		$this->Settings->add_ItemToGroup( array(
			'group_params' => $this->group_params,
			'label'        => sprintf( __( '%s Selector Label ', 'slp-experience' ), $this->addon->admin->get_value( 'label_for_country_selector' ) ),
			'description'  => __( 'The label that precedes the country selector.', 'slp-experience' ),
			'option'       => 'label_for_country_selector',
			'related_to'   => 'first_entry_for_country_selector'
		) );    // label_for_country_selector

		$this->Settings->add_ItemToGroup( array(
			'group_params' => $this->group_params,
			'label'        => sprintf( __( '%s Selector First Entry ', 'slp-experience' ), $this->addon->admin->get_value( 'label_for_country_selector' ) ),
			'description'  => __( 'The first entry on the search by country selector.', 'slp-experience' ),
			'option'       => 'first_entry_for_country_selector',
			'related_to'   => 'label_for_country_selector'
		) );    // first_entry_for_country_selector
	}

	/**
	 * View
	 */
	private function modify_view() {
		$this->group_params['section_slug'] = 'view';
		$this->modify_view_appearance();
	}

	/**
	 * View / Appearance
	 */
	private function modify_view_appearance() {
		$this->group_params['group_slug'] = 'appearance';

		$this->Settings->add_ItemToGroup( array(
			'group_params' => $this->group_params,
			'type'         => 'textarea',
			'label'        => __( 'Custom CSS', 'slp-experience' ),
			'description'  => __( 'Enter your custom CSS, preferably for SLPLUS styling only but it can be used for any page element as this will go in your page header.', 'slp-experience' ),
			'option'       => 'custom_css',
		) );
	}

	/**
	 * Extend the SLP base plugin radius behavior description text.
	 *
	 * @param string $description
	 *
	 * @return string
	 */
	public function extend_radius_behavior_description( $description ) {
		return $description .
		       __( 'Do not use - always ignore the radius.<br/>', 'slp-experience' ) .
		       __( 'Use only when address is entered - ignore unless user typed an address.', 'slp-experience' ) .
		       __( 'Useful when city, state, country discrete selector is in use.<br/>', 'slp-experience' );
	}

	/**
	 * Extend the SLP base plugin radius behavior selections.
	 *
	 * @param array $selections
	 *
	 * @return array
	 */
	public function extend_radius_behavior_selections( $selections ) {
		$selections[] = array( 'label' => __( 'Do not use', 'slp-experience' ), 'value' => 'always_ignore' );
		$selections[] = array(
			'label' => __( 'Use only when address is entered', 'slp-experience' ),
			'value' => 'ignore_with_blank_addr',
		);

		return $selections;
	}
}
