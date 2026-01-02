<?php
/**
 * CHIP for WooCommerce Payment Details
 *
 * Displays payment details (Card/FPX) in order admin page.
 *
 * @package CHIP for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CHIP Payment Details class.
 */
class Chip_Woocommerce_Payment_Details {

	/**
	 * Singleton instance.
	 *
	 * @var Chip_Woocommerce_Payment_Details
	 */
	private static $instance;

	/**
	 * Get singleton instance.
	 *
	 * @return Chip_Woocommerce_Payment_Details
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
		add_action( 'add_meta_boxes', array( $this, 'add_payment_details_metabox' ) );
	}

	/**
	 * Add payment details metabox.
	 *
	 * @return void
	 */
	public function add_payment_details_metabox() {
		$screen = $this->get_order_screen();

		if ( ! $screen ) {
			return;
		}

		// Get the order.
		$order = $this->get_current_order();

		if ( ! $order ) {
			return;
		}

		// Get purchase data from order meta.
		$purchase = $this->get_purchase_data( $order );

		if ( ! $purchase ) {
			return;
		}

		add_meta_box(
			'chip-payment-details',
			__( 'CHIP Payment Details', 'chip-for-woocommerce' ),
			array( $this, 'render_payment_details_metabox' ),
			$screen,
			'side',
			'high'
		);
	}

