import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { __ } from "@wordpress/i18n";
import { decodeEntities } from "@wordpress/html-entities";
import { getSetting } from "@woocommerce/settings";
import { TreeSelect } from "@wordpress/components";
import { useState, useEffect } from "@wordpress/element";

const PAYMENT_METHOD_NAME = 'chip_woocommerce_gateway_6';
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

const ContentContainer = (props) => {
  return (
    <>
      <Content />
      {settings.js_display == "fpx" ? <FpxBankList {...props} /> : null}
      {settings.js_display == "fpx_b2b1" ? (
        <Fpxb2b1BankList {...props} />
        ) : null}
      {settings.js_display == "razer" ? (
        <RazerEWalletList {...props} /> 
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

const chip_woocommerce_gateway_6 = {
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

registerPaymentMethod(chip_woocommerce_gateway_6);
