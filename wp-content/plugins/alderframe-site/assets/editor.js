/* Generic editor UI for Alderframe dynamic blocks: live server preview + controls for simple attributes. */
( function ( wp, blocks ) {
	const el = wp.element.createElement;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, TextControl, ToggleControl } = wp.components;
	const ServerSideRender = wp.serverSideRender;

	Object.keys( blocks ).forEach( ( slug ) => {
		const def = blocks[ slug ];
		const name = 'alderframe/' + slug;
		if ( wp.blocks.getBlockType( name ) ) {
			wp.blocks.unregisterBlockType( name );
		}
		wp.blocks.registerBlockType( name, {
			apiVersion: 3,
			title: def.title,
			category: 'alderframe',
			icon: 'admin-site-alt3',
			attributes: def.attributes,
			supports: { html: false, align: [ 'wide', 'full' ] },
			edit( { attributes, setAttributes, context } ) {
				const controls = Object.keys( def.attributes ).map( ( key ) => {
					const type = def.attributes[ key ].type;
					const label = key.replace( /([A-Z])/g, ' $1' ).toLowerCase();
					if ( type === 'boolean' ) {
						return el( ToggleControl, { key, label, checked: !! attributes[ key ], onChange: ( v ) => setAttributes( { [ key ]: v } ) } );
					}
					if ( type === 'string' || type === 'number' ) {
						return el( TextControl, {
							key, label, value: attributes[ key ] ?? '',
							type: type === 'number' ? 'number' : 'text',
							onChange: ( v ) => setAttributes( { [ key ]: type === 'number' ? Number( v ) : v } ),
						} );
					}
					return null;
				} );
				return el( 'div', useBlockProps(),
					controls.some( Boolean ) && el( InspectorControls, null, el( PanelBody, { title: def.title }, controls ) ),
					el( ServerSideRender, { block: name, attributes, urlQueryArgs: context && context.postId ? { post_id: context.postId } : {} } )
				);
			},
			save: () => null,
		} );
	} );
} )( window.wp, window.afBlocks || {} );
