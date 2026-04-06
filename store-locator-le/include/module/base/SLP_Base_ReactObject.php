<?php
defined( 'SLPLUS_VERSION' ) || exit;

/**
 * Base object used for React driven components.
 *
 * @property-read string $buildDir The build directory where the React script lives.
 * @property-read string $buildURL The build URL where the React script lives.
 * @property string $pageName   The page name for React, update with initialize() override.
 * @property-read string $scriptAssetFile The fully qualified file name for the React script supporting asset PHP file.
 * @property string $scriptHandle Both the WP handle for managing the react script and the build/<handle> subdir in which ot find the react scripts.
 * @property-read string $scriptFile The fully qualified file name for the React script.
 * @property-read string $scriptURL The URL for the React script.
 * @property string $scriptFilebase The name of the edit/view mode script file, default: script.js.
 * @property boolean $uses_slplus Default false try to use SLPlus::get_instance() legacy models that use $this->slplus must set this to true.
 */
class SLP_Base_ReactObject extends SLPlus_BaseClass_Object {
	private $buildDir;
	private $buildURL;
	protected $pageName = 'The Page Name';
	private $scriptAssetFile;
	protected $scriptHandle = 'slp_react_script';
	private $scriptFile;
	protected $scriptFilebase = 'script';
	private $scriptURL;
	protected $uses_slplus = false;

	/**
	 * On invocation
	 * @return void
	 */
	protected function initialize() {
		$dir                   = empty( $this->slug ) ? SLPLUS_PLUGINDIR : $this->addon->dir;
		$url                   = empty( $this->slug ) ? SLPLUS_PLUGINURL : $this->addon->url;
		$this->buildDir        = $dir . 'build/' . $this->scriptHandle . '/';
		$this->buildURL        = $url . '/build/' . $this->scriptHandle . '/';
		$this->scriptAssetFile = $this->buildDir . $this->scriptFilebase . '.asset.php';
		$this->scriptFile      = $this->buildDir . $this->scriptFilebase . '.js';
		$this->scriptURL       = $this->buildURL . $this->scriptFilebase . '.js';
	}

	/**
	 * Pass data from PHP to React JavaScript environment.
	 *
	 * Use this for one-time setup, things that are mostly static in PHP but you need to send to JS.
	 *
	 * @return array
	 */
	protected function get_vars_for_react() {
		return array(
			'env'      => array(
				'mySLP' => defined( 'MYSLP_VERSION' ),
			),
			'pageName' => $this->pageName,
			'url'      => array(
				'main_site'         => SLPlus::get_instance()->url_main_slp_site,
				'slp_documentation' => SLP_Text::get_instance()->get_url( 'slp_docs' ),
				'rest'              => rest_url(),
			),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
		);
	}

	/**
	 * Things we normally want to do before each render
	 * @return void
	 */
	protected function enqueueReact() {
		// -- MATERIALUI -- enqueue styles here to ensure it only happens on this page
		wp_enqueue_style( 'material_icons', 'https://fonts.googleapis.com/icon?family=Material+Icons' );
		wp_enqueue_style( 'material_ui', 'https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&display=swap' );

		// -- include the assets file to get the WordPress Scripts defined dependencies and version ID
		// if scriptHandle is set and the typical generic WP block.json script names of "script" are in play it will enqueue the React stuff
		if ( is_dir( $this->buildDir ) ) {
			if ( is_readable( $this->scriptAssetFile ) && is_readable( $this->scriptFile ) ) {
				$asset = include $this->scriptAssetFile;
				wp_enqueue_script( $this->scriptHandle, $this->scriptURL, $asset['dependencies'], $asset['version'], true );
				wp_add_inline_script( $this->scriptHandle, 'const slpReact = ' . wp_json_encode( $this->get_vars_for_react() ) . ';', 'before' );
			}
		}
	}

	/**
	 * Render admin page - default can be overriden.
	 * Provide the DOM element where the React app will render: slp-full-page-react-app
	 * @return void
	 */
	public function render() {
		$this->enqueueReact();
		?>
        <div class='dashboard-wrapper react-wrapper' id="slp-full-page-react-app"><!-- React App Will Go here --></div>
		<?php
	}
}