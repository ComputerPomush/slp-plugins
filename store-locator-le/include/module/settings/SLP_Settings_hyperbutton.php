<?php
defined( 'ABSPATH' ) || exit;

/**
 * The hyperbutton setting.
 */
class SLP_Settings_hyperbutton extends SLP_Setting {
	public $button_label = '';
	public $onClick;

	/**
	 * The hyperbutton HTML.
	 *
	 * @param string $data
	 * @param string $attributes
	 *
	 * @return string
	 */
	protected function get_content( $data, $attributes ) {
		if ( ! empty ( $this->onClick ) ) {
			$onclick = " onclick='{$this->onClick}' ";
		} else {
			$onclick = '';
		}
		return
			"<a href='#' {$onclick}  class='hyper_button button_{$this->id}' " .
		     "data-cy='{$this->id}' id='{$this->id}' value='{$this->display_value}' " .
		     "{$data}>{$this->button_label}</a>";
	}
}
