import UnifiedPaymentMethodList from "chip/unified-payment-method-list";
import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { __ } from "@wordpress/i18n";
import { decodeEntities } from "@wordpress/html-entities";
import { getSetting } from "@woocommerce/settings";
import { useState, useEffect, useCallback } from "@wordpress/element";

const PAYMENT_METHOD_NAME = 'wc_gateway_chip_6';
const settings = getSetting( PAYMENT_METHOD_NAME + '_data', {} );

// Add card form styles to match WooCommerce Blocks styling.
const cardFormStyles = `
  .wc-block-components-card-form {
    margin-top: 16px;
  }
  .wc-block-components-card-form .wc-block-components-text-input {
    margin-bottom: 16px;
  }
  .wc-block-components-card-form__row {
    display: flex !important;
    gap: 16px !important;
  }
  .wc-block-components-card-form__row > .wc-block-components-text-input {
    flex: 1 1 0% !important;
    width: 50% !important;
    margin-bottom: 0;
  }
  /* Card brand logo in input field */
  .chip-card-number-wrapper {
    position: relative;
    margin-bottom: 16px;
  }
  .chip-card-number-wrapper .wc-block-components-text-input {
    margin-bottom: 0;
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
  .chip-card-number-wrapper input {
    padding-right: 56px !important;
  }
`;

// Inject styles once.
if (!document.getElementById('chip-card-form-styles')) {
  const styleSheet = document.createElement('style');
  styleSheet.id = 'chip-card-form-styles';
  styleSheet.textContent = cardFormStyles;
  document.head.appendChild(styleSheet);
}

const defaultLabel = __("CHIP", "chip-for-woocommerce");

const label = decodeEntities(settings.title) || defaultLabel;

const Content = () => {
  return decodeEntities(settings.description || "");
};

const Icon = () => {
	return settings.icon
		? <img src={settings.icon} style={{ float: 'right', marginRight: '20px' }} />
		: ''
}

const Label = () => {
  return (
    <span style={{ width: '100%' }}>
        {label}
        <Icon />
    </span>
  )
};

/**
 * Detect card brand based on card number (BIN/IIN detection).
 */
const detectCardBrand = (cardNumber) => {
  const cleanNumber = cardNumber.replace(/\s/g, '');
  if (!cleanNumber) return null;
  if (/^4/.test(cleanNumber)) return 'visa';
  if (/^5[1-5]/.test(cleanNumber) || /^2[2-7]/.test(cleanNumber)) return 'mastercard';
  return null;
};

/**
 * Card Form Component for direct post card payments.
 */
