<?php
defined( 'ABSPATH' ) || exit;
class SLP_Settings_import_stats extends SLP_Setting {
	private $text;

	/**
	 * Things we do at the start.
	 */
	protected final function at_startup() {
		$text_slugs = array(
            'auto_refresh'        ,
			'download'            ,
			'geocoding'           ,
			'geocoding_location'  ,
			'geocode_after_import',
			'geocode_in_progress' ,
			'imported'            ,
			'importing'           ,
			'imports'             ,
			'import_in_progress'  ,
			'no_active_geocoding' ,
			'no_active_imports'   ,
			'processing'          ,
			'reading_line'        ,
		);
		$text = SLP_Text::get_instance();
		foreach ( $text_slugs as $slug ) {
			$this->text[ $slug ] = $text->get_text_string( $slug );
		}
	}

	/**
	 * Return a card HTML string for a file.
	 *
	 * @param int $id
	 * @param array $meta
	 *
	 * @return string
	 */
	private function file_card( $id , $meta ) {
		$meta = stripslashes_deep( $meta );
		$meta[ 'card_class' ] = ! empty( $meta[ 'card_class' ] ) ? $meta[ 'card_class' ] : '';

		$media_link    = sprintf( '<a href="%s%s" target="store_locator_plus" class="header_link">%s</a>' , admin_url( 'upload.php?item=' ) , $id, $meta['original_name'] );
		$progress      = sprintf( "%.2f" , ($meta[ 'offset' ]/$meta[ 'size' ])*100 );
		$progress_text = $progress . '%';

		return <<<HTML
			<div data-attachment_id="{$id}" class="import_card {$meta['card_class']}  v-card v-card--outlined  v-sheet v-sheet--tile theme--light">
				<div class="v-card__title">
					{$this->text['importing']} {$media_link}
				</div>
				<div class="v-card__text">			
				      <v-progress-circular
				        :rotate="-90"
				        :size="100"
				        :width="15"
				        :value="pct_complete"
				        color="primary"
				      >
				        {{ pct_complete }}%
				      </v-progress-circular>						               
                    <div class="row">
                        <div class="col-12">
	                        <div class="v-sheet theme--light"> 
	                            {$this->text['geocode_after_import']}
	                        </div>
                        </div>
                    </div>
                    <div class="row">
						<div class="col-5">
	                        <div class="v-sheet theme--light"> 
	                            <strong>{$this->text['reading_line']}</strong>
	                            <div class="current_record stat text-center">{$meta['record']}</div>
	                        </div>
                        </div>
						<div class="col-5">
	                        <div class="v-sheet theme--light"> 
	                            <a href="{$meta['url']}" title="{$this->text['download']}"><span  class="dashicons dashicons-download stat"></span></a>
	                        </div>
                        </div>
                    </div>
				</div>
			</div>
HTML;
	}

