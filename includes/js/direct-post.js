jQuery(($) => {
  // wc_checkout_params is required to continue, ensure the object exists
  if ( typeof wc_checkout_params === 'undefined' ) {
    return false;
  }

  // Default card-logos URL (all CHIP gateways share the same assets/ dir).
  var CARD_LOGOS_URL = (typeof gateway_option !== 'undefined' && gateway_option.card_logos_url)
    ? gateway_option.card_logos_url
    : (window.chip_card_logos_url || '');

  // Inject CSS styles for card brand icon
  if (!document.getElementById('chip-card-brand-styles')) {
    const styles = `
      .chip-card-number-wrapper {
        position: relative !important;
        display: block !important;
      }
      .chip-card-number-wrapper input[type="tel"],
      .chip-card-number-wrapper input.input-text {
        padding-right: 60px !important;
        box-sizing: border-box;
      }
      .chip-card-number-wrapper .chip-card-brand-icon {
        position: absolute !important;
        right: 10px !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
        width: 40px !important;
        height: 25px !important;
        object-fit: contain !important;
        pointer-events: none !important;
        z-index: 10 !important;
        margin: 0 !important;
        padding: 0 !important;
        display: block !important;
      }
      .chip-card-number-wrapper .chip-card-brand-icon.chip-hidden {
        display: none !important;
      }
    `;
    const styleSheet = document.createElement('style');
    styleSheet.id = 'chip-card-brand-styles';
    styleSheet.textContent = styles;
    document.head.appendChild(styleSheet);
  }

  // Several CHIP clones may be active and each wp_localize_script() writes to
  // the same global 'gateway_option' (last one wins). We therefore scope every
  // lookup to the ACTIVE gateway's <li> at submit time instead of relying on
  // the global, so the card POST fires for whichever gateway is checked.

  // The currently checked CHIP gateway's id + box.
  var activeCardGateway = function() {
    var methodId = '';
    var $box = null;
    $( 'input[name="payment_method"]' ).each( function() {
      if ( this.checked ) {
        methodId = this.value;
        $box = $( this ).closest( 'li.wc_payment_method' );
      }
    } );
    if ( ! $box || ! $box.length || ! methodId ) {
      return null;
    }
    return { id: methodId, $box: $box };
  };

  // The set of CHIP gateway ids present in the DOM with a card form.
  var chipCardGatewayIds = function() {
    var ids = [];
    $( 'li.wc_payment_method[class*="payment_method_wc_gateway_chip"]' ).each( function() {
      var classes = (this.className || '').split( /\s+/ );
      var methodId = '';
      for ( var i = 0; i < classes.length; i++ ) {
        if ( classes[i].indexOf( 'payment_method_wc_gateway_chip' ) === 0 ) {
          methodId = classes[i].replace( 'payment_method_', '' );
          break;
        }
      }
      if ( methodId && $( this ).find( '#wc-' + methodId + '-cc-form' ).length > 0 ) {
        ids.push( methodId );
      }
    } );
    return ids;
  };

  // Card brand detection based on card number (BIN/IIN detection)
  const detectCardBrand = (cardNumber) => {
    const cleanNumber = cardNumber.replace(/\s/g, '');
    if (!cleanNumber) return null;
    if (/^4/.test(cleanNumber)) return 'visa';
    if (/^5[1-5]/.test(cleanNumber) || /^2[2-7]/.test(cleanNumber)) return 'mastercard';
    return null;
  };

  // Update card brand icon on card number input
  const updateCardBrandIcon = ($input) => {
    const cardNumber = $input.val();
    const cardBrand = detectCardBrand(cardNumber);
    const $wrapper = $input.closest('.chip-card-number-wrapper');
    const $icon = $wrapper.find('.chip-card-brand-icon');

    if (cardBrand && CARD_LOGOS_URL) {
      $icon.attr('src', CARD_LOGOS_URL + cardBrand + '.svg');
      $icon.attr('alt', cardBrand);
      $icon.removeClass('chip-hidden');
    } else {
      $icon.addClass('chip-hidden');
    }
  };

  // Card number formatting - format as 1234 5678 9012 3456
  $('body').on('input', 'input[id$="-card-number"]', function(e) {
    var $target = $(this);
    var value = $target.val();

    var cleaned = value.replace(/\D/g, '');
    if (cleaned.length > 16) cleaned = cleaned.substring(0, 16);

    var formatted = cleaned.replace(/(\d{4})(?=\d)/g, '$1 ');
    if ($target.val() !== formatted) $target.val(formatted);

    updateCardBrandIcon($target);
  });

  // Cardholder name validation - only allow [a-zA-Z \'\.\-]
  $('body').on('keypress', 'input[id$="-card-name"]', function(e) {
    var digit = String.fromCharCode(e.which);
    var regex = new RegExp("[a-zA-Z \'\.\-]+$");
    if (!regex.test(digit)) e.preventDefault();
  });

  // Expiry field formatting - auto-format as MM / YY
  $('body').on('input', 'input[id$="-card-expiry"]', function(e) {
    var $target = $(this);
    var value = $target.val();

    var cleaned = value.replace(/\D/g, '');
    if (cleaned.length > 4) cleaned = cleaned.substring(0, 4);

    var formatted = '';
    if (cleaned.length >= 2) formatted = cleaned.substring(0, 2) + ' / ' + cleaned.substring(2);
    else formatted = cleaned;

    if ($target.val() !== formatted) $target.val(formatted);
  });

  // Prevent non-numeric input on expiry field
  $('body').on('keypress', 'input[id$="-card-expiry"]', function(e) {
    var charCode = e.which ? e.which : e.keyCode;
    if (charCode === 8 || charCode === 9 || charCode === 13 || charCode === 27 || charCode === 46) return true;
    if (charCode < 48 || charCode > 57) { e.preventDefault(); return false; }
    return true;
  });

  // CVC field - only allow numeric input
  $('body').on('keypress', 'input[id$="-card-cvc"]', function(e) {
    var charCode = e.which ? e.which : e.keyCode;
    if (charCode === 8 || charCode === 9 || charCode === 13 || charCode === 27 || charCode === 46) return true;
    if (charCode < 48 || charCode > 57) { e.preventDefault(); return false; }
    return true;
  });

  // CVC field - remove non-numeric characters on input
  $('body').on('input', 'input[id$="-card-cvc"]', function(e) {
    var $target = $(this);
    var value = $target.val();
    var cleaned = value.replace(/\D/g, '');
    if (cleaned.length > 4) cleaned = cleaned.substring(0, 4);
    if ($target.val() !== cleaned) $target.val(cleaned);
  });

  // Bind a per-gateway card validation handler for every CHIP clone that has
  // a card form, so validation runs for whichever gateway the customer picks.
  var bindValidation = function( methodId ) {
    $( 'form.checkout' ).on( 'checkout_place_order_' + methodId, function( event, wc_checkout_form ) {
      if ( typeof wc_checkout_form === 'undefined' || typeof wc_checkout_form.submit_error !== 'function' ) {
        return true;
      }
      var ctx = activeCardGateway();
      if ( ! ctx || ctx.id !== methodId ) {
        return true;
      }
      var $cardForm = $( '#wc-' + methodId + '-cc-form' );
      // Card validation only applies when the card form is visible AND (in
      // unified mode) the customer selected Card. Redirect methods never
      // carry card data.
      if ( ! $cardForm.is( ':visible' ) ) {
        return true;
      }
      var $unified = ctx.$box.find( 'select[name^="chip_payment_method_"]' );
      if ( $unified.length && $unified.val() !== 'card' ) {
        return true;
      }
      if ( $( '#' + methodId + '-card-name' ).val() === '' ) {
        wc_checkout_form.submit_error( '<div class="woocommerce-error">Cardholder Name cannot be empty</div>' );
        return false;
      }
      var illegal_character = /[^a-zA-Z \'\.\-]/;
      if ( illegal_character.test( $( '#' + methodId + '-card-name' ).val() ) ) {
        wc_checkout_form.submit_error( '<div class="woocommerce-error">Cardholder Name contains illegal character</div>' );
        return false;
      }
      if ( $( '#' + methodId + '-card-number' ).val() === '' ) {
        wc_checkout_form.submit_error( '<div class="woocommerce-error">Card Number cannot be empty</div>' );
        return false;
      }
      if ( $( '#' + methodId + '-card-expiry' ).val() === '' ) {
        wc_checkout_form.submit_error( '<div class="woocommerce-error">Expiry (MM/YY) cannot be empty</div>' );
        return false;
      }
      if ( $( '#' + methodId + '-card-cvc' ).val() === '' ) {
        wc_checkout_form.submit_error( '<div class="woocommerce-error">CVC cannot be empty</div>' );
        return false;
      }
      return true;
    } );
  };

  // Bind card POST on checkout success for the active gateway.
  $('form.checkout').on( 'checkout_place_order_success', function( event, result, wc_checkout_form ) {
    if ( typeof wc_checkout_form === 'undefined' ) {
      return;
    }
    var ctx = activeCardGateway();
    if ( ! ctx ) {
      return true;
    }
    var methodId = ctx.id;
    var $cardForm = ctx.$box.find( '#wc-' + methodId + '-cc-form' );
    if ( ! $cardForm.is( ':visible' ) ) {
      return true;
    }
    var $unified = ctx.$box.find( 'select[name^="chip_payment_method_"]' );
    if ( $unified.length && $unified.val() !== 'card' ) {
      return true;
    }
    if ( result.result === 'success' ) {
      var redirect_location = result.redirect;
      var card_expiry = $( '#' + methodId + '-card-expiry' ).val().replace(/\s/g, '');
      var form = '<input type="hidden" name="cardholder_name" value="' + $( '#' + methodId + '-card-name' ).val() + '">';
      form += '<input type="hidden" name="card_number" value="' + $( '#' + methodId + '-card-number' ).val() + '">';
      form += '<input type="hidden" name="expires" value="' + card_expiry + '">';
      form += '<input type="hidden" name="cvc" value="' + $( '#' + methodId + '-card-cvc' ).val() + '">';
      var save_card_checkbox = $( '#wc-' + methodId + '-new-payment-method' );
      var remember_card = ( save_card_checkbox.length && save_card_checkbox.is(':checked') ) ? 'on' : 'off';
      form += '<input type="hidden" name="remember_card" value="' + remember_card + '">';
      $('<form action="' + redirect_location + '" method="POST">' + form + '</form>').appendTo('body').submit();
      return false;
    }
    return true;
  });

  // Bind validation for all CHIP card gateways present on load.
  $( function() {
    var ids = chipCardGatewayIds();
    ids.forEach( function( id ) { bindValidation( id ); } );
  } );
});
