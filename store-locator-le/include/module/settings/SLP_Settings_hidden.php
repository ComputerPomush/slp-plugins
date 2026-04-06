<?php
defined( 'ABSPATH' ) || exit;

/**
 * The hidden setting.
 */
class SLP_Settings_hidden extends SLP_Setting {
    public function display() {
        ?>
        <input type='hidden'
               data-cy='<?php echo esc_attr($this->id); ?>'
               id='<?php echo esc_attr($this->id); ?>'
               name='<?php echo wp_kses_post($this->name); ?>'
               data-field='<?php echo wp_kses_post($this->data_field); ?>'
               value='<?php echo wp_kses_post($this->display_value); ?>'
            >
        <?php
    }
}
