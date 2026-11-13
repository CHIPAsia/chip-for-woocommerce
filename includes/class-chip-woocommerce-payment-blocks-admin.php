<?php
/**
 * CHIP for WooCommerce - Payment Methods screen.
 *
 * WooCommerce → CHIP Payment Methods. Lists the standard blocks (the six SHIP
 * gateways the plugin always registers) plus the blocks the merchant created,
 * and offers a drawer to add or edit a created block.
 *
 * The screen writes straight into each block's WooCommerce gateway settings
 * option, which is the same place the standard gateway settings screen writes.
 * One source of truth per field: whichever screen saved last wins, and no
 * value is stored twice.
 *
 * @package CHIP for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Payment Methods admin screen.
 */
class Chip_Woocommerce_Payment_Blocks_Admin {

	/**
	 * Page slug.
	 */
	const PAGE = 'chip-payment-blocks';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_chip_payment_block_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_chip_payment_block_delete', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_chip_payment_block_toggle', array( __CLASS__, 'handle_toggle' ) );
	}

	/**
	 * Enqueue the screen assets, only on this screen.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue( $hook_suffix ) {
		if ( 'woocommerce_page_' . self::PAGE !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'chip-payment-blocks-admin',
			CHIP_WOOCOMMERCE_URL . 'assets/css/chip-payment-blocks-admin.css',
			array(),
			CHIP_WOOCOMMERCE_MODULE_VERSION
		);

		wp_enqueue_script(
			'chip-payment-blocks-admin',
			CHIP_WOOCOMMERCE_URL . 'assets/js/chip-payment-blocks-admin.js',
			array(),
			CHIP_WOOCOMMERCE_MODULE_VERSION,
			true
		);

		$families = array();
		foreach ( Chip_Woocommerce_Payment_Blocks::families() as $key => $family ) {
			$families[ $key ] = array(
				'title'       => $family['title'],
				'description' => $family['description'],
			);
		}

		wp_localize_script(
			'chip-payment-blocks-admin',
			'ChipBlocksAdmin',
			array(
				'families' => $families,
				'watch'    => array( 'GrabPay', "Touch 'n Go", 'ShopeePay', 'Maybank', 'Atome', 'DuitNow' ),
				'strings'  => array(
					/* translators: %1$s: number of selected methods, %2$s: total methods in the family */
					'counter'   => __( '%1$s of %2$s methods selected', 'chip-for-woocommerce' ),
					'empty'     => __( 'Pick at least one method — a block with no method cannot be saved.', 'chip-for-woocommerce' ),
					/* translators: %s: payment method name */
					'single'    => __( 'Single-method block: checkout submits %s directly, with no dropdown.', 'chip-for-woocommerce' ),
					/* translators: %s: number of payment methods */
					'multi'     => __( 'Dropdown block: %s methods, sent to CHIP as the payment-method whitelist.', 'chip-for-woocommerce' ),
					/* translators: %s: payment methods named in the title */
					'mismatch'  => __( 'The checkout title names %s, but that is not in the methods above — customers will look for a method this block does not accept.', 'chip-for-woocommerce' ),
					'addTitle'  => __( 'Add payment method', 'chip-for-woocommerce' ),
					'editTitle' => __( 'Edit payment method', 'chip-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Add the screen under WooCommerce.
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'CHIP Payment Methods', 'chip-for-woocommerce' ),
			__( 'CHIP Payment Methods', 'chip-for-woocommerce' ),
			'manage_woocommerce',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * URL of this screen.
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	protected static function page_url( array $args = array() ) {
		return add_query_arg(
			array_merge( array( 'page' => self::PAGE ), $args ),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Settings screen URL for a standard gateway.
	 *
	 * @param string $gateway_id Gateway id.
	 * @return string
	 */
	protected static function gateway_settings_url( $gateway_id ) {
		return add_query_arg(
			array(
				'page'    => 'wc-settings',
				'tab'     => 'checkout',
				'section' => $gateway_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Standard CHIP gateways (the six that ship with the plugin).
	 *
	 * @return array
	 */
	protected static function standard_gateways() {
		if ( ! function_exists( 'WC' ) || null === WC()->payment_gateways() ) {
			return array();
		}

		$created = array_column( Chip_Woocommerce_Payment_Blocks::get_blocks(), 'id' );
		$created = array_map( array( 'Chip_Woocommerce_Payment_Blocks', 'gateway_id' ), $created );

		$standard = array();
		foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
			if ( ! is_a( $gateway, 'Chip_Woocommerce_Gateway' ) ) {
				continue;
			}
			if ( in_array( $gateway->id, $created, true ) ) {
				continue;
			}
			$standard[] = $gateway;
		}

		return $standard;
	}

	/**
	 * Method labels for a settings row.
	 *
	 * @param array $settings Block settings.
	 * @return array
	 */
	protected static function method_labels( array $settings ) {
		$labels  = Chip_Woocommerce_Gateway::payment_method_labels();
		$methods = isset( $settings['payment_method_whitelist'] ) ? (array) $settings['payment_method_whitelist'] : array();

		$out = array();
		foreach ( $methods as $method ) {
			if ( isset( $labels[ $method ] ) ) {
				$out[] = $labels[ $method ];
			}
		}

		return $out;
	}

	/**
	 * Handle create/update.
	 *
	 * @return void
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage payment methods.', 'chip-for-woocommerce' ) );
		}
		check_admin_referer( 'chip_payment_block_save' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$block_id    = isset( $_POST['block_id'] ) ? sanitize_key( wp_unslash( $_POST['block_id'] ) ) : '';
		$family      = isset( $_POST['family'] ) ? sanitize_key( wp_unslash( $_POST['family'] ) ) : '';
		$methods     = isset( $_POST['methods'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['methods'] ) ) : array();
		$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
		$logo        = isset( $_POST['logo'] ) ? sanitize_key( wp_unslash( $_POST['logo'] ) ) : '';
		$enabled     = isset( $_POST['enabled'] ) && 'no' === $_POST['enabled'] ? 'no' : 'yes';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$args = array(
			'methods'     => $methods,
			'title'       => $title,
			'description' => $description,
			'logo'        => $logo,
			'enabled'     => $enabled,
		);

		if ( '' === $block_id ) {
			$result = Chip_Woocommerce_Payment_Blocks::add_block( $family, $args );
			$notice = 'created';
		} else {
			$result = Chip_Woocommerce_Payment_Blocks::update_block( $block_id, $args );
			$notice = 'updated';
		}

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( self::page_url( array( 'chip_error' => rawurlencode( $result->get_error_message() ) ) ) );
			exit;
		}

		wp_safe_redirect( self::page_url( array( 'chip_notice' => $notice ) ) );
		exit;
	}

	/**
	 * Handle delete.
	 *
	 * @return void
	 */
	public static function handle_delete() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage payment methods.', 'chip-for-woocommerce' ) );
		}
		check_admin_referer( 'chip_payment_block_delete' );

		$block_id = isset( $_POST['block_id'] ) ? sanitize_key( wp_unslash( $_POST['block_id'] ) ) : '';
		Chip_Woocommerce_Payment_Blocks::delete_block( $block_id );

		wp_safe_redirect( self::page_url( array( 'chip_notice' => 'deleted' ) ) );
		exit;
	}

	/**
	 * Handle enable/disable.
	 *
	 * @return void
	 */
	public static function handle_toggle() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage payment methods.', 'chip-for-woocommerce' ) );
		}
		check_admin_referer( 'chip_payment_block_toggle' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$block_id = isset( $_POST['block_id'] ) ? sanitize_key( wp_unslash( $_POST['block_id'] ) ) : '';
		$enabled  = isset( $_POST['enabled'] ) && 'yes' === $_POST['enabled'] ? 'yes' : 'no';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$settings            = Chip_Woocommerce_Payment_Blocks::get_block_settings( $block_id );
		$settings['enabled'] = $enabled;
		update_option( Chip_Woocommerce_Payment_Blocks::settings_key( $block_id ), $settings );

		wp_safe_redirect( self::page_url( array( 'chip_notice' => 'updated' ) ) );
		exit;
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$blocks   = Chip_Woocommerce_Payment_Blocks::get_blocks();
		$families = Chip_Woocommerce_Payment_Blocks::families();
		$labels   = Chip_Woocommerce_Gateway::payment_method_labels();
		$standard = self::standard_gateways();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only notice display.
		$notice = isset( $_GET['chip_notice'] ) ? sanitize_key( wp_unslash( $_GET['chip_notice'] ) ) : '';
		$error  = isset( $_GET['chip_error'] ) ? sanitize_text_field( wp_unslash( $_GET['chip_error'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap chip-blocks">
			<h1>
				<?php esc_html_e( 'CHIP Payment Methods', 'chip-for-woocommerce' ); ?>
				<button type="button" class="page-title-action" data-chip-drawer-open><?php esc_html_e( 'Add payment method', 'chip-for-woocommerce' ); ?></button>
			</h1>

			<p class="chip-blocks__lede">
				<?php esc_html_e( 'Each row below becomes its own payment block on the checkout page. Add a block when a payment family has to be split into several blocks — for example GrabPay + Touch \'n Go in one block, and ShopeePay on its own.', 'chip-for-woocommerce' ); ?>
			</p>

			<?php if ( 'created' === $notice || 'updated' === $notice || 'deleted' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php
					if ( 'created' === $notice ) {
						esc_html_e( 'Payment block created.', 'chip-for-woocommerce' );
					} elseif ( 'updated' === $notice ) {
						esc_html_e( 'Payment block updated.', 'chip-for-woocommerce' );
					} else {
						esc_html_e( 'Payment block deleted.', 'chip-for-woocommerce' );
					}
					?>
				</p></div>
			<?php endif; ?>
			<?php if ( '' !== $error ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<div class="chip-blocks__notice">
				<strong><?php esc_html_e( 'Nothing changes until you add a block.', 'chip-for-woocommerce' ); ?></strong>
				<?php esc_html_e( 'The standard blocks keep their current titles, methods and settings. A block you create here is additional; deleting it restores the previous checkout.', 'chip-for-woocommerce' ); ?>
			</div>

			<table class="widefat striped chip-blocks__table">
				<thead>
					<tr>
						<th class="chip-blocks__col-title"><?php esc_html_e( 'Payment block', 'chip-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Methods shown in the block', 'chip-for-woocommerce' ); ?></th>
						<th class="chip-blocks__col-cred"><?php esc_html_e( 'CHIP credentials', 'chip-for-woocommerce' ); ?></th>
						<th class="chip-blocks__col-status"><?php esc_html_e( 'Status', 'chip-for-woocommerce' ); ?></th>
						<th class="chip-blocks__col-actions"><?php esc_html_e( 'Actions', 'chip-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $standard as $gateway ) : ?>
						<?php $standard_methods = self::method_labels( (array) $gateway->settings ); ?>
						<tr data-chip-block-row data-title="<?php echo esc_attr( $gateway->get_title() ); ?>" data-methods="<?php echo esc_attr( implode( ' · ', $standard_methods ) ); ?>">
							<td>
								<strong><?php echo esc_html( $gateway->get_title() ); ?></strong>
								<div class="chip-blocks__meta"><?php esc_html_e( 'Standard block', 'chip-for-woocommerce' ); ?></div>
							</td>
							<td>
								<?php if ( empty( $standard_methods ) ) : ?>
									<span class="chip-blocks__chip is-auto"><?php esc_html_e( 'All available methods', 'chip-for-woocommerce' ); ?></span>
								<?php else : ?>
									<?php foreach ( $standard_methods as $method_label ) : ?>
										<span class="chip-blocks__chip"><?php echo esc_html( $method_label ); ?></span>
									<?php endforeach; ?>
								<?php endif; ?>
							</td>
							<td class="chip-blocks__meta"><?php esc_html_e( 'Primary', 'chip-for-woocommerce' ); ?></td>
							<td>
								<span class="chip-blocks__state <?php echo 'yes' === $gateway->enabled ? 'is-on' : 'is-off'; ?>">
									<?php echo 'yes' === $gateway->enabled ? esc_html__( 'Enabled', 'chip-for-woocommerce' ) : esc_html__( 'Disabled', 'chip-for-woocommerce' ); ?>
								</span>
							</td>
							<td>
								<a href="<?php echo esc_url( self::gateway_settings_url( $gateway->id ) ); ?>"><?php esc_html_e( 'Settings', 'chip-for-woocommerce' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>

					<?php foreach ( $blocks as $block ) : ?>
						<?php
						$settings      = Chip_Woocommerce_Payment_Blocks::get_block_settings( $block['id'] );
						$block_title   = isset( $settings['title'] ) && '' !== $settings['title'] ? $settings['title'] : $families[ $block['family'] ]['title'];
						$block_methods = self::method_labels( $settings );
						$block_enabled = isset( $settings['enabled'] ) ? $settings['enabled'] : 'no';
						$has_own_creds = ! empty( $settings['brand_id'] ) && ! empty( $settings['secret_key'] );
						?>
						<tr data-chip-block-row data-title="<?php echo esc_attr( $block_title ); ?>" data-methods="<?php echo esc_attr( implode( ' · ', $block_methods ) ); ?>">
							<td>
								<strong><?php echo esc_html( $block_title ); ?></strong>
								<div class="chip-blocks__meta">
									<?php echo esc_html( $families[ $block['family'] ]['label'] ); ?>
									<span class="chip-blocks__badge"><?php esc_html_e( 'custom', 'chip-for-woocommerce' ); ?></span>
								</div>
							</td>
							<td>
								<?php foreach ( $block_methods as $method_label ) : ?>
									<span class="chip-blocks__chip"><?php echo esc_html( $method_label ); ?></span>
								<?php endforeach; ?>
								<?php if ( 1 === count( $block_methods ) ) : ?>
									<span class="chip-blocks__meta"><?php esc_html_e( 'single method — submitted directly', 'chip-for-woocommerce' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="chip-blocks__meta">
								<?php echo $has_own_creds ? esc_html__( 'Own brand / key', 'chip-for-woocommerce' ) : esc_html__( 'Inherits Primary', 'chip-for-woocommerce' ); ?>
							</td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="chip-blocks__inline-form">
									<input type="hidden" name="action" value="chip_payment_block_toggle" />
									<input type="hidden" name="block_id" value="<?php echo esc_attr( $block['id'] ); ?>" />
									<input type="hidden" name="enabled" value="<?php echo 'yes' === $block_enabled ? 'no' : 'yes'; ?>" />
									<?php wp_nonce_field( 'chip_payment_block_toggle' ); ?>
									<button type="submit" class="chip-blocks__state-button <?php echo 'yes' === $block_enabled ? 'is-on' : 'is-off'; ?>">
										<?php echo 'yes' === $block_enabled ? esc_html__( 'Enabled', 'chip-for-woocommerce' ) : esc_html__( 'Disabled', 'chip-for-woocommerce' ); ?>
									</button>
								</form>
							</td>
							<td class="chip-blocks__actions">
								<button
									type="button"
									class="button-link"
									data-chip-drawer-open
									data-block-id="<?php echo esc_attr( $block['id'] ); ?>"
									data-family="<?php echo esc_attr( $block['family'] ); ?>"
									data-title="<?php echo esc_attr( $block_title ); ?>"
									data-description="<?php echo esc_attr( isset( $settings['description'] ) ? $settings['description'] : '' ); ?>"
									data-logo="<?php echo esc_attr( isset( $settings['display_logo'] ) ? $settings['display_logo'] : '' ); ?>"
									data-enabled="<?php echo esc_attr( $block_enabled ); ?>"
									data-methods="<?php echo esc_attr( implode( ',', isset( $settings['payment_method_whitelist'] ) ? (array) $settings['payment_method_whitelist'] : array() ) ); ?>"
								><?php esc_html_e( 'Edit', 'chip-for-woocommerce' ); ?></button>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="chip-blocks__inline-form">
									<input type="hidden" name="action" value="chip_payment_block_delete" />
									<input type="hidden" name="block_id" value="<?php echo esc_attr( $block['id'] ); ?>" />
									<?php wp_nonce_field( 'chip_payment_block_delete' ); ?>
									<button type="submit" class="button-link button-link-delete"><?php esc_html_e( 'Delete', 'chip-for-woocommerce' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p class="chip-blocks__meta">
				<?php esc_html_e( 'Standard blocks are configured on their own WooCommerce settings screen and cannot be deleted here. Blocks you create can be edited or deleted; their checkout order follows the WooCommerce gateway order.', 'chip-for-woocommerce' ); ?>
			</p>

			<div class="chip-blocks__scrim" data-chip-scrim></div>

			<div class="chip-blocks__drawer" data-chip-drawer aria-hidden="true">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-chip-form>
					<input type="hidden" name="action" value="chip_payment_block_save" />
					<input type="hidden" name="block_id" value="" data-chip-block-id />
					<?php wp_nonce_field( 'chip_payment_block_save' ); ?>

					<header class="chip-blocks__drawer-head">
						<h2 data-chip-drawer-title><?php esc_html_e( 'Add payment method', 'chip-for-woocommerce' ); ?></h2>
						<button type="button" class="chip-blocks__drawer-close" data-chip-drawer-close aria-label="<?php esc_attr_e( 'Close', 'chip-for-woocommerce' ); ?>">&times;</button>
					</header>

					<div class="chip-blocks__drawer-body">
						<div class="chip-blocks__field">
							<label for="chip-block-family"><?php esc_html_e( 'Payment family', 'chip-for-woocommerce' ); ?></label>
							<select id="chip-block-family" name="family" data-chip-family>
								<?php foreach ( $families as $key => $family ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $family['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="chip-blocks__hint"><?php esc_html_e( 'The family decides which methods a block can offer and how the block behaves at checkout. It cannot be changed after the block is created.', 'chip-for-woocommerce' ); ?></p>
						</div>

						<div class="chip-blocks__field">
							<span class="chip-blocks__label"><?php esc_html_e( 'Methods in this block', 'chip-for-woocommerce' ); ?></span>
							<?php foreach ( $families as $key => $family ) : ?>
								<div class="chip-blocks__methods" data-chip-family-methods="<?php echo esc_attr( $key ); ?>">
									<?php foreach ( $family['methods'] as $method ) : ?>
										<label class="chip-blocks__method">
											<input type="checkbox" name="methods[]" value="<?php echo esc_attr( $method ); ?>" />
											<span><?php echo esc_html( isset( $labels[ $method ] ) ? $labels[ $method ] : $method ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							<?php endforeach; ?>
							<p class="chip-blocks__hint"><?php esc_html_e( 'Only these methods are offered inside the block, and only these are sent to CHIP as the payment-method whitelist. A block with a single e-wallet submits that wallet directly, with no dropdown.', 'chip-for-woocommerce' ); ?></p>
						</div>

						<div class="chip-blocks__field">
							<label for="chip-block-title"><?php esc_html_e( 'Title shown at checkout', 'chip-for-woocommerce' ); ?></label>
							<input type="text" id="chip-block-title" name="title" value="" data-chip-title />
							<p class="chip-blocks__hint"><?php esc_html_e( 'This controls the text of the checkout radio button.', 'chip-for-woocommerce' ); ?></p>
						</div>

						<div class="chip-blocks__field">
							<label for="chip-block-description"><?php esc_html_e( 'Description', 'chip-for-woocommerce' ); ?></label>
							<input type="text" id="chip-block-description" name="description" value="" data-chip-description />
						</div>

						<div class="chip-blocks__field">
							<label for="chip-block-logo"><?php esc_html_e( 'Logo on the block', 'chip-for-woocommerce' ); ?></label>
							<select id="chip-block-logo" name="logo" data-chip-logo>
								<option value="auto"><?php esc_html_e( 'Auto — built from the selected methods', 'chip-for-woocommerce' ); ?></option>
								<option value="logo"><?php esc_html_e( 'CHIP logo', 'chip-for-woocommerce' ); ?></option>
								<option value="ewallet"><?php esc_html_e( 'E-Wallet logos with CHIP branding', 'chip-for-woocommerce' ); ?></option>
								<option value="ewallet_only"><?php esc_html_e( 'E-Wallet logos only', 'chip-for-woocommerce' ); ?></option>
								<option value="fpx"><?php esc_html_e( 'FPX with CHIP branding', 'chip-for-woocommerce' ); ?></option>
								<option value="fpx_only"><?php esc_html_e( 'FPX only', 'chip-for-woocommerce' ); ?></option>
								<option value="card"><?php esc_html_e( 'Card with CHIP branding', 'chip-for-woocommerce' ); ?></option>
								<option value="card_only"><?php esc_html_e( 'Card only', 'chip-for-woocommerce' ); ?></option>
								<option value="duitnow"><?php esc_html_e( 'DuitNow QR with CHIP branding', 'chip-for-woocommerce' ); ?></option>
								<option value="duitnow_only"><?php esc_html_e( 'DuitNow QR only', 'chip-for-woocommerce' ); ?></option>
								<option value="paywithchip_all"><?php esc_html_e( 'Pay with CHIP (all methods)', 'chip-for-woocommerce' ); ?></option>
							</select>
							<p class="chip-blocks__hint"><?php esc_html_e( '"Auto" composes the icon from the wallets you ticked, so a wallet you remove stops appearing on the block.', 'chip-for-woocommerce' ); ?></p>
						</div>

						<div class="chip-blocks__field">
							<label for="chip-block-enabled"><?php esc_html_e( 'Status', 'chip-for-woocommerce' ); ?></label>
							<select id="chip-block-enabled" name="enabled" data-chip-enabled>
								<option value="yes"><?php esc_html_e( 'Enabled — shown at checkout', 'chip-for-woocommerce' ); ?></option>
								<option value="no"><?php esc_html_e( 'Disabled — hidden at checkout', 'chip-for-woocommerce' ); ?></option>
							</select>
						</div>

						<div class="chip-blocks__field">
							<span class="chip-blocks__label"><?php esc_html_e( 'CHIP credentials', 'chip-for-woocommerce' ); ?></span>
							<p class="chip-blocks__hint"><?php esc_html_e( 'Inherited from the primary CHIP block (Online Banking / FPX). Brand ID and secret key are entered once per site; open the block\'s WooCommerce settings screen to use a different brand for this block.', 'chip-for-woocommerce' ); ?></p>
						</div>

						<div class="chip-blocks__status" data-chip-status></div>

						<div class="chip-blocks__field">
							<span class="chip-blocks__label"><?php esc_html_e( 'Checkout preview', 'chip-for-woocommerce' ); ?></span>
							<div class="chip-blocks__preview" data-chip-preview></div>
						</div>
					</div>

					<footer class="chip-blocks__drawer-foot">
						<span class="chip-blocks__counter" data-chip-counter></span>
						<button type="button" class="button" data-chip-drawer-close><?php esc_html_e( 'Cancel', 'chip-for-woocommerce' ); ?></button>
						<button type="submit" class="button button-primary" data-chip-submit><?php esc_html_e( 'Save payment method', 'chip-for-woocommerce' ); ?></button>
					</footer>
				</form>
			</div>
		</div>
		<?php
	}
}
