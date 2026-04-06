// store-locator-le/js/block.js
; ( function( ) {
	var __ = wp.i18n.__,
		blocks = wp.blocks,
		el = wp.element.createElement,
		InspectorControls = wp.blockEditor.InspectorControls,
		TextControl = wp.components.TextControl
	;

	var blockStyle = {
		height: slplus.map_height,
		width: slplus.map_width
	};

	/**
	 * SLPlus Full Locations Map
	 */
	blocks.registerBlockType( 'store-locator-plus/basic-locations-map', {
		title: __( 'SLP - Basic Locator', 'store-locator-le' ),
		icon: 'location-alt',
		keywords: [ __('Map', 'store-locator-le'), __('Maps', 'store-locator-le'), 'Google', __('Locations', 'store-locator-le'), __('Locator', 'store-locator-le'), 'Store Locator Plus' ],

		category: 'common',

		attributes: {
			'server_key': {type:'string', value: slplus.google_server_key },
			'map_center': {type:'string', default: slplus.map_center },
			'initial_radius': { type:'string', default: slplus.initial_radius }
		},


		// Block Editor Rendering
		//
		edit: function( props ) {
			var controls,
				control_elements = [],
				children = [],
				message_elements = [];

			message_elements.push(
					el( 'span' , { key: 'message' }, __( 'Your Store Locator Plus® search form, map, and location list will appear in this location on the front end. ', 'store-locator-le') ),
					el( 'a' , { key: 'addon_link' , href: 'https://wordpress.storelocatorplus.com/', target: '__blank' }, __( 'For more map block types check out the Premier add on package.' , 'store-locator-le' ) )
				);

			children.push(
				el( wp.components.Dashicon , { key: 'icon' , icon: 'location-alt' } ),
				el( 'div' , { key: 'inner_wrapper', className: 'message-block' } , message_elements )
			);

			/**
			 * Save values of controls.
			 * @param newValue
			 */
			function onChangeMapCenter( newValue ) {
				props.setAttributes( { 'map_center': newValue } );
			}
			function onChangeRadius( newValue ) {
				props.setAttributes( { 'initial_radius': newValue } );
			}
			function onChangeServerKey( newValue ) {
				props.setAttributes( { 'server_key': newValue } );

				// Set on SLP backend
				jQuery.post( encodeURI(slplus.rest_url + 'options/google_server_key/' + slplus.api_key ) , { value: newValue } ).done(function(resp) {
					if ( resp.error_message ) {
						console.log( resp.error_message );
					} else {
						resp.error_message = '';
					}
				}).fail(function(resp) {
					console.log( 'get_from_server failed' );
				})
				;
			}

			if ( slplus.server_key ) {
				control_elements.push(
					el(
						'h3',
						{ key: 'map_controls_header' },
						__( 'Map Settings' )
					),
					el(
					TextControl,
					{
						key: 'map_center_control',
						label: __('Map Center Address'),
						onChange: onChangeMapCenter,
						value: props.attributes.map_center
					}
					),
					el(
						TextControl,
						{
							key: 'initial_radius_control',
							label: __('Maximum Distance From Center'),
							onChange: onChangeRadius,
							value: props.attributes.initial_radius
						}
					)
				);
			} else {
				control_elements.push(
					el( 'span' , { key: 'need_google_key' } , __( 'You need a Google Maps JavaScript API key to display the map on your site. ' ) ),
					el( 'a' , { key: 'google_key_signup' , href: 'https://console.cloud.google.com/google/maps-apis/start' } , __( 'Get one at the Google Developers Console. ' ) ),
					el( 'p' , { key: 'enter_key_below' } , __( 'Once you have your key, enter it below.' ) ),
					el( TextControl,
						{
							key: 'server_key_control',
							label: __( 'Maps JavaScript API key'),
							onChange: onChangeServerKey,
							value: props.attributes.server_key
						}),
					el( 'a' , { key: 'myslp_signup' , href: 'https://storelocatorplus.com' } , __( 'Skip the hassle of getting a Google billing account, try our SaaS service and let us take care of the details. ' ) )
				);
			}

			controls = el( InspectorControls, { key: 'inspector' }, control_elements );

			return [
				controls,
				el( 'div' , { key: 'wrapper' , className: props.className + ' slp-locations-block' , style: blockStyle } , children )
			];
		},

		// Front End Rendering
		save: function( props ) {
			return null; // Render with PHP
		}
	} );
} )();
