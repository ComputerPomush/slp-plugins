<?php
defined( 'ABSPATH' ) || exit;
if (! class_exists('SLP_Extended_Data_Manager_Activation')) {

	require_once(SLPLUS_PLUGINDIR.'/include/base_class.activation.php');

	/**
	 * Manage plugin activation.
	 *
	 * @package StoreLocatorPlus\SLP_Extended_Data_Manager\Activation
	 * @author DeBAAT <slp-edm@de-baat.nl>
	 * @copyright 2022 De B.A.A.T. - Charleston Software Associates, LLC
	 *
	 * @property    SLP_Extended_Data_Manager                          $addon
	 *
	 */
	class SLP_Extended_Data_Manager_Activation extends SLP_BaseClass_Activation {

		public    $addon;

        // protected $smart_options = array(

			// // General SLP_Extended_Data_Manager Options
			// 'edm_elements_per_page',
        // );

		/**
		 * Settable options for the old addon
		 *
		 * @var mixed[] $options
		 */
		public $option_name_before  = 'csl-slplus-EDM-options';
		public $options_before      = array();

		/**
		 * Update or create the data tables.
		 */
		function update() {

			$this->debugMP('pr', __FUNCTION__ . ' found options:', $this->options);

			// Extended Location Data Enhancements for Extended Data Manager
			//
			if ((version_compare($this->addon->options['installed_version'], '5.0.00', '<'))){
				$update_msg = sprintf(__("Update Extended Data Manager version %s to version %s", 'slp-extended-data-manager' ),
											$this->addon->options['installed_version'],
											$this->addon->version
										);

				$this->addon->options['edm_elements_per_page'] = 20;

				$this->debugMP('msg','',$update_msg);
			}

			// Migrate the options of the old addon
			$this->migrate_options();

            parent::update();

			// Update the options.
//			$this->addon->options['installed_version'] = $this->addon->version;
			update_option( $this->addon->option_name, $this->addon->options );
		}

		/**
		 * Migrate the options of the old Extended Data Manager addon.
		 */
		private function migrate_options() {
			$this->debugMP('msg', __FUNCTION__ . ' started.');

			$this->options_before = get_option( $this->option_name_before );
			$this->debugMP('pr', __FUNCTION__ . ' found options_before:', $this->options_before);

			// Return when there are no options to migrate
			if ( $this->options_before === false ) {
				return;
			}

			// Migrate the options found
			$this->installed_version_before = $this->options_before[ 'installed_version' ];

			// Only update if not processed yet
            if ( version_compare( $this->installed_version_before , '99.99' , '<=' ) ) {
				// Migrate original options_before
				//$this->addon->option_name[ 'edm_dummy_option' ] = $this->options_before[ 'dummy_option' ];
            }

			// Update the options to indicate they have been processed.
			$this->options_before['installed_version'] = '99.99.91';
			update_option( $this->option_name_before, $this->options_before );

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
}