<?php
defined( 'SLPLUS_VERSION' ) || exit;

/**
 * A base class that helps add-on packs separate admin functionality.
 *
 * Add on packs should include and extend this class.
 *
 * This allows the main plugin to only include this file in admin mode
 * via the admin_menu call.   Reduces the front-end footprint.
 *
 * @property        SLP_BaseClass_Activation $activation
 * @property        SLP_BaseClass_Addon $addon
 * @property        string $admin_page_slug        The slug for the admin page.
 * @property        string $current_group          Current settings group.
 * @property        string $current_section        Current settings section.
 * @property        string[] $js_requirements        An array of the JavaScript hooks that are needed by the userinterface.js script.
 * @property        string[] $js_settings            JavaScript settings that are to be localized as a <slug>_settings JS variable.
 * @property-read   array $saved_option_state     What the option values where before we did admin save.
 * @property        SLP_Settings $settings_interface
 * @property        SLPlus $slplus
 *
 * @property        array $settings_pages         The settings pages we support and the checkboxes that live there:
 *                                              $settings_pages[<slug>] = array( 'checkbox_option_1' , 'checkbox_option_2', ...)  // <slug> = the page name
 *
 * @property-read   boolean $ok_to_nq_js             keep track of whether we have looked up this capability before or not.
 */
class SLP_BaseClass_Admin extends SLP_Object_With_Objects {
	protected $activation;
	public $addon;
	protected $admin_page_slug;
	public $current_group;
	public $current_section;
	protected $js_requirements = array( 'jquery' );
	public $js_settings = array();
	private $saved_option_state = array();
	protected $settings_interface;
	protected $settings_pages;
	private $settings_have_been_saved = false;
	private $ok_to_nq_js;

	/**
	 * Add a checkbox to the settings.
	 *
	 * @param string $label
	 * @param string $description
	 * @param string $setting
	 */
	public function add_checkbox( $label, $description, $setting ) {
		$this->add_setting( $label, $description, $setting, 'checkbox' );
	}

	/**
	 * Add a dropdown to the settings.
	 *
	 * @param string $label
	 * @param string $description
	 * @param array $items
	 * @param string $setting
	 */
	public function add_dropdown( $label, $description, $items, $setting ) {

		$this->settings_interface->add_ItemToGroup( array(
			'label'       => $label,
			'description' => $description,
			'setting'     => $this->addon->option_name . '[' . $setting . ']',
			'selectedVal' => $this->get_value( $setting ),
			'custom'      => $items,
			'type'        => 'dropdown',
			'use_prefix'  => false,
			'section'     => $this->current_section,
			'group'       => $this->current_group,
		) );
	}

	/**
	 * Add a input to the settings.
	 *
	 * @param string $label
	 * @param string $description
	 * @param string $setting
	 */
	public function add_input( $label, $description, $setting ) {
		$this->add_setting( $label, $description, $setting, 'text' );
	}

	/**
	 * Add setting.
	 *
	 * @param string $label
	 * @param string $description
	 * @param string $setting
	 * @param string $type
	 */
	private function add_setting( $label, $description, $setting, $type ) {
		$this->settings_interface->add_ItemToGroup( array(
			'label'       => $label,
			'description' => $description,
			'setting'     => $this->addon->option_name . '[' . $setting . ']',
			'value'       => $this->get_value( $setting ),
			'type'        => $type,
			'use_prefix'  => false,
			'section'     => $this->current_section,
			'group'       => $this->current_group,
		) );
	}

	/**
	 * Add sublabel.
	 *
	 * @param string $label
	 * @param string $description
	 */
	public function add_sublabel( $label, $description = '' ) {
		$this->settings_interface->add_ItemToGroup( array(
			'section'     => $this->current_section,
			'group'       => $this->current_group,
			'label'       => $label,
			'description' => $description,
			'type'        => 'subheader',
			'show_label'  => false,
		) );
	}

	/**
	 * Add a textarea to the settings.
	 *
	 * @param string $label
	 * @param string $description
	 * @param string $setting
	 */
	public function add_textarea( $label, $description, $setting ) {
		$this->add_setting( $label, $description, $setting, 'textarea' );
	}

