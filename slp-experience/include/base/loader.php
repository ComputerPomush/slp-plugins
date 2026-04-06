<?php
defined( 'ABSPATH' ) || exit;
if ( ! function_exists( 'get_plugin_data' ) ) {
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}
$this_plugin = get_plugin_data( SLP_EXPERIENCE_FILE , false, false);
$min_wp_version   = '5.3';

if ( ! defined( 'SLPLUS_PLUGINDIR' ) ) {
	function experience_notify_slplus_dependency() {
		echo '<div class="error"><p>' .
		     'Store Locator Plus® - Experience' .
		     __( ' requires Store Locator Plus to function properly. ', 'slp-experience' ) .
		     '<br/>' .
		     __( 'This plugin has been deactivated.', 'slp-experience' ) .
		     __( 'Please install Store Locator Plus.', 'slp-experience' ) .
		     '</p></div>';
	}

	add_action( 'admin_notices', 'experience_notify_slplus_dependency' );
	deactivate_plugins( plugin_basename( SLP_EXPERIENCE_FILE ) );

	return;
}

global $wp_version;
if ( version_compare( $wp_version, $min_wp_version, '<' ) ) {
	add_action(
		'admin_notices',
		function() use ( $min_wp_version ) {
			echo '<div class="error"><p>';
			printf(
				esc_html__( 'Store Locator Plus® - Experience requires WordPress %s or newer. You are running version %s. Please upgrade.', 'slp-experience' ),
				esc_html( $min_wp_version ),
				esc_html( $GLOBALS['wp_version'] )
			);
			echo '</p></div>';
		}
	);
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	deactivate_plugins( plugin_basename( SLP_EXPERIENCE_FILE ) );

	return;
}

if ( current_user_can( 'activate_plugins' ) ) {
    $slp_widget_slug = 'slp-widgets/slp-widgets.php';
    if (is_plugin_active($slp_widget_slug)) {
        deactivate_plugins($slp_widget_slug);

        add_action(
            'admin_notices',
            create_function(
                '',
                "echo '<div class=\"error\"><p>" .
                sprintf(
                    __('%s deactivated the conflicting SLP Widget Pack add-on. ', 'slp-experience'),
                    $this_plugin['Name']
                ) .
                "</p></div>';"
            )
        );
        return;
    }
}

if ( ! defined( 'SLP_EXPERIENCE_REL_DIR' ) ) define( 'SLP_EXPERIENCE_REL_DIR', plugin_dir_path( SLP_EXPERIENCE_FILE ) );
if ( ! defined( 'SLP_EXPERIENCE_VERSION' ) ) define( 'SLP_EXPERIENCE_VERSION', $this_plugin[ 'Version'] );

// Go forth and sprout your tentacles...
// Get some Store Locator Plus sauce.
//
require_once( SLP_EXPERIENCE_REL_DIR . 'include/SLP_Experience.php' );
SLP_Experience::init();
