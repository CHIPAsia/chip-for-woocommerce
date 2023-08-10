import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { __ } from "@wordpress/i18n";
import { decodeEntities } from "@wordpress/html-entities";
import { getSetting } from "@woocommerce/settings";
import { TreeSelect } from "@wordpress/components";
import { useState, useEffect } from "@wordpress/element";

const settings = getSetting(GATEWAY.id + "_data", {});

const defaultLabel = __("CHIP", "chip-for-woocommerce");

/**
 * console.log(wc.wcSettings);
 * wc.wcSettings.allSettings;
 */

const label = decodeEntities(settings.title) || defaultLabel;

const Content = () => {
  return decodeEntities(settings.description || "");
};

const Label = (props) => {
  const { PaymentMethodLabel } = props.components;
  return <PaymentMethodLabel text={label} />;
};

const FpxBankList = (props) => {
  const [bankId, setBankId] = useState(undefined);
  const { eventRegistration, emitResponse } = props;
  const { onPaymentProcessing } = eventRegistration;

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
    const unsubscribeProcessing = onPaymentProcessing(onSubmit);
    return () => {
      unsubscribeProcessing();
    };
  }, [onPaymentProcessing, bankId]);

  return (
    <TreeSelect
      label={__("Internet Banking", "chip-for-woocommerce")}
      noOptionLabel={__("Choose an option", "chip-for-woocommerce")}
      onChange={(selected_bank_id) => {
        setBankId(selected_bank_id);
      }}
      selectedId={bankId}
      tree={[
        {
          name: __("Affin Bank", "chip-for-woocommerce"),
          id: "ABB0233",
        },
        {
          name: __("Alliance Bank (Personal)", "chip-for-woocommerce"),
          id: "ABMB0212",
        },
        { name: __("AGRONet", "chip-for-woocommerce"), id: "AGRO01" },
        { name: __("AmBank", "chip-for-woocommerce"), id: "AMBB0209" },
        { name: __("Bank Islam", "chip-for-woocommerce"), id: "BIMB0340" },
        { name: __("Bank Muamalat", "chip-for-woocommerce"), id: "BMMB0341" },
        { name: __("Bank Rakyat", "chip-for-woocommerce"), id: "BKRM0602" },
        { name: __("Bank Of China", "chip-for-woocommerce"), id: "BOCM01" },
        { name: __("BSN", "chip-for-woocommerce"), id: "BSN0601" },
        { name: __("CIMB Bank", "chip-for-woocommerce"), id: "BCBB0235" },
        {
          name: __("Hong Leong Bank", "chip-for-woocommerce"),
          id: "HLB0224",
        },
        { name: __("HSBC Bank", "chip-for-woocommerce"), id: "HSBC0223" },
        { name: __("KFH", "chip-for-woocommerce"), id: "KFH0346" },
        { name: __("Maybank2E", "chip-for-woocommerce"), id: "MBB0228" },
        { name: __("Maybank2u", "chip-for-woocommerce"), id: "MB2U0227" },
        { name: __("OCBC Bank", "chip-for-woocommerce"), id: "OCBC0229" },
        { name: __("Public Bank", "chip-for-woocommerce"), id: "PBB0233" },
        { name: __("RHB Bank", "chip-for-woocommerce"), id: "RHB0218" },
        {
          name: __("Standard Chartered", "chip-for-woocommerce"),
          id: "SCB0216",
        },
        { name: __("UOB Bank", "chip-for-woocommerce"), id: "UOB0226" },
      ]}
    />
  );
};

const Fpxb2b1BankList = (props) => {
  const [bankIdB2b, setBankIdB2b] = useState(undefined);
  const { eventRegistration, emitResponse } = props;
  const { onPaymentProcessing } = eventRegistration;

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
    const unsubscribeProcessing = onPaymentProcessing(onSubmit);
    return () => {
      unsubscribeProcessing();
    };
  }, [onPaymentProcessing, bankIdB2b]);

  return (
    <TreeSelect
      label={__("Internet Banking", "chip-for-woocommerce")}
      noOptionLabel={__("Choose an option", "chip-for-woocommerce")}
      onChange={(selected_bank_id) => {
        setBankIdB2b(selected_bank_id);
      }}
      selectedId={bankIdB2b}
      tree={[
        { name: __("AFFINMAX", "chip-for-woocommerce"), id: "ABB0235" },
        {
          name: __("Alliance Bank (Business)", "chip-for-woocommerce"),
          id: "ABMB0213",
        },
        { name: __("AGRONetBIZ", "chip-for-woocommerce"), id: "AGRO02" },
        { name: __("AmBank", "chip-for-woocommerce"), id: "AMBB0208" },
        { name: __("Bank Islam", "chip-for-woocommerce"), id: "BIMB0340" },
        { name: __("Bank Muamalat", "chip-for-woocommerce"), id: "BMMB0342" },
        { name: __("BNP Paribas", "chip-for-woocommerce"), id: "BNP003" },
        { name: __("CIMB Bank", "chip-for-woocommerce"), id: "BCBB0235" },
        {
          name: __("Citibank Corporate Banking", "chip-for-woocommerce"),
          id: "CIT0218",
        },
        { name: __("Deutsche Bank", "chip-for-woocommerce"), id: "DBB0199" },
        { name: __("Hong Leong Bank", "chip-for-woocommerce"), id: "HLB0224" },
        { name: __("HSBC Bank", "chip-for-woocommerce"), id: "HSBC0223" },
        { name: __("Bank Rakyat", "chip-for-woocommerce"), id: "BKRM0602" },
        { name: __("KFH", "chip-for-woocommerce"), id: "KFH0346" },
        { name: __("Maybank2E", "chip-for-woocommerce"), id: "MBB0228" },
        { name: __("OCBC Bank", "chip-for-woocommerce"), id: "OCBC0229" },
        { name: __("Public Bank", "chip-for-woocommerce"), id: "PBB0233" },
        {
          name: __("Public Bank PB enterprise", "chip-for-woocommerce"),
          id: "PBB0234",
        },
        { name: __("RHB Bank", "chip-for-woocommerce"), id: "RHB0218" },
        {
          name: __("Standard Chartered", "chip-for-woocommerce"),
          id: "SCB0215",
        },
        { name: __("UOB Regional", "chip-for-woocommerce"), id: "UOB0228" },
      ]}
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
    </>
  );
};

const wc_gateway_chip = {
  name: settings.method_name,
  label: <Label />,
  content: <ContentContainer />,
  edit: <ContentContainer />,
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
    showSavedCards: settings.saved_option,
    showSaveOption: settings.save_option,
    features: settings.supports,
  },
};

registerPaymentMethod(wc_gateway_chip);
