<?php
defined( 'SLPLUS_VERSION' ) || exit;

class SLP_Power_Text_Pages_Tab extends SLPlus_BaseClass_Object {
	public function initialize() {
		/** @var  SLP_Text $the */
		$the = SLP_Text::get_instance();

		$the->text_strings['label']['pages_directory_entry_css_class']       = __( 'Page List Item Class', 'slp-power' );
		$the->text_strings['description']['pages_directory_entry_css_class'] = __( 'CSS class used with individual page listing entries.', 'slp-power' );

		$the->text_strings['label']['pages_directory_wrapper_css_class']       = __( 'Page List Wrapper Class', 'slp-power' );
		$the->text_strings['description']['pages_directory_wrapper_css_class'] = __( 'CSS class used with the div that wraps individual page listing entries.', 'slp-power' );

		$the->text_strings['label']['permalink_flush_needed']       = __( 'Flush Permalinks', 'slp-power' );
		$the->text_strings['description']['permalink_flush_needed'] = __( 'Flush the WordPress permalinks cache whenever URL info chages. ', 'slp-power' ) .
		                                                              __( 'Use sparingly, this can take a lot of time to process. ', 'slp-power' );
	}
}