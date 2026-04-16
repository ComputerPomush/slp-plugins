<?php

/*
 * Plugin Name:  Store Locator Plus® | Extended Data Manager
 * Plugin URI:   https://www.de-baat.nl/slp-extended-data-manager/
 * Description:  SLP Extended Data Manager is an add-on pack for Store Locator Plus that lets admin manage the extended data settings.
 * Author:       DeBAAT
 * Author URI:   https://www.de-baat.nl/slp/
 * License:      GPL3
 * Tested up to: 6.1.1
 * Version:      6.1.1
 * 
 * Text Domain:  slp-extended-data-manager
 * Domain Path:  /languages/
 * 
 * 
 * Copyright 2022 De B.A.A.T. (slp-extended-data-manager@de-baat.nl)
 */
// No direct access allowed outside WordPress
//
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
slp_edm_maybe_define_constant( 'SLP_EDM_FREEMIUS_ID', '3350' );
//
slp_edm_maybe_define_constant( 'SLP_EDM_SHORT_SLUG', 'slp-extended-data-manager' );
//
slp_edm_maybe_define_constant( 'SLP_EDM_PREMIUM_SLUG', 'slp-extended-data-manager-premium' );
//
slp_edm_maybe_define_constant( 'SLP_EDM_CLASS_PREFIX', 'SLP_Extended_Data_Manager_' );
//
slp_edm_maybe_define_constant( 'SLP_EDM_ADMIN_PAGE_SLUG', 'slp_general&tab=wpcsl-option-data' );
//
slp_edm_maybe_define_constant( 'SLP_EDM_ADMIN_PAGE_SLUG_FRE', 'slp_general&tab=wpcsl-option-data-pricing' );
//
slp_edm_maybe_define_constant( 'SLP_EDM_SELECTED_NAV_ELEMENT', '#wpcsl-option-data' );
//
slp_edm_maybe_define_constant( 'SLP_EDM_MIN_SLP', '5.5.0' );
//
slp_edm_maybe_define_constant( 'SLP_EDM_FILE', __FILE__ );
//
slp_edm_maybe_define_constant( 'SLP_EDM_REL_DIR', plugin_dir_path( SLP_EDM_FILE ) );
//
slp_edm_maybe_define_constant( 'WP_DEBUG_LOG_SLP_EDM', false );
//
slp_edm_maybe_define_constant( 'SLP_EDM_NO_INSTALLED_VERSION', '0.0.0' );
//
/**
 * Define a constant if it is not already defined.
 *
 * @param string $name  Constant name.
 * @param string $value Value.
 *
 * @since  6.1.1
 */
function slp_edm_maybe_define_constant( $name, $value )
{
    if ( !defined( $name ) ) {
        define( $name, $value );
    }
}

// Include Freemius SDK integration

