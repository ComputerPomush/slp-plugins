<?php
defined( 'SLPLUS_VERSION' ) || exit;

require_once( SLPLUS_PLUGINDIR . 'include/unit/SLP_Setting_item.php' );

/**
 * Renders am admin UI stack of cards in a vertical list.
 *
 * Used for things like the Settings | View | Locator Style list.
 *
 * @package StoreLocatorPlus\Settings
 *
 */
class SLP_Settings_card_list extends SLP_Setting {
	/* @var int $items_per_page How many items to load per page. */
	public $items_per_page = 3;

	/* @var int $items_to_load_at_start How many items to load from source to start */
	public $items_to_load_at_start = 9;

	/* @var SLP_Setting_item[] $items The array of item objects */
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
		$customize_label = __( 'Reload', 'store-locator-le' );
		?>
        <div class="label input-label">
            <label for="<?php echo esc_attr( $this->name ) ?>">
				<?php echo wp_kses_post( $this->label ) ?>
            </label>
        </div>

        <div class='<?php echo esc_attr( $classes ); ?>' <?php echo wp_kses_post( $data ); ?>
             id='<?php echo esc_attr( $id ); ?>'>
            <input type="hidden" id="active_text" value="<?php echo wp_kses_post( $customize_label ) ?>"/>
            <input type="hidden" id="select_text" value="<?php echo wp_kses_post( $activate_label ) ?>"/>
            <input type="hidden" id="activating_text"
                   value="<?php esc_html_e( 'Activating...', 'store-locator-le' ); ?>"/>
            <input type="hidden" id="<?php echo esc_attr( $this->id ); ?>" name="<?php echo esc_attr( $this->id ); ?>"
                   value="<?php echo wp_kses_post( $this->value ); ?>"/>
            <div class="card_list">
				<?php
				if ( ! empty( $this->items ) ) {
					foreach ( $this->items as $item ) {
						$selected = ( $this->value === $item->clean_title );

						if ( $selected ) {
							$item->classes[] = 'active';
						}

						$item_data = $this->get_data_string( $item->data );
						?>
                        <div class="card theme <?php echo esc_attr( $this->get_classes_string( $item->classes ) ); ?>"
                             data-style="<?php echo esc_attr( $item->clean_title ) ?>">

                            <div class="card-divider">
                                <h2 class="theme-name"><?php echo wp_kses_post( $item->title ); ?></h2>
                            </div>

                            <div class="card-section details">
								<?php echo wp_kses_post( $item->description ); ?>
                            </div>

							<?php if ( $item->has_actions ) { ?>
                                <div class="card-section theme-actions">
									<?php if ( $selected ) { ?>
                                        <a class="button button-secondary customize" <?php echo wp_kses_post( $item_data ); ?>
                                           aria-label="<?php echo esc_attr( $customize_label ); ?>"><?php echo wp_kses_post( $customize_label ); ?></a>
									<?php } else { ?>
                                        <a class="button button-secondary activate" <?php echo wp_kses_post( $item_data ); ?>
                                           aria-label="<?php echo esc_attr( $activate_label ); ?>"><?php echo wp_kses_post( $activate_label ); ?></a>
									<?php } ?>

                                </div>
							<?php } ?>

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

