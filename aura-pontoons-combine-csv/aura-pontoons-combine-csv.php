<?php
/*
Plugin Name: Aura Pontoons Combine CSV
Plugin URI: https://aurapontoons.com/
Description: 
Version: 1.0.0
License: GPL-3.0+
Text Domain: apcc
Domain Path: /languages

*/

class APCC_Notice{
    private string $message;
    private string $type;

    public function __construct(string $message, string $type = "error"){
        $this->message = $message;
        $this->type = $type;
        if (did_action('admin_notices') > 0){
            $this->render();
        } else {
            add_action('admin_notices',[$this,'render']);
        }
    }

    public function render(){
        printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', $this->type, esc_html( $this->message ) );
    }
}

class AuraPontoonsCombineCSV{
    private static $instance;
    private static $debug = true;
    private static $time_to_run = "00:00:00";
    public static function instance(){
        if ( ! isset( self::$instance ) && ! ( self::$instance instanceof AuraPontoonsCombineCSV ) ) {
            // Main plugin class.
            self::$instance = new AuraPontoonsCombineCSV();

            // Include required files.
            self::$instance->includes();

            // Required only when admin.
            if ( is_admin() ) {
                self::$instance->init_admin();
            }

            // Required only when not admin.
            if ( ! is_admin() ) {
                self::$instance->init_frontend();
            }
            self::$instance->add_actions();
            // Schedule an action if it's not already scheduled
            if ( ! wp_next_scheduled( 'apcc_daily' ) ) {
                wp_schedule_event( strtotime(self::$time_to_run), 'daily', 'apcc_daily' );
            }
        }

        return self::$instance;
    }

