<?php

if (!class_exists('SLP_Avalon')){
    class SLP_Avalon{
        private static $instance;

        public static function instance(){
            if ( ! isset( self::$instance ) && ! ( self::$instance instanceof SLP_Avalon ) ) {

                // Main plugin class.
                self::$instance = new SLP_Avalon();
    
                // Include required files.
                self::$instance->includes();
    
                self::$instance->add_actions();
                self::$instance->register_shortcodes();
            }
            return self::$instance;
        }

        // 2026-08-26 Phase 0.6 - DESTINATION CHANGED ONLY.
        // Previously appended to slp_avalon/error.log, which WP Engine serves
        // publicly (confirmed HTTP 200). error_log() goes to WP Engine's PHP
        // error log, outside the web root.
        private static function log($error)
        {
            error_log("SLP Avalon : " . print_r($error, true));
        }

        public static function init(){
            // Required only when admin.
            if ( is_admin() ) {
                self::$instance->init_admin();
            }

            // Required only when not admin.
            if ( ! is_admin() ) {
                self::$instance->init_frontend();
            }
        }

        public static function activate(){

        }

        private function includes(){

        }

        private function add_actions(){
            add_action('init',array(self::$instance,'init'));
            add_action('slp_ajax_find_locations_complete',array(self::$instance,'slp_ajax_find_locations_complete_filter'));
            add_filter('comments_open', array(self::$instance,'filter_store_comment_status'), 10, 2);
            add_filter('slp_csv_locationdata_added', array(self::$instance,'csv_locationdata_added_func'), 10, 2);
            add_action('slp_csv_processing_complete', array(self::$instance,'csv_processing_complete_func'));
            add_filter('slp_geocode_address', array(self::$instance,'geocode_address_filter'), 10, 2);
            add_action('admin_head', array(self::$instance,'admin_head'), 10);
            add_action('wp_ajax_remove_import_cron_job', array(self::$instance,'remove_import_cron_job_ajax_func'));
            add_filter('gform_notification_23', array(self::$instance,'gform_send_emails_to_dealers'), 10, 3 );
            add_action('slp_manage_locations_action', array(self::$instance,'slp_manage_locations_action_func'), 1, 1);
            add_action('slp_csv_processing_complete', array(self::$instance,'remove_old_csv_files_after_import'), 999);
            add_filter('posts_where', array(self::$instance,'attachments_posts_where'), 10, 2);
            add_filter('slp_ajaxsql_queryparams',array(self::$instance,'slp_ajaxsql_queryparams'),999,2);
            // SLP Dealer Guard, Layer 3. Priority 20: after the priority-10
            // callback above, so the gate is unconditionally the last thing to
            // touch the payload and cannot be refilled by a later filter.
            // Registered with add_filter, not add_action: this IS a filter.
            add_filter('slp_ajax_find_locations_complete',array(self::$instance,'territory_gate'),20,1);
            // SLP Dealer Guard, import hygiene. Issue 26 and Issue 27.
            //
            // Priority 20 is forced, not chosen. SLP Power's
            // prepare_for_import() puts add_sl_to_base_fieldnames on this
            // hook at 8 - that callback is what creates the sl_* keys we
            // read - strip_extra_spaces_from_csv_location_data at 10, and
            // create_categories_from_location_data at 30. Run before 8 and
            // there is nothing to read; run after 30 and we are fighting
            // the category manager.
            add_filter('slp_csv_locationdata',array(self::$instance,'avalon_import_coordinate_guard'),20,1);
            //
            // Priority 500 on completion: after csv_processing_complete_func
            // at 10 has finished reconciling the table against the CSV, and
            // before remove_old_csv_files_after_import at 999 clears the
            // working directory out from under us.
            add_action('slp_csv_processing_complete',array(self::$instance,'avalon_flush_import_log'),500);
        }

        private function register_shortcodes(){
            add_shortcode('dealer_name_avalon', array(self::$instance,'dealer_name_avalon_sc_func'));
            add_shortcode('avalon_store_if_set_prop', array(self::$instance,'avalon_store_if_set_prop_sc_func'));
            add_shortcode('avalon_store_contact_dealer_button', array(self::$instance,'avalon_store_contact_dealer_button_sc_func'));
            add_shortcode('avalon_map_location', array(self::$instance,'avalon_map_location_sc_func'));
        }

        private function init_admin(){

        }

        public static function file_version($filename)
        {
            // Use ASLP_DIR (defined in plugin root) instead of plugin_dir_path(__FILE__)
            // which would incorrectly resolve relative to /inc/ subdirectory
            $pathToFile = ASLP_DIR . $filename;

            if (file_exists($pathToFile)) {
                return filemtime($pathToFile);
            } else {
                // Log the failed path to help with future debugging
                error_log('ASLP file_version() - File not found: ' . $pathToFile);
                return '0.0.1';  // Fallback version rather than exposing 'FileNotFound' publicly
            }

            // orginal 
            // $pathToFile = plugin_dir_path(__FILE__) . $filename;
            // if (file_exists($pathToFile)) {
            //     // return the time the file was last modified
            //     return filemtime($pathToFile);
            // } else {
            //     // let them know the file wasn't found
            //     return 'FileNotFound';
            // }
        }

        private function init_frontend(){
            //We only need to deque it on the find a dealer page
            global $slplus;
            // Styles
            // wp_enqueue_style('bootstrap-5', 'https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css', false);
            // wp_enqueue_style('bootstrap-5-grid', 'https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.0.1/css/bootstrap-grid.min.css', false);

            // Scripts (uncomment if needed!)
            // wp_enqueue_script('bootstrap-5', 'https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.min.js', array('jquery'));
            wp_enqueue_script(
                'slp_avalon',
                ASLP_URL . 'assets/js/slp_avalon.js',
                array('jquery'),
                self::file_version('assets/js/slp_avalon.js'),
                true  // Load in footer — best practice
            );
            $google_maps_api_url = $this->splus_get_google_maps_url();
            //Deque old js url
            wp_dequeue_script('google_maps');
            $js_version = "1.0";
            //Enqueue the new js url
            wp_enqueue_script('google_maps', $google_maps_api_url, array('slp_avalon', 'slp_core'), $js_version, !$slplus->javascript_is_forced);
        }

        public function splus_get_google_maps_url(){
            global $slplus;
            if (!is_a($slplus, 'SLPlus')) return;
            // Google JavaScript API server Key
            // $server_key = !empty($slplus->SmartOptions->google_server_key->value) ? '&key=' . $slplus->SmartOptions->google_server_key->value : '';
            $the_key = ! empty ( $slplus->SmartOptions->google_geocode_key->value ) ? $slplus->SmartOptions->google_geocode_key->value : '';
            if ( empty( $the_key ) ) {
                $the_key = ! empty ( $slplus->SmartOptions->google_server_key->value ) ? $slplus->SmartOptions->google_server_key->value : '';
            }
            $server_key = ! empty ( $the_key ) ? '&key=' . $the_key : '';
            // Set the map language
            $language = 'language=' . $slplus->options_nojs['map_language'];
            if (defined('ICL_LANGUAGE_CODE')) {
                $lang_var = ICL_LANGUAGE_CODE;
                if (!empty($lang_var)) {
                    $language = 'language=' . ICL_LANGUAGE_CODE;
                }
            }

            // Base Google API URL
            $google_api_url = 'https://maps.googleapis.com/maps/api/js';

            $libraries = array('geometry', 'places');
            $google_api_url .= "?libraries=" . implode(',', $libraries) . "&";

            // Region
            $country_manager = SLP_Country_Manager::get_instance();
            if (isset($slplus->SmartOptions->default_country) && isset($country_manager->countries[$slplus->SmartOptions->default_country->value])) {
                $country = strtoupper($country_manager->countries[$slplus->SmartOptions->default_country->value]->cctld);
            } else {
                $country = '';
            }
            $region = !empty($country) ? '&region=' . $country : '';
            $callback = "&callback=avalon_init_gmaps";
            return $google_api_url . $language . $region . $server_key . $callback;
        }

        public function slp_ajax_find_locations_complete_filter($results){
            //return $results;
            //If we are searching for state name, make sure the results are in the state
            if (isset($_POST['address'])){
                //Was five str_replace lines duplicated verbatim in
                //slp_ajaxsql_queryparams(). The copies had diverged - only
                //that one trimmed - and both feed is_state(), so the
                //divergence could raise the SQL limit to 50 while this
                //filter, the thing meant to narrow those 50 rows, sat out.
                $address = $this->normalize_search_address($_POST['address']);
                if ($this->is_state($address)){
                    $stateInitial = $this->get_state_initial($address);
                    $new_response = array();
                    //The canonical name for the code we matched. Derived
                    //from the table rather than from $address because
                    //strcasecmp() does not fold accents: a visitor typing
                    //Quebec with its accent and one typing it without must
                    //both match a record stored as QUEBEC.
                    $states     = $this->get_states();
                    $state_full = isset($states[$stateInitial])
                        ? $states[$stateInitial]
                        : '';
                    foreach ($results['response'] as $k=>$loc){
                        //sl_state is stored inconsistently. Live values on
                        //Aura DEV include MI and NH but also NEW HAMPSHIRE,
                        //DELAWARE and ONTARIO. A code-only compare drops a
                        //dealer that IS in the searched state, and the
                        //distance-ranked backfill then re-admits it in the
                        //wrong position or not at all.
                        //
                        //Cast before compare: 25 of 308 records carry a
                        //malformed state and strcasecmp(null, ...) is
                        //deprecated on PHP 8.4. error.log is publicly
                        //reachable, so deprecation spam is not free.
                        $state_name = (string) (isset($loc['state']) ? $loc['state'] : '');
                        if (
                            strcasecmp($state_name, (string) $stateInitial) === 0 ||
                            ($state_full !== '' && strcasecmp($state_name, $state_full) === 0)
                        ){
                            $new_response[] = $loc;
                        }
                    }
                    $results['response'] = $new_response;
                    $results['count'] = count($results['response']);
                    //return $results;
                }
            }
            //if we have results, do nothing
            if ($results['count'] >= 3) return $results;
            $hide_radius = true;
            if ($results['count'] > 0) {
                $hide_radius = false;
            }
            //Let's get source coords
            $origin = array(
                'address' => $results['http_query']['address'],
                'lat' => $results['http_query']['lat'],
                'lng' => $results['http_query']['lng']
            );
            //Let's get the dealers
            $locations = $this->slp_get_locations_new($origin['lat'], $origin['lng']);
            //Now we sort the locations by distance
            usort($locations, function ($a, $b) {
                if ($a['sl_distance'] == null) return 1;
                if ($b['sl_distance'] == null) return -1;
                return ($a['sl_distance'] < $b['sl_distance']) ? -1 : 1;
            });
            foreach ($locations as $row) {
                $location_marker = $this->slp_add_marker($row);
                if ($location_marker) {
                    //Check if the marker is already in results
                    foreach ($results['response'] as $existing_marker) {
                        if ($existing_marker['id'] == $location_marker['id']) {
                            continue 2;
                        }
                    }
                    $results['response'][] = $location_marker;
                }
                if (count($results['response']) == 3) break;
            }
            $results['count'] = count($results['response']);
            $results['outside_radius'] = $hide_radius;
            return $results;
        }

        public function slp_add_marker($row = null)
        {
            global $slplus;
            if (!is_a($slplus, 'SLPlus')) return;
            if ($row == null) {
                return '';
            }

            $slplus->currentLocation->set_PropertiesViaArray($row);

            /** @var  SLP_Location_Utilities $location_utils */
            $location_utils = SLP_Location_Utilities::get_instance();

            $marker = array(
                'name'          => esc_attr($row['sl_store']),
                'address'       => esc_attr($row['sl_address']),
                'address2'      => esc_attr($row['sl_address2']),
                'city'          => esc_attr($row['sl_city']),
                'state'         => esc_attr($row['sl_state']),
                'zip'           => esc_attr($row['sl_zip']),
                'country'       => esc_attr($row['sl_country']),
                'lat'           => $row['sl_latitude'],
                'lng'           => $row['sl_longitude'],
                'description'   => html_entity_decode($row['sl_description']),
                'url'           => esc_url($row['sl_url']),
                'sl_pages_url'  => esc_url($row['sl_pages_url']),
                'email'         => esc_attr($row['sl_email']),
                'email_link'    => $location_utils->create_email_link($row['sl_email']),
                'hours'         => esc_attr($row['sl_hours']),
                'phone'         => esc_attr($row['sl_phone']),
                'fax'           => esc_attr($row['sl_fax']),
                'image'         => esc_attr($row['sl_image']),
                'distance'      => isset($row['sl_distance']) ? $row['sl_distance'] : '',
                'tags'          => esc_attr($row['sl_tags']),
                'option_value'  => esc_js($row['sl_option_value']),
                'attributes'    => maybe_unserialize($row['sl_option_value']),
                'id'            => $row['sl_id'],
                'linked_postid' => $row['sl_linked_postid'],
                'neat_title'    => esc_attr($row['sl_neat_title']),
                'data'          => $row,
                'city_state_zip' => $location_utils->create_city_state_zip(),
                'zip_state_city' => $location_utils->create_zip_state_city(),
            );

            // Need to come after $marker[url] is set above.
            $marker['web_link']  = (empty($marker['url'])) ? '' : sprintf("<a href='%s' target='_blank' class='storelocatorlink'>%s</a><br/>", $marker['url'], $slplus->Text->get_text('label_website'));
            $marker['url_link']  = (empty($marker['url'])) ? '' : sprintf("<a href='%s' target='_blank' class='storelocatorlink'>%s</a><br/>", $marker['url'], $marker['url']);


            // FILTER: slp_results_marker_data
            // Modify the map marker object that is sent back to the UI in the JSONP response.
            //
            $marker = apply_filters('slp_results_marker_data', $marker);

            return $marker;
        }

        //Get locations with disatnce already calculated
        public function slp_get_locations_new($lat_from, $lng_from)
        {
            global $wpdb;
            $table_name = $wpdb->prefix . "store_locator";
            $query = "SELECT *,( 3959 * acos( cos( radians( {$lat_from} ) ) * cos( radians( sl_latitude ) ) * cos( radians( sl_longitude ) - radians( {$lng_from} ) ) + sin( radians( {$lat_from} ) ) * sin( radians( sl_latitude ) ) ) ) AS sl_distance FROM {$table_name}";
            $results = $wpdb->get_results($query, ARRAY_A);
            foreach ($results as &$result) {
                $result = array_merge($result, array(
                    'id' => '',
                    'identifier' => '',
                    'contact' => '',
                    'first_name' => '',
                    'last_name' => '',
                    'title' => '',
                    'department' => '',
                    'training' => '',
                    'facility_type' => '',
                    'office_phone' => '',
                    'mobile_phone' => '',
                    'contact_fax' => '',
                    'contact_email' => '',
                    'office_hours' => '',
                    'contact_address' => '',
                    'notes' => '',
                    'introduction' => '',
                    'year_established' => '',
                    'county' => '',
                    'district' => '',
                    'region' => '',
                    'territory' => '',
                    'contact_image' => '',
                    'featured' => '',
                    'rank' => '',
                    'marker' => ''
                ));
            }
            return $results;
        }


        public function vincentyGreatCircleDistance(
            $latitudeFrom,
            $longitudeFrom,
            $latitudeTo,
            $longitudeTo,
            $earthRadius = 6371000
        ) {
            // convert from degrees to radians
            $latFrom = deg2rad($latitudeFrom);
            $lonFrom = deg2rad($longitudeFrom);
            $latTo = deg2rad($latitudeTo);
            $lonTo = deg2rad($longitudeTo);

            $lonDelta = $lonTo - $lonFrom;
            $a = pow(cos($latTo) * sin($lonDelta), 2) +
                pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
            $b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);

            $angle = atan2(sqrt($a), $b);
            return $angle * $earthRadius;
        }

        /**
         * Import coordinate hygiene. Filter on slp_csv_locationdata, prio 20.
         *
         * Runs once per CSV row, on both the manual upload and the nightly
         * cron - they converge on SLP_Power_Locations_Import::import() at 307,
         * so there is one code path here, not two.
         *
         * Tier 1  coordinates absent, blank, non-numeric, out of range or
         *         exactly 0,0     -> geocode from the address and write.
         * Tier 2  coordinates sane but disagreeing with their own address by
         *         at least the correction threshold -> geocode wins.
         *         Between the observation floor and the threshold, nothing is
         *         written and the disagreement is logged.
         *
         * This runs BEFORE add_to_database() at 864, so it does not matter
         * what skip_geocoding holds - we geocode for ourselves.
         *
         * @param  mixed[] $location_data
         * @return mixed[]
         */
        public function avalon_import_coordinate_guard($location_data){
            $cfg = $this->avalon_import_config();

            $store = $this->avalon_field($location_data, 'sl_store');
            if ($store === '') {
                $store = $this->avalon_field($location_data, 'name');
            }
            $city  = $this->avalon_field($location_data, 'sl_city');
            $state = $this->avalon_field($location_data, 'sl_state');

            // SLP backfills a missing sl_latitude with '' at line 850, one
            // line after this filter, so at priority 20 the key can genuinely
            // be absent. Never index it unguarded.
            $lat_raw = $this->avalon_field($location_data, 'sl_latitude');
            $lng_raw = $this->avalon_field($location_data, 'sl_longitude');

            $address = implode(', ', array_filter(array(
                $this->avalon_field($location_data, 'sl_address'),
                $this->avalon_field($location_data, 'sl_address2'),
                $city,
                $state,
                $this->avalon_field($location_data, 'sl_zip'),
                $this->avalon_field($location_data, 'sl_country')
            )));

            // Nothing to geocode against. Leave the row exactly as it came in.
            if ($address === '') {
                return $location_data;
            }

            $sane = $this->avalon_coord_is_sane($lat_raw, $lng_raw);
            $tier = $sane ? 2 : 1;

            if ($tier === 1 && ! $cfg['tier1']) {
                return $location_data;
            }
            if ($tier === 2 && ! $cfg['tier2']) {
                return $location_data;
            }

            // The correction cap is a circuit breaker, not a quota. Once it
            // trips, Tier 2 is done for this import - a systemic failure that
            // wants to move 200 rows must not be allowed to move the first 25
            // and then stop half way.
            if ($tier === 2 && $this->avalon_state('tier2_aborted')) {
                return $location_data;
            }

            if ($tier === 2 && $this->avalon_tier2_is_excluded($store, $city, $state)) {
                $this->avalon_state_bump('excluded');
                $this->avalon_note_exclusion_hit($store, $city, $state);
                return $location_data;
            }

            $geo = $this->avalon_geocode_cached($address);
            if (! $geo['success']) {
                $this->avalon_import_log(array(
                    'tier'   => $tier,
                    'action' => 'geocode_failed',
                    'store'  => $store,
                    'where'  => $city . ', ' . $state,
                    'reason' => $geo['error']
                ));
                return $location_data;
            }

            // A geocode that lands outside served territory is rejected on the
            // same predicate Layer 3 applies to search results. 0,0 is the
            // Gulf of Guinea and is refused here, which is defect 4.
            if (! $this->is_in_territory($geo['lat'], $geo['lng'])) {
                $this->avalon_import_log(array(
                    'tier'   => $tier,
                    'action' => 'rejected_out_of_territory',
                    'store'  => $store,
                    'where'  => $city . ', ' . $state,
                    'to'     => $geo['lat'] . ',' . $geo['lng']
                ));
                return $location_data;
            }

            if ($tier === 1) {
                $location_data['sl_latitude']  = $geo['lat'];
                $location_data['sl_longitude'] = $geo['lng'];
                $this->avalon_state_bump('tier1_written');
                $this->avalon_import_log(array(
                    'tier'   => 1,
                    'action' => 'geocoded',
                    'store'  => $store,
                    'where'  => $city . ', ' . $state,
                    'from'   => $lat_raw . ',' . $lng_raw,
                    'to'     => $geo['lat'] . ',' . $geo['lng']
                ));
                return $location_data;
            }

            $miles = $this->vincentyGreatCircleDistance(
                (float) $lat_raw, (float) $lng_raw,
                (float) $geo['lat'], (float) $geo['lng']
            ) / 1609.344;

            if ($miles < $cfg['observe_mi']) {
                return $location_data;   // ordinary geocoder disagreement
            }

            if ($miles < $cfg['correct_mi']) {
                $this->avalon_state_bump('observed');
                $this->avalon_import_log(array(
                    'tier'   => 2,
                    'action' => 'observed_not_corrected',
                    'store'  => $store,
                    'where'  => $city . ', ' . $state,
                    'miles'  => round($miles, 2),
                    'from'   => $lat_raw . ',' . $lng_raw,
                    'to'     => $geo['lat'] . ',' . $geo['lng']
                ));
                return $location_data;
            }

            if ($this->avalon_state('tier2_written') >= $cfg['max_corrections']) {
                $this->avalon_state_set('tier2_aborted', true);
                $this->avalon_import_log(array(
                    'tier'   => 2,
                    'action' => 'ABORTED_correction_cap',
                    'store'  => $store,
                    'where'  => $city . ', ' . $state,
                    'reason' => 'cap of ' . $cfg['max_corrections'] . ' reached; '
                                . 'no further Tier 2 writes this import'
                ));
                return $location_data;
            }

            $location_data['sl_latitude']  = $geo['lat'];
            $location_data['sl_longitude'] = $geo['lng'];
            $this->avalon_state_bump('tier2_written');
            $this->avalon_import_log(array(
                'tier'   => 2,
                'action' => 'corrected',
                'store'  => $store,
                'where'  => $city . ', ' . $state,
                'miles'  => round($miles, 2),
                'from'   => $lat_raw . ',' . $lng_raw,
                'to'     => $geo['lat'] . ',' . $geo['lng']
            ));

            return $location_data;
        }

        /**
         * Read a key that may not exist yet, trimmed, as a string.
         *
         * add_sl_to_base_fieldnames copies a CSV value to its sl_ key only
         * when ! empty(), and empty('0') is true in PHP, so both a blank cell
         * and a cell holding a bare 0 leave the sl_ key undefined.
         */
        private function avalon_field($location_data, $key){
            if (! isset($location_data[$key])) {
                return '';
            }
            if (is_array($location_data[$key]) || is_object($location_data[$key])) {
                return '';
            }
            return trim((string) $location_data[$key]);
        }

        /**
         * Are these coordinates usable as they stand?
         *
         * Deliberately NOT the (int) cast the previous revision used.
         * (int)"-9838239.000000000" is -9838239, which is truthy, so the one
         * genuinely broken longitude in the Aura feed passed the old gate
         * untouched. A blank, a non-number, anything outside the physical
         * range, and exactly 0,0 all count as unusable.
         */
        private function avalon_coord_is_sane($lat, $lng){
            if (! is_numeric($lat) || ! is_numeric($lng)) {
                return false;
            }
            $lat = (float) $lat;
            $lng = (float) $lng;
            if (! is_finite($lat) || ! is_finite($lng)) {
                return false;
            }
            if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
                return false;
            }
            if ($lat === 0.0 && $lng === 0.0) {
                return false;
            }
            return true;
        }

        /**
         * Import hygiene configuration.
         *
         * Every value is overridable from wp-config.php, so a single site can
         * be changed without a deploy and without touching the other five.
         * The defaults are the shipped behaviour.
         */
        public function avalon_import_config(){
            return array(
                'tier1'           => defined('AVALON_IMPORT_GEOCODE_TIER1')
                                     ? (bool)  AVALON_IMPORT_GEOCODE_TIER1   : true,
                'tier2'           => defined('AVALON_IMPORT_GEOCODE_TIER2')
                                     ? (bool)  AVALON_IMPORT_GEOCODE_TIER2   : true,
                'correct_mi'      => defined('AVALON_TIER2_CORRECT_MI')
                                     ? (float) AVALON_TIER2_CORRECT_MI       : 10.0,
                'observe_mi'      => defined('AVALON_TIER2_OBSERVE_MI')
                                     ? (float) AVALON_TIER2_OBSERVE_MI       : 2.0,
                'max_corrections' => defined('AVALON_TIER2_MAX_CORRECTIONS')
                                     ? (int)   AVALON_TIER2_MAX_CORRECTIONS  : 25,
                'geocode_budget'  => defined('AVALON_IMPORT_GEOCODE_BUDGET')
                                     ? (int)   AVALON_IMPORT_GEOCODE_BUDGET  : 150,
                'timeout'         => defined('AVALON_IMPORT_GEOCODE_TIMEOUT')
                                     ? (int)   AVALON_IMPORT_GEOCODE_TIMEOUT : 8,
            );
        }

        /**
         * Rows Tier 2 must never move.
         *
         * DONNIE MARCH, Howell MI, carries I-94 Marine's Belleville
         * coordinates - the two rows are 0.52 metres apart and share
         * identifier CDMII9114. Correcting it would publish a private
         * residence as a dealer location. Suppressing it instead would delete
         * the record that same night, because csv_processing_complete_func()
         * removes every location whose hash is absent from
         * avalon_updated_slp_locations, and currentLocation->delete() leaves
         * the store_page post orphaned: the Aura DEV sitemap carries 321
         * entries against 308 records, and I-94 Marine alone holds three
         * permalinks from exactly that delete-and-recreate churn. So it stays.
         *
         * C/O Cole International USA is a customs broker in Pembina ND acting
         * for a dealer in Lac Du Bonnet MB. The stored coordinates are the
         * dealer's, the address is the broker's, and neither is wrong enough
         * to overwrite the other.
         *
         * Matched on store + city + state so a zip or whitespace fix in the
         * feed cannot silently un-exclude a row. avalon_flush_import_log()
         * warns when an entry stops matching anything at all, which is the
         * signal that a dealer was renamed and the decision needs revisiting.
         */
        public function avalon_tier2_exclusions(){
            return array(
                'DONNIE MARCH|HOWELL|MI',
                'C/O COLE INTERNATIONAL USA|PEMBINA|ND',
            );
        }

        private function avalon_exclusion_key($store, $city, $state){
            $norm = function ($v) {
                return preg_replace('/\s+/', ' ', strtoupper(trim((string) $v)));
            };
            return $norm($store) . '|' . $norm($city) . '|' . $norm($state);
        }

        private function avalon_tier2_is_excluded($store, $city, $state){
            return in_array(
                $this->avalon_exclusion_key($store, $city, $state),
                $this->avalon_tier2_exclusions(),
                true
            );
        }

        private function avalon_note_exclusion_hit($store, $city, $state){
            $key = $this->avalon_exclusion_key($store, $city, $state);
            $hit = $this->avalon_state('exclusion_hits');
            if (! is_array($hit)) {
                $hit = array();
            }
            $hit[$key] = true;
            $this->avalon_state_set('exclusion_hits', $hit);
        }

        /**
         * Geocode an address, cached, inside a per-import budget.
         *
         * The cache is a single non-autoloaded option keyed on the md5 of the
         * normalised address, so a steady-state import performs zero network
         * calls and a single delete_option() clears it. The budget bounds how
         * much a cold cache can add to one import: Aura needs 308 geocodes
         * from cold, which at the configured timeout is a worst case far
         * longer than any cron run should take. Rows beyond the budget are
         * simply not corrected tonight; the cache warms over two or three
         * imports and completes itself.
         */
        private function avalon_geocode_cached($address){
            $key   = md5(preg_replace('/\s+/', ' ', strtoupper(trim($address))));
            $cache = $this->avalon_state('geocode_cache');

            if (! is_array($cache)) {
                $cache = get_option('avalon_geocode_cache');
                if (! is_array($cache)) {
                    $cache = array();
                }
                $this->avalon_state_set('geocode_cache', $cache);
            }

            if (isset($cache[$key]['lat'], $cache[$key]['lng'])) {
                return array(
                    'success' => true,
                    'lat'     => $cache[$key]['lat'],
                    'lng'     => $cache[$key]['lng']
                );
            }

            $cfg = $this->avalon_import_config();
            if ($this->avalon_state('geocodes_spent') >= $cfg['geocode_budget']) {
                return array('success' => false, 'error' => 'geocode budget exhausted');
            }

            $this->avalon_state_bump('geocodes_spent');
            $geo = $this->geocode_from_address($address);

            if (! empty($geo['success'])) {
                $cache[$key] = array(
                    'lat' => $geo['lat'],
                    'lng' => $geo['lng'],
                    'ts'  => time()
                );
                $this->avalon_state_set('geocode_cache', $cache);
                $this->avalon_state_set('cache_dirty', true);
            }

            return $geo;
        }

        /**
         * Per-import scratch state. Held on the instance, never in a global.
         */
        private $avalon_import_state = null;

        private function avalon_state($key){
            if (! is_array($this->avalon_import_state)) {
                $this->avalon_import_state = array();
            }
            if (! isset($this->avalon_import_state[$key])) {
                return 0;
            }
            return $this->avalon_import_state[$key];
        }

        private function avalon_state_set($key, $value){
            if (! is_array($this->avalon_import_state)) {
                $this->avalon_import_state = array();
            }
            $this->avalon_import_state[$key] = $value;
        }

        private function avalon_state_bump($key){
            $this->avalon_state_set($key, ((int) $this->avalon_state($key)) + 1);
        }

        /**
         * Record one override.
         *
         * error_log() goes to WP Engine's PHP log, outside the web root -
         * rev12 s10.6 records what happened the last time this plugin wrote a
         * log under get_stylesheet_directory(). The bounded copy in an option
         * is what the acceptance test reads, since the PHP log rotates and is
         * noisy. Written immediately to the log, batched to the option, so a
         * respawned import cannot lose the whole record.
         */
        private function avalon_import_log($record){
            $line = 'SLP Dealer Guard import: ' . wp_json_encode($record);
            error_log($line);

            $buf = $this->avalon_state('log_buffer');
            if (! is_array($buf)) {
                $buf = array();
            }
            $buf[] = $record;
            $this->avalon_state_set('log_buffer', $buf);

            if (count($buf) >= 20) {
                $this->avalon_flush_import_log(false);
            }
        }

        /**
         * Flush the override log and the geocode cache.
         *
         * Hooked to slp_csv_processing_complete at 500 - after the reconcile
         * at 10, before the working directory is cleared at 999. Also called
         * mid-import when the buffer fills.
         *
         * @param bool $final True at end of import: writes the run summary and
         *                    warns about exclusions that matched nothing.
         */
        public function avalon_flush_import_log($final = true){
            $buf = $this->avalon_state('log_buffer');
            if (is_array($buf) && ! empty($buf)) {
                $stored = get_option('avalon_geocode_overrides');
                if (! is_array($stored)) {
                    $stored = array();
                }
                $stored = array_merge($stored, $buf);
                if (count($stored) > 500) {
                    $stored = array_slice($stored, -500);
                }
                update_option('avalon_geocode_overrides', $stored, 'no');
                $this->avalon_state_set('log_buffer', array());
            }

            if ($this->avalon_state('cache_dirty')) {
                $cache = $this->avalon_state('geocode_cache');
                if (is_array($cache)) {
                    update_option('avalon_geocode_cache', $cache, 'no');
                }
                $this->avalon_state_set('cache_dirty', false);
            }

            if (! $final) {
                return;
            }

            $hits    = $this->avalon_state('exclusion_hits');
            $missing = array();
            foreach ($this->avalon_tier2_exclusions() as $entry) {
                if (! is_array($hits) || ! isset($hits[$entry])) {
                    $missing[] = $entry;
                }
            }

            $summary = array(
                'finished_utc'    => gmdate('c'),
                'tier1_written'   => (int) $this->avalon_state('tier1_written'),
                'tier2_written'   => (int) $this->avalon_state('tier2_written'),
                'observed'        => (int) $this->avalon_state('observed'),
                'excluded'        => (int) $this->avalon_state('excluded'),
                'geocodes_spent'  => (int) $this->avalon_state('geocodes_spent'),
                'tier2_aborted'   => (bool) $this->avalon_state('tier2_aborted'),
                'stale_exclusions'=> $missing
            );

            error_log('SLP Dealer Guard import summary: ' . wp_json_encode($summary));
            update_option('avalon_geocode_last_run', $summary, 'no');

            $this->avalon_import_state = null;
        }

        public function geocode_from_address($address)
        {
            global $slplus;
            $result = array(
                'success' => false,
                'error' => 'Unknown'
            );
            $server_key = !empty($slplus->SmartOptions->google_server_key->value) ? $slplus->SmartOptions->google_server_key->value : '';
            if (!$server_key) {
                $result['error'] = 'No Google Maps API Key';
                return $result;
            }
            $address = urlencode($address);
            $api_url = "https://maps.googleapis.com/maps/api/geocode/json?address={$address}&key={$server_key}";
            // wp_remote_get, not bare curl_exec. The previous revision set no
            // CURLOPT_TIMEOUT at all, so a stalled Google connection could
            // hang a cron import indefinitely with nothing in the log.
            $timeout = defined('AVALON_IMPORT_GEOCODE_TIMEOUT')
                ? (int) AVALON_IMPORT_GEOCODE_TIMEOUT
                : 8;
            $response = wp_remote_get($api_url, array(
                'timeout'     => $timeout,
                'redirection' => 2,
                'sslverify'   => true
            ));
            if (is_wp_error($response)) {
                $result['error'] = 'HTTP: ' . $response->get_error_message();
                return $result;
            }
            $output = wp_remote_retrieve_body($response);
            $json = json_decode($output, true);
            if ($json) {
                if ($json['status'] == 'OK') {
                    if (isset($json['results'][0]['geometry']['location']['lat'],
                              $json['results'][0]['geometry']['location']['lng'])) {
                        $lat = $json['results'][0]['geometry']['location']['lat'];
                        $lng = $json['results'][0]['geometry']['location']['lng'];
                        // A geocoder that answers 0,0 has not found anything;
                        // that is the Gulf of Guinea. Writing it back is how a
                        // zero-coordinate row stays a zero-coordinate row.
                        if (! is_numeric($lat) || ! is_numeric($lng)
                            || ((float) $lat === 0.0 && (float) $lng === 0.0)) {
                            $result['error'] = 'Geocoder returned 0,0 or a non-number';
                            return $result;
                        }
                        $result['success'] = true;
                        $result['lat'] = $lat;
                        $result['lng'] = $lng;
                        return $result;
                    } else {
                        $result['error'] = 'No Geomtry in geocoding response';
                    }
                } else {
                    $result['error'] = $json['status'];
                }
            } else {
                $result['error'] = 'Invalid JSON response';
            }
            return $result;
        }

        public function slp_get_all_locations()
        {
            global $wpdb;
            $table_name = $wpdb->prefix . "store_locator";
            $ext_table_name = $wpdb->prefix . "slp_extendo";
            $query = "SELECT sl.sl_id, sl.sl_store, sl.sl_address, sl.sl_address2, sl.sl_city, sl.sl_state, sl.sl_zip, sl.sl_country, sle.identifier FROM {$table_name} sl LEFT JOIN {$ext_table_name} sle ON sl.sl_id = sle.sl_id";
            $results = $wpdb->get_results($query, ARRAY_A);
            // foreach ($results as &$result) {
            //     $result = array_merge($result);
            // }
            return $results;
        }
        public function slp_get_location_by_id($store_id)
        {
            global $wpdb;
            $table_name = $wpdb->prefix . "store_locator";
            $query = "SELECT * FROM {$table_name} WHERE sl_id = {$store_id} LIMIT 1";
            $results = $wpdb->get_results($query, ARRAY_A);
            foreach ($results as &$result) {
                $result = array_merge($result, array(
                    'id' => '',
                    'identifier' => '',
                    'contact' => '',
                    'first_name' => '',
                    'last_name' => '',
                    'title' => '',
                    'department' => '',
                    'training' => '',
                    'facility_type' => '',
                    'office_phone' => '',
                    'mobile_phone' => '',
                    'contact_fax' => '',
                    'contact_email' => '',
                    'office_hours' => '',
                    'contact_address' => '',
                    'notes' => '',
                    'introduction' => '',
                    'year_established' => '',
                    'county' => '',
                    'district' => '',
                    'region' => '',
                    'territory' => '',
                    'contact_image' => '',
                    'featured' => '',
                    'rank' => '',
                    'marker' => ''
                ));
            }
            if (count($results) > 0) {
                return $results[0];
            }
            return null;
        }

        public function dealer_name_avalon_sc_func($atts, $content = "")
        {
            $atts = shortcode_atts(array(), $atts, 'dealer_name_avalon');
            $store_id = isset($_GET['store_id']) ? $_GET['store_id'] : null;
            $store_id = intval($store_id);
            if (!$store_id) {
                return "";
            }
            $store = $this->slp_get_location_by_id($store_id);
            if (!$store) {
                return "";
            }
            $html = "";
            ob_start();
            echo $store['sl_store'];
            $html = ob_get_clean();
            return $html;
        }

        public function addhttp($url)
        {
            if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
                $url = "https://" . $url;
            }
            return $url;
        }

        public function avalon_store_if_set_prop_sc_func($atts, $content = "")
        {
            global $slplus;
            $atts = shortcode_atts(array('prop' => '', 'raw' => false), $atts, 'avalon_store_website_if_set');
            $html = "";
            ob_start();
            if ($atts['prop']) {
                $location = $slplus->currentLocation;
                if ($location) {
                    switch ($atts['prop']) {
                        case 'website':
                        case 'url':
                            $url = $location->url;
                            if ($url) {
                                if ($atts['raw']) {
                                    echo $url;
                                } else {
                                    $url_http = $this->addhttp($location->url);
                                    echo "Visit our Website: <a href='{$url_http}' target='_blank'>{$url}</a>";
                                }
                            }
                            break;
                        case 'email':
                            $email = $location->email;
                            if ($email) {
                                if ($atts['raw']) {
                                    echo $email;
                                } else {
                                    echo "Email: <a href='mailto:{$email}'>{$email}</a>";
                                }
                            }
                            break;
                        default:
                            if (isset($location->{$atts['prop']})) {
                                $prop = $location->{$atts['prop']};
                                echo $prop;
                            }
                            break;
                    }
                }
            }
            $html = ob_get_clean();
            return $html;
        }

        public function avalon_store_contact_dealer_button_sc_func($atts, $content = "")
        {
            global $slplus;
            $atts = shortcode_atts(array(), $atts, 'avalon_store_contact_dealer');
            $location = $slplus->currentLocation;
            $html = "";
            if ($location) {
                $location_id = $location->id;
                $dealer_id = null;
                if (isset($location->exdata['identifier'])) {
                    $dealer_id = $location->exdata['identifier'];
                }
                $dealer_str = "";
                if ($dealer_id) {
                    $dealer_str = "&dealer_id={$dealer_id}";
                }
                $url = get_site_url(null, "contact-dealer?store_id={$location_id}{$dealer_str}");
                ob_start(); ?>
                <div class="store_locator_single_contact_store"><a href="<?php echo $url; ?>" class="store_locator_contact_store_button btn button et_pb_button btn-primary theme-button btn-lg center" style="font-size:18px !important;">Contact Dealer</a></div>
            <?php
                $html = ob_get_clean();
            }
            return  $html;
        }

        public function avalon_map_location_sc_func($atts, $content = "")
        {
            global $slplus;
            $atts = shortcode_atts(array(), $atts, 'avalon_map_location');
            $location = $slplus->currentLocation;
            $html = "";
            if ($location && ($location->latitude && $location->longitude)) {
                ob_start(); ?>
                <div style="clear:both">
                    <script>
                        jQuery(function() {
                            avalon_init_location_map();
                        });

                        /*gestureHandling changes the controls of the map
                        greedy : one-finger control
                        cooperative : two-finger control
                        */
                        
                        function avalon_init_location_map() {
                            const location_coords = get_location_coords();
                            let map_options = {
                                zoom: 12,
                                center: location_coords,
                                gestureHandling: 'cooperative'
                            }
                            if (slplus.options.google_map_style) {
                                jQuery.extend(map_options, {
                                    styles: JSON.parse(slplus.options.google_map_style),
                                });
                            }
                            const map = new google.maps.Map(document.getElementById('avalon_location_map'), map_options);
                            const marker = new google.maps.Marker({
                                position: location_coords,
                                map: map,
                                icon: slplus.options.map_home_icon
                            });
                        }

                        function get_location_coords() {
                            const location_coords = {
                                lat: <?php echo $location->latitude; ?>,
                                lng: <?php echo $location->longitude; ?>
                            }
                            return location_coords;
                        }
                    </script>
                    <style>
                        .avalon_location_map_container {
                            width: 100%;
                            height: 300px;
                            display: block;
                            clear: both;
                        }

                        #avalon_location_map {
                            height: 100%;
                            width: 100%;
                        }
                    </style>
                    <div class="avalon_location_map_container">
                        <div id="avalon_location_map"></div>
                    </div>
                </div>
            <?php
                $html = ob_get_clean();
            }
            return $html;
        }
        /* Disable comments on store pages */
        public function filter_store_comment_status($open, $post_id)
        {
            $post = get_post($post_id);
            if ($post->post_type == 'store_page') {
                return false;
            }
            return $open;
        }

        public function create_location_hash($data)
        {
            if (isset($data['location'])) {
                $location = $data['location'];
                $name = $location['sl_store'];
                $address = $location['sl_address'];
                $address2 = $location['sl_address2'];
                $city = $location['sl_city'];
                $state = $location['sl_state'];
                $zip = $location['sl_zip'];
                $country = $location['sl_country'];
                $dealer_id = $location['identifier'];
            } else {
                $name = $data['name'];
                $address = $data['address'];
                $address2 = $data['address2'];
                $city = $data['city'];
                $state = $data['state'];
                $zip = $data['zip'];
                $country = $data['country'];
                $dealer_id = $data['dealer_id'];
            }
            $string = "{$name}_{$address}_{$address2}_{$city}_{$state}_{$zip}_{$country}_{$dealer_id}";
            $hash = md5($string);
            return $hash;
        }

        /* Remove non-existing locations when importing from csv */
        public function csv_locationdata_added_func($location_data, $result_of_add)
        {
            //If it was added correctly, add the id of the location to an option
            if ($result_of_add == 'added' || $result_of_add == 'updated' || $result_of_add == 'not_updated') {
                $location_hash = $this->create_location_hash(array('location' => $location_data));
                $location_id = $location_data['identifier'];
                $updated_locations = get_option('avalon_updated_slp_locations');
                if (!$updated_locations) {
                    $updated_locations = array();
                }
                $updated_locations[] = $location_hash;
                update_option('avalon_updated_slp_locations', $updated_locations);
            } else {
                //error_log("error updating location : {$result_of_add}");
                //error_log(print_r($location_data, true));
            }
        }

        public function csv_processing_complete_func()
        {
            global $slplus;
            if (!is_a($slplus, 'SLPlus')) {
                update_option('avalon_updated_slp_locations', array());
                return;
            }
            //Remove all the locations that are not in the saved updated locations option
            $updated_locations = get_option('avalon_updated_slp_locations');
            //Get Locations
            $locations = $this->slp_get_all_locations();
            foreach ($locations as $location) {
                //Get Identifier
                $location_hash = $this->create_location_hash(array('location' => $location));
                $identifier = $location['identifier'];
                if (!in_array($location_hash, $updated_locations)) {
                    $slplus->currentLocation->delete($location['sl_id']);
                }
            }
            //Clear the option
            update_option('avalon_updated_slp_locations', array());
        }

        public function has_dupes($array)
        {
            return (count($array) == count(array_unique($array)));
        }

        public function geocode_address_filter($response, $params)
        {
            if (is_numeric($params['address'])) {
                //error_log("is numeric");
                add_filter('slp_google_geocoding_params', function ($extra_params) use ($params) {
                    $extra_params .= "&components=postal_code:{$params['address']}|country:US";
                    return $extra_params;
                }, 10, 1);
            }
            // error_log("geocode params");
            // error_log(print_r($params, true));
            // error_log("geocode response");
            // error_log(print_r($response, true));
            // if (empty($params['region'])) {
            //     $params['region'] = SLP_Country_Manager::get_instance()->get_country_code();
            // }
            // $address = urldecode($params['address']);
            // $new_address = $address;
            // if (is_numeric($new_address)){
            //     $new_address = 
            // }
            // error_log("new address");
            // error_log(print_r($new_address, true));
            // $google           = SLP_Google::get_instance();
            // $google_json      = $google->geocode($params['region'], $new_address);
            // $geocode_response = json_decode($google_json);
            // do_action('slp_received_google_geocode_response', $geocode_response, $params);

            return $response;
        }
        //Set "Update" as default for "Duplicates Handling" on location import page
        public function admin_head()
        {
            $current_screen = get_current_screen();
            if ($current_screen->base === 'store-locator-plus_page_slp_manage_locations') {
                $ajax_url = admin_url('admin-ajax.php');
                $ajax_nonce = wp_create_nonce('avalon_import_ajax_nonce');
            ?>
                <style>
                    a.remove_import_cron {
                        cursor: pointer;
                        text-decoration: underline;
                    }
                </style>
                <script type="text/javascript">
                    jQuery(document).ready(function() {
                        jQuery("select[id='slp-power[csv_duplicates_handling]']").val('update')
                    });
                    //Add button to remove import crons
                    jQuery(document).ready(function() {
                        jQuery('#recurring_imports .v-card').each(function(index, elem) {
                            let remove_cron_btn = jQuery('<div class="col-md-12"><a class="remove_import_cron">Remove Import Cron Job</a></div>');
                            jQuery(this).find('.v-card__text .row').append(remove_cron_btn)
                        })
                        jQuery(document).on('click', '.remove_import_cron', function(e) {
                            if (confirm('Are you sure you want to remove this Cron Job?')) {
                                let parent_elem = jQuery(this).closest('.v-card');
                                let cron_id = jQuery(parent_elem).attr('id');
                                jQuery.ajax({
                                    url: '<?php echo $ajax_url; ?>',
                                    data: {
                                        action: 'remove_import_cron_job',
                                        cron_id: cron_id,
                                        cron_hook: 'cron_csv_import',
                                        nonce: '<?php echo $ajax_nonce; ?>'
                                    },
                                    success: function(result) {
                                        if (result.success) {
                                            alert('Cron Job was removed');
                                            jQuery(parent_elem).remove();
                                        } else {
                                            alert(result.error);
                                        }
                                    }
                                })
                            }

                        })
                    })
                </script>
        <?php
            }
        }


        public function get_cron_by_id_and_hook($cron_id, $cron_hook)
        {
            $crons = _get_cron_array();
            foreach ($crons as $timestamp => $cron_arr) {
                if (array_key_exists($cron_hook, $cron_arr)) {
                    if (array_key_exists($cron_id, $cron_arr[$cron_hook])) {
                        $cron = $cron_arr[$cron_hook][$cron_id];
                        $cron['id'] = $cron_id;
                        $cron['hook'] = $cron_hook;
                        return $cron;
                    }
                }
            }
            return null;
        }
        public function delete_cron_job_by_id_and_hook($cron_id, $cron_hook)
        {
            $return = array(
                'success' => false,
            );
            $cron = $this->get_cron_by_id_and_hook($cron_id, $cron_hook);
            if (!$cron) {
                $return['error'] = 'No Cron Job with this id';
                return $return;
            }
            $cron_args = $cron['args'];
            $cron_hook = $cron['hook'];
            $return['success'] = wp_clear_scheduled_hook($cron_hook, $cron_args);
            if ($return['success'] === 0) {
                $return['success'] = false;
                $return['error'] = 'No cron to delete';
            } elseif ($return['success'] === false) {
                $return['error'] = 'Unknown error trying to remove cron job';
            }
            return $return;
        }

        public function remove_import_cron_job_ajax_func()
        {

            $return = array(
                'success' => false,
            );
            if (!check_ajax_referer('avalon_import_ajax_nonce', 'nonce', false)) {
                $return['error'] = 'Invalid security token sent';
                wp_send_json($return);
            }
            $cron_id = $_REQUEST['cron_id'];
            if (!$cron_id) {
                $return['error'] = 'No cron id';
                wp_send_json($return);
            }
            $cron_hook = $_REQUEST['cron_hook'];
            if (!$cron_hook) {
                $return['error'] = 'No cron hook';
                wp_send_json($return);
            }
            $return = $this->delete_cron_job_by_id_and_hook($cron_id, $cron_hook);
            wp_send_json($return);
        }

        public function gform_send_emails_to_dealers( $notification, $form, $entry ) {

            $location_id = $entry[10]; // store_id

            $location_data = $this->slp_get_location_by_id($location_id);

            if (!$location_data['sl_email']) {
                $location_data['sl_email'] = 'sales@avalonpontoons.com';
            }

            if ($notification['id'] == '60c35652e7587') {
                // this is the notification ID that we use

                if ($location_data['sl_email']) {
                    if ( $notification['to'] ) {
                        $notification['to']  =  $notification['to'] . "," . $location_data['sl_email'];
                    } else {
                        $notification['to']  =  $location_data['sl_email'];
                    }
                }
            }



            return $notification;
        }


        //Fix CSV URL Scheduled Import
        public function slp_manage_locations_action_func($action)
        {
            global $slplus;
            $addon = $slplus->addon('Power');
            if (isset($_REQUEST['slp-power'])) {
                foreach ($_REQUEST['slp-power'] as $opt => $val) {
                    $addon->options[$opt] = $val;
                }
            }
        }

        /*********
        /* Start Remove old csv files after import
        */
        public function remove_old_csv_files_after_import()
        {
            $cron_name = "cron_csv_import";
            //Get Cron file names
            $crons = get_option('cron');
            $files = array();
            foreach ($crons as $c_id => $cron) {
                if (isset($cron[$cron_name])) {
                    foreach ($cron[$cron_name] as $s_id => $schedule) {
                        $args = $schedule['args'];
                        if ($args[0] == 'import_csv') {
                            $files[] = $args[1]['url'];
                            break;
                        }
                    }
                }
            }
            $files = array_unique($files);
            if (empty($files)) return;
            $file_names = array();
            foreach ($files as $file) {
                $name = wp_basename($file);
                $name_parts = pathinfo($name);
                $title = trim(substr($name, 0, - (1 + strlen($name_parts['extension']))));
                $file_names[] = array(
                    'title' => $title,
                    'file_name' => $name,
                );
            }
            //Now get all attachments for those files
            foreach ($file_names as $att) {
                $args = array(
                    'posts_per_page' => -1,
                    'post_type'      => 'attachment',
                    // 'title'           => trim($att['title']),
                    'avalon_attachment_title' => trim($att['title']),
                    'order' => 'DESC',
                    'orderby' => 'date',
                    'post_status' => 'inherit',
                );
                $query = new WP_Query($args);
                $to_keep = null;
                foreach ($query->posts as $post) {
                    //Are we sure it's a correct attachments?
                    //We can check the metadata
                    $meta = wp_get_attachment_metadata($post->ID);
                    if (!isset($meta['data_type']) || $meta['data_type'] != 'location_csv') {
                        continue;
                    }
                    //We should keep the first one
                    if (!$to_keep) {
                        $to_keep = $post->ID;
                        continue;
                    }
                    //We only delete those that are not yet processed
                    if (!isset($meta['processed']) || $meta['processed'] != 1) {
                        continue;
                    }
                    //We don't delete those that are still being processed
                    if (isset($meta['next_process_time']) && !empty($meta['next_process_time'])) {
                        continue;
                    }
                    //Now we can delete
                    wp_delete_attachment($post->ID);
                }
            }
        }

        public function attachments_posts_where($where, $wp_query)
        {
            global $wpdb;
            if ($avalon_attachment_title = $wp_query->get('avalon_attachment_title')) {
                $where .= ' AND ' . $wpdb->posts . '.post_title LIKE \'' . esc_sql($wpdb->esc_like($avalon_attachment_title)) . '%\'';
            }
            return $where;
        }

        /*********
        /* End Remove old csv files after import
        */

        //Show all dealers in a state when the search is exactly for a state name
        public function slp_ajaxsql_queryparams($parameters,$query_slug){
            if (isset($_POST['address'])){
                //Same normalisation as the priority-10 filter, from the
                //same function on purpose. If these two ever disagree
                //about what counts as a state name, the limit and the
                //filter disagree with it.
                $address = $this->normalize_search_address($_POST['address']);
                if ($this->is_state($address)){
                    $parameters[4] = 50;
                }
            }
            return $parameters;
        }
        public function get_states(){
            $state_list = array('AL'=>"Alabama",  
                'AK'=>"Alaska",  
                'AZ'=>"Arizona",  
                'AR'=>"Arkansas",  
                'CA'=>"California",  
                'CO'=>"Colorado",  
                'CT'=>"Connecticut",  
                'DE'=>"Delaware",  
                'DC'=>"District Of Columbia",  
                'FL'=>"Florida",  
                'GA'=>"Georgia",  
                'HI'=>"Hawaii",  
                'ID'=>"Idaho",  
                'IL'=>"Illinois",  
                'IN'=>"Indiana",  
                'IA'=>"Iowa",  
                'KS'=>"Kansas",  
                'KY'=>"Kentucky",  
                'LA'=>"Louisiana",  
                'ME'=>"Maine",  
                'MD'=>"Maryland",  
                'MA'=>"Massachusetts",  
                'MI'=>"Michigan",  
                'MN'=>"Minnesota",  
                'MS'=>"Mississippi",  
                'MO'=>"Missouri",  
                'MT'=>"Montana",
                'NE'=>"Nebraska",
                'NV'=>"Nevada",
                'NH'=>"New Hampshire",
                'NJ'=>"New Jersey",
                'NM'=>"New Mexico",
                'NY'=>"New York",
                'NC'=>"North Carolina",
                'ND'=>"North Dakota",
                'OH'=>"Ohio",  
                'OK'=>"Oklahoma",  
                'OR'=>"Oregon",  
                'PA'=>"Pennsylvania",  
                'RI'=>"Rhode Island",  
                'SC'=>"South Carolina",  
                'SD'=>"South Dakota",
                'TN'=>"Tennessee",  
                'TX'=>"Texas",  
                'UT'=>"Utah",  
                'VT'=>"Vermont",  
                'VA'=>"Virginia",  
                'WA'=>"Washington",  
                'WV'=>"West Virginia",  
                'WI'=>"Wisconsin",  
                'WY'=>"Wyoming",
                /* Canadian provinces and territories, v0.0.12. Issue 10.
                 *
                 * Confirmed necessary by live data, not assumed: the
                 * nearest dealer to Toronto stores its state as ONTARIO,
                 * full name and upper case, and before this build a search
                 * for the province was not recognised at all - so the SQL
                 * limit stayed at 3 and the three nearest dealers to the
                 * provincial centroid, all in Michigan, were the answer.
                 *
                 * None of these two-letter keys collides with the 51 US
                 * entries above. All 13 were checked against that list. */
                'AB'=>"Alberta",
                'BC'=>"British Columbia",
                'MB'=>"Manitoba",
                'NB'=>"New Brunswick",
                'NL'=>"Newfoundland and Labrador",
                'NS'=>"Nova Scotia",
                'NT'=>"Northwest Territories",
                'NU'=>"Nunavut",
                'ON'=>"Ontario",
                'PE'=>"Prince Edward Island",
                'QC'=>"Quebec",
                'SK'=>"Saskatchewan",
                'YT'=>"Yukon"
            );
            return $state_list;
        }

        /**
         * Spellings that are not the canonical name but mean one.
         *
         * Kept separate from get_states() because that array is code =>
         * name and must stay one entry per code - get_state_initial()
         * reverses it, and a second Quebec would make which code wins
         * depend on insertion order.
         *
         * Deliberately does NOT include bare two-letter codes. IN, OR, OK,
         * HI, ME, DE, LA, MA, MS, MT and CO are ordinary English words, and
         * a visitor typing "or" being sent to Oregon is a worse failure
         * than not recognising "OR" as a state.
         *
         * @return array  lower-cased spelling => code
         */
        public function get_state_aliases(){
            return array(
                //Google returns the accented form under a French locale.
                "qu\xc3\xa9bec"          => 'QC',
                'newfoundland'      => 'NL',
                'yukon territory'   => 'YT',
            );
        }

        /**
         * Lower-cased name => code, built once per request.
         *
         * @return array
         */
        public function get_state_lookup(){
            static $lookup = null;
            if ( $lookup === null ) {
                $lookup = array();
                foreach ( $this->get_states() as $code => $name ) {
                    $lookup[ strtolower( $name ) ] = $code;
                }
                foreach ( $this->get_state_aliases() as $spelling => $code ) {
                    $lookup[ strtolower( $spelling ) ] = $code;
                }
            }
            return $lookup;
        }

        /**
         * Strip the country suffix and punctuation from a search string.
         *
         * One function because the five str_replace lines it replaces were
         * duplicated in slp_ajax_find_locations_complete_filter() and
         * slp_ajaxsql_queryparams(), and had already diverged.
         *
         * The suffix is ANCHORED to the end of the string. The old
         * str_replace(" USA",...) matched anywhere, which was harmless for
         * USA but would turn "La Canada Flintridge" into "La Flintridge"
         * once Canada joined it. Case-insensitive because the field renders
         * in caps and Google writes "Ontario, Canada" into it on an
         * autocomplete selection.
         *
         * @param  mixed $raw  $_POST['address']. Always set: slp_core.js
         *                     1809 posts saneValue("addressInput",
         *                     "no address entered").
         * @return string
         */
        public function normalize_search_address( $raw ){
            $address  = (string) $raw;
            $stripped = preg_replace(
                '/\\s*,?\\s*(?:USA|U\\.S\\.A\\.|United States|Canada)\\s*$/i',
                '',
                $address
            );
            //preg_replace returns null only on a PCRE error. Falling back to
            //the unstripped string keeps a pathological input searchable
            //instead of turning it into an empty query.
            if ( $stripped !== null ) {
                $address = $stripped;
            }
            return trim( str_replace( ',', '', $address ) );
        }

        /**
         * @param  string $string
         * @return bool
         */
        public function is_state($string){
            //Delegates rather than repeating the lookup. The previous pair
            //each called ucwords() separately, so the case defect had to be
            //fixed in two places or not at all.
            return $this->get_state_initial( $string ) !== false;
        }

        /**
         * @param  string $state_name
         * @return string|false  the two-letter code, or false
         */
        public function get_state_initial($state_name){
            //Was array_search(ucwords($state_name), ...). ucwords() upper-
            //cases the first letter of each word and leaves the rest, so
            //ucwords("MICHIGAN") is "MICHIGAN" and never matched the table.
            //Measured on Aura DEV: address=Michigan returned 35 results,
            //address=MICHIGAN returned 3.
            $lookup = $this->get_state_lookup();
            $key    = strtolower( trim( (string) $state_name ) );
            return isset( $lookup[ $key ] ) ? $lookup[ $key ] : false;
        }
        /* ==============================================================
         * SLP Dealer Guard - Layer 3: server-side territory gate
         * Phase 1, Step 2.
         *
         * Layers 0-2 are client-side and do the precise
         * address_components.country work. This layer exists only to
         * backstop a direct POST to admin-ajax.php, which bypasses all of
         * them. Bounding boxes rather than a reverse geocode: a geocode
         * call on every search would add cost and latency to the critical
         * path for a check that Layers 0-2 have already made precisely.
         *
         * The boxes are deliberately coarse. They admit northern Mexico,
         * the Bahamas, Bermuda, the BVI and open ocean. That is accepted:
         * the goal is 'do not return US dealers for Paris', not sovereignty.
         *
         * Why a gate is needed at all: with ignore_radius, SLP's own SQL is
         * ORDER BY sl_distance ASC LIMIT n with no radius bound, so it
         * already returns the n nearest dealers on Earth. Verified by a live
         * POST with Paris coordinates returning count 3.
         *
         * Reporting note. SLP Power's log_locations_for_reporting runs at
         * priority 10 and is registered on init:11, while this plugin
         * registers on plugins_loaded, so Power runs after the priority-10
         * Avalon callback and before this gate. An out-of-territory search
         * therefore records BOTH a query row (intended, Decision 7) and one
         * slp_rep_query_results row per pre-gate dealer (a reporting
         * artifact). Moving this gate below 10 would let the priority-10
         * backfill refill the zeroed response and defeat the gate entirely.
         * Enforcement wins; the artifact is documented, not fixed here.
         * ============================================================== */

        /**
         * Territory bounding boxes. Single source of truth for the server
         * side; the JS mirror lives in AVALON_TERRITORY_BOXES in
         * assets/js/slp_avalon.js and must be kept identical.
         *
         * Territory is US + PR + VI + GU + MP + AS + CA.
         *
         * @return array[]
         */
        public function territory_boxes(){
            return array(
                // name                    lat_min  lat_max   lng_min   lng_max
                array( 'CONUS + Canada',      24.4,    83.2,   -141.0,    -52.0 ),
                array( 'Alaska',              51.0,    71.6,   -173.0,   -129.0 ),
                // Adak, Atka and Great Sitkin sit between -180 and -173 and
                // fall outside the Alaska box. Widening that box instead would
                // admit Wrangel Island (RU, 71.2N / -179.5); this one cannot.
                array( 'Western Aleutians',   51.0,    54.0,   -180.0,   -173.0 ),
                array( 'Aleutian wrap',       51.0,    54.0,    172.0,    180.0 ),
                array( 'Hawaii',              18.5,    22.5,   -160.6,   -154.6 ),
                array( 'Puerto Rico + USVI',  17.6,    18.6,    -67.5,    -64.5 ),
                array( 'Guam + CNMI',         13.2,    20.6,    144.5,    146.1 ),
                // Swains Island sits 380 km north of Tutuila at -11.06, which
                // is why this box reaches so far north. The -171.2 western
                // edge clears Cape Tapaga, the eastern tip of Upolu
                // (independent Samoa, WS), by about 18 km.
                array( 'American Samoa',     -14.6,   -11.0,   -171.2,   -168.0 ),
            );
        }

        /**
         * Is a coordinate pair inside the served territory?
         *
         * Bounds are inclusive: the Yukon/Alaska border is exactly -141.0 and
         * the antimeridian is exactly 180.0, so both must pass.
         *
         * Anything non-numeric or physically impossible is out of territory.
         * 0,0 is the Gulf of Guinea and is correctly rejected.
         *
         * @param  mixed $lat
         * @param  mixed $lng
         * @return bool
         */
        public function is_in_territory( $lat, $lng ){
            if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
                return false;
            }
            $lat = (float) $lat;
            $lng = (float) $lng;
            if ( ! is_finite( $lat ) || ! is_finite( $lng ) ) {
                return false;
            }
            if ( $lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0 ) {
                return false;
            }
            foreach ( $this->territory_boxes() as $box ) {
                list( , $lat_min, $lat_max, $lng_min, $lng_max ) = $box;
                if ( $lat >= $lat_min && $lat <= $lat_max
                     && $lng >= $lng_min && $lng <= $lng_max ) {
                    return true;
                }
            }
            return false;
        }

        /**
         * Layer 3. Filter on slp_ajax_find_locations_complete at priority 20.
         *
         * Rejection contract, read by avalon_guard.on_search_processed():
         *   count                      0
         *   response                   empty array
         *   avalon_territory_rejected  true
         *
         * outside_radius is unset because it means 'results exist but sit
         * outside the radius', which is no longer true, and because the JS
         * returns early on it before the marker work.
         *
         * Pass-through cases:
         *   - kill-switch SLP_AVALON_GUARD_DISABLE is defined and truthy
         *   - the payload has no usable lat/lng at all, e.g. a load with no
         *     coordinates, where there is no location to reject and SLP will
         *     fall back to its configured map centre
         *
         * Deliberately NOT scoped to action csl_ajax_search. csl_ajax_onload
         * accepts lat/lng too and would otherwise be an open bypass.
         *
         * @param  array $results
         * @return array
         */
        public function territory_gate( $results ){
            if ( defined( 'SLP_AVALON_GUARD_DISABLE' ) && SLP_AVALON_GUARD_DISABLE ) {
                return $results;
            }
            if ( ! is_array( $results ) || empty( $results['http_query'] ) ) {
                return $results;
            }

            $query = $results['http_query'];
            $lat   = isset( $query['lat'] ) ? $query['lat'] : null;
            $lng   = isset( $query['lng'] ) ? $query['lng'] : null;

            // No coordinates supplied: nothing to gate. is_numeric('') and
            // is_numeric(null) are both false, so this covers empty and absent.
            if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
                return $results;
            }

            if ( $this->is_in_territory( $lat, $lng ) ) {
                return $results;
            }

            $results['count']                     = 0;
            $results['response']                  = array();
            $results['avalon_territory_rejected'] = true;
            unset( $results['outside_radius'] );

            return $results;
        }
    }
}