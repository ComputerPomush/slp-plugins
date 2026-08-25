<?php
if ( ! class_exists( 'SLP_Extended_Data_Manager_Admin_Elements' ) ) {

	/**
	 * The things that modify the Admin / Elements.
	 *
	 * @package StoreLocatorPlus\SLP_Extended_Data_Manager\Admin\Elements
	 * @author DeBAAT <slp-edm@de-baat.nl>
	 * @copyright 2022 De B.A.A.T. - Charleston Software Associates, LLC
	 *
	 * @property        SLP_Extended_Data_Manager                     $addon
	 * @property        SLP_Extended_Data_Manager_Admin               $admin               The admin object for this addon.
	 * @property        array                                       $edm_elements_table  The table with Elements.
	 */
	class SLP_Extended_Data_Manager_Admin_Elements  extends SLPlus_BaseClass_Object {

		public  $addon;
		public  $admin;
		public  $edm_elements_table;
		// public  $edm_elements;
		// public  $cur_element_object;

        /**
         * Things we do at the start.
         */
        public function initialize() {
            $this->add_hooks_and_filters();

			// $this->edm_elements  = $this->admin->edm_elements;
			$this->edm_elements_table = false;
        }

        /**
         * WP and SLP hooks and filters.
         */
        private function add_hooks_and_filters() {
			$this->debugMP('msg', __FUNCTION__ . '-e started.');

			// Admin skinning and scripts
			//
//			add_filter('wpcsl_admin_slugs'                           ,array($this,'filter_AddOurAdminSlugEDM'                       )      );
//			add_action('slp_manage_locations_action'                 ,array($this,'action_ManageLocationsProcessingEDM'             )      );
//			add_action('slp_manage_elements_action'                  ,array($this,'action_ManageLocationsProcessingEDM'             )      );

			// Manage Location Fields
			// - tweak the add/edit form
			// - tweak the manage locations column headers
			// - tweak the manage locations column data
			//
//			add_filter('slp_column_data'                             ,array($this,'filter_AddFieldDataToManageLocationsEDM'         ),190, 3);
//			add_filter('slp_column_data'                             ,array($this,'filter_slp_column_data_elements'                 ),190, 3);

			// Manage Locations Interface
			//
//			add_filter('slp_locations_manage_filters'                ,array($this,'filter_LocationsFiltersEDM'                      ), 50   );
//			add_filter('slp_elements_manage_filters'                 ,array($this,'filter_ElementsFiltersEDM'                       ), 50   );

        }

		//-------------------------------------
		// Methods : Custom : Actions
		//-------------------------------------

		/**
		 * Render the extra fields on the manage location table.
		 *
		 * SLP Filter: slp_column_data
		 *
		 * @param string $theData  - the option_value field data from the database
		 * @param string $theField - the name of the field from the database (should be sl_option_value)
		 * @param string $theLabel - the column label for this column (should be 'Categories')
		 * @return type
		 */
		function filter_slp_column_data_elements($theData,$theField,$theLabel) {
			$this->debugMP('pr',__FUNCTION__ . ' theField = ' . $theField . ' theLabel = ' . $theLabel . ', theData = ', $theData);

			return $theData;
		}

		/**
		 * Get a table of extended data elements.
		 */
		function get_extended_data_elements( $force = true ) {

			// Check cache with edm_elements_table
			if (($this->edm_elements_table === false) || $force ) {

				// Create cache with the data to show in the table
				$table_data = array();
				$col_data = $this->slplus->database->extension->get_cols($force);
				$data_item = array();
				$this->debugMP('msg',__FUNCTION__ . ': count= ' . count($col_data) );
				//$this->debugMP('pr',__FUNCTION__ . ': count= ' . count($col_data) . ', col_data= ', $col_data);

				// Process the col_data found into the table_data
				foreach ($col_data as $key => $curCol) {
					$data_item['ID']                       = $curCol->id;
					$data_item[SLP_EDM_TABLE_FIELD_ID]     = $curCol->field_id;
					$data_item[SLP_EDM_TABLE_LABEL]        = $curCol->label;
					$data_item[SLP_EDM_TABLE_SLUG]         = $curCol->slug;
					$data_item[SLP_EDM_TABLE_TYPE]         = $curCol->type;
					$data_item[SLP_EDM_OPTIONS]            = maybe_unserialize($curCol->options);
					$data_item[SLP_EDM_TABLE_SHOW]         = (isset($data_item[SLP_EDM_OPTIONS][SLP_EDM_OPTION_SHOW])         ? $data_item[SLP_EDM_OPTIONS][SLP_EDM_OPTION_SHOW]         : '');
					$data_item[SLP_EDM_TABLE_ORDER]        = (isset($data_item[SLP_EDM_OPTIONS][SLP_EDM_OPTION_ORDER])        ? $data_item[SLP_EDM_OPTIONS][SLP_EDM_OPTION_ORDER]        : '');
					$data_item[SLP_EDM_TABLE_DISPLAY_TYPE] = (isset($data_item[SLP_EDM_OPTIONS][SLP_EDM_OPTION_DISPLAY_TYPE]) ? $data_item[SLP_EDM_OPTIONS][SLP_EDM_OPTION_DISPLAY_TYPE] : '');
					$data_item[SLP_EDM_TABLE_HELP_TEXT]    = (isset($data_item[SLP_EDM_OPTIONS][SLP_EDM_OPTION_HELP_TEXT])    ? $data_item[SLP_EDM_OPTIONS][SLP_EDM_OPTION_HELP_TEXT]    : '');

					$table_data[$curCol->slug] = $data_item;
				}

				$this->edm_elements_table = $table_data;
			}

			//$this->debugMP('pr',__FUNCTION__ . ': count= ' . count($table_data) . ': tableData= ',$table_data);
			return $this->edm_elements_table;
		}

		/**
		 * Delete the Extended Data Element
		 *
		 * @params string $edmSlug the slug of the extended data element to delete
		 * @params string $edmData the data of the extended data element to delete
		 * @return boolean true when success
		 */
		function slp_edm_element_delete($edmSlug, $edmData) {

			// Validate access and parameters
			if ($edmSlug == '') { return false; }

			// Delete the extended data from the database
			$this->debugMP('pr',__FUNCTION__ . ' edmSlug = ' . $edmSlug . ', edmData = ', $edmData);
			$this->slplus->database->extension->remove_field( $edmSlug, array('slug'=>  $edmSlug), 'immediate');

			return true;
		}

		/**
		 * Set the 'show' option of the Extended Data Element
		 *
		 * @params string $edmSlug the slug of the extended data element to update
		 * @params string $edmShow whether to show or hide the extended data element
		 * @return boolean true when success
		 */
		function slp_edm_element_show_hide($edmSlug, $edmShow = 'true') {
			$this->debugMP('msg',__FUNCTION__ . ' edmSlug = ' . $edmSlug . ', edmShow = ' . $edmShow);

			// Validate access and parameters
			if ($edmSlug == '') { return false; }

			// Set the value for the extended data option
			$element_options = array();
			$element_options[SLP_EDM_OPTION_SLUG] = $edmSlug;
			$element_options[SLP_EDM_OPTION_SHOW] = $edmShow;
			//$this->debugMP('pr',__FUNCTION__ . ' edmSlug = ' . $edmSlug . ', element_options = ', $element_options);

			// Update the extended data in the database
			if (!$this->slplus->database->extension->update_field( false, false, $element_options)) {
				$dataWritten = false;
				$this->debugMP('msg',__FUNCTION__ . ' Update extended data for ' . $edmSlug . 'DID NOT update core data. ');
			}

			return true;
		}

		/**
		 * Set the 'order' option of the Extended Data Element
		 *
		 * @params string $edmSlug the slug of the extended data element to update
		 * @params string $edmData the data of the extended data element to update
		 * @params string $edmOptionKey the option key that should be updated
		 * @params string $edmOptionValue the option value that should be updated
		 * @return boolean true when success
		 */
		function slp_edm_element_update($edmSlug, $edmData, $edmOptionKey = '', $edmOptionValue = '') {

			// Validate access and parameters
			if ($edmSlug      == '') { return false; }
			if ($edmData      == '') { return false; }

			// Get the field data to alter
			$this->debugMP('pr',__FUNCTION__ . ' edmSlug = ' . $edmSlug . ', edmData = ', $edmData);

			// Set the value for the extended data options
			$edmOptions = array();
			if (isset($edmData[SLP_EDM_OPTIONS])) {
				$edmOptions = maybe_unserialize($edmData[SLP_EDM_OPTIONS]);
			}
			$edmOptions['slug']  = $edmSlug;
			if (!empty($edmOptionKey)) {
				$edmOptions[$edmOptionKey] = $edmOptionValue;
			}

			// Remove empty values from the set of options
			foreach ($edmOptions as $key => $value) {
				if ($value == '') {
					unset($edmOptions[$key]);
				}
			}

			// Build new data to store
			$edmLabel = isset($edmData[SLP_EDM_TABLE_LABEL]) ? $edmData[SLP_EDM_TABLE_LABEL] : false;
			$edmType  = isset($edmData[SLP_EDM_TABLE_TYPE])  ? $edmData[SLP_EDM_TABLE_TYPE]  : false;

			// Update the extended data in the database
			if (!$this->slplus->database->extension->update_field( $edmLabel, $edmType, $edmOptions)) {
				$dataWritten = false;
				$this->debugMP('msg','',"Update extended data for {$edmSlug} DID NOT update core data.");
			}

			return true;
		}

		/**
		 * Add an element.
		 */
		function add_Element() {
			$this->debugMP('msg', __FUNCTION__ . ' Started!' );

		}

		/**
		 * Save an element.
		 */
		function save_Element() {
			$this->debugMP('msg', __FUNCTION__ . ' Started!' );

			if (!isset($_REQUEST['element'])) {
				$this->debugMP('msg',__FUNCTION__ . " element # _REQUEST[element] NOT set so do not process!");
			}

			$this->debugMP('msg',__FUNCTION__ . " element # {$_REQUEST['element']}");

			// Ensure there is a cur_element_object object
			// $this->cur_element_object = $this->edm_elements->get_cur_element_object();
			// $this->cur_element_object->set_PropertiesViaDB($_REQUEST['element']);

		}

		/**
		 * Delete an element.
		 */
		function delete_Element() {
			$this->debugMP('msg', __FUNCTION__ . ' Started!' );

			if (!isset($_REQUEST['element'])) {
				$this->debugMP('msg',__FUNCTION__ . " element # _REQUEST[element] NOT set so do not process!");
			}

			// Make a list of elements to delete
			// $elementList = is_array( $_REQUEST['element'] ) ? $_REQUEST['element'] : array( $_REQUEST['element'] );
			// $this->debugMP('pr', __FUNCTION__ . " Delete Action for elementList:", $elementList);
			// foreach ($elementList as $elementID) {
				// // Delete the cur_element_object object by elementID
				// $this->delete_Element_by_ElementID( $elementID );
			// }

		}

		/**
		 * Delete a element.
		 */
		function delete_Element_by_ElementID( $elementID = false ) {

			if ( $elementID == false ) {
				$this->debugMP('msg',__FUNCTION__ . " element # elementID NOT set so do not process!");
			}

			$this->debugMP('msg',__FUNCTION__ . " element # {$elementID}");

			// Ensure there is a cur_element_object object to delete
			// $this->cur_element_object = $this->edm_elements->get_cur_element_object();
			// if ( $this->cur_element_object ) {
				// $this->cur_element_object->set_PropertiesViaDB($elementID);
				// $this->cur_element_object->debugProperties();
				// $this->cur_element_object->DeletePermanently();
			// }

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
		function debugMP($type,$hdr,$msg='') {
			if (($type === 'msg') && ($msg!=='')) {
				$msg = esc_html($msg);
			}
			if (($hdr!=='')) {   // Adding __CLASS__ to non-empty hdr
				$hdr = __CLASS__ . '::' . $hdr;
			}
			SLP_Extended_Data_Manager_debugMP($type,$hdr,$msg,NULL,NULL,true);
		}

	}

}