	/**
	 * Return an array of checkbox names for the current settings page being processed.
	 *
	 * If settings_pages is in use (and it should be) - return the array of checkbox names for the currently active tab (page slug).
	 *
	 * @return string[]
	 */
	private function get_my_checkboxes() {
		if ( isset( $this->settings_pages ) && isset( $this->settings_pages[ $this->slplus->clean['page'] ] ) ) {
			return $this->settings_pages[ $this->slplus->clean['page'] ];
		}

		return array();
	}

	/**
	 * Run these things during invocation. (called from base object in __construct)
	 */
	protected function initialize() {
		parent::initialize();
		if ( empty( $this->addon ) ) {
			$this->addon = $this->slplus->find_addon( get_called_class() );
		}
		if ( ! isset( $this->admin_page_slug ) || empty( $this->admin_page_slug ) ) {
			$this->admin_page_slug = $this->addon->short_slug;
		}

		$this->set_addon_properties();
		// TODO: this is called once for the add-on (power plugin) and again for the base plugin (SLP) when deactivating the add on
		if ( ! $this->being_deactivated() ) {
			$this->do_admin_startup();
		}
		$this->add_hooks_and_filters();     // TODO: shouldn't this be moved into the ! being_deactivated() test above?
	}

	/**
	 * Add the plugin specific hooks and filter configurations here.
	 *
	 * Add your hooks and filters in the class that extends this base class.
	 * Then call parent::add_hooks_and_filters()
	 *
	 * Should include WordPress and SLP specific hooks and filters.
	 *
	 * @uses \SLP_BaseClass_Admin::enqueue_admin_javascript
	 */
	function add_hooks_and_filters() {
		add_filter( 'plugin_row_meta', array( $this, 'add_meta_links' ), 10, 2 );
		add_filter( 'wpcsl_admin_slugs', array( $this, 'filter_AddOurAdminSlug' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_javascript' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_css' ) );
	}

	/**
	 * Add meta links.
	 *
	 * @param string[] $links
	 * @param string $file
	 *
	 * @return string
	 */
	function add_meta_links( $links, $file ) {
		if ( $file == $this->addon->slug ) {

			$link_text = __( 'Documentation', 'store-locator-le' );
			$links[]   = sprintf( '<a href="%s" title="%s" target="store_locator_plus">%s</a>', $this->slplus->support_url, $link_text, $link_text );


			$links[] = '<a href="' . admin_url( 'admin.php?page=slp_experience' ) . '" title="' .
			           __( 'Settings', 'store-locator-le' ) . '">' . __( 'Settings', 'store-locator-le' ) . '</a>';

			$newer_version = $this->get_newer_version();
			if ( ! empty( $newer_version ) ) {
				$links[] = '<strong>' . sprintf( __( 'Version %s in production ', 'store-locator-le' ), $newer_version ) . '</strong>';
			}

		}

		return $links;
	}

	/**
	 * Is this add-on being deactivated?
	 *
	 * @return bool
	 */
	protected function being_deactivated() {
		if ( empty( $_REQUEST['plugin'] ) || $_REQUEST['plugin'] !== $this->addon->slug ) {
			return false;
		}
		$plugin_action = sanitize_key( isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '' );

		// Not deactivating...
		if ( $plugin_action !== 'deactivate' ) {
			return false;
		}

		// If we are deactivating, check the nonce.
		// deactivate-plugin_slp-power/slp-power.php
		if ( ! check_admin_referer( 'deactivate-plugin_' . $this->addon->slug ) ) {
			return false;
		}

		$action_is_deactivate = ! empty( $this->slplus->clean['action'] ) && ( $this->slplus->clean['action'] === 'deactivate' );
		$plugin_is_this_one   = ! empty( $_REQUEST['plugin'] ) && ( $_REQUEST['plugin'] === $this->addon->slug );

		return ( $plugin_is_this_one && $action_is_deactivate );
	}

