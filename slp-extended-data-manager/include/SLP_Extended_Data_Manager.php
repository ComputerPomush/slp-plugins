<?php
defined( 'ABSPATH' ) || exit;

require_once( SLPLUS_PLUGINDIR . 'include/base_class.addon.php' );

// Define some constants for use by this add-on
slp_edm_maybe_define_constant( 'SLP_EDM_OPTIONS',               'options'                             );  // 
slp_edm_maybe_define_constant( 'SLP_EDM_OPTION_SLUG',           'slug'                                );  // 
slp_edm_maybe_define_constant( 'SLP_EDM_OPTION_SHOW',           'show'                                );  // 
slp_edm_maybe_define_constant( 'SLP_EDM_OPTION_ORDER',          'order'                               );  // 
slp_edm_maybe_define_constant( 'SLP_EDM_OPTION_DISPLAY_TYPE',   'display_type'                        );  // 
slp_edm_maybe_define_constant( 'SLP_EDM_OPTION_HELP_TEXT',      'help_text'                           );  // 

slp_edm_maybe_define_constant( 'SLP_EDM_OPTION_NAME',           'slp-extended-data-manager-options'   );  // 
slp_edm_maybe_define_constant( 'SLP_EDM_OPTION_NAME_OLD',       'slp-extended-data-manager'           );  // 

slp_edm_maybe_define_constant( 'SLP_EDM_FILTER_ACTION',         'show_hidden'                         );  // 
slp_edm_maybe_define_constant( 'SLP_EDM_FILTER_OPTION',         'slp_edm_filter_option'               );  // 
slp_edm_maybe_define_constant( 'SLP_EDM_FILTER_NORMAL',         'Normal'                              );  // 
slp_edm_maybe_define_constant( 'SLP_EDM_FILTER_SHOW_HIDDEN',    'filter_show_hidden'                  );  // 

slp_edm_maybe_define_constant( 'SLP_EDM_NEW_ELEMENT_SLUG',      'edm_new_element_slug'                );  // 
slp_edm_maybe_define_constant( 'SLP_EDM_UPDATE_PRE',            'edm_update'                          );  // 

slp_edm_maybe_define_constant( 'SLP_EDM_TABLE_SLUG',            'tab_slug'                            );  // 
slp_edm_maybe_define_constant( 'SLP_EDM_TABLE_SHOW',            'option_show'                         );  // 
slp_edm_maybe_define_constant( 'SLP_EDM_TABLE_TYPE',            'type'                                );  // 
slp_edm_maybe_define_constant( 'SLP_EDM_TABLE_LABEL',           'label'                               );  // 
slp_edm_maybe_define_constant( 'SLP_EDM_TABLE_ORDER',           'option_order'                        );  // 
slp_edm_maybe_define_constant( 'SLP_EDM_TABLE_OPTIONS',         'options'                             );  // 
slp_edm_maybe_define_constant( 'SLP_EDM_TABLE_FIELD_ID',        'field_id'                            );  // 
slp_edm_maybe_define_constant( 'SLP_EDM_TABLE_DISPLAY_TYPE',    'options][display_type'               );  // 
slp_edm_maybe_define_constant( 'SLP_EDM_TABLE_HELP_TEXT',       'options][help_text'                  );  // 

slp_edm_maybe_define_constant( 'SLP_EDM_OPTION_ORDER_MAX',      999                                   );  // 
slp_edm_maybe_define_constant( 'SLP_EDM_SUPPORT_URL',           'https://www.de-baat.nl/' . SLP_EDM_SHORT_SLUG );  // The URL link to the documentation support page

slp_edm_maybe_define_constant( 'SLP_EDM_ELEMENT_SLUG_PREFIX',   'slp_edm__'                           );  //
slp_edm_maybe_define_constant( 'SLP_EDM_CSL_SEPARATOR',         '--'                                  );  //

slp_edm_maybe_define_constant( 'SLP_EDM_ACTION',                'edm_action'                          );  //
slp_edm_maybe_define_constant( 'SLP_EDM_ACTION_SAVE',           'edm_action_save'                     );  //
slp_edm_maybe_define_constant( 'SLP_EDM_ACTION_REQUEST',        'edm_action_request'                  );  //
slp_edm_maybe_define_constant( 'SLP_EDM_ACTION_ELEMENT',        'edm_action_element'                  );  //
slp_edm_maybe_define_constant( 'SLP_EDM_ACTION_ELEMENT_ADD',    'edm_action_element_add'              );  //
slp_edm_maybe_define_constant( 'SLP_EDM_ACTION_ELEMENT_HIDE',   'edm_action_element_hide'             );  //
slp_edm_maybe_define_constant( 'SLP_EDM_ACTION_ELEMENT_SHOW',   'edm_action_element_show'             );  //
slp_edm_maybe_define_constant( 'SLP_EDM_ACTION_ELEMENT_DELETE', 'edm_action_element_delete'           );  //
slp_edm_maybe_define_constant( 'SLP_EDM_ACTION_ELEMENT_UPDATE', 'edm_action_element_update'           );  //

