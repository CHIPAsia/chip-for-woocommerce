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
 */
( function( wp ) {
    'use strict';
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useCallback = wp.element.useCallback;

    function UnifiedPaymentMethodList( props ) {
        var value        = useState( '' );
        var setValue     = value[ 1 ];
        var options      = useState( [] );
        var setOptions   = options[ 1 ];
        var loading      = useState( true );
        var setLoading   = loading[ 1 ];
        var error        = useState( null );
        var setError     = error[ 1 ];
        var currentState = options[ 0 ];
        var isLoading    = loading[ 0 ];
        var fetchError   = error[ 0 ];

        useEffect( function() {
            if ( ! props.banksApi ) {
                setLoading( false );
                return;
            }
            setLoading( true );
            fetch( props.banksApi, { headers: { 'X-WP-Nonce': props.nonce } } )
                .then( function( r ) { return r.json(); } )
                .then( function( data ) {
                    var entries = Object.entries( data ).map( function( pair ) {
                        return { value: pair[0], label: pair[1] };
                    } );
                    setOptions( entries );
                    setLoading( false );
                } )
                .catch( function( err ) {
                    setError( err.message || 'Failed to load payment methods' );
                    setLoading( false );
                } );
        }, [ props.banksApi ] );

        // Forward the chosen value to WooCommerce Blocks via onPaymentSetup.
        // Without this, the underlying <select name="chip_payment_method">
        // is not submitted -- only paymentMethodData returned by this hook is.
        var onPaymentSetup = props && props.onPaymentSetup;
        var emitResponse   = props && props.emitResponse;
        var onSubmit       = useCallback( function() {
            if ( ! value[ 0 ] ) {
                return {
                    type: emitResponse && emitResponse.responseTypes
                        ? emitResponse.responseTypes.ERROR
                        : 'error',
                    message: props.requiredMessage || 'Please choose a payment method',
                };
            }
            return {
                type: emitResponse && emitResponse.responseTypes
                    ? emitResponse.responseTypes.SUCCESS
                    : 'success',
                meta: { paymentMethodData: { chip_payment_method: value[ 0 ] } },
            };
        }, [ value[ 0 ], emitResponse ] );

        useEffect( function() {
            if ( typeof onPaymentSetup !== 'function' ) {
                return undefined;
            }
            var unsubscribe = onPaymentSetup( onSubmit );
            return function() {
                if ( typeof unsubscribe === 'function' ) {
                    unsubscribe();
                }
            };
        }, [ onPaymentSetup, onSubmit ] );

        if ( fetchError ) {
            return el( 'div', { className: 'woocommerce-error' }, fetchError );
        }
        if ( isLoading ) {
            return el( 'div', { className: 'chip-loading' }, 'Loading…' );
        }
        if ( currentState.length === 0 ) {
            return null;
        }

        return el(
            'select',
            {
                name:        'chip_payment_method',
                className:   'chip-unified-payment-method',
                'data-testid': 'chip-unified-payment-method',
                required:    true,
                value:       value[ 0 ],
                onChange:    function( e ) { setValue( e.target.value ); },
            },
            el( 'option', { value: '' }, props.placeholder || 'Choose a payment method' ),
            currentState.map( function( opt ) {
                return el( 'option', { key: opt.value, value: opt.value }, opt.label );
            } )
        );
    }

    wp.element.createElement( 'UnifiedPaymentMethodList', UnifiedPaymentMethodList );
} )( window.wp );