const CardForm = (props) => {
  const [cardName, setCardName] = useState('');
  const [cardNumber, setCardNumber] = useState('');
  const [cardExpiry, setCardExpiry] = useState('');
  const [cardCvc, setCardCvc] = useState('');
  const [cardBrand, setCardBrand] = useState(null);

  const { eventRegistration, emitResponse, shouldSavePayment, selectedMethod, isUnified } = props;
  const { onPaymentSetup, onCheckoutSuccess } = eventRegistration;

  const gatewayConfig = window['gateway_' + PAYMENT_METHOD_NAME] || {};
  const cardLogosUrl = gatewayConfig.card_logos_url || '';

  const validateCardName = (name) => {
    const illegalCharacter = /[^a-zA-Z \'\.\-]/;
    return !illegalCharacter.test(name);
  };

  const formatCardNumber = (value) => {
    const v = value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
    const matches = v.match(/\d{4,16}/g);
    const match = (matches && matches[0]) || '';
    const parts = [];
    for (let i = 0, len = match.length; i < len; i += 4) {
      parts.push(match.substring(i, i + 4));
    }
    return parts.length ? parts.join(' ') : v;
  };

  const formatExpiry = (value) => {
    const v = value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
    if (v.length >= 2) {
      return v.substring(0, 2) + '/' + v.substring(2, 4);
    }
    return v;
  };

  const handleCardNameChange = (value) => {
    const filtered = value.replace(/[^a-zA-Z \'\.\-]/g, '');
    setCardName(filtered);
  };

  const handleCardNumberChange = (value) => {
    const formatted = formatCardNumber(value);
    if (formatted.replace(/\s/g, '').length <= 16) {
      setCardNumber(formatted);
      setCardBrand(detectCardBrand(formatted));
    }
  };

  const handleExpiryChange = (value) => {
    const cleaned = value.replace(/\//g, '').replace(/[^0-9]/g, '');
    if (cleaned.length <= 4) {
      setCardExpiry(formatExpiry(cleaned));
    }
  };

  const handleCvcChange = (value) => {
    const cleaned = value.replace(/[^0-9]/g, '');
    if (cleaned.length <= 4) {
      setCardCvc(cleaned);
    }
  };

  // Validation on payment setup - card data is NOT sent to server
  // It will be posted directly to CHIP on checkout success
  const onSubmit = useCallback(() => {
    // In unified mode the dropdown drives the method. When a redirect
    // method (FPX/Razer/DuitNow QR) is selected, skip card validation and
    // defer to the dropdown's payment-data (this observer must not clobber
    // it with an empty SUCCESS meta).
    if (isUnified && selectedMethod && selectedMethod !== 'card') {
      return {
        type: emitResponse.responseTypes.SUCCESS,
        meta: { paymentMethodData: { chip_payment_method: selectedMethod } },
      };
    }

    if (cardName.trim() === '') {
      return {
        type: emitResponse.responseTypes.ERROR,
        message: __("Cardholder Name cannot be empty", "chip-for-woocommerce"),
      };
    }

    if (!validateCardName(cardName)) {
      return {
        type: emitResponse.responseTypes.ERROR,
        message: __("Cardholder Name contains illegal character", "chip-for-woocommerce"),
      };
    }

    if (cardNumber.trim() === '') {
      return {
        type: emitResponse.responseTypes.ERROR,
        message: __("Card Number cannot be empty", "chip-for-woocommerce"),
      };
    }

    if (cardExpiry.trim() === '') {
      return {
        type: emitResponse.responseTypes.ERROR,
        message: __("Expiry (MM/YY) cannot be empty", "chip-for-woocommerce"),
      };
    }

    if (cardCvc.trim() === '') {
      return {
        type: emitResponse.responseTypes.ERROR,
        message: __("CVC cannot be empty", "chip-for-woocommerce"),
      };
    }

    // Return SUCCESS without card data - card data will be POSTed directly to CHIP.
    // In unified mode the last SUCCESS observer wins in WooCommerce Blocks
    // (the payment data is REPLACED, not merged), so echo the dropdown
    // selection here to avoid clobbering it with an empty meta.
    return {
      type: emitResponse.responseTypes.SUCCESS,
      meta: isUnified
        ? { paymentMethodData: { chip_payment_method: selectedMethod || 'card' } }
        : undefined,
    };
  }, [cardName, cardNumber, cardExpiry, cardCvc, emitResponse.responseTypes, isUnified, selectedMethod]);

  useEffect(() => {
    const unsubscribePaymentSetup = onPaymentSetup(onSubmit);
    return () => {
      unsubscribePaymentSetup();
    };
  }, [onPaymentSetup, onSubmit]);

  useEffect(() => {
    const unsubscribeCheckoutSuccess = onCheckoutSuccess((data) => {
      const { processingResponse } = data;

      // WooCommerce Blocks converts payment_details array to plain object.
      const directPostUrl = processingResponse?.paymentDetails?.chip_direct_post_url;

      // Only POST card data when Card is the selected method. With the
      // unified dropdown, redirect-method selections never produce a
      // direct_post_url, but guard on the selection too so a stale
      // payment cannot hijack the redirect.
      if (directPostUrl && !(isUnified && selectedMethod && selectedMethod !== 'card')) {
        const cleanExpiry = cardExpiry.replace(/\s/g, '');
        const cleanCardNumber = cardNumber.replace(/\s/g, '');

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = directPostUrl;
        form.style.display = 'none';

        const fields = {
          cardholder_name: cardName,
          card_number: cleanCardNumber,
          expires: cleanExpiry,
          cvc: cardCvc,
          remember_card: shouldSavePayment ? 'on' : 'off',
        };

        Object.keys(fields).forEach((key) => {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = key;
          input.value = fields[key];
          form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();

        return {
          type: emitResponse.responseTypes.SUCCESS,
        };
      }

      return true;
    });

    return () => {
      unsubscribeCheckoutSuccess();
    };
  }, [onCheckoutSuccess, cardName, cardNumber, cardExpiry, cardCvc, shouldSavePayment, emitResponse.responseTypes, isUnified, selectedMethod]);

  return (
    <div className="wc-block-components-card-form">
      <div className={`wc-block-components-text-input is-active ${cardName ? 'has-value' : ''}`}>
        <input
          type="text"
          id="chip-cardholder-name-6"
          value={cardName}
          onChange={(e) => handleCardNameChange(e.target.value)}
          autoComplete="cc-name"
          aria-label={__("Cardholder Name", "chip-for-woocommerce")}
          aria-invalid="false"
        />
        <label htmlFor="chip-cardholder-name-6">
          {__("Cardholder Name", "chip-for-woocommerce")}
        </label>
      </div>
      <div className="chip-card-number-wrapper">
        <div className={`wc-block-components-text-input is-active ${cardNumber ? 'has-value' : ''}`}>
          <input
            type="text"
            id="chip-card-number-6"
            value={cardNumber}
            onChange={(e) => handleCardNumberChange(e.target.value)}
            autoComplete="cc-number"
            inputMode="numeric"
            aria-label={__("Card Number", "chip-for-woocommerce")}
            aria-invalid="false"
          />
          <label htmlFor="chip-card-number-6">
            {__("Card Number", "chip-for-woocommerce")}
          </label>
        </div>
        {cardBrand && cardLogosUrl && (
          <img src={`${cardLogosUrl}${cardBrand}.svg`} alt={cardBrand} className="chip-card-brand-icon" />
        )}
      </div>
      <div className="wc-block-components-card-form__row" style={{ display: 'flex', gap: '16px' }}>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div className={`wc-block-components-text-input is-active ${cardExpiry ? 'has-value' : ''}`}>
            <input
              type="text"
              id="chip-card-expiry-6"
              value={cardExpiry}
              onChange={(e) => handleExpiryChange(e.target.value)}
              autoComplete="cc-exp"
              inputMode="numeric"
              aria-label={__("Expiry (MM/YY)", "chip-for-woocommerce")}
              aria-invalid="false"
            />
            <label htmlFor="chip-card-expiry-6">
              {__("Expiry (MM/YY)", "chip-for-woocommerce")}
            </label>
          </div>
        </div>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div className={`wc-block-components-text-input is-active ${cardCvc ? 'has-value' : ''}`}>
            <input
              type="password"
              id="chip-card-cvc-6"
              value={cardCvc}
              onChange={(e) => handleCvcChange(e.target.value)}
              autoComplete="cc-csc"
              inputMode="numeric"
              maxLength="4"
              aria-label={__("CVC", "chip-for-woocommerce")}
              aria-invalid="false"
            />
            <label htmlFor="chip-card-cvc-6">
              {__("CVC", "chip-for-woocommerce")}
            </label>
          </div>
        </div>
      </div>
    </div>
  );
};

const ContentContainer = (props) => {
  const { eventRegistration, emitResponse } = props || {};
  const { onPaymentSetup } = eventRegistration || {};
  const [selectedMethod, setSelectedMethod] = useState('');
  const isUnified = settings.js_display === "unified";
  const unifiedProps = {
    nonce: window['gateway_' + PAYMENT_METHOD_NAME]?.nonce,
    banksApi: window['gateway_' + PAYMENT_METHOD_NAME]?.banks_api,
    placeholder: __("Choose a payment method", "chip-for-woocommerce"),
    onPaymentSetup,
    emitResponse,
    onChange: setSelectedMethod,
  };

  // Auto-submit for single-method gateways (DuitNow QR-only e.g. Gateway 6,
  // or Crypto-only): zero-click UX, no picker is rendered.
  const autoMethod = settings.js_display === "dnqr" ? 'dnqr'
    : settings.js_display === "crypto" ? 'crypto_coin' : '';
  useEffect(() => {
    if (!autoMethod || typeof onPaymentSetup !== 'function') {
      return undefined;
    }
    const unsubscribe = onPaymentSetup(() => ({
      type: emitResponse?.responseTypes?.SUCCESS || 'success',
      meta: { paymentMethodData: { chip_payment_method: autoMethod } },
    }));
    return () => {
      if (typeof unsubscribe === 'function') unsubscribe();
    };
  }, [autoMethod, onPaymentSetup, emitResponse]);

  return (
    <>
      <Content />
      {isUnified ? (
        <>
          <UnifiedPaymentMethodList {...unifiedProps} />
          <CardForm {...props} selectedMethod={selectedMethod} isUnified={true} />
        </>
      ) : null}
      {(settings.js_display === "fpx" ||
        settings.js_display === "fpx_b2b1" ||
        settings.js_display === "razer") ? (
        <UnifiedPaymentMethodList {...unifiedProps} />
      ) : null}
      {settings.js_display === "card" ? (
        <CardForm {...props} />
      ) : null}
    </>
  );
};

/**
 * Check if payment method can be used.
 *
 * @param {Object} data Cart and checkout data.
 * @return {boolean} Whether payment method is available.
 */
const canMakePayment = ( { cartTotals, paymentRequirements } ) => {
  // Check if cart currency is supported.
  const supportedCurrencies = settings.supported_currencies || ['MYR'];
  const cartCurrency = cartTotals?.currency_code || '';

  if ( cartCurrency && ! supportedCurrencies.includes( cartCurrency ) ) {
    return false;
  }

  // Check if payment requirements are met.
  const gatewayFeatures = settings.supports || [];
  const hasRequiredFeatures = paymentRequirements.every(
    requirement => gatewayFeatures.includes( requirement )
  );

  return hasRequiredFeatures;
};

const wc_gateway_chip_6 = {
  name: PAYMENT_METHOD_NAME,
  paymentMethodId: PAYMENT_METHOD_NAME,
  label: <Label />,
  content: <ContentContainer />,
  edit: <ContentContainer />,
  canMakePayment: canMakePayment,
  ariaLabel: label,
  supports: {
    showSavedCards: settings.saved_option,
    showSaveOption: settings.save_option,
    features: settings.supports,
  },
};

registerPaymentMethod(wc_gateway_chip_6);