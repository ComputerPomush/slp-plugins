<?php
/**
 * Handle the AJAX location_manager requests.
 *
 * @property    SLP_AJAX        $ajax
 */
class SLP_AJAX_Location_Manager extends SLPlus_BaseClass_Object {

    /**
     * Delete a single location.
     */
    public function delete_location() {
	    $this->slplus->currentLocation->set_PropertiesViaDB( $this->slplus->ajax->query_params['location_id'] );

	    $status = $this->slplus->currentLocation->delete();
	    if ( is_int( $status ) ) {
		    $count = $status;
		    $status = 'ok';
	    } else {
	        $count = '0';
		    $status = 'error';
	    }


	    $response = array(
	        'status'       => $status,
		    'count'        => $count,
	        'action'      => 'delete_location',
	        'location_id' => $this->slplus->ajax->query_params['location_id'],
	    );

	    wp_die( wp_json_encode( $response ) );
    }
}
