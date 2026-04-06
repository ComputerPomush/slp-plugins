/**
 * SLP Admin
 * 2210.05.01
 */
/* globals jQuery, ajaxurl, slp, slp_data */
const SLP_ADMIN = {
    has_pubsub: true,
    topics: {}
};

// --  No callbacks.
if (typeof jQuery.Callbacks === 'undefined') {
    SLP_ADMIN.has_pubsub = false;
    SLP_ADMIN.log('jQuery callbacks not supported.');
    SLP_ADMIN.log(
        'Something is forcing jQuery version ' + jQuery.fn.jquery + ' .'
    );
}

/**
 * jQuery Observer (pub/sub) class.
 * @param id
 * @returns {*}
 * @constructor
 */
const slp_Admin_Filter = function (id) {
    // -- no jQuery callbacks
    if (!SLP_ADMIN.has_pubsub) {
        return {
            publish: function (data) {
                return data;
            },
        };
    }

    // -- no filter ID, bail
    if (!id) {
        slp.log(
            'slp_Admin_filter(id) - the id is required.'
        );
        return;
    }

    // -- no "topic", define it...
    if (!SLP_ADMIN.topics[id]) {
        const callbacks = jQuery.Callbacks();
        SLP_ADMIN.topics[id] = {
            hasSubscriptions: callbacks.has,
            publish: callbacks.fire,
            subscribe: callbacks.add,
            unsubscribe: callbacks.remove,
        };
    }

    return SLP_ADMIN.topics[id];
};
const slp_AdminFilter = slp_Admin_Filter;

/**
 * Option Manager Class
 * Revealing Module pattern.  See return for public methods and properties.
 */
SLP_ADMIN.options = (function () {
    let thing_that_changed = null;

    /**
     * Handle the change option response.
     *
     * @param response
     */
    const process_change_option_response = (response) => {
        jQuery(thing_that_changed).removeClass('saving');
        const json_response = JSON.parse(response);
        if (json_response.status !== 'ok') {
            alert('Option not saved.');
        }
    };

    /**
     * Change Options
     * @param input_object
     * @param input_id
     * @param input_value
     */
    this.change_option = function (input_object, input_id = undefined, input_value = undefined) {
        thing_that_changed = input_object; //important so we can remove saving class later
        jQuery(input_object).addClass('saving');

        if (typeof input_object !== 'undefined') {
            if (jQuery(input_object).is(':checkbox')) {
                if (typeof input_object.checked !== 'undefined') {
                    jQuery(input_object).val(input_object.checked ? '1' : '0');
                }
            }
        }

        const post_data = {
            'action': 'slp_change_option',
            'security': slp_data.ajax_nonce,
            'formdata': {
                'option_name':
                    typeof input_id === 'undefined'
                        ? jQuery(input_object).attr('id')
                        : input_id,
                'option_value':
                    typeof input_value === 'undefined'
                        ? jQuery(input_object).val()
                        : input_value,
            },
        };

        jQuery.post(ajaxurl, post_data, process_change_option_response);
    };

    /**
     * Start us up.
     */
    this.initialize = function () {
        // -- image icon selector is clicked
        jQuery('.slp_icon_selector').on('click', function () {
            const image_src = jQuery(this).attr('src');
            const input_div = jQuery(this).closest('div.input-group');
            const iconOption = input_div.find('input[type="text"]');
            input_div
                .find('img.slp_settings_icon')
                .attr('src', image_src);
            iconOption.val(image_src);
            this.change_option(iconOption);
        });

        const qs_inputs = jQuery('.quick_save').find(':input').not('button.add_media');
        qs_inputs.on('change', (e) => {
            this.change_option(e.currentTarget)
        });
    };

    // Public properties and methods
    return {
        quick_save: true,
        initialize: this.initialize,
        change_option: this.change_option,
    };
})();

/**
 * Notifications Manager Class
 */
