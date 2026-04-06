<?php
/*
Plugin Name: Store Locator Plus® for WP : Experience
Plugin URI: https://wordpress.storelocatorplus.com/product/experience/
Description: Extends the Store Locator Plus® plugin with features that allow site authors and developers to create a customized robust user experience.
Author: Store Locator Plus®
Author URI: https://storelocatorplus.com

Text Domain: slp-experience
Domain Path: /languages/

Tested up to: 6.4.1
Version: 2311.20.01
Copyright 2015 - 2023  Charleston Software Associates (support@storelocatorplus.com)

Store Locator Plus® for WP : Experience is free software: you can redistribute it and/or modify it under the terms of the
Lesser GNU General Public License as published by the Free Software Foundation, either version 3 of the License,
or (at your option) any later version.

Store Locator Plus® for WP : Experience is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the Lesser GNU General Public License for more details.

You should have received a copy of the Lesser GNU General Public License along with Store Locator Plus® for WP : Power.
If not, see <https://www.gnu.org/licenses/>.
*/
defined( 'ABSPATH' ) or exit;
defined( 'SLP_EXPERIENCE_MIN_SLP' ) || define( 'SLP_EXPERIENCE_MIN_SLP', '2310.23' );
defined( 'SLP_EXPERIENCE_FILE' ) || define( 'SLP_EXPERIENCE_FILE', __FILE__ );
if ( defined( 'DOING_AJAX' ) && DOING_AJAX && ! empty( $_REQUEST['action'] ) && ( $_REQUEST['action'] === 'heartbeat' ) ) {
	return;
}

function SLP_Experience_loader() {
	require_once( 'include/base/loader.php' );
}

add_action( 'plugins_loaded', 'SLP_Experience_loader' );
