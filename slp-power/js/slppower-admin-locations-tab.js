/**
 * Power JS for Admin Locations Tab
 * 2209.12.01
 */
/* global jQuery, Vuetify, Vue, SLP_ADMIN, slp_Admin_Filter, SLP_Location_Manager, AdminUI, ajaxurl, location_import */
const SLPPOWER_ADMIN_LOCATIONS = {
    state: {
        current_message: '',
        upload_div_id: undefined,
        state_list_populated: false,
        country_list_populated: false,
    }
};

/**
 * Location Filters.
 */
const slp_power_filters = function () {
    /**
     * Initialize.
     */
    this.initialize = function () {
        jQuery('#state_filter').on('click', this.get_state_list);
        jQuery('#country_filter').on('click', this.get_country_list);
    };

    /**
     * Get the list of countries and populate the drop down.
     */
    this.get_country_list = function () {
        if (SLPPOWER_ADMIN_LOCATIONS.state.country_list_populated) {
            return;
        }

        jQuery('div#country_filter_spinner').addClass('is-active');

        var post_data = {};
        post_data['action'] = 'slp_get_country_list';

        var ajax_settings = {};
        ajax_settings['url'] = ajaxurl;
        ajax_settings['method'] = 'POST';
        ajax_settings['dataType'] = 'jsonp';
        ajax_settings['data'] = post_data;

        var request = jQuery.ajax(ajax_settings);

        request.always(function (response) {
            SLPPOWER_ADMIN_LOCATIONS.filters.populate_country_list(response.responseText);
        });
    };

    /**
     *
     * @param response
     */
    this.populate_country_list = function (response) {
        jQuery('div#country_filter_spinner').removeClass('is-active');

        var json_response = JSON.parse(response);
        if (json_response.success) {
            var country_dropdown = jQuery('select#country_filter');
            for (var cnt = 0; cnt < json_response.data.count; cnt++) {
                country_dropdown.append(jQuery('<option />').attr('value', json_response.data.states[cnt]).text(json_response.data.states[cnt]));
            }

            SLPPOWER_ADMIN_LOCATIONS.state.country_list_populated = true;
        } else {
            SLPPOWER_ADMIN_LOCATIONS.state.state_list_populated = false;
        }
    };

    /**
     * Get the list of states and populate the drop down.
     */
    this.get_state_list = function () {
        if (SLPPOWER_ADMIN_LOCATIONS.state.state_list_populated) {
            return;
        }

        jQuery('div#state_filter_spinner').addClass('is-active');

        var post_data = {};
        post_data['action'] = 'slp_get_state_list';

        var ajax_settings = {};
        ajax_settings['url'] = ajaxurl;
        ajax_settings['method'] = 'POST';
        ajax_settings['dataType'] = 'jsonp';
        ajax_settings['data'] = post_data;

        var request = jQuery.ajax(ajax_settings);

        request.always(function (response) {
            SLPPOWER_ADMIN_LOCATIONS.filters.populate_state_list(response.responseText);
        });
    };

    /**
     *
     * @param response
     */
    this.populate_state_list = function (response) {
        jQuery('div#state_filter_spinner').removeClass('is-active');

        var json_response = JSON.parse(response);
        if (json_response.success) {
            var state_dropdown = jQuery('select#state_filter');
            for (var cnt = 0; cnt < json_response.data.count; cnt++) {
                state_dropdown.append(jQuery('<option />').attr('value', json_response.data.states[cnt]).text(json_response.data.states[cnt]));
            }

            SLPPOWER_ADMIN_LOCATIONS.state.state_list_populated = true;
        } else {
            SLPPOWER_ADMIN_LOCATIONS.state.state_list_populated = false;
        }
    }
};

/**
 * Location Messages.
 */
