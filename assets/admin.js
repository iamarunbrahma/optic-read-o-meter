( function( $ ) {
	$( function() {
		var data = window.optromAdmin || {};
		var defaults = data.defaults || {};

		var form = document.getElementById( 'optrom-form' );
		var preview = document.getElementById( 'optrom-preview-badge' );
		var iconHost = document.getElementById( 'optrom-icon-templates' );
		if ( ! form || ! preview || ! iconHost ) return;
		var iconSlot = preview.querySelector( '.optrom-icon-slot' );
		var textSlot = preview.querySelector( '.optrom-text' );

		// Cache one cloneable Element per icon key from the <template>.
		var iconNodes = {};
		var srcRoot = iconHost.content || iconHost;
		srcRoot.querySelectorAll( '[data-icon]' ).forEach( function( el ) {
			iconNodes[ el.dataset.icon ] = el;
		} );

		function field( name ) {
			return form.querySelector( '[name="optrom_settings[' + name + ']"]' );
		}
		function val( name, fallback ) {
			var el = field( name );
			return el && el.value !== '' ? el.value : ( fallback || '' );
		}

		function setIcon( name ) {
			while ( iconSlot.firstChild ) iconSlot.removeChild( iconSlot.firstChild );
			var src = iconNodes[ name ];
			if ( ! src ) return;
			// Clone the wrapper's children (the SVG, if any). Wrapper itself stays in the template.
			for ( var i = 0; i < src.childNodes.length; i++ ) {
				iconSlot.appendChild( src.childNodes[ i ].cloneNode( true ) );
			}
		}

		function syncSwatchActiveState() {
			var color = val( 'color', '' ).toLowerCase();
			var bg = val( 'bg', '' ).toLowerCase();
			document.querySelectorAll( '.optrom-palette-swatch' ).forEach( function( btn ) {
				var match = btn.dataset.color.toLowerCase() === color && btn.dataset.bg.toLowerCase() === bg;
				btn.classList.toggle( 'is-active', match );
			} );
		}

		function updatePreview() {
			var style = val( 'style', 'pill' );
			preview.className = 'optrom-reading-time optrom-style-' + style;

			var color = val( 'color', defaults.color || '#065F46' );
			var bg = val( 'bg', defaults.bg || '#D1FAE5' );
			preview.style.setProperty( '--optrom-color', color );
			preview.style.setProperty( '--optrom-bg', bg );

			setIcon( val( 'icon', 'clock' ) );

			var singular = val( 'template', defaults.template || '%s min read' );
			var plural = val( 'template_plural', '' );
			var tpl = ( plural && plural.indexOf( '%s' ) !== -1 ) ? plural : singular;
			if ( ! tpl || tpl.indexOf( '%s' ) === -1 ) tpl = '%s min read';
			var label = tpl.replace( '%s', '3' );
			textSlot.textContent = label;
			preview.setAttribute( 'aria-label', label );

			syncSwatchActiveState();
		}

		// wpColorPicker: the only place jQuery is required.
		$( '.optrom-color-field' ).wpColorPicker( {
			change: function() { setTimeout( updatePreview, 30 ); },
			clear:  function() { setTimeout( updatePreview, 30 ); }
		} );

		form.addEventListener( 'input', updatePreview );
		form.addEventListener( 'change', updatePreview );

		document.querySelectorAll( '.optrom-palette-swatch' ).forEach( function( btn ) {
			btn.addEventListener( 'click', function() {
				$( '#optrom_color' ).wpColorPicker( 'color', btn.dataset.color );
				$( '#optrom_bg' ).wpColorPicker( 'color', btn.dataset.bg );
				setTimeout( updatePreview, 30 );
			} );
		} );

		var resetBtn = document.getElementById( 'optrom-reset-btn' );
		if ( resetBtn ) {
			resetBtn.addEventListener( 'click', function() {
				function set( name, value ) {
					var el = field( name );
					if ( ! el ) return;
					if ( el.type === 'checkbox' ) {
						el.checked = !! value;
					} else {
						el.value = value;
					}
				}
				set( 'wpm', defaults.wpm );
				set( 'count_images', defaults.count_images );
				set( 'seconds_per_image', defaults.seconds_per_image );
				set( 'min_words', defaults.min_words );
				set( 'style', defaults.style );
				set( 'icon', defaults.icon );
				set( 'position', defaults.position );
				set( 'template', defaults.template );
				set( 'template_plural', defaults.template_plural );

				var defaultPostTypes = defaults.post_types || [];
				document.querySelectorAll( 'input[name="optrom_settings[post_types][]"]' ).forEach( function( cb ) {
					cb.checked = defaultPostTypes.indexOf( cb.value ) !== -1;
				} );

				$( '#optrom_color' ).wpColorPicker( 'color', defaults.color );
				$( '#optrom_bg' ).wpColorPicker( 'color', defaults.bg );
				setTimeout( updatePreview, 30 );
			} );
		}

		updatePreview();
	} );
} )( jQuery );
