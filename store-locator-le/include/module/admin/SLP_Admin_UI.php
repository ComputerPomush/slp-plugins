<?php
defined( 'SLPLUS_VERSION' ) || exit;
require_once( SLPLUS_PLUGINDIR . 'include/module/admin_tabs/SLP_BaseClass_Admin.php' );

/**
 * Store Locator Plus basic admin user interface.
 *
 * @property-read   boolean $already_enqueue    True if admin stylesheet enqueued.
 *
 * @property        string[] $cache             Run time cache to make things faster.
 *                                                             used by \SLP_BaseClass_Admin::ok_to_enqueue_admin_js
 *
 * @property-read   boolean $isOurAdminPage     True if we are on an admin page for the plugin.
 * @property        string[] $admin_slugs        The registered admin page hooks for the plugin.
 * @property        string $styleHandle
 */
class SLP_Admin_UI extends SLP_BaseClass_Admin {
	public $cache = array(
		'admin_pages' => array(),
	);

	public $menu_items = array();

	private $already_enqueued = false;

	private $icon_selector_files;
	private $icon_selector_urls;

	private $is_our_admin_page = false;
	private $slp_admin_slugs = array(
		'toplevel_page_slp-network-admin',
		'slp_general',
		'settings_page_csl-slplus-options',
		'slp_general',
		'slp_info',
		'slp_inforeact',
		'slp_manage_locations',
		'slp_experience',
	);

	public $styleHandle;

