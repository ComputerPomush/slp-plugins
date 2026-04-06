<?php
defined( 'ABSPATH' ) || exit;

class SLP_Power_Admin_Reports2022 extends SLPlus_BaseClass_Object {
	public $slug = 'slp-power';

	/**
	 * Pass data from PHP to React JavaScript environment.
	 * TODO: Make this a primary SLP method with a filter to be able to extend the array
	 *
	 * Use this for one-time setup, things that are mostly static in PHP but you need to send to JS.
	 *
	 * @return array
	 */
	private function get_vars_for_react() {
		return array(
			'pageName' => __( 'Reports', 'slp-power' ),
			'url'      => array(
				'slp_documentation' => $this->slplus->Text->get_url( 'slp_docs' ),
				'rest'              => rest_url(),
			),
		);
	}

	/**
	 * Render admin page
	 * @return void
	 */
	public function render() {
		// -- MATERIALUI -- enqueue styles here to ensure it only happens on this page
		wp_enqueue_style( 'material_icons', 'https://fonts.googleapis.com/icon?family=Material+Icons' );
		wp_enqueue_style( 'material_ui', 'https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&display=swap' );

		// -- include the assets file to get the WordPress Scripts defined dependencies and version ID
		$asset = include $this->addon->dir . 'build/reports.asset.php';
		wp_enqueue_script( 'power_reports2022', $this->addon->url . '/build/reports.js', $asset['dependencies'], $asset['version'], true );

		wp_add_inline_script( 'power_reports2022', 'const slpReact = ' . wp_json_encode( $this->get_vars_for_react() ) . ';', 'before' );
		?>
        <div class='dashboard-wrapper react-wrapper'>
            <div id="slp-power-reports"></div>
        </div>
		<?php
	}
}