	/**
	 * Things we want our add on packs to do when they start.
	 *
	 * Extend this by overriding this method and then calling parent::do_admin_startup()
	 * before or after your extension.
	 */
	protected function do_admin_startup() {
		if ( $this->being_deactivated() ) {
			return;
		}

		$this->update_install_info();

		// Only save settings if the update action is set.
		// phpcs:ignore do not need to check a nonce for a short circuit
		// phpcs:ignore
		if ( empty( $_POST ) ) {
			return;
		}
		if ( empty( $this->slplus->clean['page'] ) ) {
			return;
		}
		if ( empty( $this->slplus->clean['action'] ) ) {
			return;
		}
		if ( $this->slplus->clean['action'] !== 'update' ) {
			return;
		}
		if (
			! empty( $this->settings_pages ) && array_key_exists( $this->slplus->clean['page'], $this->settings_pages )
		) {

			$this->save_my_settings();
		}
	}

	/**
	 * If the file admin.css exists and the page prefix starts with slp_ , enqueue the admin style.
	 */
	public function enqueue_admin_css( $hook ) {
		if ( defined( 'MYSLP_VERSION' ) ) {
			if ( ! $this->nq_this_file( $hook, 'css/myslp_admin.min.css', 'myslp-' ) ) { // enqueue minified CSS first
				$this->nq_this_file( $hook, 'css/myslp_admin.css', 'myslp-' );
			}
		}

		if ( ! $this->nq_this_file( $hook, 'css/admin.min.css' ) ) { // enqueue minified CSS first
			$this->nq_this_file( $hook, 'css/admin.css' );
		}
		if ( ! $this->nq_this_file( $hook, 'css/admin-react.min.css' ) ) { // enqueue minified CSS first
			$this->nq_this_file( $hook, 'css/admin-react.css', 'react' );
		}
	}

	/**
	 * Determine if any add-on packs are installed that require legacy support.
	 *
	 * @param string $fq_method The specific method we are checking in ClassName::MethodName format.
	 *
	 * @return     boolean                    Whether or not an add-on is in use that requires legacy support.
	 */
	private function needed_for_addon( $fq_method ) {
		$needs_legacy_support = $this->is_legacy_needed_for( $fq_method );
		if ( $needs_legacy_support ) {
			SLP_Admin_Helper::get_instance()->add_wp_admin_notification( $this->slplus->AddOns->get_recommendations_text(), 'update-nag' );
		}

		return $needs_legacy_support;
	}

