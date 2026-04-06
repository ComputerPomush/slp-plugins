<?php
defined( 'ABSPATH' ) || exit;

/**
 * The things that modify the Admin / General Tab.
 */
class SLP_Power_Admin_Info  extends SLPlus_BaseClass_Object {
    /**
     * Things we do at the start.
     */
    public function initialize() {
	    add_filter( 'slp_version_report_slp-power' , array( $this , 'show_activated_modules' ) );
    }

    /**
     * Show activated modules.
     *
     * @param $version
     *
     * @return mixed
     */
    public function show_activated_modules( $version ) {
        $active_modules = array();
        if ( $this->slplus->SmartOptions->use_pages->is_true ) {
            $active_modules[] = 'Pages';
        }
        if ( $this->slplus->SmartOptions->use_contact_fields->is_true ) {
            $active_modules[] = 'Contacts';
        }

        if ( ! empty( $active_modules ) ){
            $active_modules = '<br/><span class="label">+</span>' . join( ' , ' , $active_modules );
        } else {
            $active_modules = '';
        }

        return $version . $active_modules;
    }
}
