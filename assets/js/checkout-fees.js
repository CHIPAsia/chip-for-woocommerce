/**
 * checkout-fees.js.
 */

jQuery(($) => {

  const orderPayReferrer = $('input[name="_wp_http_referer"]').val();
  let referrerArr = '';
  
  if( orderPayReferrer != undefined ) {
    referrerArr = orderPayReferrer.split('/');
  }
  
  
  $('form#order_review').on('click', 'input[name="payment_method"]', () => {
    window.alert('haha');
    const order_id = ( chip_checkout_order_id.order_id ) ? chip_checkout_order_id.order_id : referrerArr[3];

    $('#place_order').prop('disabled', true);
    
    var paymentMethod = $('input[name="payment_method"]:checked').val();

    // Get Payment Title and strip out all html tags.
    var paymentMethodTitle = $(`label[for="payment_method_${paymentMethod}"]`).text().replace(/[\t\n]+/g,'').trim();

    // On visiting Pay for order page, take the payment method and payment title which are present in the order.
    if ( '' !== chip_checkout_order_id.payment_method ) {
      paymentMethod = chip_checkout_order_id.payment_method;
      paymentMethodTitle = $(`label[for="payment_method_${paymentMethod}"]`).text().replace(/[\t\n]+/g,'').trim();
    }

    const data = {
      payment_method: paymentMethod,
      payment_method_title: paymentMethodTitle,
      order_id: order_id,
      security: chip_checkout_params.update_payment_method_nonce
    };

    // We need to set the payment method blank because when second time when it comes here on changing the payment method it should take that changed value and not the payment method present in the order.
    chip_checkout_order_id.payment_method = '';
    $.post('?wc-ajax=chip_' + chip_checkout_params.chip_gateway_id + '_update_fees', data, (response) => {
    	$('#place_order').prop('disabled', false);
    	if (response && response.fragments) {
    		$('#order_review').html(response.fragments);
    		$(`input[name="payment_method"][value=${paymentMethod}]`).prop('checked', true);
    		$(`.payment_method_${paymentMethod}`).css('display', 'block');
    		$(`div.payment_box:not(".payment_method_${paymentMethod}")`).filter(':visible').slideUp(0);
    		$(document.body).trigger('updated_checkout');
    	}
    });
  });

  $('body').on('change', 'input[name="payment_method"]', function() {
    $('body').trigger('update_checkout');
  });

  $('body').on('payment_method_selected', () => {
    if ($('.woocommerce-order-pay').length === 0) {
      $('input[name="payment_method"]').trigger( 'change' ); 
    }
  });
});
