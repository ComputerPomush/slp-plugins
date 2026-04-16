<?php

defined( 'ABSPATH' ) || exit;

if ( !class_exists( 'SLP_Extended_Data_Manager_Admin' ) ) {
    require_once SLPLUS_PLUGINDIR . 'include/module/admin_tabs/SLP_BaseClass_Admin.php';
    /**
     * Holds the admin-only code.
     *
     * @package StoreLocatorPlus\SLP_Extended_Data_Manager\Admin
     * @author DeBAAT <slp-edm@de-baat.nl>
     * @copyright 2022 De B.A.A.T. - Charleston Software Associates, LLC
     *
     * This allows the main plugin to only include this file in admin mode
     * via the admin_menu call.   Reduces the front-end footprint.
     *
     * @property        SLP_Extended_Data_Manager                     $addon
     * @property        SLP_Extended_Data_Manager_Activation          $edm_activation       The edm_activation object.
     * @property        SLP_Extended_Data_Manager_Admin_General       $edm_admin_general    The edm_general_settings tab.
     * @property        SLP_Extended_Data_Manager_Admin_Elements      $edm_admin_elements   The admin Elements object.
     */
    class SLP_Extended_Data_Manager_Admin extends SLP_BaseClass_Admin
    {
        //-------------------------------------
        // Properties
        //-------------------------------------
        protected  $class_prefix = SLP_EDM_CLASS_PREFIX ;
        /**
         * This addon pack.
         *
         * @var \SLP_Extended_Data_Manager $addon
         */
        public  $addon ;
        public  $edm_activation ;
        public  $edm_admin_general ;
        public  $edm_admin_elements ;
        //-------------------------------------
        // Methods
        //-------------------------------------
        /**
         * Add our SLP hooks and Filters for Admin Mode
         */
        public function add_hooks_and_filters()
        {
            $this->debugMP( 'msg', __FUNCTION__ . '2 started.' );
            parent::add_hooks_and_filters();
            // Create admin objects
            $this->create_object_admin_objects();
            // Load objects based on which admin page we are on.
            //
            if ( isset( $_REQUEST['page'] ) ) {
                switch ( $_REQUEST['page'] ) {
                    case 'slp_general':
                        $this->create_object_general();
                        break;
                    case 'slp_info':
                        $this->create_object_info();
                        break;
                }
            }
            // Admin skinning and scripts
            //
            add_action( 'admin_enqueue_scripts', array( $this, 'action_EnqueueAdminScriptsEDM' ) );
        }
        
        /**
         * Enqueue the admin scripts.
         *
         * @param string $hook
         */
        function action_EnqueueAdminScriptsEDM( $hook )
        {
            $this->debugMP( 'msg', __FUNCTION__, $hook );
            $styleHandle = '';
            // Load up the edm_admin.css style sheet for Extended Data Manager
            //
            $this->debugMP( 'msg', __FUNCTION__, $this->addon->url . '/css/edm_admin.css' );
            wp_register_style( 'slp_edm_style', $this->addon->url . '/css/edm_admin.css' );
            wp_enqueue_style( 'slp_edm_style' );
        }
        
        /**
         * Create and attach the admin objects.
         */
        private function create_object_admin_objects()
        {
            $this->debugMP( 'msg', __FUNCTION__ . ' started.' );
            // Create the Admin_Elements objects
            $this->edm_admin_elements = new SLP_Extended_Data_Manager_Admin_Elements( array(
                'addon' => $this->addon,
                'admin' => $this,
            ) );
        }
        
        /**
         * Create and attach the admin info object.
         */
        private function create_object_info()
        {
            $this->debugMP( 'msg', __FUNCTION__ . ' started.' );
            if ( !isset( $this->info ) ) {
                //require_once( SLP_EDM_REL_DIR . 'include/module/admin/SLP_Extended_Data_Manager_Admin_Info.php' );
                $this->info = new SLP_Extended_Data_Manager_Admin_Info( array(
                    'addon' => $this->addon,
                    'admin' => $this,
                ) );
            }
        }
        
        /**
         * Create and attach the user general object.
         */
        private function create_object_general()
        {
            $this->debugMP( 'msg', __FUNCTION__ . ' started.' );
            if ( !isset( $this->edm_admin_general ) ) {
                //require_once('class.admin.general.php');
                $this->edm_admin_general = new SLP_Extended_Data_Manager_Admin_General( array(
                    'addon' => $this->addon,
                    'admin' => $this,
                ) );
            }
        }
        
        /**
         * Create the activation object
         */
        function create_object_edm_activation()
        {
            $this->debugMP( 'msg', __FUNCTION__ . ' started.' );
            
            if ( !isset( $this->edm_activation ) ) {
                $this->edm_activation = new SLP_Extended_Data_Manager_Activation( array(
                    'addon' => $this->addon,
                    'admin' => $this,
                ) );
                $this->debugMP( 'msg', __FUNCTION__ . ' SLP_Extended_Data_Manager_Activation created.' );
            }
        
        }
        
        /**
         * If there is a newer version get the link.
         *
         * @return string
         */
        public function get_newer_version()
        {
            $this->debugMP( 'msg', __FUNCTION__ . ' started.' );
            $this->debugMP( 'msg', __FUNCTION__ . ' TODO: Replace with Freemius function.' );
            return '';
            return 'get_newer_version TODO: Replace with Freemius function.';
        }
        
        /**
         * Execute some admin startup things for this add-on pack.
         */
        function do_admin_startup()
        {
            $this->debugMP( 'msg', __FUNCTION__ . ' started.' );
            $this->debugMP( 'pr', __FUNCTION__ . ' _SERVER = ', $_SERVER );
            $this->debugMP( 'pr', __FUNCTION__ . ' _REQUEST = ', $_REQUEST );
            // Check we need to be on our admin page
            if ( isset( $_SERVER['REQUEST_URI'] ) ) {
                
                if ( false !== strpos( $_SERVER['REQUEST_URI'], 'page=' . SLP_EDM_ADMIN_PAGE_SLUG ) ) {
                    // Set the selected_nav_element
                    $this->set_selected_nav_element();
                    $this->debugMP( 'msg', __FUNCTION__ . ' set_selected_nav_element because _SERVER[REQUEST_URI] contains ' . SLP_EDM_ADMIN_PAGE_SLUG . '.' );
                }
            
            }
            parent::do_admin_startup();
        }
        
        /**
         * Save general settings for extended data options.
         *
         * We need this instead of just calling data_SaveOptions as we want to screen $_REQUEST for known values.
         * Checkboxes are evil and are NOT passed in the input stream if they are off.  WTF.   A decade of coding around a
         * bad design decision made in HTML and parameter passing... you think someone would have addressed this by now.
         */
        function action_SaveGeneralSettingsEDM()
        {
            // Check and process the order information if available
            
            if ( isset( $_REQUEST[SLP_EDM_ACTION_ELEMENT_UPDATE] ) ) {
                $slugValues = array();
                
                if ( is_array( $_REQUEST[SLP_EDM_ACTION_ELEMENT_UPDATE] ) ) {
                    foreach ( $_REQUEST[SLP_EDM_ACTION_ELEMENT_UPDATE] as $slugValue ) {
                        $slugValues[] = $slugValue;
                    }
                } else {
                    $slugValues[] = $_REQUEST[SLP_EDM_ACTION_ELEMENT_UPDATE];
                }
                
                $this->debugMP( 'pr', __FUNCTION__ . ' started for request_name_option ' . SLP_EDM_ACTION_ELEMENT_UPDATE . ', slugValues=', $slugValues );
                $slugValuesSanitized = $this->get_sanitize_key_array( $_REQUEST, SLP_EDM_ACTION_ELEMENT_UPDATE );
                $this->debugMP( 'pr', __FUNCTION__ . ' started for request_name_option ' . SLP_EDM_ACTION_ELEMENT_UPDATE . ', slugValuesSanitized=', $slugValuesSanitized );
                // Process option values found
                $table_data = $this->edm_admin_elements->get_extended_data_elements( false );
                foreach ( $slugValues as $curSlug => $curValue ) {
                    // Only allow integer values for option_order
                    
                    if ( isset( $curValue[SLP_EDM_OPTION_ORDER] ) && is_numeric( $curValue[SLP_EDM_OPTION_ORDER] ) ) {
                        $intValue = $curValue[SLP_EDM_OPTION_ORDER];
                    } else {
                        $intValue = '';
                    }
                    
                    //					$this->debugMP('pr',__FUNCTION__.' started for intValue = ' . is_numeric($curValue) . ' for curValue: ', $curValue);
                    if ( isset( $table_data[$curSlug] ) ) {
                        $this->edm_admin_elements->slp_edm_element_update(
                            $curSlug,
                            $curValue,
                            SLP_EDM_OPTION_ORDER,
                            $intValue
                        );
                    }
                }
                // Set the selected_nav_element
                $this->set_selected_nav_element();
                //$_REQUEST['selected_nav_element'] = SLP_EDM_SELECTED_NAV_ELEMENT;
            }
        
        }
        
        /**
         * Add our admin pages to the valid admin page slugs.
         *
         * @param string[] $slugs admin page slugs
         * @return string[] modified list of admin page slugs
         */
        function filter_AddOurAdminSlug( $slugs )
        {
            $this->debugMP( 'msg', __FUNCTION__ . ' started.' );
            $slugs = parent::filter_AddOurAdminSlug( $slugs );
            $slugs = array_merge( $slugs, array( SLP_EDM_ADMIN_PAGE_SLUG, SLP_ADMIN_PAGEPRE . SLP_EDM_ADMIN_PAGE_SLUG ) );
            $this->debugMP( 'pr', __FUNCTION__ . ' returned slugs:', $slugs );
            return $slugs;
        }
        
        /**
         * Add meta links specific for this AddOn.
         *
         * @param string[] $links
         * @param string   $file
         *
         * @return string
         */
        function add_meta_links( $links, $file )
        {
            $this->debugMP( 'msg', __FUNCTION__ . ' started.' );
            
            if ( $file == $this->addon->slug ) {
                // Add Documentation support_url link
                $link_text = __( 'Documentation', 'slp-extended-data-manager' );
                $links[] = sprintf(
                    '<a href="%s" title="%s" target="store_locator_plus">%s</a>',
                    SLP_EDM_SUPPORT_URL,
                    $link_text,
                    $link_text
                );
                // Add Settings link
                $link_text = __( 'Settings', 'slp-extended-data-manager' );
                $links[] = sprintf(
                    '<a href="%s" title="%s">%s</a>',
                    admin_url( 'admin.php?page=' . SLP_EDM_ADMIN_PAGE_SLUG ),
                    $link_text,
                    $link_text
                );
                // $newer_version = $this->get_newer_version();
                // if ( ! empty( $newer_version ) ) {
                // $links[] = '<strong>' . sprintf( __( 'Version %s in production ', 'slp-extended-data-manager' ), $newer_version ) . '</strong>';
                // }
            }
            
            return $links;
        }
        
        /**
         * Set valid options from the incoming REQUEST
         *
         * @param mixed  $val - the value of a form var
         * @param string $key - the key for that form var
         */
        function set_ValidOptions( $val, $key )
        {
            $this->debugMP( 'msg', __FUNCTION__ . ' started.' );
            $simpleKey = str_replace( SLPLUS_PREFIX . '-', '', $key );
            if ( array_key_exists( $simpleKey, $this->addon->options ) ) {
                $_POST[$this->addon->option_name][$simpleKey] = stripslashes_deep( $val );
            }
        }
        
        /**
         * Update_install_info for this add-on pack.
         */
        function update_install_info()
        {
            $this->debugMP( 'msg', __FUNCTION__ . ' started.' );
            parent::update_install_info();
            // Do a check on the activation update
            $this->create_object_edm_activation();
            $this->edm_activation->update();
        }
        
        /**
         * Creates the string to use a name for the setting.
         *
         * @param bool $addform - true if rendering add socials form
         */
        function create_SettingsSetting( $settingName, $settingAction, $settingID = '' )
        {
            $this->debugMP( 'msg', __FUNCTION__ . ' settingName = ' . $settingName . ', settingID = ' . $settingID . '.' );
            return $settingAction . SLP_EDM_CSL_SEPARATOR . $settingName . SLP_EDM_CSL_SEPARATOR . $settingID;
        }
        
        /**
         * Get the string used as name for the setting.
         *
         * @param bool $addform - true if rendering add socials form
         */
        function get_SettingsSettingKey( $settingKey, $settingAction, $settingID = '' )
        {
            $this->debugMP( 'msg', __FUNCTION__, ' settingKey = ' . $settingKey . ', settingID = ' . $settingID . '.' );
            $keyPattern = '#^.*' . $settingAction . SLP_EDM_CSL_SEPARATOR . '(.*)' . SLP_EDM_CSL_SEPARATOR . '.*#';
            $keyReplacement = '\\1';
            $newSettingKey = preg_replace( $keyPattern, $keyReplacement, $settingKey );
            $this->debugMP( 'msg', '', ' keyPattern = ' . $keyPattern . ', keyReplacement = ' . $keyReplacement . '.' );
            $this->debugMP( 'msg', '', ' settingKey = ' . $settingKey . ', newSettingKey = ' . $newSettingKey . '.' );
            return $newSettingKey;
        }
        
        /**
         * Set the selected_nav_element to return focus to that section.
         */
        function set_selected_nav_element()
        {
            global  $slplus ;
            $this->debugMP( 'msg', __FUNCTION__ . ' started with slplus->clean[selected_nav_element] = ' . $slplus->clean['selected_nav_element'] );
            $_REQUEST['selected_nav_element'] = SLP_EDM_SELECTED_NAV_ELEMENT;
            
            if ( empty($slplus->clean['selected_nav_element']) ) {
                $this->debugMP( 'msg', __FUNCTION__ . ' reset with selected_nav_element = ' . SLP_EDM_SELECTED_NAV_ELEMENT );
                $slplus->clean['selected_nav_element'] = SLP_EDM_SELECTED_NAV_ELEMENT;
                $_SERVER['REQUEST_URI'] .= SLP_EDM_SELECTED_NAV_ELEMENT;
            }
        
        }
        
        /**
         * Get the current action being executed by the plugin.
         */
        function is_CurrentAction_edm_filter_action_selected()
        {
            $current_action = '';
            if ( isset( $_REQUEST['act'] ) ) {
                $current_action = sanitize_key( $_REQUEST['act'] );
            }
            return $current_action == SLP_EDM_FILTER_ACTION;
        }
        
        /**
         * Get the current action being executed by the plugin.
         */
        function get_CurrentAction()
        {
            $current_action = '';
            if ( isset( $_REQUEST['act'] ) ) {
                $current_action = sanitize_key( $_REQUEST['act'] );
            }
            return $current_action;
        }
        
        /**
         * Get the ID values from the input array, e.g. _REQUEST
         *
         */
        public function get_sanitize_key_array( $input_array = null, $input_key = '' )
        {
            // $this->debugMP('msg',__FUNCTION__.' started.');
            // Check input parameters
            if ( $input_array == null ) {
                return '';
            }
            if ( $input_key == '' ) {
                return '';
            }
            if ( !isset( $input_array[$input_key] ) ) {
                return '';
            }
            // Get sanitize_key for values
            
            if ( is_array( $input_array[$input_key] ) ) {
                $output_value = array();
                foreach ( $input_array[$input_key] as $key => $value ) {
                    $output_value[$key] = $this->get_sanitize_key_array( $input_array[$input_key], $key );
                }
            } else {
                $output_value = sanitize_text_field( $input_array[$input_key] );
            }
            
            return $output_value;
        }
        
        /**
         * Simplify the plugin debugMP interface.
         *
         * Typical start of function call: $this->debugMP('msg',__FUNCTION__);
         *
         * @param string $type
         * @param string $hdr
         * @param string $msg
         */
        function debugMP( $type, $hdr, $msg = '' )
        {
            if ( $type === 'msg' && $msg !== '' ) {
                $msg = esc_html( $msg );
            }
            if ( $hdr !== '' ) {
                // Adding __CLASS__ to non-empty hdr
                $hdr = __CLASS__ . '::' . $hdr;
            }
            SLP_Extended_Data_Manager_debugMP(
                $type,
                $hdr,
                $msg,
                NULL,
                NULL,
                true
            );
        }
    
    }
}
