const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const WooCommerceDependencyExtractionWebpackPlugin = require('@woocommerce/dependency-extraction-webpack-plugin');
const path = require('path');

const wcDepMap = {
	'@woocommerce/blocks-registry': ['wc', 'wcBlocksRegistry'],
	'@woocommerce/settings'       : ['wc', 'wcSettings']
};

const wcHandleMap = {
	'@woocommerce/blocks-registry': 'wc-blocks-registry',
	'@woocommerce/settings'       : 'wc-settings'
};

const requestToExternal = (request) => {
	if (wcDepMap[request]) {
		return wcDepMap[request];
	}
};

const requestToHandle = (request) => {
	if (wcHandleMap[request]) {
		return wcHandleMap[request];
	}
};

// Export configuration.
module.exports = {
	...defaultConfig,
	entry: {
		'frontend/blocks_chip_woocommerce_gateway': '/resources/js/frontend/blocks_chip_woocommerce_gateway.js',
    'frontend/blocks_chip_woocommerce_gateway_2': '/resources/js/frontend/blocks_chip_woocommerce_gateway_2.js',
    'frontend/blocks_chip_woocommerce_gateway_3': '/resources/js/frontend/blocks_chip_woocommerce_gateway_3.js',
    'frontend/blocks_chip_woocommerce_gateway_4': '/resources/js/frontend/blocks_chip_woocommerce_gateway_4.js',
    'frontend/blocks_chip_woocommerce_gateway_5': '/resources/js/frontend/blocks_chip_woocommerce_gateway_5.js',
    'frontend/blocks_chip_woocommerce_gateway_6': '/resources/js/frontend/blocks_chip_woocommerce_gateway_6.js',
	},
	output: {
		path: path.resolve( __dirname, 'assets/js' ),
		filename: '[name].js',
	},
	plugins: [
		...defaultConfig.plugins.filter(
			(plugin) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new WooCommerceDependencyExtractionWebpackPlugin({
			requestToExternal,
			requestToHandle
		})
	]
};
