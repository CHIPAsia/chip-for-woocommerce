import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { __ } from "@wordpress/i18n";
import { decodeEntities } from "@wordpress/html-entities";
import { getSetting } from "@woocommerce/settings";
import { TreeSelect } from "@wordpress/components";
import { useState, useEffect } from "@wordpress/element";

const settings = getSetting(gateway_wc_gateway_chip_5.id + "_data", {});

const defaultLabel = __("CHIP", "chip-for-woocommerce");

/**
 * console.log(wc.wcSettings);
 * wc.wcSettings.allSettings;
 */

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
  // return <PaymentMethodLabel text={label} />;
};

const wc_gateway_chip = {
  name: settings.method_name,
  label: <Label />,
  canMakePayment: () => true,
  content: <Content />,
  edit: <Content />,
  ariaLabel: label,
  supports: {
    showSavedCards: settings.saved_option,
    showSaveOption: settings.save_option,
    features: settings.supports,
  },
};

registerPaymentMethod(wc_gateway_chip);
