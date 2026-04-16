<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
// Exit if accessed directly, dang hackers
// Make sure the classes are only defined once.
//
if ( !class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
/**
 * The data interface helper.
 *
 * @package StoreLocatorPlus\Extended_Data_Manager\AdminUI\ElementManager_Table
 * @author De B.A.A.T. <slp-edm@de-baat.nl>
 * @copyright 2022 De B.A.A.T. - Charleston Software Associates, LLC
 *
 * @property        SLP_Extended_Data_Manager                     $addon
 * @property        SLP_Extended_Data_Manager_Admin               $admin                The admin object for this addon.
 * @property        SLP_Extended_Data_Manager_Admin_Elements      $edm_admin_elements   The object for Elements.
 *
 */
class SLP_Extended_Data_Manager_AdminUI_ElementManager_Table extends WP_List_Table
{
    private  $slplus ;
    public  $addon ;
    public  $admin ;
    public  $edm_admin_elements ;
    public  $baseAdminURL = '' ;
    public  $cleanAdminURL = '' ;
    public  $hangoverURL = '' ;
    private  $db_orderbydir = '' ;
    private  $db_orderbyfield = '' ;
    private  $my_current_action = '' ;
    //-------------------------------------
    // Methods : Base
    //-------------------------------------
    /**
     * Initialize the List Table
     *
     * @param mixed[] $params
     */
    public function __construct( $params = null )
    {
        // Parse the params
        if ( $params != null && is_array( $params ) ) {
            foreach ( $params as $key => $value ) {
                $this->{$key} = $value;
                $this->debugMP( 'msg', __FUNCTION__ . ' parsed key: ' . $key );
            }
        }
        // Define the edm_types and edm_display_types
        $edm_types = $this->edm_get_types();
        $edm_display_types = $this->edm_get_display_types();
        parent::__construct( array(
            'singular' => 'edm_slug',
            'plural'   => 'edm_slugs',
            'ajax'     => false,
        ) );
        // Get the currentAction
        $this->initialize();
    }
    
    /**
     * Things we do at the start.
     */
    public function initialize()
    {
        $this->debugMP( 'msg', __FUNCTION__ . ' started.' );
        SLP_Extended_Data_Manager_Text::get_instance();
        $this->edm_admin_elements = $this->admin->edm_admin_elements;
        // $this->cur_element_object  = $this->edm_admin_elements->get_cur_element_object();
        // Get the my_current_action
        $this->my_current_action = $this->current_action();
        // Set our base Admin URL
        //
        
        if ( isset( $_SERVER['REQUEST_URI'] ) ) {
            $this->cleanAdminURL = ( isset( $_SERVER['QUERY_STRING'] ) ? str_replace( '?' . $_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI'] ) : $_SERVER['REQUEST_URI'] );
            $queryParams = array();
            // Base Admin URL = must have params
            //
            if ( isset( $_REQUEST['page'] ) ) {
                $queryParams['page'] = esc_url( $_REQUEST['page'] );
            }
            $queryParams[SLP_EDM_ACTION] = 'reorder';
            $this->baseAdminURL = $this->cleanAdminURL . '?' . build_query( $queryParams );
            //$_SERVER['REQUEST_URI'] = $this->baseAdminURL;
            // Hangover URL = params we like to carry around sometimes
            //
            $this->db_orderbyfield = SLP_EDM_TABLE_SLUG;
            $this->db_orderbydir = 'asc';
            if ( isset( $_REQUEST['searchfor'] ) && !empty($_REQUEST['searchfor']) ) {
                $queryParams['searchfor'] = sanitize_text_field( $_REQUEST['searchfor'] );
            }
            if ( isset( $_REQUEST['start'] ) && intval( $_REQUEST['start'] ) >= 0 ) {
                $queryParams['start'] = intval( $_REQUEST['start'] );
            }
            
            if ( isset( $_REQUEST['orderby'] ) && !empty($_REQUEST['orderby']) ) {
                $queryParams['orderby'] = sanitize_sql_orderby( $_REQUEST['orderby'] );
                $this->db_orderbyfield = sanitize_sql_orderby( $_REQUEST['orderby'] );
            }
            
            
            if ( isset( $_REQUEST['order'] ) && !empty($_REQUEST['order']) ) {
                $queryParams['order'] = sanitize_text_field( $_REQUEST['order'] );
                $this->db_orderbydir = sanitize_text_field( $_REQUEST['order'] );
            }
            
            $this->hangoverURL = $this->cleanAdminURL . '?' . build_query( $queryParams );
            $this->debugMP( 'msg', __FUNCTION__ );
            $this->debugMP( 'msg', __FUNCTION__ . ' _SERVER[REQUEST_URI]: ' . $_SERVER['REQUEST_URI'] );
            $this->debugMP( 'msg', __FUNCTION__ . ' cleanAdminURL:  ' . $this->cleanAdminURL );
            $this->debugMP( 'msg', __FUNCTION__ . ' baseAdminURL:   ' . $this->baseAdminURL );
            $this->debugMP( 'msg', __FUNCTION__ . ' hangoverURL:    ' . $this->hangoverURL );
            $this->debugMP( 'pr', __FUNCTION__ . ' slplus->clean:  ', $this->slplus->clean );
            $this->debugMP( 'pr', __FUNCTION__ . ' _SERVER:        ', $_SERVER );
        }
    
    }
    
    /**
     * Used to help set column labels and output structure.
     *
     * @return mixed[]
     */
    function get_columns()
    {
        //$this->debugMP('msg',__FUNCTION__.' started.');
        $columns = array(
            'cb'                       => '<input type="checkbox" />',
            SLP_EDM_TABLE_LABEL        => __( 'Label', 'slp-extended-data-manager' ),
            SLP_EDM_TABLE_SLUG         => __( 'Slug', 'slp-extended-data-manager' ),
            SLP_EDM_TABLE_SHOW         => __( 'Show/Hide', 'slp-extended-data-manager' ),
            SLP_EDM_TABLE_ORDER        => __( 'Show Order', 'slp-extended-data-manager' ),
            SLP_EDM_TABLE_TYPE         => __( 'Type', 'slp-extended-data-manager' ),
            SLP_EDM_TABLE_DISPLAY_TYPE => __( 'Display Type', 'slp-extended-data-manager' ),
            SLP_EDM_TABLE_HELP_TEXT    => __( 'Help Text', 'slp-extended-data-manager' ),
        );
        return $columns;
    }
    
    /**
     * Used to help set column meta data about which table columns should be hidden.
     *
     * @return mixed[]
     */
    function get_hidden_columns()
    {
        $this->debugMP( 'msg', __FUNCTION__ . ' started.' );
        $hidden_columns = array(
            SLP_EDM_TABLE_FIELD_ID => __( 'Field id', 'slp-extended-data-manager' ),
            SLP_EDM_TABLE_OPTIONS  => __( 'Options', 'slp-extended-data-manager' ),
        );
        return $hidden_columns;
    }
    
    /**
     * Used to help set column meta data about which table columns can be sorted.
     *
     * @return mixed[]
     */
    function get_sortable_columns()
    {
        $this->debugMP( 'msg', __FUNCTION__ . ' started.' );
        $sortable_columns = array(
            SLP_EDM_TABLE_FIELD_ID     => array( SLP_EDM_TABLE_FIELD_ID, true ),
            SLP_EDM_TABLE_LABEL        => array( SLP_EDM_TABLE_LABEL, false ),
            SLP_EDM_TABLE_SLUG         => array( SLP_EDM_TABLE_SLUG, false ),
            SLP_EDM_TABLE_TYPE         => array( SLP_EDM_TABLE_TYPE, false ),
            SLP_EDM_TABLE_SHOW         => array( SLP_EDM_TABLE_SHOW, false ),
            SLP_EDM_TABLE_ORDER        => array( SLP_EDM_TABLE_ORDER, false ),
            SLP_EDM_TABLE_DISPLAY_TYPE => array( SLP_EDM_TABLE_DISPLAY_TYPE, false ),
        );
        return $sortable_columns;
    }
    
    /**
     * Output the special checkbox column.
     * @param mixed[] $item
     * @return string
     */
    function column_cb( $item )
    {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/
            $this->_args['singular'],
            //Let's simply repurpose the table's singular label ("edm_slug")
            /*$2%s*/
            $item[SLP_EDM_TABLE_SLUG]
        );
    }
    
    /**
     * Output the special checkbox column.
     * @param mixed[] $item
     * @return string
     */
    function column_option_order( $item )
    {
        $request_name_option = SLP_EDM_ACTION_ELEMENT_UPDATE . '[' . $item[SLP_EDM_TABLE_SLUG] . '][' . SLP_EDM_OPTION_ORDER . ']';
        return sprintf(
            '<input type="number" class="digits-text" name="%1$s" value="%2$s" max="%3$s" />',
            /*$1%s*/
            $request_name_option,
            //The key should reflect the option name
            /*$2%s*/
            $item[SLP_EDM_TABLE_ORDER],
            /*$3%s*/
            SLP_EDM_OPTION_ORDER_MAX
        );
    }
    
    /**
     * Output the special checkbox column.
     * @param mixed[] $item
     * @return string
     */
    function column_option_help_text( $item )
    {
        $request_name_option = SLP_EDM_ACTION_ELEMENT_UPDATE . '[' . $item[SLP_EDM_TABLE_SLUG] . '][' . SLP_EDM_OPTIONS . '][' . SLP_EDM_OPTION_HELP_TEXT . ']';
        $num_rows = ( strlen( $item[SLP_EDM_TABLE_HELP_TEXT] ) > 0 ? strlen( $item[SLP_EDM_TABLE_HELP_TEXT] ) / 25 : 2 );
        return sprintf(
            '<textarea name="%1$s" rows="%2$s" style="min-width:1px;width:10em;">%3$s</textarea>',
            /*$1%s*/
            $request_name_option,
            //The key should reflect the option name
            /*$2%s*/
            $num_rows,
            /*$3%s*/
            $item[SLP_EDM_TABLE_HELP_TEXT]
        );
    }
    
    /**
     * Output the column showing the loginname including the actions to perform.
     * @param mixed[] $item
     * @return string
     */
    function column_label( $item )
    {
        // Generate input field for label
        $column_id = SLP_EDM_ACTION_ELEMENT_UPDATE . '[' . $item[SLP_EDM_TABLE_SLUG] . '][label]';
        $row_label = $this->get_text_html( $column_id, $item[SLP_EDM_TABLE_LABEL] );
        //Build row actions
        $actions = array();
        //Return the title contents
        return sprintf(
            '%1$s %2$s',
            /*$1%s*/
            $row_label,
            /*$2%s*/
            $this->row_actions( $actions )
        );
    }
    
    /**
     *
     * @param type $item
     * @param type $column_name
     */
    function column_default( $item, $column_name )
    {
        //$this->debugMP('pr',__FUNCTION__ . ': column_default ' . $column_name . ' for item:', $item);
        $column_id = SLP_EDM_ACTION_ELEMENT_UPDATE . '[' . $item[SLP_EDM_TABLE_SLUG] . '][' . $column_name . ']';
        //$this->debugMP('pr',__FUNCTION__ . ': column_id=' . $column_id . ', item=', $item);
        switch ( $column_name ) {
            case 'ID':
            case SLP_EDM_TABLE_SLUG:
                return $this->get_slug_html( $item );
            case SLP_EDM_TABLE_FIELD_ID:
            case SLP_EDM_TABLE_ORDER:
                return $item[$column_name];
            case SLP_EDM_TABLE_LABEL:
                return $this->get_text_html( $column_id, $item[$column_name] );
            case SLP_EDM_TABLE_TYPE:
                return $this->get_dropdown_html( $this->edm_get_types(), $column_id, $item[$column_name] );
            case SLP_EDM_TABLE_DISPLAY_TYPE:
                return $this->get_dropdown_html( $this->edm_get_display_types(), $column_id, $item[$column_name] );
            case SLP_EDM_TABLE_HELP_TEXT:
                return $this->get_help_text_html( $item );
            case SLP_EDM_TABLE_OPTIONS:
                return $this->get_options_html( $item[$column_name] );
            case SLP_EDM_TABLE_SHOW:
                return $this->get_boolean_html( $item[$column_name], __( 'Show', 'slp-extended-data-manager' ), __( 'Hide', 'slp-extended-data-manager' ) );
            default:
                return print_r( $item, true );
        }
    }
    
    /** ************************************************************************
     * Optional. If you need to include bulk actions in your list table, this is
     * the place to define them. Bulk actions are an associative array in the format
     * 'slug'=>'Visible Title'
     * 
     * If this method returns an empty value, no bulk action will be rendered. If
     * you specify any bulk actions, the bulk actions box will be rendered with
     * the table automatically on display().
     * 
     * Also note that list tables are not automatically wrapped in <form> elements,
     * so you will need to create those manually in order for bulk actions to function.
     * 
     * @return array An associative array containing all the bulk actions: 'slugs'=>'Visible Titles'
     **************************************************************************/
    function get_bulk_actions()
    {
        //Build bulk actions
        $actions = array();
        return $actions;
    }
    
    /**
     * Get the current action selected from the bulk actions dropdown.
     *
     * @since 3.1.0
     * @access public
     *
     * @return string|bool The action name or False if no action was selected
     */
    public function current_action()
    {
        if ( isset( $_REQUEST['filter_action'] ) && !empty($_REQUEST['filter_action']) ) {
            return false;
        }
        if ( isset( $_REQUEST[SLP_EDM_ACTION] ) && -1 != $_REQUEST[SLP_EDM_ACTION] ) {
            return sanitize_key( $_REQUEST[SLP_EDM_ACTION] );
        }
        if ( isset( $_REQUEST[SLP_EDM_ACTION . '2'] ) && -1 != $_REQUEST[SLP_EDM_ACTION . '2'] ) {
            return sanitize_key( $_REQUEST[SLP_EDM_ACTION . '2'] );
        }
        if ( isset( $_REQUEST['action'] ) && array_key_exists( $_REQUEST['action'], $this->get_bulk_actions() ) ) {
            return sanitize_key( $_REQUEST['action'] );
        }
        return false;
    }
    
    /** ************************************************************************
     * Optional. You can add additional filters after the bulk action.
     * 
     * @see $this->prepare_items()
     **************************************************************************/
    function extra_tablenav( $which )
    {
        $this->debugMP( 'msg', __FUNCTION__ . ' started.' );
        
        if ( $which == 'top' ) {
            $theHTML = '';
            $theHTML .= '<div class="alignleft actions">';
            $theHTML .= $this->createstring_FiltersBlock();
            $theHTML .= '</div>';
            echo  $theHTML ;
        }
    
    }
    
    /**
     * Create the filters drop down for the top-of-table navigation.
     *
     */
    function createstring_FiltersBlock()
    {
        // TODO: Fix filtering using AJAX functionality
        return;
        // Setup the properties array for our drop down.
        //
        $dropdownItems = array( array(
            'label'    => __( 'Show All', 'slp-extended-data-manager' ),
            'value'    => SLP_EDM_ACTION_FILTER_ALL,
            'selected' => true,
        ), array(
            'label'    => __( 'Only Show', 'slp-extended-data-manager' ),
            'value'    => SLP_EDM_ACTION_FILTER_SHOW,
            'selected' => false,
        ), array(
            'label'    => __( 'Only Hidden', 'slp-extended-data-manager' ),
            'value'    => SLP_EDM_ACTION_FILTER_HIDE,
            'selected' => false,
        ) );
        // FILTER: slp_elements_manage_filters
        //
        $dropdownItems = apply_filters( 'slp_elements_manage_filters', $dropdownItems );
        // Only show filter when multiple options available
        if ( count( $dropdownItems ) <= 1 ) {
            return;
        }
        // Loop through the action boxes content array
        //
        $baExtras = '';
        foreach ( $dropdownItems as $item ) {
            if ( isset( $item['extras'] ) && !empty($item['extras']) ) {
                $baExtras .= $item['extras'];
            }
        }
        // Create the box div string.
        //
        $morebox = "'#extra_'+jQuery('#filterTypeEDM').val()";
        $filter_dialog_title = __( 'Filter Elements By', 'slp-extended-data-manager' );
        $dialog_options = "appendTo: '#locationForm'      , " . "minWidth: 450                  , " . "title: '{$filter_dialog_title}'  , " . "position: { my: 'left top', at: 'left bottom', of: '#filterTypeEDM' } ";
        return $this->slplus->Helper->createstring_DropDownMenuWithButton( array(
            'id'          => 'filterTypeEDM',
            'name'        => 'filter',
            'items'       => $dropdownItems,
            'onchange'    => "jQuery({$morebox}).dialog({ {$dialog_options} });",
            'buttonlabel' => __( 'Filter', 'slp-extended-data-manager' ),
            'onclick'     => 'AdminUI.doAction(jQuery(\'#filterTypeEDM\').val(),\'\');',
        ) ) . $baExtras;
    }
    
    /** ************************************************************************
     * Optional. You can handle your bulk actions anywhere or anyhow you prefer.
     * For this example package, we will handle it in the class to keep things
     * clean and organized.
     * 
     * @see $this->prepare_items()
     **************************************************************************/
    function process_bulk_action()
    {
        $this->debugMP( 'msg', __FUNCTION__ . ': current_action = ' . $this->current_action() );
    }
    
    /**
     * Get the list from the output buffer of parent method.
     *
     * @return string
     */
    function display()
    {
        $this->debugMP( 'msg', __FUNCTION__ . ' started.' );
        ob_start();
        //<!-- For plugins, we also need to ensure that the form posts back to our current page -->
        echo  '<input type="hidden" name="page" value="slp_general" />' ;
        echo  '<input type="hidden" id="selected_nav_element" name="selected_nav_element" value="' . SLP_EDM_SELECTED_NAV_ELEMENT . '" />' ;
        //<!-- Now we can render the completed list table -->
        parent::display();
        return ob_get_clean();
    }
    
    /**
     * No extended data elements found.
     */
    function no_items()
    {
        _e( 'No extended data elements were found.', 'slp-extended-data-manager' );
    }
    
    /**
     * Create the text input for the element
     */
    function get_text_html( $data_label = '', $data_value = '' )
    {
        //$this->debugMP('msg',__FUNCTION__ . ': data_value= ' . $data_value);
        $html_output = '';
        
        if ( !empty($data_value) ) {
            $html_output .= '<input class="element-text" name="';
            $html_output .= $data_label;
            $html_output .= '" value="';
            $html_output .= $data_value;
            $html_output .= '">';
        }
        
        return $html_output;
    }
    
    /**
     * Create the output for the boolean
     */
    function get_boolean_html( $data_value = '', $true_value = 'Yes', $false_value = 'No' )
    {
        //$this->debugMP('msg',__FUNCTION__ . ': data_value= ' . $data_value);
        if ( $data_value == '' ) {
            return __( '--', 'slp-extended-data-manager' );
        }
        
        if ( $this->slplus->is_CheckTrue( $data_value ) ) {
            return $true_value;
        } else {
            return $false_value;
        }
        
        return '';
    }
    
    /**
     * Create the text input for the element
     */
    function get_slug_html( $data_value = '' )
    {
        //$this->debugMP('msg',__FUNCTION__ . ': data_value= ' . $data_value);
        $html_output = '';
        // Start tooltip for options
        $html_output .= '<div class="edm_hover">';
        $html_output .= $data_value[SLP_EDM_TABLE_SLUG];
        $html_output .= '<div class="edm_tooltip edm_tooltip_options">';
        //		$html_output .= '<span>';
        // Get the list of options
        $data_options = $data_value[SLP_EDM_TABLE_OPTIONS];
        
        if ( is_array( $data_options ) ) {
            $html_output .= '<ul>';
            $html_output .= sprintf( '<li>%s => %s</li>', 'ID', $data_value['ID'] );
            $html_output .= sprintf( '<li>%s => %s</li>', 'Field ID', $data_value['field_id'] );
            foreach ( $data_options as $optionKey => $optionValue ) {
                $html_output .= '<li>';
                $html_output .= $optionKey;
                $html_output .= ' => ';
                $html_output .= ( is_array( $optionValue ) ? __( 'array', 'slp-extended-data-manager' ) : $optionValue );
                $html_output .= '</li>';
            }
            $html_output .= '</ul>';
        } else {
            $html_output .= $data_options;
        }
        
        // Close of tooltip
        //		$html_output .= '</span>';
        $html_output .= '</div>';
        $html_output .= '</div>';
        return $html_output;
    }
    
    /**
     * Create the text input for the element
     */
    function get_help_text_html( $data_value = '' )
    {
        //$this->debugMP('msg',__FUNCTION__ . ': data_value= ' . $data_value);
        $html_output = '';
        // Start tooltip for options
        $html_output .= '<div class="edm_hover">';
        // Generate text input for help_text
        $request_name_option = SLP_EDM_ACTION_ELEMENT_UPDATE . '[' . $data_value[SLP_EDM_TABLE_SLUG] . '][' . SLP_EDM_TABLE_HELP_TEXT . ']';
        $html_output .= '<input type="text" class="element-text" name="';
        $html_output .= $request_name_option;
        $html_output .= '" value="';
        $html_output .= $data_value[SLP_EDM_TABLE_HELP_TEXT];
        $html_output .= '">';
        // Show help_text as tooltip
        
        if ( $data_value[SLP_EDM_TABLE_HELP_TEXT] != '' ) {
            $html_output .= '<div class="edm_tooltip edm_tooltip_help_text">';
            $html_output .= $data_value[SLP_EDM_TABLE_HELP_TEXT];
            $html_output .= '</div>';
        }
        
        $html_output .= '</div>';
        return $html_output;
    }
    
    /**
     * Create a nice list of options to show.
     */
    function get_options_html( $data_options = '' )
    {
        $html_output = '';
        $data_label = '[' . SLP_EDM_OPTIONS . ']';
        $data_value = maybe_serialize( $data_options );
        // Create a hidden text element
        $html_output .= '<input type="hidden" name="';
        $html_output .= $data_label;
        $html_output .= '" value="';
        $html_output .= $data_value;
        $html_output .= '">';
        return $html_output;
    }
    
    /**
     * Create a nice list of options to show.
     */
    function get_dropdown_html( $data_options = '', $input_id = '', $selected_value = '' )
    {
        $html_output = '';
        //$this->debugMP('pr',__FUNCTION__ . ': input_id=' . $input_id . ', selected_value=' . $selected_value . ', data_options=', $data_options);
        
        if ( is_array( $data_options ) ) {
            $html_output .= '<select id="' . $input_id . '" name="' . $input_id . '">';
            foreach ( $data_options as $optionKey => $optionValue ) {
                $is_selected = ( $optionKey === $selected_value ? 'selected' : '' );
                $html_output .= '<option value="';
                $html_output .= $optionKey;
                $html_output .= '" ' . $is_selected . '>';
                $html_output .= $optionValue;
                $html_output .= '</option>';
            }
            $html_output .= '</select>';
        } else {
            $html_output = $data_options;
        }
        
        return $html_output;
    }
    
    /**
     * Sort the table elements according to the settings in the _REQUEST.
     */
    function sort_data_elements( $data_elements = '' )
    {
        $table_elements = $data_elements;
        /**
         * This checks for sorting input and sorts the data in our array accordingly.
         *
         */
        if ( !function_exists( 'usort_table_order' ) ) {
            function usort_table_order( $a, $b )
            {
                $orderby = ( !empty($_REQUEST['orderby']) ? sanitize_sql_orderby( $_REQUEST['orderby'] ) : SLP_EDM_TABLE_SLUG );
                //If no sort,  default to tab_slug
                $order = ( !empty($_REQUEST['order']) ? sanitize_text_field( $_REQUEST['order'] ) : 'asc' );
                //If no order, default to asc
                
                if ( $orderby == SLP_EDM_TABLE_ORDER ) {
                    $result = intval( $a[$orderby] ) - intval( $b[$orderby] );
                    //Determine sort order for integers
                } else {
                    $result = strcmp( $a[$orderby], $b[$orderby] );
                    //Determine sort order
                }
                
                return ( $order === 'asc' ? $result : -$result );
                //Send final sort direction to usort
            }
        
        }
        usort( $table_elements, 'usort_table_order' );
        //$this->debugMP('msg',__FUNCTION__ . ': orderby=' . $this->db_orderbyfield . ', order=' . $this->db_orderbydir);
        return $table_elements;
    }
    
    /**
     * Fetch the elements from the database.
     */
    function prepare_items()
    {
        // If there are searches added at a later date
        // this array can have where SQL commands added
        // to filter the return results
        //
        $this->debugMP( 'msg', __FUNCTION__ . ' started.' );
        /**
         * First, lets decide how many records per page to show
         */
        $per_page = $this->slplus->SmartOptions->edm_elements_per_page->value;
        $this->debugMP( 'msg', __FUNCTION__ . ': per_page = ' . $per_page );
        //$per_page = 20;
        // Define the columns to use
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );
        /**
         * Optional. You can handle your bulk actions however you see fit. In this
         * case, we'll handle them within our package just to keep things clean.
         */
        $this->process_bulk_action();
        // Create the data to show in the table
        $table_data_items = $this->edm_admin_elements->get_extended_data_elements( true );
        $table_data_items = $this->sort_data_elements( $table_data_items );
        /**
         * REQUIRED for pagination. Let's figure out what page the user is currently
         * looking at. We'll need this later, so you should always include it in
         * your own package classes.
         */
        $current_page = $this->get_pagenum();
        /**
         * REQUIRED for pagination. Let's check how many items are in our data array.
         * In real-world use, this would be the total number of items in your database,
         * without filtering. We'll need this later, so you should always include it
         * in your own package classes.
         */
        $total_items = count( $table_data_items );
        $per_page = max( $total_items, 1 );
        /**
         * The WP_List_Table class does not handle pagination for us, so we need
         * to ensure that the data is trimmed to only the current page. We can use
         * array_slice() to
         */
        $table_data_items = array_slice( $table_data_items, ($current_page - 1) * $per_page, $per_page );
        /**
         * REQUIRED. Now we can add our *sorted* data to the items property, where
         * it can be used by the rest of the class.
         */
        $this->items = $table_data_items;
        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );
    }
    
    //-------------------------------------
    // Methods : Added
    //-------------------------------------
    /*
     * Set the edm_types array
     *
     */
    function edm_get_types()
    {
        $edm_types = array(
            'text'    => __( 'Text Area', 'slp-extended-data-manager' ),
            'varchar' => __( 'Text Line', 'slp-extended-data-manager' ),
            'boolean' => __( 'Boolean', 'slp-extended-data-manager' ),
            'int'     => __( 'Integer', 'slp-extended-data-manager' ),
        );
        return $edm_types;
    }
    
    /*
     * Set the edm_types array
     *
     */
    function edm_get_display_types()
    {
        $edm_display_types = array(
            ''         => __( 'Default', 'slp-extended-data-manager' ),
            'text'     => __( 'Text Line', 'slp-extended-data-manager' ),
            'textarea' => __( 'Text Area', 'slp-extended-data-manager' ),
            'checkbox' => __( 'Boolean', 'slp-extended-data-manager' ),
            'image'    => __( 'Image', 'slp-extended-data-manager' ),
            'icon'     => __( 'Icon', 'slp-extended-data-manager' ),
            'none'     => __( 'None', 'slp-extended-data-manager' ),
        );
        return $edm_display_types;
    }
    
    /**
     * Simplify the plugin debugMP interface.
     *
     * Typical start of function call: $this->debugMP('msg',__FUNCTION__);
     *
     * @param string $type
     * @param string $hdr
     * @param string $msg
     */
    function debugMP( $type, $hdr, $msg = '' )
    {
        if ( $type === 'msg' && $msg !== '' ) {
            $msg = esc_html( $msg );
        }
        if ( $hdr !== '' ) {
            // Adding __CLASS__ to non-empty hdr
            $hdr = __CLASS__ . '::' . $hdr;
        }
        SLP_Extended_Data_Manager_debugMP(
            $type,
            $hdr,
            $msg,
            NULL,
            NULL,
            true
        );
    }

}