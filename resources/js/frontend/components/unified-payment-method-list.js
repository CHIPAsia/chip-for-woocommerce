/**
 * UnifiedPaymentMethodList
 *
 * Renders a single <select> listing all eligible payment methods for the
 * current gateway. Replaces the three separate FPX/Razer dropdowns with one
 * unified picker. Selected value is submitted as `chip_payment_method`
 * with a tag-encoded format (e.g. 'fpx:MB2U0227', 'dnqr', 'card').
 *
 * Props:
 *   - nonce         (string)  X-WP-Nonce for the REST request
 *   - banksApi      (string)  Full REST URL to the unified banks endpoint
 *   - logoBaseUrl   (string)  Base URL for bank/ewallet logo PNGs
 *   - cardLogosUrl  (string)  Base URL for card brand SVGs
 *   - placeholder   (string)  Placeholder text for the empty option
 */
( function( wp ) {
    'use strict';
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;

    function UnifiedPaymentMethodList( props ) {
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
            },
            el( 'option', { value: '' }, props.placeholder || 'Choose a payment method' ),
            currentState.map( function( opt ) {
                return el( 'option', { key: opt.value, value: opt.value }, opt.label );
            } )
        );
    }

    wp.element.createElement( 'UnifiedPaymentMethodList', UnifiedPaymentMethodList );
} )( window.wp );
