<?php

class Dodo_Payments_API
{
    private const MAX_PRODUCT_NAME_LENGTH = 100;
    private const MAX_PRODUCT_DESCRIPTION_LENGTH = 999;

    private bool $testmode;
    private string $api_key;
    /**
     * Can be 'digital_products', 'saas', 'e_book', 'edtech'
     * @var string
     */
    private string $global_tax_category;
    /**
     * Whether tax is included in all product prices
     * @var bool
     */
    private bool $global_tax_inclusive;

    /**
     * Initializes the Dodo_Payments_API instance with configuration options.
     *
     * @param array{testmode: bool, api_key: string, global_tax_category: string, global_tax_inclusive: bool} $options Configuration options for API access and behavior.
     */
    public function __construct($options)
    {
        $this->testmode = $options['testmode'];
        $this->api_key = $options['api_key'];
        $this->global_tax_category = $options['global_tax_category'];
        $this->global_tax_inclusive = $options['global_tax_inclusive'];
    }

    /**
     * Creates a one-time price product in the Dodo Payments API using WooCommerce product data.
     *
     * Strips HTML from the product description, truncates it to 999 characters, and sends product details including name, price, currency, and tax settings to the API. Throws an exception if the API request fails.
     *
     * @param WC_Product $product The WooCommerce product to create in the API.
     * @return array{product_id: string} The created product's data from the Dodo Payments API.
     * @throws \Exception If the API request fails or returns an error response.
     */
    public function create_product($product)
    {
        $stripped_description = wp_strip_all_tags($product->get_description());
        $truncated_description = mb_substr($stripped_description, 0, self::MAX_PRODUCT_DESCRIPTION_LENGTH);

        $body = array(
            'name' => mb_substr($product->get_name(), 0, self::MAX_PRODUCT_NAME_LENGTH),
            'description' => $truncated_description,
            'price' => array(
                'type' => 'one_time_price',
                'currency' => get_woocommerce_currency(),
                'price' => (int) ($product->get_price() * 100), // fixme: assuming that the currency is INR or USD
                'discount' => 0, // todo: update defaults
                'purchasing_power_parity' => false, // todo: deal with it when the feature is implemented
                'tax_inclusive' => $this->global_tax_inclusive,
            ),
            'tax_category' => $this->global_tax_category,
        );

        $res = $this->post('/products', $body);

        if (is_wp_error($res)) {
            throw new Exception('Failed to create product: ' . esc_html($res->get_error_message()));
        }

        if (wp_remote_retrieve_response_code($res) !== 200) {
            throw new Exception('Failed to create product: ' . esc_html($res['body']));
        }

        return json_decode($res['body'], true);
    }

    /**
     * Updates an existing product in the Dodo Payments API with new WooCommerce product data.
     *
     * Preserves the product's tax and discount settings from the API while updating its name, description, and price.
     *
     * @param string $dodo_product_id The Dodo Payments product ID to update.
     * @param WC_Product $product The WooCommerce product containing updated data.
     * @throws \Exception If the product is not found or the API update fails.
     */
    public function update_product($dodo_product_id, $product)
    {
        $dodo_product = $this->get_product($dodo_product_id);

        if (!$dodo_product) {
            throw new Exception('Product (' . esc_html($dodo_product_id) . ') not found');
        }

        $stripped_description = wp_strip_all_tags($product->get_description());
        $truncated_description = mb_substr($stripped_description, 0, self::MAX_PRODUCT_DESCRIPTION_LENGTH);

        // ignore global options, respect the tax_category and tax_inclusive set
        // from the dashboard
        $body = array(
            'name' => mb_substr($product->get_name(), 0, self::MAX_PRODUCT_NAME_LENGTH),
            'description' => $truncated_description,
            'price' => array(
                'type' => 'one_time_price',
                'currency' => get_woocommerce_currency(),
                'price' => (int) ($product->get_price() * 100), // fixme: assuming that the currency is INR or USD
                'discount' => $dodo_product['price']['discount'],
                'purchasing_power_parity' => $dodo_product['price']['purchasing_power_parity'],
                'tax_inclusive' => $dodo_product['price']['tax_inclusive'],
            ),
            'tax_category' => $dodo_product['tax_category'],
        );

        $res = $this->patch("/products/{$dodo_product_id}", $body);

        if (is_wp_error($res)) {
            throw new Exception("Failed to update product: " . esc_html($res->get_error_message()));
        }

        if (wp_remote_retrieve_response_code($res) !== 200) {
            throw new Exception("Failed to update product: " . esc_html($res['body']));
        }

        return;
    }

