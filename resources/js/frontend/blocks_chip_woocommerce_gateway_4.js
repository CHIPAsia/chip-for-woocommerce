import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { __ } from "@wordpress/i18n";
import { decodeEntities } from "@wordpress/html-entities";
import { getSetting } from "@woocommerce/settings";
import { TreeSelect, TextControl } from "@wordpress/components";
import { useState, useEffect, useCallback } from "@wordpress/element";

const PAYMENT_METHOD_NAME = 'chip_woocommerce_gateway_4';
const settings = getSetting( PAYMENT_METHOD_NAME + '_data', {} );

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

const Label = (props) => {
  const { PaymentMethodLabel } = props.components;

  return (
    <span style={{ width: '100%' }}>
        {label}
        <Icon />
    </span>
  )
};

const FpxBankList = (props) => {
  const [bankId, setBankId] = useState(undefined);
  const { eventRegistration, emitResponse } = props;
  const { onPaymentSetup } = eventRegistration;

  const onSubmit = () => {
    if (undefined === bankId) {
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

  const fpx_b2c = window['gateway_' + PAYMENT_METHOD_NAME]?.fpx_b2c || {};

  let fpx_b2c_array = [];

  Object.keys(fpx_b2c).forEach(key => {
    fpx_b2c_array.push({name: fpx_b2c[key], id: key});
  });

  return (
    <TreeSelect
      label={__("Internet Banking", "chip-for-woocommerce")}
      noOptionLabel={__("Choose your bank", "chip-for-woocommerce")}
      onChange={(selected_bank_id) => {
        setBankId(selected_bank_id);
      }}
      selectedId={bankId}
      tree={fpx_b2c_array}
    />
  );
};

const Fpxb2b1BankList = (props) => {
  const [bankIdB2b, setBankIdB2b] = useState(undefined);
  const { eventRegistration, emitResponse } = props;
  const { onPaymentSetup } = eventRegistration;

  const onSubmit = () => {
    if (undefined === bankIdB2b) {
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

  const fpx_b2b1 = window['gateway_' + PAYMENT_METHOD_NAME]?.fpx_b2b1 || {};

  let fpx_b2b1_array = [];

  Object.keys(fpx_b2b1).forEach(key => {
    fpx_b2b1_array.push({name: fpx_b2b1[key], id: key});
  });

  return (
    <TreeSelect
      label={__("Internet Banking", "chip-for-woocommerce")}
      noOptionLabel={__("Choose your bank", "chip-for-woocommerce")}
      onChange={(selected_bank_id) => {
        setBankIdB2b(selected_bank_id);
      }}
      selectedId={bankIdB2b}
      tree={fpx_b2b1_array}
    />
  );
};

const RazerEWalletList = (props) => {
  const [walletId, setWalletId] = useState(undefined);
  const { eventRegistration, emitResponse } = props;
  const { onPaymentSetup } = eventRegistration;

  const onSubmit = () => {
    if (undefined === walletId) {      
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

  const razer_ewallets = window['gateway_' + PAYMENT_METHOD_NAME]?.razer || {};

  let razer_ewallets_array = [];

  Object.keys(razer_ewallets).forEach(key => {
    razer_ewallets_array.push({name: razer_ewallets[key], id: key});
  });

  return (
    <TreeSelect
      label={__("E-Wallet", "chip-for-woocommerce")}
      noOptionLabel={__("Choose your e-wallet", "chip-for-woocommerce")}
      onChange={(selected_wallet_id) => {
        setWalletId(selected_wallet_id);
      }}
      selectedId={walletId}
      tree={razer_ewallets_array}
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
  
  const { eventRegistration, emitResponse } = props;
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

  // Helper function to get payment detail value from array format.
  const getPaymentDetail = (paymentDetails, key) => {
    if (!paymentDetails || !Array.isArray(paymentDetails)) {
      return null;
    }
    const detail = paymentDetails.find(item => item.key === key);
    return detail ? detail.value : null;
  };

  useEffect(() => {
    const unsubscribeCheckoutSuccess = onCheckoutSuccess((data) => {
      const { processingResponse } = data;
      
      const directPostUrl = getPaymentDetail(
        processingResponse?.paymentDetails,
        'chip_direct_post_url'
      );

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
  }, [onCheckoutSuccess, cardName, cardNumber, cardExpiry, cardCvc, emitResponse.responseTypes]);

  return (
    <div className="wc-block-components-card-form">
      <TextControl
        label={__("Cardholder Name", "chip-for-woocommerce")}
        value={cardName}
        onChange={handleCardNameChange}
        placeholder={__("Name on card", "chip-for-woocommerce")}
        autoComplete="cc-name"
      />
      <TextControl
        label={__("Card Number", "chip-for-woocommerce")}
        value={cardNumber}
        onChange={handleCardNumberChange}
        placeholder="•••• •••• •••• ••••"
        autoComplete="cc-number"
        inputMode="numeric"
      />
      <div style={{ display: 'flex', gap: '16px' }}>
        <div style={{ flex: 1 }}>
          <TextControl
            label={__("Expiry (MM/YY)", "chip-for-woocommerce")}
            value={cardExpiry}
            onChange={handleExpiryChange}
            placeholder="MM/YY"
            autoComplete="cc-exp"
            inputMode="numeric"
          />
        </div>
        <div style={{ flex: 1 }}>
          <TextControl
            label={__("CVC", "chip-for-woocommerce")}
            value={cardCvc}
            onChange={handleCvcChange}
            placeholder="•••"
            autoComplete="cc-csc"
            inputMode="numeric"
          />
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
  
  if ( ! supportedCurrencies.includes( cartCurrency ) ) {
    return false;
  }

  // Check if payment requirements are met.
  const gatewayFeatures = settings.supports || [];
  const hasRequiredFeatures = paymentRequirements.every( 
    requirement => gatewayFeatures.includes( requirement ) 
  );

  return hasRequiredFeatures;
};

const chip_woocommerce_gateway_4 = {
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

registerPaymentMethod(chip_woocommerce_gateway_4);
