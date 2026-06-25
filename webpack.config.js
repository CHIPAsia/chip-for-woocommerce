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
 * Webpack plugin that injects the shared `unified-payment-method-list`
 * bundle as a dependency of every clone bundle.
 *
 * After the main compile, each clone's generated `*.asset.php` declares
 * the modules it imports (e.g. `react-jsx-runtime`, `wc-blocks-registry`).
 * The clones use `wp.element.createElement( 'UnifiedPaymentMethodList' )`
 * to retrieve a component registered by the shared bundle, so the shared
 * bundle must load first. We append it to the dependencies array of every
 * `*.asset.php` we find under assets/js/frontend/, except the shared
 * bundle's own manifest.
 *
 * The plugin only modifies clones (entries whose asset file lives in
 * `assets/js/frontend/` and is not the shared bundle itself). This keeps
 * the shared bundle free of a self-referencing dependency.
 */
class SharedBundleDependencyPlugin {
	constructor( options ) {
		this.sharedHandle = options.sharedHandle;
	}

	apply( compiler ) {
		const fs      = require( 'fs' );
		const pathMod = require( 'path' );

		const sharedHandle = this.sharedHandle;
		const baseDir      = pathMod.resolve( compiler.options.output.path );
		const dir          = pathMod.join( baseDir, 'frontend' );

		const rewriteAssets = () => {
			if ( ! fs.existsSync( dir ) ) {
				return;
			}
			fs.readdirSync( dir )
				.filter( ( f ) => f.endsWith( '.asset.php' ) )
				.forEach( ( file ) => {
					if ( file === sharedHandle + '.asset.php' ) {
						return;
					}
					const fullPath = pathMod.join( dir, file );
					let   source   = fs.readFileSync( fullPath, 'utf8' );
					if ( source.indexOf( "'" + sharedHandle + "'" ) !== -1 ) {
						return;
					}
					const updated = source.replace(
						/('dependencies'\s*=>\s*array\()([^)]*?)(\))/,
						function ( match, open, items, close ) {
							if ( items.trim() === '' ) {
								return open + "'" + sharedHandle + "'" + close;
							}
							return open + items + ", '" + sharedHandle + "'" + close;
						}
					);
					if ( updated !== source ) {
						fs.writeFileSync( fullPath, updated );
					}
				} );
		};

		// DependencyExtractionWebpackPlugin emits *.asset.php files in child
		// compilations. Hook into the compiler's afterDone so we run once,
		// after every compilation (main and children) has finished emitting.
		compiler.hooks.afterDone.tap( 'SharedBundleDependencyPlugin', rewriteAssets );
	}
}

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
		'frontend/unified-payment-method-list': './resources/js/frontend/components/unified-payment-method-list.js',
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
		new SharedBundleDependencyPlugin( {
			sharedHandle: 'unified-payment-method-list',
		} ),
	],
};
