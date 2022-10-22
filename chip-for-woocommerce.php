<?php

/**
 * Plugin Name: CHIP for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/chip-for-woocommerce/
 * Description: Cash, Card and Coin Handling Integrated Platform
 * Version: 1.1.3
 * Author: Chip In Sdn Bhd
 * Author URI: http://www.chip-in.asia

 * WC requires at least: 3.3.4
 * WC tested up to: 7.0.0
 *
 * Copyright: © 2022 CHIP
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// based on
// http://docs.woothemes.com/document/woocommerce-payment-gateway-plugin-base/
// docs http://docs.woothemes.com/document/payment-gateway-api/

define('WC_CHIP_MODULE_VERSION', 'v1.1.3');

require_once dirname(__FILE__) . '/api.php';

add_action('plugins_loaded', 'wc_chip_payment_gateway_init');
function wc_chip_payment_gateway_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class ChipWCLogger
    {
        public function __construct()
        {
            $this->logger = new WC_Logger();
        }

        public function log($message)
        {
            $this->logger->add('chip', $message);
        }
    }

    class WC_Chip_Gateway extends WC_Payment_Gateway
    {
        public $id = "chip";
        public $title = "";
        public $method_title = "CHIP E-commerce Gateway";
        public $description = " ";
        public $method_description = "";
        public $debug = true;
        public $supports = array( 'products', 'refunds' );

        private $cached_api;

        public function __construct()
        {
            // TODO: Set icon. Probably can be an external URL.
            $this->init_form_fields();
            $this->init_settings();
            $this->hid = $this->get_option( 'hid' );
            $this->label = $this->get_option( 'label' );
            $this->method_desc = $this->get_option( 'method_desc' );
            $this->title = $this->label;
            $this->method_description = $this->method_desc;
            $this->icon = null;

            if ($this->title === '') {
                $ptitle = "Select Payment Method";
                $this->title = $ptitle;
            };

            if ($this->method_description === '') {
                $pmeth = "Choose payment method on next page";
                $this->method_description = $pmeth;
            };

            add_action(
                'woocommerce_update_options_payment_gateways_' . $this->id,
                array($this, 'process_admin_options')
            );
            str_replace(
                'https:',
                'http:',
                add_query_arg('wc-api', 'WC_Chip_Gateway', home_url('/'))
            );
            add_action(
                'woocommerce_api_wc_gateway_' . $this->id,
                array($this, 'handle_callback')
            );


        }

        private function chip_api()
        {
            if (!$this->cached_api) {
                $this->cached_api = new WC_Chip_API(
                    $this->settings['private-key'],
                    $this->settings['brand-id'],
                    new ChipWCLogger(),
                    $this->debug
                );
            }
            return $this->cached_api;
        }

        private function log_order_info($msg, $o)
        {
            $this->chip_api()
                ->log_info($msg . ': ' . $o->get_order_number());
        }

        function handle_callback()
        {
            // Docs http://docs.woothemes.com/document/payment-gateway-api/
            // http://127.0.0.1/wordpress/?wc-api=wc_gateway_chip&id=&action={paid,sent}
            // The new URL scheme
            // (http://127.0.0.1/wordpress/wc-api/wc_gateway_chip) is broken
            // for some reason.
            // Old one still works.

            $GLOBALS['wpdb']->get_results(
                "SELECT GET_LOCK('chip_payment', 15);"
            );

            $get_input = print_r($_GET, true);
            $this->chip_api()->log_info('received callback: ' . esc_html($get_input));

            $order_id = intval($_GET["id"]);
            $order = new WC_Order($order_id);

            $this->log_order_info('received success callback', $order);
            $payment_id = WC()->session->get(
                'chip_payment_id_' . $order_id
            );
            if (!$payment_id) {
                $input = json_decode(file_get_contents('php://input'), true);
                $payment_id = array_key_exists('id', $input) ? sanitize_key($input['id']) : '';
            }

            if ($this->chip_api()->was_payment_successful($payment_id)) {
                if (!$order->is_paid()) {
                    $order->payment_complete($payment_id);
                    $order->add_order_note(
                        sprintf( __( 'Payment Successful. Transaction ID: %s', 'woocommerce' ), $payment_id )
                    );
                }
                WC()->cart->empty_cart();
                $this->log_order_info('payment processed', $order);
            } else {
                if (!$order->is_paid()) {
                    $order->update_status(
                        'wc-failed',
                        __('ERROR: Payment was received, but order verification failed.')
                    );
                    $this->log_order_info('payment not successful', $order);
                }
            }

            $GLOBALS['wpdb']->get_results(
                "SELECT RELEASE_LOCK('chip_payment');"
            );

            header("Location: " . $this->get_return_url($order));
        }

        public function init_form_fields()
        {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable API', 'chip-for-woocommerce'),
                    'label' => __('Enable API', 'chip-for-woocommerce'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ),
                'hid' => array(
                    'title' => __('Enable payment method selection', 'chip-for-woocommerce'),
                    'label' => __('Enable payment method selection', 'chip-for-woocommerce'),
                    'type' => 'checkbox',
                    'description' => 'If set, buyers will be able to choose the desired payment method directly in WooCommerce',
                    'default' => 'yes',

                ),
                'method_desc' => array(
                    'title' => __('Change payment method description', 'chip-for-woocommerce'),
                    'label' => __('', 'chip-for-woocommerce'),
                    'type' => 'text',
                    'description' => 'If not set, "Choose payment method on next page" will be used',
                    'default' => 'Choose payment method on next page',

                ),
                'label' => array(
                    'title' => __('Change payment method title', 'chip-for-woocommerce'),
                    'type' => 'text',
                    'description' => 'If not set, "Select payment method" will be used. Ignored if payment method selection is enabled',
                    'default' => 'Select Payment Method',

                ),
                'brand-id' => array(
                    'title' => __('Brand ID', 'chip-for-woocommerce'),
                    'type' => 'text',
                    'description' => __(
                        'Please enter your brand ID',
                        'chip-for-woocommerce'
                    ),
                    'default' => '',
                ),
                'private-key' => array(
                    'title' => __('Secret key', 'chip-for-woocommerce'),
                    'type' => 'text',
                    'description' => __(
                        'Please enter your secret key',
                        'chip-for-woocommerce'
                    ),
                    'default' => '',
                ),
                'debug' => array(
                    'title' => __('Debug Log', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable logging', 'woocommerce'),
                    'default' => 'no',
                    'description' =>
                    sprintf(
                        __(
                            'Log events to <code>%s</code>',
                            'woocommerce'
                        ),
                        wc_get_log_file_path('chip')
                    ),
                ),
            );
        }

        public function payment_fields() {
           if ($this->hid === 'no') {
               echo wp_kses_post($this->method_description);
           }
           else {
                $payment_methods = $this->chip_api()->payment_methods(
                    get_woocommerce_currency(),
                    $this->get_language()
                );

                if (is_null($payment_methods)) {
                    echo('System error!');
                    return;
                }

                if (!array_key_exists("by_country", $payment_methods)) {
                    echo 'Plugin configuration error!';
                } else {
                    $data = $payment_methods["by_country"];
                    $methods = [];
                    foreach ($data as $country => $pms) {
                        foreach ($pms as $pm) {
                            if (!array_key_exists($pm, $methods)) {
                                $methods[$pm] = [
                                    "payment_method" => sanitize_key($pm),
                                    "countries" => [],
                                ];
                            }
                            if (!in_array($country, $methods[$pm]["countries"])) {
                                $methods[$pm]["countries"][] = $country;
                            }
                        }
                    }

                    echo "<span style=\"display: flex; flex-flow: row wrap;\" >";
                    $checked = false;
                    if (count($methods) != 1) {
                        $checked = true;
                    }
                    foreach ($methods as $key => $data) {
                        $pm = $data['payment_method'];
                        echo "<label style=\"padding: 1em; width: 250px; \">
                                <input type=radio
                                    class=chip-payment-method
                                    name=chip-payment-method
                                    value=\"" . esc_attr($pm) . "\"";

                        if (!$checked) {
                            echo "checked=\"checked\" ";
                            $checked = true;
                        }

                        echo ">";

                        $pm_name = esc_html($payment_methods['names'][$data["payment_method"]]);

                        echo wp_kses_post("<div style=\"font-size: 14px;\">{$pm_name}</div>");

                        $logo = $payment_methods['logos'][$data["payment_method"]];
                        if (!is_array($logo)) {
                            $logo_array = explode('/', $logo);
                            $pmlogo = htmlspecialchars(end($logo_array));
                            $pmlogo_url = plugins_url("assets/$pmlogo", __FILE__);
                            echo wp_kses_post("<div><img src='".$pmlogo_url."' height='30' style='max-width: 160px; max-height: 30px;'></div>");
                        } else {
                            $c = count($logo);
                            if ($c > 4) {
                                $c = 4;
                            }
                            $c = $c * 50;
                            echo wp_kses_post("<span style=\"display: block; padding-bottom: 3px; min-width: ".esc_attr($c)."px; max-width: ".$c."px;\">");
                            foreach ($logo as $i) {
                                $logo_array = explode('/', $i);
                                $pmlogo = htmlspecialchars(end($logo_array));
                                $pmlogo_url = plugins_url("assets/$pmlogo", __FILE__);
                                echo wp_kses_post("<img src='".$pmlogo_url."' width='40' height='35' style='margin: 0 10px 10px 0; float: left;'>");
                            }
                            echo wp_kses_post("<div style='clear: both;'></div></span>");
                        }

                        echo "</label>";
                    }
                    echo '</span>';
                }
            }
        }

        public function get_language()
        {
            if (defined('ICL_LANGUAGE_CODE')) {
                $ln = ICL_LANGUAGE_CODE;
            } else {
                $ln = get_locale();
            }
            switch ($ln) {
            case 'et_EE':
                $ln = 'et';
                break;
            case 'ru_RU':
                $ln = 'ru';
                break;
            case 'lt_LT':
                $ln = 'lt';
                break;
            case 'lv_LV':
                $ln = 'lv';
                break;
            case 'et':
            case 'lt':
            case 'lv':
            case 'ru':
               break;
            default:
               $ln = 'en';
            }

            return $ln;
        }

        public function process_payment($o_id)
        {
            $o = new WC_Order($o_id);
            $total = round($o->calculate_totals() * 100);
            $notes = $this->get_notes();
//             if ($o->get_total_discount() > 0) {
//                 $total -= round($o->get_total_discount() * 100);
//             }

            $chip = $this->chip_api();
            $u = home_url() . '/?wc-api=wc_gateway_chip&id=' . $o_id;
            $params = [
                'success_callback' => $u . "&action=paid",
                'success_redirect' => $u . "&action=paid",
                'failure_redirect' => $u . "&action=cancel",
                'cancel_redirect' => $u . "&action=cancel",
                'creator_agent' => 'Chip Woocommerce module: '
                    . WC_CHIP_MODULE_VERSION,
                'reference' => (string)$o->get_order_number(),
                'platform' => 'woocommerce',
                'due' => apply_filters( 'wc_chip_due_timestamp', $this->get_due_timestamp() ),
                'purchase' => [
                    "currency" => $o->get_currency(),
                    "language" => $this->get_language(),
                    "notes" => $notes,
                    "due_strict" => apply_filters( 'wc_chip_purchase_due_strict', true ),
                    "products" => [
                        [
                            'name' => 'Order #' . $o_id . ' '. home_url(),
                            'price' => $total,
                            'quantity' => 1,
                        ],
                    ],
                ],
                'brand_id' => $this->settings['brand-id'],
                'client' => [
                    'email' => $o->get_billing_email(),
                    'phone' => $o->get_billing_phone(),
                    'full_name' => $o->get_billing_first_name() . ' '
                        . $o->get_billing_last_name(),
                    'street_address' => $o->get_billing_address_1() . ' '
                        . $o->get_billing_address_2(),
                    'country' => $o->get_billing_country(),
                    'city' => $o->get_billing_city(),
                    'zip_code' => $o->get_shipping_postcode(),
                    'shipping_street_address' => $o->get_shipping_address_1()
                        . ' ' . $o->get_shipping_address_2(),
                    'shipping_country' => $o->get_shipping_country(),
                    'shipping_city' => $o->get_shipping_city(),
                    'shipping_zip_code' => $o->get_shipping_postcode(),
                ],
            ];

            $payment = $chip->create_payment($params);

            if (!array_key_exists('id', $payment)) {
                return array(
                    'result' => 'failure',
                );
            }

            WC()->session->set(
              'chip_payment_id_' . $o_id,
              $payment['id']
            );

            $this->log_order_info('got checkout url, redirecting', $o);
            $u = $payment['checkout_url'];
            if (array_key_exists("chip-payment-method", $_REQUEST)) {
                $payment_method = htmlspecialchars($_REQUEST["chip-payment-method"]);

                if (in_array($payment_method, ['billplz', 'fpx', 'fpxb2b1', 'card'])){
                    $u .= "?preferred=" . $payment_method;
                }
            }
            return array(
                'result' => 'success',
                'redirect' => esc_url($u),
            );
        }

        public function get_notes() {
            $cart = WC()->cart->get_cart();
            $nameString = '';
            foreach ($cart as $key => $cart_item) {
                $cart_product = $cart_item['data'];
                $name = method_exists( $cart_product, 'get_name' ) === true ? $cart_product->get_name() : $cart_product->name;
                if (array_keys($cart)[0] == $key) {
                    $nameString = $name;
                } else {
                    $nameString = $nameString . ';' . $name;
                }
            }
            return $nameString;
        }

        public function get_due_timestamp(){
            return time() + (absint( get_option( 'woocommerce_hold_stock_minutes', '60' ) ) * 60);
        }

        public function can_refund_order( $order ) {
            $has_api_creds = $this->get_option( 'enabled' ) && $this->get_option( 'private-key' ) && $this->get_option( 'brand-id' );

            return $order && $order->get_transaction_id() && $has_api_creds;
        }

        public function process_refund( $order_id, $amount = null, $reason = '' ) {
            $order = wc_get_order( $order_id );

            if ( ! $this->can_refund_order( $order ) ) {
                $this->log_order_info( 'Cannot refund order', $order );
                return new WP_Error( 'error', __( 'Refund failed.', 'woocommerce' ) );
            }

            $chip = $this->chip_api();
            $params = [
                'amount' => round($amount * 100),
            ];

            $result = $chip->refund_payment($order->get_transaction_id(), $params);

            if ( is_wp_error( $result ) || isset($result['__all__']) ) {
                $this->chip_api()
                    ->log_error($result['__all__'] . ': ' . $order->get_order_number());

                return new WP_Error( 'error', var_export($result['__all__'], true) );
            }

            $this->log_order_info( 'Refund Result: ' . wc_print_r( $result, true ), $order );

            switch ( strtolower( $result['status'] ) ) {
                case 'success':
                    $refund_amount = round($result['payment']['amount'] / 100, 2) . $result['payment']['currency'];

                    $order->add_order_note(
                    /* translators: 1: Refund amount, 2: Refund ID */
                        sprintf( __( 'Refunded %1$s - Refund ID: %2$s', 'woocommerce' ), $refund_amount, $result['id'] )
                    );
                    return true;
            }

            return true;
        }
    }

    // Add the Gateway to WooCommerce
    function wc_chip_add_gateway($methods)
    {
        $methods[] = 'WC_Chip_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'wc_chip_add_gateway');

    function wc_chip_setting_link($links)
    {
        $new_links = array(
            'settings' => sprintf(
              '<a href="%1$s">%2$s</a>', admin_url('admin.php?page=wc-settings&tab=checkout&section=chip'), esc_html__('Settings', 'chip-for-woocommerce')
            )
        );
        return array_merge($new_links, $links);
    }

    add_filter(
        'plugin_action_links_' . plugin_basename(__FILE__),
        'wc_chip_setting_link'
    );
}
