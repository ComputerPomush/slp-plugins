<?php
defined( 'SLPLUS_VERSION' ) || exit;

/**
 * Handle the Schedule apiGroup endpoints
 *
 * Read [the REST API documentation](./api) for more details.
 *
 * @package StoreLocatorPlus\REST
 */
class SLP_REST_Schedule extends SLPlus_BaseClass_Object {
	/**
	 * Get the schedule.
	 * @return void
	 */
	public function get() {
		$data = [
			[
				'label' => __( 'About', 'store-locator-le' ),
				'value' => $this->about(),
			],
			[
				'label' => __( 'Current Time', 'store-locator-le' ),
				'value' => date( "d F Y H:i:s", time() ),
			],
			[
				'label' => __( '', 'store-locator-le' ),
				'value' => $this->get_wp_cron_list(),
			],
		];

		return $data;
	}

	/**
	 * Get the about header.
	 * @return string
	 */
	private function about() {
		$message      = __( 'This list shows the internal ID for the WP Cron tasks followed by the next time the task will run. ', 'store-locator-le' );
		$more_message = __( 'Store Locator Plus® scheduled items are found under the General / Schedule tab and Location / Import tab.', 'store-locator-le' );

		return sprintf( '<p>%s</p><p>%s</p>', $message, $more_message );
	}

	/**
	 * Get a formatted list of WP Cron items.
	 *
	 * @return string
	 */
	private function get_wp_cron_list() {
		$crons = _get_cron_array();
		if ( empty( $crons ) ) {
			return __( 'There is nothing scheduled to run automatically on this site.', 'store-locator-le' );
		}

		$cron_events = array();
		$html        =
			'<p class="cron_entry">' .
			'<span class="hook">%s</span>' .
			'</p>';
		foreach ( $crons as $timestamp => $cron ) {
			foreach ( $cron as $slug => $details ) {
				$mdkey                                                         = key( $details );
				$cron_events[ $details[ $mdkey ]['schedule'] ][ $timestamp ][] = sprintf( $html, $slug );
			}
		}

		$cron_table = '';
		foreach ( $cron_events as $schedule => $list_of_times ) {
			$cron_table      .= sprintf( '<h4 class="cron_schedule">%s</h4>', $schedule );
			$previous_run_at = null;
			foreach ( $list_of_times as $run_at => $list_of_events ) {
				if ( is_null( $previous_run_at ) ) {
					$cron_table      .= sprintf( '<h5 class="run_time">Next run: %s</h5>', date( "d F Y H:i:s", $run_at ) );
					$previous_run_at = $run_at;
				}
				foreach ( $list_of_events as $event_details ) {
					$cron_table .= sprintf( '<div class="event">%s</div>', $event_details );
				}
			}
		}


		return $cron_table;
	}

}
