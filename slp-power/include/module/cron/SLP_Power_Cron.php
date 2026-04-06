<?php
defined( 'SLPLUS_VERSION' ) || exit;

/**
 * The cron job processing class.
 *
 * @property        SLPPower_Messages $messages           The message stack for the current import operation.
 */
class SLP_Power_Cron extends SLPlus_BaseClass_Object {
	private $messages;

	/**
	 * Connnect the Power add on pointer and messages as needed.
	 */
	private function connect_addon() {
		$this->addon = $this->slplus->addon( 'Power' );
		$this->addon->create_object_schedule_messages();
		$this->messages = $this->addon->messages['schedule'];
	}

	/**
	 * Add our hooks that spin off bot babies.
	 *
	 * @uses \SLP_Power_Cron::import_locations  for Cron hook slp_import_locations
	 */
	public function make_bot_babies() {

		// Geocode Locations Bot...
		add_action( SLP_Power_Locations_Geocode::cron_hook, array( $this, 'geocode_locations' ) );

		// Direct Upload Location Import
		add_action( SLP_Power_Locations_Import::hook, array( $this, 'import_locations' ) );

		// Remote URL Location Import
		add_action( SLP_Power_Locations_Import::csv_import_hook, array( $this, 'import_remote_locations' ), 10, 2 );

	}

	/**
	 * Start the location geocoding.
	 *
	 * @param int $max_id
	 */
	public function geocode_locations( $max_id ) {
		$location_geocoder = new SLP_Power_Locations_Geocode();
		$location_geocoder->geocode( $max_id );
	}

	/**
	 * Start the location import.
	 *
	 * @param int $attachment_id
	 *
	 * @uses    \SLP_Power_Locations_Import::import
	 *
	 * @used-by \SLP_Power_Cron::make_bot_babies
	 *
	 */
	public function import_locations( $attachment_id ) {
		SLP_Power_Locations_Import::get_instance()->import( $attachment_id );
	}

	/**
	 * Import Remote Locations
	 *
	 * @used-by \SLP_Power_Cron::make_bot_babies        manages these cron calls:
	 *
	 * @param string $action 'import_csv'
	 * @param array $params the file_meta
	 */
	public function import_remote_locations( $action, $params ) {
		if ( empty( $action ) ) {
			return;
		}
		$this->connect_addon();
		$this->addon->create_CSVLocationImporter();
		$this->addon->csvImporter->start_remote_file_import( $params );
	}

	/**
	 * Schedule a one-time import.
	 *
	 * @param array $file_meta
	 */
	public function schedule_one_time_job( $file_meta ) {
		$this->connect_addon();

		if ( empty( $this->addon->options['cron_import_timestamp'] ) ) {
			$this->addon->options['cron_import_timestamp'] = 'now';
			$timestamp                                     = time();
		} else {
			$timestamp = strtotime( $this->addon->options['cron_import_timestamp'] );
		}

		// TODO: old-import
		$scheduled_without_problems = wp_schedule_single_event( $timestamp, SLP_Power_Locations_Import::csv_import_hook, array(
			'import_csv',
			$file_meta
		) );

		if ( $scheduled_without_problems !== false ) {
			$this->messages->add_message( sprintf( __( 'Scheduled a one-time import at %s (%s).', 'slp-power' ), $this->addon->options['cron_import_timestamp'], $timestamp ) );
		} else {
			$this->messages->add_message( sprintf( __( 'Could not a one-time import at %s (%s).', 'slp-power' ), $this->addon->options['cron_import_timestamp'], $timestamp ) );
		}
	}

	/**
	 * Schedule a remote locations file retrieval.
	 *
	 * @used-by \SLP_Power_Cron::import_remote_locations
	 */
	public function schedule_remote_file_retrieval() {
		$this->connect_addon();

		// Recurrence = none = do not schedule import
		if ( $this->addon->options['cron_import_recurrence'] === 'none' ) {
			return;
		}

		if ( empty( $this->addon->options['cron_import_timestamp'] ) ) {
			$this->addon->options['cron_import_timestamp'] = 'now';
			$timestamp                                     = time();
		} else {
			$timestamp = strtotime( $this->addon->options['cron_import_timestamp'] );
		}

		$remote_import_settings = array(
			'schedule'  => $this->addon->options['cron_import_recurrence'],
			'timestamp' => $timestamp,
			'url'       => $this->addon->options['csv_file_url'],
		);
		$import_settings        = array(
			'csv_skip_geocoding',
			'load_data',
			'csv_clear_messages_on_import',
			'csv_duplicates_handling',
		);
		foreach ( $import_settings as $import_setting ) {
			$remote_import_settings[ $import_setting ] = $this->addon->options[ $import_setting ];
		}

		// At = one time at specified time.
		if ( $remote_import_settings['schedule'] === 'at' ) {
			$scheduled_without_problems = wp_schedule_single_event( $remote_import_settings['timestamp'], SLP_Power_Locations_Import::csv_import_hook, array(
				'import_csv',
				$remote_import_settings
			) );

			// All others is recurring
		} else {
			$scheduled_without_problems = wp_schedule_event( $remote_import_settings['timestamp'], $remote_import_settings['schedule'], SLP_Power_Locations_Import::csv_import_hook, array(
				'import_csv',
				$remote_import_settings
			) );
		}

		$could_or_could_not = $scheduled_without_problems ? __( 'Scheduled', 'slp-power' ) : __( 'Could not schedule', 'slp-power' );
		$now_or_later       = ( $remote_import_settings['schedule'] !== 'at' ) ? $remote_import_settings['schedule'] : 'immediate';
		$message            = sprintf( __( '%s a recurring %s import of %s at %s.', 'slp-power' ), $could_or_could_not, $now_or_later, $remote_import_settings['url'], date( "Y-m-d\TH:i:s\Z", $remote_import_settings['timestamp'] ) );

		$this->messages->add_message( $message );
		$this->slplus->notifications->add_notice( 'warning', $message );
	}
}
