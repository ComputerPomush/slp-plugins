<?php
if ( ! function_exists( 'get_plugin_data' ) ) {
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}
$this_plugin    = get_plugin_data( SLP_EDM_FILE, false, false );
$min_wp_version = '5.0';

if ( ! defined( 'SLP_EDM_VERSION' ) ) define( 'SLP_EDM_VERSION', $this_plugin[ 'Version'] );

if ( ! defined( 'SLPLUS_PLUGINDIR' ) ) {
	add_action(
		'admin_notices',
		create_function(
			'',
			"echo '<div class=\"error\"><p>" .
			sprintf(
				__( '%s requires Store Locator Plus to function properly. ', 'slp-extended-data-manager' ),
				$this_plugin['Name']
			) . '<br/>' .
			__( 'This plugin has been deactivated.', 'slp-extended-data-manager' ) .
			__( 'Please install Store Locator Plus.', 'slp-extended-data-manager' ) .
			"</p></div>';"
		)
	);
	deactivate_plugins( plugin_basename( SLP_EDM_FILE ) );

	return;
}

global $wp_version;
if ( version_compare( $wp_version, $min_wp_version, '<' ) ) {
	add_action(
		'admin_notices',
		create_function(
			'',
			"echo '<div class=\"error\"><p>" .
			sprintf(
				__( '%s requires WordPress %s to function properly. ', 'slp-extended-data-manager' ),
				$this_plugin['Name'],
				$min_wp_version
			) .
			__( 'This plugin has been deactivated.', 'slp-extended-data-manager' ) .
			__( 'Please upgrade WordPress.', 'slp-extended-data-manager' ) .
			"</p></div>';"
		)
	);
	deactivate_plugins( plugin_basename( SLP_EDM_FILE ) );

	return;
}

// Set SLP_Settings_image file names.
if ( defined( 'SLP_SETTINGS_IMAGE_SOURCE'   ) === false ) { define( 'SLP_SETTINGS_IMAGE_SOURCE',   SLP_EDM_REL_DIR  . 'include/module/settings/SLP_Settings_image.php' ); } //
if ( defined( 'SLP_SETTINGS_IMAGE_TARGET'   ) === false ) { define( 'SLP_SETTINGS_IMAGE_TARGET',   SLPLUS_PLUGINDIR . 'include/module/settings/SLP_Settings_image.php' ); } //

function SLP_Extended_Data_Manager_check_slp_settings_image() {

	// Check whether the SLP_Settings_image.php target file exists
	//
	if ( ! file_exists( SLP_SETTINGS_IMAGE_TARGET ) ) {
		// check the existence of the source file
		if ( empty( SLP_SETTINGS_IMAGE_SOURCE ) ) {
			return;
		}
		if ( ! is_readable( SLP_SETTINGS_IMAGE_SOURCE ) ) {
			return;
		}

		// Copy the SLP_Settings_image.php file from source to target
		copy( SLP_SETTINGS_IMAGE_SOURCE, SLP_SETTINGS_IMAGE_TARGET );
//error_log( __FUNCTION__ . ' copied SLP_SETTINGS_IMAGE_SOURCE  = ' . SLP_SETTINGS_IMAGE_SOURCE);
	}
//error_log( __FUNCTION__ . ' checked SLP_SETTINGS_IMAGE_TARGET  = ' . SLP_SETTINGS_IMAGE_TARGET);
}
SLP_Extended_Data_Manager_check_slp_settings_image();

// Go forth and sprout your tentacles...
// Get some Store Locator Plus sauce.
//
require_once( SLP_EDM_REL_DIR . 'include/SLP_Extended_Data_Manager.php' );
//error_log( ' LOADER for SLP_Extended_Data_Manager::init for SLP_EDM_FILE = ' . SLP_EDM_FILE );
//error_log( ' LOADER for SLP_Extended_Data_Manager::init for SLP_EDM_REL_DIR = ' . SLP_EDM_REL_DIR );
SLP_Extended_Data_Manager::init();