var slp_notifications = function () {
    var id = 0;

    /**
     * For all notifications - attach the close on click and auto-close after 10 seconds
     */
    this.initialize = function () {
        jQuery('div.slp-notification').on('click', function () {
            this.remove();
        });
        setTimeout(this.remove_all, 5000);
    };

    /**
     * Remove all notifications
     */
    this.remove_all = function () {
        jQuery('.slp-notification').each(function (i) {
            var elm = jQuery(this);
            setTimeout(function () {
                elm.remove();
            }, 3000 + i * 300);
        });
    };

    /**
     * Add a notification.
     *
     * @param msg
     * @param level  'information' , 'is-error' , 'warning' , 'success'
     */
    this.add = function (level, message) {
        var header_div = jQuery('div.store-locator-plus section.dashboard-content');
        var alert_icons = {
            success: 'fa-check',
            info: 'fa-info',
            warning: 'fa-exclamation',
            failure: 'fa-exclamation-triangle',
        };
        var alert_class = {
            success: 'success',
            info: 'information',
            warning: 'warning',
            failure: 'is-error',
        };
        var alert_id = 'slp_alert_' + id++;
        var new_div = jQuery(
            '<div class="alert_box" id="' +
            alert_id +
            ' ">' +
            '<div class="slp-notification alert-center ' +
            alert_class[level] +
            ' active">' +
            '<div class="alert-icon"><i class="fa ' +
            alert_icons[level] +
            '"></i></div>' +
            '<div class="alert-content">' +
            message +
            '</div>' +
            '<div class="alert-action"><span class="button close"><i class="fa fa-close"></i></span></div>' +
            '</div>' +
            '</div>'
        );
        header_div.append(new_div);

        // Love removal machine...
        new_div.on('click', function () {
            this.remove();
        });
        setTimeout(function () {
            new_div.remove();
        }, 5000);

        return alert_id;
    };

    /**
     * Update a notification.
     *
     * @param div_id
     * @param message
     */
    this.update = function (div_id, message) {
        jQuery('#' + div_id + ' .alert-content').html(message);
    };
};

/**
 * AJAX Manager
 */
var slp_ajax_manager = function () {
    /**
     * Post to SLP AJAX.
     *
     * @param data
     * @param messages
     */
    this.post = function (data, messages) {
        data.security = slp_data.ajax_nonce || null;
        var ajax_call = jQuery.post(ajaxurl, data);

        // When the call is done...
        ajax_call.done(function (response) {
            var json_response = JSON.parse(response);

            // Response status = 'ok'
            //
            if (json_response.status == 'ok' && messages['message_ok']) {
                AdminUI.notifications.add('success', messages['message_ok']);
                slp_Admin_Filter('ajax_post_ok').publish(json_response);

                // Response status != 'ok'
            } else {
                if (messages['message_info']) {
                    AdminUI.notifications.add('info', messages['message_info']);
                }
                slp_Admin_Filter('ajax_post_info').publish(json_response);
            }
        });

        // If the call failed , 404 etc.
        ajax_call.fail(function (response) {
            if (messages['message_failure']) {
                AdminUI.notifications.add('failure', messages['message_failure']);
            }
            slp_Admin_Filter('ajax_post_failure').publish(response);
        });
    };
};

/**
 * AdminUI Class
 */
