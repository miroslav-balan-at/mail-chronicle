( function () {
	'use strict';

	var providerSelect = document.getElementById( 'provider' );

	function showWebhookSection() {
		var providerValue = providerSelect ? providerSelect.value : 'WordPress';
		document.querySelectorAll( '.mc-webhook-section' ).forEach( function ( section ) {
			section.style.display = 'none';
		} );
		var target = document.querySelector( '.mc-webhook-' + providerValue );
		if ( target ) {
			target.style.display = '';
		}
	}

	if ( providerSelect ) {
		providerSelect.addEventListener( 'change', showWebhookSection );
		showWebhookSection();
	}

	document.querySelectorAll( '.mc-copy-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var clipboardText = btn.getAttribute( 'data-clipboard-text' );
			var labelEl       = btn.querySelector( '.mc-copy-btn__label' );

			if ( ! clipboardText || ! labelEl ) {
				return;
			}

			navigator.clipboard.writeText( clipboardText ).then( function () {
				btn.classList.add( 'mc-copy-btn--copied' );
				labelEl.textContent = window.mailChronicleSettings.i18n.copied;
				setTimeout( function () {
					btn.classList.remove( 'mc-copy-btn--copied' );
					labelEl.textContent = window.mailChronicleSettings.i18n.copy;
				}, 2000 );
			} );
		} );
	} );
} )();