	/**
	 * Is legacy support needed for any active add-on packs?
	 *
	 * Some add-on packs break when things are updated in the base plugin.
	 *
	 * @param string $fq_method The specific method we are checking in ClassName::MethodName format.
	 *
	 * @returns     boolean                    Whether or not an add-on is in use that requires legacy support.
	 */
	private function is_legacy_needed_for( $fq_method ) {
		$active_versions = $this->slplus->AddOns->get_versions();
		foreach ( $active_versions as $slug => $version ) {
			if ( in_array( $slug, $this->slplus->AddOns->recommended_upgrades ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Enqueue a CSS file.
	 *
	 * @param string $hook
	 * @param string $css_file
	 * @param string $augmentation
	 *
	 * @return bool  true if file exists and we did the enqueue (we think)
	 */
	private function nq_this_file( $hook, $css_file, $augmentation = '' ) {
		if ( ! file_exists( $this->addon->dir . $css_file ) ) {
			return false;
		}

		if ( ( strpos( $hook, $this->slplus->admin_page_prefix ) === false ) && ! SLP_Admin_UI::get_instance()->is_our_admin_page( $hook ) ) {
			return false;
		}

		wp_enqueue_style( $augmentation . $this->addon->short_slug . '_admin_css', $this->addon->url . '/' . $css_file, array(), filemtime( $this->addon->dir . $css_file ) );

		return true;
	}

	/**
	 * Enqueues Admin JavaScript for include/admin.js and js/admin.js and js/admin-<tabname>-tab.js (minified if present)
	 *
	 * @used-by \SLP_BaseClass_Admin::add_hooks_and_filters     for WP Hook: admin_enqueue_scripts [10]
	 *
	 * @see https://docs.storelocatorplus.com/development/2022/07/13/debugging-add-locations-w-power-uncaught-syntaxerror-redeclaration-of-const-wp_data/
	 *
	 * @param string $hook
	 */
	public function enqueue_admin_javascript( $hook ) {
		if ( ! $this->ok_to_enqueue_admin_js( $hook ) ) {
			return;
		}


		if ( $this->addon->short_slug === 'store-locator-le' ) {
			$base_name = 'slp';
		} else {
			$base_name = preg_replace( '/\W/', '', dirname( $this->addon->slug ) );
		}

		// Base Admin Files For All SLP Admin Pages
		//
		$files = array( 'js/admin.min.js', 'js/admin.js', 'include/admin.min.js', 'include/admin.js' );
		foreach ( $files as $file ) {
			if ( WP_DEBUG && strpos( $file, '.min.js' ) > 1 ) {
				continue;
			}
			if ( file_exists( $this->addon->dir . $file ) ) {
				$handle = $base_name . '_admin_script';
				wp_enqueue_script( $handle, $this->addon->url . '/' . $file, $this->js_requirements, filemtime( $this->addon->dir . '/' . $file ), false );
				if ( ! empty( $this->js_settings ) ) {
					wp_add_inline_script(
						$handle,
						'const ' . $base_name . '_settings = ' . wp_json_encode( $this->js_settings ) );
				}
				break;
			}
		}

		// Tab specific JS
		//
		$files = array();
		$tab   = str_replace( $this->slplus->admin_page_prefix . 'slp_', '', $hook );

		$tab_js_settings = [];

		// All our JS will now have an AJAX_NONCE setting via the base plugin.
		// This is only used in SLP admin.js via SLP_ADMIN.options.change_option()
		if ( $base_name === 'slp' ) {
			$tab_js_settings = array(
				'ajax_nonce' => wp_create_nonce( 'slp_ajax' ),
			);
		}

		switch ( $tab ) {
			case 'info':
			case 'experience': // aka Settings
				$files           = array( 'js/admin-settings-tab.min.js', 'js/admin-settings-tab.js' );
				$tab_js_settings = array_merge( $tab_js_settings, array(
					'nonce'        => wp_create_nonce( 'media-form' ),
					'import_nonce' => wp_create_nonce( 'wp_rest' ),
					'upload_url'   => admin_url( 'async-upload.php' ),
					'rest_path'    => SLP_REST_Handler::get_instance()->get_rest_path()
				) );
				break;

			case 'general':
				$files = array( 'js/admin-general-tab.min.js', 'js/admin-general-tab.js' );
				break;

			case 'manage_locations':
				$files = array(
					'js/admin-locations-tab.min.js',
					'js/admin-locations-tab.js',
					'js/' . $base_name . '-admin-locations-tab.min.js',
					'js/' . $base_name . '-admin-locations-tab.js'
				);
				break;
		}

		// Enqueue the first file we find...
		// The script can get localized variables in the add on modules as these are loaded in the footer.
		//
		if ( ! empty ( $files ) ) {
			foreach ( $files as $file ) {
				if ( WP_DEBUG && strpos( $file, '.min.js' ) > 1 ) {
					continue;
				}
				if ( file_exists( $this->addon->dir . $file ) ) {
					$handle = $base_name . '_' . $tab;
					wp_enqueue_script( $handle, $this->addon->url . '/' . $file, $this->js_requirements, filemtime( $this->addon->dir . $file ), true );
					if ( ! empty( $tab_js_settings ) ) {
						wp_add_inline_script(
							$handle,
							'const ' . $base_name . '_data = ' . wp_json_encode( $tab_js_settings )
						);
					}
					break;
				}
			}
		}


	}

	/**
	 * If there is a newer version get the link.
	 *
	 * @return string
	 */
	public function get_newer_version() {
		if ( isset( $this->addon->meta ) ) {
			$current_version = $this->addon->meta->get_meta( 'Version' );
			if ( ! empty( $current_version ) ) {
				$latest_version = $this->slplus->AddOns->get( $this->addon->short_slug, 'latest_version' ) ?? $current_version;
				if ( version_compare( $current_version, $latest_version, '<' ) ) {
					return $latest_version;
				}
			}
		}

		return '';
	}


	/**
	 * Get the value for a specific add-on option.  If empty use add-on option_defaults.   If still empty use slplus defaults.
	 *
	 * @param string $setting The key name for the setting to retrieve.
	 *
	 * @return    mixed                The value of the add-on options[<setting>], add-on option_defaults[<setting>], or slplus defaults[<setting>]
	 */
	public function get_value( $setting ) {

		// Default: add-on options value
		//
		$value = $this->addon->options[ $setting ];

		// First Alternative: add-on option_defaults value.
		//
		if ( empty( $value ) && isset( $this->addon->option_defaults[ $setting ] ) ) {
			$value = $this->addon->option_defaults[ $setting ];
		}

		// Second Alternative: slplus defaults value.
		//
		if ( empty( $value ) && $this->slplus->SmartOptions->exists( $setting ) ) {
			$value = $this->slplus->SmartOptions->{$setting}->default;
		}

		return $value;
	}

	/**
	 * Check if it is OK to enqueue the admin JavaScript, assume so if the hook starts with our prefix.
	 *
	 * @param $hook
	 *
	 * @return boolean
	 */
	protected function ok_to_enqueue_admin_js( $hook ) {
		if ( ! isset( $this->ok_to_nq_js ) ) {

			$admin_ui = SLP_Admin_UI::get_instance();
			if ( in_array( $hook, $admin_ui->cache['admin_pages'] ) ) {
				$this->ok_to_nq_js = true;

				return true;
			}

			/**
			 * Check our menu hooks.
			 */
			foreach ( $admin_ui->menu_items as $slug => $meta ) {
				if ( $meta['hook'] === $hook ) {
					$this->ok_to_nq_js = true;

					return true;
				}
			}

			if ( strpos( $hook, SLP_ADMIN_PAGEPRE ) === 0 ) {
				$admin_ui->cache['admin_pages'][] = $hook;
				$this->ok_to_nq_js                = true;

				return true;
			}

			$admin_ui->cache['admin_pages'] = apply_filters( 'slp_managed_admin_pages', $this->slplus->AdminUI->cache['admin_pages'] );

			$this->ok_to_nq_js = ( in_array( $hook, $admin_ui->cache['admin_pages'] ) );

		}

		return $this->ok_to_nq_js;
	}

	/**
	 * Return true if the option named by <slug> change after save_my_settings.
	 */
	public function option_changed( $slug, $check_boolean_is_true = false ) {
		$did_it_change = ( $this->saved_option_state[ $slug ] !== $this->addon->options[ $slug ] );
		if ( ( ! $check_boolean_is_true ) || ! $did_it_change ) {
			return $did_it_change;
		}

		return $this->slplus->is_CheckTrue( $this->addon->options[ $slug ] );
	}

	/**
	 * Use this to save option settings far earlier in the admin process.
	 *
	 * Necessary if you are going to use your options in localized admin scripts.
	 *
	 * TODO: Refactor to save_experience_tab_settings
	 *
	 * Make sure you set the settings_pages properties so the right checkboxes end up on the right pages.
	 *
	 * @return bool
	 */
	function save_my_settings() {
		// phpcs:ignore do not need to check a nonce for a short circuit
		// phpcs:ignore
		if ( empty( $_POST ) ) {
			return false;
		}
		if ( ! check_admin_referer( 'slp_ajax', 'security' ) ) {
			return false;
		}

		$this->save_current_option_state();

		// Don't short circuit if we are using crappy old add-on code (that I probably wrote...)
		//
		if ( ! $this->needed_for_addon( get_class() . '::' . __FUNCTION__ ) && $this->settings_have_been_saved ) {
			return false;
		}

		array_walk( $_POST, array( $this->addon, 'set_ValidOptions' ) );

		$this->addon->options = $this->save_SerializedOption(
			$this->addon->option_name, $this->addon->options, $this->get_my_checkboxes()
		);

		// TODO: 4.5 eliminate drops support for PAGES legacy
		if ( method_exists( $this->addon, 'init_options' ) ) {
			$this->addon->init_options();
		}

		$this->settings_have_been_saved = true;

		return true;
	}

	/**
	 * Save the current addon option state.
	 *
	 * Used to know what options were set to before an admin save.
	 */
	public function save_current_option_state() {
		$this->saved_option_state = $this->addon->options;
	}

	/**
	 * Merge existing options and POST options, then save to the wp_options table.
	 *
	 * NOTE: USED BY ADD ON PACKS ONLY
	 *
	 * TODO: Rewire calls from add on packs afterwards (ELM, POWER, PRO, SME, UML)
	 *
	 * Typically used to merge post options from admin interface changes with
	 * existing options in a class.
	 *
	 * @param string $optionName name of option to update
	 * @param mixed[] $currentOptions current options as a named array
	 * @param string[] $cbOptionArray array of options that are checkboxes
	 *
	 * @return mixed[] the updated options
	 */
	public function save_SerializedOption( $optionName, $currentOptions, $cbOptionArray = null ) {

		// If we did not send in a checkbox Array
		// AND there are not post options
		// get the heck out of here...
		//
		if (
			( $cbOptionArray === null ) &&
			// phpcs:ignore only called from save_my_settings which already checked the nonce
			// phpcs:ignore
			! isset( $_POST[ $optionName ] )
		) {
			return $currentOptions;
		}


		// Set a blank array if the post option name is not set
		// We can only get here with a blank post[optionname] if
		// we are given a cbOptionArray to process
		//
		// phpcs:ignore only called from save_my_settings which already checked the nonce
		// phpcs:ignore
		$optionValue = ( isset( $_POST[ $optionName ] ) ) ?
			// phpcs:ignore only called from save_my_settings which already checked the nonce
			// phpcs:ignore
			array_walk( $_POST[ $optionName ], 'wp_kses_post' ) ? $_POST[ $optionName ] : array() :
			array();

		// Checkbox Pre-processor
		//
		if ( ! is_null( $cbOptionArray ) && ! empty( $cbOptionArray ) ) {
			foreach ( $cbOptionArray as $cbname ) {
				if ( ! isset( $optionValue[ $cbname ] ) ) {
					$optionValue[ $cbname ] = '0';
				}
			}
		}

		// Merge new options from POST with existing options
		//
		$optionValue = stripslashes_deep( array_merge( $currentOptions, $optionValue ) );

		// Make persistent, write back to the wp_options table
		// Only write if something has changed.
		//
		if ( $currentOptions != $optionValue ) {
			$this->slplus->WPOption_Manager->update_wp_option( $optionName, $optionValue );
		}

		// Send back the updated options
		//
		return $optionValue;
	}


	/**
	 * Set base class properties so we can have more cross-add-on methods.
	 */
	function set_addon_properties() {
		// Replace this with the properties from the parent add-on to set this class properties.
		//
		// $this->admin_page_slug = <class>::ADMIN_PAGE_SLUG
	}

	/**
	 * Set valid options according to the addon options array.
	 *
	 * Use $this->addon->set_ValidOptions instead.
	 *
	 * @param $val
	 * @param $key
	 *
	 * @deprecated
	 *
	 * TODO: deprecate when all add-on packs use ($this->addon , 'set_ValidOptions') instead of $this->set_ValidOptions in admin class.
	 *
	 */
	function set_ValidOptions( $val, $key ) {
		$this->addon->set_ValidOptions( $val, $key );
	}

	/**
	 * Update the install info for this add on.
	 */
	protected function update_install_info() {
		$installed_version = isset( $this->addon->options['installed_version'] ) ?
			$this->addon->options['installed_version'] :
			'0.0.0';

		if ( version_compare( $installed_version, $this->addon->version ?? $installed_version, '<' ) ) {
			$this->update_prior_installs();
			$this->addon->options['installed_version'] = $this->addon->version;
			update_option( $this->addon->option_name, $this->addon->options );
		}
	}

	/**
	 * Update prior add-on pack installations.
	 */
	private function update_prior_installs() {
		if ( ! empty( $this->addon->activation_class_name ) ) {
			if ( ! class_exists( $this->addon->activation_class_name ) ) {
				if ( file_exists( $this->addon->dir . 'include/class.activation.php' ) ) {
					require_once( $this->addon->dir . 'include/class.activation.php' );
					$this->activation = new $this->addon->activation_class_name( array( 'addon' => $this->addon ) );
					$this->activation->update();
				}
			}
		}
	}

	/**
	 * Add our admin pages to the valid admin page slugs.
	 *
	 * @param string[] $slugs admin page slugs
	 *
	 * @return string[] modified list of admin page slugs
	 */
	function filter_AddOurAdminSlug( $slugs ) {
		return array_merge( $slugs, array(
			$this->admin_page_slug,
			$this->slplus->admin_page_prefix . $this->admin_page_slug
		) );
	}
}

