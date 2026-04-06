<?php
defined( 'SLPLUS_VERSION' ) || exit;

/**
 * Handle the Environment apiGroup endpoints
 *
 * Read [the REST API documentation](./api) for more details.
 *
 * @package StoreLocatorPlus\REST
 */
class SLP_REST_Environment extends SLPlus_BaseClass_Object {
	/**
	 * @return void
	 */
	public function get() {
		/** @var wpdb $wpdb */
		global $wpdb;
		$my_metadata = get_plugin_data( SLPLUS_FILE );

		// -- base plugin info
		$envVars = [];
		array_push( $envVars,
			[
				'label' => __( 'Site URL', 'store-locator-le' ),
				'value' => get_option( 'siteurl' ),
			],
			[
				'label' => $my_metadata['Name'],
				'value' => $my_metadata['Version']
				,
			],
		);

		// --  Add ON Packs
		if ( isset( $this->slplus->AddOns ) ) {
			foreach ( $this->slplus->AddOns->instances as $addon => $instantiated_addon ) {
				if ( strpos( $addon, 'slp.' ) === 0 ) {
					continue;
				}

				if ( isset( $instantiated_addon ) ) {
					if ( ! $instantiated_addon->admin && $instantiated_addon->admin_class_name ) {
						$instantiated_addon->admin = $instantiated_addon->admin_class_name::get_instance();
					}
					$new_versions[ $instantiated_addon->name ] = $instantiated_addon->admin->get_newer_version();
				}

				$newest_version = isset ( $new_versions[ $instantiated_addon->name ] ) ? $new_versions[ $instantiated_addon->name ] : '';

				$version =
					! is_null( $instantiated_addon ) && method_exists( $instantiated_addon, 'get_meta' ) ?
						$instantiated_addon->get_meta( 'Version' ) :
						'active';

				// If update is available, report it.
				//
				if ( $instantiated_addon != null ) {
					if ( ! empty( $newest_version ) && version_compare( $version, $newest_version, '<' ) ) {
						$url       = $instantiated_addon->get_meta( 'PluginURI' );
						$link_text = '<strong>' . sprintf( __( 'Version %s in production ', 'store-locator-le' ), $newest_version ) . '</strong>';
						$version   .= ' ' . sprintf( '<a href="%s">%s</a> ', $url, $link_text );

					}
					if ( ! empty( $version ) ) {
						$envVars[] = [ 'label' => $instantiated_addon->name, 'value' => $version ];
					}
				}
			}
		}

		// -- environment info
		array_push( $envVars,
			[
				'label' => __( 'SLP Network Active', 'store-locator-le' ),
				'value' => is_plugin_active_for_network( $this->slplus->slug ) ? __( 'Yes', 'store-locator-le' ) : __( 'No', 'store-locator-le' ),
			],
			[
				'label' => __( 'WordPress Version', 'store-locator-le' ),
				'value' => $GLOBALS['wp_version'],
			],
			[
				'label' => __( 'WordPress Memory Limit', 'store-locator-le' ),
				'value' => WP_MEMORY_LIMIT,
			],
			[
				'label' => __( 'WordPress Max Memory Limit', 'store-locator-le' ),
				'value' => WP_MAX_MEMORY_LIMIT,
			],
			[
				'label' => __( 'PHP Version', 'store-locator-le' ),
				'value' => phpversion(),
			],
			[
				'label' => __( 'PHP Memory Limit', 'store-locator-le' ),
				'value' => ini_get( 'memory_limit' ),
			],
			[
				'label' => __( 'PHP Post Max Size', 'store-locator-le' ),
				'value' => ini_get( 'post_max_size' ),
			],
			[
				'label' => __( 'PHP Peak RAM', 'store-locator-le' ),
				'value' => sprintf( '%0.2d MB', ( memory_get_peak_usage( true ) / 1024 / 1024 ) ),
			],
			[
				'label' => __( 'MySQL Version', 'store-locator-le' ),
				'value' => $wpdb->db_version(),
			],
		);

		return $envVars;
	}

	/**
	 * Return true if the cache is expired.
	 *
	 * Note: keep this to cache REST data possibly for News/Docs endpoints
	 *
	 * 1 H = 3600s
	 *
	 * @return bool
	 */
	private function is_cache_expired() {
		return ( time() - $this->slplus->options_nojs['broadcast_timestamp'] > ( 24 * 3600 ) );
	}

	/**
	 * Update cache timestamp
	 * Note: keep this to cache REST data possibly for News/Docs endpoints
	 */
	private function update_cache_timestamp() {
		$this->slplus->options_nojs['broadcast_timestamp'] = time();
		$this->slplus->WPOption_Manager->update_wp_option( 'nojs' );
	}
}
