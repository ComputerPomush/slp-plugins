<?php
defined( 'ABSPATH' ) || exit;
require_once( SLPLUS_PLUGINDIR . '/include/base/SLP_AddOn_Options.php' );

/**
 * Class SLP_Extended_Data_Manager_Options
 *
 * @package StoreLocatorPlus\SLP_Extended_Data_Manager\Options
 * @author DeBAAT <slp-edm@de-baat.nl>
 * @copyright 2022 De B.A.A.T. - Charleston Software Associates, LLC
 */
class SLP_Extended_Data_Manager_Options extends SLP_AddOn_Options {

	/**
	 * Create our options.
	 */
	protected function create_options() {

		global $slplus;

		$this->addon  = SLP_Extended_Data_Manager_Get_Instance();
		$this->slplus = $slplus;
		// $this->debugMP('pr', __FUNCTION__ . ' started with _REQUEST: ', $_REQUEST );

		SLP_Extended_Data_Manager_Text::get_instance();

		// General Extended_Data_Manager options
		$this->augment_edm_general_data_edm_general_settings();

	}

	/**
	 * General / Data / Packaged Data Extensions
	 */
	private function augment_edm_general_data_edm_general_settings() {
		$new_options = array();

		//$new_options['edm_elements_per_page'] = array( 'is_text' => true,    'default' => '20' );

		//$this->attach_to_slp( $new_options , array( 'page'=> 'slp_general','section' => 'data', 'group' => 'edm_general_settings' ) );
	}

	/**
	 * Simplify the plugin debugMP interface.
	 *
	 * Typical start of function call: $this->debugMP('msg',__FUNCTION__);
	 *
	 * @param string $type
	 * @param string $hdr
	 * @param string $msg
	*/
	function debugMP($type,$hdr,$msg='') {
		if (($type === 'msg') && ($msg!=='')) {
			$msg = esc_html($msg);
		}
		if (($hdr!=='')) {   // Adding __CLASS__ to non-empty hdr
			$hdr = __CLASS__ . '::' . $hdr;
		}
		SLP_Extended_Data_Manager_debugMP($type,$hdr,$msg,NULL,NULL,true);
	}

}