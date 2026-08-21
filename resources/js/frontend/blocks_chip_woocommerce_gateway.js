import UnifiedPaymentMethodList from "chip/unified-payment-method-list";
import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { __ } from "@wordpress/i18n";
import { decodeEntities } from "@wordpress/html-entities";
import { getSetting } from "@woocommerce/settings";
import { useState, useEffect, useCallback } from "@wordpress/element";

const PAYMENT_METHOD_NAME = 'wc_gateway_chip';
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
  /* Hide the WooCommerce Blocks "Save payment information" checkbox unless
   * a card payment method is the active selection. Mirrors the Stripe
   * gateway's showSaveOptionByMethod behaviour: the checkbox is only
   * meaningful for card (reusable) methods. Toggled via a body class so it
   * survives Blocks unmount/remount of the checkbox (see below). */
  body.chip-hide-save-checkbox .wc-block-components-payment-methods__save-card-info {
    display: none !important;
  }
`;

// Inject styles once.
if (!document.getElementById('chip-card-form-styles')) {
  const styleSheet = document.createElement('style');
  styleSheet.id = 'chip-card-form-styles';
  styleSheet.textContent = cardFormStyles;
  document.head.appendChild(styleSheet);
}

const HIDE_SAVE_CHECKBOX_CLASS = 'chip-hide-save-checkbox';

/**
 * Show the WooCommerce Blocks "Save payment information" checkbox only when
 * a Card payment method is the active selection. Blocks renders the checkbox
 * statically for any gateway whose supports.showSaveOption is true, so we
 * toggle a body class (like Stripe's handleDisplayOfSavingCheckbox) that a
 * stylesheet rule turns into display:none. A CSS selector is used instead of
 * inline style because Blocks can unmount/remount the checkbox element when a
 * signed-in user toggles between saved tokens and a new payment method, which
 * would lose an inline style set directly on the DOM node.
 *
 * @param {boolean} isCard Whether the currently selected method is card.
 */
function toggleSaveCheckbox( isCard ) {
  document.body.classList.toggle( HIDE_SAVE_CHECKBOX_CLASS, ! isCard );
  if ( !isCard && typeof window?.wp?.data?.dispatch === 'function' ) {
    try {
      const paymentStore = window.wp.data.dispatch( 'wc/store/payment' );
      if ( paymentStore && typeof paymentStore.__internalSetShouldSavePaymentMethod === 'function' ) {
        paymentStore.__internalSetShouldSavePaymentMethod( false );
      }
    } catch ( e ) {
      // Store unavailable (classic-only contexts) — nothing to clear.
    }
  }
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
 * Returns 'visa', 'mastercard', or null.
 */
const detectCardBrand = (cardNumber) => {
  const cleanNumber = cardNumber.replace(/\s/g, '');
  if (!cleanNumber) return null;

  // Visa: starts with 4
  if (/^4/.test(cleanNumber)) {
    return 'visa';
  }

  // Mastercard: starts with 51-55 or 2221-2720
  if (/^5[1-5]/.test(cleanNumber) || /^2[2-7]/.test(cleanNumber)) {
    return 'mastercard';
  }

  return null;
};

/**
 * Card Form Component for direct post card payments.
 * Implements the same validation as direct-post.js for legacy checkout.
 */
const CardForm = (props) => {
  const [cardName, setCardName] = useState('');
  const [cardNumber, setCardNumber] = useState('');
  const [cardExpiry, setCardExpiry] = useState('');
  const [cardCvc, setCardCvc] = useState('');
  const [cardBrand, setCardBrand] = useState(null);

  const { eventRegistration, emitResponse, shouldSavePayment, selectedMethod, isUnified } = props;
  const { onPaymentSetup, onCheckoutSuccess } = eventRegistration;

  // Get card logos URL from gateway config.
  const gatewayConfig = window['gateway_' + PAYMENT_METHOD_NAME] || {};
  const cardLogosUrl = gatewayConfig.card_logos_url || '';

  // Validate cardholder name - only allow [a-zA-Z \'\.\-]
  const validateCardName = (name) => {
    const illegalCharacter = /[^a-zA-Z \'\.\-]/;
    return !illegalCharacter.test(name);
  };

  // Format card number with spaces (e.g., 4111 1111 1111 1111)
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

  // Format expiry date (MM/YY)
  const formatExpiry = (value) => {
    const v = value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
    if (v.length >= 2) {
      return v.substring(0, 2) + '/' + v.substring(2, 4);
    }
    return v;
  };

  // Handle cardholder name input - filter invalid characters
  const handleCardNameChange = (value) => {
    // Only allow valid characters
    const filtered = value.replace(/[^a-zA-Z \'\.\-]/g, '');
    setCardName(filtered);
  };

  // Handle card number input
  const handleCardNumberChange = (value) => {
    const formatted = formatCardNumber(value);
    if (formatted.replace(/\s/g, '').length <= 16) {
      setCardNumber(formatted);
      // Detect card brand from the number
      setCardBrand(detectCardBrand(formatted));
    }
  };

  // Handle expiry input
  const handleExpiryChange = (value) => {
    // Remove any existing slash and non-digits
    const cleaned = value.replace(/\//g, '').replace(/[^0-9]/g, '');
    if (cleaned.length <= 4) {
      setCardExpiry(formatExpiry(cleaned));
    }
  };

  // Handle CVC input
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

    // Validate cardholder name
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

    // Validate card number
    if (cardNumber.trim() === '') {
      return {
        type: emitResponse.responseTypes.ERROR,
        message: __("Card Number cannot be empty", "chip-for-woocommerce"),
      };
    }

    // Validate expiry
    if (cardExpiry.trim() === '') {
      return {
        type: emitResponse.responseTypes.ERROR,
        message: __("Expiry (MM/YY) cannot be empty", "chip-for-woocommerce"),
      };
    }

    // Validate CVC
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

  // Handle checkout success - redirect with POST data like direct-post.js
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

        // Create and submit form like direct-post.js
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

        // Prevent default redirect
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
          id="chip-cardholder-name"
          value={cardName}
          onChange={(e) => handleCardNameChange(e.target.value)}
          autoComplete="cc-name"
          aria-label={__("Cardholder Name", "chip-for-woocommerce")}
          aria-invalid="false"
        />
        <label htmlFor="chip-cardholder-name">
          {__("Cardholder Name", "chip-for-woocommerce")}
        </label>
      </div>
      <div className="chip-card-number-wrapper">
        <div className={`wc-block-components-text-input is-active ${cardNumber ? 'has-value' : ''}`}>
          <input
            type="text"
            id="chip-card-number"
            value={cardNumber}
            onChange={(e) => handleCardNumberChange(e.target.value)}
            autoComplete="cc-number"
            inputMode="numeric"
            aria-label={__("Card Number", "chip-for-woocommerce")}
            aria-invalid="false"
          />
          <label htmlFor="chip-card-number">
            {__("Card Number", "chip-for-woocommerce")}
          </label>
        </div>
        {cardBrand && cardLogosUrl && (
          <img
            src={`${cardLogosUrl}${cardBrand}.svg`}
            alt={cardBrand}
            className="chip-card-brand-icon"
          />
        )}
      </div>
      <div className="wc-block-components-card-form__row" style={{ display: 'flex', gap: '16px' }}>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div className={`wc-block-components-text-input is-active ${cardExpiry ? 'has-value' : ''}`}>
            <input
              type="text"
              id="chip-card-expiry"
              value={cardExpiry}
              onChange={(e) => handleExpiryChange(e.target.value)}
              autoComplete="cc-exp"
              inputMode="numeric"
              aria-label={__("Expiry (MM/YY)", "chip-for-woocommerce")}
              aria-invalid="false"
            />
            <label htmlFor="chip-card-expiry">
              {__("Expiry (MM/YY)", "chip-for-woocommerce")}
            </label>
          </div>
        </div>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div className={`wc-block-components-text-input is-active ${cardCvc ? 'has-value' : ''}`}>
            <input
              type="password"
              id="chip-card-cvc"
              value={cardCvc}
              onChange={(e) => handleCvcChange(e.target.value)}
              autoComplete="cc-csc"
              inputMode="numeric"
              maxLength="4"
              aria-label={__("CVC", "chip-for-woocommerce")}
              aria-invalid="false"
            />
            <label htmlFor="chip-card-cvc">
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

  // Mirror Stripe's showSaveOptionByMethod: keep the Blocks save-card checkbox
  // visible only when a Card method is the active selection. For a unified
  // gateway the card form (and thus the save checkbox) is only shown once the
  // customer selects 'card' in the dropdown; for the single-method card
  // gateway it is always shown; auto-submit single-method gateways never.
  const activeIsCard = settings.js_display === 'card'
    || ( isUnified && selectedMethod === 'card' );
  useEffect(() => {
    toggleSaveCheckbox( activeIsCard );
  }, [ activeIsCard, settings.js_display ]);
  const unifiedProps = {
    nonce: window['gateway_' + PAYMENT_METHOD_NAME]?.nonce,
    banksApi: window['gateway_' + PAYMENT_METHOD_NAME]?.banks_api,
    placeholder: __("Choose a payment method", "chip-for-woocommerce"),
    onPaymentSetup,
    emitResponse,
    onChange: setSelectedMethod,
    logoBaseUrl: window['gateway_' + PAYMENT_METHOD_NAME]?.fpx_logo_base_url,
    razerLogoBaseUrl: window['gateway_' + PAYMENT_METHOD_NAME]?.razer_logo_base_url,
    cardLogosUrl: window['gateway_' + PAYMENT_METHOD_NAME]?.card_logos_url,
  };

  // Auto-submit for single-method gateways (DuitNow QR-only e.g. Gateway 6,
  // Crypto-only, or Google Pay/Apple Pay-only): zero-click UX, no picker.
  const autoMethod = settings.js_display === "dnqr" ? 'dnqr'
    : settings.js_display === "crypto" ? 'crypto_coin'
    : settings.js_display === "mpgs" ? 'mpgs_google_pay' : '';
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
          {selectedMethod === 'card' ? (
            <CardForm {...props} selectedMethod={selectedMethod} isUnified={true} />
          ) : null}
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

const wc_gateway_chip = {
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

registerPaymentMethod(wc_gateway_chip);
