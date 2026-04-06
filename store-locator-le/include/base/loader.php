<?php
/**
 * Check if it is ok to load SLP.
 *
 * @return bool
 */
function slp_passed_requirements() {
	$min_wp_version  = '5.3';
	$min_php_version = '7.2';

	// Check WP Version
	//
	global $wp_version;
	if ( version_compare( $wp_version, $min_wp_version, '<' ) ) {
		add_action(
			'admin_notices',
			function () use ( $min_wp_version ) {
				echo '<div class="error"><p>';
				printf(
					esc_html__( '%s requires WordPress %s or newer. You are running version %s. Please upgrade.', 'store-locator-le' ),
					esc_html( SLPLUS_NAME ),
					esc_html( $min_wp_version ),
					esc_html( $GLOBALS['wp_version'] )
				);
				echo '</p></div>';
			}
		);
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		deactivate_plugins( plugin_basename( __FILE__ ) );

		return false;
	}

	// Check PHP Version
	//
	if ( version_compare( PHP_VERSION, $min_php_version, '<' ) ) {
		add_action(
			'admin_notices',
			function () use ( $min_php_version ) {
				echo '<div class="error"><p>';
				printf(
					esc_html__( '%s requires PHP %s to function properly and has been deactivated. ', 'store-locator-le' ),
					esc_html( SLPLUS_NAME ),
					esc_html( $min_php_version )
				);
				echo '</p></div>';

			}
		);
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		deactivate_plugins( plugin_basename( __FILE__ ) );

		return false;
	}

	return true;
}

/**
 * Setup the SLP Environment (defines, etc.)
 */
function slp_setup_environment() {
	if ( ! function_exists( 'get_plugin_data' ) ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	$this_plugin = get_plugin_data( SLPLUS_FILE, false, false );

	// constants from WP plugin data from file headers
	defined( 'SLPLUS_VERSION' ) || define( 'SLPLUS_VERSION', $this_plugin['Version'] );

	// Paths and such derived from SLPLUS_FILE
	defined( 'SLPLUS_BASENAME' ) || define( 'SLPLUS_BASENAME', plugin_basename( SLPLUS_FILE ) ); // The relative path from the plugins directory
	defined( 'SLPLUS_ICONDIR' ) || define( 'SLPLUS_ICONDIR', SLPLUS_PLUGINDIR . 'images/icons/' ); // Path to the icon images

	// URLs
	defined( 'SLPLUS_PLUGINURL' ) || define( 'SLPLUS_PLUGINURL', plugins_url( '', SLPLUS_FILE ) );
	defined( 'SLPLUS_ICONURL' ) || define( 'SLPLUS_ICONURL', SLPLUS_PLUGINURL . '/images/icons/' ); // Fully qualified URL to the icon images.

	defined( 'SLP_DETECTED_HEARTBEAT' ) || define( 'SLP_DETECTED_HEARTBEAT', ( defined( 'DOING_AJAX' ) && DOING_AJAX && ! empty( $_REQUEST['action'] ) && ( $_REQUEST['action'] === 'heartbeat' ) ) );

	if ( defined( 'SLPLUS_UPLOADDIR' ) === false ) {
		$upload_dir = wp_upload_dir( 'slp' );
		$error      = $upload_dir['error'];
		if ( empty( $error ) ) {
			define( 'SLPLUS_UPLOADDIR', $upload_dir['path'] );
			define( 'SLPLUS_UPLOADURL', $upload_dir['url'] );
		} else {
			global $slp_upload_error;
			$slp_upload_error = preg_replace(
				'/Unable to create directory /',
				'Unable to create directory ' . ABSPATH,
				$error
			);
			add_action( 'admin_notices', 'slp_upload_dir_notice' );
			define( 'SLPLUS_UPLOADDIR', SLPLUS_PLUGINDIR );
			define( 'SLPLUS_UPLOADURL', SLPLUS_PLUGINURL );
		}
	}

	defined( 'SLPLUS_PREFIX' ) || define( 'SLPLUS_PREFIX', 'csl-slplus' );
	defined( 'SLP_ADMIN_PAGEPRE' ) || define( 'SLP_ADMIN_PAGEPRE', 'store-locator-plus_page_' );

	require_once( SLPLUS_PLUGINDIR . 'include/SLPlus.php' );
}

/**
 * Upload directory issue warning.
 */
function slp_upload_dir_notice() {
	global $slp_upload_error;
	echo '<div class="error"><p>';
	esc_html_e( 'Store Locator Plus® upload directory error.', 'store-locator-le' );
	echo esc_html( $slp_upload_error );
	echo '</p></div>';
}
