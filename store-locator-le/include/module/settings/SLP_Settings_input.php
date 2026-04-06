<?php
defined( 'ABSPATH' ) || exit;

/**
 * The input setting.
 */
class SLP_Settings_input extends SLP_Setting {


	/**
	 * The input HTML.
	 *
	 * @param string $data
	 * @param string $attributes
	 *
	 * @return string
	 */
	protected function get_content( $data, $attributes ) {
		if ( ! $this->vue ) {
			return "<input type='text' data-cy='{$this->id}' id='{$this->id}' name='{$this->name}' value='{$this->display_value}' {$data} {$attributes}>";
		} else {
			return "<v-text-field data-cy='{$this->id}' name='{$this->name}' id='{$this->id}' v-model='location.{$this->data_field}' label='{$this->label}'></v-text-field>";
		}
	}


}
