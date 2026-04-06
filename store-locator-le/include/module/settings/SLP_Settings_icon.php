<?php
defined( 'ABSPATH' ) || exit;

/**
 * The icon setting.
 */
class SLP_Settings_icon extends SLP_Setting {
    public $uses_slplus = true;

    /**
     * Need media library for this.
     */
    public function at_startup() {
        wp_enqueue_media();
        $this->data['base_id'] = $this->id;
    }

    /**
     * The icon HTML.
     *
     * @param string $data
     * @param string $attributes
     *
     * @return string
     */
    protected function get_content( $data, $attributes ) {
    	if ( ! $this->vue ) {
	        return
	            "<input type='text' data-cy='{$this->id}' id='{$this->id}' name='{$this->name}' {$data} value='{$this->display_value}' {$attributes}/>" .
	            $this->media_button_html( $data ) .
	            SLP_Admin_UI::get_instance()->create_string_icon_selector( $this->id, $this->id . '_icon' )
	            ;
    	} else {
		    return
			    "<v-text-field name='{$this->name}' data-cy='{$this->id}' id='{$this->id}' v-model='location.{$this->data_field}' label='{$this->label}' {$data}></v-text-field>" .
			    $this->media_button_html( $data ) .
			    SLP_Admin_UI::get_instance()->create_string_icon_selector( $this->id, $this->id . '_icon' )
			    ;
	    }
    }

    /**
     * Set the media button HTML
     *
     * @param string $data
     *
     * @return string
     */
    private function media_button_html( $data ) {
	    $icon_src = ! empty( $this->display_value ) ? $this->display_value : $this->slplus->SmartOptions->map_end_icon;
        return
             '<div class="wp-media-buttons">' .
                 "<button type='button' class='button insert-media add_media' {$data}>" .
                    '<span class="dashicons dashicons-admin-media"></span>' .
                    __( 'Use Media Image' , 'store-locator-le' ) .
                '</button>' .
	            sprintf( '<span class="icon"><img id="%d_icon" alt="%s icon" src="%s" class="slp_settings_icon"></span>', $this->id, $this->name, $icon_src) .
            '</div>'
            ;
    }

    /**
     * Takover render label.
     */
    protected function render_label() {
        if ( ! $this->show_label ) {
            return;
        }
        ?>
        <div class="label input-label">
            <label for='<?php echo  wp_kses_post($this->name) ?>'><?php echo  wp_kses_post($this->label) ?></label>
        </div>
        <?php
    }

}