slp_edm_maybe_define_constant( 'SLP_EDM_ACTION_FILTER_ALL',     'edm_action_filter_all'               );  //
slp_edm_maybe_define_constant( 'SLP_EDM_ACTION_FILTER_HIDE',    'edm_action_filter_hide'              );  //
slp_edm_maybe_define_constant( 'SLP_EDM_ACTION_FILTER_SHOW',    'edm_action_filter_show'              );  //

slp_edm_maybe_define_constant( 'SLP_EDM_GROUP_SLUG',            'edm_data_group'                      );  //
slp_edm_maybe_define_constant( 'SLP_EDM_SECTION_SLUG',          'data'                                );  //
slp_edm_maybe_define_constant( 'SLP_EDM_SECTION_SLUG_DATA',     'data'                                );  //
slp_edm_maybe_define_constant( 'SLP_EDM_SECTION_SLUG_ADD',      'edm_elements_section_add'            );  //
slp_edm_maybe_define_constant( 'SLP_EDM_SECTION_SLUG_TABLE',    'edm_elements_section_table'          );  //


/**
 * The Extended Data Manager add-on pack for Store Locator Plus.
 *
 * @package StoreLocatorPlus\SLP_Extended_Data_Manager
 * @author DeBAAT <slp-edm@de-baat.nl>
 * @copyright 2022 De B.A.A.T. - Charleston Software Associates, LLC
 */