    /**
     * Uploads the WooCommerce product image to the Dodo Payments API and assigns it to the specified product.
     *
     * @param WC_Product $product The WooCommerce product whose image will be synced.
     * @param string $dodo_product_id The Dodo Payments product ID to associate the image with.
     * @throws \Exception If the product has no image, the image file cannot be found or read, or if the upload or assignment fails.
     */
    public function sync_image_for_product($product, $dodo_product_id)
    {
        $image_id = $product->get_image_id();

        if (!$image_id) {
            throw new Exception('Product has no image');
        }

        $image_path = get_attached_file($image_id);
        if (!$image_path) {
            throw new Exception('Could not find image file');
        }

        $image_contents = file_get_contents($image_path);
        if ($image_contents === false) {
            throw new Exception('Could not read image file');
        }

        ['url' => $upload_url, 'image_id' => $image_id] = $this->get_upload_url_and_image_id($dodo_product_id);
        $response = wp_remote_request($upload_url, array(
            'method' => 'PUT',
            'body' => $image_contents,
        ));

        if (is_wp_error($response)) {
            throw new Exception('Failed to upload image: ' . esc_html($response->get_error_message()));
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            throw new Exception('Failed to upload image: ' . esc_html($response['body']));
        }

        $this->set_product_image_id($dodo_product_id, $image_id);

        return;
    }

