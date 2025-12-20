import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { __ } from "@wordpress/i18n";
import { decodeEntities } from "@wordpress/html-entities";
import { getSetting } from "@woocommerce/settings";

const PAYMENT_METHOD_NAME = 'chip_woocommerce_gateway_5';
const settings = getSetting( PAYMENT_METHOD_NAME + '_data', {} );

const defaultLabel = __("Atome", "chip-for-woocommerce");

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

const chip_woocommerce_gateway_5 = {
  name: PAYMENT_METHOD_NAME,
  paymentMethodId: PAYMENT_METHOD_NAME,
  label: <Label />,
  canMakePayment: canMakePayment,
  content: <Content />,
  edit: <Content />,
  ariaLabel: label,
  supports: {
    showSavedCards: settings.saved_option,
    showSaveOption: settings.save_option,
    features: settings.supports,
  },
};

registerPaymentMethod(chip_woocommerce_gateway_5);
