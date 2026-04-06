<?php
defined( 'SLPLUS_VERSION' ) || exit;
require_once( SLPLUS_PLUGINDIR . '/include/base_class.activation.php' );

/**
 * Manage plugin activation.
 *
 * @property    SLPPower $addon
 */
class SLPPower_Activation extends SLP_BaseClass_Activation {
	protected $smartOptions = array(
		'ajax_orderby_catcount',
		'default_icons',
		'hide_empty',
		'label_category',
		'log_import_messages',
		'log_schedule_messages',
		'permalink_flush_needed',
		'reporting_enabled',
		'show_cats_on_search',
		'show_icon_array',
		'show_legend_text',
		'show_option_all',
		'use_contact_fields',
		'use_pages',
		'use_nonces',
		'use_sensor',
	);

	/**
	 * Add the contact fields.
	 */
	public function add_data_extensions() {
		$this->slplus->database->extension->add_field(
			__( 'Identifier', 'slp-power' ),
			'varchar',
			array(
				'addon'        => $this->addon->short_slug,
				'slug'         => 'identifier',
				'display_type' => 'text',
				'help_text'    => __( 'The identifier field is meant to store a unique location record ID from an external data source. ', 'slp-power' ) .
				                  __( 'During a CSV import, this field is used to match up incoming data with existing locations. ', 'slp-power' )
			) );
		$this->slplus->database->extension->add_field( __( 'Contact', 'slp-power' ), 'varchar', array(
			'slug'  => 'contact',
			'addon' => $this->addon->short_slug
		) );
		$this->slplus->database->extension->add_field( __( 'First Name', 'slp-power' ), 'varchar', array(
			'slug'  => 'first_name',
			'addon' => $this->addon->short_slug
		) );
		$this->slplus->database->extension->add_field( __( 'Last Name', 'slp-power' ), 'varchar', array(
			'slug'  => 'last_name',
			'addon' => $this->addon->short_slug
		) );
		$this->slplus->database->extension->add_field( __( 'Title', 'slp-power' ), 'varchar', array(
			'slug'  => 'title',
			'addon' => $this->addon->short_slug
		) );
		$this->slplus->database->extension->add_field( __( 'Department', 'slp-power' ), 'varchar', array(
			'slug'  => 'department',
			'addon' => $this->addon->short_slug
		) );
		$this->slplus->database->extension->add_field( __( 'Training', 'slp-power' ), 'varchar', array(
			'slug'  => 'training',
			'addon' => $this->addon->short_slug
		) );
		$this->slplus->database->extension->add_field( __( 'Facility Type', 'slp-power' ), 'varchar', array(
			'slug'  => 'facility_type',
			'addon' => $this->addon->short_slug
		) );
		$this->slplus->database->extension->add_field( __( 'Office Phone', 'slp-power' ), 'varchar', array(
			'slug'  => 'office_phone',
			'addon' => $this->addon->short_slug
		) );
		$this->slplus->database->extension->add_field( __( 'Mobile Phone', 'slp-power' ), 'varchar', array(
			'slug'  => 'mobile_phone',
			'addon' => $this->addon->short_slug
		) );
		$this->slplus->database->extension->add_field( __( 'Contact Fax', 'slp-power' ), 'varchar', array(
			'slug'  => 'contact_fax',
			'addon' => $this->addon->short_slug
		) );
		$this->slplus->database->extension->add_field( __( 'Contact Email', 'slp-power' ), 'varchar', array(
			'slug'  => 'contact_email',
			'addon' => $this->addon->short_slug
		) );
		$this->slplus->database->extension->add_field( __( 'Office Hours', 'slp-power' ), 'text', array(
			'slug'  => 'office_hours',
			'addon' => $this->addon->short_slug
		) );
		$this->slplus->database->extension->add_field( __( 'Contact Address', 'slp-power' ), 'text', array(
			'slug'  => 'contact_address',
			'addon' => $this->addon->short_slug
		) );
		$this->slplus->database->extension->add_field( __( 'Notes', 'slp-power' ), 'text', array(
			'slug'  => 'notes',
			'addon' => $this->addon->short_slug
		) );
		$this->slplus->database->extension->add_field( __( 'Introduction', 'slp-power' ), 'text', array(
			'slug'  => 'introduction',
			'addon' => $this->addon->short_slug
		) );
		$this->slplus->database->extension->add_field( __( 'Year Established', 'slp-power' ), 'varchar', array(
			'slug'  => 'year_established',
			'addon' => $this->addon->short_slug
		) );
		$this->slplus->database->extension->add_field( __( 'County', 'slp-power' ), 'varchar', array(
			'slug'  => 'county',
			'addon' => $this->addon->short_slug
		) );
		$this->slplus->database->extension->add_field( __( 'District', 'slp-power' ), 'varchar', array(
			'slug'  => 'district',
			'addon' => $this->addon->short_slug
		) );
		$this->slplus->database->extension->add_field( __( 'Region', 'slp-power' ), 'varchar', array(
			'slug'  => 'region',
			'addon' => $this->addon->short_slug
		) );
		$this->slplus->database->extension->add_field( __( 'Territory', 'slp-power' ), 'varchar', array(
			'slug'  => 'territory',
			'addon' => $this->addon->short_slug
		) );
		$this->slplus->database->extension->add_field( __( 'Image', 'slp-power' ), 'varchar', array(
			'slug'  => 'contact_image',
			'addon' => $this->addon->short_slug
		) );
		$this->slplus->database->extension->update_data_table();

	}

