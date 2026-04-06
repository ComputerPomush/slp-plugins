<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'SLP_Experience_UI' ) ) {
	require_once( SLPLUS_PLUGINDIR . 'include/base_class.userinterface.php' );

	/**
	 * Holds the UI-only code.
	 *
	 * This allows the main plugin to only include this file in the front end
	 * via the wp_enqueue_scripts call.   Reduces the back-end footprint.
	 *
	 * @property-read   SLP_Experience $addon
	 * @property-read   boolean $attribute_js_options           Options set via shortcode attributers.
	 * @property-read    boolean $is_address_in_url                Used when testing if address was passed succesfully in URL and is valid to process.
	 * @property-read    array $shortcode_options_combo        Shortcode attributes we accept that must be set to a value of A|B\C
	 */
	class SLP_Experience_UI extends SLP_BaseClass_UI {
		public $addon;
		protected $slug = 'slp-experience';

		private $attribute_js_options = array();
		private $is_address_in_url;

		private $shortcode_options_combo = array(
			'city_selector'    => array(
				'discrete' => 'dropdown_discretefilter',
				'input'    => 'dropdown_addressinput',
				'hidden'   => 'hidden'
			),
			'country_selector' => array(
				'discrete' => 'dropdown_discretefilter',
				'input'    => 'dropdown_addressinput',
				'hidden'   => 'hidden'
			),
			'state_selector'   => array(
				'discrete' => 'dropdown_discretefilter',
				'input'    => 'dropdown_addressinput',
				'hidden'   => 'hidden'
			),
		);

		/**
		 * Things we do when starting.
		 */
		final function at_startup() {
			$this->js_requirements[] = 'jquery-ui-core';
			$this->js_requirements[] = 'jquery-ui-dialog';
			$this->js_requirements[] = 'jquery-ui-autocomplete';

			$gmsSetting = $this->slplus->SmartOptions->google_map_style->value;
			if ( ! empty( $gmsSetting ) ) {
				if ( is_null( json_decode( $gmsSetting ) ) ) {
					$this->slplus->SmartOptions->set( 'google_map_style', '' );
				}
			}
		}

		/**
		 * Add popup email form div to the map output.
		 *
		 * @param $slp_html
		 *
		 * @return string
		 */
		function add_email_form( $slp_html ) {
			if ( $this->slplus->SmartOptions->email_link_format->value !== 'popup_form' ) {
				return $slp_html;
			}

			$form_html =
				'<div id="email_form">' .
				'<form id="the_email_form"><fieldset>' .
				wp_nonce_field( 'email_form', 'email_nonce' ) .
				'<input type="hidden" name="sl_id" value="" /> ' .
				"<input type='text' name='email_from' 	 " .
				"placeholder='{$this->addon->options['popup_email_from_placeholder']}'    />" .
				"<input type='text' name='email_subject' " .
				"placeholder='{$this->addon->options['popup_email_subject_placeholder']}' />" .
				"<textarea          name='email_message' " .
				"placeholder='{$this->addon->options['popup_email_message_placeholder']}' ></textarea> " .
				'</fieldset></form>' .
				'</div>';

			return $slp_html . $form_html;
		}

		/**
		 * Add Experience-specific data queries.
		 *
		 * @param string $slug
		 *
		 * @return string
		 */
		public function add_data_queries( $slug ) {
			require_once( SLP_EXPERIENCE_REL_DIR . 'include/module/data/SLP_Experience_Data.php' );

			return $this->slplus->Experience_Data->lookup_sql_by_slug( $slug );
		}

		/**
		 * Add WordPress and SLP hooks and filters.
		 *
		 * WP syntax reminder: add_filter( <filter_name> , <function> , <priority> , # of params )
		 *
		 * Remember: <function> can be a simple function name as a string
		 *  - or - array( <object> , 'method_name_as_string' ) for a class method
		 * In either case the <function> or <class method> needs to be declared public.
		 *
		 * @link http://codex.wordpress.org/Function_Reference/add_filter
		 *
		 */
		public function add_hooks_and_filters() {
			parent::add_hooks_and_filters();

			add_filter( 'shortcode_slp_option', array( SLP_UI_Shortcode_slp_option::get_instance(), 'modify' ) );
			add_filter( 'shortcode_slp_searchelement', array( $this, 'filter_ProcessSearchElement' ) );
			add_filter( 'slp_change_ui_radius_selector', array( $this, 'filter_ui_radius_selector' ) );
			add_filter( 'slp_find_button_text', array( $this, 'change_find_button_text' ) );

			add_filter( 'slp_javascript_results_string', array( $this, 'modify_results_layout' ), 90 );

			add_filter( 'slp_js_options', array( $this, 'modify_js_options' ), 90 );

			add_filter( 'slp_map_html', array( $this, 'filter_ModifyMapOutput' ), 05, 1 );
			add_filter( 'slp_map_html', array( $this, 'filter_ModifyMapOutput_ES' ), 90 );

			add_filter( 'slp_radius_selections', array( $this, 'add_radius_first_entry' ), 10, 2 );

			add_filter( 'slp_regwpml_map_settings_inputs', array( $this, 'set_wmpl_enabled_options' ) );
			add_filter( 'slp_search_default_address', array( $this, 'set_SearchAddressFromRequest' ) );

			add_filter( 'slp_searchlayout', array( $this, 'modify_searchlayout' ) );

			add_filter( 'slp_shortcode_atts', array( $this, 'add_slplus_shortcode_attributes' ), 99, 3 );

			add_filter( 'slp_layout', array( $this, 'createstring_LocatorLayout' ), 5 );
			add_filter( 'slp_ui_headers', array( $this, 'createstring_CSSForHeader' ) );

			add_filter( 'slp_layout', array( $this, 'add_email_form' ) );

		}

		/**
		 * Add a starting entry on the radius selection menu.
		 *
		 * @param string[] $options
		 * @param SLP_UI $ui_object
		 *
		 * @return array
		 */
		public function add_radius_first_entry( $options, $ui_object ) {
			if ( empty( $this->slplus->SmartOptions->first_entry_for_radius_selector->value ) ) {
				return $options;
			}

			$new_options[] = "<option value='{$ui_object->radius_default}' selected='selected'>" . $this->slplus->SmartOptions->first_entry_for_radius_selector . "</option>";

			// Strip out the selected item select attribute in the current option list.
			//
			foreach ( $options as $option ) {
				$new_options[] = preg_replace( '/selected=\'selected\'/', '', $option );
			}

			return $new_options;
		}

		/**
		 * Extends the main SLP shortcode approved attributes list, setting defaults.
		 *
		 * This will extend the approved shortcode attributes to include the items listed.
		 * The array key is the attribute name, the value is the default if the attribute is not set.
		 *
		 * The attribute values are set in the SLP UI class render_shortcode method via WordPress core
		 * functions.
		 *
		 * Any changes that are needed in slp.js need to be recorded here for later use by the 'slp_js_options' filter.
		 *
		 * @param array $valid_attributes current list of approved attributes
		 * @param array $attributes_in_use attributes from the shortcode as entered
		 * @param string $content
		 *
		 * @return  array                           return the allowed attributes (key) and their defaults (value)
		 */
		public function add_slplus_shortcode_attributes( $valid_attributes, $attributes_in_use, $content ) {

			// Checkboxes
			$checkbox_setting = array(
				'allow_addy_in_url', // old-school support now is a Smart Option url_allow_address
				'disable_initial_directory',
				'hide_map',         // SmartOption -- do we still need this?
				'ignore_radius',
				'show_maptoggle'    // SmartOption -- do we still need this?
			);
			foreach ( $checkbox_setting as $attribute_name ) {

				// Attribute Set, Use That
				if ( isset( $attributes_in_use[ $attribute_name ] ) ) {
					$this->attribute_js_options[ $attribute_name ] = $this->slplus->is_CheckTrue( $attributes_in_use[ $attribute_name ], 'string' );
					if ( $attribute_name === 'allow_addy_in_url' ) {
						$this->slplus->SmartOptions->set( 'url_allow_address', $this->slplus->is_CheckTrue( $attributes_in_use[ $attribute_name ], 'boolean' ) );
					}

					// Attribute Not Set, Use Option Setting As Default
					//
				} else {
					$this->attribute_js_options[ $attribute_name ] = isset( $this->addon->options[ $attribute_name ] ) ? $this->addon->options[ $attribute_name ] : '0';
				}
			}

			// Values : Any Setting  with option name mapping.
			//
			// entries with 'slplus::<blah>' will ues the slplus->options[<blah>] value if not set
			// entries that are not blank will use $this->addon->options['blah'] value if not set
			//
			// TODO: check that map_center, map_end_icon, and map_home_icon are all still in slplus->options
			//
			$shortcode_options_unrestricted = array(
				'center_map_at' => 'slplus::map_center',
				'city'          => '',
				'country'       => '',
				'endicon'       => 'slplus::map_end_icon',
				'homeicon'      => 'slplus::map_home_icon',
				'map_region'    => '',
				'orderby'       => '',
				'order_by'      => 'orderby',
				'state'         => '',
			);
			foreach ( $shortcode_options_unrestricted as $attribute_name => $option_name ) {
				$slplus_option = false;
				if ( empty( $option_name ) ) {
					$option_name = $attribute_name;
				}
				if ( strpos( $option_name, 'slplus::' ) === 0 ) {
					$option_name   = str_replace( 'slplus::', '', $option_name );
					$slplus_option = true;
				}

				// Attribute Set, Use That
				//
				if ( isset( $attributes_in_use[ $attribute_name ] ) ) {
					$this->attribute_js_options[ $option_name ] = $attributes_in_use[ $attribute_name ];

					// Attribute Not Set, Use Option Setting As Default
					//
				} else {

					// Don't set this again if it was set by an earlier attribute.
					//
					if ( ! isset( $this->attribute_js_options[ $option_name ] ) ) {
						$this->attribute_special_processing( $option_name );
						$this->attribute_js_options[ $option_name ] = $slplus_option ? $this->slplus->options[ $option_name ] : $this->addon->options[ $option_name ];
					}
				}
			}

			// Values: Restricted Setting
			//
			foreach ( $this->shortcode_options_combo as $attribute_name => $valuePairs ) {
				if ( isset( $attributes_in_use[ $attribute_name ] ) && isset( $valuePairs[ $attributes_in_use[ $attribute_name ] ] ) ) {
					$this->attribute_js_options[ $attribute_name ] = $valuePairs[ $attributes_in_use[ $attribute_name ] ];
				} else {
					$this->attribute_js_options[ $attribute_name ] = $this->addon->options[ $attribute_name ];
				}
			}

			// Set our attribute defaults
			//
			// NOTE: this is actually setting the default = the user specified value (bad idea) OR the add-on option setting OR slplus setting (see exception: initial_results_returned)
			// NOTE: this should only return true defaults here
			//
			$allowed_attributes = $this->attribute_js_options;

			return array_merge( $valid_attributes, $allowed_attributes );
		}

		/**
		 * Special attribute processing.
		 *
		 * @param $option_name
		 */
		private function attribute_special_processing( $option_name ) {
			if ( $option_name === 'map_region' ) {
				$this->addon->options['map_region'] = SLP_Country_Manager::get_instance()->get_country_code();
			}
		}

		/**
		 * Change the find button label to what we have set in this plugin.
		 *
		 * @param string $find_label
		 *
		 * @return string
		 */
		public function change_find_button_text( $find_label ) {
			return $this->slplus->SmartOptions->label_for_find_button->value;
		}

		/**
		 * Create a select drop down based on the SQL commands listed.
		 *
		 * @param string $base_name the base name for our selector
		 * @param string[] $sql_commands the sql command array
		 *
		 * @return string
		 */
		public function create_selector( $base_name, $sql_commands ) {

			// Derive some names
			//
			$option_name = $base_name . '_selector';
			$field_name  = 'addressInput' . ucfirst( $base_name );

			// Selector Value
			if ( ! empty( $this->addon->options[ $option_name ] ) ) {
				$selector_operation = $this->addon->options[ $option_name ];
				$label              = $this->addon->options[ 'label_for_' . $option_name ];
				$first_entry        = $this->addon->options[ 'first_entry_for_' . $option_name ];
			} else {
				$selector_operation = $this->slplus->SmartOptions->$option_name->value;

				$label_property = 'label_for_' . $option_name;
				$label          = $this->slplus->SmartOptions->$label_property->value;

				$fe_property = 'first_entry_for_' . $option_name;
				$first_entry = $this->slplus->SmartOptions->$fe_property->value;
			}

			// Get our field value
			//
			if ( empty( $_GET[ 'sl_' . $base_name ] ) ) {
				$field_value = empty( $this->addon->options[ $base_name ] ) ? '' : $this->addon->options[ $base_name ];
			} else {
				$field_value = $_GET[ 'sl_' . $base_name ];
			}


			// Hidden Selector
			//
			if ( $selector_operation === 'hidden' ) {
				return "<input type='hidden' id='{$field_name}' name='{$field_name}' value='{$field_value}'>";
			}

			// Things we need to build up the selector
			//
			$div_id = 'addy_in_' . $base_name;
			add_filter( 'slp_extend_get_SQL', array( $this, 'add_data_queries' ) );
			$onChange        = ( $selector_operation === 'dropdown_discretefilter' ) ? '' : 'aI=document.getElementById("searchForm").addressInput;if(this.value!=""){oldvalue=aI.value;aI.value=this.value;}else{aI.value=oldvalue;}';
			$hidden_discrete =
				( $selector_operation === 'dropdown_discretefilter' || $selector_operation === 'dropdown_discretefilteraddress' ) ?
					'<input type="hidden" name="' . $option_name . '_discrete" value="1" />' :
					'';

			// Return the selector div
			//
			return
				"<div id='{$div_id}' class='search_item es_category_selector'>" .
				"<label for='{$field_name}'>" . $label . '</label>' .
				"<select id='{$field_name}' name='{$field_name}' onchange='{$onChange}'>" .
				"<option value=''>" . $first_entry . '</option>' .
				$this->create_options_string( $field_value, $sql_commands ) .
				'</select>' .
				$hidden_discrete .
				'</div>';

		}

		/**
		 * Add Custom CSS to the UI header.
		 *
		 * @param string $HTML
		 *
		 * @return string
		 */
		function createstring_CSSForHeader( $HTML ) {
			if ( empty( $this->addon->options['custom_css'] ) ) {
				return $HTML;
			}

			return $HTML . strip_tags( $this->addon->options['custom_css'] );
		}

		/**
		 * Create the hidden search form fields from the inbound widget vars.
		 *
		 * @return string
		 */
		function create_string_hidden_fields_from_widget_vars() {
			$html = '';

			$widget_vars = isset( $_REQUEST['slp_widget'] ) ? $_REQUEST['slp_widget'] : array();
			foreach ( $widget_vars as $key => $value ) {
				$html .=
					sprintf(
						'<input name="slp_widget[%s]" id="slp_widget[%s]" value="%s" type="hidden" />',
						$key, $key, $value
					);
			}

			return $html;
		}

		/**
		 * Take over the search form layout.
		 *
		 * @param string $HTML the original SLP layout
		 *
		 * @return string the modified layout
		 */
		function createstring_LocatorLayout( $HTML ) {
			if ( empty( $this->addon->options['layout'] ) ) {
				return $HTML;
			}

			return $this->addon->options['layout'];
		}

		/**
		 * Create the <option ...> filler for a select drop down based on a returned SQL search.
		 *
		 * @param string $selected_value
		 * @param string[] $sql_commands
		 *
		 * @return string
		 */
		private function create_options_string( $selected_value, $sql_commands ) {
			$myOptions = '';

			add_filter( 'slp_location_where', array( $this->slplus->database, 'filter_SetWhereValidLatLong' ) );
			$cs_array = $this->slplus->database->get_Record( $sql_commands, array(), 0, ARRAY_A, 'get_col' );

			if ( $cs_array ) {
				foreach ( $cs_array as $sl_value ) {
					$sl_value  = preg_replace( '/, $/', '', $sl_value );    // Always strip out ending ,<space> which is not technically valid but what states or countries end with that?
					$selected  = ( $sl_value === $selected_value ) ? ' selected="selected"' : '';
					$myOptions .= "<option value='{$sl_value}' {$selected}>{$sl_value}</option>";
				}
			}

			return $myOptions;
		}

		/**
		 * Generate the HTML for the map on/off slider button if requested.
		 *
		 * @return string HTML for the map slider.
		 */
		private function createstring_MapDisplaySlider() {
			if ( $this->slplus->SmartOptions->show_maptoggle->is_false ) {
				return '';
			}

			return $this->CreateSliderButton( 'maptoggle', $this->slplus->SmartOptions->hide_map->is_false, "jQuery('#map').toggle();jQuery('#slp_tagline').toggle();" );
		}

		/**
		 * Return the HTML for a slider button.
		 *
		 * The setting parameter will be used for several things:
		 * the div ID will be "settingid_div"
		 * the assumed matching label option will be "settingid_label" for WP get_option()
		 * the a href ID will be "settingid_toggle"
		 *
		 * @param string $setting the ID for the setting
		 * @param boolean $isChecked default on/off state of checkbox
		 * @param string $onClick the onClick javascript
		 *
		 * @return string the slider HTML
		 */
		private function CreateSliderButton( $setting, $isChecked, $onClick ) {
			$checked      = ( $isChecked ? 'checked' : '' );
			$onClick      = ( ( $onClick === '' ) ? '' : ' onClick="' . $onClick . '"' );
			$toggle_label = $this->slplus->SmartOptions->label_for_map_toggle->value;

			return
				"<div id='{$setting}_div' class='onoffswitch-block'>" .
				"<span class='onoffswitch-pretext'>{$toggle_label}</span>" .
				"<div class='onoffswitch'>" .
				"<input type='checkbox' name='onoffswitch' class='onoffswitch-checkbox' id='{$setting}-checkbox' $checked>" .
				"<label class='onoffswitch-label' for='{$setting}-checkbox'  $onClick>" .
				'<div class="onoffswitch-inner"></div>' .
				"<div class='onoffswitch-switch'></div>" .
				'</label>' .
				'</div>' .
				'</div>';
		}

		/**
		 * Add our helpers for the userinterface.js.
		 */
		public function enqueue_ui_javascript() {
			$this->js_settings = $this->addon->options;

			if ( $this->slplus->SmartOptions->email_link_format->value === 'popup_form' ) {
				wp_enqueue_style( "wp-jquery-ui-dialog" );
				$this->js_settings['email_form_title'] = $this->addon->options['popup_email_title'];
			}

			parent::enqueue_ui_javascript();
		}

		/**
		 * Modify the map layout.
		 *
		 * @param string $HTML
		 *
		 * @return string
		 */
		function filter_ModifyMapOutput( $HTML ) {

			// Hide Map
			if ( $this->slplus->SmartOptions->hide_map->is_true ) {
				return '<div id="map" style="display:none;"></div>';
			}

			// Map HTML
			$HTML =
				$HTML .
				$this->createstring_MapDisplaySlider() .
				( empty( $this->slplus->SmartOptions->maplayout->value ) ?
					$this->slplus->SmartOptions->maplayout->default :
					$this->slplus->SmartOptions->maplayout->value
				);

			// Widget Search
			if ( ! empty( $_REQUEST['slp_widget']['search'] ) ) {
				$this->slplus->SmartOptions->map_initial_display->value = 'map';
			}

			// Map hidden
			//
			if ( $this->slplus->SmartOptions->map_initial_display->value == 'hide' ) {
				$HTML = '<div id="map_box_map"  style="display:none;">' . $HTML . '</div>';

				// Starting Image
				//
			} else if ( ( $this->slplus->SmartOptions->map_initial_display->value == 'image' ) && ! empty( $this->slplus->SmartOptions->starting_image->value ) ) {

				// Make sure URL starts with the plugin URL if it is not an absolute URL
				//
				$startingImage =
					( ( preg_match( '/^http/', $this->slplus->SmartOptions->starting_image->value ) <= 0 ) ? SLPLUS_PLUGINURL : '' ) .
					$this->slplus->SmartOptions->starting_image->value;

				$HTML =
					'<div id="map_box_image" style="' .
					"width:" . $this->slplus->options_nojs['map_width'] . $this->slplus->options_nojs['map_width_units'] . ';' .
					"height:" . $this->slplus->options_nojs['map_height'] . $this->slplus->options_nojs['map_height_units'] . ';' .
					'">' .
					"<img src='{$startingImage}'>" .
					'</div>' .
					'<div id="map_box_map" style="display:none;">' .
					$HTML .
					'</div>';
			}

			return $HTML;
		}

		/**
		 * Modify the map layout.
		 *
		 * @param string $HTML
		 *
		 * @return string
		 */
		function filter_ModifyMapOutput_ES( $HTML ) {
			if ( $this->is_address_passed_by_URL() ) {
				$HTML =
					str_replace(
						'<div id="map_box_map">',
						'<div id="map_box_map" style="display:block;">',
						$HTML
					);
			}

			return $HTML;
		}

		/**
		 * Perform extra search form element processing.
		 *
		 * @param mixed[] $attributes
		 *
		 * @return array
		 */
		public function filter_ProcessSearchElement( $attributes ) {
			foreach ( $attributes as $name => $value ) {

				switch ( strtolower( $name ) ) {

					case 'dropdown_with_label':
						switch ( $value ) {

							case 'city':
								return array(
									'hard_coded_value' => $this->create_selector( 'city', array(
										'select_city_state',
										'where_valid_city',
										'group_by_city_state',
										'order_by_city_state'
									) )
								);

							case 'country':
								return array(
									'hard_coded_value' => $this->create_selector( 'country', array(
										'select_country',
										'where_valid_country',
										'group_by_country',
										'order_by_country'
									) )
								);

							case 'state':
								return array(
									'hard_coded_value' => $this->create_selector( 'state', array(
										'select_states',
										'where_valid_state',
										'group_by_state',
										'order_by_state'
									) )
								);

							default:
								break;
						}
						break;

					case 'input_with_label':
						switch ( $value ) {
							case 'address':
								return array(
									'hard_coded_value' =>
										$this->slplus->UI->createstring_DefaultSearchDiv_Address( $this->slplus->SmartOptions->address_placeholder )
								);

							case 'name':
								return array(
									'hard_coded_value' =>
										$this->slplus->UI->createstring_InputDiv(
											'nameSearch',
											$this->slplus->SmartOptions->label_for_name,
											$this->slplus->SmartOptions->name_placeholder,
											( $this->slplus->SmartOptions->search_by_name->is_false ),
											'div_nameSearch'
										)
								);
								break;

							default:
								break;
						}
						break;

					/**
					 * Output the value of an option value.
					 * [search_element label_value="label_for_city_selector"]
					 */
					case 'option_value':
						return array(
							'hard_coded_value' => $this->addon->options[ $value ]
						);
						break;

					default:
						break;
				}
			}

			return $attributes;
		}

		/**
		 * Modify the UI radius selector according to the selected behavior.
		 *
		 * behavior 'always_ignore' hides the radius.
		 *
		 * @param $radius_HTML
		 *
		 * @return mixed
		 */
		public function filter_ui_radius_selector( $radius_HTML ) {
			if ( $this->slplus->options_nojs['radius_behavior'] === 'always_ignore' ) {
				return '';        // hide the radius options selector
			}

			return $radius_HTML;
		}

		/**
		 * Return true if the address was passed in via the URL and that option is enabled.
		 *
		 * @return boolean
		 */
		public function is_address_passed_by_URL() {
			if ( ! isset( $this->is_address_in_url ) ) {
				$this->is_address_in_url = $this->slplus->SmartOptions->url_allow_address->is_true && ! empty( $_REQUEST['address'] );
			}

			return $this->is_address_in_url;
		}

		/**
		 * Load the saved results layout.
		 *
		 * @param string $layout_string
		 *
		 * @return  string
		 */
		function load_results_setting( $layout_string ) {
			return $this->slplus->SmartOptions->resultslayout->value;
		}

		/**
		 * Change how the information below the map is rendered.
		 *
		 * @param string $resultString the original layout
		 *
		 * @return string                      the modified layout
		 */
		function modify_results_layout( $resultString ) {
			$resultString = $this->slplus->SmartOptions->resultslayout->value;

			// Hide Distance
			//
			if ( $this->slplus->SmartOptions->hide_distance->is_true ) {
				$pattern      = '/<span class="location_distance">\[slp_location distance_1\] \[slp_location distance_unit\]<\/span>/';
				$resultString = preg_replace( $pattern, '', $resultString, 1 );
			}

			// Hide Country
			//
			if ( $this->slplus->SmartOptions->show_country->is_false ) {
				$pattern      = '/<span class="slp_result_address slp_result_country">\[slp_location country\]<\/span>/';
				$newPattern   = '';
				$resultString = preg_replace( $pattern, $newPattern, $resultString, 1 );
			}

			// Hide Hours
			//
			if ( $this->slplus->SmartOptions->show_hours->is_false ) {
				$pattern      = '/<span class="slp_result_contact slp_result_hours">\[slp_location hours\]<\/span>/';
				$newPattern   = '';
				$resultString = preg_replace( $pattern, $newPattern, $resultString, 1 );
			}

			// Send them back the string
			//
			return $resultString;
		}

		/**
		 * Modify the slplus.options object going into SLP.js
		 *
		 * @used-by \SLP_UI::localize_script
		 *
		 * @triggered-by filter slp_js_options
		 *
		 * @param mixed[] $options
		 *
		 * @return mixed
		 */
		public function modify_js_options( $options ) {
			// Addon Options Cleaned Up A Bit (lowest pri)
			//
			if ( $this->slplus->SmartOptions->hide_bubble->is_true ) {
				$this->slplus->SmartOptions->bubblelayout->value = '';
			}

			// Options From URL Passing (Second Highest Priority)
			//
			$new_options = array();
			if ( ! empty( $_REQUEST['address'] ) && $this->is_address_passed_by_URL() ) {
				$new_options['immediately_show_locations'] = '1';
				$new_options['disable_initial_directory']  = false;
				$new_options['use_sensor']                 = false;
				$new_options['map_center']                 = $_REQUEST['address'];
			}

			// Widget Options (Highest Pri)
			//
			$widget_options = array();
			if ( property_exists( $this->addon, 'widget' ) && $this->addon->widget->is_initial_widget_search( $_REQUEST ) ) {
				$widget_options = array(
					'disable_initial_directory'  => false,
					'immediately_show_locations' => '1',
					'map_center'                 => $this->slplus->SmartOptions->map_center->value,
					'map_initial_display'        => 'map',
					'use_sensor'                 => false
				);

				// Discrete State Output - recenter map
				//
				if ( ! empty( $_REQUEST['slp_widget']['state'] ) ) {
					$widget_options['map_center'] = $_REQUEST['slp_widget']['state'];
				}

				// Set Radius
				//
				if ( isset( $_REQUEST['widget_address'] ) ) {
					if ( ! empty( $_REQUEST['widget_address'] ) ) {
						$widget_options['map_center'] = $_REQUEST['widget_address'];
					}
					if ( isset( $_REQUEST['radius'] ) ) {
						$widget_options['initial_radius'] = $_REQUEST['radius'];
					}
				}
			}

			if ( empty( $this->addon->options['map_region'] ) ) {
				$this->attribute_special_processing( 'map_region' );
			}

			// Lowest Priority On Left: current options (slplus->options), then addon options, then attribute settings, then URL passing stuff, then our widget needs
			$all_options = array_merge( $options, $this->addon->options, $this->attribute_js_options, $new_options, $widget_options );

			// Only keep original options that were changed or "for our JS" settings
			//
			// NOTE: None of the SmartOptions should appear here
			//
			$for_our_js = array(
				'address_autocomplete',
				'disable_initial_directory',
				'selector_behavior',
			);
			foreach ( $all_options as $key => $value ) {
				if ( array_key_exists( $key, $options ) || in_array( $key, $for_our_js ) ) {
					$options[ $key ] = $all_options[ $key ];        // overwrite
				}
			}

			return $options;
		}

		/**
		 * Modify the search form.
		 *
		 * @filters slp_searchlayout
		 *
		 * @param string $html
		 *
		 * @return string
		 */
		public function modify_searchlayout( $html ) {

			// Change the search form layout, hide it, etc.
			// Shortcode attribute takes precedence, then check the map settings hide search form.
			$alwaysOutput = '';

			// Ignore Radius Set, possibly in shortcode attribute, make sure it is on the form.
			// If radius behavior is set to always ignore
			// Or if it is set to ignore on blank address and the address is indeed blank
			// note: this exact logic is used in 2 places, here and sql distance
			//
			$ignore_radius_value = ( $this->slplus->options_nojs['radius_behavior'] == "always_ignore" ? '1' : '0' );
			$alwaysOutput        .= "<input type='hidden' name='ignore_radius' id='ignore_radius' value='{$ignore_radius_value}' />";

			// Always retain hidden fields.
			//
			$type_hidden = '(?=[^>]*type=["\']hidden)';
			$pattern     = "/(<input\b\s+{$type_hidden}[^>]*>)/im";
			preg_match_all( $pattern, $html, $matches );
			foreach ( $matches[0] as $match ) {
				$alwaysOutput .= $match;
			}

			// Hide Search Form
			//
			if ( $this->slplus->SmartOptions->hide_search_form->is_true ) {
				return '<form id="searchForm" onsubmit="return false;">' . $alwaysOutput . '</form>';
			}

			// Custom Layout
			//
			if ( ! empty( $this->slplus->SmartOptions->searchlayout->value ) ) {
				$html = $this->slplus->SmartOptions->searchlayout->value;
			}

			// Add Missing Dropdowns
			if ( $this->slplus->SmartOptions->search_by_name->is_true ) {
				$html .= $this->add_missing_dropdown( 'name', $html );
			}

			if ( ! preg_match( '/\[slp_search_element\s+.*dropdown_with_label="city".*\]/i', $html ) ) {
				$html .= $this->add_missing_dropdown( 'city', $html );
			}

			if ( $this->addon->options['state_selector'] !== 'hidden' ) {
				$html .= $this->add_missing_dropdown( 'state', $html );
			}

			if ( $this->addon->options['country_selector'] !== 'hidden' ) {
				$html .= $this->add_missing_dropdown( 'country', $html );
			}

			$html = $html . $alwaysOutput;

			// Add the Widget post variables to the search form data on the map page : level 10
			//
			if ( is_object( $this->addon->widget ) && $this->addon->widget->is_initial_widget_search( $_REQUEST ) ) {
				$new_html = $this->create_string_hidden_fields_from_widget_vars();
			} else {
				$new_html = '';
			}

			return $new_html . $html;
		}

		/**
		 * Return the missing dropdown HTML.
		 *
		 * @param $field
		 *
		 * @return string
		 */
		public function add_missing_dropdown( $field, $html ) {
			if ( ! preg_match( '/\[slp_search_element\s+.*dropdown_with_label="' . $field . '".*\]/i', $html ) ) {
				return '[slp_search_element dropdown_with_label="' . $field . '"]';
			}

			return '';
		}

		/**
		 * Sets the search form address input on the UI to a post/get var.
		 *
		 * The option "allow address in URL" needs to be on.
		 *
		 * @param string $currentVal
		 *
		 * @return string the default input value
		 */
		function set_SearchAddressFromRequest( $currentVal ) {
			if ( $this->is_address_passed_by_URL() && empty( $currentVal ) ) {
				return stripslashes_deep( $_REQUEST['address'] );
			}

			return $currentVal;
		}

		/**
		 * Set options that are registered with WPML.
		 *
		 * @param $wmpl_options
		 *
		 * @return array
		 */
		function set_wmpl_enabled_options( $wmpl_options ) {
			return
				array_merge(
					$wmpl_options,
					array(
						'popup_email_title',
						'popup_email_from_placeholder',
						'popup_email_subject_placeholder',
						'popup_email_message_placeholder',
					)
				);
		}
	}
}
