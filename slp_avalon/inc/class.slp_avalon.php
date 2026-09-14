<?php

if (!class_exists('SLP_Avalon')){
    class SLP_Avalon{
        private static $instance;

        /**
         * v0.0.26 Part 1. Schema version for the dealer-places table.
         *
         * Bumped whenever the CREATE TABLE in avalon_hours_install()
         * changes. avalon_hours_maybe_install() compares this against
         * the stored option and runs dbDelta when they differ, which is
         * what makes an SFTP overwrite of an already-active plugin
         * install a schema change. A version constant that is never
         * bumped is the same defect as no gate at all.
         *
         * HOURS_DB_OPTION is stored AUTOLOADED, deliberately, and it is
         * the only option in this plugin that should be. It is read on
         * every request by the gate, so autoload is what makes the gate
         * cost a string compare instead of a query. Contrast
         * avalon_geocode_cache and avalon_geocode_overrides, both
         * written with an explicit 'no' because they are large and read
         * only during an import.
         */
        const HOURS_DB_VERSION = '1';
        const HOURS_DB_OPTION  = 'avalon_hours_db_version';

        /**
         * v0.0.26 Part 3. The place-resolution queue.
         *
         * PLACES_CRON_HOOK is named once. The schedule gate, the callback
         * registration and the CLI all reach for it, and a hook name
         * spelled three times is a hook name that eventually disagrees
         * with itself.
         *
         * It is named 'resolve' although Part 3a only purges on it.
         * Renaming a cron hook after it has been scheduled strands the
         * old event in the options table with no listener, so the name is
         * chosen for what the callback becomes, not for what it does now.
         *
         * PLACES_ERROR_CEILING is the definitive-negative strike count,
         * consumed in Part 3b. Three NOT_FOUNDs do not become a find on
         * the fourth: the known bad set is data defects - ten dealers at
         * 0,0, one longitude of -9838239, 'ONTARIO' against a US-only
         * get_states() - not flaky lookups. A transport error is NOT a
         * strike, or one bad network afternoon fails half the queue
         * permanently.
         *
         * PLACES_STATUS_PENDING must equal the column default that
         * avalon_hours_install() writes for BOTH place_status and
         * hours_status. It is spelled to match, not chosen, and
         * suite-v027 asserts the two agree rather than trusting this
         * comment.
         */
        const PLACES_CRON_HOOK      = 'avalon_places_resolve';
        const PLACES_ERROR_CEILING  = 3;
        const PLACES_STATUS_PENDING = 'pending';
        const PLACES_STATUS_OK      = 'ok';
        const PLACES_STATUS_FAILED  = 'failed';

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
            //v0.0.26 Part 1. Fresh installs only.
            //
            //register_activation_hook fires when a plugin is activated
            //and at no other time. Every environment this plugin is
            //deployed to already has it active, and the deploy is an
            //SFTP overwrite of the files in place - nothing
            //re-activates. This call alone would create the table on no
            //site currently in play.
            //
            //avalon_hours_maybe_install() on init is what actually
            //installs the table on an existing site. This is here so a
            //genuinely new install has its table before the first init
            //rather than one request later.
            self::avalon_hours_install();
        }

        private function includes(){
            // v0.0.26 Part 2.  The address-key contract, generated by
            // build/build-addresskey.py from resolve-placeids.py's own
            // maps and Python's own Unicode data.  All static: no
            // constructor, no hooks, nothing to initialise, so requiring
            // it IS the whole of the wiring.  The file guards its own
            // declaration with class_exists, so a second require is
            // harmless.
            //
            // Loaded here rather than lazily at the point of use.
            // Measured on PHP 8.3 with no OPcache and no extensions:
            // 0.74 ms to compile and declare, 0.30 ms warm, 0 KB
            // resident delta.  A conditional require inside the cron
            // path would only add a second place for the file to be
            // missing.
            //
            // CALLERS MUST TEST unmapped()['count'], NOT unmapped().
            // It returns array( 'count' => int, 'codepoints' => array ),
            // which is truthy on a clean import.  s0.209.
            require_once ASLP_DIR . 'inc/class.slp_avalon_addresskey.php';
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
            //v0.0.22 Part 1. Priority 5, BEFORE the reconcile at 10. A row
            //repaired now is visible to the reconcile, which can then
            //dispose of a page it previously could not see. The reverse
            //order leaves that blind spot in place.
            add_action('slp_csv_processing_complete', array(self::$instance,'avalon_relink_orphaned_pages'), 5);
            //v0.0.22 Part 2. Priority 1 so the map is consulted before any
            //other redirect handler can claim the request.
            add_action('template_redirect', array(self::$instance,'avalon_orphan_redirect'), 1);
            //v0.0.25. Same priority, different path. The two handlers
            //cannot collide: one matches ^/store/<slug>/?$ and the
            //other ^/contact-dealer/?$.
            add_action('template_redirect', array(self::$instance,'avalon_contact_dealer_redirect'), 1);
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
            add_action('slp_csv_processing_complete',array(self::$instance,'avalon_flush_import_log'),500,0);
            // SLP Dealer Guard, REST disclosure. Issue 33.
            //
            // add_filter, not add_action: rest_post_dispatch passes the
            // response through and expects it back. Registered as an
            // action the callback would still run and its return value
            // would be discarded - the key would go out and nothing
            // would report a fault.
            //
            // Priority 999 for the reason territory_gate is at 20: the
            // strip must be the last thing to touch the payload.
            // accepted_args 3, because the callback reads the route off
            // $request; a registration passing fewer leaves it null and
            // strips nothing, silently. suite-v018 asserts all three.
            add_filter('rest_post_dispatch',array(self::$instance,'avalon_rest_strip_keys'),999,3);
            // SLP Dealer Guard, hours storage. v0.0.26 Part 1.
            //
            // Priority 1 on init, not activation. See activate() for
            // why activation cannot carry this on its own.
            //
            // Priority 1 rather than the default 10 so the table is in
            // place before anything registered later can query it. It
            // does not race SLP's post-type registration at 11 because
            // it does not touch SLP - it reads one option and, on all
            // but the first request after a schema bump, returns.
            //
            // add_action and not add_filter: this returns nothing and
            // nothing consumes a return value.
            add_action('init', array(self::$instance,'avalon_hours_maybe_install'), 1);
            // SLP Dealer Guard, place-resolution queue. v0.0.26 Part 3.
            //
            // Priority 20 on completion is forced, not chosen.
            // csv_processing_complete_func at 10 deletes rows for dealers
            // no longer in the feed, so seeding before it would create a
            // queue entry for a dealer about to be destroyed.
            // avalon_flush_import_log at 500 writes the run record, so
            // seeding must land before that for its counts to appear in
            // it. 20 is the only band that satisfies both.
            add_action('slp_csv_processing_complete', array(self::$instance,'avalon_places_seed'), 20);
            //
            // Priority 1 on init, for the reason avalon_hours_maybe_install
            // is there rather than on activation: activation does not fire
            // on a database-import setup, and the Tahoe and Avalon
            // promotion wave is exactly that case. A schedule that exists
            // only if activation ran is a schedule that silently does not
            // exist on four of six environments.
            add_action('init', array(self::$instance,'avalon_places_maybe_schedule'), 1);
            //
            // The callback, registered unconditionally. A scheduled event
            // whose hook has no listener still consumes its slot and
            // reports nothing.
            add_action(self::PLACES_CRON_HOOK, array(self::$instance,'avalon_places_cron'));
            //
            // WP-CLI, inline and guarded - deliberately NOT a new file.
            // A require_once of a file that has not landed yet is fatal,
            // and Part 2 already paid that deploy-ordering tax once.
            if ( defined('WP_CLI') && WP_CLI ) {
                WP_CLI::add_command( 'avalon places', array(self::$instance,'avalon_places_cli') );
            }
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
            // Pin the Maps JS release channel. With no v= parameter Google serves
            // the weekly channel, which can change the control surface and the
            // internal DOM between deploys - that is how gmp-internal-camera-control
            // appeared in a map nobody had edited. quarterly still receives fixes,
            // on a cadence we can plan around.
            $api_version = "&v=quarterly";
            return $google_api_url . $language . $region . $server_key . $callback . $api_version;
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

            // The correction cap is a circuit breaker, not a quota, and it
            // LATCHES: once tier2_written reaches the cap, tier2_aborted is
            // set and no further Tier 2 row is even compared for the rest of
            // this import. It does NOT roll back. Corrections already made
            // were written into $location_data row by row and are committed.
            // The cap bounds how far a systemic geocode failure can get, not
            // whether the pass is all-or-nothing - it never was.
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
                                     ? (int)   AVALON_TIER2_MAX_CORRECTIONS  : 60,
                'geocode_budget'  => defined('AVALON_IMPORT_GEOCODE_BUDGET')
                                     ? (int)   AVALON_IMPORT_GEOCODE_BUDGET  : 150,
                'timeout'         => defined('AVALON_IMPORT_GEOCODE_TIMEOUT')
                                     ? (int)   AVALON_IMPORT_GEOCODE_TIMEOUT : 8,
            );
        }

        /**
         * Issue 31 reconcile rail.
         *
         * floor_pct  The reconcile pass refuses to run at all when
         *            avalon_updated_slp_locations holds fewer hashes than
         *            this fraction of the location table. An import that
         *            died before recording anything leaves that option
         *            empty, and an unrailed loop would then delete every
         *            location on the site - 308 rows - because every hash
         *            misses. 0.5 means a feed that legitimately halved is
         *            also refused, which is correct: that wants a human.
         *
         *            This rail matters more, not less, now that the
         *            disposal is understood. SLP force-deletes each
         *            removed location's store_page. A runaway pass does
         *            not orphan 308 pages, it destroys them, along with
         *            any Elementor content they carry.
         *
         * v0.0.21 removed cleanup and max_trash. They configured a
         * wp_trash_post() branch that could never execute - SLP disposes
         * of the post one line earlier. See csv_processing_complete_func().
         */
        public function avalon_orphan_config(){
            return array(
                'floor_pct'  => defined('AVALON_RECONCILE_FLOOR_PCT')
                                ? (float) AVALON_RECONCILE_FLOOR_PCT  : 0.5,
                //v0.0.22. A handful of unlinked rows is the defect the
                //relink pass repairs. Hundreds means something systemic
                //happened, and a mass write would compound it rather
                //than fix it. 25 sits above the 12 ever observed and
                //well under a tenth of the table.
                'relink_max' => defined('AVALON_RELINK_MAX')
                                ? (int)   AVALON_RELINK_MAX           : 25,
            );
        }

        /**
         * v0.0.22 Part 1. Repair pages orphaned by an interrupted write.
         *
         * SLPlus_Location::crupdate_Page() inserts the page, then writes
         * 26 slp_location_* postmeta keys one at a time, and only THEN
         * calls MakePersistentIfChanged() to tell the row which page is
         * its own. The write that prevents orphaning is last. Die
         * anywhere in the middle and the page exists while no row claims
         * it.
         *
         * Measured, not inferred: the seven orphans that carry any
         * postmeta hold 6, 9, 10, 20, 21, 22 and 24 keys, and each set is
         * an exact PREFIX of dbFields in declaration order. 26 positions
         * checked, zero mismatches. The other five died before the first
         * add_post_meta and are unrecoverable here - nothing identifies
         * which row they belonged to.
         *
         * The trigger is not known and does not need to be. slp_location_id
         * is written FIRST, so any page that got even one key names its own
         * sl_id, and the missing link is reconstructable from the page.
         */
        public function avalon_relink_orphaned_pages()
        {
            global $wpdb;
            $table = $wpdb->prefix . 'store_locator';
            $cfg   = $this->avalon_orphan_config();

            $unlinked = $wpdb->get_col(
                "SELECT sl_id FROM {$table}
                  WHERE sl_linked_postid IS NULL OR sl_linked_postid = 0"
            );
            if (! is_array($unlinked) || count($unlinked) === 0) {
                return;
            }

            if (count($unlinked) > $cfg['relink_max']) {
                $this->avalon_state_set('relink_aborted', true);
                $this->avalon_import_log(array(
                    'stage'    => 'relink',
                    'action'   => 'relink_cap_exceeded',
                    'unlinked' => count($unlinked),
                    'cap'      => (int) $cfg['relink_max'],
                ));
                return;
            }

            foreach ($unlinked as $sl_id) {
                $sl_id = (int) $sl_id;
                if ($sl_id <= 0) {
                    continue;
                }

                $candidates = $wpdb->get_col($wpdb->prepare(
                    "SELECT p.ID
                       FROM {$wpdb->postmeta} pm
                       JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                      WHERE pm.meta_key   = 'slp_location_id'
                        AND pm.meta_value = %s
                        AND p.post_type   = 'store_page'
                        AND p.post_status = 'publish'",
                    (string) $sl_id
                ));
                if (! is_array($candidates)) {
                    $candidates = array();
                }

                //Never guess. Zero means the page died before its first
                //meta write; more than one means two pages claim the same
                //row and a human decides which.
                if (count($candidates) !== 1) {
                    $this->avalon_import_log(array(
                        'stage'      => 'relink',
                        'action'     => 'relink_skipped',
                        'sl_id'      => $sl_id,
                        'candidates' => count($candidates),
                        'reason'     => (count($candidates) === 0)
                                        ? 'no store_page carries this sl_id'
                                        : 'more than one store_page claims this sl_id',
                    ));
                    continue;
                }

                $post_id = (int) $candidates[0];

                //Never steal a page another row already owns.
                $owner = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT sl_id FROM {$table} WHERE sl_linked_postid = %d LIMIT 1",
                    $post_id
                ));
                if ($owner > 0) {
                    $this->avalon_import_log(array(
                        'stage'   => 'relink',
                        'action'  => 'relink_skipped',
                        'sl_id'   => $sl_id,
                        'post_id' => $post_id,
                        'reason'  => 'page already owned by sl_id ' . $owner,
                    ));
                    continue;
                }

                $slug = get_post_field('post_name', $post_id);
                $done = $wpdb->update(
                    $table,
                    array('sl_linked_postid' => $post_id),
                    array('sl_id' => $sl_id),
                    array('%d'),
                    array('%d')
                );

                if ($done) {
                    $this->avalon_state_bump('pages_relinked');
                    $this->avalon_import_log(array(
                        'stage'   => 'relink',
                        'action'  => 'page_relinked',
                        'sl_id'   => $sl_id,
                        'post_id' => $post_id,
                        'slug'    => is_string($slug) ? $slug : '',
                    ));
                } else {
                    $this->avalon_import_log(array(
                        'stage'   => 'relink',
                        'action'  => 'relink_failed',
                        'sl_id'   => $sl_id,
                        'post_id' => $post_id,
                    ));
                }
            }
        }

        /**
         * v0.0.22 Part 2. Disposition of the twelve orphaned store pages.
         *
         * Adjudicated 2026-09-08 by measurement, not slug similarity. Eight
         * resolved by matching the orphan's own slp_location_address
         * postmeta against the live location table; beltzville,
         * swinging-bridge, jolleys and ocean-marine carry no postmeta and
         * were forced by having exactly one live page in their slug family.
         *
         * ashley-marine-llc-3 is the one product decision here. Its
         * postmeta reads 621 Columbus Pkwy, Opelika AL, which appears zero
         * times across all three feeds - a CLOSED location, not a surplus
         * page for a surviving one. It goes to the Columbus GA store,
         * roughly thirty miles away, so a visitor stays with the same
         * dealer. A 410 would also have been defensible.
         *
         * This table is deliberately code, not an option. It is twelve
         * rows, it is reviewable in a diff, and it dies with the release
         * that stops needing it.
         */
        /**
         * v0.0.25. /contact-dealer -> the dealer's own store page.
         *
         * A page is not the answer here. Gravity Form 14 is already
         * rendered on every store page, and a second render of the same
         * form takes a Gravity Forms instance suffix, which would break
         * the element ids find-a-dealer-focus-trap.js hardcodes. So the
         * request is sent back to the page that already holds the form.
         *
         * A fragment, not a query argument. Store pages are cached;
         * ?contact=1 would either miss the cache or fragment it into
         * variants. A fragment never reaches the server at all.
         *
         * Not keyed on the referrer. It is stripped by privacy settings
         * and absent when a link is pasted or mailed - and it is not
         * needed, because store_id is in the URL and this plugin owns
         * the map from it to the page.
         */
        public function avalon_contact_dealer_redirect()
        {
            if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
                return;
            }
            if (empty($_SERVER['REQUEST_URI'])) {
                return;
            }

            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            if (! is_string($path)) {
                return;
            }
            if (! preg_match('#^/contact-dealer/?$#', $path)) {
                return;
            }

            $store_id = isset($_GET['store_id']) ? (int) $_GET['store_id'] : 0;
            $post_id  = $this->avalon_contact_dealer_resolve($store_id);

            nocache_headers();

            if ($post_id > 0) {
                $permalink = get_permalink($post_id);
                if (is_string($permalink) && $permalink !== '') {
                    //302, not 301. The destination is derived from a query
                    //argument, and a permanently cached redirect would
                    //outlive any correction to the row behind it.
                    wp_safe_redirect($permalink . '#contact-dealer', 302);
                    exit;
                }
            }

            //Unresolvable is not worth an error page. The locator is the
            //honest destination for "which dealer is not known".
            wp_safe_redirect(home_url('/find-a-dealer/'), 302);
            exit;
        }

        /**
         * v0.0.25. store_id -> published store_page ID, or 0.
         *
         * Two independent signals, in order of authority.
         * sl_linked_postid is what SLP itself maintains and what the
         * 308/308 reconcile is measured against. slp_location_id is this
         * plugin's own meta, written when the page is created, and still
         * names the right page when an SLP link has been broken after
         * the fact.
         *
         * dealer_id travels in the URL and is deliberately not used for
         * resolution. It lives in the extended-data table under a shape
         * this method has not measured, and store_id is present in every
         * anchor this plugin has ever emitted.
         */
        private function avalon_contact_dealer_resolve($store_id)
        {
            $store_id = (int) $store_id;
            if ($store_id <= 0) {
                return 0;
            }

            global $wpdb;
            $table = $wpdb->prefix . 'store_locator';

            $id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT p.ID
                   FROM {$table} s
                   JOIN {$wpdb->posts} p ON p.ID = s.sl_linked_postid
                  WHERE s.sl_id       = %d
                    AND p.post_type   = 'store_page'
                    AND p.post_status = 'publish'
                  LIMIT 1",
                $store_id
            ));
            if ($id > 0) {
                return $id;
            }

            $id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT p.ID
                   FROM {$wpdb->postmeta} pm
                   JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                  WHERE pm.meta_key   = 'slp_location_id'
                    AND pm.meta_value = %s
                    AND p.post_type   = 'store_page'
                    AND p.post_status = 'publish'
                  LIMIT 1",
                (string) $store_id
            ));
            return ($id > 0) ? $id : 0;
        }

        public function avalon_orphan_redirect_map()
        {
            return array(
                'beltzville-manor-marine'       => 'beltzville-manor-marine-2',
                'swinging-bridge-marina'        => 'swinging-bridge-marina-2',
                'jolleys-marine-rv-ctr-inc'     => 'jolleys-marine-rv-ctr-inc-2',
                'seven-winds-marina-inc'        => 'seven-winds-marina-inc-2',
                'ashley-marine-llc-3'           => 'ashley-marine-llc',
                'salty-boats'                   => 'salty-boats-2',
                'ocean-marine'                  => 'ocean-marine-2',
                'i-94-marine-watersports-llc'   => 'i-94-marine-watersports-llc-3',
                'victory-marine'                => 'victory-marine-2',
                'i-94-marine-watersports-llc-2' => 'i-94-marine-watersports-llc-3',
                'premier-boating-centers-6'     => 'premier-boating-centers-7',
            );
        }

        /**
         * Departed with no survivor to point at. firefish-industries-ltd
         * has zero rows across all three feeds and zero live pages in its
         * slug family - two independent signals agreeing, which is what
         * this list requires before it will 410 anything.
         */
        public function avalon_orphan_gone_list()
        {
            return array('firefish-industries-ltd');
        }

        /**
         * Is this slug a LIVE store page - one that a location row owns?
         *
         * Both guards below turn on this. A page with no owning row is an
         * orphan and does not count as live, which is exactly the
         * distinction the whole map exists to make.
         */
        private function avalon_slug_is_owned($slug)
        {
            global $wpdb;
            $table = $wpdb->prefix . 'store_locator';
            $id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT p.ID
                   FROM {$wpdb->posts} p
                   JOIN {$table} s ON s.sl_linked_postid = p.ID
                  WHERE p.post_name   = %s
                    AND p.post_type   = 'store_page'
                    AND p.post_status = 'publish'
                  LIMIT 1",
                $slug
            ));
            return ($id > 0);
        }

        /**
         * Serve the disposition. template_redirect, priority 1.
         *
         * Fires whether the request resolved to a post or 404ed, so the
         * same code works before the orphan posts are deleted and after.
         * That is what makes the two-phase rollout possible: prove the map
         * while the posts still exist, then delete them.
         */
        public function avalon_orphan_redirect()
        {
            if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
                return;
            }
            if (empty($_SERVER['REQUEST_URI'])) {
                return;
            }

            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            if (! is_string($path)) {
                return;
            }
            if (! preg_match('#^/store/([a-z0-9\-]+)/?$#', $path, $m)) {
                return;
            }
            $slug = $m[1];

            $map  = $this->avalon_orphan_redirect_map();
            $gone = $this->avalon_orphan_gone_list();
            if (! isset($map[$slug]) && ! in_array($slug, $gone, true)) {
                return;
            }

            //GUARD 1. SELF-DISABLING, and the reason this is safe to leave
            //in place. Deleting an orphan frees its base slug; if SLP later
            //creates a real page there, an unguarded map would hijack it.
            //A live owned page always wins. This also means a slug repaired
            //by avalon_relink_orphaned_pages() stops redirecting by itself.
            if ($this->avalon_slug_is_owned($slug)) {
                return;
            }

            if (in_array($slug, $gone, true)) {
                global $wp_query;
                status_header(410);
                nocache_headers();
                if (isset($wp_query) && is_a($wp_query, 'WP_Query')) {
                    $wp_query->set_404();
                }
                return;
            }

            //GUARD 2. Never redirect into a dead end. If the target has
            //itself been removed, let the 404 happen - an honest 404 beats
            //a 301 into another 404.
            if (! $this->avalon_slug_is_owned($map[$slug])) {
                return;
            }

            wp_safe_redirect(home_url('/store/' . $map[$slug] . '/'), 301);
            exit;
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
         * avalon_updated_slp_locations, and currentLocation->delete()
         * force-deletes the row's store_page with it. Suppressing this row
         * would destroy a live dealer page, not merely orphan it. So it
         * stays.
         *
         * The 321-against-308 sitemap gap is real but unrelated: measured
         * 2026-09-07, all 12 orphaned pages predate the 2026-08-22 rebuild
         * and every page created since is linked. They are not produced by
         * this path.
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
         * The first flush of a run ROTATES the override log: whatever
         * avalon_geocode_overrides held from the previous import is moved to
         * avalon_geocode_overrides_prev and the current option starts empty.
         * Before v0.0.17 it accumulated every import forever - 405 entries and
         * 86,712 bytes on Aura DEV after four runs - and every 20-entry flush
         * read, unserialised and rewrote the whole history. Two options, one
         * import each, bounded permanently. The rotation is keyed on the
         * per-import state flag overrides_rotated, so the three or four flushes
         * inside a single import rotate exactly once between them.
         *
         * @param bool $final True at end of import: writes the run summary and
         *                    warns about exclusions that matched nothing.
         */
        public function avalon_flush_import_log($final = true){
            $buf = $this->avalon_state('log_buffer');
            if (is_array($buf) && ! empty($buf)) {
                if ($this->avalon_state('overrides_rotated')) {
                    $stored = get_option('avalon_geocode_overrides');
                    if (! is_array($stored)) {
                        $stored = array();
                    }
                } else {
                    $prev = get_option('avalon_geocode_overrides');
                    if (is_array($prev) && ! empty($prev)) {
                        update_option('avalon_geocode_overrides_prev', $prev, 'no');
                    }
                    $stored = array();
                    $this->avalon_state_set('overrides_rotated', true);
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
                'rows_removed'      => (int)  $this->avalon_state('rows_removed'),
                'pages_destroyed'   => (int)  $this->avalon_state('pages_destroyed'),
                'pages_relinked'    => (int)  $this->avalon_state('pages_relinked'),
                'relink_aborted'    => (bool) $this->avalon_state('relink_aborted'),
                'reconcile_aborted' => (bool) $this->avalon_state('reconcile_aborted'),
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
                <?php
                // data-dealer-id carries the SLP location id to the click
                // handler in slp_avalon.js, which writes it into Gravity Forms
                // field 14_8. On /find-a-dealer/ main.js reads that id from a
                // slp_results_wrapper_<id> or slp_info_bubble_<id> ancestor. A
                // store page has neither, so the id travels on the anchor.
                //
                // No inline font-size here. It carried !important, which forced
                // any stylesheet trying to match the locator button to escalate
                // to !important as well. Presentation belongs in the theme.
                ?>
                <div class="store_locator_single_contact_store"><a href="<?php echo esc_url($url); ?>" class="store_locator_contact_store_button btn button et_pb_button btn-primary theme-button btn-lg center" data-dealer-id="<?php echo esc_attr($location_id); ?>">Contact Dealer</a></div>
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
                // Presentation values are read here and echoed into the script below.
                // This map deliberately does not touch the slplus JS global: WP Rocket's
                // "Load JavaScript deferred" wraps SLP's inline localisation in a
                // DOMContentLoaded callback, which turns its `const slplus` into a
                // function-local binding no other script can reach. Reading the
                // same values from PHP makes this map immune to that, and to any
                // future optimiser that moves inline scripts around.
                //
                // map_end_icon, not map_home_icon: on a store page the dealer is a
                // destination, not a search origin. Using map_home_icon here would
                // also couple this map to the locator's home marker.
                $map_icon = isset($slplus->options['map_end_icon']) ? trim((string) $slplus->options['map_end_icon']) : '';
                $zoom = isset($slplus->options['zoom_level']) ? (int) $slplus->options['zoom_level'] : 12;
                if ($zoom < 1 || $zoom > 21) {
                    $zoom = 12;
                }
                // Validate the style server-side. A malformed value previously
                // reached JSON.parse() at runtime and took the whole map down;
                // now it degrades to an unstyled map instead.
                $map_style_json = '';
                if (! empty($slplus->options['google_map_style'])) {
                    $decoded = json_decode((string) $slplus->options['google_map_style'], true);
                    if (is_array($decoded)) {
                        $map_style_json = wp_json_encode($decoded);
                    }
                }
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
                                zoom: <?php echo $zoom; ?>,
                                center: location_coords,
                                gestureHandling: 'cooperative',
                                // cameraControl false removes the combined pan-arrow
                                // and zoom cluster (gmp-internal-camera-control),
                                // leaving the plain +/- buttons below.
                                cameraControl: false,
                                zoomControl: true,
                                mapTypeControl: true,
                                streetViewControl: true,
                                fullscreenControl: true
                            }
<?php if ($map_style_json !== '') : ?>
                            map_options.styles = <?php echo $map_style_json; ?>;
<?php endif; ?>
                            const map = new google.maps.Map(document.getElementById('avalon_location_map'), map_options);
                            const marker_options = {
                                position: location_coords,
                                map: map
                            };
<?php if ($map_icon !== '') : ?>
                            marker_options.icon = <?php echo wp_json_encode(esc_url_raw($map_icon)); ?>;
<?php endif; ?>
                            const marker = new google.maps.Marker(marker_options);
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
                $address2 = isset($location['sl_address2']) ? $location['sl_address2'] : '';
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

        /**
         * Reconcile the location table against the feed.
         *
         * Every location whose hash is absent from
         * avalon_updated_slp_locations is removed, and SLP force-deletes
         * that row's store_page along with it.
         *
         * CORRECTION, v0.0.21. Through v0.0.20 this comment claimed the
         * post was left standing, and the orphan population was attributed
         * to this path. Both were wrong, and a release was built on them.
         * Measured 2026-09-07 on Aura DEV: all 12 orphaned pages predate
         * the 2026-08-22 rebuild, all 308 pages created since are linked,
         * and post_modified equals post_date on 11 of the 12 - they were
         * never updated after creation, so they were orphaned at or near
         * creation, not by any disposal. This pass is row-driven and
         * cannot see a page that has no row. It never could.
         *
         * Two passes. Pass 1 identifies and mutates nothing, so the floor
         * rail runs against the complete candidate set. Pass 2 acts, and
         * records each store_page destroyed before it goes.
         */
        public function csv_processing_complete_func()
        {
            global $slplus, $wpdb;
            if (!is_a($slplus, 'SLPlus')) {
                update_option('avalon_updated_slp_locations', array());
                return;
            }

            $cfg = $this->avalon_orphan_config();

            //Remove all the locations that are not in the saved updated locations option
            $updated_locations = get_option('avalon_updated_slp_locations');
            if (! is_array($updated_locations)) {
                $updated_locations = array();
            }
            //Get Locations
            $locations = $this->slp_get_all_locations();
            if (! is_array($locations)) {
                $locations = array();
            }

            //Rail 1. Refuse the whole pass when the feed record is missing
            //or implausible. in_array() against an empty set misses every
            //hash, so without this an import that died early deletes the
            //entire table. Pre-existing risk; the post trashing below is
            //what makes it unacceptable to leave in place.
            $floor = (int) ceil(count($locations) * $cfg['floor_pct']);
            if (count($locations) > 0 && count($updated_locations) < $floor) {
                $this->avalon_state_set('reconcile_aborted', true);
                $this->avalon_import_log(array(
                    'stage'   => 'reconcile',
                    'action'  => 'aborted',
                    'reason'  => 'updated_locations below floor',
                    'updated' => count($updated_locations),
                    'floor'   => $floor,
                    'total'   => count($locations),
                ));
                update_option('avalon_updated_slp_locations', array());
                return;
            }

            //Pass 1 - identify. Nothing is mutated here.
            //
            //slp_get_all_locations() does not select sl_linked_postid and
            //is shared with create_location_hash(), so the post id is read
            //per candidate rather than by widening that SELECT.
            $table = $wpdb->prefix . 'store_locator';
            $stale = array();
            foreach ($locations as $location) {
                $location_hash = $this->create_location_hash(array('location' => $location));
                if (in_array($location_hash, $updated_locations)) {
                    continue;
                }
                $post_id = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT sl_linked_postid FROM {$table} WHERE sl_id = %d",
                        $location['sl_id']
                    )
                );
                $stale[] = array(
                    'sl_id'   => (int) $location['sl_id'],
                    'store'   => isset($location['sl_store']) ? $location['sl_store'] : '',
                    'post_id' => $post_id,
                );
            }

            //Pass 2 - act.
            //
            //SLP disposes of the linked store_page itself.
            //currentLocation->delete() calls delete_store_pages(), which
            //force-deletes the post behind a pre_delete_post filter that
            //vetoes anything that is not a store_page. Read at
            //store-locator-le/include/unit/SLPlus_Location.php:771-846
            //against 2311.17.01 on 2026-09-07, not inferred from a comment.
            //
            //v0.0.20 carried a wp_trash_post() branch here, on the belief
            //that SLP left the post standing. It could not execute: the
            //guard demanded a store_page and SLP had already destroyed
            //exactly that. Three unattended runs logged orphan_skipped and
            //orphans_trashed 0. Removed in v0.0.21.
            //
            //What is recorded instead is what SLP destroyed. The disposal
            //is a force delete - no trash, no undo - and a store_page can
            //carry Elementor content and postmeta that the feed cannot
            //rebuild. Logged BEFORE the delete, because afterwards there
            //is nothing left to name.
            foreach ($stale as $row) {
                if ($row['post_id'] > 0) {
                    $slug = get_post_field('post_name', $row['post_id']);
                    $type = get_post_type($row['post_id']);
                    $this->avalon_import_log(array(
                        'stage'   => 'reconcile',
                        'action'  => ($type === 'store_page')
                                     ? 'page_destroyed_by_slp'
                                     : 'page_retained_not_store_page',
                        'store'   => $row['store'],
                        'sl_id'   => $row['sl_id'],
                        'post_id' => $row['post_id'],
                        'slug'    => is_string($slug) ? $slug : '',
                        'type'    => is_string($type) ? $type : '',
                    ));
                    if ($type === 'store_page') {
                        $this->avalon_state_bump('pages_destroyed');
                    }
                }

                $slplus->currentLocation->delete($row['sl_id']);
                $this->avalon_state_bump('rows_removed');
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

        /**
         * The option slugs that never travel to an unentitled caller.
         *
         * Two, and closed at two: google_server_key and google_geocode_key
         * are the only SmartOptions key reads in this file, at lines 152,
         * 153, 155 and 879. On Aura DEV 2026-09-05 both slugs held the
         * SAME 39-character value - one unrestricted key wearing two
         * names, which is the /options/all face of the key-split item.
         *
         * Public so suite-v018 can read the list rather than restate it.
         *
         * @return array
         */
        /**
         * v0.0.26 Part 1. The dealer-places table name.
         *
         * $wpdb->prefix and not base_prefix: each brand site has its own
         * database and its own cache, which is the arithmetic s0.186
         * ran - roughly 600 to 900 Enterprise calls a month across three
         * sites against a 1,000 allowance, not 303 shared.
         */
        public static function avalon_hours_table(){
            global $wpdb;
            return $wpdb->prefix . 'avalon_dealer_places';
        }

        /**
         * v0.0.26 Part 1. Create or migrate the dealer-places table.
         *
         * dbDelta is not SQL-tolerant and every constraint below is load
         * bearing:
         *
         *   - TWO spaces after PRIMARY KEY. One space and dbDelta does
         *     not recognise the line as a key at all.
         *   - One field per line. It parses by line, not by comma.
         *   - KEY, never INDEX.
         *   - LOWERCASE type names. dbDelta compares this text against
         *     DESCRIBE output, which MySQL returns lowercase. Uppercase
         *     types make it issue the same ALTER TABLE on every run,
         *     forever, and nothing reports it.
         *   - No index prefix lengths. dbDelta mishandles them and can
         *     re-add the same index indefinitely, which is why place_id
         *     is varchar(191) and indexed whole rather than varchar(255)
         *     indexed at (64). Google publishes no maximum place ID
         *     length; 191 is the utf8mb4 index-safe width and every ID
         *     the resolver has produced is far shorter.
         *   - No CURRENT_TIMESTAMP defaults and no zero dates. MySQL 5.7
         *     and 8.0 reject '0000-00-00' under the strict mode they
         *     default to. Every timestamp here is written by PHP, in
         *     GMT, with current_time('mysql', true).
         *
         * The SQL is assembled with implode("\n", ...) rather than
         * written as a literal. This file is CRLF; a literal would carry
         * CRLF into the statement, and while dbDelta does trim \r the
         * dependence would be invisible and one reformat from breaking.
         */
        public static function avalon_hours_install(){
            global $wpdb;

            $table   = self::avalon_hours_table();
            $collate = $wpdb->get_charset_collate();

            $sql = implode("\n", array(
                "CREATE TABLE {$table} (",
                "  address_key char(12) not null,",
                "  sl_id bigint(20) unsigned null default null,",
                "  place_id varchar(191) null default null,",
                "  place_status varchar(16) not null default 'pending',",
                "  place_checked_at datetime null default null,",
                "  hours_json longtext null default null,",
                "  hours_status varchar(16) not null default 'pending',",
                "  fetched_at datetime null default null,",
                "  primary_type_display varchar(190) null default null,",
                "  locality varchar(190) null default null,",
                "  admin_area varchar(190) null default null,",
                "  attribution_json text null default null,",
                "  error_count smallint(5) unsigned not null default 0,",
                "  last_error varchar(190) null default null,",
                "  updated_at datetime null default null,",
                "  PRIMARY KEY  (address_key),",
                "  KEY sl_id (sl_id),",
                "  KEY place_id (place_id),",
                "  KEY hours_sweep (hours_status, fetched_at),",
                "  KEY place_sweep (place_status, place_checked_at)",
                ") {$collate};"
            ));

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $changes = dbDelta( $sql );

            //A no-op dbDelta returns an empty array. Logging only on
            //change keeps the PHP log quiet on the ordinary path and
            //leaves a record of the one request that migrated.
            if ( ! empty( $changes ) ) {
                self::log( 'hours schema ' . self::HOURS_DB_VERSION . ': ' . print_r( $changes, true ) );
            }

            update_option( self::HOURS_DB_OPTION, self::HOURS_DB_VERSION );
        }

        /**
         * v0.0.26 Part 1. The schema gate. s0.189.
         *
         * Runs on init priority 1 on every request. On all but the first
         * after a deploy it reads one autoloaded option, compares two
         * short strings and returns.
         *
         * The WP_INSTALLING guard matters because WordPress sets that
         * constant during its own install and upgrade routines, where
         * the options table may not be in a state worth trusting and
         * DDL from a plugin is unwelcome.
         *
         * No lock. dbDelta is idempotent and two concurrent requests
         * racing it produce the same table; the cost of losing the race
         * is a duplicated no-op, not a corrupted schema.
         */
        public static function avalon_hours_maybe_install(){
            if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
                return;
            }
            if ( get_option( self::HOURS_DB_OPTION ) === self::HOURS_DB_VERSION ) {
                return;
            }
            self::avalon_hours_install();
        }

        /**
         * v0.0.26 Part 1. Hours configuration.
         *
         * Shaped after avalon_import_config(): one defined() override per
         * key with its default beside it, so there is one configuration
         * idiom in this plugin rather than two - s0.192.
         *
         * THE TWO TTLs ARE CLAMPED, NOT DEFAULTED. Google's Places terms
         * allow place_id to be held indefinitely and cap every other
         * field at 30 days. A constant is something somebody can set to
         * 60 in wp-config.php; a clamp is not. Enforcing the cap in code
         * rather than leaving it to cache eviction is the second half of
         * why the durable store is a table row and not a transient -
         * s0.186 - and it is the stronger position to be in if the
         * licence is ever the question.
         *
         * resolve_ceiling, details_ceiling and timeout have NO consumer
         * in Part 1. They are declared here because Part 2 reads its
         * contract from this method, and a contract written twice is a
         * contract that eventually disagrees with itself.
         */
        public function avalon_hours_config(){
            $positive = defined('AVALON_HOURS_POSITIVE_TTL_DAYS')
                        ? (int) AVALON_HOURS_POSITIVE_TTL_DAYS : 30;
            $negative = defined('AVALON_HOURS_NEGATIVE_TTL_DAYS')
                        ? (int) AVALON_HOURS_NEGATIVE_TTL_DAYS : 7;

            return array(
                'enabled'           => defined('AVALON_HOURS_ENABLED')
                                       ? (bool) AVALON_HOURS_ENABLED   : true,
                'positive_ttl_days' => max( 1, min( 30, $positive ) ),
                'negative_ttl_days' => max( 1, min( 30, $negative ) ),
                'resolve_ceiling'   => defined('AVALON_HOURS_RESOLVE_CEILING')
                                       ? (int) AVALON_HOURS_RESOLVE_CEILING : 50,
                'details_ceiling'   => defined('AVALON_HOURS_DETAILS_CEILING')
                                       ? (int) AVALON_HOURS_DETAILS_CEILING : 50,
                'timeout'           => defined('AVALON_HOURS_TIMEOUT')
                                       ? (int) AVALON_HOURS_TIMEOUT    : 8,
            );
        }

        public function avalon_rest_protected_slugs(){
            return array(
                'google_server_key',
                'google_geocode_key',
            );
        }

        /**
         * Strip the Google API keys from Store Locator Plus REST output.
         *
         * Filter on rest_post_dispatch at priority 999. Measured on Aura
         * DEV 2026-09-05: an anonymous GET of
         * /wp-json/store-locator-plus/v1/options/all returns HTTP 201 and
         * 10,539 bytes carrying the Google key twice. The v2 namespace
         * returns the identical bytes.
         *
         * SCOPE, decision 62. The route test is the /store-locator-plus/
         * PREFIX, not the v1 namespace. This install registers three SLP
         * namespaces - v1, v2, and a bare store-locator-plus carrying the
         * report routes. A v1-only test closes one of the two leaking
         * routes and leaves the other open.
         *
         * TWO LIMBS, decision 63, because the secret arrives two ways.
         *
         *   Payload limb - unset any protected key wherever it appears in
         *   the data, at any depth. The measured payload is
         *   store-locator-le -> settings -> options -> a flat map of 120
         *   slugs, every leaf at depth four. Walking by NAME rather than
         *   by that path means a future route serialising SmartOptions
         *   differently is covered without another edit.
         *
         *   Route limb - /options/<slug> and /options/filtered/<slug> name
         *   the option in the ROUTE and return it in a generically named
         *   field, so a name-keyed walk cannot see it. Both return HTTP
         *   500 today, on v1 and v2 alike, which is exactly why the shape
         *   of a working response cannot be measured and the limb cannot
         *   be keyed on field names. Protected slug in, empty body out.
         *
         * WHY THIS HOOK. Read out of wp-includes/rest-api/
         * class-wp-rest-server.php: rest_post_dispatch fires in
         * serve_request() at 463, in embedded-resource resolution at 823,
         * and once per sub-request in the batch endpoint at 1893.
         * dispatch() applies only rest_pre_dispatch, so internal
         * rest_do_request() calls do NOT pass through here and
         * server-side consumers keep the key. The batch site is why this
         * hook beats rest_pre_echo_response: there the filter runs with
         * $single_request, so get_route() is still the SLP route, where
         * rest_pre_echo_response would see /batch/v1 and the namespace
         * test would miss. Batch is opt-in per route - allow_batch['v1'],
         * line 1801 - and SLP has not opted in, so that is coverage held
         * in reserve, not a live hole closed.
         *
         * $server and $request default to null so that a mis-wired
         * registration cannot fatal on every REST response the site
         * serves. It fails CLOSED on its own behaviour and OPEN on the
         * secret, which is the wrong way round for a security control -
         * so the wiring is asserted in suite-v018 against the artefact
         * text, and again after deploy by an anonymous curl. A green
         * suite is not evidence that this filter ran.
         *
         * @param  mixed $result  WP_REST_Response, or a WP_Error.
         * @param  mixed $server  WP_REST_Server. Unused.
         * @param  mixed $request WP_REST_Request.
         * @return mixed
         */
        public function avalon_rest_strip_keys( $result, $server = null, $request = null ){
            if ( ! ( $result instanceof WP_REST_Response ) ) {
                return $result;
            }
            if ( ! ( $request instanceof WP_REST_Request ) ) {
                return $result;
            }

            $route = $request->get_route();
            if ( ! is_string( $route ) || strpos( $route, '/store-locator-plus/' ) !== 0 ) {
                return $result;
            }

            // After the route test, never before it: current_user_can() is
            // not free and this callback sees every REST response.
            if ( current_user_can( 'manage_slp_user' ) ) {
                return $result;
            }

            $slugs = $this->avalon_rest_protected_slugs();

            // Route limb. 'all' and 'import' are slugs too and are not
            // protected, so they fall through to the payload limb.
            $matched = array();
            if ( preg_match( '#/options/(?:filtered/)?([A-Za-z0-9_]+)#', $route, $matched )
                 && in_array( $matched[1], $slugs, true ) ) {
                $result->set_data( array() );
                return $result;
            }

            // Payload limb. set_data() only when something actually moved,
            // so the common case is a walk and no write.
            $removed = 0;
            $data    = $this->avalon_rest_strip_walk( $result->get_data(), $slugs, 0, $removed );
            if ( $removed > 0 ) {
                $result->set_data( $data );
            }

            return $result;
        }

        /**
         * Remove protected keys from a response body, by name, at any depth.
         *
         * Recurses into arrays and stdClass only. Any other object is left
         * exactly as it is - a REST payload can carry objects that are not
         * plain data, and walking into them is how a filter turns a
         * disclosure fix into an outage.
         *
         * Depth is capped at 10. The measured payload bottoms out at 4.
         *
         * @param  mixed $node
         * @param  array $slugs
         * @param  int   $depth
         * @param  int   $removed  By reference. Count of keys unset.
         * @return mixed
         */
        private function avalon_rest_strip_walk( $node, $slugs, $depth, &$removed ){
            if ( $depth > 10 ) {
                return $node;
            }

            if ( is_array( $node ) ) {
                foreach ( $node as $key => $value ) {
                    if ( is_string( $key ) && in_array( $key, $slugs, true ) ) {
                        unset( $node[ $key ] );
                        $removed++;
                        continue;
                    }
                    if ( is_array( $value ) || ( $value instanceof stdClass ) ) {
                        $node[ $key ] = $this->avalon_rest_strip_walk( $value, $slugs, $depth + 1, $removed );
                    }
                }
                return $node;
            }

            if ( $node instanceof stdClass ) {
                foreach ( get_object_vars( $node ) as $key => $value ) {
                    if ( in_array( $key, $slugs, true ) ) {
                        unset( $node->$key );
                        $removed++;
                        continue;
                    }
                    if ( is_array( $value ) || ( $value instanceof stdClass ) ) {
                        $node->$key = $this->avalon_rest_strip_walk( $value, $slugs, $depth + 1, $removed );
                    }
                }
                return $node;
            }

            return $node;
        }
        /**
         * v0.0.26 Part 3. The schedule gate.
         *
         * Runs on init priority 1. wp_next_scheduled() reads the
         * autoloaded cron array and scans it; it is not a query.
         *
         * The first fire is deliberately an hour out rather than
         * immediate, so a deploy cannot run the callback inside the same
         * request that installed it.
         *
         * There is no unschedule branch here, on purpose. Clearing the
         * event belongs to deactivation. A gate that both adds and removes
         * on every request is a gate that fights an administrator who
         * cleared the event deliberately.
         */
        public function avalon_places_maybe_schedule(){
            if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
                return;
            }
            if ( wp_next_scheduled( self::PLACES_CRON_HOOK ) ) {
                return;
            }
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PLACES_CRON_HOOK );
        }

        /**
         * v0.0.26 Part 3. Seed the queue from the locations table.
         *
         * THE ADDRESS PASSED IS sl_address ALONE. sl_address2 is NOT part
         * of the key. vector() takes exactly five raw_* fields and address2
         * is not one of them; the unit is split off the single address line
         * by norm_street() and does not enter the basis, which is
         * country|state|city_key|zip|street_key. Passing address2 here
         * would produce keys that no longer match
         * build/placeid/keyvectors.csv and the Python-to-PHP port would
         * diverge silently.
         *
         * THE 1:N FOLD. 638 feed rows collapse to roughly 303 keys, so the
         * sl_id stored is one of N by construction. MIN() makes which one
         * deterministic: same feed state, same value, every time.
         * Most-recently-imported is not - sl_id is AUTO_INCREMENT and the
         * reconcile is delete-and-re-add, so a dealer that leaves the feed
         * and returns churns the column while nothing about the dealer
         * changed, and slp_get_all_locations() carries no ORDER BY, which
         * makes 'most recent' whatever MySQL happens to return.
         *
         * NOTHING MAY QUERY THIS TABLE BY sl_id. It holds one of N and the
         * other N-1 dealers get silence. Every read path computes the key
         * and hits the primary key. KEY sl_id is a debugging join, not a
         * lookup path.
         *
         * An existing row is updated ONLY when its sl_id actually moved. An
         * unconditional UPDATE on every import would rewrite 303 rows with
         * their own values and leave updated_at meaning 'an import
         * happened' rather than 'this row changed'.
         *
         * place_id and place_status are never named in the UPDATE. That is
         * where never-re-resolve is enforced - in the writer, not only in
         * the reader - so a re-import cannot reset a row that resolved.
         *
         * @return array counts, for the CLI and for the tests.
         */
        public function avalon_places_seed(){
            global $wpdb;

            $out = array(
                'rows'    => 0, 'keys'    => 0, 'inserted' => 0,
                'updated' => 0, 'skipped' => 0, 'unmapped' => 0,
            );

            if ( ! class_exists( 'SLP_Avalon_AddressKey' ) ) {
                self::log( 'places seed aborted: SLP_Avalon_AddressKey not loaded' );
                return $out;
            }

            $table = self::avalon_hours_table();
            $rows  = $this->slp_get_all_locations();
            if ( ! is_array( $rows ) ) {
                $rows = array();
            }
            $out['rows'] = count( $rows );

            //s0.209. Reset first, so the count below is this pass's and not
            //the accumulation of everything since the request began.
            SLP_Avalon_AddressKey::reset_unmapped();

            $fold = array();
            foreach ( $rows as $row ) {
                $vector = SLP_Avalon_AddressKey::vector( array(
                    'raw_address' => isset( $row['sl_address'] ) ? $row['sl_address'] : '',
                    'raw_city'    => isset( $row['sl_city']    ) ? $row['sl_city']    : '',
                    'raw_state'   => isset( $row['sl_state']   ) ? $row['sl_state']   : '',
                    'raw_zip'     => isset( $row['sl_zip']     ) ? $row['sl_zip']     : '',
                    'raw_country' => isset( $row['sl_country'] ) ? $row['sl_country'] : '',
                ) );

                //A row with neither a street nor a city still hashes to a
                //perfectly good twelve characters. Queuing it would spend a
                //Places call on nothing. vector() is called rather than
                //dealer_key() precisely so this is answerable.
                if ( '' === $vector['street_key'] && '' === $vector['city_key'] ) {
                    $out['skipped']++;
                    continue;
                }

                $key   = $vector['dealer_key'];
                $sl_id = isset( $row['sl_id'] ) ? (int) $row['sl_id'] : 0;
                if ( ! isset( $fold[ $key ] ) || $sl_id < $fold[ $key ] ) {
                    $fold[ $key ] = $sl_id;
                }
            }
            $out['keys'] = count( $fold );

            //One read of the whole queue. At roughly 303 keys this is
            //cheaper than 303 existence checks, and it makes the insert and
            //update partitions decidable without leaning on affected_rows,
            //which returns 0 for an update that changed nothing and cannot
            //be told apart from a miss.
            $existing = array();
            $found    = $wpdb->get_results( "SELECT address_key, sl_id FROM {$table}", ARRAY_A );
            if ( is_array( $found ) ) {
                foreach ( $found as $r ) {
                    $existing[ $r['address_key'] ] = (int) $r['sl_id'];
                }
            }

            $now = current_time( 'mysql', true );
            foreach ( $fold as $key => $sl_id ) {
                if ( ! array_key_exists( $key, $existing ) ) {
                    $wpdb->insert(
                        $table,
                        array(
                            'address_key'  => $key,
                            'sl_id'        => $sl_id,
                            'place_status' => self::PLACES_STATUS_PENDING,
                            'updated_at'   => $now,
                        ),
                        array( '%s', '%d', '%s', '%s' )
                    );
                    $out['inserted']++;
                    continue;
                }
                if ( $existing[ $key ] === $sl_id ) {
                    continue;
                }
                $wpdb->update(
                    $table,
                    array( 'sl_id' => $sl_id, 'updated_at' => $now ),
                    array( 'address_key' => $key ),
                    array( '%d', '%s' ),
                    array( '%s' )
                );
                $out['updated']++;
            }

            //s0.209. unmapped() returns array('count'=>int,'codepoints'=>array),
            //which is TRUTHY on a clean import. Read ['count'], never the
            //array, or the alarm fires on every single run.
            $unmapped        = SLP_Avalon_AddressKey::unmapped();
            $out['unmapped'] = (int) $unmapped['count'];

            $this->avalon_import_log( array(
                'stage'    => 'places_seed',
                'action'   => 'seeded',
                'rows'     => $out['rows'],
                'keys'     => $out['keys'],
                'inserted' => $out['inserted'],
                'updated'  => $out['updated'],
                'skipped'  => $out['skipped'],
                'unmapped' => $out['unmapped'],
            ) );

            return $out;
        }

        /**
         * v0.0.26 Part 3. The TTL purge.
         *
         * NOT GATED ON enabled, deliberately. Google's Places terms permit
         * place_id to be held indefinitely and cap every other field at 30
         * days. Setting AVALON_HOURS_ENABLED false to turn the feature off
         * must not leave cached hours sitting past their TTL forever - the
         * expiry is a licence obligation, not a feature. The resolve branch
         * Part 3b adds to avalon_places_cron() is the part that reads
         * enabled.
         *
         * place_id, place_status and place_checked_at are never touched
         * here, for the same reason: they are the one thing the terms let
         * us keep.
         *
         * Two TTLs, two sweeps. A negative result is cheap to refetch and
         * worth retiring sooner; one cutoff for both would hold whichever
         * is longer against each.
         *
         * Driven off KEY hours_sweep (hours_status, fetched_at), which
         * exists for exactly this query.
         *
         * @return int rows cleared.
         */
        public function avalon_places_purge(){
            global $wpdb;

            $cfg    = $this->avalon_hours_config();
            $table  = self::avalon_hours_table();
            $now    = current_time( 'mysql', true );
            $purged = 0;

            $sweeps = array(
                self::PLACES_STATUS_OK     => (int) $cfg['positive_ttl_days'],
                self::PLACES_STATUS_FAILED => (int) $cfg['negative_ttl_days'],
            );

            foreach ( $sweeps as $status => $days ) {
                $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
                $n = $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table} SET hours_json = NULL, attribution_json = NULL,"
                        . " hours_status = %s, fetched_at = NULL, updated_at = %s"
                        . " WHERE hours_status = %s AND fetched_at IS NOT NULL AND fetched_at < %s",
                        self::PLACES_STATUS_PENDING,
                        $now,
                        $status,
                        $cutoff
                    )
                );
                if ( is_numeric( $n ) ) {
                    $purged += (int) $n;
                }
            }

            if ( $purged > 0 ) {
                self::log( 'places purge: ' . $purged . ' row(s) past TTL cleared' );
            }
            return $purged;
        }

        /**
         * v0.0.26 Part 3. The cron callback.
         *
         * Part 3a runs the purge only. Resolution - the queue read, the
         * Places Text Search call, the dead-place_id requeue and the
         * PLACES_ERROR_CEILING strike rule - is Part 3b, because it spends,
         * and a spend path ships behind a dry run with a pre-fire baseline
         * rather than alongside a queue that has never run against a real
         * table.
         */
        public function avalon_places_cron(){
            $this->avalon_places_purge();
        }

        /**
         * v0.0.26 Part 3c. Load resolved place IDs into the queue.
         *
         * The resolver runs on the workstation, against Google, and
         * spends. placeids.json is the record of what was paid for and
         * the one artefact in this project that cannot be re-derived.
         * This method is the only thing that puts it in the database.
         *
         * NEVER-RE-RESOLVE IS IN THE STATEMENT, NOT IN A BRANCH. The
         * UPDATE carries AND place_status = pending in its WHERE. A
         * PHP if() tests a status read a moment earlier; the WHERE
         * tests the status the row holds now, so a row that resolved
         * between the snapshot and the write is not overwritten.
         * avalon_places_seed enforces the same rule by never naming
         * place_id in its UPDATE; this is the same rule, stated the
         * other way round because here the column IS being written.
         *
         * place_checked_at TAKES THE FILE'S resolved_utc, NOT THE
         * IMPORT TIME. The column records when Google was asked. The
         * file knows that and the import does not. It also keeps KEY
         * place_sweep (place_status, place_checked_at) honest: import
         * time would leave 300 rows claiming to be as fresh as the day
         * they were loaded, and any later re-check sweep would believe
         * it. An unparsable stamp falls back to import time, imports
         * anyway and is counted as undated - the place ID is what was
         * paid for, and a silent substitution is worse than a reported
         * one.
         *
         * A DRY RUN WRITES NOTHING. No row, and no import-log record
         * either. The log is a write.
         *
         * A PLACE ID HELD BY TWO KEYS IS NOT AN ERROR. address_key is
         * the primary key and KEY place_id is deliberately not unique:
         * two dealer accounts at one marina are two rows at one place.
         * It is counted, and the groups are returned, because 300
         * imported against 288 distinct reads as a defect to anyone
         * who has not been told otherwise. Those groups are also the
         * duplicate-dealer evidence the Option 4 audit wants, produced
         * without a single call.
         *
         * @param string $path       file to read.
         * @param bool   $apply      false reports and writes nothing.
         * @param string $expect_md5 pin on the file. The CLI requires
         *                           one to write; checked here if given.
         * @return array counts, for the CLI and for the tests.
         */
        public function avalon_places_import( $path, $apply = false, $expect_md5 = '' ){
            global $wpdb;

            $out = array(
                'file'         => is_string( $path ) ? $path : '',
                'md5'          => '',
                'bytes'        => 0,
                'keys_in_file' => 0,
                'rejected'     => 0,
                'matched'      => 0,
                'not_in_queue' => 0,
                'imported'     => 0,
                'already_ok'   => 0,
                'other_status' => 0,
                'raced'        => 0,
                'undated'      => 0,
                'distinct'     => 0,
                'shared'       => 0,
                'groups'       => array(),
                'no_place_id'  => 0,
                'error'        => '',
            );

            if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
                $out['error'] = 'cannot read ' . $out['file'];
                return $out;
            }

            $raw = file_get_contents( $path );
            if ( false === $raw ) {
                $out['error'] = 'read failed: ' . $out['file'];
                return $out;
            }
            $out['bytes'] = strlen( $raw );
            $out['md5']   = md5( $raw );

            //Hash and length reported together, in one pass. s0.212.
            //The pin is optional here and mandatory at the CLI when
            //writing, because this method is also what the tests call.
            if ( '' !== $expect_md5 && $out['md5'] !== strtolower( trim( $expect_md5 ) ) ) {
                $out['error'] = 'md5 ' . $out['md5'] . ', expected '
                                . strtolower( trim( $expect_md5 ) );
                return $out;
            }

            $doc = json_decode( $raw, true );
            if ( ! is_array( $doc ) || ! isset( $doc['dealers'] )
                 || ! is_array( $doc['dealers'] ) ) {
                $out['error'] = 'no dealers object in ' . $out['file'];
                return $out;
            }
            $dealers             = $doc['dealers'];
            $out['keys_in_file'] = count( $dealers );

            $table = self::avalon_hours_table();

            //One read of the whole queue, as avalon_places_seed does. At
            //roughly 301 rows this is cheaper than 301 existence checks,
            //and it makes no_place_id answerable without a second query.
            $queue = array();
            $found = $wpdb->get_results(
                "SELECT address_key, place_status FROM {$table}", ARRAY_A
            );
            if ( is_array( $found ) ) {
                foreach ( $found as $r ) {
                    $queue[ $r['address_key'] ] = (string) $r['place_status'];
                }
            }

            $now  = current_time( 'mysql', true );
            $plan = array();
            $seen = array();

            foreach ( $dealers as $key => $rec ) {
                $key = (string) $key;
                $pid = '';
                if ( is_array( $rec ) && isset( $rec['place_id'] )
                     && is_string( $rec['place_id'] ) ) {
                    $pid = trim( $rec['place_id'] );
                }

                //Width and whitespace only. Google publishes no place ID
                //format and no maximum length, so a pattern assertion
                //here would be this plugin inventing a contract Google
                //has not offered. 191 is the column, and a value that
                //does not fit it would be stored truncated and silently
                //wrong. Every ID resolved so far is 27 characters, which
                //is a fact about today and not a rule.
                if ( '' === $pid || strlen( $pid ) > 191 || preg_match( '/\s/', $pid ) ) {
                    $out['rejected']++;
                    continue;
                }

                if ( ! array_key_exists( $key, $queue ) ) {
                    $out['not_in_queue']++;
                    continue;
                }
                $out['matched']++;

                if ( self::PLACES_STATUS_OK === $queue[ $key ] ) {
                    $out['already_ok']++;
                    continue;
                }
                if ( self::PLACES_STATUS_PENDING !== $queue[ $key ] ) {
                    $out['other_status']++;
                    continue;
                }

                $stamp = '';
                if ( is_array( $rec ) && isset( $rec['resolved_utc'] )
                     && is_string( $rec['resolved_utc'] ) ) {
                    $ts = strtotime( $rec['resolved_utc'] );
                    if ( is_int( $ts ) && $ts > 0 ) {
                        $stamp = gmdate( 'Y-m-d H:i:s', $ts );
                    }
                }
                if ( '' === $stamp ) {
                    $stamp = $now;
                    $out['undated']++;
                }

                $plan[ $key ] = array( 'place_id' => $pid, 'stamp' => $stamp );
                if ( ! isset( $seen[ $pid ] ) ) {
                    $seen[ $pid ] = array();
                }
                $seen[ $pid ][] = $key;
            }

            $out['distinct'] = count( $seen );
            foreach ( $seen as $pid => $keys ) {
                if ( count( $keys ) > 1 ) {
                    $out['shared']++;
                    $out['groups'][ $pid ] = $keys;
                }
            }

            if ( $apply ) {
                foreach ( $plan as $key => $row ) {
                    $n = $wpdb->query( $wpdb->prepare(
                        "UPDATE {$table} SET place_id = %s, place_status = %s,"
                        . " place_checked_at = %s, updated_at = %s"
                        . " WHERE address_key = %s AND place_status = %s",
                        $row['place_id'], self::PLACES_STATUS_OK, $row['stamp'],
                        $now, $key, self::PLACES_STATUS_PENDING
                    ) );
                    if ( is_numeric( $n ) && (int) $n > 0 ) {
                        $out['imported']++;
                        continue;
                    }
                    //The snapshot said pending and the statement matched
                    //nothing, so the row moved underneath. Counted, never
                    //retried: a retry is the branch arguing with the WHERE
                    //clause that just refused it.
                    $out['raced']++;
                }
            } else {
                $out['imported'] = count( $plan );
            }

            //Pending AND not covered by the file. Deliberately computed
            //from the snapshot and the plan rather than from the post-write
            //state, so the dry run and the apply report the same number.
            //A dry run whose counts differ from the apply it predicts is
            //not a dry run, it is a second thing to have to reconcile.
            foreach ( $queue as $key => $status ) {
                if ( self::PLACES_STATUS_PENDING === $status
                     && ! isset( $plan[ $key ] ) ) {
                    $out['no_place_id']++;
                }
            }

            if ( $apply ) {
                $this->avalon_import_log( array(
                    'stage'        => 'places_import',
                    'action'       => 'imported',
                    'file_md5'     => $out['md5'],
                    'keys_in_file' => $out['keys_in_file'],
                    'matched'      => $out['matched'],
                    'imported'     => $out['imported'],
                    'already_ok'   => $out['already_ok'],
                    'not_in_queue' => $out['not_in_queue'],
                    'no_place_id'  => $out['no_place_id'],
                    'distinct'     => $out['distinct'],
                    'shared'       => $out['shared'],
                    'raced'        => $out['raced'],
                ) );
                //avalon_import_log buffers into per-request state that
                //slp_csv_processing_complete flushes at 500. Outside an
                //import nothing flushes it, so the option copy is written
                //here, exactly as the seed subcommand does. s0.192.
                $this->avalon_flush_import_log( false );
            }

            return $out;
        }

        /**
         * v0.0.26 Part 3. WP-CLI: wp avalon places <subcommand>
         *
         *   seed    fold the locations table into the queue. Spends nothing.
         *   import  load resolved place IDs from placeids.json into
         *           the queue. --file=<path> is required and has no
         *           default; --dry-run reports and writes nothing;
         *           --expect-md5=<hash> is required to write. Spends
         *           nothing - the file records calls already paid.
         *   status  counts by place_status. Spends nothing.
         *   reset   return failed keys to pending, so a corrected address is
         *           requeued without raw SQL. --key=<12 chars> for one.
         *
         * NO CAPABILITY CHECK, deliberately. WP-CLI runs as no user, so
         * current_user_can() is false on the happy path. manage_slp_user is
         * worse still: it is granted at plugin activation, which a
         * database-import environment never ran.
         *
         * Invoke with --skip-plugins=revslider, never a bare --skip-plugins:
         * skipping slp_avalon unregisters this command and leaves
         * SLP_Avalon_AddressKey unloaded.
         */
        public function avalon_places_cli( $args, $assoc = array() ){
            global $wpdb;

            $sub   = isset( $args[0] ) ? (string) $args[0] : 'status';
            $table = self::avalon_hours_table();

            if ( 'seed' === $sub ) {
                $out = $this->avalon_places_seed();
                //avalon_import_log buffers into per-request state that
                //slp_csv_processing_complete flushes at 500. Outside an
                //import nothing flushes it, so the option copy is written
                //here. s0.192 - one logging idiom, not a second for the CLI.
                $this->avalon_flush_import_log( false );
                WP_CLI::log( sprintf(
                    'rows %d  keys %d  inserted %d  updated %d  skipped %d  unmapped %d',
                    $out['rows'], $out['keys'], $out['inserted'],
                    $out['updated'], $out['skipped'], $out['unmapped']
                ) );
                WP_CLI::success( 'seed complete' );
                return;
            }

            if ( 'import' === $sub ) {
                $file = isset( $assoc['file'] ) ? (string) $assoc['file'] : '';
                $dry  = ! empty( $assoc['dry-run'] );
                $pin  = isset( $assoc['expect-md5'] )
                        ? (string) $assoc['expect-md5'] : '';

                if ( '' === $file ) {
                    WP_CLI::error( '--file=<path> is required and has no default.' );
                    return;
                }

                //Writing requires the operator to state which file they
                //believe they are writing from. Never-overwrite makes a
                //repeat harmless but it also makes a WRONG import
                //unrecoverable: reset only returns failed to pending, so
                //300 wrong IDs would need hand-written SQL. The dry run
                //prints the hash, so the pin is copied from the run that
                //was actually read, not typed from memory. s0.204.
                if ( ! $dry && '' === $pin ) {
                    WP_CLI::error( '--expect-md5=<hash> is required to write.'
                        . ' Run --dry-run first; it prints the hash.' );
                    return;
                }

                $out = $this->avalon_places_import( $file, ! $dry, $pin );

                if ( '' !== $out['error'] ) {
                    WP_CLI::error( $out['error'] );
                    return;
                }

                WP_CLI::log( sprintf( 'file    %s', $out['file'] ) );
                WP_CLI::log( sprintf( 'md5     %s  bytes %d',
                    $out['md5'], $out['bytes'] ) );
                WP_CLI::log( sprintf(
                    'in file %d  rejected %d  matched %d  not in queue %d',
                    $out['keys_in_file'], $out['rejected'],
                    $out['matched'], $out['not_in_queue']
                ) );
                WP_CLI::log( sprintf(
                    '%s %d  already ok %d  other status %d  raced %d  undated %d',
                    $dry ? 'would import' : 'imported     ',
                    $out['imported'], $out['already_ok'],
                    $out['other_status'], $out['raced'], $out['undated']
                ) );
                WP_CLI::log( sprintf(
                    'distinct ids %d  shared by >1 key %d  queued with no place id %d',
                    $out['distinct'], $out['shared'], $out['no_place_id']
                ) );

                //The shared groups are the duplicate-dealer audit, free.
                //Printed on the dry run because that is the run somebody
                //sits and reads; the apply is the one they want short.
                if ( $dry && ! empty( $out['groups'] ) ) {
                    WP_CLI::log( '' );
                    WP_CLI::log( 'place IDs holding more than one dealer key:' );
                    foreach ( $out['groups'] as $place => $keys ) {
                        WP_CLI::log( sprintf( '  %-30s %s',
                            $place, implode( ' ', $keys ) ) );
                    }
                }

                WP_CLI::success( $dry
                    ? 'dry run, nothing written'
                    : 'import complete' );
                return;
            }

            if ( 'reset' === $sub ) {
                $key = isset( $assoc['key'] ) ? (string) $assoc['key'] : '';
                $now = current_time( 'mysql', true );
                if ( '' !== $key ) {
                    $n = $wpdb->query( $wpdb->prepare(
                        "UPDATE {$table} SET place_status = %s, error_count = 0,"
                        . " last_error = NULL, updated_at = %s"
                        . " WHERE address_key = %s AND place_status = %s",
                        self::PLACES_STATUS_PENDING, $now, $key, self::PLACES_STATUS_FAILED
                    ) );
                } else {
                    $n = $wpdb->query( $wpdb->prepare(
                        "UPDATE {$table} SET place_status = %s, error_count = 0,"
                        . " last_error = NULL, updated_at = %s"
                        . " WHERE place_status = %s",
                        self::PLACES_STATUS_PENDING, $now, self::PLACES_STATUS_FAILED
                    ) );
                }
                WP_CLI::success( sprintf( '%d key(s) returned to pending', (int) $n ) );
                return;
            }

            $counts = $wpdb->get_results(
                "SELECT place_status, COUNT(*) AS n FROM {$table} GROUP BY place_status",
                ARRAY_A
            );
            if ( ! is_array( $counts ) || empty( $counts ) ) {
                WP_CLI::log( 'queue empty' );
                return;
            }
            foreach ( $counts as $r ) {
                WP_CLI::log( sprintf( '%-8s %d', $r['place_status'], (int) $r['n'] ) );
            }
        }
    }
}