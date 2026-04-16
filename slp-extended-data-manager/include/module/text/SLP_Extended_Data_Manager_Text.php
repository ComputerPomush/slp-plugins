<?php
defined( 'ABSPATH' ) || exit;

/**
 * Augment the SLP text tables.
 *
 * @package StoreLocatorPlus\SLP_Extended_Data_Manager\Text
 * @author DeBAAT <slp-edm@de-baat.nl>
 * @copyright 2022 De B.A.A.T. - Charleston Software Associates, LLC
 *
 * @var array    text    array of our text modifications key => SLP text manager slug, value = our replacement text
 */
class SLP_Extended_Data_Manager_Text extends SLPlus_BaseClass_Object {
    private $text;

	/**
	 * Things we do at the start.
	 */
	public function initialize() {
		add_filter('slp_get_text_string', array( $this, 'augment_text_string' ) , 10, 2);
		//$this->debugMP('msg',__FUNCTION__.' started.');
	}

    /**
     * Replace the SLP Text Manager Strings at startup.
     *
     * @param string $text the original text
     * @param string $slug the slug being requested
     *
     * @return string            the new SLP text manager strings
     */
    public function augment_text_string($text, $slug) {
        $this->init_text();

        if (!is_array($slug)) $slug = array( 'general' , $slug );

        if (isset($this->text[$slug[0]]) && isset($this->text[$slug[0]][$slug[1]])) {
            return $this->text[$slug[0]][$slug[1]];
        }

        return $text;
    }

    /**
     * Initialize our text modification array.
     */
    private function init_text() {
        if (isset($this->text)) return;

		$this->init_text_sections_and_groups();
		$this->init_text_elementmanager_section();

    }

    /**
     * Initialize our text modification array for sections and groups.
     */
    private function init_text_sections_and_groups() {

		// Sections
	    $this->text['settings_section']['edm_extended_data_manager_section'  ] = __( 'Extended Data Manager Section', 'slp-extended-data-manager');
	    $this->text['settings_section']['edm_elementmanager_section'         ] = __( 'Element Manager Settings',      'slp-extended-data-manager');

		// Groups
	    $this->text['settings_group']['edm_page_settings'           ] = __( 'Page Settings',            'slp-extended-data-manager');
	    $this->text['settings_group']['edm_general_settings'        ] = __( 'General Settings',         'slp-extended-data-manager');
	    $this->text['settings_group']['edm_elementmanager_settings' ] = __( 'Element Manager Settings', 'slp-extended-data-manager');

		$this->text['settings_group_header' ] = $this->text[ 'settings_group' ];

	}

    /**
     * Initialize our text modification array.
     */
    private function init_text_elementmanager_section() {

	    $this->text['description']['edm_elements_per_page'          ] = __( 'How many elements should be shown in the overview table.', 'slp-extended-data-manager' ) . ' ' .
																		__( 'Recommended value is 20.', 'slp-extended-data-manager' );

	    $this->text['label']['edm_elements_per_page'                ] = __( 'Number To Show',                             'slp-extended-data-manager');

	    $this->text['option_default']['edm_elements_per_page'       ] = '20';

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