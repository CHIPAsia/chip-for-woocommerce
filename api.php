<?php

/*
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// This is CHIP API URL Endpoint as per documented in: https://developer.chip-in.asia/api
define("WC_CHIP_ROOT_URL", "https://gate.chip-in.asia");

class WC_Chip_API
{
    public function __construct($secret_key, $brand_id, $logger, $debug)
    {
        $this->secret_key = $secret_key;
        $this->brand_id = $brand_id;
        $this->logger = $logger;
        $this->debug = $debug;
    }

    public function create_payment($params)
    {
        $this->log_info("loading payment form");

        // time() is to force fresh instead cache
        return $this->call('POST', '/purchases/?time=' . time(), $params);
    }

    public function payment_methods($currency, $language)
    {
        $this->log_info("fetching payment methods");
        return $this->call(
            'GET',
            "/payment_methods/?brand_id={$this->brand_id}&currency={$currency}&language={$language}"
        );
    }

    public function get_payment($payment_id)
    {
        $this->log_info(sprintf("validating payment: %s", $payment_id));

        // time() is to force fresh instead cache
        $result = $this->call('GET', "/purchases/{$payment_id}/?time" . time());
        $this->log_info(sprintf(
            "success check result: %s",
            var_export($result, true)
        ));
        return $result;
    }

    public function was_payment_successful($payment_id)
    {
        $this->log_info(sprintf("validating payment: %s", $payment_id));

        // time() is to force fresh instead cache
        $result = $this->call('GET', "/purchases/{$payment_id}/?time" . time());
        $this->log_info(sprintf(
            "success check result: %s",
            var_export($result, true)
        ));
        return $result && $result['status'] == 'paid';
    }

    public function refund_payment($payment_id, $params)
    {
        $this->log_info(sprintf("refunding payment: %s", $payment_id));

        $result = $this->call('POST', "/purchases/{$payment_id}/refund/", $params);

        $this->log_info(sprintf(
            "payment refund result: %s",
            var_export($result, true)
        ));

        return $result;
    }

    public function public_key()
    {
        $this->log_info("getting public key");

        $result = $this->call('GET', "/public_key/");

        $this->log_info(sprintf(
            "public key: %s",
            var_export($result, true)
        ));

        return $result;
    }

    private function call($method, $route, $params = [])
    {
        $secret_key = $this->secret_key;
        if (!empty($params)) {
            $params = json_encode($params);
        }

        $response = $this->request(
            $method,
            sprintf("%s/api/v1%s", WC_CHIP_ROOT_URL, $route),
            $params,
            [
                'Content-type' => 'application/json',
                'Authorization' => 'Bearer ' . $secret_key,
            ]
        );
        $this->log_info(sprintf('received response: %s', $response));
        $result = json_decode($response, true);
        if (!$result) {
            $this->log_error('JSON parsing error/NULL API response');
            return null;
        }

        if (!empty($result['errors'])) {
            $this->log_error('API error', $result['errors']);
            return null;
        }

        return $result;
    }

    private function request($method, $url, $params = [], $headers = [])
    {
        $this->log_info(sprintf(
            "%s `%s`\n%s\n%s",
            $method,
            $url,
            var_export($params, true),
            var_export($headers, true)
        ));

        $wp_request = wp_remote_request( $url, array(
            'method' => $method,
            'sslverify' => apply_filters( 'wc_chip_sslverify', true),
            'headers' => $headers,
            'body' => $params,
        ));

        $response = wp_remote_retrieve_body($wp_request);

        switch ($code = wp_remote_retrieve_response_code($wp_request)) {
            case 200:
            case 201:
                break;
            default:
                $this->log_error(
                    sprintf("%s %s: %d", $method, $url, $code),
                    $response
                );
        }
        if (is_wp_error($response) ) {
            $this->log_error('wp_remote_request', $response->get_error_message());
        }

        return $response;
    }

    public function log_info($text, $error_data = null)
    {
        if ($this->debug) {
            $this->logger->log("INFO: " . $text . ";");
        }
    }

    public function log_error($error_text, $error_data = null)
    {
        $error_text = "ERROR: " . $error_text . ";";
        if ($error_data) {
            $error_text .= " ERROR DATA: " . var_export($error_data, true) . ";";
        }
        $this->logger->log($error_text);
    }
}