const slp_power_messages = function () {

    /**
     * Clear the import messages list.
     */
    this.clear_import_messages = function () {
        jQuery('.import_message_block').empty();

        var post_data = {};
        post_data['action'] = 'slp_clear_import_messages';
        jQuery.post(ajaxurl, post_data, this.process_clear_import_messages_response);
    };

    /**
     * Handle the clear response.
     *
     * @param response
     */
    this.process_clear_import_messages_response = function (response) {
        if (response !== 'ok') {
            jQuery('.import_message_block').html('<span class="clear failed">***</span>');
        }
    };
};

/**
 * Power locations setup.
 */
const slp_power_locations = function () {

    /**
     * Initialize our Power ups.
     */
    this.initialize = function () {
        jQuery('a[data-action="create_page"]').on('click', this.create_page);
        jQuery('#import_button').on('click', function () {
            AdminUI.doAction('import')
        });
    };

    /**
     * Make it so.   Create the page with AJAX (maybe REST someday) magic.
     * @param event
     */
    this.create_page = function () {
        var data = {};
        data['action'] = 'slp_create_page';
        data['id'] = jQuery(this).attr('data-id');
        data['screenoptionnonce'] = jQuery('#screenoptionnonce').val();

        var messages = {
            'message_ok': 'SEO page created.',
            'message_info': 'Could not create SEO page.',
            'message_failure': 'Could not communicate with the server.',
        };

        // let tr = jQuery( '#location-' + data['location_id'] );

        SLP_ADMIN.ajax.post(data, messages);
    }
};

/**
 * Infobox class.
 */
const slp_power_infobox = function () {
    /**
     * Update the info box.
     */
    this.update = function (json_data) {

        // Show alerts returned in json data.
        //
        if (json_data.alert) {
            alert(json_data.alert);
            return;
        }

        // No message? Leave.
        //
        if (!json_data.message) {
            return;
        } else {
            SLPPOWER_ADMIN_LOCATIONS.state.current_message = json_data.message;
        }
        jQuery('#slp-power_messages').append(this.create_string_message_div());
        jQuery('#slp-power_message_board').show();
    } ,

        /**
         * Create the message string div.
         */
        this.create_string_message_div = function () {
            return (
                '<div class="slp-power_message">' +
                SLPPOWER_ADMIN_LOCATIONS.state.current_message +
                '</div>'
            );
        }
};

/**
 * Async Uploader
 */
