<?php
defined( 'ABSPATH' ) || exit;

/**
 * SLP Text Modifier
 */
class SLP_Experience_Admin_Settings_Text extends SLP_Base_Text {

	/**
	 * Descriptions
	 *
	 * @param string $slug
	 * @param string $text
	 *
	 * @return string
	 */
	protected function description( $slug , $text ) {
		switch ( $slug ) {
			case 'add_tel_to_phone'         : return __( 'When checked, wraps the phone number in the results in a tel: href tag.', 'slp-experience' );
			case 'address_placeholder'      : return __( 'Instructions to place in the address input.', 'slp-experience' );
			case 'append_to_search'         : return __( 'Anything you enter in this box will automatically be appended to the address a user types into the locator search form address box on your site.', 'slp-experience' );
			case 'bubblelayout'             : return __( 'Set the HTML and shortcodes that determine how the inside of the map info bubble is rendered. ', 'slp-experience' );
			case 'email_link_format'        : return __( 'How email will be displayed and function in the results list.', 'slp-experience' );
			case 'first_entry_for_radius_selector' : return __( 'Show this text as the first entry on the radius drop down. ', 'slp-experience' ) .
			                                         __( 'If not left blank it will default to this being the selected radius. ', 'slp-experience' ) .
			                                         __( 'The value, when selected, will be set to the default in Radii Options . ', 'slp-experience' );
			case 'google_map_style'         : return __( 'Enter the JSON-style map style rules. ', 'slp-experience' );
			case 'hide_bubble'              : return __( 'Do not show the on-map info bubble.', 'slp-experience' );
			case 'hide_distance'            : return __( 'Do not show the distance to the location in the results table.', 'slp-experience' );
			case 'hide_map'                 : return __( 'Do not show the map on the user interface.', 'slp-experience' );
			case 'hide_search_form'         : return __( 'Do not show the user input on the search page, regardless of the SLP theme used.', 'slp-experience' );
			case 'label_for_find_button'    : return __( 'Enter the text you would like to see on the find locations button.', 'slp-experience' );
			case 'label_for_map_toggle'     : return __( 'The text to put on the map toggle slider.'         , 'slp-experience' );
			case 'label_for_name'           : return __( 'The label that precedes the name input box.', 'slp-experience' );
			case 'layout'                   : return __( 'Set the HTML and shortcodes to determine the macro-level layout for the locator. ', 'slp-experience' ).
			                                         __( 'Does the search box go before or after the map, for example.  ', 'slp-experience' );
			case 'map_initial_display'      : return __( 'Set what to display when the page loads. ', 'slp-experience' );
			case 'map_options_scaleControl' : return __( 'Show the scale on the map. ', 'slp-experience' );
			case 'map_options_mapTypeControl'   : return __( 'Show the map type selector on the map. ', 'slp-experience' );
			case 'maplayout'                : return __( 'Set the HTML and shortcodes use to display the map and tagline. ', 'slp-experience' );
			case 'name_placeholder'         : return __( 'Instructions to place in the name input.', 'slp-experience' );
			case 'no_autozoom'              : return __( 'Use only the "zoom level" setting when rendering the initial map for show locations at startup. ', 'slp-experience' ) .
			                                         __( 'Do not automatically zoom the map to show all initial locations.', 'slp-experience' );
			case 'no_homeicon_at_start'     : return __( 'Do not include the home map marker for the initial map loading with show locations at startup enabled.', 'slp-experience' );
			case 'results_box_title'        : return __( 'Displayed where [slp_option nojs="search_box_title"] appears in layout settings. ', 'slp-experience' ) .
			                                         __( 'Newer plugin styles that support the Experience add on may use this. ' , 'slp-experience' );
			case 'resultslayout'            : return __( 'Set the HTML and shortcodes that determine how each location is rendered in the location list. '  , 'slp-experience' );
			case 'search_box_title'         : return __( 'The label that goes in the search form box header, for plugin themes that support it.', 'slp-experience' ) .
			                                         __( 'Put this in a search form using the [slp_option nojs="search_box_title"] shortcode.', 'slp-experience' );
			case 'searchlayout'             : return __( 'Set the HTML and shortcodes to display the location search form to the user.'                     , 'slp-experience' );
			case 'show_country'             : return __( 'Show the country in the results table address.', 'slp-experience' );
			case 'show_hours'               : return __( 'Show the hours in the results table under the Directions link.', 'slp-experience' );
			case 'show_maptoggle'           : return __( 'Show a map on/off toggle slider on the user interface.', 'slp-experience' );
			case 'starting_image'           : return __( 'If set, this image will be displayed until a search is performed. '                               , 'slp-experience' ).
			                                         __( 'Enter the full URL for the image.'                                                                , 'slp-experience' );
			case 'url_allow_address'        : return __( 'If checked an address can be pre-loaded via a URL string ?address=my+town. '                      , 'slp-experience' ).
			                                         __( 'This will disable the location sensor whenever the address is used in the URL.'                   , 'slp-experience' );
		}
		return $text;
	}

