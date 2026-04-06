<?php
/*
Plugin Name: Store Locator Plus® for WP : Power
Plugin URI: https://wordpress.storelocatorplus.com/product/power/
Description: Adds power user features include location import, categorization, and SEO pages to Store Locator Plus®.
Author: Store Locator Plus®
Author URI: https://storelocatorplus.com
License: LGPL3

Text Domain: slp-power
Domain Path: /languages/

Tested up to: 6.4.1
Version: 2311.17.01

Copyright 2016 - 2023 Store Locator Plus® (support@storelocatorplus.com)

This file is part of Store Locator Plus® for WP : Power.

Store Locator Plus® for WP : Power is free software: you can redistribute it and/or modify it under the terms of the
Lesser GNU General Public License as published by the Free Software Foundation, either version 3 of the License,
or (at your option) any later version.

Store Locator Plus® for WP : Power is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the Lesser GNU General Public License for more details.

You should have received a copy of the Lesser GNU General Public License along with Store Locator Plus® for WP : Power.
If not, see <https://www.gnu.org/licenses/>.
*/
defined( 'ABSPATH' ) || exit;
defined( 'SLP_POWER_MIN_SLP' ) || define( 'SLP_POWER_MIN_SLP', '2210.25' );
defined( 'SLP_POWER_FILE' ) || define( 'SLP_POWER_FILE', __FILE__ );
if ( defined( 'DOING_AJAX' ) && DOING_AJAX && ! empty( $_REQUEST['action'] ) && ( $_REQUEST['action'] === 'heartbeat' ) ) {
	return;
}

function SLP_Power_loader() {
	require_once( 'include/base/loader.php' );
}

add_action( 'plugins_loaded', 'SLP_Power_loader' );