if ( function_exists( 'slp_edm_freemius' ) ) {
    slp_edm_freemius()->set_basename( true, __FILE__ );
} else {
    // DO NOT REMOVE THIS IF, IT IS ESSENTIAL FOR THE `function_exists` CALL ABOVE TO PROPERLY WORK.
    
    if ( !function_exists( 'slp_edm_freemius' ) ) {
        // Create a helper function for easy SDK access.
        function slp_edm_freemius()
        {
            global  $slp_edm_freemius ;
            
            if ( !isset( $slp_edm_freemius ) ) {
                // Include Freemius SDK.
                require_once dirname( __FILE__ ) . '/freemius/start.php';
                $slp_edm_freemius = fs_dynamic_init( array(
                    'id'               => SLP_EDM_FREEMIUS_ID,
                    'slug'             => SLP_EDM_SHORT_SLUG,
                    'premium_slug'     => SLP_EDM_PREMIUM_SLUG,
                    'type'             => 'plugin',
                    'public_key'       => 'pk_8024b43c91b5edf416c251ce4d1a0',
                    'is_premium'       => false,
                    'premium_suffix'   => 'Premium',
                    'has_addons'       => false,
                    'has_paid_plans'   => true,
                    'is_org_compliant' => true,
                    'trial'            => array(
                    'days'               => 30,
                    'is_require_payment' => false,
                ),
                    'menu'             => array(
                    'slug'    => SLP_EDM_ADMIN_PAGE_SLUG,
                    'account' => false,
                    'contact' => false,
                    'support' => false,
                    'parent'  => array(
                    'slug' => 'csl-slplus',
                ),
                ),
                    'is_live'          => true,
                ) );
            }
            
            return $slp_edm_freemius;
        }
        
        // Init Freemius.
        slp_edm_freemius();
        // Signal that SDK was initiated.
        do_action( 'slp_edm_freemius_loaded' );
        function slp_edm_freemius_settings_url()
        {
            SLP_Extended_Data_Manager_debugMP( 'pr', __FUNCTION__ . ' started with _REQUEST = ', $_REQUEST );
            //slp_ext_set_selected_nav_element();
            return admin_url( 'admin.php?page=' . SLP_EDM_ADMIN_PAGE_SLUG );
        }
        
        slp_edm_freemius()->add_filter( 'connect_url', 'slp_edm_freemius_settings_url' );
        slp_edm_freemius()->add_filter( 'after_skip_url', 'slp_edm_freemius_settings_url' );
        slp_edm_freemius()->add_filter( 'after_connect_url', 'slp_edm_freemius_settings_url' );
        slp_edm_freemius()->add_filter( 'after_pending_connect_url', 'slp_edm_freemius_settings_url' );
    }
    
    /**
     * Get the Freemius object.
     *
     * @return string
     */
    function slp_edm_freemius_get_freemius()
    {
        return freemius( SLP_EDM_FREEMIUS_ID );
    }
    
    
    if ( function_exists( 'slp_edm_freemius' ) ) {
        slp_edm_freemius()->set_basename( false, __FILE__ );
        //	return;
    }
    
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX && !empty($_POST['action']) && $_POST['action'] === 'heartbeat' ) {
        return;
    }
    function SLP_Extended_Data_Manager_loader()
    {
        require_once 'include/base/loader.php';
    }
    
    add_action( 'plugins_loaded', 'SLP_Extended_Data_Manager_loader' );
    function SLP_Extended_Data_Manager_Get_Instance()
    {
        global  $slplus ;
        $edm_slug = SLP_EDM_SHORT_SLUG;
        $this_edm_addon = $slplus->AddOns->get( $edm_slug, 'instance' );
        return $this_edm_addon;
        return $slplus->AddOns->get( $edm_slug, 'instance' );
        return $slplus->AddOns->get( SLP_EDM_SHORT_SLUG, 'instance' );
    }
    
    function SLP_Extended_Data_Manager_admin_menu()
    {
        global  $_registered_pages ;
        $_registered_pages['admin_page_' . SLP_EDM_ADMIN_PAGE_SLUG] = true;
        $_registered_pages['admin_page_' . SLP_EDM_ADMIN_PAGE_SLUG_FRE] = true;
        $_registered_pages[SLP_EDM_ADMIN_PAGE_SLUG_FRE] = true;
        //	$_registered_pages['admin_page_' . SLP_EDM_ADMIN_PAGE_SLUG] = true;
    }
    
    /**
     * Set the selected_nav_element to return focus to that section.
     */
    // function slp_ext_set_selected_nav_element() {
    // global $slplus;
    // SLP_Extended_Data_Manager_debugMP('msg', __FUNCTION__ . ' started with slplus->clean[selected_nav_element] = ' . $slplus->clean[ 'selected_nav_element' ] );
    // $_REQUEST['selected_nav_element'] = SLP_EDM_SELECTED_NAV_ELEMENT;
    // if ( empty($slplus->clean[ 'selected_nav_element' ])) {
    // SLP_Extended_Data_Manager_debugMP('msg', __FUNCTION__ . ' reset with selected_nav_element = ' . SLP_EDM_SELECTED_NAV_ELEMENT );
    // $slplus->clean[ 'selected_nav_element' ] = SLP_EDM_SELECTED_NAV_ELEMENT;
    // $_SERVER['REQUEST_URI'] .= SLP_EDM_SELECTED_NAV_ELEMENT;
    // }
    // }
    function SLP_Extended_Data_Manager_admin_init()
    {
        global  $plugin_page ;
    }
    
    /**
     * Translate the slug for an add_on.
     *
     * @param object $this_addon this object for the addon
     * @param string $addon_slug slug for the addon
     *
     * @return object reference to this addon
     */
    function filter_edm_slp_get_addon( $this_addon, $addon_slug )
    {
        
        if ( strtolower( $addon_slug ) == 'extended' || strtolower( $addon_slug ) == 'extended-data-manager' ) {
            $this_edm_addon = SLP_Extended_Data_Manager_Get_Instance();
            return $this_edm_addon;
            // return SLP_Extended_Data_Manager_Get_Instance();
            // global $slplus;
            // return $slplus->AddOns->get( SLP_EDM_SHORT_SLUG, 'instance' );
        }
        
        return $this_addon;
    }
    
    /**
     * Auto-loads classes whenever new ClassName() is called.
     *
     * Loads them from the module/<submodule> directory for the add on.  <submodule> is the part after the class prefix before an _ or .
     * For example SLP_Extended_Data_Manager_Admin would load the include/module/admin/SLP_Extended_Data_Manager_Admin.php file.
     *
     * @param $class_name
     */
    function SLP_Extended_Data_Manager_auto_load( $class_name )
    {
        // error_log( __CLASS__ . '::' . __FUNCTION__ . ' 0: class_name = ' . $class_name );
        if ( strpos( $class_name, SLP_EDM_CLASS_PREFIX ) !== 0 ) {
            return;
        }
        // Set submodule and file name.
        //
        $prefix = SLP_EDM_CLASS_PREFIX;
        preg_match( "/{$prefix}([a-zA-Z]*)/", $class_name, $matches );
        $file_name = SLP_EDM_REL_DIR . 'include/module/' . (( isset( $matches[1] ) ? strtolower( $matches[1] ) . '/' : '' )) . $class_name . '.php';
        // error_log( __CLASS__ . '::' . __FUNCTION__ . ' 3: matches = ' . print_r( $matches, true ) );
        // error_log( __CLASS__ . '::' . __FUNCTION__ . ' 3: class_name = ' . $class_name );
        // error_log( __CLASS__ . '::' . __FUNCTION__ . ' 3: file_name  = ' . $file_name );
        // If the include/module/submodule/class.php file exists, load it.
        //
        if ( is_readable( $file_name ) ) {
            require_once $file_name;
        }
    }
    
    // Register the local SLP_Extended_Data_Manager_auto_load
    spl_autoload_register( 'SLP_Extended_Data_Manager_auto_load' );
    /**
     * Simplify the plugin debugMP interface.
     *
     * @param string $type
     * @param string $hdr
     * @param string $msg
     */
    function SLP_Extended_Data_Manager_debugMP(
        $type = 'msg',
        $header = '',
        $message = '',
        $file = null,
        $line = null,
        $notime = true
    )
    {
        $panel = 'slp.edm';
        if ( WP_DEBUG_LOG_SLP_EDM ) {
            switch ( strtolower( $type ) ) {
                case 'pr':
                    error_log( 'HDR: ' . $header . ' PR is no MSG ' . print_r( $message, true ) );
                    break;
                default:
                    error_log( 'HDR: ' . $header . ' MSG: ' . $message );
                    break;
            }
        }
        // Panel not setup yet?  Return and do nothing.
        //
        if ( !isset( $GLOBALS['DebugMyPlugin'] ) || !isset( $GLOBALS['DebugMyPlugin']->panels[$panel] ) ) {
            return;
        }
        // Do normal real-time message output.
        //
        switch ( strtolower( $type ) ) {
            case 'pr':
                $GLOBALS['DebugMyPlugin']->panels[$panel]->addPR(
                    $header,
                    $message,
                    $file,
                    $line,
                    $notime
                );
                break;
            default:
                $GLOBALS['DebugMyPlugin']->panels[$panel]->addMessage(
                    $header,
                    $message,
                    $file,
                    $line,
                    $notime
                );
                break;
        }
    }
    
    // Register the additional admin pages!!!
    add_action( 'admin_init', 'SLP_Extended_Data_Manager_admin_init', 25 );
    add_action( 'admin_menu', 'SLP_Extended_Data_Manager_admin_menu' );
    add_action( 'user_admin_menu', 'SLP_Extended_Data_Manager_admin_menu' );
    // Addon slug translation
    add_filter(
        'slp_get_addon',
        'filter_edm_slp_get_addon',
        10,
        2
    );
    add_action( 'dmp_addpanel', array( 'SLP_Extended_Data_Manager', 'create_DMPPanels' ) );
}
