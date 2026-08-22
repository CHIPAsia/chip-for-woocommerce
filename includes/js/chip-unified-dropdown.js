jQuery( ( $ ) => {
	/**
	 * Unified payment-method dropdown (classic checkout).
	 *
	 * Enhances every <select name="chip_payment_method_<gateway_id>">
	 * rendered by payment_fields() with the same UX the legacy single-method
	 * dropdowns had: selectWoo init, bank/e-wallet logos, and offline-bank
	 * disabling.
	 *
	 * The field name is scoped per gateway ID so multiple CHIP clones rendered
	 * in one form (order-pay) don't collide. The hidden mirror field carries
	 * the same scoped name so it survives updated_checkout rebuilds.
	 *
	 * IMPORTANT: this script is enqueued once per active CHIP gateway, but
	 * wp_localize_script() writes every clone's data to the SAME global
	 * 'gateway_unified_option' (last one wins). We therefore never read
	 * gateway_unified_option.id — the gateway id is derived from each select's
	 * own name attribute, and every scoped select is enhanced in a single
	 * pass guarded by a window flag so the N enqueued copies don't re-init.
	 *
	 * Localized data (gateway_unified_option.unified) is identical across all
	 * clones (shared assets + shared FPX health-check), so reading it from
	 * whichever copy won is safe:
	 *   - fpx_logo_base    (string)  Base URL for FPX bank logos (assets/fpx_bank/).
	 *   - razer_logo_base  (string)  Base URL for Razer e-wallet logos (assets/razer_ewallet/).
	 *   - dnqr_logo_url    (string)  URL of the DuitNow QR logo.
	 *   - card_logo_url    (string)  URL of the Card logo.
	 *   - unavailable_fpx  (array)   Offline FPX B2C bank codes to disable.
	 *   - unavailable_b2b1 (array)   Offline FPX B2B1 bank codes to disable.
	 */

	if ( typeof gateway_unified_option === 'undefined' || typeof gateway_unified_option.unified === 'undefined' ) {
		return;
	}

	// Guard against the multiple enqueued copies of this script (one per
	// active CHIP clone). Only the first copy performs the enhancement pass.
	if ( window.__chipUnifiedDropdownInit ) {
		return;
	}
	window.__chipUnifiedDropdownInit = true;

	var unified = gateway_unified_option.unified;

	// Dropdown option logo styles (mirrors the legacy bank dropdown styles).
	if ( ! document.getElementById( 'chip-unified-dropdown-styles' ) ) {
		var styleSheet = document.createElement( 'style' );
		styleSheet.id = 'chip-unified-dropdown-styles';
		styleSheet.textContent =
			'.chip-unified-option { display: flex; align-items: center; gap: 10px; }' +
			'.chip-unified-option-logo { width: 32px; height: 32px; object-fit: contain; flex-shrink: 0; }' +
			'.chip-unified-option-text { flex: 1; }' +
			'.select2-results__option .chip-unified-option,' +
			'.select2-selection__rendered .chip-unified-option { display: flex; align-items: center; }';
		document.head.appendChild( styleSheet );
	}

	var logoForTag = function( tag ) {
		var parts = tag.split( ':' );
		var type  = parts[0];
		var code  = parts.length > 1 ? parts[1] : '';

		if ( 'fpx' === type || 'fpx_b2b1' === type ) {
			return unified.fpx_logo_base ? unified.fpx_logo_base + code + '.png' : '';
		}
		if ( 'razer' === type ) {
			return unified.razer_logo_base ? unified.razer_logo_base + code + '.png' : '';
		}
		if ( 'dnqr' === tag ) {
			return unified.dnqr_logo_url || '';
		}
		if ( 'card' === tag ) {
			return unified.card_logo_url || '';
		}
		return '';
	};

	var isUnavailable = function( tag ) {
		var parts = tag.split( ':' );
		var type  = parts[0];
		var code  = parts.length > 1 ? parts[1] : '';
		if ( 'fpx' === type ) {
			return $.inArray( code, unified.unavailable_fpx || [] ) !== -1;
		}
		if ( 'fpx_b2b1' === type ) {
			return $.inArray( code, unified.unavailable_b2b1 || [] ) !== -1;
		}
		return false;
	};

	var formatOption = function( option ) {
		if ( ! option.id || ! option.text ) {
			return option.text;
		}
		var $option = $( '<span class="chip-unified-option"><span class="chip-unified-option-text">' + option.text + '</span></span>' );
		var logoUrl = logoForTag( option.id );
		if ( logoUrl ) {
			$option.prepend(
				'<img class="chip-unified-option-logo" src="' + logoUrl +
				'" onerror="this.style.display=\'none\'" alt="" />'
			);
		}
		return $option;
	};

	var formatSelection = function( option ) {
		return option.text || '';
	};

	// Preserve each gateway's dropdown selection across updated_checkout
	// AJAX refreshes. WooCommerce rebuilds the payment-box HTML on
	// updated_checkout, creating a fresh <select> that loses the chosen
	// value. SelectWoo then auto-picks the first non-empty option (e.g.
	// dnqr), so the form submits the wrong payment method.
	//
	// We use a hidden <input> that WooCommerce's checkout.js serialize()
	// always reads (hidden inputs are never stripped from a rebuild the
	// way a SelectWoo-enhanced <select> can be) and sync the <select>
	// to it on every init. The hidden input is added to the <form> once
	// and survives updated_checkout because it is outside the payment-box
	// fragment that WooCommerce replaces.
	var savedByGateway = {};

	var syncHiddenInput = function( $select, gatewayId ) {
		var $hidden = $( 'input[name="chip_payment_method_' + gatewayId + '_hidden"]' );
		if ( $hidden.length === 0 ) {
			$hidden = $( '<input type="hidden" name="chip_payment_method_' + gatewayId + '_hidden" value="" />' );
			$( 'form.checkout, form#order_review' ).append( $hidden );
		}
		// When the select changes, update the hidden field.
		$select.off( 'change.chipHidden' ).on( 'change.chipHidden', function() {
			savedByGateway[ gatewayId ] = $( this ).val() || '';
			$hidden.val( savedByGateway[ gatewayId ] );
		} );
		// Restore from hidden field if available.
		if ( $hidden.val() ) {
			savedByGateway[ gatewayId ] = $hidden.val();
		}
		if ( savedByGateway[ gatewayId ] ) {
			$select.val( savedByGateway[ gatewayId ] );
		}
	};

	var initUnifiedDropdown = function() {
		// Enhance every scoped select, deriving the gateway id from the
		// select's own name (never from the shared localized global).
		$( '.chip-unified-payment-method select[name^="chip_payment_method_"]' ).each( function() {
			var $select   = $( this );
			var gatewayId = $select.attr( 'name' ).replace( 'chip_payment_method_', '' );

			// Skip selects already enhanced by selectWoo (it adds the
			// select2-hidden-accessible class and hides the original).
			if ( $select.hasClass( 'select2-hidden-accessible' ) ) {
				return;
			}

			// Restore the previously selected value if the select was rebuilt.
			if ( savedByGateway[ gatewayId ] ) {
				$select.val( savedByGateway[ gatewayId ] );
			}

			// Disable offline banks before selectWoo is applied so the
			// placeholder option is not affected.
			$select.find( 'option' ).each( function() {
				if ( isUnavailable( $( this ).val() ) ) {
					$( this ).prop( 'disabled', true );
				}
			} );

			if ( $.fn.selectWoo ) {
				$select.selectWoo( {
					placeholder: $select.find( 'option[value=""]' ).text() || 'Choose a payment method',
					allowClear: false,
					width: '100%',
					templateResult: formatOption,
					templateSelection: formatSelection,
				} );
			}

			// Persist the selection whenever the customer changes it.
			$select.on( 'change', function() {
				savedByGateway[ gatewayId ] = $( this ).val() || '';
			} );

			// Sync with hidden input (survives updated_checkout rebuilds).
			syncHiddenInput( $select, gatewayId );
		} );
	};

	// Run on initial load (document.ready) so order-pay and direct page loads
	// get the logos too, not only AJAX-driven checkout updates.
	$( function() {
		initUnifiedDropdown();
	} );

	$( document.body ).on( 'updated_checkout', initUnifiedDropdown );
} );
