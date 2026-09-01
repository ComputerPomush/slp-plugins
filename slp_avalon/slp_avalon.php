<?php
/**
 * Plugin Name: Store Locator Plus Avalon
 * Description: Avalon Customization for SLP plugin
 * Version: 0.0.14
 * Author: WildMedia
 */

  // If this file is called directly, abort.
  defined( 'ABSPATH' ) || exit;
if ( defined( 'DOING_AJAX' ) && DOING_AJAX && ! empty( $_REQUEST['action'] ) && ( $_REQUEST['action'] === 'heartbeat' ) ) {
	return;
}
 define( 'ASLP_DIR', plugin_dir_path( __FILE__ ) );
 define( 'ASLP_URL', plugin_dir_url( __FILE__ ) );
 // Define plugin base file.
 define( 'ASLP_BASE_FILE', __FILE__ );
 

 
 //Main Class File
 require_once ASLP_DIR . 'inc/class.slp_avalon.php';
 
 
 //Main Function
 function aslp_init(){
    if (!defined('SLP_POWER_FILE')){
        //SLP power plugin not loaded, and we need it
        function aslp_notify_slplus_dependency() {
            echo '<div class="error"><p>' .
                 'Store Locator Plus Avalon' .
                 ' requires Store Locator Plus® - Power to function properly. '.
                 '<br/>' .
                 'This plugin has been deactivated.'.
                 ' Please install Store Locator Plus® - Power.' .
                 '</p></div>';
        }
    
        add_action( 'admin_notices', 'aslp_notify_slplus_dependency' );
        deactivate_plugins( plugin_basename( ASLP_BASE_FILE ) );
    
        return;
    }
     return SLP_Avalon::instance();
 }
 /**
  * Plugin activation actions.
  *
  * Actions to perform during plugin activation.
  * We will be registering default options in this function.
  *
  * @uses   register_activation_hook() To register activation hook.
  */
 register_activation_hook(
    ASLP_BASE_FILE,
     array( 'SLP_Avalon', 'activate' )
 );
 
 // Run the plugin.
 add_action( 'plugins_loaded', 'aslp_init' );