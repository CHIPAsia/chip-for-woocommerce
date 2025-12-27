import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { __ } from "@wordpress/i18n";
import { decodeEntities } from "@wordpress/html-entities";
import { getSetting } from "@woocommerce/settings";
import { useState, useEffect, useCallback } from "@wordpress/element";

const PAYMENT_METHOD_NAME = 'wc_gateway_chip_4';
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
  /* Custom bank dropdown with logos */
  .chip-bank-dropdown {
    position: relative;
    width: 100%;
  }
  .chip-bank-dropdown__trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 12px 16px;
    background: #fff;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    min-height: 48px;
    box-sizing: border-box;
  }
  .chip-bank-dropdown__trigger:hover {
    border-color: #2271b1;
  }
  .chip-bank-dropdown__trigger:focus {
    outline: 2px solid #2271b1;
    outline-offset: -2px;
  }
  .chip-bank-dropdown.is-open .chip-bank-dropdown__trigger {
    border-color: #2271b1;
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
  }
  .chip-bank-dropdown__selected {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
  }
  .chip-bank-dropdown__logo {
    width: 24px;
    height: 24px;
    object-fit: contain;
    flex-shrink: 0;
  }
  .chip-bank-dropdown__arrow {
    width: 24px;
    height: 24px;
    flex-shrink: 0;
    transition: transform 0.2s;
  }
  .chip-bank-dropdown.is-open .chip-bank-dropdown__arrow {
    transform: rotate(180deg);
  }
  .chip-bank-dropdown__menu {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    max-height: 300px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #2271b1;
    border-top: none;
    border-bottom-left-radius: 4px;
    border-bottom-right-radius: 4px;
    z-index: 100;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
  }
  .chip-bank-dropdown__option {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    cursor: pointer;
    font-size: 14px;
  }
  .chip-bank-dropdown__option:hover {
    background: #f0f0f1;
  }
  .chip-bank-dropdown__option.is-selected {
    background: #e7f3ff;
  }
  .chip-bank-dropdown__option.is-disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
  .chip-bank-dropdown__placeholder {
    color: #757575;
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
 * Custom Bank Dropdown with logos.
 */
const BankDropdown = ({ banks, value, onChange, placeholder, label, id, logoBaseUrl }) => {
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = React.useRef(null);

  useEffect(() => {
    const handleClickOutside = (event) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const selectedBank = value ? banks[value] : null;

  const handleSelect = (bankCode) => {
    onChange(bankCode);
    setIsOpen(false);
  };

  const handleKeyDown = (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      setIsOpen(!isOpen);
    } else if (e.key === 'Escape') {
      setIsOpen(false);
    }
  };

  return (
    <div className="chip-bank-select">
      <label className="wc-blocks-components-select__label" style={{ marginBottom: '8px', display: 'block' }}>
        {label}
      </label>
      <div className={`chip-bank-dropdown ${isOpen ? 'is-open' : ''}`} ref={dropdownRef}>
        <div
          className="chip-bank-dropdown__trigger"
          onClick={() => setIsOpen(!isOpen)}
          onKeyDown={handleKeyDown}
          tabIndex="0"
          role="combobox"
          aria-expanded={isOpen}
          aria-haspopup="listbox"
          aria-labelledby={id}
        >
          <div className="chip-bank-dropdown__selected">
            {selectedBank ? (
              <>
                <img src={`${logoBaseUrl}${value}.png`} alt="" className="chip-bank-dropdown__logo" onError={(e) => { e.target.style.display = 'none'; }} />
                <span>{selectedBank}</span>
              </>
            ) : (
              <span className="chip-bank-dropdown__placeholder">{placeholder}</span>
            )}
          </div>
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" className="chip-bank-dropdown__arrow" aria-hidden="true">
            <path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path>
          </svg>
        </div>
        {isOpen && (
          <div className="chip-bank-dropdown__menu" role="listbox">
            {Object.keys(banks).map((bankCode) => (
              <div
                key={bankCode}
                className={`chip-bank-dropdown__option ${value === bankCode ? 'is-selected' : ''}`}
                onClick={() => handleSelect(bankCode)}
                role="option"
                aria-selected={value === bankCode}
              >
                <img src={`${logoBaseUrl}${bankCode}.png`} alt="" className="chip-bank-dropdown__logo" onError={(e) => { e.target.style.display = 'none'; }} />
                <span>{banks[bankCode]}</span>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};

const FpxBankList = (props) => {
  const [bankId, setBankId] = useState('');
  const [banks, setBanks] = useState({});
  const [loading, setLoading] = useState(true);
  const { eventRegistration, emitResponse } = props;
  const { onPaymentSetup } = eventRegistration;

  const gatewayConfig = window['gateway_' + PAYMENT_METHOD_NAME] || {};
  const logoBaseUrl = gatewayConfig.logo_base_url || '';

  useEffect(() => {
    const banksApi = gatewayConfig.banks_api;
    if (banksApi) {
      fetch(banksApi, { headers: { 'X-WP-Nonce': gatewayConfig.nonce } })
        .then(response => response.json())
        .then(data => { setBanks(data); setLoading(false); })
        .catch(() => { setLoading(false); });
    } else {
      setLoading(false);
    }
  }, []);

  const onSubmit = useCallback(() => {
    if ('' === bankId) {
      return {
        type: emitResponse.responseTypes.ERROR,
        message: __("<strong>Internet Banking</strong> is a required field.", "chip-for-woocommerce"),
      };
    }
    return {
      type: emitResponse.responseTypes.SUCCESS,
      meta: { paymentMethodData: { chip_fpx_bank: bankId } },
    };
  }, [bankId, emitResponse.responseTypes]);

  useEffect(() => {
    const unsubscribeProcessing = onPaymentSetup(onSubmit);
    return () => { unsubscribeProcessing(); };
  }, [onPaymentSetup, onSubmit]);

  if (loading) {
    return <p>{__("Loading banks...", "chip-for-woocommerce")}</p>;
  }

  return (
    <BankDropdown
      banks={banks}
      value={bankId}
      onChange={setBankId}
      placeholder={__("Choose your bank", "chip-for-woocommerce")}
      label={__("Internet Banking", "chip-for-woocommerce")}
      id="chip-fpx-bank-4"
      logoBaseUrl={logoBaseUrl}
    />
  );
};

const Fpxb2b1BankList = (props) => {
  const [bankIdB2b, setBankIdB2b] = useState('');
  const [banks, setBanks] = useState({});
  const [loading, setLoading] = useState(true);
  const { eventRegistration, emitResponse } = props;
  const { onPaymentSetup } = eventRegistration;

  const gatewayConfig = window['gateway_' + PAYMENT_METHOD_NAME] || {};
  const logoBaseUrl = gatewayConfig.logo_base_url || '';

  useEffect(() => {
    const banksApi = gatewayConfig.banks_api;
    if (banksApi) {
      fetch(banksApi, { headers: { 'X-WP-Nonce': gatewayConfig.nonce } })
        .then(response => response.json())
        .then(data => { setBanks(data); setLoading(false); })
        .catch(() => { setLoading(false); });
    } else {
      setLoading(false);
    }
  }, []);

  const onSubmit = useCallback(() => {
    if ('' === bankIdB2b) {
      return {
        type: emitResponse.responseTypes.ERROR,
        message: __("<strong>Corporate Internet Banking</strong> is a required field.", "chip-for-woocommerce"),
      };
    }
    return {
      type: emitResponse.responseTypes.SUCCESS,
      meta: { paymentMethodData: { chip_fpx_b2b1_bank: bankIdB2b } },
    };
  }, [bankIdB2b, emitResponse.responseTypes]);

  useEffect(() => {
    const unsubscribeProcessing = onPaymentSetup(onSubmit);
    return () => { unsubscribeProcessing(); };
  }, [onPaymentSetup, onSubmit]);

  if (loading) {
    return <p>{__("Loading banks...", "chip-for-woocommerce")}</p>;
  }

  return (
    <BankDropdown
      banks={banks}
      value={bankIdB2b}
      onChange={setBankIdB2b}
      placeholder={__("Choose your bank", "chip-for-woocommerce")}
      label={__("Corporate Internet Banking", "chip-for-woocommerce")}
      id="chip-fpx-b2b1-bank-4"
      logoBaseUrl={logoBaseUrl}
    />
  );
};

const RazerEWalletList = (props) => {
  const [walletId, setWalletId] = useState('');
  const [wallets, setWallets] = useState({});
  const [loading, setLoading] = useState(true);
  const { eventRegistration, emitResponse } = props;
  const { onPaymentSetup } = eventRegistration;

  const gatewayConfig = window['gateway_' + PAYMENT_METHOD_NAME] || {};
  const logoBaseUrl = gatewayConfig.logo_base_url || '';

  useEffect(() => {
    const banksApi = gatewayConfig.banks_api;
    if (banksApi) {
      fetch(banksApi, { headers: { 'X-WP-Nonce': gatewayConfig.nonce } })
        .then(response => response.json())
        .then(data => { setWallets(data); setLoading(false); })
        .catch(() => { setLoading(false); });
    } else {
      setLoading(false);
    }
  }, []);

  const onSubmit = useCallback(() => {
    if ('' === walletId) {
      return {
        type: emitResponse.responseTypes.ERROR,
        message: __("<strong>E-Wallet</strong> is a required field.", "chip-for-woocommerce"),
      };
    }
    return {
      type: emitResponse.responseTypes.SUCCESS,
      meta: { paymentMethodData: { chip_razer_ewallet: walletId } },
    };
  }, [walletId, emitResponse.responseTypes]);

  useEffect(() => {
    const unsubscribeProcessing = onPaymentSetup(onSubmit);
    return () => { unsubscribeProcessing(); };
  }, [onPaymentSetup, onSubmit]);

  if (loading) {
    return <p>{__("Loading e-wallets...", "chip-for-woocommerce")}</p>;
  }

  return (
    <BankDropdown
      banks={wallets}
      value={walletId}
      onChange={setWalletId}
      placeholder={__("Choose your e-wallet", "chip-for-woocommerce")}
      label={__("E-Wallet", "chip-for-woocommerce")}
      id="chip-razer-ewallet-4"
      logoBaseUrl={logoBaseUrl}
    />
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
          id="chip-cardholder-name-4"
          value={cardName}
          onChange={(e) => handleCardNameChange(e.target.value)}
          autoComplete="cc-name"
          aria-label={__("Cardholder Name", "chip-for-woocommerce")}
          aria-invalid="false"
        />
        <label htmlFor="chip-cardholder-name-4">
          {__("Cardholder Name", "chip-for-woocommerce")}
        </label>
      </div>
      <div className={`wc-block-components-text-input is-active ${cardNumber ? 'has-value' : ''}`}>
        <input
          type="text"
          id="chip-card-number-4"
          value={cardNumber}
          onChange={(e) => handleCardNumberChange(e.target.value)}
          autoComplete="cc-number"
          inputMode="numeric"
          aria-label={__("Card Number", "chip-for-woocommerce")}
          aria-invalid="false"
        />
        <label htmlFor="chip-card-number-4">
          {__("Card Number", "chip-for-woocommerce")}
        </label>
      </div>
      <div className="wc-block-components-card-form__row" style={{ display: 'flex', gap: '16px' }}>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div className={`wc-block-components-text-input is-active ${cardExpiry ? 'has-value' : ''}`}>
            <input
              type="text"
              id="chip-card-expiry-4"
              value={cardExpiry}
              onChange={(e) => handleExpiryChange(e.target.value)}
              autoComplete="cc-exp"
              inputMode="numeric"
              aria-label={__("Expiry (MM/YY)", "chip-for-woocommerce")}
              aria-invalid="false"
            />
            <label htmlFor="chip-card-expiry-4">
              {__("Expiry (MM/YY)", "chip-for-woocommerce")}
            </label>
          </div>
        </div>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div className={`wc-block-components-text-input is-active ${cardCvc ? 'has-value' : ''}`}>
            <input
              type="password"
              id="chip-card-cvc-4"
              value={cardCvc}
              onChange={(e) => handleCvcChange(e.target.value)}
              autoComplete="cc-csc"
              inputMode="numeric"
              maxLength="4"
              aria-label={__("CVC", "chip-for-woocommerce")}
              aria-invalid="false"
            />
            <label htmlFor="chip-card-cvc-4">
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

const wc_gateway_chip_4 = {
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

registerPaymentMethod(wc_gateway_chip_4);
