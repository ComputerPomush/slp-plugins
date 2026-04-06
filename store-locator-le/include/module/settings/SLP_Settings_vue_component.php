<?php
defined( 'SLPLUS_VERSION' ) || exit;

/**
 * The input setting.
 */
class SLP_Settings_vue_component extends SLP_Setting {
	public $component;
	public $wrapper = false;

	/**
	 * The input HTML.
	 *
	 * @param string $data
	 * @param string $attributes
	 *
	 * @return string
	 */
	protected function get_content( $data, $attributes ) {
		return SLP_Template_Vue::get_instance()->get_content( $this->component );
	}
}
