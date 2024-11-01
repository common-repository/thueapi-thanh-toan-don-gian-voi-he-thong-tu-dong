<?php

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

define('Apibank_VERSION', '0.1.0');

class ThueAPI_React_Blocks implements IntegrationInterface
{
    /**
     * The name of the integration.
     *
     * @return string
     */
    public function get_name()
    {
        return 'apibank';
    }

    /**
     * When called invokes any initialization/setup for the integration.
     */
    public function initialize()
    {
        $this->register_main_integration();
    }

    /**
     * Registers the main JS file required to add filters and Slot/Fills.
     */
    public function register_main_integration()
    {
        $script_path = '/blocks/index.js';

        $script_url = plugins_url($script_path, __FILE__);

        $script_asset_path = __DIR__.'/blocks/index.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : [
                'dependencies' => [],
                'version' => $this->get_file_version($script_path),
            ];

        wp_register_script(
            'apibank-blocks-integration',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );
        wp_set_script_translations(
            'apibank-blocks-integration',
            'apibank',
            __DIR__.'/languages'
        );
    }

    /**
     * Returns an array of script handles to enqueue in the frontend context.
     *
     * @return string[]
     */
    public function get_script_handles()
    {
        return ['apibank-blocks-integration'];
    }

    /**
     * Returns an array of script handles to enqueue in the editor context.
     *
     * @return string[]
     */
    public function get_editor_script_handles()
    {
        return ['apibank-blocks-integration'];
    }

    /**
     * An array of key, value pairs of data made available to the block on the client side.
     *
     * @return array
     */
    public function get_script_data()
    {
        $setting = WC()->payment_gateways->payment_gateways()['thueapi'];

        return [
            'title' => $setting->title,
            'description' => $setting->description,
            'placeOrderButtonLabel' => $setting->settings['placeOrderButtonLabel'],
        ];
    }

    /**
     * Get the file modified time as a cache buster if we're in dev mode.
     *
     * @param  string  $file Local path to the file.
     * @return string The cache buster value to use for the given file.
     */
    protected function get_file_version($file)
    {
        if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG && file_exists($file)) {
            return filemtime($file);
        }

        return Apibank_VERSION;
    }
}