	/**
	 * Labels
	 *
	 * @param string $slug
	 * @param string $text
	 *
	 * @return string
	 */
	protected function label( $slug , $text ) {
		switch ( $slug ) {
			case 'add_tel_to_phone'             : return __( 'Use Dial Link For Phone'  , 'slp-experience' );
			case 'address_placeholder'          : return __( 'Address Placeholder'      , 'slp-experience' );
			case 'append_to_search'             : return __( 'Append This To Searches'  , 'slp-experience' );
			case 'bubblelayout'                 : return __( 'Bubble Layout'            , 'slp-experience' );
			case 'email_link_format'            : return __( 'Email Format'             , 'slp-experience' );
			case 'first_entry_for_radius_selector' : return __( 'Radius First Entry'    , 'slp-experience' );
			case 'google_map_style'             : return __( 'Map Style'                , 'slp-experience' );
			case 'hide_bubble'                  : return __( 'Hide Info Bubble'         , 'slp-experience' );
			case 'hide_map'                     : return __( 'Hide The Map'             , 'slp-experience' );
			case 'hide_distance'                : return __( 'Hide Distance'            , 'slp-experience' );
			case 'hide_search_form'             : return __( 'Hide Search Form'         , 'slp-experience' );
			case 'layout'                       : return __( 'Layout'                   , 'slp-experience' );
			case 'label_for_find_button'        : return __( 'Find Button'              , 'slp-experience' );
			case 'label_for_name'               : return __( 'Name'                     , 'slp-experience' );
			case 'label_for_map_toggle'         : return __( 'Map Toggle Label'         , 'slp-experience' );
			case 'map_initial_display'          : return __( 'Map Display'              , 'slp-experience' );
			case 'map_options_scaleControl'     : return __( 'Map Scale'                , 'slp-experience' );
			case 'map_options_mapTypeControl'   : return __( 'Map Type'                 , 'slp-experience' );
			case 'maplayout'                    : return __( 'Map Layout'               , 'slp-experience' );
			case 'name_placeholder'             : return __( 'Name Placeholder'          , 'slp-experience' );
			case 'no_autozoom'                  : return __( 'Do Not Autozoom'          , 'slp-experience' );
			case 'no_homeicon_at_start'         : return __( 'Hide Home Marker'         , 'slp-experience' );
			case 'results_box_title'            : return __( 'Results Box Title'        , 'slp-experience' );
			case 'resultslayout'                : return __( 'Results Layout'           , 'slp-experience' );
			case 'search_box_title'             : return __( 'Search Box Title'         , 'slp-experience' );
			case 'searchlayout'                 : return __( 'Search Layout'            , 'slp-experience' );
			case 'show_country'                 : return __( 'Show Country'             , 'slp-experience' );
			case 'show_hours'                   : return __( 'Show Hours'               , 'slp-experience' );
			case 'show_maptoggle'               : return __( 'Show Map Toggle'          , 'slp-experience' );
			case 'starting_image'               : return __( 'Starting Image'           , 'slp-experience' );
			case 'url_allow_address'            : return __( 'Allow Address In URL'     , 'slp-experience' );
		}

		return $text;
	}

	/**
	 * Option Default
	 *
	 * @param string $slug
	 * @param string $text
	 *
	 * @return string
	 */
	protected function option_default( $slug , $text ) {
		switch ( $slug ) {
			case 'address_placeholder'              : return '';
			case 'first_entry_for_radius_selector'  : return '';
			case 'google_map_style'                 : return '';
			case 'label_for_find_button'            : return __( 'Find', 'slp-experience' );
			case 'label_for_name'                   : return __( 'Store Name', 'slp-experience' );
			case 'name_placeholder'                 : return '';
			case 'results_box_title'                : return __( 'Your Closest Locations', 'slp-experience' );
			case 'search_box_title'                 : return '';
		}

		return $text;
	}
}
