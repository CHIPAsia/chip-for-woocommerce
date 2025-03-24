<?php

if (! defined('ABSPATH')) {
    exit;
}

class CHIP_Site_Health
{

    public function __construct()
    {
        add_filter('site_status_tests', [$this, 'CHIP_plugin_register_site_health_tests']);
    }

    public function CHIP_plugin_register_site_health_tests($tests)
    {
        $tests['direct']['CHIP_plugin_api_status'] = [
            'test'  => [$this, 'CHIP_plugin_check_api_health'],
            'label' => 'CHIP Plugin Connection Status',
        ];
        return $tests;
    }

    public function CHIP_plugin_check_api_health()
    {
        $purchase_API = WC_CHIP_ROOT_URL . '/purchases/';
        $response     = wp_remote_get($purchase_API);

        $status_code = wp_remote_retrieve_response_code($response);

        switch ($status_code) {
            case 401:
                return $this->response_success();
            default:
                return $this->response_fail();
        }
    }

    public function response_success()
    {
        return [
            'label'       => 'CHIP API is working',
            'status'      => 'good',
            'badge'       => [
                'label' => 'CHIP Plugin',
                'color' => 'green',
            ],
            'description' => sprintf(
                '<p>%s</p>',
                'CHIP API is responding correctly.'
            ),
            'actions'     => [],
            'test'        => 'CHIP_plugin_api_status',
        ];
    }

    public function response_fail()
    {
        return [
            'label'       => 'API Issue Detected',
            'status'      => 'critical',
            'badge'       => [
                'label' => 'CHIP Plugin',
                'color' => 'red',
            ],
            'description' => sprintf(
                '<p>%s</p>',
                'Unable to connect to CHIP API.'
            ),
            'actions'     => [],
            'test'        => 'CHIP_plugin_api_status',
        ];
    }
}

add_action('plugins_loaded', function () {
    new CHIP_Site_Health();
});
