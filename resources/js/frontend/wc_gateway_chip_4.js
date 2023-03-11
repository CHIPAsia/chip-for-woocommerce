import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';

const settings = getSetting( 'wc_gateway_chip_4_data', {} );

const defaultLabel = __(
	'CHIP 4',
	'chip-for-woocommerce'
);

/**
 * console.log(wc.wcSettings);
 * wc.wcSettings.allSettings;
 */

const label = decodeEntities( settings.title ) || defaultLabel;

const Content = () => {
  return decodeEntities( settings.description || '' );
};

const Label = ( props ) => {
  const { PaymentMethodLabel } = props.components;
  return <PaymentMethodLabel text={ label } />;
};

const wc_gateway_chip = {
  name: settings.method_name,
  label: <Label />,
  content: <Content />,
  edit: <Content />,
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
		showSaveOption: settings.save_option,
    features: settings.supports,
  },
};

registerPaymentMethod( wc_gateway_chip );