const slp_power_location_import = function () {
    var csv_file = jQuery('#csv_file');
    var geo_card = jQuery('.geocode_card');

    var geo_status_pump;

    // Import Status Vue Applet
    var import_status_app = new Vue({
        vuetify: new Vuetify(),
        el: '#wpcsl_settings_group-status_bar',
        data: {
            pct_complete: 0,
        }
    });

    this.initialize = function () {
        var import_card = jQuery('div.import_card');

        /**
         * Progress update on card click...
         */
        import_card.on('click', this.get_import_update);
        geo_card.on('click', this.get_geocode_update);

        /**
         * Progress updated called by code...
         */
        import_card.on('get_update', this.get_import_update);
        geo_card.on('get_update', this.get_geocode_update);

        /**
         * On CSV File Input Change
         */
        csv_file.on('change', function (e) {
            e.preventDefault();

            // Once we start an import any clicking of the Locations | List menu item should reload.
            jQuery('#wpcsl-option-current_locations_sidemenu').on('click', function () {
                window.location = jQuery('#locationForm').attr('action');
            });

            if (!csv_file[0].files[0].name) return;

            let formData = new FormData();

            formData.append('data_type', 'location_csv');
            formData.append('action', 'upload-attachment');
            formData.append('async-upload', csv_file[0].files[0]);
            formData.append('name', csv_file[0].files[0].name);
            formData.append('_wpnonce', location_import.nonce);

            let uploaded_file;

            this.processUpload = function (resp) {
                uploaded_file = resp.data.filename;

                // Server reports success
                if (resp.success) {
                    const att_id = resp.data.id;

                    jQuery.get(location_import.cron_url);

                    import_card.attr('data-attachment_id', att_id);

                    const card_header = import_card.find('.header_link');
                    card_header.text(uploaded_file);
                    card_header.attr('href', resp.data.link);

                    import_card.removeClass('hidden');
                    import_card.show();
                    csv_file.val('');

                    AdminUI.notifications.remove_all();

                    import_card.trigger('get_update');

                    // Server reports failed
                } else {
                    import_status_app.pct_complete = 0;
                    AdminUI.notifications.add('info', 'Uploading ' + uploaded_file + ' failed.');
                    if (resp.data.message) {
                        AdminUI.notifications.add('error', resp.data.message);
                    }
                }
            };


            /**
             * Post ajax to WP async-uploader
             */
            jQuery.ajax({
                url: location_import.upload_url,
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                type: 'POST',

                /**
                 * Upload Success
                 *
                 * @param resp
                 */
                success: this.processUpload,

                /**
                 * Before Sending File...
                 */
                beforeSend: function () {
                    SLPPOWER_ADMIN_LOCATIONS.state.upload_div_id = AdminUI.notifications.add('info', 'Uploading ' + csv_file[0].files[0].name + '&hellip;');
                    import_status_app.pct_complete = 0;
                },

                /**
                 * XHR processing
                 *
                 * @returns {*}
                 */
                xhr: function () {
                    var myXhr = jQuery.ajaxSettings.xhr();

                    if (myXhr.upload) {
                        myXhr.upload.addEventListener('progress', function (e) {
                            if (e.lengthComputable) {
                                import_status_app.pct_complete = (e.loaded / e.total) * 100;
                            }
                        }, false);
                    }
                    return myXhr;
                }
            });
        });

    };

    /**
     * Geocode Card Update (on click of card)
     */
    this.get_geocode_update = function (obj) {
        if (geo_status_pump) return;

        var card = jQuery(obj.currentTarget);
        geo_card.find('.reload_icon').fadeOut(300);
        geo_status_pump = setInterval(function () {
            jQuery.getJSON(location_import.rest_geocode_url)
                .done(function (resp) {
                    var all_encoded = true;
                    jQuery.each(resp.data.jobs, function (i, item) {
                        var pct_complete = (((item.start_uncoded - resp.data.current_uncoded) / item.start_uncoded) * 100).toFixed(2);
                        let progress_bar = card.find('#geocode_' + item.max);
                        if (progress_bar.length < 1) {
                            progress_bar = card.find('#geocode_0');
                            progress_bar.attr('id', 'geocode_' + item.max);
                            progress_bar.removeClass('hidden');
                        }
                        progress_bar.attr('aria-valuenow', pct_complete);
                        progress_bar.attr('aria-valuetext', pct_complete + '%');
                        progress_bar.find('.progress-meter').css('width', pct_complete + '%');
                        progress_bar.find('.progress-meter-text').text(pct_complete + '%');
                        if (all_encoded && (pct_complete < 100)) {
                            all_encoded = false;
                        }
                    });


                    // Update current record
                    if (resp.data.current_location !== '') {
                        geo_card.find('.current_record').html(resp.data.current_location);
                    }

                    // Clear this out.
                    if ((resp.data.current_uncoded <= 0) || all_encoded) {
                        clearInterval(geo_status_pump);
                        geo_card.fadeOut(3000);
                    }
                })
                .fail(function () {
                    clearInterval(geo_status_pump);
                    console.log('geo_pump request failed');
                })
            ;
        }, 1000);
    };

    /**
     * Import Card Update (on click or trigger call)
     */
    this.get_import_update = function (obj) {
        const card = jQuery(obj.currentTarget);
        const att_id = card.attr('data-attachment_id');

        const import_status_pump = setInterval(function () {
            jQuery.getJSON(location_import.rest_imports_url)
                .done(function (resp) {
                    let pct_complete = 100;
                    let record = 'last';
                    if (resp.data[att_id]) {
                        var data = resp.data[att_id];
                        pct_complete = ((data.meta.offset / data.meta.size) * 100).toFixed(2);
                        record = data.meta.record;
                    }
                    card.find('.current_record').html(record);
                    import_status_app.pct_complete = pct_complete;

                    if (pct_complete == 100) {
                        clearInterval(import_status_pump);
                        card.attr('data-attachment_id', 0);
                        //card.fadeOut( 3000 );

                        geo_card.trigger('get_update');
                        geo_card.fadeIn(2000);

                    }
                })
                .fail(function () {
                    clearInterval(import_status_pump);
                    console.log('progresspump request failed');
                })
            ;
        }, 1000);
    };
};

