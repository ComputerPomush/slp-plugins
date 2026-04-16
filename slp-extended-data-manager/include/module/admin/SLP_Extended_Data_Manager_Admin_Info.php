<?php

if ( !class_exists( 'SLP_Extended_Data_Manager_Admin_Info' ) ) {
    /**
     * The things that modify the Admin / General Tab.
     *
     * @package StoreLocatorPlus\SLP_Extended_Data_Manager\Admin\Info
     * @author DeBAAT <slp-edm@de-baat.nl>
     * @copyright 2022 De B.A.A.T. - Charleston Software Associates, LLC
     *
     * Text Domain: slp-extended-data-manager
     *
     * @property        SLP_Extended_Data_Manager          $addon
     */
    class SLP_Extended_Data_Manager_Admin_Info extends SLPlus_BaseClass_Object
    {
        public  $addon ;
        /**
         * Things we do at the start.
         */
        public function initialize()
        {
            $this->add_hooks_and_filters();
        }
        
        /**
         * WP and SLP hooks and filters.
         */
        private function add_hooks_and_filters()
        {
            $this->debugMP( 'msg', __FUNCTION__ . '-i started.' );
            add_filter( 'slp_version_report_' . $this->addon->short_slug, array( $this, 'show_activated_modules' ) );
        }
        
        /**
         * Show activated modules.
         *
         * @param $version
         *
         * @return mixed
         */
        public function show_activated_modules( $version )
        {
            $active_modules = array();
            
            if ( !empty($active_modules) ) {
                $active_modules = '<br/><span class="label">+</span>' . join( ', ', $active_modules );
            } else {
                $active_modules = '';
            }
            
            return $version . $active_modules;
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