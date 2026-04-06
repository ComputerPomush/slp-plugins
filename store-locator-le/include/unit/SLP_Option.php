<?php
defined( 'SLPLUS_VERSION' ) || exit;

require_once( SLPLUS_PLUGINDIR . 'include/base_class.object.php' );

/**
 * A single Smart Option object that is setup via things like SLP_SmartOptions.
 *
 * Remember only private properties are run through the __get(), __set(), and __isset() magic methods.
 *
 * @package StoreLocatorPlus\Settings
 *
 * @property        boolean $add_to_settings_tab  true (default) to show on settings tab on admin panel
 * @property        null||callback  $call_when_time       the function to call when the cron time fires - gets $slug - executed with ->execute_cron_jobs
 * @property        array $classes              an array of class strings to add to the admin render
 * @property        mixed $default              default value (used if the value is empty)
 * @property        string $description          description to show on admin settings page - via text manager
 * @property        array $get_item_callback     object and method in typical array( <obj> , <method>) format that returns an array of items.
 * @property        mixed $initial_value        the initial value from the SLP options
 * @property        boolean $is_false             is this thing false ('0' for checkbox, empty string) :: empty(value)
 * @property        boolean $is_text              true if a gettext translatable string
 * @property        boolean $is_true              is this thing true (NOT '0' for checkbox, not empty string) :: ! empty( value)
 * @property        string $label                label to show on admin settings page - via text manager
 * @property        array $options              array of drop down (select) options array( array( 'label' => __() , 'value'=>'val' ) )
 * @property        string $group                the slug for the settings group where the settings is displayed to admin users
 * @property        string $page                 the slug for the settings page where the option setting is displayed to admin users
 * @property        string $related_to           slugs for related settings
 * @property        string $section              the slug for the settings section where the option setting is displayed to admin users
 * @property        boolean $show_label           true to show label on admin settings page
 * @property        string $slug                 the slug
 * @property        string $type                 type of admin input field
 * @property        boolean $use_in_javascript    use in javascript (true: gets localized in localize_script)
 * @property        mixed $value                current value of option
 */
class SLP_Option extends SLPlus_BaseClass_Object {
	/* @var null||object  $call_when_changed the function to call when the value changes - gets $slug, $old_value, $new_value as parameters - executed with ->execute_change_callbacks */
	public $call_when_changed = null;

	public $add_to_settings_tab = true;
	public $allow_empty = false;
	public $call_when_time = null;
	public $classes = array();
	public $default = '';
	public $get_items_callback;
	public $is_text = false;
	public $options;
	public $related_to;
	public $show_label = true;
	public $slug;
	public $type = 'text';
	public $use_in_javascript = false;

	// Managed with magic method setters and getters.
	private $description;
	private $label;
	private $is_false;
	private $is_true;
	private $initial_value;
	private $value;

	private $page;      // pages have sections (subtabs)
	private $section;   // sections have groups (sublabels)
	private $group;     // groups have individual settings


	/**
	 * Get the value, running it through a filter. "Magic method".
	 *
	 * @param string $property
	 *
	 * @return mixed     null if not set or the value
	 */
	function __get( $property ) {
		if ( property_exists( $this, $property ) ) {

			// Value
			//
			if ( $property === 'value' ) {

				// Not Set Yet: Set value of smart option from SLP options or options_nojs
				//
				if ( ! isset( $this->initial_value ) ) {
					if ( $this->use_in_javascript ) {
						$this->initial_value = array_key_exists( $this->slug, $this->slplus->options ) ? $this->slplus->options[ $this->slug ] : null;
					} else {
						$this->initial_value = array_key_exists( $this->slug, $this->slplus->options_nojs ) ? $this->slplus->options_nojs[ $this->slug ] : null;
					}

					// Default If Empty
					//
					if ( is_null( $this->value ) ) {
						if ( is_null( $this->initial_value ) ) {
							$this->value = $this->default;
						} else {
							$this->value = $this->initial_value;
						}
					}
				}

				/**
				 * FILTER: slp_get_option_<slug>
				 *
				 * Note: Use this instead of slp_option_<slug>_value to set the long term value.
				 *
				 * <slug> can be a slplus->options or slplus->options_nojs array key
				 */
				$this->value = apply_filters( 'slp_get_option_' . $this->slug, $this->value );

				$this->is_true  = ! empty( $this->value );
				$this->is_false = empty( $this->is_true );

				// Label
				//
			} elseif ( $property === 'label' ) {
				if ( ! isset( $this->label ) ) {
					$this->label = $this->slplus->Text->get_text_string( array( 'label', $this->slug ) );
				}

				// Description
				//
			} elseif ( $property === 'description' ) {
				if ( ! isset( $this->description ) ) {
					$this->description =
						$this->slplus->Text->get_text_string( array( 'description', $this->slug ) ) .
						'<span class="view_docs">' .
						$this->slplus->Text->get_web_link( 'docs_for_' . $this->slug ) .
						'</span>';
				}

				// is_true - returns true if the checkbox IS checked, __get sets is_true and is_false property
			} elseif ( $property === 'is_true' ) {
				$this->__get( 'value' );

				// is_false - returns true if the checkbox is NOT checked, __get sets is_true and is_false property
			} elseif ( $property === 'is_false' ) {
				$this->__get( 'value' );
			}

			return $this->$property;
		}

		return null;
	}

	/**
	 * Allow isset to be called on private properties.  "Magic method".
	 *
	 * @param $property
	 *
	 * @return bool
	 */
	public function __isset( $property ) {

		// Always set value. It can't be not set or empty() always fails with the slp_get_option_<slug> filter.
		if ( ( $property === 'value' ) ) {
			$this->__get( 'value' );
		}

		return isset( $this->$property );
	}

	/**
	 * Allow value to be set directly.  "Magic method".
	 *
	 * @param $property
	 *
	 * @param $value
	 *
	 * @return SLP_Option|null
	 */
	public function __set( $property, $value ) {
		if ( property_exists( $this, $property ) ) {
			switch ( $property ) {
				case 'description':
				case 'group':
				case 'label':
				case 'page':
				case 'section':
					$this->$property = $value;
					break;
				case 'value':
					if ( $this->type !== 'checkbox' ) {
						$this->value = $value;
					} else {
						$this->value    = $this->slplus->is_CheckTrue( $value );
						$this->is_true  = ! empty( $this->value );
						$this->is_false = empty( $this->is_true );
					}
					break;
			}

			return $this;
		}
	}

	/**
	 * To String. "Magic method", what to do when asked to output this object...
	 * @return string mixed
	 */
	public function __toString() {
		return $this->__get( 'value' );
	}


}

