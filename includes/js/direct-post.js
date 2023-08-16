jQuery(($) => {
  // wc_checkout_params is required to continue, ensure the object exists
	if ( typeof wc_checkout_params === 'undefined' ) {
		return false;
	}

  $('form.checkout').on('checkout_place_order_'+gateway_option.id, function(event, wc_checkout_form) {
    if ($('input[name="wc-' + gateway_option.id + '-payment-token"]:checked').val() != 'new') {
      return true;
    }

    if ($('#' + gateway_option.id + '-card-name').val() === '') {
      wc_checkout_form.submit_error( '<div class="woocommerce-error">Card Name cannot be empty</div>' ); // eslint-disable-line max-len
      return false;
    }

    if ($('#' + gateway_option.id + '-card-number').val() === '') {
      wc_checkout_form.submit_error( '<div class="woocommerce-error">Card Number cannot be empty</div>' ); // eslint-disable-line max-len
      return false;
    }

    if ($('#' + gateway_option.id + '-card-expiry').val() === '') {
      wc_checkout_form.submit_error( '<div class="woocommerce-error">Card Expiry cannot be empty</div>' ); // eslint-disable-line max-len
      return false;
    }

    if ($('#' + gateway_option.id + '-card-cvc').val() === '') {
      wc_checkout_form.submit_error( '<div class="woocommerce-error">Card Code cannot be empty</div>' ); // eslint-disable-line max-len
      return false;
    }
    
  });

  // https://stackoverflow.com/questions/19036684/jquery-redirect-with-post-data

  $('form.checkout').on( 'checkout_place_order_success', function( event, result, wc_checkout_form ) {
    var card_expiry = $('#' + gateway_option.id + '-card-expiry').val();
    var card_no_space_expiry = card_expiry.replaceAll(' ', '');

    if (wc_checkout_form.get_payment_method() == gateway_option.id && $('input[name="wc-' + gateway_option.id + '-payment-token"]:checked').val() == 'new') {
      if(result.result == 'success') {
        var redirect_location = result.redirect;
        var form = '<input type="hidden" name="cardholder_name" value="'+$('#' + gateway_option.id + '-card-name').val()+'">';
        form += '<input type="hidden" name="card_number" value="'+$('#' + gateway_option.id + '-card-number').val()+'">';
        form += '<input type="hidden" name="expires" value="'+card_no_space_expiry+'">';
        form += '<input type="hidden" name="cvc" value="'+$('#' + gateway_option.id + '-card-cvc').val()+'">';
        
        $('<form action="'+redirect_location+'" method="POST">'+form+'</form>').appendTo('body').submit();
      }
      return false;
    }

    return true;
  });
});
