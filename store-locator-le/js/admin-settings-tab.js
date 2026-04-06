/**
 * SLP Admin Settings Tab
 * @version 2303.03.01
 */
/* globals jQuery, slp_data, AdminUI, SLP_Admin_Settings_Help, SLP_ADMIN */

/**
 * Setting Helpers
 */
const slp_setting_helper = function () {
    let turned_on = {},
        upload_div_id,
        formData = new FormData(),
        settings_file = jQuery('#settings_file'),
        _this = this;

    /**
     * Downloads a file.
     *
     * Snarfed from WP Core wp-includes/js/dist/list-reusable-blocks.js
     *
     * @param {string} fileName    File Name.
     * @param {string} content     File Content.
     * @param {string} contentType File mime type.
     */
    this.download = function (fileName, content, contentType) {
        var file = new window.Blob([content], {
            type: contentType,
        });
        if (window.navigator.msSaveOrOpenBlob) {
            window.navigator.msSaveOrOpenBlob(file, fileName);
        } else {
            var a = document.createElement('a');
            a.href = URL.createObjectURL(file);
            a.download = fileName;
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    };

    /*
     * Initialize our helpers.
     */
    this.initialize = function () {
        jQuery('div[data-related_to]').on('mouseenter',SLP_ADMIN.helper.related_on).on('mouseleave',SLP_ADMIN.helper.related_off);
        jQuery('a#export_settings').on('click', this.export_settings);
        settings_file.on('change', this.upload_settings);
    };

    /**
     * Export SLP Settings.
     */
    this.export_settings = function () {
        jQuery
            .ajax({
                method: 'GET',
                url: slp_data.rest_path + 'options/all/',
                dataType: 'json',
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                console.log(jqXHR, textStatus, errorThrown);
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    jQuery('#' + _this.divID + ' .map-messages')
                        .addClass('error')
                        .prepend(jqXHR.responseJSON.message)
                        .show();
                }
            })
            .done(function (response) {
                if (response.state !== 200) {
                    console.log('SLP settings export error');
                }
                _this.download(
                    'slp_settings.json',
                    JSON.stringify(response),
                    'application/json'
                );
            });
    };

    /**
     * Import Settings
     *
     * @param e
     */
    this.import_settings = function (data) {
        if (!data.url) return;

        formData.append('data_type', 'settings_json');
        formData.append('action', 'import-settings');
        formData.append('file-meta', JSON.stringify(data));
        formData.append('_wpnonce', slp_data.import_nonce);

        /**
         * Post ajax to WP async-uploader
         */
        jQuery.ajax({
            url: slp_data.rest_path + 'options/import/',
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
            success: function (resp) {
                if (resp.success) {
                    AdminUI.notifications.remove_all();
                    AdminUI.notifications.add(
                        'info',
                        'Import of ' + data.filename + completed
                    );
                } else {
                    AdminUI.notifications.add(
                        'info',
                        'Uploading ' + data.filename + ' failed.'
                    );
                    if (resp.data.message) {
                        AdminUI.notifications.add('error', resp.data.message);
                    }
                }
            },

            /**
             * Before Sending File...
             */
            beforeSend: function () {
                upload_div_id = AdminUI.notifications.add(
                    'info',
                    'Importing ' + data.filename + '&hellip;'
                );
            },

            /**
             *
             * @returns {*}
             */
            xhr: function () {
                var myXhr = jQuery.ajaxSettings.xhr();

                if (myXhr.upload) {
                    myXhr.upload.addEventListener(
                        'progress',
                        function (e) {
                            if (e.lengthComputable) {
                                var perc = (e.loaded / e.total) * 100;
                                perc = perc.toFixed(2);
                                AdminUI.notifications.update(
                                    upload_div_id,
                                    'Server received ' + perc + '%'
                                );
                            }
                        },
                        false
                    );
                }

                return myXhr;
            },
        });
    };

    /**
     * Upload SLP Settings.
     */
    this.upload_settings = function (e) {
        e.preventDefault();
        if (!settings_file[0].files[0].name) return;

        formData.append('data_type', 'settings_json');
        formData.append('action', 'upload-attachment');
        formData.append('async-upload', settings_file[0].files[0]);
        formData.append('name', settings_file[0].files[0].name);
        formData.append('_wpnonce', slp_data.nonce);

        /**
         * Post ajax to WP async-uploader
         */
        jQuery.ajax({
            url: slp_data.upload_url,
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
            success: function (resp) {
                if (resp.success) {
                    AdminUI.notifications.remove_all();
                    AdminUI.notifications.add(
                        'info',
                        'Upload complete to ' + resp.data.filename
                    );
                    _this.import_settings(resp.data);
                } else {
                    AdminUI.notifications.add(
                        'info',
                        'Uploading ' + resp.data.filename + ' failed.'
                    );
                    if (resp.data.message) {
                        AdminUI.notifications.add('error', resp.data.message);
                    }
                }
            },

            /**
             * Upload Error
             *
             * @param resp
             */
            error: function (jqXHR, textStatus, errorThrown) {
                AdminUI.notifications.add('info', 'Uploading failed.');
                AdminUI.notifications.add('error', textStatus);
                AdminUI.notifications.add('error', errorThrown);
                AdminUI.notifications.add('error', jqXHR.responseText);
            },

            /**
             * Before Sending File...
             */
            beforeSend: function () {
                upload_div_id = AdminUI.notifications.add(
                    'info',
                    'Uploading ' + settings_file[0].files[0].name + '&hellip;'
                );
            },
        });
    };

    /**
     * Add 'highlight-related' CSS to all elements containing the related_to string in their name property.
     * Add those elements to the "turned on" list.
     */
    this.related_on = function () {
        let related_to = jQuery(this).attr('data-related_to'),
            related_array = related_to.split(',');

        related_array.forEach(function (item) {
            let related_element = jQuery('div[id*="' + item + '"]');
            jQuery(related_element).addClass('highlight-related');
            if (typeof turned_on[item] === 'undefined') {
                turned_on[item] = related_element;
            }
        });
    };

    /**
     * Remove 'highlight-related' CSS from all elements on the "turned on" list.
     */
    this.related_off = function () {
        let property;
        for (property in turned_on) {
            if (turned_on.hasOwnProperty(property)) {
                let related_element = turned_on[property];
                jQuery(related_element).removeClass('highlight-related');
                turned_on[property] = undefined;
            }
        }
    };
};

