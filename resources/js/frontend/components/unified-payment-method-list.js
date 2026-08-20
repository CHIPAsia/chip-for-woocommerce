/**
 * UnifiedPaymentMethodList
 *
 * Renders a single <select> listing all eligible payment methods for the
 * current gateway. Replaces the three separate FPX/Razer dropdowns with one
 * unified picker. Selected value is submitted as `chip_payment_method`
 * with a tag-encoded format (e.g. 'fpx:MB2U0227', 'dnqr', 'card').
 *
 * Props:
 *   - nonce           (string)  X-WP-Nonce for the REST request
 *   - banksApi        (string)  Full REST URL to the unified banks endpoint
 *   - placeholder     (string)  Placeholder text for the empty option
 *   - onPaymentSetup  (func)    WooCommerce Blocks onPaymentSetup callback
 *   - emitResponse    (object)  WooCommerce Blocks emitResponse helpers
 *   - requiredMessage (string)  Custom error message when no value chosen
 *   - onChange        (func)    Optional callback fired with the selected
 *                               tag-encoded value whenever the selection
 *                               changes (e.g. so a parent component can
 *                               show/hide the card form accordingly)
 *
 * Consumed by the 5 clone bundles (gateway 1/2/3/4/6) via:
 *   `import UnifiedPaymentMethodList from 'chip/unified-payment-method-list';`
 *
 * Webpack treats `chip/unified-payment-method-list` as an external in the
 * clone entries and resolves it to `window.chip.UnifiedPaymentMethodList` at
 * runtime. This shared bundle exposes its default export at that global via
 * a per-entry `library` config in webpack.config.js. The shared bundle must
 * load BEFORE the clone bundles; the PHP blocks-support class appends
 * `chip-unified-payment-method-list` to each clone's `dependencies` array to
 * guarantee that load order.
 */
import { useState, useEffect, useCallback, useId } from '@wordpress/element';
import { Icon, chevronDown } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

const UnifiedPaymentMethodList = ( props ) => {
    const [ options, setOptions ] = useState( [] );
    const [ loading, setLoading ] = useState( true );
    const [ error, setError ]     = useState( null );
    const [ value, setValue ]     = useState( '' );

    // Must be called before any early returns (Rules of Hooks).
    const generatedId = useId();
    const inputId = `chip-unified-payment-method-${ generatedId }`;

    useEffect( () => {
        if ( ! props.banksApi ) {
            setLoading( false );
            return undefined;
        }
        setLoading( true );
        fetch( props.banksApi, { headers: { 'X-WP-Nonce': props.nonce } } )
            .then( ( r ) => r.json() )
            .then( ( data ) => {
                const entries = Object.entries( data ).map( ( [ tag, label ] ) => ( {
                    value: tag,
                    label,
                } ) );
                setOptions( entries );
                setLoading( false );
            } )
            .catch( ( err ) => {
                setError(
                    err.message ||
                        __( 'Failed to load payment methods', 'chip-for-woocommerce' )
                );
                setLoading( false );
            } );

        return () => {
            // No cleanup needed; fetch results that arrive after unmount
            // are ignored by React because the component state is gone.
        };
    }, [ props.banksApi ] );

    const emitResponse = props.emitResponse;
    const onSubmit = useCallback( () => {
        if ( ! value ) {
            return {
                type: emitResponse?.responseTypes?.ERROR || 'error',
                message:
                    props.requiredMessage ||
                    __( 'Please choose a payment method', 'chip-for-woocommerce' ),
            };
        }
        return {
            type: emitResponse?.responseTypes?.SUCCESS || 'success',
            meta: { paymentMethodData: { chip_payment_method: value } },
        };
    }, [ value, emitResponse ] );

    useEffect( () => {
        if ( typeof props.onPaymentSetup !== 'function' ) {
            return undefined;
        }
        const unsubscribe = props.onPaymentSetup( onSubmit );
        return () => {
            if ( typeof unsubscribe === 'function' ) {
                unsubscribe();
            }
        };
    }, [ props.onPaymentSetup, onSubmit ] );

    if ( error ) {
        return <div className="woocommerce-error">{ error }</div>;
    }
    if ( loading ) {
        return (
            <div className="chip-loading">
                { __( 'Loading…', 'chip-for-woocommerce' ) }
            </div>
        );
    }
    if ( options.length === 0 ) {
        return null;
    }

    return (
        <div className="wc-blocks-components-select">
            <div className="wc-blocks-components-select__container">
                <label
                    htmlFor={ inputId }
                    className="wc-blocks-components-select__label"
                >
                    { __( 'Payment method', 'chip-for-woocommerce' ) }
                </label>
                <select
                    id={ inputId }
                    name="chip_payment_method"
                    className="wc-blocks-components-select__select chip-unified-payment-method"
                    data-testid="chip-unified-payment-method"
                    required
                    value={ value }
                    onChange={ ( e ) => {
                        setValue( e.target.value );
                        if ( typeof props.onChange === 'function' ) {
                            props.onChange( e.target.value );
                        }
                    } }
                >
                    <option value="" disabled>
                        { props.placeholder ||
                            __( 'Choose a payment method', 'chip-for-woocommerce' ) }
                    </option>
                    { options.map( ( opt ) => (
                        <option key={ opt.value } value={ opt.value }>
                            { opt.label }
                        </option>
                    ) ) }
                </select>
                <Icon
                    className="wc-blocks-components-select__expand"
                    icon={ chevronDown }
                />
            </div>
        </div>
    );
};

export default UnifiedPaymentMethodList;