    private static function log($error)
    {
        if (!self::$debug) return false;
        return self::error($error,"Aura Pontoons Combine CSV Error");
    }
    private static function error($error,$title = "Aura Pontoons Combine CSV Error"){
        $log_file = dirname(__FILE__) . "/error.log";
        error_log("log_file");
        error_log($log_file);
        $time = date("j-F-Y g:i:s T");
        file_put_contents($log_file, "[{$time}] {$title} : " . print_r($error, true) . PHP_EOL, FILE_APPEND);
    }
    public function add_actions(){
        add_action('parse_request',[self::$instance,'parseRequest']);
        
        add_action('apcc_daily',[self::$instance,'apcc_daily_hook']);
        //AJAX
        // add_action('wp_ajax_lserp_get_price_sync_job_status',[self::$instance,'get_price_sync_job_status_ajax_func']);
        // add_action('wp_ajax_lserp_start_price_sync_ajax',[self::$instance,'start_price_sync_ajax_func']);
    }
    public static function activate(){
    }
    public function includes(){

    }
    public function init_admin(){
        //Create admin pages
        add_action('admin_init',[$this,'register_and_build_settings_fields']);
        add_action('admin_menu', [$this,'settings_page_init']);
    }
    public function init_frontend(){
    }
    public function parseRequest(){
        $request_uri_string = $_SERVER['REQUEST_URI'];
        // if (strpos($request_uri_string,'test_request') !== false){
        // }
    }
    public function apcc_daily_hook(){
        self::combine_csv_files();
    }
    public function register_and_build_settings_fields(){
        add_settings_section('apcc_settings_general','Aura Pontoons Combine CSV',[$this,'api_settings_description'],'apcc_settings');
        $settings_fields = [
            [
                'name' => 'apcc_csv_1_url',
                'label' => 'First CSV URL',
                'render_cb' => [$this,'render_settings_field'],
                'page' => 'apcc_settings',
                'section' => 'apcc_settings_general',
                'args' => [
                    'type' => 'input',
                    'subtype' => 'text',
                    'id' => 'apcc_csv_1_url',
                    'name' => 'apcc_csv_1_url',
                    'required' => false,
                    'get_options_list' => '',
                    'value_type' => 'normal',
                    'wp_data' => 'option',
                    'description' => "URL for the the first CSV File"
                ]
            ],
            [
                'name' => 'apcc_csv_2_url',
                'label' => 'Second CSV URL',
                'render_cb' => [$this,'render_settings_field'],
                'page' => 'apcc_settings',
                'section' => 'apcc_settings_general',
                'args' => [
                    'type' => 'input',
                    'subtype' => 'text',
                    'id' => 'apcc_csv_2_url',
                    'name' => 'apcc_csv_2_url',
                    'required' => false,
                    'get_options_list' => '',
                    'value_type' => 'normal',
                    'wp_data' => 'option',
                    'description' => "URL for the the second CSV File"
                ]
            ],
            [
                'name' => 'apcc_combined_csv_name',
                'label' => 'Combined CSV Name',
                'render_cb' => [$this,'render_settings_field'],
                'page' => 'apcc_settings',
                'section' => 'apcc_settings_general',
                'args' => [
                    'type' => 'input',
                    'subtype' => 'text',
                    'id' => 'apcc_combined_csv_name',
                    'name' => 'apcc_combined_csv_name',
                    'required' => false,
                    'get_options_list' => '',
                    'value_type' => 'normal',
                    'wp_data' => 'option',
                    'description' => "Name of the combined CSV file."
                ]
            ],
        ];
        
        foreach ($settings_fields as $field){
            add_settings_field($field['name'],$field['label'],$field['render_cb'],$field['page'],$field['section'],$field['args']);
            register_setting($field['page'],$field['name']);
        }   
    }
    public function render_settings_field($args)
    {
        /* EXAMPLE INPUT
               'type'      => 'input',
               'subtype'   => '',
               'id'    => $this->plugin_name.'_example_setting',
               'name'      => $this->plugin_name.'_example_setting',
               'required' => 'required="required"',
               'get_option_list' => "",
                 'value_type' = serialized OR normal,
        'wp_data'=>(option or post_meta),
        'post_id' =>
        */     
        if($args['wp_data'] == 'option'){
            $wp_data_value = get_option($args['name']);
        } elseif($args['wp_data'] == 'post_meta'){
            $wp_data_value = get_post_meta($args['post_id'], $args['name'], true );
        }
        switch ($args['type']) {
            case 'input':
                $value = ($args['value_type'] == 'serialized') ? serialize($wp_data_value) : $wp_data_value;
                if($args['subtype'] != 'checkbox'){
                    $prependStart = isset($args['prepend_value']) ? '<div class="input-prepend"> <span class="add-on">'.$args['prepend_value'].'</span>' : '';
                    $prependEnd = isset($args['prepend_value']) ? '</div>' : '';
                    $step = isset($args['step']) ? 'step="'.$args['step'].'"' : '';
                    $min = isset($args['min']) ? 'min="'.$args['min'].'"' : '';
                    $max = isset($args['max']) ? 'max="'.$args['max'].'"' : '';
                    if(isset($args['disabled'])){
                        // hide the actual input bc if it was just a disabled input the information saved in the database would be wrong - bc it would pass empty values and wipe the actual information
                        echo $prependStart.'<input type="'.$args['subtype'].'" id="'.$args['id'].'_disabled" '.$step.' '.$max.' '.$min.' name="'.$args['name'].'_disabled" size="40" disabled value="' . esc_attr($value) . '" /><input type="hidden" id="'.$args['id'].'" '.$step.' '.$max.' '.$min.' name="'.$args['name'].'" size="40" value="' . esc_attr($value) . '" />'.$prependEnd;
                    } else {
                        echo $prependStart.'<input type="'.$args['subtype'].'" id="'.$args['id'].'" "'.$args['required'].'" '.$step.' '.$max.' '.$min.' name="'.$args['name'].'" size="40" value="' . esc_attr($value) . '" />'.$prependEnd;
                    }
                    /*<input required="required" '.$disabled.' type="number" step="any" id="'.$this->plugin_name.'_cost2" name="'.$this->plugin_name.'_cost2" value="' . esc_attr( $cost ) . '" size="25" /><input type="hidden" id="'.$this->plugin_name.'_cost" step="any" name="'.$this->plugin_name.'_cost" value="' . esc_attr( $cost ) . '" />*/
                } else {
                    $checked = $value ? 'checked' : '';
                    echo '<input type="'.$args['subtype'].'" id="'.$args['id'].'" "'.$args['required'].'" name="'.$args['name'].'" size="40" value="1" '.$checked.' />';
                }
                break;
            default:
                # code...
                break;
        }
        if (isset($args['description'])){
            echo "<p class='description' id='{$args['id']}-description'>{$args['description']}</p>";
        }
    }
    public function api_settings_description(){
        echo "";
    }
    public function settings_page_init(){
        add_options_page('Combine CSV','Combine CSV','administrator','apcc_settings',[$this,'settings_page_render']);
    }
    public function process_settings_page_post(){
        //process POST
        $inputs = [
            'apcc_csv_1_url' => [
                'id' => 'apcc_csv_1_url',
                'name' => 'CSV 1 URL',
                'value' => null,
                'type' => 'url',
                'file_type' => 'csv'
            ],
            'apcc_csv_2_url' => [
                'id' => 'apcc_csv_2_url',
                'name' => 'CSV 2 URL',
                'value' => null,
                'type' => 'url',
                'file_type' => 'csv'
            ],
            'apcc_combined_csv_name' => [
                'id' => 'apcc_combined_csv_name',
                'name' => 'Combined CSV Name',
                'value' => null,
                'type' => 'filename'
            ]
        ];
        foreach ($inputs as $id=>$input){
            $inputs[$id]['value'] = $_POST[$input['id']] ?? null;
        }
        $validation_check = self::validate_inputs($inputs,true);
        foreach ($validation_check['inputs'] as $input){
            if ($input['valid']){
                update_option($input['id'],$input['value']);
            }
        }
        if (!$validation_check['success']){
            foreach ($validation_check['errors'] as $error){
                new APCC_Notice($error);
            }
            return $validation_check;
        }
        //Check if we have all values
        foreach ($validation_check['inputs'] as $input){
            if (empty($input['value'])){
                //We can't continue, but we still return success since we updated the values, even if incomplete
                //We just won't proceed to combining the CSV's
                return [
                    'success' => true
                ];
            }
        }
        $combine_return = self::combine_csv_files();
        if (!$combine_return['success']){
            foreach ($combine_return['errors'] as $error){
                new APCC_Notice($error);
            }
        }
        return $combine_return;
    }
    public function settings_page_render()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $processing_result = $this->process_settings_page_post();
        }
        $last_combine_time = get_option('apcc_last_combine_time');
        if (empty($last_combine_time)) {
            $last_combine_time = "Never";
        } else {
            $last_combine_time = date('F j Y H:i:s', $last_combine_time) . ' ' . date_default_timezone_get();
        }
        ?>
        <div class="wrap">
            <form method="POST">
                <?php
                    settings_fields('apcc_settings');
                    do_settings_sections('apcc_settings');
                ?>
                <p>The CSV files will be combined everyday at midnight, and whenever you Save Changes on this page.</p>
                <?php
                    $combined_csv_file_url = self::get_combined_csv_file_url();
                    if (!empty($combined_csv_file_url)){
                        echo "<p>The Combined CSV File can be found at this url : <a href='{$combined_csv_file_url}' target='_blank'>{$combined_csv_file_url}</a></p>";
                    }
                ?>
                <?php submit_button();?>
            </form>
        </div>
        <?php
    }
    public static function get_combined_csv_file_url(){
        $combined_csv_file_name = get_option('apcc_combined_csv_name');
        if (empty($combined_csv_file_name)){
            return null;
        }
        $url = content_url() . "/apcc/{$combined_csv_file_name}";
        return $url;
    }

    public static function is_valid_csv($file){
        //Check if resource
        if (!file_exists($file)){
            return false;
        }
        $csv_mimes = [
            'text/x-comma-separated-values', 
            'text/comma-separated-values', 
            'application/octet-stream', 
            'application/vnd.ms-excel', 
            'application/x-csv', 
            'text/x-csv', 
            'text/csv', 
            'application/csv', 
            'application/excel', 
            'application/vnd.msexcel', 
            'text/plain'
        ];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo,$file);
        return in_array($mime,$csv_mimes);
    }

    public static function combine_csv_files(){
        $return = [
            'success' => false,
        ];
        $inputs = [
            'apcc_csv_1_url' => [
                'id' => 'apcc_csv_1_url',
                'name' => 'CSV 1 URL',
                'value' => null,
                'type' => 'url',
                'file_type' => 'csv,'
            ],
            'apcc_csv_2_url' => [
                'id' => 'apcc_csv_2_url',
                'name' => 'CSV 2 URL',
                'value' => null,
                'type' => 'url',
                'file_type' => 'csv'
            ],
            'apcc_combined_csv_name' => [
                'id' => 'apcc_combined_csv_name',
                'name' => 'Combined CSV Name',
                'value' => null,
                'type' => 'filename'
            ]
        ];
        foreach ($inputs as $id=>$input){
            $inputs[$id]['value'] = get_option($input['id']);
        }
        $validation_check = self::validate_inputs($inputs);
        if (!$validation_check['success']){
            return $validation_check;
        }
        $inputs = $validation_check['inputs'];
        //Double check the inputs
        $errors = [];
        foreach ($inputs as $input){
            if (!$input['valid']){
                $errors[] = $input['error'];
            }
        }
        if (count($errors) > 0){
            $return['errors'] = $errors;
            return $return;
        }
        //Now we can combine
        //Check if we have the folder in contents, otherwise create it
        $dir_path = WP_CONTENT_DIR . "/apcc";
        if (!is_dir($dir_path)){
            mkdir($dir_path,0777,true);
        }
        //Check again if the path exists
        if (!is_dir($dir_path)){
            //Something bad happened, can't create directory
            $errors[] = "Can not create directory 'apcc' in 'wp-content'";
            $return['errors'] = $errors;
            return $return;
        }
        //Now that we have the directory, start downloading the files to a temp folder
        if ( ! function_exists( 'download_url' ) ) {
            require_once(WPINC . '/file.php');
        }
        $file_urls = [
            $inputs['apcc_csv_1_url']['value'],
            $inputs['apcc_csv_2_url']['value']
        ];
        $combined_file_path = WP_CONTENT_DIR . "/apcc/{$inputs['apcc_combined_csv_name']['value']}";
        $combined_file_temp_path = WP_CONTENT_DIR . "/apcc/temp_result.csv";
        //Delete the file if it still exists from previous attempts
        if (file_exists($combined_file_temp_path)){
            @unlink($combined_file_temp_path);
        }
        //We'll use the first csv file as temp result
        $combined_file_temp_h = null;
        foreach ($file_urls as $url){
            //Safely download the file first
            $temp_input_file = download_url($url);
            if (is_wp_error($temp_input_file)){
                $errors[] = $temp_input_file->get_error_message();
                continue;
            }
            //Make sure it's CSV
            if (!self::is_valid_csv($temp_input_file)){
                $errors[] = "{$url} is not a valid CSV file";
                unlink($temp_input_file);
                continue;
            }
            //If we don't have a temp result file, we use this file
            if ($combined_file_temp_h === null || (is_resource($combined_file_temp_h) && get_resource_type($combined_file_temp_h) !== 'stream')){
                copy($temp_input_file,$combined_file_temp_path);
                $combined_file_temp_h = fopen($combined_file_temp_path,"a+");
                @unlink($temp_input_file);
                continue;
            }
            //Now we add each line individually, but skipping the first line (headers)
            $header_skipped = false;
            $temp_input_file_h = fopen($temp_input_file,"r");
            while (!feof($temp_input_file_h)){
                $line = fgets($temp_input_file_h);
                if (!$header_skipped){
                    $header_skipped = true;
                    continue;
                }
                fwrite($combined_file_temp_h,$line);
            }
            fclose($temp_input_file_h);
            unset($temp_input_file_h);
            @unlink($temp_input_file);
            fwrite($combined_file_temp_h,"\n");//Usually last line doesn't have a newline
        }
        fclose($combined_file_temp_h);
        //If we have any errors, we don't replace the file
        if (count($errors) > 0){
            $return['errors'] = $errors;
            //Unlink the temp file
            @unlink($combined_file_temp);
            unset($combined_file_temp_h);
            return $return;
        }
        //Now we replace the old file with the new file
        @unlink($combined_file_path);
        copy($combined_file_temp_path,$combined_file_path);
        $return['success'] = true;
        return $return;
    }
    public static function validate_inputs($inputs,$ignore_empty = false){
        $return = [
            'success' => false,
            'inputs' => [],
            'errors' => [],
        ];
        foreach ($inputs as &$input){
            if (empty($input['value'])){
                if ($ignore_empty){
                    $input['valid'] = true;
                } else {
                    $input['valid'] = false;
                    $input['error'] = "{$input['name']} can not be empty";
                    $return['errors'][] = $input['error'];
                }
                continue;
            }
            switch ($input['type']){
                case "url":
                    $input['valid'] = wp_http_validate_url($input['value']);
                    if (!$input['valid']){
                        $input['error'] = "{$input['value']} is not a valid URL";
                        $return['errors'][] = $input['error'];
                    } else {
                        //Check if it's correct filetype
                        if (isset($input['file_type'])){
                            if ($input['file_type'] == 'csv'){
                                //Download the file and check if it's valid csv
                                //Safely download the file first
                                $temp_input_file = download_url($input['value']);
                                if (is_wp_error($temp_input_file)){
                                    $input['valid'] = false;
                                    $input['error'] = $temp_input_file->get_error_message();
                                    $return['errors'][] = $input['error'];
                                } else {
                                    //Check if it's CSV
                                    if (!self::is_valid_csv($temp_input_file)){
                                        $input['valid'] = false;
                                        $input['error'] = "{$input['value']} is not a valid CSV file";
                                        $return['errors'][] = $input['error'];
                                    }
                                    unlink($temp_input_file);
                                }
                            }
                        }
                    }
                    break;
                case "filename":
                    $input['valid'] = self::valid_filename($input['value']);
                    if (!$input['valid']){
                        $input['error'] = "{$input['value']} is not a valid filename";
                        $return['errors'][] = $input['error'];
                    }
                    break;
            }
        }
        $return['inputs'] = $inputs;
        if (count($return['errors']) == 0){
            $return['success'] = true;
        }
        return $return;
    }
    public static function valid_filename($filename){
        return preg_match('/^[\w\-. ]+$/', $filename);
    }
}

// Run the plugin.
add_action( 'plugins_loaded', 'apcc_init' );
function apcc_init(){
    return AuraPontoonsCombineCSV::instance();
}