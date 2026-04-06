<?php
defined( 'SLPLUS_VERSION' ) || exit;

/**
 * Class SLP_Style
 *
 * The legacy Plugin Style interface  using CSS files with headers.
 * Replaced with the REST API Style Gallery Service in 4.7.3.
 *
 * This is only here to continue to support sites that may have employed custom CSS styles.
 *
 * @var             string $css_dir The theme CSS directory, absolute.
 */
class SLP_Style extends SLPlus_BaseClass_Object {
	private $css_dir;

	// TODO: clean up all the legacy shit in here
	private $addon_settings = array(
		'bubble' => array(
			'slp-experience'   => 'slp-experience[bubblelayout]',
			'slp-enhanced-map' => 'bubblelayout',
		),

		'layout' => array(
			'slp-experience' => 'slp-experience[layout]',
			'slp-pro'        => 'csl-slplus-layout',
		),

		'results' => array(
			'slp-experience'       => 'slp-experience[resultslayout]',
			'slp-enhanced-results' => 'csl-slplus-ER-options[resultslayout]',
		),

		'results_header' => array(
			'slp-premier' => 'options[results_header]',
		),

		'search' => array(
			'slp-experience'       => 'slp-experience[searchlayout]',
			'slp-enhanced-results' => 'csl-slplus-ES-options[searchlayout]',
		),
	);

	/**
	 * Things we do at the start.
	 */
	public function initialize() {
		$this->css_dir        = SLPLUS_PLUGINDIR . 'css/';
		$this->addon_settings = apply_filters( 'slp_plugin_style_addon_settings', $this->addon_settings );
	}
}
