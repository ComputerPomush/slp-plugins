<?php

if ( !class_exists( 'SLPEDM_Admin_GeneralSettings' ) ) {
    /**
     * The things that modify the Admin / General Tab.
     *
     * @package StoreLocatorPlus\SLP_Extended_Data_Manager\Admin\General
     * @author DeBAAT <slp-edm@de-baat.nl>
     * @copyright 2022 De B.A.A.T. - Charleston Software Associates, LLC
     *
     * @property    SLP_Extended_Data_Manager                                 $addon
     * @property    SLP_Extended_Data_Manager_Admin                           $admin
     * @property    SLP_Extended_Data_Manager_Text                            $Admin_General_Text
     * @property    SLP_Extended_Data_Manager_AdminUI_ElementManager_Add      $element_manager_add
     * @property    SLP_Extended_Data_Manager_AdminUI_ElementManager_Table    $element_manager_table
     */
    class SLP_Extended_Data_Manager_Admin_General extends SLPlus_BaseClass_Object
    {
        public  $addon ;
        public  $admin ;
        public  $settings ;
        private  $group_params ;
        // private $extended_data_manager;
        private  $element_manager_add_edit ;
        private  $element_manager_table ;
        /**
         * Things we do at the start.
         */
        function initialize()
        {
            SLP_Extended_Data_Manager_Text::get_instance();
            $this->group_params = array(
                'plugin'       => $this->addon,
                'section_slug' => null,
                'group_slug'   => null,
            );
            $this->createobject_ElementManagers();
            $this->process_edm_action();
            add_action( 'slp_build_general_settings_panels', array( $this, 'add_data_element_subtab' ), 90 );
        }
        
        /**
         * General / Data
         *
         * @param   SLP_Settings    $settings
         */
        public function process_edm_action()
        {
            $this->admin->action_SaveGeneralSettingsEDM();
        }
        
        /**
         * General / Data
         *
         * @param   SLP_Settings    $settings
         */
        public function add_data_element_subtab( $settings )
        {
            $this->settings = $settings;
            $this->render_general_data_group( $settings );
            $this->add_manage_extended_data_elements_group( $settings );
        }
        
        /**
         * General / Data / Add Extended Data Element Group
         *
         * @param   SLP_Settings    $settings
         */
        public function render_general_data_group( $settings )
        {
            $this->debugMP( 'msg', __FUNCTION__ . ' started.' );
            //$this->debugMP('pr', __FUNCTION__ . ' started with _REQUEST: ', $_REQUEST );
            // Add the section for the Extended Data Element group
            $section_params['name'] = __( 'Data', 'slp-extended-data-manager' );
            $section_params['slug'] = SLP_EDM_SECTION_SLUG;
            $settings->add_section( $section_params );
        }
        
        /**
         * Create the ElementManagers interfaces.
         */
        function createobject_ElementManagers()
        {
            if ( !isset( $this->element_manager_table ) ) {
                $this->element_manager_table = new SLP_Extended_Data_Manager_AdminUI_ElementManager_Table( array(
                    'addon'  => $this->addon,
                    'admin'  => $this->admin,
                    'slplus' => $this->slplus,
                ) );
            }
        }
        
        /**
         * General / Data / Manage Extended Data Elements
         *
         * @param   SLP_Settings    $settings
         */
        private function add_manage_extended_data_elements_group( $settings )
        {
            $section_params['name'] = __( 'Data', 'slp-extended-data-manager' );
            // with the add_extended_data_element_group method called first we don't really need this.
            $section_params['slug'] = SLP_EDM_SECTION_SLUG;
            $settings->add_section( $section_params );
            $group_params['header'] = __( 'Extended Data Elements', 'slp-extended-data-manager' );
            $group_params['section_slug'] = SLP_EDM_SECTION_SLUG;
            $group_params['group_slug'] = SLP_EDM_SECTION_SLUG_TABLE;
            $settings->add_group( $group_params );
            $this->element_manager_table->prepare_items();
            $settings->add_ItemToGroup( array(
                'section_slug' => SLP_EDM_SECTION_SLUG,
                'group_slug'   => SLP_EDM_SECTION_SLUG_TABLE,
                'label'        => '',
                'type'         => 'details',
                'show_label'   => false,
                'description'  => $this->element_manager_table->display(),
            ) );
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