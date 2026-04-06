<?php
if ( ! function_exists( 'get_plugin_data' ) ) {
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}
$this_plugin    = get_plugin_data( SLP_POWER_FILE, false, false );
$min_wp_version = '5.8';

if ( ! defined( 'SLPLUS_PLUGINDIR' ) ) {
	function power_notify_slplus_dependency() {
		echo '<div class="error"><p>' .
		     'Store Locator Plus® - Power' .
		     __( ' requires Store Locator Plus® to function properly. ', 'slp-power' ) .
		     '<br/>' .
		     __( 'This plugin has been deactivated.', 'slp-power' ) .
		     __( 'Please install Store Locator Plus®.', 'slp-power' ) .
		     '</p></div>';
	}

	add_action( 'admin_notices', 'power_notify_slplus_dependency' );
	deactivate_plugins( plugin_basename( SLP_POWER_FILE ) );

	return;
}

global $wp_version;
if ( version_compare( $wp_version, $min_wp_version, '<' ) ) {
	function power_notify_wp_dependency() {
		echo '<div class="error"><p>' .
		     'Store Locator Plus® - Power' .
		     __( ' requires WordPress 5.8 to function properly. ', 'slp-power' ) .
		     '<br/>' .
		     __( 'This plugin has been deactivated.', 'slp-power' ) .
		     __( 'Please install Store Locator Plus®.', 'slp-power' ) .
		     '</p></div>';

	}

	add_action( 'admin_notices', 'power_notify_wp_dependency' );
	deactivate_plugins( plugin_basename( SLP_POWER_FILE ) );

	return;
}

defined( 'SLPPOWER_REL_DIR' ) || define( 'SLPPOWER_REL_DIR', plugin_dir_path( SLP_POWER_FILE ) );
defined( 'SLP_POWER_VERSION' ) || define( 'SLP_POWER_VERSION', $this_plugin['Version'] );

// Go forth and sprout your tentacles...
// Get some Store Locator Plus sauce.
//
require_once( SLPPOWER_REL_DIR . 'include/SLPPower.php' );
SLPPower::init();