/**
 * Location Edit Loader
 */
const slp_power_location_edit = function () {
    /**
     * Connect to the SLP location editor form loader
     */
    this.setup_subscriptions = function () {
        slp_Admin_Filter('location_edit_init').subscribe(this.setup_edit); // init location edit for Vue
        slp_Admin_Filter('exportBulkAction').subscribe(this.exportLocations); // export locations as single page application
    };

    /**
     * Export locations without leaving the locations page
     */
    this.exportLocations = function () {
        let all = jQuery('#locationForm input[name="apply_to_all"]').val();
        let ajax_data = {
            action: 'slp_download_locations_csv',
            filename: 'locations',
            formdata: jQuery(
                '#locationForm input:not([data-field="linked_postid"])').serialize()
        };

        if (!all) {
            ajax_data.sl_id = [];
            jQuery('#locationForm input[name="sl_id[]"]:checked').each(function () {
                ajax_data.sl_id.push(jQuery(this).val());
            });
        }

        jQuery('#power_csv_download').attr('src', ajaxurl + '?' + jQuery.param(ajax_data));
    }

    /**
     * Setup the form for editing a location
     */
    this.setup_edit = function (location_edit_options) {

        // Category Checklist
        //

        // Uncheck All
        location_edit_options.form_div.find('input[name="tax_input[stores][]"]').each(function () {
            jQuery(this).prop('checked', false);
        });

        // Add mode - done.
        if (SLP_Location_Manager.vue.update_app.act === 'add') {
            return;
        }

        // Edit mode - check all location cats
        location_edit_options.table_row.find('a.category_edit_link').each(function () {
            location_edit_options.form_div.find('#in-stores-' + jQuery(this).attr('data-value')).prop('checked', true);

        });
    };
};

// Document Ready
jQuery(document).ready(
    function () {
        SLPPOWER_ADMIN_LOCATIONS.power_up = new slp_power_locations();
        SLPPOWER_ADMIN_LOCATIONS.power_up.initialize();

        SLPPOWER_ADMIN_LOCATIONS.filters = new slp_power_filters();
        SLPPOWER_ADMIN_LOCATIONS.filters.initialize();
        SLPPOWER_ADMIN_LOCATIONS.messages = new slp_power_messages();

        // If we don't have SLP 4.9.3 quick_save support...
        if (typeof SLP_ADMIN.options.quick_save === 'undefined') {
            jQuery('.quick_save').find(':input').on('change', function (e) {
                SLP_ADMIN.options.change_option(e.currentTarget);
            });
        }

        // Process incoming request actions
        //
        if (typeof location_import !== 'undefined') {
            switch (location_import.action) {
                // Export, locally hosted.
                //
                case 'export_local':
                    var infobox = new slp_power_infobox();
                    infobox.update(
                        {'message': location_import.download_file_message});
                    break;
            }
        }

        // On Import Tab Click
        jQuery('#wpcsl-option-import_sidemenu').on('click', function () {
                if (!SLPPOWER_ADMIN_LOCATIONS.upload_ux) {
                    SLPPOWER_ADMIN_LOCATIONS.upload_ux = new slp_power_location_import();
                    SLPPOWER_ADMIN_LOCATIONS.upload_ux.initialize();
                }
            }
        )

        SLPPOWER_ADMIN_LOCATIONS.location_editor = new slp_power_location_edit();
        SLPPOWER_ADMIN_LOCATIONS.location_editor.setup_subscriptions();
    }
);
