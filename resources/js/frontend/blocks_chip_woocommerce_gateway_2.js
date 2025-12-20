import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { __ } from "@wordpress/i18n";
import { decodeEntities } from "@wordpress/html-entities";
import { getSetting } from "@woocommerce/settings";
import { useState, useEffect, useCallback } from "@wordpress/element";

const PAYMENT_METHOD_NAME = 'chip_woocommerce_gateway_2';
const settings = getSetting( PAYMENT_METHOD_NAME + '_data', {} );

// Add card form and select input styles to match WooCommerce Blocks styling.
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
  .chip-bank-select {
    margin-top: 16px;
  }
  .chip-bank-select .wc-block-components-combobox .wc-block-components-combobox-control {
    position: relative;
  }
  .chip-bank-select .wc-block-components-combobox-control input[type="text"] {
    display: none;
  }
  .chip-bank-select select {
    width: 100%;
    padding: 1.5em 3em 0.5em 1em;
    font-size: 1em;
    font-family: inherit;
    line-height: 1.375;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 4px;
    background-color: #fff;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    cursor: pointer;
    min-height: 3.5em;
    color: inherit;
  }
  .chip-bank-select select:focus {
    outline: none;
    border-color: #000;
    box-shadow: 0 0 0 1px #000;
  }
  .chip-bank-select .chip-select-label {
    position: absolute;
    top: 0.75em;
    left: 1em;
    font-size: 0.75em;
    font-weight: 400;
    color: #757575;
    pointer-events: none;
    z-index: 1;
  }
  .chip-bank-select .chip-select-chevron {
    position: absolute;
    right: 1em;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    fill: currentColor;
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

const FpxBankList = (props) => {
  const [bankId, setBankId] = useState('');
  const [banks, setBanks] = useState({});
  const [loading, setLoading] = useState(true);
  const { eventRegistration, emitResponse } = props;
  const { onPaymentSetup } = eventRegistration;

  // Lazy load banks from API.
  useEffect(() => {
    const gatewayConfig = window['gateway_' + PAYMENT_METHOD_NAME] || {};
    const banksApi = gatewayConfig.banks_api;

    if (banksApi) {
      fetch(banksApi, {
        headers: { 'X-WP-Nonce': gatewayConfig.nonce }
      })
        .then(response => response.json())
        .then(data => {
          setBanks(data);
          setLoading(false);
        })
        .catch(() => {
          setLoading(false);
        });
    } else {
      setLoading(false);
    }
  }, []);

  const onSubmit = () => {
    if ('' === bankId) {
      return {
        type: emitResponse.responseTypes.ERROR,
        message: __(
          "<strong>Internet Banking</strong> is a required field.",
          "chip-for-woocommerce"
        ),
      };
    }

    return {
      type: emitResponse.responseTypes.SUCCESS,
      meta: {
        paymentMethodData: {
          chip_fpx_bank: bankId,
        },
      },
    };
  };

  useEffect(() => {
    const unsubscribeProcessing = onPaymentSetup(onSubmit);
    return () => {
      unsubscribeProcessing();
    };
  }, [onPaymentSetup, bankId]);

  if (loading) {
    return <p>{__("Loading banks...", "chip-for-woocommerce")}</p>;
  }

  return (
    <div className="chip-bank-select">
      <div className="wc-block-components-combobox wc-block-components-combobox-control" style={{ position: 'relative' }}>
        <span className="chip-select-label">
          {__("Internet Banking", "chip-for-woocommerce")}
        </span>
        <select
          id="chip-fpx-bank-2"
          value={bankId}
          onChange={(e) => setBankId(e.target.value)}
          aria-label={__("Internet Banking", "chip-for-woocommerce")}
        >
          <option value="">{__("Choose your bank", "chip-for-woocommerce")}</option>
          {Object.keys(banks).map((key) => (
            <option key={key} value={key}>{banks[key]}</option>
          ))}
        </select>
        <svg 
          className="chip-select-chevron" 
          xmlns="http://www.w3.org/2000/svg" 
          viewBox="0 0 24 24" 
          width="18" 
          height="18" 
          aria-hidden="true"
        >
          <path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path>
        </svg>
      </div>
    </div>
  );
};

const Fpxb2b1BankList = (props) => {
  const [bankIdB2b, setBankIdB2b] = useState('');
  const [banks, setBanks] = useState({});
  const [loading, setLoading] = useState(true);
  const { eventRegistration, emitResponse } = props;
  const { onPaymentSetup } = eventRegistration;

  // Lazy load banks from API.
  useEffect(() => {
    const gatewayConfig = window['gateway_' + PAYMENT_METHOD_NAME] || {};
    const banksApi = gatewayConfig.banks_api;

    if (banksApi) {
      fetch(banksApi, {
        headers: { 'X-WP-Nonce': gatewayConfig.nonce }
      })
        .then(response => response.json())
        .then(data => {
          setBanks(data);
          setLoading(false);
        })
        .catch(() => {
          setLoading(false);
        });
    } else {
      setLoading(false);
    }
  }, []);

  const onSubmit = () => {
    if ('' === bankIdB2b) {
      return {
        type: emitResponse.responseTypes.ERROR,
        message: __(
          "<strong>Corporate Internet Banking</strong> is a required field.",
          "chip-for-woocommerce"
        ),
      };
    }

    return {
      type: emitResponse.responseTypes.SUCCESS,
      meta: {
        paymentMethodData: {
          chip_fpx_b2b1_bank: bankIdB2b,
        },
      },
    };
  };

  useEffect(() => {
    const unsubscribeProcessing = onPaymentSetup(onSubmit);
    return () => {
      unsubscribeProcessing();
    };
  }, [onPaymentSetup, bankIdB2b]);

  if (loading) {
    return <p>{__("Loading banks...", "chip-for-woocommerce")}</p>;
  }

  return (
    <div className="chip-bank-select">
      <div className="wc-block-components-combobox wc-block-components-combobox-control" style={{ position: 'relative' }}>
        <span className="chip-select-label">
          {__("Corporate Internet Banking", "chip-for-woocommerce")}
        </span>
        <select
          id="chip-fpx-b2b1-bank-2"
          value={bankIdB2b}
          onChange={(e) => setBankIdB2b(e.target.value)}
          aria-label={__("Corporate Internet Banking", "chip-for-woocommerce")}
        >
          <option value="">{__("Choose your bank", "chip-for-woocommerce")}</option>
          {Object.keys(banks).map((key) => (
            <option key={key} value={key}>{banks[key]}</option>
          ))}
        </select>
        <svg 
          className="chip-select-chevron" 
          xmlns="http://www.w3.org/2000/svg" 
          viewBox="0 0 24 24" 
          width="18" 
          height="18" 
          aria-hidden="true"
        >
          <path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path>
        </svg>
      </div>
    </div>
  );
};

const RazerEWalletList = (props) => {
  const [walletId, setWalletId] = useState('');
  const [wallets, setWallets] = useState({});
  const [loading, setLoading] = useState(true);
  const { eventRegistration, emitResponse } = props;
  const { onPaymentSetup } = eventRegistration;

  // Lazy load ewallets from API.
  useEffect(() => {
    const gatewayConfig = window['gateway_' + PAYMENT_METHOD_NAME] || {};
    const banksApi = gatewayConfig.banks_api;

    if (banksApi) {
      fetch(banksApi, {
        headers: { 'X-WP-Nonce': gatewayConfig.nonce }
      })
        .then(response => response.json())
        .then(data => {
          setWallets(data);
          setLoading(false);
        })
        .catch(() => {
          setLoading(false);
        });
    } else {
      setLoading(false);
    }
  }, []);

  const onSubmit = () => {
    if ('' === walletId) {
      return {
        type: emitResponse.responseTypes.ERROR,
        message: __(
          "<strong>E-Wallet</strong> is a required field.",
          "chip-for-woocommerce"
        ),
      };
    }

    return {
      type: emitResponse.responseTypes.SUCCESS,
      meta: {
        paymentMethodData: {
          chip_razer_ewallet: walletId,
        },
      },
    };
  };

  useEffect(() => {
    const unsubscribeProcessing = onPaymentSetup(onSubmit);
    return () => {
      unsubscribeProcessing();
    };
  }, [onPaymentSetup, walletId]);

  if (loading) {
    return <p>{__("Loading e-wallets...", "chip-for-woocommerce")}</p>;
  }

  return (
    <div className="chip-bank-select">
      <div className="wc-block-components-combobox wc-block-components-combobox-control" style={{ position: 'relative' }}>
        <span className="chip-select-label">
          {__("E-Wallet", "chip-for-woocommerce")}
        </span>
        <select
          id="chip-razer-ewallet-2"
          value={walletId}
          onChange={(e) => setWalletId(e.target.value)}
          aria-label={__("E-Wallet", "chip-for-woocommerce")}
        >
          <option value="">{__("Choose your e-wallet", "chip-for-woocommerce")}</option>
          {Object.keys(wallets).map((key) => (
            <option key={key} value={key}>{wallets[key]}</option>
          ))}
        </select>
        <svg 
          className="chip-select-chevron" 
          xmlns="http://www.w3.org/2000/svg" 
          viewBox="0 0 24 24" 
          width="18" 
          height="18" 
          aria-hidden="true"
        >
          <path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path>
        </svg>
      </div>
    </div>
  );
};

/**
 * Card Form Component for direct post card payments.
 */
const CardForm = (props) => {
  const [cardName, setCardName] = useState('');
  const [cardNumber, setCardNumber] = useState('');
  const [cardExpiry, setCardExpiry] = useState('');
  const [cardCvc, setCardCvc] = useState('');
  
  const { eventRegistration, emitResponse, shouldSavePayment } = props;
  const { onPaymentSetup, onCheckoutSuccess } = eventRegistration;

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
    const v = value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
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

    // Return SUCCESS without card data - card data will be POSTed directly to CHIP
    return {
      type: emitResponse.responseTypes.SUCCESS,
    };
  }, [cardName, cardNumber, cardExpiry, cardCvc, emitResponse.responseTypes]);

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

      if (directPostUrl) {
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
  }, [onCheckoutSuccess, cardName, cardNumber, cardExpiry, cardCvc, shouldSavePayment, emitResponse.responseTypes]);

  return (
    <div className="wc-block-components-card-form">
      <div className={`wc-block-components-text-input is-active ${cardName ? 'has-value' : ''}`}>
        <input
          type="text"
          id="chip-cardholder-name-2"
          className="wc-block-components-text-input__input"
          value={cardName}
          onChange={(e) => handleCardNameChange(e.target.value)}
          autoComplete="cc-name"
          aria-label={__("Cardholder Name", "chip-for-woocommerce")}
        />
        <label htmlFor="chip-cardholder-name-2" className="wc-block-components-text-input__label">
          {__("Cardholder Name", "chip-for-woocommerce")}
        </label>
      </div>
      <div className={`wc-block-components-text-input is-active ${cardNumber ? 'has-value' : ''}`}>
        <input
          type="text"
          id="chip-card-number-2"
          className="wc-block-components-text-input__input"
          value={cardNumber}
          onChange={(e) => handleCardNumberChange(e.target.value)}
          autoComplete="cc-number"
          inputMode="numeric"
          aria-label={__("Card Number", "chip-for-woocommerce")}
        />
        <label htmlFor="chip-card-number-2" className="wc-block-components-text-input__label">
          {__("Card Number", "chip-for-woocommerce")}
        </label>
      </div>
      <div className="wc-block-components-card-form__row" style={{ display: 'flex', gap: '16px' }}>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div className={`wc-block-components-text-input is-active ${cardExpiry ? 'has-value' : ''}`}>
            <input
              type="text"
              id="chip-card-expiry-2"
              className="wc-block-components-text-input__input"
              value={cardExpiry}
              onChange={(e) => handleExpiryChange(e.target.value)}
              autoComplete="cc-exp"
              inputMode="numeric"
              aria-label={__("Expiry (MM/YY)", "chip-for-woocommerce")}
            />
            <label htmlFor="chip-card-expiry-2" className="wc-block-components-text-input__label">
              {__("Expiry (MM/YY)", "chip-for-woocommerce")}
            </label>
          </div>
        </div>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div className={`wc-block-components-text-input is-active ${cardCvc ? 'has-value' : ''}`}>
            <input
              type="password"
              id="chip-card-cvc-2"
              className="wc-block-components-text-input__input"
              value={cardCvc}
              onChange={(e) => handleCvcChange(e.target.value)}
              autoComplete="cc-csc"
              inputMode="numeric"
              maxLength="4"
              aria-label={__("CVC", "chip-for-woocommerce")}
            />
            <label htmlFor="chip-card-cvc-2" className="wc-block-components-text-input__label">
              {__("CVC", "chip-for-woocommerce")}
            </label>
          </div>
        </div>
      </div>
    </div>
  );
};

const ContentContainer = (props) => {
  return (
    <>
      <Content />
      {settings.js_display === "fpx" ? <FpxBankList {...props} /> : null}
      {settings.js_display === "fpx_b2b1" ? (
        <Fpxb2b1BankList {...props} />
        ) : null}
      {settings.js_display === "razer" ? (
        <RazerEWalletList {...props} /> 
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

const chip_woocommerce_gateway_2 = {
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

registerPaymentMethod(chip_woocommerce_gateway_2);
