/**
 * SLP Admin Settings Help
 * 2209.12
 */
/* globals jQuery */
const SLP_Admin_Settings_Help = {};

/**
 * Help UX
 */
var SLP_Admin_Help_UX = function () {
  /**
   * Initialize the UX
   */
  this.initialize = function () {
    jQuery('div.input-group').on('mouseenter',function () {
      let title = jQuery(this).find('LABEL').html();
      if (typeof title === 'undefined') {
        title = '';
      }
      let details = jQuery(this).find('.input-description').html();
      if (typeof details === 'undefined') {
        return;
      }
      jQuery('.settings-description')
        .toggleClass('is-visible')
        .html('<h3>' + title + '</h3>' + details);
    });

    jQuery('H3.aside-heading').on(
      'click',
      SLP_Admin_Settings_Help.UX.accordion
    );
  };

  this.accordion = function () {
    const is_now = jQuery('.dashboard-aside-secondary').css('flex-basis');
    const help_sidebar = jQuery('.dashboard-aside-secondary');
    if (is_now === '350px') {
      jQuery(help_sidebar).css('flex-basis', '10px');
      jQuery(help_sidebar).find('IMG').hide();
    } else {
      jQuery(help_sidebar).css('flex-basis', '350px');
      jQuery(help_sidebar).find('IMG').show();
    }
  };

  /**
   * Clear the more info box.
   */
  this.clear_more_info = function () {
    jQuery('.settings-description').toggleClass('is-visible').html('');
  };
};

/**
 * Icon Helper
 * @constructor
 */
var SLP_Admin_icons = function () {
  let active_setting;

  /**
   * Initialize the icon interface.
   */
  this.initialize = function () {
    this.connect_wp_media_to_insert_media_buttons();
  };

  /**
   * Fire up WP Media selector on insert media buttons.
   */
  this.connect_wp_media_to_insert_media_buttons = function () {
    jQuery('.input-group .wp-media-buttons .insert-media').on(
      'click',
      function (event) {
          event.preventDefault();
          const setting = jQuery(this).attr('data-base_id');
          SLP_Admin_Settings_Help.icons.active_setting = setting.replace(
            /(:|\.|\[|\])/g,
            '\\$1'
          );
          SLP_Admin_Settings_Help.icons.create_media_frame();
          return false;
      }
    );

    /**
     * Create the tweaked WP Media frame, make it a singleton.
     */
    this.create_media_frame = function () {
      if (!wp.media.frames.slp_icon_frame) {
        wp.media.frames.slp_icon_frame = wp.media({
          title: 'Select or Upload An Icon', // TODO: pass via localized script variable for i18n/l10n
          button: { text: 'Use This' }, // TODO: pass via localized script variable for i18n/l10n
          multiple: false,
          library: {
            type: 'image',
          },
        });

        wp.media.frames.slp_icon_frame.on('select', function () {
          // Get the details about the file the user uploaded/selected
          const attachment = wp.media.frames.slp_icon_frame
            .state()
            .get('selection')
            .first()
            .toJSON();

          const iconOption = jQuery('#' + SLP_Admin_Settings_Help.icons.active_setting);
          iconOption.val(attachment.url);
          SLP_ADMIN.options.change_option(iconOption);

          iconOption
              .closest('div.input-group')
              .find('img.slp_settings_icon')
              .attr('src', iconOption.val());
        });
      }

      wp.media.frames.slp_icon_frame.open();
    };
  };
};

/**
 * Locations Tab Admin JS
 */
jQuery(document).ready(function () {
  SLP_Admin_Settings_Help.UX = new SLP_Admin_Help_UX();
  SLP_Admin_Settings_Help.UX.initialize();

  if (jQuery('.wp-media-buttons')[0]) {
    SLP_Admin_Settings_Help.icons = new SLP_Admin_icons();
    SLP_Admin_Settings_Help.icons.initialize();
  }
});
