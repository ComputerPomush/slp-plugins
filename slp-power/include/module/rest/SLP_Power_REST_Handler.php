<?php
defined( 'SLPLUS_VERSION' ) || exit;

/**
 * Configure the REST handler and connect to WordPress engine.
 *
 * @package Power \ REST
 */
class SLP_Power_REST_Handler extends SLPlus_BaseClass_Object {

	/**
	 * Get us going.
	 */
	public function initialize() {
		$this->setup_endpoints();
	}

	/**
	 * Setup endpoints.
	 *
	 * @route   GET wp-json/store-locator-plus/v2/imports/
	 * Get list of running imports.
	 *
	 * @route   GET wp-json/store-locator-plus/v2/geocoding/
	 * Get list of running geocodes.
	 */
	private function setup_endpoints() {
		register_rest_route(
			SLP_REST_SLUG . '/v2',
			'/imports/',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_imports' ),
				'permission_callback' => '__return_true',
			) );
		register_rest_route(
			SLP_REST_SLUG . '/v2',
			'/geocoding/',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_geocoding' ),
				'permission_callback' => '__return_true',
			) );
		register_rest_route(
			SLP_REST_SLUG,
			'/report/location/search_history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'GetReportLocationSearchHistory' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'limit' => array(
						'validate_callback' => function ( $param, $request, $key ) {
							return intval( $param ) > 0;
						},
						'sanitize_callback' => 'absint'
					),
				),
			) );

		/**
		 * @api {get} /report/location/search_history_count Get historical search counts.
		 * @apiName GetReportLocationSearchHistoryCount
		 * @apiGroup Report
		 *
		 * @apiParam {string} start The start date as a string (YYYY-MM-DD)
		 * @apiParam {string} end The end date as a string (YYYY-MM-DD)
		 *
		 * @apiSuccess (201) {Object[]} records The data set
		 * @apiSuccess (201) {string} records.count The number of searches that day
		 * @apiSuccess (201) {string} records.date The date
		 * @apiSuccess (201) {Object} metadata The metadata
		 * @apiSuccess (201) {string} start The start date that was used
		 * @apiSuccess (201) {string} end The end date that was used
		 *
		 * @apiSuccessExample {json} Success-Response:
		 * {
		 *     "records":
		 *      [
		 *          {
		 *              "count": "4",
		 *              "date": "2022-08-22"
		 *          },
		 *          {
		 *              "count": "14",
		 *              "date": "2022-08-24"
		 *          },
		 *          {
		 *              "count": "2",
		 *              "date": "2022-08-25"
		 *          }
		 *      ],
		 *     "metadata":
		 *      {
		 *          "start": "2022-07-25",
		 *          "end": "2022-08-25 23:59:59"
		 *       }
		 * }
		 */
		register_rest_route(
			SLP_REST_SLUG,
			'/report/location/search_history_count',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'GetReportLocationSearchHistoryCount' ),
				'permission_callback' => '__return_true',
			) );

		/**
		 * @api {get} /report/location/search_result_history Get data for location result history report.
		 * @apiName GetReportLocationSearchResultHistory
		 * @apiGroup Report
		 *
		 * @apiParam {string} start The start date as a string (YYYY-MM-DD)
		 * @apiParam {string} end The end date as a string (YYYY-MM-DD)
		 * @apiParam {number} limit Max records to return from SQL query.
		 */
		register_rest_route(
			SLP_REST_SLUG,
			'/report/location/search_result_history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'GetReportLocationSearchResultHistory' ),
				'permission_callback' => '__return_true',
			) );

		/**
		 * @api {get} /report/location/result_history_count Get historical search counts.
		 * @apiName GetReportLocationResultHistoryCount
		 * @apiGroup Report
		 *
		 * @apiParam {string} start The start date as a string (YYYY-MM-DD)
		 * @apiParam {string} end The end date as a string (YYYY-MM-DD)
		 *
		 * @apiSuccess (201) {Object[]} records The data set
		 * @apiSuccess (201) {string} records.count The number of searches that day
		 * @apiSuccess (201) {string} records.date The date
		 * @apiSuccess (201) {Object} metadata The metadata
		 * @apiSuccess (201) {string} start The start date that was used
		 * @apiSuccess (201) {string} end The end date that was used
		 *
		 * @apiSuccessExample {json} Success-Response:
		 * {
		 * "records": [
		 *      {
		 *          "count": "9",
		 *          "date": "2022-08-22"
		 *      },
		 *      {
		 *          "count": "23",
		 *          "date": "2022-08-24"
		 *      },
		 *      {
		 *          "count": "8",
		 *          "date": "2022-08-25"
		 *      }
		 *    ],
		 * "metadata": {
		 *      "start": "2022-07-25",
		 *      "end": "2022-08-25 23:59:59"
		 *    }
		 * }
		 *
		 */
		register_rest_route(
			SLP_REST_SLUG,
			'/report/location/result_history_count',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'GetReportLocationResultHistoryCount' ),
				'permission_callback' => '__return_true',
			) );

	}

	/**
	 * Return a list of active location imports by attachment ID.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 * @api
	 *
	 * @used-by \SLP_Power_REST_Handler::setup_endpoints    via READABLE REST Route store-locator-plus/location_imports/
	 */
	public function get_geocoding( WP_REST_Request $request ) {
		/* @var SLP_Power_Locations_Geocode $obj */
		$obj      = SLP_Power_Locations_Geocode::get_instance();
		$response = new WP_REST_Response( $obj->get_active_list() );
		$response->set_status( 201 );

		return $response;
	}


	/**
	 * Return a list of active location imports by attachment ID.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 * @api
	 *
	 * @used-by \SLP_Power_REST_Handler::setup_endpoints    via READABLE REST Route store-locator-plus/location_imports/
	 *
	 */
	public function get_imports( WP_REST_Request $request ) {
		$obj      = SLP_Power_Locations_Import::get_instance();
		$response = new WP_REST_Response( $obj->get_active_list() );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Handle the REST requests coming in on /report/location/search_history
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 *
	 * @api {get} /report/location/search_history Get data for location search history report.
	 * @route  {get} /report/location/search_history Get data for location search history report.
	 * @apiName GetReportLocationSearchHistory
	 * @apiGroup Report
	 *
	 * @apiParam {string} start The start date as a string (YYYY-MM-DD)
	 * @apiParam {string} end The end date as a string (YYYY-MM-DD)
	 * @apiParam {number} limit Max records to return from SQL query.
	 */
	public function GetReportLocationSearchHistory( WP_REST_Request $request ) {
		$requestParams = $request->get_params();
		$end           = $requestParams['end'] . ' 23:59:59';
		$obj           = SLP_Power_Data_Reports::get_instance();
		$obj->set_top_searches( $requestParams['start'], $end, $requestParams['limit'], ARRAY_A );
		$response = new WP_REST_Response( array(
			'records'  => $obj->top_searches,
			'metadata' => array(
				'start' => $requestParams['start'],
				'end'   => $end,
				'limit' => $requestParams['limit'],
			),
		) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Handle the REST requests coming in on /report/location/search_history_count
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function GetReportLocationSearchHistoryCount( WP_REST_Request $request ) {
		$requestParams = $request->get_params();
		$end           = $requestParams['end'] . ' 23:59:59';
		$obj           = SLP_Power_Data_Reports::get_instance();
		$obj->set_search_count( $requestParams['start'], $end, ARRAY_A );
		$response = new WP_REST_Response( array(
			'records'  => $obj->search_count_history,
			'metadata' => array(
				'start' => $requestParams['start'],
				'end'   => $end,
			),
		) );
		$response->set_status( 201 );

		return $response;
	}


	/**
	 * Handle the REST requests coming in on /report/location/search_result_history
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function GetReportLocationSearchResultHistory( WP_REST_Request $request ) {
		$requestParams = $request->get_params();
		$end           = $requestParams['end'] . ' 23:59:59';
		$obj           = SLP_Power_Data_Reports::get_instance();
		$obj->set_top_results( $requestParams['start'], $end, $requestParams['limit'], ARRAY_A );
		$response = new WP_REST_Response( array(
			'records'  => $obj->top_results,
			'metadata' => array(
				'start' => $requestParams['start'],
				'end'   => $end,
				'limit' => $requestParams['limit'],
			),
		) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Handle the REST requests coming in on /report/location/result_history_count
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function GetReportLocationResultHistoryCount( WP_REST_Request $request ) {
		$requestParams = $request->get_params();
		$end           = $requestParams['end'] . ' 23:59:59';
		$obj           = SLP_Power_Data_Reports::get_instance();
		$obj->set_result_count( $requestParams['start'], $end, ARRAY_A );
		$response = new WP_REST_Response( array(
			'records'  => $obj->result_count_history,
			'metadata' => array(
				'start' => $requestParams['start'],
				'end'   => $end,
			),
		) );
		$response->set_status( 201 );

		return $response;
	}
}