	/**
	 * Get file cards.
	$file_cards = array(
	'2949' => array(
	'data_type' => 'location_csv',
	'processed' => false,
	'record' => 190,
	'offset' => 37068,
	'original_name' => 'ben_test_geocode_680.csv',
	'size' => 86016,
	'url' => "http:\/\/wpslp.test\/wp-content\/uploads\/2017\/11\/bennett_locations_2017_oct_31-1-13.csv",
	'filename' => "\/2017\/11\/bennett_locations_2017_oct_31-1-13.csv"
	'local_file' => "\/srv\/www\/wpslp\/public_html\/wp-content\/uploads\/2017\/11\/bennett_locations_2017_oct_31-1-13.csv",
	),
	);
	 *
	 */
	private function get_file_cards() {
		/**
		 * @var SLP_Power_Locations_Import $obj
		 */
		$obj = SLP_Power_Locations_Import::get_instance();
		$list = $obj->get_active_list();
		$file_cards = $list['data'];

/*
 * A test card...
$file_cards[ '9999' ][ 'meta' ] = array(
			'data_type' => 'location_csv',
			'processed' => false,
			'record' => 1237,
			'offset' => 80018,
			'original_name' => 'ben_test_geocode_680.csv',
			'size' => 86016,
			'url' => "http:\/\/wpslp.test\/wp-content\/uploads\/2017\/11\/bennett_locations_2017_oct_31-1-13.csv",
			'filename' => "\/2017\/11\/bennett_locations_2017_oct_31-1-13.csv"
        );
*/

        // The hidden empty card
		$file_cards[ '0' ][ 'meta' ] = array(
		    'card_class' => 'hidden',
			'data_type' => 'location_csv',
			'processed' => false,
			'record' => 0,
			'offset' => 0,
			'original_name' => 'new import',
			'size' => 100,
			'url' => '.',
			'filename' => ''
		);

		// Build a status card for each active import.
		//
        $cards = array();
        foreach ( $file_cards as $id => $data ) {

        	// File Import Processing Are Real-Time Not Scheduled
	        //
        	if ( empty( $data[ 'schedule' ] ) && ( empty( $data[ 'action'] ) || ( $data[ 'action' ] !== 'process_csv' ) ) ) {
		        // Empty meta size, file likely was deleted before finished processing.
		        if ( empty( $data['meta']['size'] ) || ! $data['meta']['url'] ) {
		            $obj->stop_processing( $id );
			        continue;
		        }
	        }
        	if ( ! empty( $data['meta']['original_name'])) {
		        $cards[] = $this->file_card( $id, $data['meta'] );
	        }
        }

        return join( '', $cards );
	}

	/**
	 * Get geocode card
	 * @return string
	 */
	private function get_geocode_card() {
	    global $slplus;
		$obj = SLP_Power_Locations_Geocode::get_instance();
		$list = $obj->get_active_list();

		$progress_bars = '';
		$display_class = empty( $list['data']['jobs'] ) ? 'hidden' : '';

		$list['data']['jobs'][] = array( 'max' => 0 , 'start_uncoded' => 0 );

		// Build a status card for each active import.
		//
        $description = $this->text['geocode_in_progress'];
        $cards = array();
        foreach ( $list['data']['jobs'] as $job ) {
            $pct_complete      = empty( $job['max'] ) ? '0' : sprintf( "%.2f" , (($job['start_uncoded'] - $list['data']['current_uncoded'])/$job['start_uncoded'])*100 );
            $bar_display = empty( $pct_complete ) ? 'hidden' : '';
            $progress_bars .= <<<PROGRESS_BAR
                <div id="geocode_{$job['max']}" class="progress {$bar_display}" role="progressbar" tabindex="0" aria-valuenow="{$pct_complete}" aria-valuemin="0" aria-valuetext="{$pct_complete} %" aria-valuemax="100">
                  <span class="progress-meter" style="width: {$pct_complete}%">
                    <p class="progress-meter-text">{$pct_complete}%</p>
                  </span>
                </div>
PROGRESS_BAR;
        }

		return <<<HTML
			<div class="geocode_card {$display_class} v-card v-card--outlined v-sheet v-sheet--tile theme--light">
				<div class="v-card__title">
					{$this->text['geocoding']}
					<span title="{$this->text['auto_refresh']}" class="reload_icon dashicons dashicons-image-rotate"></span>
				</div>
				<div class="v-card__text">
                    <div class="row">
						<div class="col-12">
							{$this->text['geocode_in_progress']}						
						</div>
					</div>
                    <div class="row">
						<div class="col-12">
	                        <div class="v-sheet theme--light"> 
								<strong>{$this->text['geocoding_location']}</strong>
                                <p class="current_record stat text-center">{$list['data']['current_location']}</div>
                            </div>
                        </div>
                    </div>
				</div>
			</div>
HTML;
	}

	/**
	 * Just the content - no standard Settings wrappers here.
	 */
	protected function wrap_in_default_html() {
		?>
			<div id="csv_import_status" class="container container--fluid">
				<div class="row align-start justify-center">
					<div class="col-5">
						<?= $this->get_file_cards() ?>
					</div>
					<div class="col-5">
						<?= $this->get_geocode_card() ?>
					</div>
				</div>
			</div>
		<?php
	}

}