const AdminUI = {
    /**
     * Confirm a message then redirect the user.
     */
    confirmClick: function (message, href) {
        if (confirm(message)) {
            location.href = href;
        } else {
            return false;
        }
    },

    // Fires on dismissing the admin notice.
    //
    dismiss_persistent_notice: function () {
        SLP_ADMIN.options.change_option(
            undefined,
            'options_nojs[admin_notice_dismissed]',
            '1'
        );
    },

    /**
     * Perform an action on the specified form.
     */
    doAction: function (action) {
        jQuery('#locationForm input[name="act"]').val(action);
        jQuery('#locationForm').submit();
    },

    /**
     * load_first_tab()
     */
    load_first_tab: function () {
        const selectedNav =
            jQuery('#selected_nav_element').val() ||
            jQuery('#content>div.group.subtab:first');
        if (selectedNav) {
            jQuery('.group').hide();
            jQuery(selectedNav).show();
        }
    },

    /**
     * open_first_menu()
     */
    open_first_menu: function () {
        jQuery('#wpcsl-nav li.current.has-children:first ul.sub-menu')
            .hide()
            .addClass('open')
            .children('li:first')
            .addClass('active')
            .parents('li.has-children')
            .addClass('open');
    }, // End open_first_menu()

    /**
     * toggle_nav_menus()
     */
    toggle_nav_menus: function () {
        jQuery('#wpcsl-nav li.has-children > a').on('click', function () {
            if (jQuery(this).parent().hasClass('open')) {
                return false;
            }

            jQuery('#wpcsl-nav li.top-level')
                .removeClass('open')
                .removeClass('current');
            jQuery('#wpcsl-nav li.active').removeClass('active');
            if (jQuery(this).parents('.top-level').hasClass('open')) {
            } else {
                jQuery('#wpcsl-nav .sub-menu.open')
                    .removeClass('open')
                    .hide()
                    .parent()
                    .removeClass('current');
                jQuery(this)
                    .parent()
                    .addClass('open')
                    .addClass('current')
                    .find('.sub-menu')
                    .hide()
                    .addClass('open')
                    .children('li:first')
                    .addClass('active');
            }

            // Find the first child with sections and display it.
            var clickedGroup = jQuery(this)
                .parent()
                .find('.sub-menu li a:first')
                .attr('href');
            if (clickedGroup != '') {
                jQuery('.group').hide();
                jQuery(clickedGroup).show();
            }
            return false;
        });
    }, // End toggle_nav_menus()

    /**
     * setup_nav_highlights()
     */
    setup_nav_highlights: function () {
        // Highlight the first item by default.
        var selectedNav = jQuery('#selected_nav_element').val();
        if (selectedNav == '') {
            jQuery('#wpcsl-nav li.top-level:first')
                .addClass('current')
                .addClass('open');
        } else {
            jQuery('#wpcsl-nav li.top-level:has(a[href="' + selectedNav + '"])')
                .addClass('current')
                .addClass('open');
        }

        // Default single-level logic.
        jQuery('#wpcsl-nav li.top-level')
            .not('.has-children')
            .find('a')
            .on('click', function () {
                var thisObj = jQuery(this);
                var clickedGroup = thisObj.attr('href');

                if (clickedGroup != '') {
                    jQuery('#selected_nav_element').val(clickedGroup);
                    jQuery('#wpcsl-nav .open').removeClass('open');
                    jQuery('.sub-menu').hide();
                    jQuery('#wpcsl-nav .active').removeClass('active');
                    jQuery('#wpcsl-nav li.current').removeClass('current');
                    thisObj.parent().addClass('current');

                    jQuery('.group').hide();
                    jQuery(clickedGroup).show();
                    jQuery(clickedGroup).trigger('is_shown');

                    return false;
                }
            });

        // Sub-menu link click logic.
        jQuery('.sub-menu a').on('click', function () {
            var thisObj = jQuery(this);
            var parentMenu = jQuery(this).parents('li.top-level');
            var clickedGroup = thisObj.attr('href');

            if (
                jQuery('.sub-menu li a[href="' + clickedGroup + '"]').hasClass('active')
            ) {
                return false;
            }

            if (clickedGroup != '') {
                parentMenu.addClass('open');
                jQuery('.sub-menu li, .flyout-menu li').removeClass('active');
                jQuery(this).parent().addClass('active');
                jQuery('.group').hide();
                jQuery(clickedGroup).show();
            }

            return false;
        });
    }, // End setup_nav_highlights()

    /**
     * unhide_hidden()
     */
    unhide_hidden: function (obj) {
        obj = jQuery('#' + obj); // Get the jQuery object.

        if (obj.attr('checked')) {
            obj
                .parent()
                .parent()
                .parent()
                .nextAll()
                .hide()
                .removeClass('hidden')
                .addClass('visible');
        } else {
            obj
                .parent()
                .parent()
                .parent()
                .nextAll()
                .each(function () {
                    if (jQuery(this).filter('.last').length) {
                        jQuery(this).hide().addClass('hidden');
                        return false;
                    }
                    jQuery(this).hide().addClass('hidden');
                });
        }
    }, // End unhide_hidden()
}; // End AdminUI Object

/**
 * Log a message if the console window is active.
 *
 * @param message
 */
SLP_ADMIN.log = function (message) {
    if (window.console) {
        console.log(message);
    }
};

// Document Ready
//
jQuery(document).ready(function () {
    // Handle SLP Notifications
    //
    AdminUI.notifications = new slp_notifications();
    AdminUI.notifications.initialize();

    // Real Time Options Manager
    //
    SLP_ADMIN.options.initialize();

    // AJAX Manager
    SLP_ADMIN.ajax = new slp_ajax_manager();

    // Setup Panel Navigation Elements
    //
    AdminUI.load_first_tab();
    AdminUI.setup_nav_highlights();
    AdminUI.toggle_nav_menus();
    AdminUI.open_first_menu();

    // Settigns group expand/collapse
    // Defunct(?)
    jQuery('div.settings-group')
        .children('h3')
        .on('click', function () {
            var p = jQuery(this).parent('.settings-group');
            p.toggleClass('closed');
        });

    // Dismiss persistent admin notices
    //
    jQuery('#slp_persistent_notice .notice-dismiss').on(
        'click',
        AdminUI.dismiss_persistent_notice
    );
});
