<?php
// Placeholder until code is updated to use singleton
defined( 'ABSPATH' ) || exit;
global $slplus;
if ( is_a( $slplus, 'SLPlus' ) ) {
	$slplus->add_object( SLP_Country_Manager::get_instance() );
}
