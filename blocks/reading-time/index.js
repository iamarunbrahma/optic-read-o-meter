( function( wp ) {
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var registerBlockType = wp.blocks.registerBlockType;
	var ServerSideRender = wp.serverSideRender;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var __ = wp.i18n.__;

	registerBlockType( 'optrom/reading-time', {
		edit: function( props ) {
			var attrs = props.attributes;
			var setAttrs = props.setAttributes;
			var blockProps = useBlockProps();

			return el( Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Reading time', 'optic-read-o-meter' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Format', 'optic-read-o-meter' ),
							value: attrs.format,
							options: [
								{ label: __( 'Styled badge', 'optic-read-o-meter' ), value: 'badge' },
								{ label: __( 'Plain text', 'optic-read-o-meter' ), value: 'text' }
							],
							onChange: function( v ) { setAttrs( { format: v } ); }
						} ),
						el( TextControl, {
							label: __( 'Plain-text wrapper (optional)', 'optic-read-o-meter' ),
							help: __( 'Wraps the label. Use %s for the label text. Applies to the "Plain text" format only.', 'optic-read-o-meter' ),
							value: attrs.template,
							onChange: function( v ) { setAttrs( { template: v } ); }
						} )
					)
				),
				el( 'div', blockProps,
					el( ServerSideRender, {
						block: 'optrom/reading-time',
						attributes: attrs
					} )
				)
			);
		},
		// Dynamic block. Server renders the content, save returns null.
		save: function() { return null; }
	} );
} )( window.wp );
