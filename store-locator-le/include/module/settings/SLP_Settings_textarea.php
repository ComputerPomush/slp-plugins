<?php
defined( 'ABSPATH' ) || exit;

/**
 * The checkbox setting.
 */
class SLP_Settings_textarea extends SLP_Setting {

	/**
	 * The checkbox HTML.
	 *
	 * @param string $data
	 * @param string $attributes
	 *
	 * @return string
	 */
	protected function get_content( $data, $attributes ) {
		if ( ! $this->vue ) {
			return
				"<textarea  name='{$this->name}' data-cy='{$this->id}' id='{$this->id}' cols='50' rows='5' {$data} {$attributes}>" .
				$this->display_value .
				'</textarea>';
		} else {
			return "<v-textarea name='{$this->name}' data-cy='{$this->id}' id='{$this->id}' v-model='location.{$this->data_field}' label='{$this->label}'></v-textarea>";
		}
	}

	/**
	 * Set the display value
	 */
	protected function set_display_value() {
		if ( isset( $this->display_value ) ) {
			return;
		}
		$this->display_value =  esc_textarea( $this->value );
	}
}
