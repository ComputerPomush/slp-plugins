=== Store Locator Plus® | Extended Data Manager ===
Plugin Name:       Store Locator Plus® | Extended Data Manager
Contributors: DeBAAT, freemius
Donate link:       https://www.de-baat.nl/slp-extended-data-manager/
Tags:              store locator plus, data tables, google maps, locations, integration
Required PHP:      8.0
Requires at least: 6.0
License:           GPL3
Tested up to:      6.1.1
Stable tag:        6.1.1

SLP Extended Data Manager is an add-on pack for Store Locator Plus that lets admin manage the extended data settings.

== Description ==

[SLP](https://www.storelocatorplus.com/) | [Location and Directory SaaS](https://my.storelocatorplus.com)| [WordPress Plugins](https://wordpress.storelocatorplus.com)  | [Documentation](https://docs.storelocatorplus.com) | [Demo](https://demo.storelocatorplus.com)

SLP Extended Data Manager is an add-on pack for Store Locator Plus that lets admin manage the extended data settings.
Add new data fields to the Store Locator Plus locations or manage the interaction with data fields added by other SLP add-on packs.

== Basic Features ==

- Show an overview of extended data elements on the General Settings page
- Show the following options to extended data elements:
* Show (default = 'true')
* Order (default = '')

== Pro Features ==

- Add new extended data elements on the General Settings page
- Manage extended data elements options on the General Settings page
- Filter to only show selected extended data elements on the locations overview
- Order the extended data elements on the locations overview


== Installation ==

= Requirements =

* Store Locator Plus: 2210.25
* WordPress: 6.0
* PHP: 8.0

= Install After SLP =

1. Go fetch and install [Store Locator Plus](https://wordpress.org/plugins/store-locator-le/).
2. Install this plugin directly from the WordPress org site.

OR

2. Download this plugin from the WordPress org site to get the latest .zip file.
3. Go to plugins/add new.
4. Select upload.
5. Upload the zip file.

== Frequently Asked Questions ==

= How does the addon work? =

The addon adds a new sub-page to the set of SLP configuration pages under the General Settings tab.
The 'Extended Data Manager' tab presents the Admin with a list of configured extended data elements.
The visibility of each individual extended data element can be toggled between 'Show' and 'Hide'; when no option is explicitly set, it will show '--' which has the same effect as 'Show'.
Additionally, a 'Filtered' option is added to the dropdown list of display options on the 'Locations' page.
When this option is selected, the list of locations shows an extended set of data, similar to the 'Extended' option.
The difference is in the Show/Hide toggle set on the 'Extended Data Manager' tab:
the extended data elements that have the option set to 'Hide' are omitted from the table.
The Order option can be used to define the order of the elements shown.

= What are the terms of the license? =

The license for the free plugin is GPL. You get the code, feel free to modify it as you wish. We prefer that our customers pay us for the Premium version because they like what we do and want to support our efforts to bring useful software to market. Learn more on our [DeBAAT License Terms](https://www.de-baat.nl/general-eula/) page.

== Changelog ==

= 6.1.1 =
* Tested to work with WP 6.1.1 and SLP 2210.25.
* Tested to work with PHP 8
* Updated Freemius SDK to V2.5.3

= 5.9.1 =
* Tested to work with WP 5.9.1 and SLP 5.12.
* Security fix

= 5.9.0 =
* Tested to work with WP 5.9 and SLP 5.12.

= 5.8.0 =
* Updated Freemius SDK to 2.4.2
* Tested to work with WP 5.8.3 and SLP 5.12.

= 5.7.0 =
* Renamed display name to Store Locator Plus® - Extended Data Manager
* Tested to work with WP and SLP 5.6.
* Fixed handling of options after update
* Fixed handling of debug code

= 5.5.1 =
* Improved validation of input processing.

= 5.5.0 =
* Started Extended Data Manager as a free implementation for showing extended data elements.

= 5.3.2 =
* Cleaned some code

= 5.3.1 =
* Updated to work with Store Locator Plus 5.5.

= 5.0.1 =
* Updated to work with Freemius deployment

= 5.0.00 =
* Updated to work with WP and SLP 5.0.

= 4.8.00 =
* Updated to work with SLP 4.8.
* Fixed filtering option to Show Hidden EDM Columns

= 4.7.0 =
* Fixed issue adding extended data element.
* Tested with WordPress 4.7.2.

= 4.5.01 =
* Tested with WordPress 4.5.
* Updated to support SLP 4.5.

= 4.5 =

Enhancements

* Add support for new 'none' display type.  This prevents the input from rendering on the add location screen.  Useful for backend-programatic storage such as serialized data.

= 4.4.02 =

Fixes

* Clean up the general tab styling.

Changes

* Use a new commmon 'Data' subtab under 'General' tab.

= 4.4.00 =
* Change: Requires SLP 4.4
* Tested on WordPress 4.4.1
* Renamed Text Domain to reflect name of plugin.
* Added support for new extended data element options.

= 4.3 =
* Change: Requires SLP 4.3
* Added options to add, edit and delete extended data elements on the management page.

= 4.2.03 =
* Added option to sort the extended data elements on the management page.

= 4.2.02 =
* Added option to order the extended data elements on the Locations page.
* Fixed save options functionality in combination with bulk_actions.

= 4.2.01 =
* Initial release.
