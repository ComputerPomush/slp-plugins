<?php
defined( 'ABSPATH' ) || exit;


/**
 * The data interface helper.
 *
 * @package StoreLocatorPlus\SLP_Extended_Data_Manager\AdminUI\ElementManager_Add
 * @author De B.A.A.T. <slp-edm@de-baat.nl>
 * @copyright 2022 De B.A.A.T. - Charleston Software Associates, LLC
 *
 * @property        SLP_Extended_Data_Manager                     $addon
 * @property        SLP_Extended_Data_Manager_Admin               $admin                The admin object for this addon.
 *
 */
class SLP_Extended_Data_Manager_AdminUI_ElementManager_Add extends SLPlus_BaseClass_Object {

	public  $addon;
	public  $admin;

	/**
	 * Initialize this object.
	 */
	public function initialize() {
		SLP_Extended_Data_Manager_Text::get_instance();

	}

	/**
	 * General / Data / Add Extended Data Element
	 *
	 * @param   SLP_Settings    $settings
	 */
	public function render_ElementAddGroup( $settings ) {
		$this->debugMP('msg',__FUNCTION__.' started.');
		//$this->debugMP('pr', __FUNCTION__ . ' started with _REQUEST: ', $_REQUEST );

		// Add the Add Extended Data Element group itself
		$group_params['header']         = __( 'Add Extended Data Element' , 'slp-extended-data-manager' );
		$group_params['section_slug']   = SLP_EDM_SECTION_SLUG;
		$group_params['group_slug']     = SLP_EDM_SECTION_SLUG_ADD;
		$settings->add_group( $group_params );

		// Add the slug entry to the Add Extended Data Element group
		$settings->add_ItemToGroup(array(
				'section_slug'  => SLP_EDM_SECTION_SLUG,
				'group_params'  => $group_params,
				'label'         => __( 'New Element Label', 'slp-extended-data-manager' ),
				'id'            => SLP_EDM_ACTION_ELEMENT_ADD,
				'name'          => SLP_EDM_ACTION_ELEMENT_ADD,
				'value'         => '',
				'use_prefix'    => false,
				'description'   =>
					__( 'The label for the new Extended Data element.', 'slp-extended-data-manager' )
					. ' ' .
					__( 'The label is used to generate the slug, it makes the label all lower case and replaces all spaces and odd characters with an underscore "_".', 'slp-extended-data-manager' )
					. ' ' .
					__( 'The slug is used to identify the element and thus cannot be changed.', 'slp-extended-data-manager' )
					. ' ' .
					__( 'If you want to change the slug, you should delete the element and add a new one with the desired slug.', 'slp-extended-data-manager' )
					. ' ' .
					__( 'The label, type and order values can be updated after the element is added.', 'slp-extended-data-manager' )
		));

		// Add the Add New Element button to the Add Extended Data Element group
		$settings->add_ItemToGroup(array(
			'section_slug'  => SLP_EDM_SECTION_SLUG,
			'group_params'  => $group_params,
			'value'         => __('Add New Element','slp-extended-data-manager'),
			'type'          => 'submit_button',
			'show_label'    => false,
			'description'   => ''
		));
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
	function debugMP($type,$hdr,$msg='') {
		if (($type === 'msg') && ($msg!=='')) {
			$msg = esc_html($msg);
		}
		if (($hdr!=='')) {   // Adding __CLASS__ to non-empty hdr
			$hdr = __CLASS__ . '::' . $hdr;
		}
		SLP_Extended_Data_Manager_debugMP($type,$hdr,$msg,NULL,NULL,true);
	}

}
