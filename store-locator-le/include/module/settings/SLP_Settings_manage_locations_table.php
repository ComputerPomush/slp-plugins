<?php

class SLP_Settings_manage_locations_table extends SLP_Setting {
	public function display() {
		SLP_Admin_Locations::get_instance()->display_manage_locations_table();
	}
}