(()=>{"use strict";const e=window.React,t=window.wc.wcBlocksRegistry,n=window.wp.i18n,o=window.wp.htmlEntities,a=window.wc.wcSettings,r=window.wp.components,s=window.wp.element,c=(0,a.getSetting)(gateway_wc_gateway_chip_6.id+"_data",{}),i=(0,n.__)("CHIP","chip-for-woocommerce"),p=(0,o.decodeEntities)(c.title)||i,l=()=>(0,o.decodeEntities)(c.description||""),m=()=>c.icon?(0,e.createElement)("img",{src:c.icon,style:{float:"right",marginRight:"20px"}}):"",_=t=>{const[o,a]=(0,s.useState)(void 0),{eventRegistration:c,emitResponse:i}=t,{onPaymentSetup:p}=c,l=()=>void 0===o?{type:i.responseTypes.ERROR,message:(0,n.__)("<strong>Internet Banking</strong> is a required field.","chip-for-woocommerce")}:{type:i.responseTypes.SUCCESS,meta:{paymentMethodData:{chip_fpx_bank:o}}};(0,s.useEffect)((()=>{const e=p(l);return()=>{e()}}),[p,o]);const m=gateway_wc_gateway_chip_6.fpx_b2c;let _=[];return Object.keys(m).forEach((e=>{_.push({name:m[e],id:e})})),(0,e.createElement)(r.TreeSelect,{label:(0,n.__)("Internet Banking","chip-for-woocommerce"),noOptionLabel:(0,n.__)("Choose your bank","chip-for-woocommerce"),onChange:e=>{a(e)},selectedId:o,tree:_})},d=t=>{const[o,a]=(0,s.useState)(void 0),{eventRegistration:c,emitResponse:i}=t,{onPaymentSetup:p}=c,l=()=>void 0===o?{type:i.responseTypes.ERROR,message:(0,n.__)("<strong>Corporate Internet Banking</strong> is a required field.","chip-for-woocommerce")}:{type:i.responseTypes.SUCCESS,meta:{paymentMethodData:{chip_fpx_b2b1_bank:o}}};(0,s.useEffect)((()=>{const e=p(l);return()=>{e()}}),[p,o]);const m=gateway_wc_gateway_chip_6.fpx_b2b1;let _=[];return Object.keys(m).forEach((e=>{_.push({name:m[e],id:e})})),(0,e.createElement)(r.TreeSelect,{label:(0,n.__)("Internet Banking","chip-for-woocommerce"),noOptionLabel:(0,n.__)("Choose your bank","chip-for-woocommerce"),onChange:e=>{a(e)},selectedId:o,tree:_})},w=t=>{const[o,a]=(0,s.useState)(void 0),{eventRegistration:c,emitResponse:i}=t,{onPaymentSetup:p}=c,l=()=>void 0===o?{type:i.responseTypes.ERROR,message:(0,n.__)("<strong>E-Wallet</strong> is a required field.","chip-for-woocommerce")}:{type:i.responseTypes.SUCCESS,meta:{paymentMethodData:{chip_razer_ewallet:o}}};(0,s.useEffect)((()=>{const e=p(l);return()=>{e()}}),[p,o]);const m=gateway_wc_gateway_chip_6.razer;let _=[];return Object.keys(m).forEach((e=>{_.push({name:m[e],id:e})})),(0,e.createElement)(r.TreeSelect,{label:(0,n.__)("E-Wallet","chip-for-woocommerce"),noOptionLabel:(0,n.__)("Choose your e-wallet","chip-for-woocommerce"),onChange:e=>{a(e)},selectedId:o,tree:_})},h=t=>(0,e.createElement)(e.Fragment,null,(0,e.createElement)(l,null),"fpx"==c.js_display?(0,e.createElement)(_,{...t}):null,"fpx_b2b1"==c.js_display?(0,e.createElement)(d,{...t}):null,"razer"==c.js_display?(0,e.createElement)(w,{...t}):null),y={name:c.method_name,label:(0,e.createElement)((t=>{const{PaymentMethodLabel:n}=t.components;return(0,e.createElement)("span",{style:{width:"100%"}},p,(0,e.createElement)(m,null))}),null),content:(0,e.createElement)(h,null),edit:(0,e.createElement)(h,null),canMakePayment:()=>!0,ariaLabel:p,supports:{showSavedCards:c.saved_option,showSaveOption:c.save_option,features:c.supports}};(0,t.registerPaymentMethod)(y)})();