<?php
defined( 'SLPLUS_VERSION' ) || exit;

/**
 * System-wide Pages functionality for Power add on.
 *
 * CALLED only if using_pages is active.
 * CALLED after slp_init for all add-on packs.
 *
 * @property        SLPPower $addon          The add on.
 * @property-read   array $currentPage    Stores the values for the current Store Page. an array of page values*
 *
 */
class SLP_Power_Pages_Global extends SLPlus_BaseClass_Object {
	public $addon;
	private $currentPage = null;

	/**
	 * Things we do when invoked.
	 */
	public function initialize() {
		$this->addon = $this->slplus->addon( 'Power' );
		$this->add_hooks_and_filters();
	}

	/**
	 * Add hooks and filters for WP and SLP , only those after WP's init and slp init().
	 */
	private function add_hooks_and_filters() {
		add_action( 'the_post', array( $this, 'manage_store_page_post_type' ) );
		add_filter( 'slp_menu_items', array(
			$this,
			'add_pages_tab'
		), 11 );  // Priority 11 ensures it runs after the add on filter.
	}

	/**
	 * Add the Pages tab to SLP.
	 *
	 * @return array
	 */
	public function add_pages_tab( $menu_entries ) {
		$power = SLPPower::get_instance();
		if ( ! $power->using_pages ) {
			return $menu_entries;
		}

		$power->createobject_Admin();
		$pages_tab = array(
			'label'    => __( 'Pages', 'slp-power' ),
			'slug'     => 'slp-pages',
			'class'    => $power->admin->pages,
			'function' => 'render_pages_tab',
		);

		$new_tab                           = array( $pages_tab );
		$this->addon->admin_menu_entries[] = $pages_tab;

		return array_merge( (array) $menu_entries, $new_tab );
	}

	/**
	 * Things we do ONE TIME when a post is being processed.
	 *
	 * Set the plugin currentLocation based on the current post ID.
	 */
	function manage_store_page_post_type() {
		if ( empty( $this->currentPage ) ) {
			return;
		}
		if ( get_post_type( get_the_ID() ) !== 'store_page' ) {
			return;
		}
		if ( $this->currentPage['ID'] === null ) {
			return;
		}
		if ( ! ctype_digit( $this->currentPage['ID'] ) ) {
			return;
		}

		$this->slplus->currentLocation->set_PropertiesViaArray(
			$this->slplus->database->get_Record(
				array( 'select_all', 'wherelinkedpostid' ), $this->currentPage['ID']
			)
		);
	}


}