/**
 * Plugin Style
 */
const SLP_Admin_Plugin_Style = {
    scroll_in_progress: false,
    starting_theme: undefined,
    style_id: undefined,
    style_obj: undefined,
    user_is_premier: false,

    /**
     * Things we do to start.
     */
    initialize: function () {
        this.style_obj = jQuery('#input-group-options_nojs\\[style\\]');
        this.user_is_premier = jQuery(this.style_obj).attr('data-premier') === '1';
        this.style_id = jQuery('#options_nojs\\[style_id\\]').val()

        // New Style
        // The admin settings help is only enqueued on the settings tab.
        // The info tab also uses thsi script (for now) but not the help script.
        if (typeof SLP_Admin_Settings_Help !== 'undefined') {
            jQuery('div.theme .button-secondary').on(
                'click',
                SLP_Admin_Settings_Help.PluginStyle.set_active_style
            );
            jQuery('.wpcsl-style_vision_list').on(
                'scroll',
                SLP_Admin_Settings_Help.PluginStyle.get_more_style
            );
        }
    },

    /**
     * Get more style.
     */
    get_more_style: function () {
        if (!SLP_Admin_Settings_Help.PluginStyle.scroll_in_progress) {
            SLP_Admin_Settings_Help.PluginStyle.scroll_in_progress = true;

            var style_obj = SLP_Admin_Settings_Help.PluginStyle.style_obj;
            var page_size = jQuery(style_obj).attr('data-page_len');
            var page = jQuery(style_obj).attr('data-pages_loaded');

            const full_url = 'https://storelocatorplus.com/wp-json/wp/v2/slp_style_gallery?orderby=title&order=asc&per_page=' + page_size + '&page=' + ++page;
            jQuery
                .get(full_url, '', SLP_Admin_Settings_Help.PluginStyle.add_more_style)
                .fail(function (data) {
                    SLP_Admin_Settings_Help.PluginStyle.scroll_in_progress = false;
                    if ((typeof data.responseJSON !== 'undefined') && (data.responseJSON.code === 'rest_post_invalid_page_number')) {
                        jQuery('.wpcsl-style_vision_list').off('scroll');
                    }
                });
        }
    },

    /**
     * Add style.
     */
    add_more_style: function (data) {
        var style_obj = SLP_Admin_Settings_Help.PluginStyle.style_obj;
        if (data.length < 1) {
            jQuery(style_obj).attr(
                'style',
                'border-bottom: none; padding-bottom: 1em;'
            );
            return;
        }

        var page = jQuery(style_obj).attr('data-pages_loaded');
        jQuery(style_obj).attr('data-pages_loaded', ++page);

        var vision_list = jQuery(style_obj).find('.card_list');
        var style_html = '';
        jQuery(data).each(function () {
            style_html = SLP_Admin_Settings_Help.PluginStyle.vision_item_html(this);
            jQuery(vision_list).append(style_html);
        });

        // attach button click to new entries
        jQuery('div.theme .button-secondary').off('click').on(
            'click',
            SLP_Admin_Settings_Help.PluginStyle.set_active_style
        );

        SLP_Admin_Settings_Help.PluginStyle.scroll_in_progress = false;
    },

    /**
     * HTML from style object.
     */
    vision_item_html: function (style) {
        let active = '';
        if (SLP_Admin_Settings_Help.PluginStyle.style_id == style.id) {
            active = ' active ';
        }

        var access_level = '';
        if (typeof style.custom_fields.access_level !== 'undefined') {
            access_level = style.custom_fields.access_level[0];
        }

        var actions = '';
        if (
            access_level == '' ||
            SLP_Admin_Settings_Help.PluginStyle.user_is_premier
        ) {
            let activate_label =
                active == ''
                    ? jQuery('#select_text').val()
                    : jQuery('#active_text').val();
            actions =
                '<div class="card-section theme-actions">' +
                '<a class="button button-secondary activate" ' +
                ' data-post_id="' + style.id + '"' +
                ' aria-label="' +
                activate_label +
                '">' +
                activate_label +
                '</a>' +
                '</div>';
        }

        var style_html =
            '<div class="card theme ' +
            access_level +
            active +
            '">' +
            '<div class="card-divider">' +
            '<h2 class="theme-name">' +
            style.title.rendered +
            '</h2>' +
            '</div>' +
            '<div class="card-section details">' +
            style.content.rendered +
            '</div>' +
            actions +
            '</div>';

        return style_html;
    },

    /**
     * Set the active style.
     *
     * @param event e
     * @returns {undefined}
     */
    set_active_style: function (e) {
        e.preventDefault();
        jQuery(this).text('Updating settings...');

        const styleID = jQuery(this).attr('data-post_id');
        const style = jQuery(this).attr('data-slug');
        const activeCard = jQuery('div.theme.active');
        const thisCard = jQuery(this).closest('.card.theme');

        // Drop active and "reload" from active style...
        if (jQuery(activeCard).attr('data-style') !== jQuery(thisCard).attr('data-style')) {
            jQuery(activeCard).removeClass('active');
            jQuery(activeCard).find('.button-secondary').text('Select');
        } else {
            SLP_ADMIN.options.change_option(thisCard, 'options_nojs[style_id]', '0');
        }

        // Show reload and set this to active
        jQuery(thisCard).addClass('active');

        jQuery('#options_nojs\\[style_id\\]').val(styleID);
        jQuery('#options_nojs\\[style\\]').val(style);
        jQuery('.button-primary').click();
    },

    /**
     * Show the theme details panel and hide the prior active selection.
     *
     * @returns {undefined}
     */
    show_details: function () {
        var selected_theme = jQuery(
            'select#options_nojs\\[theme\\] option:selected'
        ).val();
        var selected_theme_details = '#' + selected_theme + '_details';

        var content =
            '<h3>' +
            jQuery('select#options_nojs\\[theme\\] option:selected').text() +
            '</h3>' +
            jQuery(selected_theme_details).html();
        jQuery('.settings-description').toggleClass('is-visible').html(content);

        // Auto apply plugin theme layouts
        if (selected_theme !== SLP_Admin_Settings_Help.PluginStyle.starting_theme) {
            SLP_Admin_Settings_Help.PluginStyle.starting_theme = selected_theme;
            SLP_Admin_Settings_Help.PluginStyle.set_theme_options(
                selected_theme_details
            );
        }
    },

    /**
     * Set theme options on plugin style change.
     * @returns {boolean}
     */
    set_theme_options: function (selected_theme_details) {
        jQuery(selected_theme_details + ' > .theme_option_value').each(function () {
            var field_name = jQuery(this).attr('settings_field');
            jQuery('[name="' + field_name + '"]').val(jQuery(this).text());
        });
        return false;
    },
};

// Is our page loaded?  Go do stuff.
//
jQuery(document).ready(function () {
    SLP_ADMIN.helper = new slp_setting_helper();
    SLP_ADMIN.helper.initialize();

    if (typeof SLP_Admin_Settings_Help !== 'undefined') {
        SLP_Admin_Settings_Help.PluginStyle = SLP_Admin_Plugin_Style;
        SLP_Admin_Settings_Help.PluginStyle.initialize();
    }
});
