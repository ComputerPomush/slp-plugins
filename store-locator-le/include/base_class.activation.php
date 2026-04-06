<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'SLP_BaseClass_Activation' ) ) {

	/**
	 * A base class that helps add-on packs separate activation functionality.
	 *
	 * Add on packs should include and extend this class.
	 *
	 * This allows the main plugin to only include this file during activation.
	 *
	 * @property    SLP_BaseClass_Addon $addon
	 * @see SLPLUS class.activation.upgrade for examples.
	 * @property    string[] $smartOptions      A list of addon options converting to SmartOptions
	 * @property    string $updating_from      The version of this add-on that was installed previously.
	 */
	class SLP_BaseClass_Activation extends SLPlus_BaseClass_Object {
		protected $addon;
		protected $smartOptions = array();
		protected $updating_from;

		/**
		 * Convert legacy add on options to smart options.
		 *
		 * The parent SLP_BaseClass_Admin.php from SLP will auto-call update_option( ) for addon->options.
		 */
		private function convert_to_smartoptions() {
			if ( empty( $this->smartOptions ) ) {
				return;
			}
			foreach ( $this->smartOptions as $option_slug ) {
				if ( isset( $this->addon->options[ $option_slug ] ) ) {
					$this->setup_smart_option( $option_slug, $this->addon->options[ $option_slug ] );
					$this->slplus->SmartOptions->set( $option_slug, $this->addon->options[ $option_slug ] );
					unset( $this->addon->options[ $option_slug ] );
				}
			}

			$this->slplus->SmartOptions->execute_change_callbacks();       // Anything changed?  Execute their callbacks.
			$this->slplus->WPOption_Manager->update_wp_option( 'js' );        // Change callbacks may interact with JS or NOJS, make sure both are saved after ALL callbacks
			$this->slplus->WPOption_Manager->update_wp_option( 'nojs' );
		}

		/**
		 * Override
		 *
		 * @param $slug
		 * @param $value
		 */
		protected function setup_smart_option( $slug, $value ) {
		}

		/**
		 * Things we do at startup.
		 */
		function initialize() {
			$this->updating_from = $this->addon->options['installed_version'];
		}

		/**
		 * Remove any options listed in smart options lists.
		 */
		private function remove_obsolete_options() {
			$remove_these = $this->smartOptions;
			if ( empty( $remove_these ) ) {
				return;
			}
			foreach ( $remove_these as $key ) {
				if ( array_key_exists( $key, $this->addon->options ) ) {
					unset( $this->addon->options[ $key ] );
				}
			}
		}

		/**
		 * Remove all numeric option names.
		 *
		 * This is cleanup from ill-behaved add-on packs.
		 */
		protected function remove_unnamed_options() {
			foreach ( $this->addon->options as $name => $value ) {
				if ( is_numeric( $name ) ) {
					unset( $this->addon->options[ $name ] );
				}
			}
		}

		/**
		 * Do this whenever the activation class is instantiated.
		 *
		 * This is triggered via the update_prior_installs method in the admin class,
		 * which is run via update_install_info() in the admin class.
		 *
		 * update_install_info should be something you put in any add-on pack
		 * that is using the base add-on class.  It typically goes inside the
		 * do_admin_startup() method which is overridden by the new add on
		 * adminui class code.
		 */
		public function update() {
			$this->convert_to_smartoptions();
			$this->remove_obsolete_options();
			$this->remove_unnamed_options();
			update_option( $this->addon->option_name, $this->addon->options );
		}
	}
}
