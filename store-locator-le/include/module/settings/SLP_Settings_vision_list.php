<?php
defined( 'SLPLUS_VERSION' ) || exit;

require_once( SLPLUS_PLUGINDIR . 'include/unit/SLP_Setting_item.php' );

/**
 * The vision list.
 *
 * @property SLP_Setting_item[] $items
 * @property int $items_to_load_at_start     how many items to load from source to start
 */
class SLP_Settings_vision_list extends SLP_Setting {
	public $items_per_page = 3;
	public $items_to_load_at_start = 9;
	public $items;

	/**
	 * Things we do when starting out.
	 */
	protected function at_startup() {
		$this->get_items();
	}

	/**
	 * Render me.
	 */
	public function display() {
		$this->data['page_len']     = $this->items_per_page;
		$this->data['pages_loaded'] = 3;

		$classes         = $this->get_classes_string();
		$data            = $this->get_data_string();
		$id              = sprintf( 'input-group-%s', $this->id );
		$activate_label  = __( 'Select', 'store-locator-le' );
		$customize_label = __( 'Active', 'store-locator-le' );

		// TODO: $data will contain the data attribute in the format data-<slug>="<value>" and cannot be whitelisted
		// TODO: going to need a way to output this as it can return data-aaa="aaaval" data-bbb="bbbval" etc.
		?>
        <div class='<?php echo esc_attr( $classes ); ?>' <?php echo wp_kses_post( $data ); ?>
             id='<?php echo esc_attr( $id ); ?>'>
            <input type="hidden" id="active_text" value="<?php echo esc_attr( $customize_label ) ?>"/>
            <input type="hidden" id="select_text" value="<?php echo esc_attr( $activate_label ) ?>"/>
            <input type="hidden" id="activating_text"
                   value="<?php echo esc_attr( __( 'Activating...', 'store-locator-le' ) ); ?>"/>
            <input type="hidden" id="<?php echo esc_attr( $this->id ); ?>" name="<?php echo esc_attr( $this->id ); ?>"
                   value="<?php echo esc_attr( $this->value ); ?>"/>
            <div class="vision_list theme-browser">
				<?php
				if ( ! empty( $this->items ) ) {
					/**
					 * @var SLP_Setting_item $item
					 */
					foreach ( $this->items as $item ) {
						$selected = ( $this->value === $item->clean_title );

						if ( $selected ) {
							$item->classes[] = 'active';
						}

						$item_data = $this->get_data_string( $item->data );
						?>
                        <div class="vision_list_item theme <?php echo esc_attr( $this->get_classes_string( $item->classes ) ); ?>"
                             data-style="<?php echo esc_attr( $item->clean_title ) ?>">
                            <div class="vision_list_details">
                                <div class="vision_list_text">
									<?php echo wp_kses_post( $item->description ); ?>
                                </div>
                                <div class="theme-id-container">
                                    <h2 class="theme-name"><?php echo esc_html( $item->title ); ?></h2>
									<?php if ( $item->has_actions ) { ?>
                                        <div class="theme-actions">
											<?php if ( $selected ) { ?>
                                                <a class="button button-secondary customize" <?php echo wp_kses_post( $item_data ); ?>
                                                   aria-label="<?php echo esc_attr( $activate_label ); ?>"><?php echo wp_kses_post( $customize_label ); ?></a>
											<?php } else { ?>
                                                <a class="button button-secondary activate" <?php echo wp_kses_post( $item_data ); ?>
                                                   aria-label="<?php echo esc_attr( $activate_label ); ?>"><?php echo wp_kses_post( $activate_label ); ?></a>
											<?php } ?>

                                        </div>
									<?php } ?>
                                </div>
                            </div>
                        </div>
						<?php
					}

					// Overflow


				} else {
					esc_html_e( 'No items found.', 'store-locator-le' );
				}
				?>
            </div>
			<?php $this->render_description(); ?>
        </div>
		<?php
	}


	/**
	 * Override.  Get the items for the list.
	 */
	protected function get_items() {
	}


}

