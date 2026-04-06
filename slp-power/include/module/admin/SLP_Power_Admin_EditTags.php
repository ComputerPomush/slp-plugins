<?php
defined( 'ABSPATH' ) || exit;

/**
 * The WP edit tags interface.
 *
 * @property        SLPPower     $addon
 */
class SLP_Power_Admin_EditTags extends SLPlus_BaseClass_Object {

    /**
     * Add the WordPress taxonomy editor filters.
     */
    public function add_wp_filters() {
        add_filter( 'term_updated_messages'                                       , array( $this , 'set_messages'      )            );
        add_filter( 'manage_edit-' . SLPlus::locationTaxonomy  . '_columns'       , array( $this , 'set_columns'       )            );
        add_filter( 'manage_'      . SLPlus::locationTaxonomy  . '_custom_column' , array( $this , 'set_column_data'   ) , 20 , 3   );
    }

	/**
	 * @param $messages
	 *
	 * @return mixed
	 */
    public function set_messages( $messages ) {
	    return SLP_Power_Category_Stores_Taxonomy::get_instance()->set_messages( $messages );
    }

	/**
	 * @param $columns
	 *
	 * @return mixed
	 */
    public function set_columns( $columns ) {
        return SLP_Power_Category_Stores_Taxonomy::get_instance()->set_columns( $columns );
    }

	/**
	 * @param $output
	 * @param string $column_name
	 * @param null $term_id
	 *
	 * @return mixed
	 */
    public function set_column_data( $output, $column_name = '', $term_id = null ) {
        return SLP_Power_Category_Stores_Taxonomy::get_instance()->set_column_data( $output, $column_name, $term_id );
    }
}

