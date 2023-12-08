!function(){"use strict";var e=window.React,t=window.wc.wcBlocksRegistry,n=window.wp.i18n,o=window.wp.htmlEntities,a=window.wc.wcSettings,s=window.wp.components,c=window.wp.element;const r=(0,a.getSetting)(gateway_wc_gateway_chip_3.id+"_data",{}),i=(0,n.__)("CHIP","chip-for-woocommerce"),p=(0,o.decodeEntities)(r.title)||i,l=()=>(0,o.decodeEntities)(r.description||""),m=t=>{const[o,a]=(0,c.useState)(void 0),{eventRegistration:r,emitResponse:i}=t,{onPaymentSetup:p}=r,l=()=>void 0===o?{type:i.responseTypes.ERROR,message:(0,n.__)("<strong>Internet Banking</strong> is a required field.","chip-for-woocommerce")}:{type:i.responseTypes.SUCCESS,meta:{paymentMethodData:{chip_fpx_bank:o}}};(0,c.useEffect)((()=>{const e=p(l);return()=>{e()}}),[p,o]);const m=gateway_wc_gateway_chip_3.fpx_b2c;let w=[];return Object.keys(m).forEach((e=>{w.push({name:m[e],id:e})})),(0,e.createElement)(s.TreeSelect,{label:(0,n.__)("Internet Banking","chip-for-woocommerce"),noOptionLabel:(0,n.__)("Choose your bank","chip-for-woocommerce"),onChange:e=>{a(e)},selectedId:o,tree:w})},w=t=>{const[o,a]=(0,c.useState)(void 0),{eventRegistration:r,emitResponse:i}=t,{onPaymentSetup:p}=r,l=()=>void 0===o?{type:i.responseTypes.ERROR,message:(0,n.__)("<strong>Corporate Internet Banking</strong> is a required field.","chip-for-woocommerce")}:{type:i.responseTypes.SUCCESS,meta:{paymentMethodData:{chip_fpx_b2b1_bank:o}}};(0,c.useEffect)((()=>{const e=p(l);return()=>{e()}}),[p,o]);const m=gateway_wc_gateway_chip_3.fpx_b2b1;let w=[];return Object.keys(m).forEach((e=>{w.push({name:m[e],id:e})})),(0,e.createElement)(s.TreeSelect,{label:(0,n.__)("Internet Banking","chip-for-woocommerce"),noOptionLabel:(0,n.__)("Choose an option","chip-for-woocommerce"),onChange:e=>{a(e)},selectedId:o,tree:w})},_=t=>(0,e.createElement)(e.Fragment,null,(0,e.createElement)(l,null),"fpx"==r.js_display?(0,e.createElement)(m,{...t}):null,"fpx_b2b1"==r.js_display?(0,e.createElement)(w,{...t}):null),d={name:r.method_name,label:(0,e.createElement)((t=>{const{PaymentMethodLabel:n}=t.components;return(0,e.createElement)(n,{text:p})}),null),content:(0,e.createElement)(_,null),edit:(0,e.createElement)(_,null),canMakePayment:()=>!0,ariaLabel:p,supports:{showSavedCards:r.saved_option,showSaveOption:r.save_option,features:r.supports}};(0,t.registerPaymentMethod)(d)}();