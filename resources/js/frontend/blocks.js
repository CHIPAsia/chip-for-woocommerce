import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';
import { TreeSelect } from '@wordpress/components';
import { useState } from '@wordpress/element';

const settings = getSetting( GATEWAY.id + '_data', {} );

const defaultLabel = __(
	'CHIP',
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

// const setPageAA = ( value ) => {
//   console.log(value);
// }

let setPageAA = '';

const FpxBankList = () => {
    const [ page, setPage ] = useState( 'p21' );

    return (
        <TreeSelect
            label="Internet Banking"
            noOptionLabel="Select Bank"
            onChange={ 
              ( newPage ) => { 
                setPageAA = newPage;
                setPage( newPage );
              }
            }
            selectedId={ page }
            tree={ [
                {
                    name: 'Affin Bank',
                    id: 'ABB0233',
                },
                {
                    name: 'Alliance Bank (Personal)',
                    id: 'ABMB0212',
                },
                {   name: 'AGRONet',
                    id: 'AGRO01', 
                },
                {   name: 'AmBank',
                    id: 'AMBB0209', 
                },
                {   name: 'Bank Islam',
                    id: 'BIMB0340', 
                },
                {   name: 'Bank Muamalat',
                    id: 'BMMB0341', 
                },
                {   name: 'Bank Rakyat',
                    id: 'BKRM0602', 
                },
                {   name: 'Bank Of China',
                    id: 'BOCM01', 
                },
                {   name: 'BSN',
                    id: 'BSN0601', 
                },
                {   name: 'CIMB Bank',
                    id: 'BCBB0235', 
                },
                {   name: 'Hong Leong Bank',
                    id: 'HLB0224', 
                },
                {   name: 'HSBC Bank',
                    id: 'HSBC0223', 
                },
                {   name: 'KFH',
                    id: 'KFH0346', 
                },
                {   name: 'Maybank2E',
                    id: 'MBB0228', 
                },
                {   name: 'Maybank2u',
                    id: 'MB2U0227', 
                },
                {   name: 'OCBC Bank',
                    id: 'OCBC0229', 
                },
                {   name: 'Public Bank',
                    id: 'PBB0233', 
                },
                {   name: 'RHB Bank',
                    id: 'RHB0218', 
                },
                {   name: 'Standard Chartered',
                    id: 'SCB0216', 
                },
                {   name: 'UOB Bank',
                    id: 'UOB0226', 
                }
            ] }
        />
    );
}

const Fpxb2b1BankList = () => {
  const [ page, setPage ] = useState( 'p21' );

  return (
      <TreeSelect
          label="Internet Banking"
          noOptionLabel="Select Bank"
          onChange={ ( newPage ) => setPage( newPage ) }
          selectedId={ page }
          tree={ [
              {   name: 'AFFINMAX',
                  id: 'ABB0235',
              },
              {   name: 'Alliance Bank (Business)',
                  id: 'ABMB0213',
              },
              {   name: 'AGRONetBIZ',
                  id: 'AGRO02',
              },
              {   name: 'AmBank',
                  id: 'AMBB0208',
              },
              {   name: 'Bank Islam',
                  id: 'BIMB0340',
              },
              {   name: 'Bank Muamalat',
                  id: 'BMMB0342',
              },
              {   name: 'BNP Paribas',
                  id: 'BNP003',
              },
              {   name: 'CIMB Bank',
                  id: 'BCBB0235',
              },
              {   name: 'Citibank Corporate Banking',
                  id: 'CIT0218',
              },
              {   name: 'Deutsche Bank',
                  id: 'DBB0199',
              },
              {   name: 'Hong Leong Bank',
                  id: 'HLB0224',
              },
              {   name: 'HSBC Bank',
                  id: 'HSBC0223',
              },
              {   name: 'Bank Rakyat',
                  id: 'BKRM0602',
              },
              {   name: 'KFH',
                  id: 'KFH0346',
              },
              {   name: 'Maybank2E',
                  id: 'MBB0228',
              },
              {   name: 'OCBC Bank',
                  id: 'OCBC0229',
              },
              {   name: 'Public Bank',
                  id: 'PBB0233',
              },
              {   name: 'Public Bank PB enterprise',
                  id: 'PBB0234',
              },
              {   name: 'RHB Bank',
                  id: 'RHB0218',
              },
              {   name: 'Standard Chartered',
                  id: 'SCB0215',
              },
              {   name: 'UOB Regional',
                  id: 'UOB0228',
              },
          ] }
      />
  );
}

// fragment --> <> --> untuk wrap bila return mesti 1 component sahaja.
const content_display = <><Content />
{ settings.js_display == 'fpx' ? <FpxBankList /> : null }
{ settings.js_display == 'fpx_b2b1' ? <Fpxb2b1BankList /> : null}
</>;

const wc_gateway_chip = {
  name: settings.method_name,
  label: <Label />,
  content: content_display,
  edit: content_display,
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
    showSavedCards: settings.saved_option,
		showSaveOption: settings.save_option,
    features: settings.supports,
  },
};

registerPaymentMethod( wc_gateway_chip );
