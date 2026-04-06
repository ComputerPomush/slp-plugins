<?php
defined( 'SLPLUS_VERSION' ) || exit;

/**
 * Class SLPlus_BaseClass_Object
 *
 * @property object $addon
 * @property SLPlus $slplus
 * @property boolean $uses_slplus        Set to true (default) if the object needs access to the SLPlus plugin object.
 */
class SLPlus_BaseClass_Object {
	protected $addon;
	protected $slplus;
	protected $uses_slplus = true;

	/**
	 * @param array $options
	 */
	function __construct( $options = array() ) {
		$this->set_properties( $options );

		if ( $this->uses_slplus ) {
			global $slplus_plugin;
			$this->slplus = $slplus_plugin;
		}

		$this->initialize();
	}

	/**
	 * @param string $property
	 *
	 * @return SLPlus_BaseClass_Object
	 */
	function __get( $property ) {
		switch ( $property ) {
			case 'addon':
				global $slplus_plugin;
				if ( ! isset( $this->addon ) && property_exists( $this, 'slug' ) && ! empty( $this->slug ) && $this->slug !== 'store-locator-plus/store-locator-le.php' ) {
					$this->addon = $slplus_plugin->AddOns->instances[ $this->slug ];
				} else {
					$this->addon = $slplus_plugin;
				}

				return $this->addon;

			default:
				if ( property_exists( $this, $property ) ) {
					return $this->$property;
				}
		}

		return null;
	}

	/**
	 * @param string $property
	 *
	 * @return bool
	 */
	function __isset( $property ) {
		return isset( $this->$property );
	}

	/**
	 * Do these things when this object is invoked. Override in your class.
	 */
	protected function initialize() {
	}

	/**
	 * Return an instance of the object which is also registered to the slplus global less the SLP_ part.
	 *
	 * TODO: PHP7.4 and PHP8.0 the static instance variable returns an object matching $class
	 * TODO: PHP8.1 the static instance continually returns the FIRST object (SLP_Admin_Helper) every time
	 * -- it is like PHP8.1 static instance is bound to base_class-object versus the calling class object
	 *
	 * @param boolean $no_global set to true to skip assigning object to SLP global as a property.
	 * @param mixed $params object init params
	 *
	 * @return SLPlus_BaseClass_Object
	 */
	public static function get_instance( $no_global = false, $params = array() ) {
		static $instance = array();
		$class = get_called_class();

		if ( empty( $instance[ $class ] ) ) {
			$instance[ $class ] = new $class( $params );
			if ( ! $no_global ) {
				$GLOBALS['slplus']->add_object( $instance[ $class ] );  // TODO: make this go away if all slplus-><obj> references become <obj>::get_instance() instead.
			}
		}

		return $instance[ $class ];
	}

	/**
	 * Set our properties.
	 *
	 * @param array $options
	 */
	public function set_properties( $options = array() ) {
		if ( ! empty( $options ) && is_array( $options ) ) {
			foreach ( $options as $property => $value ) {
				if ( property_exists( $this, $property ) ) {
					$this->$property = $value;
				}
			}
		}
	}
}
