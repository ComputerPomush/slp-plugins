<?php
defined( 'ABSPATH' ) || exit;

/**
 * A base class that helps add-on packs separate UI functionality.
 *
 * Add on packs should include and extend this class.
 *
 * This allows the main plugin to only include this file when NOT in admin mode.
 *
 * @property    SLP_BaseClass_Addon $addon
 * @property    SLPlus $slplus
 * @property    string[] $js_requirements    An array of the JavaScript hooks that are needed by the userinterface.js script.
 *              userinterface.js is only loaded if the file exists in the include directory.
 * @property    string[] $js_settings        JavaScript settings that are to be localized as a <slug>_settings JS variable.
 */
class SLP_BaseClass_UI extends SLPlus_BaseClass_Object {
	public $addon;
	public $jsHandle;
	protected $js_requirements = array( 'jquery', 'slp_core' );
	public $js_settings = array();
	protected $slplus;

	/**
	 * Instantiate the admin panel object.
	 */
	function initialize() {
		$this->at_startup();
		$this->add_hooks_and_filters();
	}

	/**
	 * Add the plugin specific hooks and filter configurations here.
	 *
	 * Should include WordPress and SLP specific hooks and filters.
	 */
	function add_hooks_and_filters() {
		add_action( 'slp_after_render_shortcode', array( $this, 'enqueue_ui_javascript' ) );
		add_action( 'slp_after_render_shortcode', array( $this, 'enqueue_ui_css' ) );
	}

	/**
	 * Things we want our add on packs to do when they start.
	 */
	protected function at_startup() {
		// Add your startup methods you want the add on to run here.
	}

	/**
	 * If the file userinterface.css exists, enqueue it.
	 */
	public function enqueue_ui_css() {
		if ( file_exists( $this->addon->dir . 'css/userinterface.css' ) ) {
			wp_enqueue_style( $this->addon->slug . '_userinterface_css', $this->addon->url . '/css/userinterface.css', array(), filemtime( $this->addon->dir . '/css/userinterface.css' ) );
		}
	}

	/**
	 * Enqueue the  userinterface.js scripts (./js/<slug>_userinterface.min.js preferred).
	 *
	 * Minified take precedence.
	 *
	 * Called from \MySLP_REST_API::get_options() to send data to front-end/locations.js for embeding maps.
	 *
	 * Look first in ./js/<slug>_userinterface.js then ./js/userinterface.js then ./include/userinterface.js
	 */
	public function enqueue_ui_javascript() {
		$this->jsHandle = $this->addon->slug . '_userinterface';
		$enq            = false;

		$files = array(
			'js/' . $this->addon->short_slug . '_userinterface.min.js',
			'js/' . $this->addon->short_slug . '_userinterface.js',
			'js/userinterface.min.js',
			'js/userinterface.js',
			'include/userinterface.min.js',
			'include/userinterface.js'
		);
		foreach ( $files as $file ) {
			if ( WP_DEBUG && strpos( $file, '.min.js' ) > 1 ) {
				continue;
			}
			if ( file_exists( $this->addon->dir . $file ) ) {
				wp_enqueue_script( $this->jsHandle, $this->addon->url . '/' . $file, $this->js_requirements, filemtime( $this->addon->dir . $file ), false );
				$enq = true;
				break;
			}
		}

		if ( $enq ) {
			$this->js_settings['locations'] = array( 'get_option' => rest_url( 'store-locator-plus/v2/options/' ), );
			$dataArrayVar                   = preg_replace( '/\W/', '', $this->addon->short_slug ) . '_settings';
			wp_add_inline_script(
				$this->jsHandle,
				'const ' . $dataArrayVar . ' = ' . wp_json_encode( $this->js_settings )
			);
		}
	}
}