	/**
	 * Get order screen for metabox.
	 *
	 * @return string|null
	 */
	private function get_order_screen() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return null;
		}

		// HPOS screen.
		if ( 'woocommerce_page_wc-orders' === $screen->id ) {
			return 'woocommerce_page_wc-orders';
		}

		// Legacy screen.
		if ( 'shop_order' === $screen->id ) {
			return 'shop_order';
		}

		return null;
	}

	/**
	 * Get current order being viewed/edited.
	 *
	 * @return WC_Order|null
	 */
	private function get_current_order() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading order ID for display only.
		$order_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		// Fallback for legacy orders.
		if ( ! $order_id ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading post ID for display only.
			$order_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		}

		if ( ! $order_id ) {
			global $post;
			if ( $post && 'shop_order' === $post->post_type ) {
				$order_id = $post->ID;
			}
		}

		if ( ! $order_id ) {
			return null;
		}

		return wc_get_order( $order_id );
	}

	/**
	 * Get purchase data from order meta.
	 *
	 * @param WC_Order $order Order object.
	 * @return array|null Purchase data or null if not found.
	 */
	private function get_purchase_data( $order ) {
		$payment_method = $order->get_payment_method();

		if ( empty( $payment_method ) ) {
			return null;
		}

		// Try to get the purchase data from order meta.
		$purchase = $order->get_meta( '_' . $payment_method . '_purchase' );

		if ( empty( $purchase ) || ! is_array( $purchase ) ) {
			return null;
		}

		return $purchase;
	}

	/**
	 * Render payment details metabox.
	 *
	 * @param WP_Post|WC_Order $post_or_order Post object or order object.
	 * @return void
	 */
	public function render_payment_details_metabox( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );

		if ( ! $order ) {
			return;
		}

		$purchase = $this->get_purchase_data( $order );

		if ( ! $purchase ) {
			return;
		}

		$payment_method = isset( $purchase['transaction_data']['payment_method'] ) ? $purchase['transaction_data']['payment_method'] : '';

		echo '<div class="chip-payment-details">';

		// Check if it's an FPX payment.
		if ( in_array( $payment_method, array( 'fpx', 'fpx_b2b1' ), true ) ) {
			$this->render_fpx_details( $purchase );
		} elseif ( in_array( $payment_method, array( 'visa', 'mastercard', 'maestro' ), true ) ) {
			$this->render_card_details( $purchase );
		}

		echo '</div>';

		$this->render_styles();
	}

	/**
	 * Extract FPX data from purchase payload.
	 *
	 * @param array $purchase Purchase data.
	 * @return array|null FPX data or null if not found.
	 */
	private function extract_fpx_data( $purchase ) {
		if ( empty( $purchase['transaction_data']['attempts'] ) ) {
			return null;
		}

		$attempt = $purchase['transaction_data']['attempts'][0];
		if ( empty( $attempt['extra'] ) ) {
			return null;
		}

		$extra = $attempt['extra'];

		// Keys to skip when looking for FPX data.
		$blacklist = array( 'user_web_browser_data' );

		// Iterate through all keys to find FPX data.
		foreach ( $extra as $key => $value ) {
			if ( in_array( $key, $blacklist, true ) ) {
				continue;
			}

			if ( is_array( $value ) && isset( $value['fpx_debitAuthCode'] ) ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Get FPX field value.
	 *
	 * FPX fields can be arrays (from webhook) or strings (from redirect).
	 *
	 * @param array  $fpx_data FPX data array.
	 * @param string $field    Field name.
	 * @return string|null Field value or null.
	 */
	private function get_fpx_field( $fpx_data, $field ) {
		if ( ! isset( $fpx_data[ $field ] ) ) {
			return null;
		}

		$value = $fpx_data[ $field ];

		return is_array( $value ) ? $value[0] : $value;
	}

	/**
	 * Render FPX details.
	 *
	 * @param array $purchase Purchase data.
	 * @return void
	 */
	private function render_fpx_details( $purchase ) {
		$fpx_data = $this->extract_fpx_data( $purchase );

		if ( ! $fpx_data ) {
			return;
		}

		$buyer_name      = $this->get_fpx_field( $fpx_data, 'fpx_buyerName' );
		$buyer_bank      = $this->get_fpx_field( $fpx_data, 'fpx_buyerBankBranch' );
		$debit_auth_code = $this->get_fpx_field( $fpx_data, 'fpx_debitAuthCode' );
		$fpx_txn_id      = $this->get_fpx_field( $fpx_data, 'fpx_fpxTxnId' );

		echo '<table class="chip-details-table">';
		echo '<tbody>';

		if ( $buyer_name ) {
			$this->render_detail_row( __( 'Account Holder', 'chip-for-woocommerce' ), esc_html( $buyer_name ) );
		}

		if ( $buyer_bank ) {
			$this->render_detail_row( __( 'Bank', 'chip-for-woocommerce' ), esc_html( $buyer_bank ) );
		}

		if ( $debit_auth_code ) {
			$this->render_detail_row( __( 'Debit Auth Code', 'chip-for-woocommerce' ), '<code>' . esc_html( $debit_auth_code ) . '</code>' );
		}

		if ( $fpx_txn_id ) {
			$this->render_detail_row( __( 'FPX Txn ID', 'chip-for-woocommerce' ), '<code>' . esc_html( $fpx_txn_id ) . '</code>' );
		}

		echo '</tbody>';
		echo '</table>';
	}

	/**
	 * Render card details.
	 *
	 * @param array $purchase Purchase data.
	 * @return void
	 */
	private function render_card_details( $purchase ) {
		if ( empty( $purchase['transaction_data']['attempts'] ) ) {
			return;
		}

		$attempt = $purchase['transaction_data']['attempts'][0];
		if ( empty( $attempt['extra'] ) ) {
			return;
		}

		$extra = $attempt['extra'];

		// Convert 2-digit year to 4-digit year.
		$expiry_year = isset( $extra['expiry_year'] ) ? $extra['expiry_year'] : '';
		if ( $expiry_year && strlen( (string) $expiry_year ) <= 2 ) {
			$expiry_year = '20' . str_pad( $expiry_year, 2, '0', STR_PAD_LEFT );
		}

		echo '<table class="chip-details-table">';
		echo '<tbody>';

		if ( ! empty( $extra['cardholder_name'] ) ) {
			$this->render_detail_row( __( 'Cardholder', 'chip-for-woocommerce' ), esc_html( $extra['cardholder_name'] ) );
		}

		if ( ! empty( $extra['masked_pan'] ) ) {
			$this->render_detail_row( __( 'Card Number', 'chip-for-woocommerce' ), esc_html( $extra['masked_pan'] ) );
		}

		if ( ! empty( $extra['card_brand'] ) ) {
			$card_brand_display = $this->get_card_brand_display( $extra['card_brand'] );
			$this->render_detail_row( __( 'Card Brand', 'chip-for-woocommerce' ), $card_brand_display );
		}

		if ( ! empty( $extra['expiry_month'] ) && $expiry_year ) {
			$expiry = sprintf( '%s/%s', str_pad( $extra['expiry_month'], 2, '0', STR_PAD_LEFT ), $expiry_year );
			$this->render_detail_row( __( 'Expiry', 'chip-for-woocommerce' ), esc_html( $expiry ) );
		}

		if ( ! empty( $extra['card_issuer'] ) ) {
			$this->render_detail_row( __( 'Issuer', 'chip-for-woocommerce' ), esc_html( ucwords( $extra['card_issuer'] ) ) );
		}

		if ( ! empty( $extra['card_issuer_country'] ) ) {
			$this->render_detail_row( __( 'Country', 'chip-for-woocommerce' ), esc_html( $extra['card_issuer_country'] ) );
		}

		if ( ! empty( $extra['card_category'] ) ) {
			$this->render_detail_row( __( 'Category', 'chip-for-woocommerce' ), esc_html( $extra['card_category'] ) );
		}

		if ( ! empty( $extra['card_type'] ) ) {
			$this->render_detail_row( __( 'Type', 'chip-for-woocommerce' ), esc_html( ucwords( $extra['card_type'] ) ) );
		}

		if ( ! empty( $extra['authorization_approval_code'] ) ) {
			$this->render_detail_row( __( 'Auth Code', 'chip-for-woocommerce' ), '<code>' . esc_html( $extra['authorization_approval_code'] ) . '</code>' );
		}

		echo '</tbody>';
		echo '</table>';
	}

	/**
	 * Get card brand display with logo for supported brands.
	 *
	 * @param string $card_brand Card brand name.
	 * @return string HTML for card brand display.
	 */
	private function get_card_brand_display( $card_brand ) {
		$card_brand_lower = strtolower( $card_brand );

		$supported_logos = array( 'visa', 'mastercard' );

		if ( in_array( $card_brand_lower, $supported_logos, true ) ) {
			$logo_url = plugins_url( 'assets/' . $card_brand_lower . '.svg', CHIP_WOOCOMMERCE_FILE );
			$logo_img = '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( ucwords( $card_brand ) ) . '" width="40" height="24" />';
			return '<span class="chip-card-brand-logo">' . $logo_img . ' ' . esc_html( ucwords( $card_brand ) ) . '</span>';
		}

		return esc_html( ucwords( $card_brand ) );
	}

	/**
	 * Render a detail row.
	 *
	 * @param string $label Row label.
	 * @param string $value Row value (pre-escaped).
	 * @return void
	 */
	private function render_detail_row( $label, $value ) {
		echo '<tr>';
		echo '<th>' . esc_html( $label ) . '</th>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Value is pre-escaped by caller.
		echo '<td>' . $value . '</td>';
		echo '</tr>';
	}

	/**
	 * Render inline styles.
	 *
	 * @return void
	 */
	private function render_styles() {
		static $styles_rendered = false;

		if ( $styles_rendered ) {
			return;
		}

		$styles_rendered = true;

		?>
		<style>
			.chip-payment-details .chip-details-table {
				width: 100%;
				border-collapse: collapse;
			}
			.chip-payment-details .chip-details-table th,
			.chip-payment-details .chip-details-table td {
				padding: 6px 0;
				vertical-align: top;
				border-bottom: 1px solid #f0f0f0;
			}
			.chip-payment-details .chip-details-table th {
				text-align: left;
				font-weight: 600;
				color: #646970;
				width: 40%;
			}
			.chip-payment-details .chip-details-table td {
				text-align: right;
				color: #1d2327;
			}
			.chip-payment-details .chip-details-table tr:last-child th,
			.chip-payment-details .chip-details-table tr:last-child td {
				border-bottom: none;
			}
			.chip-payment-details .chip-details-table code {
				background: #f0f0f1;
				padding: 2px 5px;
				font-size: 11px;
			}
			.chip-payment-details .chip-card-brand-logo {
				display: inline-flex;
				align-items: center;
				gap: 6px;
			}
			.chip-payment-details .chip-card-brand-logo svg {
				vertical-align: middle;
				flex-shrink: 0;
			}
		</style>
		<?php
	}
}

Chip_Woocommerce_Payment_Details::get_instance();
