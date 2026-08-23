<?php
/**
 * CHIP for WooCommerce Admin Token Metabox
 *
 * Adds a metabox to the subscription admin page that lets a store owner
 * switch the subscription's saved card (payment token) without requiring the
 * customer to log in. Only existing saved tokens are offered — the admin
 * never enters a raw card number. Selecting a token records a consent note
 * on the subscription for audit purposes.
 *
 * @package CHIP for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CHIP Admin Token Metabox class.
 */
class Chip_Woocommerce_Admin_Token {

	/**
	 * Singleton instance.
	 *
	 * @var Chip_Woocommerce_Admin_Token
	 */
	private static $instance;

	/**
	 * Get singleton instance.
	 *
	 * @return Chip_Woocommerce_Admin_Token
	 */
	public static function get_instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->add_actions();
	}

	/**
	 * Add actions.
	 *
	 * @return void
	 */
	public function add_actions() {
		add_action( 'add_meta_boxes', array( $this, 'add_token_metabox' ) );
		// 'woocommerce_update_order' fires for both HPOS and legacy post
		// storage when an order/subscription is saved in the admin.
		add_action( 'woocommerce_update_order', array( $this, 'save_token_metabox' ), 10, 1 );
	}

	/**
	 * Add the saved-token metabox to the subscription admin page.
	 *
	 * @return void
	 */
	public function add_token_metabox() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		// With HPOS enabled the subscription edit screen id is
		// 'woocommerce_page_wc-orders', not 'shop_subscription'. Resolve the
		// correct id via WooCommerce's helper (falls back to the raw type).
		$subscription_screen_id = function_exists( 'wc_get_page_screen_id' )
			? wc_get_page_screen_id( 'shop_subscription' )
			: 'shop_subscription';

		if ( $subscription_screen_id !== $screen->id ) {
			return;
		}

		add_meta_box(
			'chip-subscription-token',
			__( 'CHIP Saved Card', 'chip-for-woocommerce' ),
			array( $this, 'render_token_metabox' ),
			$subscription_screen_id,
			'side',
			'high'
		);
	}

	/**
	 * Render the saved-token metabox.
	 *
	 * @param WP_Post $post Subscription post object.
	 * @return void
	 */
	public function render_token_metabox( $post ) {
		$subscription = wcs_get_subscription( $post->ID );

		if ( ! $subscription ) {
			return;
		}

		$customer_id = $subscription->get_customer_id();

		if ( ! $customer_id ) {
			echo '<p>' . esc_html__( 'This subscription has no customer assigned.', 'chip-for-woocommerce' ) . '</p>';
			return;
		}

		// Only offer tokens for the subscription's own payment gateway. A
		// token saved under a different CHIP clone (e.g. wc_gateway_chip_3)
		// cannot be charged by this subscription's gateway (auto_charge()
		// matches tokens by exact gateway id), so offering it would let the
		// admin pick a card that fails every renewal with "Invalid or
		// inactive recurring token".
		$gateway_id = $subscription->get_payment_method();
		$tokens     = WC_Payment_Tokens::get_customer_tokens( $customer_id, $gateway_id );

		$chip_tokens = array();
		foreach ( $tokens as $token ) {
			if ( $token->get_gateway_id() === $gateway_id ) {
				$chip_tokens[] = $token;
			}
		}

		if ( empty( $chip_tokens ) ) {
			echo '<p>' . esc_html__( 'This customer has no saved CHIP cards.', 'chip-for-woocommerce' ) . '</p>';
			return;
		}

		$current_token_ids = $subscription->get_payment_tokens();
		$current_token_id  = ! empty( $current_token_ids ) ? current( $current_token_ids ) : '';

		wp_nonce_field( 'chip_admin_token_' . $post->ID, 'chip_admin_token_nonce' );
		?>
		<p>
			<label for="chip_admin_token_id"><?php esc_html_e( 'Saved card', 'chip-for-woocommerce' ); ?></label>
			<select name="chip_admin_token_id" id="chip_admin_token_id" style="width: 100%;">
				<?php foreach ( $chip_tokens as $token ) : ?>
					<option value="<?php echo esc_attr( $token->get_id() ); ?>" <?php selected( $current_token_id, $token->get_id() ); ?>>
						<?php echo esc_html( $token->get_display_name() ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description">
			<?php esc_html_e( 'Switching the saved card requires the customer\'s consent. Confirm you have obtained consent before saving.', 'chip-for-woocommerce' ); ?>
		</p>
		<?php
	}

	/**
	 * Save the saved-token metabox.
	 *
	 * @param int $order_id Order/subscription ID.
	 * @return void
	 */
	public function save_token_metabox( $order_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified below.
		if ( ! isset( $_POST['chip_admin_token_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['chip_admin_token_nonce'] ) ), 'chip_admin_token_' . $order_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['chip_admin_token_id'] ) ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$token_id = absint( $_POST['chip_admin_token_id'] );

		if ( ! $token_id ) {
			return;
		}

		$subscription = wcs_get_subscription( $order_id );

		if ( ! $subscription ) {
			return;
		}

		$token = WC_Payment_Tokens::get( $token_id );

		// The token must belong to the subscription's own payment gateway.
		// A token saved under a different CHIP clone cannot be charged by
		// this subscription's gateway (auto_charge() matches by exact
		// gateway id), so accepting it would fail every renewal.
		if ( ! $token || $token->get_gateway_id() !== $subscription->get_payment_method() ) {
			return;
		}

		// The token must belong to the subscription's customer.
		if ( $token->get_user_id() !== $subscription->get_customer_id() ) {
			return;
		}

		$current_token_ids = $subscription->get_payment_tokens();
		$current_token_id  = ! empty( $current_token_ids ) ? current( $current_token_ids ) : '';

		// No change.
		if ( (string) $current_token_id === (string) $token_id ) {
			return;
		}

		// Attach the new token to the subscription.
		$data_store = WC_Data_Store::load( 'order' );
		$data_store->update_payment_token_ids( $subscription, array() );
		$subscription->add_payment_token( $token );

		// Also update any failed renewal orders that still hold the old
		// token. A renewal order copies the subscription's token at creation
		// time, so a failed renewal created before this switch keeps the old
		// (possibly wrong-gateway) token in its own meta and would fail again
		// on retry. Re-point them at the new token so a manual retry charges
		// the correct card.
		$this->update_failed_renewal_orders( $subscription, $token );

		// Record consent for audit trail.
		$admin_user = wp_get_current_user();
		$subscription->add_order_note(
			sprintf(
				/* translators: %1$s: admin display name, %2$s: token display name */
				__( 'Saved card changed to %2$s by admin %1$s. Customer consent confirmed.', 'chip-for-woocommerce' ),
				$admin_user->display_name,
				$token->get_display_name()
			)
		);
	}

	/**
	 * Re-point failed renewal orders at the new token.
	 *
	 * @param WC_Subscription $subscription Subscription object.
	 * @param object          $token        New payment token.
	 * @return void
	 */
	private function update_failed_renewal_orders( $subscription, $token ) {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return;
		}

		$renewal_orders = $subscription->get_related_orders( 'all', 'renewal' );

		if ( empty( $renewal_orders ) ) {
			return;
		}

		$data_store = WC_Data_Store::load( 'order' );

		foreach ( $renewal_orders as $renewal_order ) {
			if ( ! $renewal_order->has_status( 'failed' ) ) {
				continue;
			}

			$data_store->update_payment_token_ids( $renewal_order, array() );
			$renewal_order->add_payment_token( $token );
			$renewal_order->save();
		}
	}
}

Chip_Woocommerce_Admin_Token::get_instance();
