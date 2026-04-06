# Store Locator Plus® Developer Notes

# PHP Documentation

If you have Docker installed you can generate PHP documentation with this command:

    docker run --rm -v "$(pwd):/data" "phpdoc/phpdoc:3"

This runs phpDocumentor with the phpdoc.dist.xml configuration

# Sass, SCSS, and CSS Files

Use Sass to compile SCSS directly to minified CSS files.

[Read more...](https://internal.storelocatorplus.com/saas-scss-and-css-files/)

# Nonces

## Base Plugin Nonces

### Actions in use

* **media-form** : Settings admin tab
* **screen-options-nonce** : Location admin tab
* **slp_ajax** : All admin tabs
* **wp_rest** : SLPLUS shortcode forms and Settings admin tab

---
### Action: media-form

**usage:** Settings Admin Tab AJAX calls

#### Settings Admin Tab Rendering

**implementation:** in wp_data.nonce as a localized JavaScript variable 

Provided with AJAX calls to the backend from the Settings Admin tab.

---
### Action: screen-options-nonce

**usage:** Locations Admin Page

#### Locations Admin Tab Rendering

**implementation:** as a hidden form field

A wp_nonce_field( 'screen-options-nonce' , 'screenoptionnonce', false) hidden POST field that lives in the screen options drop down.

It is submitted with all POST requests from the admin locations page.   

Need to investigate AJAX (quick save/quick action) requests from this page.  It is not likely part of the AJAX quick save requests.

If is nomally checked with check_admin_referer( 'screen-options-nonce', 'screenoptionnonce' ).

---
### Action: slp_ajax

**usage:** All Admin Tab AJAX calls

#### Any Admin Tab Rendering

**implementation:** in wp_data.ajax_nonce as a localized JavaScript variable

**implementation:** in a hidden form field 'security' on form posts

Provided with AJAX calls to the backend inside $_REQUEST['security'] during the admin.js jQuery.post() call.

Provide via standard form posts in $_REQUEST['security']

---
### Action: wp_rest

**usage:** JavaScript & AJAX Requests including SLPLUS search form

**usage:** JavaScript & AJAX Requests from the Admin Settings tab

#### SLPLUS Shortcode Rendering

**implementation:** in slplus.nonce as a localized JavaScript variable

It is sent with all AJAX requests from SLP shortcodes, even unregistered front end users.

#### Settings Admin Tab Rendering

**implementation:** in wp_data.import_nonce as a localized JavaScript variable

Provided with AJAX calls to the backend from the Settings Admin tab.

