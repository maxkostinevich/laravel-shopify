<?php

namespace OhMyBrew\ShopifyApp;

use Illuminate\Foundation\Application;
use OhMyBrew\ShopifyApp\Models\Shop;

class ShopifyApp
{
    /**
     * Laravel application.
     *
     * @var \Illuminate\Foundation\Application
     */
    public $app;

    /**
     * The current shop.
     *
     * @var \OhMyBrew\ShopifyApp\Models\Shop
     */
    public $shop;

    /**
     * Create a new confide instance.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Gets/sets the current shop.
     *
     * @return \OhMyBrew\Models\Shop
     */
    public function shop()
    {
        $shopifyDomain = session('shopify_domain');
        if (!$this->shop && $shopifyDomain) {
            // Grab shop from database here
            $shopModel = config('shopify-app.shop_model');
            $shop = $shopModel::withTrashed()->firstOrCreate(['shopify_domain' => $shopifyDomain]);

            // Update shop instance
            $this->shop = $shop;
        }

        return $this->shop;
    }

    /**
     * Gets an API instance.
     *
     * @return object
     */
    public function api()
    {
        $apiClass = config('shopify-app.api_class');
        $api = new $apiClass();
        $api->setApiKey(config('shopify-app.api_key'));
        $api->setApiSecret(config('shopify-app.api_secret'));

        if (config('shopify-app.api_rate_limiting_enabled') === true) {
            $api->enableRateLimiting(
                config('shopify-app.api_rate_limit_cycle'),
                config('shopify-app.api_rate_limit_cycle_buffer')
            );
        }

        return $api;
    }

    /**
     * Ensures shop domain meets the specs.
     *
     * @param string $domain The shopify domain
     *
     * @return string
     */
    public function sanitizeShopDomain($domain)
    {
        if (empty($domain)) {
            return;
        }

        $configEndDomain = config('shopify-app.myshopify_domain');
        $domain = preg_replace('/https?:\/\//i', '', trim($domain));

        if (strpos($domain, $configEndDomain) === false && strpos($domain, '.') === false) {
            // No myshopify.com ($configEndDomain) in shop's name
            $domain .= ".{$configEndDomain}";
        }

        // Return the host after cleaned up
        return parse_url("http://{$domain}", PHP_URL_HOST);
    }

    /**
     * HMAC creation helper.
     *
     * @param array $opts
     *
     * @return string
     */
    public function createHmac(array $opts)
    {
        // Setup defaults
        $data = $opts['data'];
        $raw = $opts['raw'] ?? false;
        $buildQuery = $opts['buildQuery'] ?? false;
        $encode = $opts['encode'] ?? false;
        $secret = $opts['secret'] ?? config('shopify-app.api_secret');

        if ($buildQuery) {
            //Query params must be sorted and compiled
            ksort($data);
            $queryCompiled = [];
            foreach ($data as $key => $value) {
                $queryCompiled[] = "{$key}=".(is_array($value) ? implode($value, ',') : $value);
            }
            $data = implode($queryCompiled, '');
        }

        // Create the hmac all based on the secret
        $hmac = hash_hmac('sha256', $data, $secret, $raw);

        // Return based on options
        return $encode ? base64_encode($hmac) : $hmac;
    }
}