	/**
	 * Add hooks and filters.
	 *
	 * @uses \SLP_Admin_UI::enqueue_block_editor_assets
	 */
	public function add_hooks_and_filters() {
		parent::add_hooks_and_filters();
		add_filter( 'upload_mimes', array( $this, 'add_json_mime_type' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
	}

	/**
	 * Allows WordPress to process JSON file types
	 *
	 * @used-by \get_allowed_mime_types
	 * @set-via \SLPPower::add_hooks_and_filters
	 *
	 * @param array $existing_mimes
	 *
	 * @return array
	 */
	public function add_json_mime_type( $existing_mimes = array() ) {
		$existing_mimes['json'] = 'text/plain';

		return $existing_mimes;
	}

	/**
	 * Create the admin menu.
	 *
	 * Roles and Caps
	 * manage_slp_admin
	 * manage_slp_user
	 *
	 * WordPress Store Locator Plus Menu Roles & Caps
	 *
	 * Info : manage_slp_admin
	 * Locations: manage_slp_user
	 * Settings: manage_slp_admin
	 * General: manage_slp_admin
	 */
	public function create_admin_menu() {
		do_action( 'slp_admin_menu_starting' );

		// The main hook for the menu
		//
		$this->slplus->admin_page_prefix = sanitize_title( SLPLUS_NAME ) . '_page_';
		$main_hook                       = add_menu_page( SLPLUS_NAME, SLPLUS_NAME, 'manage_slp', SLPLUS_PREFIX, array(
			SLP_Admin_General::get_instance(),
			'display'
		), SLPlus::menu_icon, 31 );

		// Default menu items
		//
		$this->menu_items['slp_manage_locations'] =
			array(
				'label'          => __( 'Locations', 'store-locator-le' ),
				'slug'           => 'slp_manage_locations',
				'class'          => SLP_Admin_Locations::get_instance(),
				'function'       => 'display',
				'screen_options' => 'slp_manage_locations_screen_options',
			);
		$this->menu_items['slp_experience']       =
			array(
				'label'    => __( 'Settings', 'store-locator-le' ),
				'slug'     => 'slp_experience',
				'class'    => SLP_Admin_Settings::get_instance(),
				'function' => 'display'
			);
		$this->menu_items['slp_general']          =
			array(
				'label'    => __( 'General', 'store-locator-le' ),
				'slug'     => 'slp_general',
				'class'    => SLP_Admin_General::get_instance(),
				'function' => 'display'
			);

		add_filter( 'slp_menu_items', array( $this, 'add_info_menu' ), 90 );

		// Third party plugin add-ons
		//
		$new_menu_items = apply_filters( 'slp_menu_items', $this->menu_items );
		foreach ( $new_menu_items as $slug => $menu_item ) {
			if ( ! array_key_exists( $slug, $this->menu_items ) ) {
				$this->menu_items[ $menu_item['slug'] ] = $menu_item;
			}
		}


		// Attach Menu Items To Sidebar and Top Nav
		//
		foreach ( $this->menu_items as $slug => $menu_item ) {

			// Sidebar connect...
			//
			// Differentiate capability for User Managed Locations
			if ( $menu_item['label'] == __( 'Locations', 'store-locator-le' ) ) {
				$slpCapability = 'manage_slp_user';
			} else {
				$slpCapability = 'manage_slp_admin';
			}

			// Using class names (or objects)
			//
			if ( isset( $menu_item['class'] ) ) {
				$this->menu_items[ $slug ]['hook'] = add_submenu_page(
					SLPLUS_PREFIX,
					$menu_item['label'],
					$menu_item['label'],
					$slpCapability,
					$menu_item['slug'],
					array( $menu_item['class'], $menu_item['function'] ),
					$menu_item['position'] ?? null,
				);
				if ( ! empty( $this->menu_items[ $slug ]['screen_options'] ) ) {
					add_action( 'load-' . $this->menu_items[ $slug ]['hook'], array(
						$this,
						$this->menu_items[ $slug ]['screen_options']
					) );
				}

				// Full URL or plain function name
				//
			} else {
				if ( isset( $menu_item['url'] ) && isset( $menu_item['label'] ) ) {
					$this->menu_items[ $slug ]['hook'] = add_submenu_page(
						SLPLUS_PREFIX,
						$menu_item['label'],
						$menu_item['label'],
						$slpCapability,
						$menu_item['url'],
						'',
						$menu_item['position'] ?? null,
					);

				}
			}
		}

		$this->menu_items[ SLPLUS_PREFIX ]['hook'] = $main_hook;

		// Remove the duplicate menu entry
		//
		remove_submenu_page( SLPLUS_PREFIX, SLPLUS_PREFIX );
	}

	/**
	 * @param $menu_entries
	 *
	 * @return array
	 */
	public function add_info_menu( $menu_entries ) {
		$menu_entries['slp_info'] = array(
			'label'    => __( 'Info', 'store-locator-le' ),
			'slug'     => 'slp_info',
			'class'    => SLP_Admin_Info::get_instance(),
			'function' => 'render',
			'position' => 99,
		);

		return $menu_entries;
	}

	/**
	 * @used-by \SLP_Admin_UI::add_hooks_and_filters
	 */
	public function enqueue_block_editor_assets() {
		$script_data = array(
			'api_key'        => SLPlus::get_instance()->get_apikey(),
			'rest_url'       => rest_url( 'store-locator-plus/v2/' ),
			'server_key'     => $this->slplus->SmartOptions->google_server_key->value,
			'initial_radius' => $this->slplus->SmartOptions->initial_radius->value,
			'map_center'     => $this->slplus->SmartOptions->map_center->value,
			'map_height'     => $this->slplus->SmartOptions->map_height->value . $this->slplus->SmartOptions->map_height_units->value,
			'map_width'      => $this->slplus->SmartOptions->map_width->value . $this->slplus->SmartOptions->map_width_units->value,
		);

		wp_enqueue_style( 'store-locator-plus-block',
			plugins_url( 'css/slp-blocks.css', SLPLUS_FILE ),
			array(),
			filemtime( SLPLUS_PLUGINDIR . 'css/slp-blocks.css' ) );
		wp_enqueue_script( 'store-locator-plus-block',
			plugins_url( 'js/slp-blocks.js', SLPLUS_FILE ),
			array(
				'wp-blocks',
				'wp-editor',
				'wp-element',
				'wp-i18n'
			),
			filemtime( SLPLUS_PLUGINDIR . 'js/slp-blocks.js' ), false );
		wp_set_script_translations( 'store-locator-plus-block', 'store-locator-le' );
		wp_add_inline_script(
			'store-locator-plus-block',
			'const slplus = ' . wp_json_encode( $script_data ),
			'before'
		);
	}

	/**
	 * Invoke the AdminUI class.
	 */
	function initialize() {
		$this->addon           = $this->slplus;
		$this->slplus->AdminUI = $this;
		parent::initialize();
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_stylesheet' ), 5 );

		if ( ! empty( $this->slplus->clean['page'] ) && ( $this->slplus->clean['page'] === 'slp_manage_locations' ) ) {
			add_action( 'admin_enqueue_scripts', array( $this->slplus, 'enqueue_google_maps_script' ) );

		}

		$this->styleHandle = $this->slplus->styleHandle;

		// Called after admin_menu and admin_init when the current screen info is available.
		//
		add_action( 'current_screen', array( $this, 'setup_admin_screen' ) );

		/**
		 * HOOK: slp_admin_init_complete
		 */
		do_action( 'slp_admin_init_complete' );
	}

	/**
	 * Sets $this->isOurAdminPage true if we are on a SLP managed admin page.  Returns true/false accordingly.
	 *
	 * @param string $hook
	 *
	 * @return boolean
	 */
	function is_our_admin_page( $hook ) {
		if ( ! is_admin() ) {
			$this->is_our_admin_page = false;

			return false;
		}

		// Our Admin Page : true if we are on the admin page for this plugin
		// or we are processing the update action sent from this page
		//
		$this->is_our_admin_page = ( $hook == SLPLUS_PREFIX . '-options' ) || ( $hook === 'slp_info' ) || strpos( $hook, SLP_ADMIN_PAGEPRE ) === 0;
		if ( $this->is_our_admin_page ) {
			return true;
		}

		/**
		 * Check our menu hooks.
		 */
		foreach ( $this->menu_items as $slug => $meta ) {
			if ( $meta['hook'] === $hook ) {
				$this->is_our_admin_page = true;

				return true;
			}
		}

		// Request Action is "update" on option page
		$this->is_our_admin_page = ! empty( $this->slplus->clean['action'] ) &&
		                           ( $this->slplus->clean['action'] === 'update' ) &&
		                           ! empty( $this->slplus->clean['option_page'] ) &&
		                           ( substr( $this->slplus->clean['option_page'], 0, strlen( SLPLUS_PREFIX ) ) === SLPLUS_PREFIX );
		if ( $this->is_our_admin_page ) {
			return true;
		}

		// This test allows for direct calling of the options page from an
		// admin page call direct from the sidebar using a class/method
		// operation.
		//
		// To use: pass an array of strings that are valid admin page slugs for
		// this plugin.  You can also pass a single string, we catch that too.
		//
		$this->is_our_admin_page = in_array( $hook, apply_filters( 'wpcsl_admin_slugs', $this->slp_admin_slugs ) );

		return $this->is_our_admin_page;
	}

	/**
	 * Render the admin page navbar (tabs)
	 *
	 * @return string
	 * @global mixed[] $submenu the WordPress Submenu array
	 *
	 */
	public function create_Navbar() {
		global $submenu;
		$navbar_items = empty( $submenu[ SLPLUS_PREFIX ] ) ? null : $submenu[ SLPLUS_PREFIX ];
		if ( ! is_array( $navbar_items ) || empty( $navbar_items ) ) {
			return '';
		}

		$li_classes = array();
		foreach ( $this->menu_items as $slug => $menu ) {
			if ( empty ( $menu['label'] ) ) {
				continue;
			}
			$slug                = sanitize_key( $menu['label'] );
			$li_classes[ $slug ] = ! empty( $menu['css_class'] ) ? $menu['css_class'] : '';
		}

		$content =
			'<header id="myslp-header" class="panel-navbar">';
		$content .= '<div class="alert_box">' . $this->slplus->notifications->get_html() . '</div></header>';

		return $content;
	}

	/**
	 * Return the icon selector HTML for the icon images in saved markers and default icon directories.
	 *
	 * @used-by \SLP_Settings_icon::get_content
	 * @used-by \SLP_Power_Category_Manager::render_ExtraCategoryFields
	 * @used-by \MySLP_Admin::admin_hook
	 * @used-by \MySLP_Admin::get_icons_array
	 *
	 * @param string|null $field_id
	 * @param string|null $image_id
	 *
	 * @return string
	 */
	public function create_string_icon_selector( $field_id = null, $image_id = null ) {
		if ( ( $field_id == null ) || ( $image_id == null ) ) {
			return '';
		}

		$htmlStr = '';
		$files   = array();
		$fqURL   = array();

		// If we already got a list of icons and URLS, just use those
		//
		if ( isset( $this->icon_selector_files ) && isset( $this->icon_selector_urls ) ) {
			$files = $this->icon_selector_files;
			$fqURL = $this->icon_selector_urls;

			// If not, build the icon info but remember it for later
			// this helps cut down looping directory info twice (time consuming)
			// for things like home and end icon processing.
			//
		} else {

			// Load the file list from our directories
			//
			// using the same array for all allows us to collapse files by
			// same name, last directory in is highest precedence.
			$iconAssets = apply_filters( 'slp_icon_directories',
				array(
					array(
						'dir' => SLPLUS_UPLOADDIR . 'saved-icons/',
						'url' => SLPLUS_UPLOADURL . 'saved-icons/',
					),
					array(
						'dir' => SLPLUS_ICONDIR,
						'url' => SLPLUS_ICONURL,
					),
				)
			);
			$fqURLIndex = 0;
			foreach ( $iconAssets as $icon ) {
				if ( is_dir( $icon['dir'] ) ) {
					// phpcs:ignore -- no , don't really mean to do a comparison here...
					// phpcs:ignore
					if ( $iconDir = opendir( $icon['dir'] ) ) {
						$fqURL[] = $icon['url'];
						// phpcs:ignore -- no , don't really mean to do a comparison here...
						// phpcs:ignore
						while ( $filename = readdir( $iconDir ) ) {
							if ( strpos( $filename, '.' ) === 0 ) {
								continue;
							}
							$files[ $filename ] = $fqURLIndex;
						};
						closedir( $iconDir );
						$fqURLIndex ++;
					} else {
						$this->slplus->notifications->add_notice(
							9,
							sprintf(
								__( 'Could not read icon directory %s', 'store-locator-le' ),
								$icon['dir']
							)
						);
					}
				}
			}
			ksort( $files );
			$this->icon_selector_files = $files;
			$this->icon_selector_urls  = $fqURL;
		}

		// Build our icon array now that we have a full file list.
		//
		foreach ( $files as $filename => $fqURLIndex ) {
			if (
				( preg_match( '/\.(png|gif|jpg)/i', $filename ) > 0 ) &&
				( preg_match( '/shadow\.(png|gif|jpg)/i', $filename ) <= 0 )
			) {
				$htmlStr .= <<<HTML
					<div class='slp_icon_selector_box'>
					<img data-filename='{$filename}' class='slp_icon_selector' src='{$fqURL[$fqURLIndex]}{$filename}' />
					</div>
HTML;

			}
		}

		// Wrap it in a div
		//
		if ( $htmlStr != '' ) {
			$htmlStr = '<div id="' . $field_id . '_icon_row" class="slp_icon_row">' . $htmlStr . '</div>';

		}

		return $htmlStr;
	}

	/**
	 * Enqueue the admin stylesheet when needed.
	 *
	 * @param string $hook Current page hook.
	 */
	public function enqueue_admin_stylesheet( $hook ) {
		if ( ! $this->is_our_admin_page( $hook ) || $this->already_enqueued ) {
			return;
		}

		$this->slplus->load_jquery_theme( 'base' );
		$this->enqueue_admin_css( $hook );
		wp_enqueue_style( 'font-awesome', SLPLUS_PLUGINURL . '/css/admin/font-awesome.min.css', array(), filemtime( SLPLUS_PLUGINDIR . '/css/admin/font-awesome.min.css' ), 'all' );

		$this->already_enqueued = true;
	}

	/**
	 * Attach the wanted screen object and save the settings if appropriate.
	 *
	 * @param WP_Screen $current_screen The current screen object.
	 */
	public function setup_admin_screen( $current_screen ) {
		switch ( $current_screen->id ) {
			case $this->slplus->admin_page_prefix . 'slp_experience':
				SLP_Admin_Settings::get_instance()->save_options();
				break;
			case 'toplevel_page_slp-network-admin-network':
			case $this->slplus->admin_page_prefix . 'slp_general':
				SLP_Admin_General::get_instance()->save_options();
				break;
			case $this->slplus->admin_page_prefix . 'slp_info':
				SLP_Admin_Info::get_instance();
				break;
			case $this->slplus->admin_page_prefix . 'slp_manage_locations':
				SLP_Admin_Locations::get_instance()->screen = $current_screen->id;
				break;
			default:
				break;
		}
	}

	/**
	 * Add Manage Locations Screen Options
	 */
	public function slp_manage_locations_screen_options() {
		SLP_Admin_Locations::get_instance()->add_screen_options();
	}
}
