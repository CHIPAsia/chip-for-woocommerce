const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );
const path = require( 'path' );

/**
 * Dependency extraction: map an `import` request to a window-global
 * (read by the bundled `wp_*` runtime via a `<script>` tag).
 *
 * Each entry is `[ globalNamespace, globalProperty ]`, which the
 * @woocommerce/dependency-extraction-webpack-plugin turns into a runtime
 * lookup like `window[namespace][property]`.
 */
const wcDepMap = {
	'@woocommerce/blocks-registry': [ 'wc', 'wcBlocksRegistry' ],
	'@woocommerce/settings': [ 'wc', 'wcSettings' ],
	'chip/unified-payment-method-list': [ 'chip', 'UnifiedPaymentMethodList' ],
};

/**
 * Map an `import` request to the WordPress script handle that exposes the
 * runtime global. The PHP blocks-support class registers this handle and
 * lists it as a dependency of each clone bundle so the shared component is
 * available on `window.chip.UnifiedPaymentMethodList` before any clone
 * tries to import it.
 */
const wcHandleMap = {
	'@woocommerce/blocks-registry': 'wc-blocks-registry',
	'@woocommerce/settings': 'wc-settings',
	'chip/unified-payment-method-list': 'chip-unified-payment-method-list',
};

/**
 * Map request to external.
 *
 * @param {string} request The request.
 * @return {Array|undefined} The external mapping.
 */
const requestToExternal = ( request ) => {
	if ( wcDepMap[ request ] ) {
		return wcDepMap[ request ];
	}
};

/**
 * Map request to handle.
 *
 * @param {string} request The request.
 * @return {string|undefined} The handle.
 */
const requestToHandle = ( request ) => {
	if ( wcHandleMap[ request ] ) {
		return wcHandleMap[ request ];
	}
};

/**
 * Webpack configuration.
 */
module.exports = {
	...defaultConfig,
	entry: {
		'frontend/blocks_chip_woocommerce_gateway':
			'./resources/js/frontend/blocks_chip_woocommerce_gateway.js',
		'frontend/blocks_chip_woocommerce_gateway_2':
			'./resources/js/frontend/blocks_chip_woocommerce_gateway_2.js',
		'frontend/blocks_chip_woocommerce_gateway_3':
			'./resources/js/frontend/blocks_chip_woocommerce_gateway_3.js',
		'frontend/blocks_chip_woocommerce_gateway_4':
			'./resources/js/frontend/blocks_chip_woocommerce_gateway_4.js',
		'frontend/blocks_chip_woocommerce_gateway_5':
			'./resources/js/frontend/blocks_chip_woocommerce_gateway_5.js',
		'frontend/blocks_chip_woocommerce_gateway_6':
			'./resources/js/frontend/blocks_chip_woocommerce_gateway_6.js',
		// The shared bundle. Object-form `entry` + per-entry `library` makes
		// webpack 5 emit a UMD-style file that exposes this module's default
		// export at `window.chip.UnifiedPaymentMethodList`. The 5 clone
		// bundles import from `chip/unified-payment-method-list`, which the
		// dependency-extraction plugin resolves to that same global.
		'frontend/unified-payment-method-list': {
			import:
				'./resources/js/frontend/components/unified-payment-method-list.js',
			library: {
				name: [ 'chip', 'UnifiedPaymentMethodList' ],
				type: 'window',
			},
		},
	},
	output: {
		path: path.resolve( __dirname, 'assets/js' ),
		filename: '[name].js',
	},
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
				'DependencyExtractionWebpackPlugin' !== plugin.constructor.name
		),
		new WooCommerceDependencyExtractionWebpackPlugin( {
			requestToExternal,
			requestToHandle,
		} ),
	],
};