// Make sure the class is only defined once.
//
//if (!class_exists('SLP_Extended_Data_Manager'   )) {

	//require_once( WP_PLUGIN_DIR . '/store-locator-le/include/base_class.addon.php');


	/**
	 * Class SLP_Extended_Data_Manager
	 *
	 * @property        SLP_Extended_Data_Manager_Admin         $admin
	 * @property        SLP_Extended_Data_Manager               $instance
	 * @property        SLP_Extended_Data_Manager_Options       $options                    Settable options for this plugin.
	 *
	 */
	class SLP_Extended_Data_Manager extends SLP_BaseClass_Addon  {

		protected $class_prefix = SLP_EDM_CLASS_PREFIX;

		/**
		 * Settable options for this plugin.
		 *
		 * @var mixed[] $options
		 */
		public        $options                      = array(

			// General SLP_Extended_Data_Manager_Options
			'installed_version'                     => SLP_EDM_NO_INSTALLED_VERSION,
			// 'edm_elements_per_page'                 => '20',
		);

		public        $admin;
		public static $instance;

		public        $remote_version         = '';

		/**
		 * Initialize a singleton of this object.
		 *
		 * @return SLP_Extended_Data_Manager
		 */
		public static function init() {
			static $instance = false;

			if (!$instance) {
				load_plugin_textdomain('slp-extended-data-manager', false, SLP_EDM_REL_DIR . '/languages/');
				$instance = new SLP_Extended_Data_Manager(
					array(
						'version'                   => SLP_EDM_VERSION,
						'min_slp_version'           => SLP_EDM_MIN_SLP,

						'name'                      => __('Extended Data Manager', 'slp-extended-data-manager'),
						'option_name'               => SLP_EDM_OPTION_NAME,
						'file'                      => SLP_EDM_FILE,

						'activation_class_name'     => 'SLP_Extended_Data_Manager_Activation',
						'admin_class_name'          => 'SLP_Extended_Data_Manager_Admin',
					)
				);
			}
			return $instance;
		}

	    /**
	     * Run these things during invocation. (called from base object in __construct)
	     */
	    protected function initialize() {
			$this->debugMP('msg',__FUNCTION__.'-1 started.');

			$this->slplus->min_add_on_versions[ SLP_EDM_SHORT_SLUG ] = SLP_EDM_VERSION;

			parent::initialize();

		}

		/**
		 * Add cross-element hooks & filters.
		 *
		 * Haven't yet moved all items to the AJAX and UI classes.
		 */
		function add_hooks_and_filters() {
			$this->debugMP('msg',__FUNCTION__.'-1 started.');

		}

		/**
		 * Check whether the current version of this Add On works with the latest version of the SLP base plugin.
		 * This is already checked against the SLP_EDM_MIN_SLP version in the loader
		 *
		 * @return boolean
		 */
		private function check_my_version_compatibility() {
			$this->debugMP('msg', __CLASS__ . ' ' . __FUNCTION__ . ' started but not needed for version=' . $this->version );

			return true;
		}

		/**
		 * Get the latest version of this Add On from Freemius.
		 *
		 * @return string
		 */
		function get_latest_version_from_freemius() {

			// Get the Freemius object for this plugin
			$fs   = slp_edm_freemius_get_freemius();
			//$this->debugMP('pr', __CLASS__ . ' ' . __FUNCTION__ . ' found fs=', $fs );

			// Get the _storage object of this FS Freemius object
			$_slug        = $fs->get_slug();
			$_module_type = $fs->get_module_type();
			$_storage     = FS_Storage::instance( $_module_type, $_slug );
			//$this->debugMP('pr', __CLASS__ . ' ' . __FUNCTION__ . ' found _storage=', $_storage );
			$this->remote_version = $_storage->plugin_last_version;

			$this->debugMP('msg', __CLASS__ . ' ' . __FUNCTION__ . ' found remote_version=' . $this->remote_version . ' for _module_type=' . $_module_type . ' and _slug=' . $_slug );
			return $this->remote_version;
		}

		/**
		 * Creates updates object AND checks for updates for this add-on.
		 * Not needed as this is handled by Freemius
		 *
		 * @param boolean $force
		 */
		function create_object_Updates( $force ) {

			$latest_version = $this->get_latest_version_from_freemius();
			$this->debugMP('msg', __CLASS__ . ' ' . __FUNCTION__ . ' found version=' . $this->version . ' and latest_version=' . $latest_version );

		}

		/**
		 * Initialize all our js goodness.
		 *
		 * TODO: SLP 4.3 has an admin.js file checker and will load this auto-magically.
		 * Rename to include/admin.js and the SLP_Extended_Data_Manager_Admin class will take care of it as long as you use the default admin startup.
		 *
		 */
		public static function user_header_js() {
			if ((isset($_REQUEST['page'])) && ($_REQUEST['page'] == 'slp_general')) {
	            wp_enqueue_script( 'slp_edm_script', plugins_url('/js/slp-edm.js', SLP_EDM_FILE));
			}
		}

		/**
		 * Things we do at the start.
		 */
		protected function at_startup() {
			$this->debugMP('msg',__FUNCTION__.' started.');

			// Create the addon objects
			$this->create_object_addon_objects();
		}

		/**
		 * Add the tabs/main menu items.
		 *
		 * @param mixed[] $menuItems
		 * @return mixed[]
		 */
		public function filter_AddMenuItems($menuItems) {
			$this->createobject_Admin();
			return parent::filter_AddMenuItems( $menuItems );
		}

		/**
		 * Initialize the options properties from the WordPress database.
		 */
		function init_options() {
			$this->debugMP('msg',__FUNCTION__.' started.');

			// Set the defaults for first-run
			// Especially useful for gettext stuff you cannot put in the property definitions.
			//
			$this->option_defaults = $this->options;
			//$this->option_defaults['first_entry_for_city_selector'      ] = __( 'All Cities...', 'slp-extended-data-manager' );

			parent::init_options();
		}

		/**
		 * Create and attach the addon info objects.
		 */
		private function create_object_addon_objects() {
			$this->debugMP('msg', __FUNCTION__ . ' started.');

		}

		/**
		 * Create a Extended Data Manager Debug My Plugin panel.
		 *
		 * @return null
		 */
		static function create_DMPPanels() {
			if (!isset($GLOBALS['DebugMyPlugin'])) { return; }
			if (class_exists('DMPPanelSLPEDM') == false) {
				require_once('class.dmppanels.php');
			}
			$GLOBALS['DebugMyPlugin']->panels['slp.edm'] = new DMPPanelSLPEDM();
		}

		/**
		 * Simplify the plugin debugMP interface.
		 *
		 * Typical start of function call: $this->debugMP('msg',__FUNCTION__);
		 *
		 * @param string $type
		 * @param string $hdr
		 * @param string $msg
		*/
		function debugMP($type,$hdr,$msg='') {
			if (($type === 'msg') && ($msg!=='')) {
				$msg = esc_html($msg);
			}
			if (($hdr!=='')) {   // Adding __CLASS__ to non-empty hdr
				$hdr = __CLASS__ . '::' . $hdr;
			}
			SLP_Extended_Data_Manager_debugMP($type,$hdr,$msg,NULL,NULL,true);
		}
	}

	// Hook to invoke the plugin.
	//
	//add_action('init',             array('SLP_Extended_Data_Manager','init'              ));

	// Create the scripts and code for using the jquery ui (e.g. datepicker)
	//add_filter('wp_print_scripts', array('SLP_Extended_Data_Manager','user_header_js'    ));
//}

