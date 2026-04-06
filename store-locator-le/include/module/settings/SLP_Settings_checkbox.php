<?php
defined( 'ABSPATH' ) || exit;
/**
 * The checkbox setting.
 */
class SLP_Settings_checkbox extends SLP_Setting {
	public $uses_slplus = true;

	/**
	 * The checkbox HTML.
	 *
	 * @param string $data
	 * @param string $attributes
	 *
	 * @return string
	 */
	protected function get_content( $data, $attributes ) {
		$checked = $this->slplus->is_CheckTrue( $this->display_value ) ? 'checked' : '';
		if ( ! $this->vue ) {
			return "<input type='checkbox' data-cy='{$this->id}' id='{$this->id}'  name='{$this->name}' {$data} value='1' {$checked} {$attributes}/>";
		} else {
			return '<v-checkbox
            v-model="location.' . $this->data_field . '"
            v-bind:label="location_manager.text.' . $this->data_field . '"
            v-bind:value="1"
            data-cy="' . $this->id . '"                        
            id="' . $this->id . '"
            name="' . $this->name . '"
            true-value="1"
            false-value="0"></v-checkbox>';
		}
	}
}
