<?php
defined( 'ABSPATH' ) || exit;
require_once( SLPLUS_PLUGINDIR . '/include/base/SLP_AddOn_Options.php' );

/**
 * SmartOptions for the Experience add on.
 */
class SLP_Experience_Options extends SLP_AddOn_Options {

	/**
	 * Create our options.
	 */
	protected function create_options() {
		$this->general_ui_url_controls();

		$this->map_appearance();
		$this->map_startup();

		$this->results_interaction();
		$this->results_appearance();
		$this->results_labels();

		$this->search_functionality();
		$this->search_appearance();
		$this->search_labels();

		$this->view();
	}

	/**
	 * Settings | Map | Appearance
	 */
	private function map_appearance() {
		$new_options[ 'hide_map'             ] = array( 'type' => 'checkbox' , 'default' => '0' , 'related_to' => 'maplayout,hide_map,show_maptoggle' );
		$new_options[ 'hide_bubble'          ] = array( 'type' => 'checkbox' , 'default' => '0' , 'related_to' => 'bubblelayout,bubble_footnote,hide_bubble' );
		$new_options[ 'show_maptoggle'       ] = array( 'type' => 'checkbox' , 'default' => '0' , 'related_to' => 'maplayout,hide_map,show_maptoggle,label_for_map_toggle' );
		$new_options[ 'label_for_map_toggle' ] = array( 'default' => __( 'Map', 'slp-experience' ) , 'related_to' => 'maplayout,hide_map,show_maptoggle,label_for_map_toggle' );

		$related = 'map_controls_subheader,map_options_scaleControl,map_options_mapTypeControl,map_option_zoomControl,map_option_fullscreenControl,map_option_hide_streetview';
		$new_options[ 'map_controls_subheader' ] = array( 'type' => 'subheader' , 'related_to' => $related );
		$new_options[ 'map_options_scaleControl' ] = array( 'type' => 'checkbox' , 'default' => '1' , 'related_to' => $related );
		$new_options[ 'map_options_mapTypeControl' ] = array( 'type' => 'checkbox' , 'default' => '1' , 'related_to' => $related );

		$new_options[ 'google_map_style' ] = array( 'type'    => 'textarea', 'use_in_javascript' => true, 'is_text' => true ,);
		$this->attach_to_slp( $new_options , array( 'page'    => 'slp_experience', 'section' => 'map', 'group'   => 'appearance', 'classes'    => array( 'quick_save' ) ) );
	}

	/**
	 * Settings | Map | Startup
	 */
	private function map_startup() {
		$related = 'map_initial_display,starting_image';
		$new_options[ 'map_initial_display'  ] = array( 'related_to' => $related, 'type'    => 'dropdown' , 'default' => 'map' ,
            'options'           => array(
				array( 'label' => __( 'Show Map'            , 'slp-experience' ) , 'value' => 'map'     , 'description' => __( 'Display a map.'                                 , 'slp-experience' ) ) ,
				array( 'label' => __( 'Hide Until Search'   , 'slp-experience' ) , 'value' => 'hide'    , 'description' => __( 'Display nothing until an address is searched.'  , 'slp-experience' ) ) ,
				array( 'label' => __( 'Image Until Search'  , 'slp-experience' ) , 'value' => 'image'   , 'description' => __( 'Display the image set by Starting Image. '      , 'slp-experience' ) ) ,
			)
		);
		$new_options[ 'starting_image'       ] = array( 'related_to' => $related );

		$new_options[ 'no_autozoom'          ] = array( 'type'    => 'checkbox', 'use_in_javascript' => true );
		$new_options[ 'no_homeicon_at_start' ] = array( 'type'    => 'checkbox', 'use_in_javascript' => true , 'default' => true );
		$this->attach_to_slp( $new_options , array( 'page'    => 'slp_experience', 'section' => 'map', 'group'   => 'at_startup', 'classes'    => array( 'quick_save' ) ) );
	}

