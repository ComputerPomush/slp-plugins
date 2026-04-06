<?php
defined( 'SLPLUS_VERSION' ) || exit;

class SLP_Admin_Info extends SLP_Base_ReactObject {
	protected $scriptHandle = 'slp_infotab';


	/**
	 * Do at invocation...
	 * @return void
	 */
	protected function initialize() {
		$this->pageName = __( 'Info', 'store-locator-le' );
		parent::initialize();
	}

	/**
	 * Render admin page
	 * @return void
	 */
	public function render() {
		$this->enqueueReact();
		?>
        <div class='dashboard-wrapper react-wrapper'>
            <div id="slp-info-tab"></div>
        </div>
		<?php
	}
}