	/**
	 * Install or update the main table
	 * @global object $wpdb
	 */
	private function create_MoreInfoTable() {
		$this->dbupdater(
			$this->addon->category_data->get_SQL( 'create_tagalong_helper' ),
			$this->addon->category_data->plugintable['name']
		);
	}

	/**
	 * Update legacy settings.
	 */
	function update() {
		parent::update();

		$this->install_reporting_tables();

		$this->create_MoreInfoTable();

		if ( $this->slplus->SmartOptions->use_contact_fields->is_true ) {
			$this->add_data_extensions();
		}
	}

	/**
	 * Install reporting tables
	 *
	 * Update the plugin version in config.php on every structure change.
	 */
	private function install_reporting_tables() {
		global $wpdb;

		$charset_collate = '';
		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		}
		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE $wpdb->collate";
		}

		// Reporting: Queries
		//
		$table_name = $wpdb->prefix . "slp_rep_query";
		$sql        = "CREATE TABLE $table_name (
                slp_repq_id         bigint(20) unsigned NOT NULL auto_increment,
                slp_repq_time       timestamp NOT NULL default current_timestamp,
                slp_repq_query      varchar(255) NOT NULL,
                slp_repq_tags       varchar(255),
                slp_repq_address    varchar(255),
                slp_repq_radius     varchar(5),
                meta_value          longtext,
                PRIMARY KEY  (slp_repq_id),
                KEY slp_repq_time (slp_repq_time)
                )
                $charset_collate
                ";
		$this->dbupdater( $sql, $table_name );

		// Reporting: Query Results
		//
		$table_name = $wpdb->prefix . "slp_rep_query_results";
		$sql        = "CREATE TABLE $table_name (
                slp_repqr_id    bigint(20) unsigned NOT NULL auto_increment,
                slp_repq_id     bigint(20) unsigned NOT NULL,
                sl_id           mediumint(8) unsigned NOT NULL,
                PRIMARY KEY  (slp_repqr_id),
                KEY slp_repq_id (slp_repq_id)
                )
                $charset_collate
                ";

		// Install or Update the slp_rep_query_results table
		//
		$this->dbupdater( $sql, $table_name );
	}

	/**
	 * Update the data structures on new db versions.
	 *
	 * @param string $sql
	 * @param string $table_name
	 *
	 * @return string
	 * @global object $wpdb
	 */
	private function dbupdater( $sql, $table_name ) {
		global $wpdb;
		$retval = ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) ? 'new' : 'updated';

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
		global $EZSQL_ERROR;
		$EZSQL_ERROR = array();

		return $retval;
	}

	/**
	 * Setup Smart Options.
	 *
	 * @param $slug
	 * @param $value
	 */
	protected function setup_smart_option( $slug, $value ) {
		switch ( $slug ) {
			case 'log_import_messages':
				$this->addon->create_object_import_messages();
				break;
			case 'log_schedule_messages':
				$this->addon->create_object_schedule_messages();
				break;
		}
	}
}