    /**
     * Creates a checkout session in the Dodo Payments API using WooCommerce order data.
     *
     * Replaces the deprecated `POST /payments` and `POST /subscriptions` endpoints with the
     * unified `POST /checkouts` endpoint that handles both one-time payments and subscriptions
     * via a single product cart. The cart may contain either one-time products (which honor the
     * per-item `amount` override) or a subscription product; the API resolves the flow based on
     * the products' configuration.
     *
     * All `billing_address` fields (except `country`) and `customer.name` are nullable in the
     * Checkout Sessions schema, so WooCommerce values are forwarded verbatim -- empty strings
     * included -- and the hosted Dodo checkout will collect anything that's still missing.
     *
     * @param WC_Order $order The WooCommerce order to use for checkout details.
     * @param array{amount: int|null, product_id: string, quantity: int}[] $synced_products List of products to include in the checkout.
     * @param string|null $dodo_discount_code Optional discount code to apply.
     * @param string $return_url URL to redirect the customer after checkout completion.
     * @param array<string, string> $metadata Metadata to associate with the checkout (used by webhooks to resolve the WC order/subscription).
     * @param array{on_demand?: array{mandate_only: bool}, trial_period_days?: int}|null $subscription_data Optional subscription configuration (e.g. mandate-only authorization).
     * @throws \Exception If the API request fails or returns an error.
     * @return array{session_id: string, checkout_url: string|null} The created checkout session.
     */
    public function create_checkout_session(
        $order,
        $synced_products,
        $dodo_discount_code,
        $return_url,
        $metadata = array(),
        $subscription_data = null
    ) {
        $request = array(
            'billing_address' => array(
                'city' => $order->get_billing_city(),
                'country' => $order->get_billing_country(),
                'state' => $order->get_billing_state(),
                'street' => trim($order->get_billing_address_1() . ' ' . $order->get_billing_address_2()),
                'zipcode' => $order->get_billing_postcode(),
            ),
            'customer' => array(
                'email' => $order->get_billing_email(),
                'name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            ),
            'product_cart' => $synced_products,
            'return_url' => $return_url,
        );

        $tax_id = self::get_order_tax_id($order);
        if ($tax_id !== null) {
            $request['tax_id'] = $tax_id;
        }

        if ($dodo_discount_code) {
            $request['discount_code'] = $dodo_discount_code;
        }

        if (!empty($metadata)) {
            $request['metadata'] = $metadata;
        }

        if ($subscription_data !== null) {
            $request['subscription_data'] = $subscription_data;
        }

        $res = $this->post('/checkouts', $request);

        if (is_wp_error($res)) {
            throw new Exception('Failed to create checkout session: ' . esc_html($res->get_error_message()));
        }

        if (wp_remote_retrieve_response_code($res) !== 200) {
            throw new Exception('Failed to create checkout session: ' . esc_html($res['body']));
        }

        return json_decode($res['body'], true);
    }

    /**
     * Best-effort lookup of the customer's tax ID / VAT number from a WC order.
     *
     * WooCommerce core does not collect a tax ID on its checkout form; the value
     * comes from third-party VAT extensions, each of which uses its own order
     * meta key. This helper checks the three most widely-used keys in the
     * WooCommerce ecosystem and returns the first non-empty match.
     *
     * The list is intentionally short -- adding every long-tail key risks
     * picking up the wrong field on stores running multiple extensions. Stores
     * using a non-standard key can extend the list via the
     * `dodo_payments_order_tax_id_meta_keys` filter without forking the plugin.
     *
     * Priority order (highest signal first):
     *   1. `_billing_vat_number` -- WooCommerce EU VAT Number (official woocommerce.com extension)
     *   2. `_billing_eu_vat_number` -- EU VAT for WooCommerce (WP Whale / Algoritmika)
     *   3. `_billing_vat_id` -- Germanized Pro
     *
     * @param WC_Order $order
     * @return string|null Trimmed tax ID, or null when none of the keys carry a value.
     */
    private static function get_order_tax_id($order)
    {
        $keys = apply_filters('dodo_payments_order_tax_id_meta_keys', array(
            '_billing_vat_number',
            '_billing_eu_vat_number',
            '_billing_vat_id',
        ), $order);

        foreach ($keys as $key) {
            $value = $order->get_meta($key, true);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * Requests an upload URL and image ID for a product image from the API.
     *
     * @param string $dodo_product_id The ID of the product in the Dodo Payments API.
     * @return array{url: string, image_id: string} An array containing the upload URL and image ID.
     * @throws \Exception If the API request fails or returns an error response.
     */
    private function get_upload_url_and_image_id($dodo_product_id)
    {
        $res = $this->put("/products/{$dodo_product_id}/images?force_update=true", null);

        if (is_wp_error($res)) {
            throw new Exception('Failed to get upload url and image id for product ('
                . esc_html($dodo_product_id) . '): '
                . esc_html($res->get_error_message()));
        }

        if ($res['response']['code'] !== 200) {
            throw new Exception('Failed to get upload url and image id for product ('
                . esc_html($dodo_product_id) . '): '
                . esc_html($res['body']));
        }

        return json_decode($res['body'], true);
    }


    /**
     * Assigns an image to a product in the Dodo Payments API.
     *
     * Updates the specified product with the given image ID by sending a PATCH request to the API.
     *
     * @param string $dodo_product_id The ID of the product in the Dodo Payments API.
     * @param string $image_id The image ID to assign to the product.
     * @return void
     * @throws \Exception If the API request fails or returns a non-200 response.
     */
    private function set_product_image_id($dodo_product_id, $image_id)
    {
        $res = $this->patch("/products/{$dodo_product_id}", array('image_id' => $image_id));

        if (is_wp_error($res)) {
            throw new Exception('Failed to assign image (' . esc_html($image_id)
                . ') to product (' . esc_html($dodo_product_id)
                . '): ' . esc_html($res->get_error_message()));
        }

        if ($res['response']['code'] !== 200) {
            throw new Exception('Failed to assign image (' . esc_html($image_id)
                . ') to product (' . esc_html($dodo_product_id)
                . '): ' . esc_html($res['body']));
        }

        return;
    }

    /**
     * Retrieves product details from the Dodo Payments API by product ID.
     *
     * Returns an associative array of product data if found, or false if the product does not exist or an error occurs.
     *
     * @param string $dodo_product_id The unique identifier of the product in the Dodo Payments system.
     * @return array{
     *    addons: array<string>,
     *    business_id: string,
     *    created_at: string,
     *    description: string,
     *    image: string,
     *    is_recurring: bool,
     *    license_key_activation_message: string,
     *    license_key_activations_limit: int,
     *    license_key_duration: array{
     *      count: int,
     *      interval: string
     *    },
     *    license_key_enabled: bool,
     *    name: string,
     *    price: array{
     *      currency: string,
     *      discount: int,
     *      pay_what_you_want: bool,
     *      price: int,
     *      purchasing_power_parity: bool,
     *      suggested_price: int,
     *      tax_inclusive: bool,
     *      type: string
     *    },
     *    product_id: string,
     *    tax_category: string,
     *    updated_at: string
     * }|false Product data array on success, or false if not found or on error.
     */
    public function get_product($dodo_product_id)
    {
        $res = $this->get("/products/{$dodo_product_id}");

        if (is_wp_error($res)) {
            error_log("Dodo Payments: Failed to get product ($dodo_product_id): " . $res->get_error_message());
            return false;
        }

        if (wp_remote_retrieve_response_code($res) === 404) {
            error_log("Dodo Payments: Product ($dodo_product_id) not found: " . $res['body']);
            return false;
        }

        return json_decode($res['body'], true);
    }

    /**
     * Retrieves discount code details from the Dodo Payments API.
     *
     * Returns an associative array with discount code information if found, or false if the code does not exist or an error occurs.
     *
     * @param string $dodo_discount_id The unique identifier of the discount code in the Dodo Payments system.
     * @return array{
     *    amount: int,
     *    business_id: string,
     *    code: string,
     *    created_at: string,
     *    discount_id: string,
     *    expires_at: string,
     *    name: string,
     *    restricted_to: array<string>,
     *    times_used: int,
     *    type: string,
     *    usage_limit: int
     * }|false Discount code details as an associative array, or false if not found or on error.
     */
    public function get_discount_code($dodo_discount_id)
    {
        $res = $this->get("/discounts/{$dodo_discount_id}");

        if (is_wp_error($res)) {
            error_log("Dodo Payments: Failed to get discount code ($dodo_discount_id): " . $res->get_error_message());
            return false;
        }

        if (wp_remote_retrieve_response_code($res) === 404) {
            error_log("Dodo Payments: Discount code ($dodo_discount_id) not found: " . $res['body']);
            return false;
        }

        return json_decode($res['body'], true);
    }

    /**
     * Creates a new discount code in the Dodo Payments API.
     *
     * Sends the provided discount code details to the API and returns the created discount code data.
     *
     * @param array{
     *    amount: int,
     *    code: string,
     *    expires_at: string,
     *    name: string,
     *    restricted_to: string[],
     *    type: string,
     *    usage_limit: int
     * } $dodo_discount_body Discount code details to create.
     * @return array{
     *    amount: int,
     *    business_id: string,
     *    code: string,
     *    created_at: string,
     *    discount_id: string,
     *    expires_at: string,
     *    name: string,
     *    restricted_to: string[],
     *    times_used: int,
     *    type: string,
     *    usage_limit: int
     * } The created discount code data.
     * @throws \Exception If the API request fails or returns an error response.
     */
    public function create_discount_code($dodo_discount_body)
    {
        $res = $this->post('/discounts', $dodo_discount_body);

        if (is_wp_error($res)) {
            throw new Exception('Failed to create discount code: ' . esc_html($res->get_error_message()), );
        }

        if (wp_remote_retrieve_response_code($res) !== 200) {
            throw new Exception('Failed to create discount code: ' . esc_html($res['body']));
        }

        return json_decode($res['body'], true);
    }

    /**
     * Updates an existing discount code in the Dodo Payments API.
     *
     * @param string $dodo_discount_id The ID of the discount code to update.
     * @param array $dodo_discount_body The updated discount code data.
     * @return array The updated discount code details.
     * @throws \Exception If the API request fails or returns an error response.
     */
    public function update_discount_code($dodo_discount_id, $dodo_discount_body)
    {
        $res = $this->patch("/discounts/{$dodo_discount_id}", $dodo_discount_body);

        if (is_wp_error($res)) {
            throw new Exception('Failed to update discount code: ' . esc_html($res->get_error_message()));
        }

        if (wp_remote_retrieve_response_code($res) !== 200) {
            throw new Exception('Dodo Payments: Failed to update discount code: ' . esc_html($res['body']));
        }

        return json_decode($res['body'], true);
    }

    /**
     * Creates a subscription product in the Dodo Payments API using WooCommerce subscription product data.
     *
     * Extracts subscription period, interval, length, trial period, and price from the WooCommerce product, converts them to the API's expected format, and sends a request to create a recurring price product. Throws an exception if the WooCommerce Subscriptions plugin is not available or if the API request fails.
     *
     * @param WC_Product $product The WooCommerce subscription product to create in the API.
     * @return array{product_id: string} The created product's data from the Dodo Payments API.
     * @throws \Exception If the WooCommerce Subscriptions plugin is missing or the API request fails.
     */
    public function create_subscription_product($product)
    {
        if (!class_exists('WC_Subscriptions_Product')) {
            throw new Exception('WooCommerce Subscriptions plugin is required for subscription products');
        }

        $stripped_description = wp_strip_all_tags($product->get_description());
        $truncated_description = mb_substr($stripped_description, 0, self::MAX_PRODUCT_DESCRIPTION_LENGTH);

        // Get subscription details
        $period = WC_Subscriptions_Product::get_period($product);
        $period_count = WC_Subscriptions_Product::get_interval($product);
        $length = WC_Subscriptions_Product::get_length($product);

        if ($length === 0) {
            // default to 10 years
            switch ($period) {
                case 'day':
                    $length = 3650;
                    break;
                case 'week':
                    $length = 520;
                    break;
                case 'month':
                    $length = 120;
                    break;
                case 'year':
                    $length = 10;
                    break;
            }
        }

        $trial_length = WC_Subscriptions_Product::get_trial_length($product);
        $trial_period = WC_Subscriptions_Product::get_trial_period($product);

        $trial_period_days = 0;
        if ($trial_length > 0) {
            switch ($trial_period) {
                case 'day':
                    $trial_period_days = $trial_length;
                    break;
                case 'week':
                    $trial_period_days = $trial_length * 7;
                    break;
                case 'month':
                    $trial_period_days = $trial_length * 30;
                    break;
                case 'year':
                    $trial_period_days = $trial_length * 365;
                    break;
            }
        }

        $price_data = array(
            'currency' => get_woocommerce_currency(),
            'discount' => 0,
            'payment_frequency_count' => (int) $period_count,
            'payment_frequency_interval' => self::convert_wc_period_to_dodo($period),
            'price' => (int) ($product->get_price() * 100),
            'purchasing_power_parity' => false,
            'subscription_period_count' => (int) $length,
            'subscription_period_interval' => self::convert_wc_period_to_dodo($period),
            'type' => 'recurring_price',
            'tax_inclusive' => $this->global_tax_inclusive,
            'trial_period_days' => $trial_period_days,
        );

        $body = array(
            'name' => mb_substr($product->get_name(), 0, self::MAX_PRODUCT_NAME_LENGTH),
            'description' => $truncated_description,
            'price' => $price_data,
            'tax_category' => $this->global_tax_category,
        );

        $res = $this->post('/products', $body);

        if (is_wp_error($res)) {
            throw new Exception('Failed to create subscription product: ' . esc_html($res->get_error_message()));
        }

        if (wp_remote_retrieve_response_code($res) !== 200) {
            throw new Exception('Failed to create subscription product: ' . esc_html($res['body']));
        }

        return json_decode($res['body'], true);
    }

    /**
     * Updates an existing subscription product in the Dodo Payments API using WooCommerce subscription product data.
     *
     * Retrieves the current product from the API, extracts subscription details from the WooCommerce product, and updates the API product while preserving existing discount and tax settings. Throws an exception if the WooCommerce Subscriptions plugin is missing, the product is not found, or the API request fails.
     *
     * @param string $dodo_product_id The Dodo Payments product ID to update.
     * @param WC_Product $product The WooCommerce subscription product to sync.
     * @throws \Exception If the WooCommerce Subscriptions plugin is missing, the product is not found, or the API update fails.
     */
    public function update_subscription_product($dodo_product_id, $product)
    {
        if (!class_exists('WC_Subscriptions_Product')) {
            throw new Exception('WooCommerce Subscriptions plugin is required for subscription products');
        }

        $dodo_product = $this->get_product($dodo_product_id);

        if (!$dodo_product) {
            throw new Exception('Product (' . esc_html($dodo_product_id) . ') not found');
        }

        $stripped_description = wp_strip_all_tags($product->get_description());
        $truncated_description = mb_substr($stripped_description, 0, self::MAX_PRODUCT_DESCRIPTION_LENGTH);

        $period = WC_Subscriptions_Product::get_period($product);
        $period_count = WC_Subscriptions_Product::get_interval($product);
        $length = WC_Subscriptions_Product::get_length($product);

        if ($length === 0) {
            // default to 10 years, if subscription length is not set
            switch ($period) {
                case 'day':
                    $length = 3650;
                    break;
                case 'week':
                    $length = 520;
                    break;
                case 'month':
                    $length = 120;
                    break;
                case 'year':
                    $length = 10;
                    break;
            }
        }

        $trial_length = WC_Subscriptions_Product::get_trial_length($product);
        $trial_period = WC_Subscriptions_Product::get_trial_period($product);

        $trial_period_days = 0;
        if ($trial_length > 0) {
            switch ($trial_period) {
                case 'day':
                    $trial_period_days = $trial_length;
                    break;
                case 'week':
                    $trial_period_days = $trial_length * 7;
                    break;
                case 'month':
                    $trial_period_days = $trial_length * 30;
                    break;
                case 'year':
                    $trial_period_days = $trial_length * 365;
                    break;
            }
        }

        $price_data = array(
            'currency' => get_woocommerce_currency(),
            'payment_frequency_count' => (int) $period_count,
            'payment_frequency_interval' => self::convert_wc_period_to_dodo($period),
            'price' => (int) ($product->get_price() * 100),
            'discount' => $dodo_product['price']['discount'],
            'purchasing_power_parity' => $dodo_product['price']['purchasing_power_parity'],
            'subscription_period_count' => (int) $length,
            'subscription_period_interval' => self::convert_wc_period_to_dodo($period),
            'type' => 'recurring_price',
            'tax_inclusive' => $dodo_product['price']['tax_inclusive'],
            'trial_period_days' => $trial_period_days,
        );

        $body = array(
            'name' => mb_substr($product->get_name(), 0, self::MAX_PRODUCT_NAME_LENGTH),
            'description' => $truncated_description,
            'price' => $price_data,
            'tax_category' => $dodo_product['tax_category'],
        );

        $res = $this->patch("/products/{$dodo_product_id}", $body);

        if (is_wp_error($res)) {
            throw new Exception('Failed to update subscription product: ' . esc_html($res->get_error_message()));
        }

        if (wp_remote_retrieve_response_code($res) !== 200) {
            throw new Exception('Failed to update subscription product: ' . esc_html($res['body']));
        }

        return;
    }

    /**
     * Retrieves subscription details from the Dodo Payments API.
     *
     * @param string $dodo_subscription_id The ID of the subscription to retrieve.
     * @return array|false The subscription data as an associative array, or false if not found or on error.
     */
    public function get_subscription($dodo_subscription_id)
    {
        $res = $this->get("/subscriptions/{$dodo_subscription_id}");

        if (is_wp_error($res)) {
            error_log("Dodo Payments: Failed to get subscription ($dodo_subscription_id): " . $res->get_error_message());
            return false;
        }

        if (wp_remote_retrieve_response_code($res) === 404) {
            error_log("Dodo Payments: Subscription ($dodo_subscription_id) not found: " . $res['body']);
            return false;
        }

        return json_decode($res['body'], true);
    }

    /**
     * Cancels a subscription immediately in the Dodo Payments API.
     *
     * Sets the subscription status to 'cancelled' via the API. Throws an exception if the operation fails.
     *
     * @param string $dodo_subscription_id The ID of the subscription to cancel.
     * @throws \Exception If the API request fails or returns an error response.
     */
    public function cancel_subscription($dodo_subscription_id)
    {
        $body = array(
            'status' => 'cancelled'
        );

        $res = $this->patch("/subscriptions/{$dodo_subscription_id}", $body);

        if (is_wp_error($res)) {
            throw new Exception("Failed to cancel subscription: " . esc_html($res->get_error_message()));
        }

        if (wp_remote_retrieve_response_code($res) !== 200) {
            throw new Exception("Failed to cancel subscription: " . esc_html($res['body']));
        }

        return;
    }

    /**
     * Schedules a subscription to be canceled at its next billing date in the Dodo Payments API.
     *
     * @param string $dodo_subscription_id The ID of the subscription to update.
     * @throws \Exception If the API request fails or returns an error response.
     */
    public function cancel_subscription_at_next_billing_date($dodo_subscription_id)
    {
        $body = array(
            'cancel_at_next_billing_date' => true
        );

        $res = $this->patch("/subscriptions/{$dodo_subscription_id}", $body);

        if (is_wp_error($res)) {
            throw new Exception("Failed to cancel subscription: " . esc_html($res->get_error_message()));
        }

        if (wp_remote_retrieve_response_code($res) !== 200) {
            throw new Exception("Failed to cancel subscription: " . esc_html($res['body']));
        }

        return;
    }

    /**
     * Pauses an active subscription in the Dodo Payments API.
     *
     * Sets the subscription status to 'on_hold' for the specified subscription ID. Throws an exception if the API request fails.
     *
     * @param string $dodo_subscription_id The ID of the subscription to pause.
     * @throws \Exception If the API request fails or returns an error response.
     */
    public function pause_subscription($dodo_subscription_id)
    {
        $body = array(
            'status' => 'on_hold'
        );

        $res = $this->patch("/subscriptions/{$dodo_subscription_id}", $body);

        if (is_wp_error($res)) {
            throw new Exception("Failed to pause subscription: " . esc_html($res->get_error_message()));
        }

        if (wp_remote_retrieve_response_code($res) !== 200) {
            throw new Exception("Failed to pause subscription: " . esc_html($res['body']));
        }

        return;
    }

    /**
     * Resumes a paused subscription in the Dodo Payments API by setting its status to active and clearing any pending cancellation.
     *
     * @param string $dodo_subscription_id The ID of the subscription to resume.
     * @throws \Exception If the API request fails or returns an error response.
     */
    public function resume_subscription($dodo_subscription_id)
    {
        $body = array(
            'cancel_at_next_billing_date' => false,
            'status' => 'active'
        );

        $res = $this->patch("/subscriptions/{$dodo_subscription_id}", $body);

        if (is_wp_error($res)) {
            throw new Exception("Failed to resume subscription: " . esc_html($res->get_error_message()));
        }

        if (wp_remote_retrieve_response_code($res) !== 200) {
            throw new Exception("Failed to resume subscription: " . esc_html($res['body']));
        }

        return;
    }

    /**
     * Converts a WooCommerce subscription period string to the Dodo Payments interval format.
     *
     * Supported values are 'day', 'week', 'month', and 'year'. Returns 'Month' if the input is unrecognized.
     *
     * @param string $wc_period WooCommerce subscription period ('day', 'week', 'month', 'year').
     * @return string Corresponding Dodo Payments interval ('Day', 'Week', 'Month', 'Year').
     */
    private static function convert_wc_period_to_dodo($wc_period)
    {
        switch ($wc_period) {
            case 'day':
                return 'Day';
            case 'week':
                return 'Week';
            case 'month':
                return 'Month';
            case 'year':
                return 'Year';
            default:
                return 'Month'; // Default to month if unknown
        }
    }

    /**
     * Sends an authenticated GET request to the Dodo Payments API for the specified path.
     *
     * @param string $path The API endpoint path to request.
     * @return array|WP_Error The response from the API as an array, or a WP_Error on failure.
     */
    private function get($path)
    {
        return wp_remote_get(
            $this->get_base_url() . $path,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                ),
            )
        );
    }

    /**
     * Sends an authenticated POST request with a JSON body to the Dodo Payments API.
     *
     * @param string $path The API endpoint path.
     * @param mixed $body The data to send as the JSON request body.
     * @return array|WP_Error The response from the API or a WP_Error on failure.
     */
    private function post($path, $body)
    {
        return wp_remote_post(
            $this->get_base_url() . $path,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode($body),
            )
        );
    }

    /**
     * Sends an authenticated PUT request with a JSON body to the Dodo Payments API.
     *
     * @param string $path The API endpoint path.
     * @param mixed $body The data to be sent as the JSON request body.
     * @return array|WP_Error The response from the API or a WP_Error on failure.
     */
    private function put($path, $body)
    {
        return wp_remote_request(
            $this->get_base_url() . $path,
            array(
                'method' => 'PUT',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode($body)
            )
        );
    }

    /**
     * Sends an authenticated PATCH request with a JSON body to the Dodo Payments API.
     *
     * @param string $path The API endpoint path.
     * @param mixed $body The data to be sent as the JSON request body.
     * @return array|WP_Error The response from the API or a WP_Error on failure.
     */
    private function patch($path, $body)
    {
        return wp_remote_request(
            $this->get_base_url() . $path,
            array(
                'method' => 'PATCH',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode($body),
            )
        );
    }

    /**
     * Returns the base URL for the Dodo Payments API, selecting the test or live endpoint based on the current mode.
     *
     * @return string The API base URL.
     */
    private function get_base_url()
    {
        return $this->testmode ? 'https://test.dodopayments.com' : 'https://live.dodopayments.com';
    }
}
