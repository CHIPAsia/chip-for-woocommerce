jQuery(($) => {
  // wc_checkout_params is required to continue, ensure the object exists
	if ( typeof wc_checkout_params === 'undefined' ) {
		return false;
	}

  // Inject CSS styles for card brand icon
  if (!document.getElementById('chip-card-brand-styles')) {
    const styles = `
      .chip-card-number-wrapper {
        position: relative;
        display: block;
      }
      .chip-card-number-wrapper input {
        padding-right: 56px !important;
      }
      .chip-card-brand-icon {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        width: 40px;
        height: 24px;
        object-fit: contain;
        pointer-events: none;
        z-index: 1;
      }
    `;
    const styleSheet = document.createElement('style');
    styleSheet.id = 'chip-card-brand-styles';
    styleSheet.textContent = styles;
    document.head.appendChild(styleSheet);
  }

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
    
    if (cardBrand && gateway_option.card_logos_url) {
      $icon.attr('src', gateway_option.card_logos_url + cardBrand + '.svg');
      $icon.attr('alt', cardBrand);
      $icon.show();
    } else {
      $icon.hide();
    }
  };

  // Listen for card number input changes
  $('body').on('input', 'input[id$="-card-number"]', function() {
    updateCardBrandIcon($(this));
  });

  // Cardholder name validation - only allow [a-zA-Z \'\.\-]
  $('body').on('keypress', 'input.wc-credit-card-form-card-name', function(e) {
    var $target, card, digit, length, re, upperLength, value;
    digit = String.fromCharCode(e.which);
    var regex = new RegExp("[a-zA-Z \'\.\-]+$");
    if (!regex.test(digit)) {
      e.preventDefault();
    }
	});

  // Expiry field formatting - auto-format as MM/YY
  $('body').on('input', 'input.wc-credit-card-form-card-expiry', function(e) {
    var $target = $(this);
    var value = $target.val();
    
    // Remove all non-digits and slashes
    var cleaned = value.replace(/[^\d]/g, '');
    
    // Limit to 4 digits (MMYY)
    if (cleaned.length > 4) {
      cleaned = cleaned.substring(0, 4);
    }
    
    // Format as MM/YY
    var formatted = '';
    if (cleaned.length >= 2) {
      formatted = cleaned.substring(0, 2) + ' / ' + cleaned.substring(2);
    } else {
      formatted = cleaned;
    }
    
    // Only update if different to avoid cursor issues
    if ($target.val() !== formatted) {
      $target.val(formatted);
    }
  });

  // Prevent non-numeric input on expiry field
  $('body').on('keypress', 'input.wc-credit-card-form-card-expiry', function(e) {
    var charCode = e.which ? e.which : e.keyCode;
    // Allow: backspace, delete, tab, escape, enter, and numbers
    if (charCode === 8 || charCode === 9 || charCode === 13 || charCode === 27 || charCode === 46) {
      return true;
    }
    // Allow numbers only
    if (charCode < 48 || charCode > 57) {
      e.preventDefault();
      return false;
    }
    return true;
  });

  // CVC field - only allow numeric input
  $('body').on('keypress', 'input.wc-credit-card-form-card-cvc', function(e) {
    var charCode = e.which ? e.which : e.keyCode;
    // Allow: backspace, delete, tab, escape, enter, and numbers
    if (charCode === 8 || charCode === 9 || charCode === 13 || charCode === 27 || charCode === 46) {
      return true;
    }
    // Allow numbers only
    if (charCode < 48 || charCode > 57) {
      e.preventDefault();
      return false;
    }
    return true;
  });

  // CVC field - remove non-numeric characters on input
  $('body').on('input', 'input.wc-credit-card-form-card-cvc', function(e) {
    var $target = $(this);
    var value = $target.val();
    var cleaned = value.replace(/[^\d]/g, '');
    if (cleaned.length > 4) {
      cleaned = cleaned.substring(0, 4);
    }
    if ($target.val() !== cleaned) {
      $target.val(cleaned);
    }
  });

  $('form.checkout').on('checkout_place_order_'+gateway_option.id, function(event, wc_checkout_form) {
    // if ($('input[name="wc-' + gateway_option.id + '-payment-token"]:checked').val() != 'new') {
    //   return true;
    // }

    if ( typeof wc_checkout_form === 'undefined' ) {
      return true;
    }

    if (typeof wc_checkout_form.submit_error !== "function") {
      return true;
    }

    if ($('.wc-payment-form').is(":hidden")) {
      return true;
    }

    if ($('#' + gateway_option.id + '-card-name').val() === '') {
      wc_checkout_form.submit_error( '<div class="woocommerce-error">Cardholder Name cannot be empty</div>' ); // eslint-disable-line max-len
      return false;
    }

    let illegal_character = /[^a-zA-Z \'\.\-]/;
    let chip_card_name = $('#' + gateway_option.id + '-card-name').val();
    if (illegal_character.test(chip_card_name)) {
      wc_checkout_form.submit_error( '<div class="woocommerce-error">Cardholder Name contains illegal character</div>' ); // eslint-disable-line max-len
      return false;
    }

    if ($('#' + gateway_option.id + '-card-number').val() === '') {
      wc_checkout_form.submit_error( '<div class="woocommerce-error">Card Number cannot be empty</div>' ); // eslint-disable-line max-len
      return false;
    }

    if ($('#' + gateway_option.id + '-card-expiry').val() === '') {
      wc_checkout_form.submit_error( '<div class="woocommerce-error">Expiry (MM/YY) cannot be empty</div>' ); // eslint-disable-line max-len
      return false;
    }

    if ($('#' + gateway_option.id + '-card-cvc').val() === '') {
      wc_checkout_form.submit_error( '<div class="woocommerce-error">CVC cannot be empty</div>' ); // eslint-disable-line max-len
      return false;
    }
    
  });

  // https://stackoverflow.com/questions/19036684/jquery-redirect-with-post-data

  $('form.checkout').on( 'checkout_place_order_success', function( event, result, wc_checkout_form ) {

    if ( typeof wc_checkout_form === 'undefined' ) {
      return;
    }

    var card_expiry = $('#' + gateway_option.id + '-card-expiry').val();
    // Remove spaces only, keeping slash for MM/YY format
    var card_no_space_expiry = card_expiry.replace(/\s/g, '');

    if (wc_checkout_form.get_payment_method() == gateway_option.id && $('.wc-payment-form').is(":visible")) {
      if(result.result == 'success') {
        var redirect_location = result.redirect;
        var form = '<input type="hidden" name="cardholder_name" value="'+$('#' + gateway_option.id + '-card-name').val()+'">';
        form += '<input type="hidden" name="card_number" value="'+$('#' + gateway_option.id + '-card-number').val()+'">';
        form += '<input type="hidden" name="expires" value="'+card_no_space_expiry+'">';
        form += '<input type="hidden" name="cvc" value="'+$('#' + gateway_option.id + '-card-cvc').val()+'">';
        
        // Check if customer wants to save the card.
        var save_card_checkbox = $('#wc-' + gateway_option.id + '-new-payment-method');
        var remember_card = (save_card_checkbox.length && save_card_checkbox.is(':checked')) ? 'on' : 'off';
        form += '<input type="hidden" name="remember_card" value="'+remember_card+'">';
        
        $('<form action="'+redirect_location+'" method="POST">'+form+'</form>').appendTo('body').submit();
      }
      return false;
    }

    return true;
  });
});
