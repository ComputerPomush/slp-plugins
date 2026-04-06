<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin Report Data Interface for Power
 *
 * @property        string[] $table The SQL data table names.
 * @property        string[] $top_results Top results array.
 * @property        string[] $top_searches Top searches array.
 *
 */
class SLP_Power_Data_Reports extends SLPlus_BaseClass_Object {
	public $result_count_history;
	public $search_count_history;
	public $table = array(
		'query'     => 'slp_rep_query',
		'results'   => 'slp_rep_query_results',
		'locations' => 'store_locator',
	);
	public $top_results;
	public $top_searches;

	/**
	 * Things to do at the start.
	 */
	function initialize() {
		foreach ( $this->table as $name => $value ) {
			$this->table[ $name ] = $this->slplus->db->prefix . $value;
		}
	}

	/**
	 * Delete report data more than 13 months old, max 100 records at a time.
	 */
	public function delete_older_report_data() {
		$timezone = new DateTimeZone( 'UTC' );
		$datetime = new DateTime( 'now', $timezone );
		$datetime->sub( new DateInterval( 'P13M' ) );
		$thirteen_months_ago = $datetime->format( 'Y-m-d H:i:s' );
		$this->delete_history_before( $thirteen_months_ago, '100' );
	}

	/**
	 * Delete all history entries before the specified date.
	 *
	 * @param $date
	 * @param string $limit max records to delete
	 *
	 * @return int|boolean
	 */
	public function delete_history_before( $date, $limit = '10000' ) {
		global $wpdb;
		$query         = sprintf( 'DELETE q,r FROM %s q INNER JOIN %s r ON q.slp_repq_id = r.slp_repq_id ', $this->table['query'], $this->table['results'] ) .
		                 'WHERE slp_repq_time < %s ' .
		                 'LIMIT %d';
		$prepped_query = $wpdb->prepare( $query, array( $date, $limit ) );

		return $wpdb->query( $prepped_query );

	}

	/**
	 * Set the search count with dates for the given date range.
	 *
	 * dataset['count'] = count (*)
	 * dataset['date'] = the date.
	 *
	 * @param string $start_date
	 * @param string $end_date
	 */
	public function set_result_count( $start_date, $end_date ) {
		if ( isset( $this->result_count_history ) ) {
			return;
		}

		$query = sprintf(
			'SELECT sum((select count(*) from %s where slp_repq_id = qry2.slp_repq_id)) as count,' .
			'DATE(slp_repq_time) as date ' .
			'FROM %s qry2 ' .
			"WHERE slp_repq_time > '%s' AND " .
			"      slp_repq_time <= '%s' " .
			"GROUP BY date",

			$this->table['results'],
			$this->table['query'],
			$start_date,
			$end_date
		);

		$this->result_count_history = $this->slplus->db->get_results( $query );
	}

	/**
	 * Set the search count with dates for the given date range.
	 *
	 * dataset['count'] = count (*)
	 * dataset['date'] = the date.
	 *
	 * @param string $start_date
	 * @param string $end_date
	 */
	public function set_search_count( $start_date, $end_date ) {
		if ( isset( $this->search_count_history ) ) {
			return;
		}

		$query = sprintf(

			'SELECT ' .
			'count(*) as count, ' .

			"DATE(slp_repq_time) as date " .

			"FROM %s qry2 " .

			"WHERE slp_repq_time > '%s' AND " .
			"      slp_repq_time <= '%s' " .

			"GROUP BY date",

			$this->table['query'],
			$start_date,
			$end_date
		);

		$this->search_count_history = $this->slplus->db->get_results( $query );
	}

	/**
	 * Set the top_results dataset for the given date range.
	 *
	 * @param $start_date
	 * @param $end_date
	 * @param $limit
	 */
	public function set_top_results( $start_date, $end_date, $limit = 10 ) {

		// SELECT sl_store,sl_city,sl_state, sl_zip, sl_tags, count(*) as ResultCount
		//      FROM wp_slp_rep_query_results res
		//          LEFT JOIN wp_store_locator sl
		//              ON (res.sl_id = sl.sl_id)
		//      WHERE slp_repq_time > '%s' AND slp_repq_time <= '%s'
		//      GROUP BY sl_store,sl_city,sl_state,sl_zip,sl_tags
		//      ORDER BY ResultCount DESC
		//      LIMIT %s
		//
		$query = sprintf(
			"SELECT res.sl_id,sl_store,sl_address,sl_city,sl_state, sl_zip, sl_tags, count(*) as ResultCount " .
			"FROM %s res " .
			"LEFT JOIN %s sl  ON (res.sl_id = sl.sl_id) " .
			"LEFT JOIN %s qry ON (res.slp_repq_id = qry.slp_repq_id) " .

			"WHERE slp_repq_time > '%s' AND slp_repq_time <= '%s' " .

			"GROUP BY sl_store,sl_city,sl_state,sl_zip,sl_tags " .

			"ORDER BY ResultCount DESC " .

			"LIMIT %s"
			,
			$this->table['results'],
			$this->table['locations'],
			$this->table['query'],
			$start_date,
			$end_date,
			$limit
		);

		$this->top_results = $this->slplus->db->get_results( $query );
	}

	/**
	 * Set the top searches dataset for the given date range.
	 *
	 * @param $start_date
	 * @param $end_date
	 * @param $limit
	 * @param $return_type
	 */
	public function set_top_searches( $start_date, $end_date, $limit = 10, $return_type = OBJECT ) {
		// SELECT slp_repq_address,count(*) as QueryCount
		//      FROM wp_slp_rep_query
		//      WHERE slp_repq_time > '%s' AND slp_repq_time <= '%s'
		//      GROUP BY slp_repq_address
		//      ORDER BY QueryCount DESC;
		//
		$query = sprintf(
			'SELECT slp_repq_address, count(*)  as QueryCount FROM %s ' .
			"WHERE slp_repq_time > '%s' AND " .
			"      slp_repq_time <= '%s' " .
			"GROUP BY slp_repq_address " .
			"ORDER BY QueryCount DESC " .
			"LIMIT %s"
			,
			$this->table['query'],
			$start_date,
			$end_date,
			$limit
		);

		$this->top_searches = $this->slplus->db->get_results( $query, $return_type );
	}
}
