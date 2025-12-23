const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );
const path = require( 'path' );

/**
 * WooCommerce dependency mapping.
 */
const wcDepMap = {
	'@woocommerce/blocks-registry': [ 'wc', 'wcBlocksRegistry' ],
	'@woocommerce/settings': [ 'wc', 'wcSettings' ],
};

/**
 * WooCommerce handle mapping.
 */
const wcHandleMap = {
	'@woocommerce/blocks-registry': 'wc-blocks-registry',
	'@woocommerce/settings': 'wc-settings',
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
		'frontend/blocks_chip_woocommerce_gateway': './resources/js/frontend/blocks_chip_woocommerce_gateway.js',
		'frontend/blocks_chip_woocommerce_gateway_2': './resources/js/frontend/blocks_chip_woocommerce_gateway_2.js',
		'frontend/blocks_chip_woocommerce_gateway_3': './resources/js/frontend/blocks_chip_woocommerce_gateway_3.js',
		'frontend/blocks_chip_woocommerce_gateway_4': './resources/js/frontend/blocks_chip_woocommerce_gateway_4.js',
		'frontend/blocks_chip_woocommerce_gateway_5': './resources/js/frontend/blocks_chip_woocommerce_gateway_5.js',
		'frontend/blocks_chip_woocommerce_gateway_6': './resources/js/frontend/blocks_chip_woocommerce_gateway_6.js',
	},
	output: {
		path: path.resolve( __dirname, 'assets/js' ),
		filename: '[name].js',
	},
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) => 'DependencyExtractionWebpackPlugin' !== plugin.constructor.name
		),
		new WooCommerceDependencyExtractionWebpackPlugin( {
			requestToExternal,
			requestToHandle,
		} ),
	],
};
