<?php
/*
Plugin Name: Store Locator Plus® for WordPress
Plugin URI: https://storelocatorplus.com/
Description: Add a location finder or directory to your site in minutes. Extensive add-on library available!
Author: Store Locator Plus®
Author URI: https://storelocatorplus.com
License: GPL3

Text Domain: store-locator-le

Copyright 2012 - 2023  Charleston Software Associates (info@storelocatorplus.com). All rights reserved worldwide.

Tested up to: 6.4.1
Version: 2311.17.01
*/
defined( 'ABSPATH' ) || exit;
if ( defined( 'SLPLUS_VERSION' ) ) {
	return;
}
defined( 'SLPLUS_FILE' ) || define( 'SLPLUS_FILE', __FILE__ );
defined( 'SLPLUS_PLUGINDIR' ) || define( 'SLPLUS_PLUGINDIR', plugin_dir_path( SLPLUS_FILE ) );
defined( 'SLPLUS_NAME' ) || define( 'SLPLUS_NAME', __( 'Store Locator Plus®', 'store-locator-le' ) );

require_once( SLPLUS_PLUGINDIR . 'include/base/loader.php' );
if ( ! slp_passed_requirements() ) {
	return;
}
slp_setup_environment();