	/**
	 * Settings | Results | appearance
	 */
	private function results_appearance() {
		$new_options[ 'hide_distance' ] = array( 'type' => 'checkbox' , 'default' => '0' );
		$new_options[ 'show_country'  ] = array( 'type' => 'checkbox' , 'default' => '1' );
		$new_options[ 'show_hours'    ] = array( 'type' => 'checkbox' , 'default' => '1' );
		$new_options[ 'email_link_format'  ] = array( 'type'    => 'dropdown' , 'default' => 'label_link' ,
            'options'           => array(
                array( 'label' => __( 'Email Label with Email Link'  , 'slp-experience' ) , 'value' => 'label_link', 'description' => __( 'Display the email label hyperlinked via mailto.'             , 'slp-experience' ) ) ,
                array( 'label' => __( 'Email Address with Email Link', 'slp-experience' ) , 'value' => 'email_link', 'description' => __( 'Display the email hyperlinked via mailto.'                   , 'slp-experience' ) ) ,
                array( 'label' => __( 'Popup Form Linked To Email'   , 'slp-experience' ) , 'value' => 'popup_form', 'description' => __( 'Display the email label linked to a JavaScript popup form. ' , 'slp-experience' ) ) ,
            )
		);


		$this->attach_to_slp( $new_options , array( 'page' => 'slp_experience' , 'section' => 'results' , 'group' => 'appearance', 'classes'    => array( 'quick_save' ) ) );
	}

	/**
	 * Settings | Results | Interaction
	 */
	private function results_interaction() {
		$new_options[ 'add_tel_to_phone' ] = array( 'type' => 'checkbox' , 'default' => '0' , 'related_to' => 'phone_extension_delimiter' );
		$this->attach_to_slp( $new_options , array( 'page' => 'slp_experience' , 'section' => 'results' , 'group' => 'results_interaction', 'classes'    => array( 'quick_save' ) ) );
	}


	/**
	 * Results / Labels
	 */
	private function results_labels() {
		$new_options[ 'results_box_title' ] = array( 'is_text' => true, );
		$this->attach_to_slp( $new_options , array( 'page'    => 'slp_experience', 'section' => 'results', 'group'   => 'labels', 'classes'    => array( 'quick_save' )) );
	}

	/**
	 * Search / Appearance
	 */
	private function search_appearance() {
		$new_options[ 'hide_search_form' ] = array(
			'type' => 'checkbox' ,
			'default' => '0'
		);
		$new_options[ 'search_by_name'   ] = array(
			'type'         => 'checkbox',
			'label'        => __( 'Show Search By Name', 'slp-experience' ),
			'description'  => __( 'Shows the name search entry box to the user.', 'slp-experience' )
		);
		$this->attach_to_slp( $new_options , array( 'page' => 'slp_experience' , 'section' => 'search' , 'group' => 'appearance', 'classes'    => array( 'quick_save' ) ) );
	}

	/**
	 * Search / Labels
	 */
	private function search_labels() {
		$new_options[ 'first_entry_for_radius_selector' ] = array(  'is_text'    => true, 'related_to' => 'label_radius,first_entry_for_radius_selector' );
		$new_options[ 'address_placeholder'             ] = array(  'is_text'    => true, 'related_to' => 'address_placeholder,label_search,hide_address_entry' );
		$new_options[ 'label_for_find_button'           ] = array(  'is_text'    => true );
		$new_options[ 'label_for_name'                  ] = array(  'is_text'    => true , 'related_to' => 'label_for_name,name_placeholder' );
		$new_options[ 'name_placeholder'                ] = array(  'is_text'    => true , 'related_to' => 'label_for_name,name_placeholder' );
		$new_options[ 'search_box_title'                ] = array(  'is_text'    => true , 'related_to' => 'search_box_title,search_box_subtitle' );
		$this->attach_to_slp( $new_options , array( 'page' => 'slp_experience' , 'section' => 'search' , 'group' => 'labels', 'classes'    => array( 'quick_save' ) ) );
	}

	/**
	 * View
	 */
	private function view() {
		$this->slplus->SmartOptions->bubblelayout->add_to_settings_tab = true;
		$this->slplus->SmartOptions->layout->add_to_settings_tab = true;
		$this->slplus->SmartOptions->maplayout->add_to_settings_tab = true;
		$this->slplus->SmartOptions->resultslayout->add_to_settings_tab = true;
		$this->slplus->SmartOptions->searchlayout->add_to_settings_tab = true;
	}

	/**
	 * General / User Interface / URL Controls
	 */
	private function general_ui_url_controls() {
		$new_options[ 'url_allow_address' ] = array( 'type' => 'checkbox' , 'default' => '0' );
		$this->attach_to_slp( $new_options , array( 'page' => 'slp_general' , 'section' => 'user_interface' , 'group' => 'url_control', 'classes'    => array( 'quick_save' ) ) );
	}

	/**
	 * Search / Functionality
	 */
	private function search_functionality() {
		$new_options[ 'append_to_search' ] = array( 'use_in_javascript' => true );

		$this->attach_to_slp( $new_options , array( 'page' => 'slp_experience' , 'section' => 'search' , 'group' => 'functionality', 'classes'    => array( 'quick_save' ) ) );
	}
}
