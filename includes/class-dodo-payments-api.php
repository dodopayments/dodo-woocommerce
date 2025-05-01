<?php

class Dodo_Payments_API
{
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
   * Summary of __construct
   * @param array{testmode: bool, api_key: string, global_tax_category: string, global_tax_inclusive: bool} $options
   */
  public function __construct($options)
  {
    $this->testmode = $options['testmode'];
    $this->api_key = $options['api_key'];
    $this->global_tax_category = $options['global_tax_category'];
    $this->global_tax_inclusive = $options['global_tax_inclusive'];
  }

  /**
   * Creates a product
   * 
   * @param WC_Product $product Product in WooCommerce
   * @return array{product_id: string} Product in the Dodo Payments API 
   * @throws \Exception
   */
  public function create_product($product)
  {
    $stripped_description = wp_strip_all_tags($product->get_description());
    $truncated_description = mb_substr($stripped_description, 0, max(1000, mb_strlen($stripped_description)));

    $body = array(
      'name' => $product->get_name(),
      'description' => $truncated_description,
      'price' => array(
        'type' => 'one_time_price',
        'currency' => get_woocommerce_currency(),
        'price' => (int) $product->get_price() * 100, // fixme: assuming that the currency is INR or USD
        'discount' => 0, // todo: update defaults
        'purchasing_power_parity' => false, // todo: deal with it when the feature is implemented
        'tax_inclusive' => $this->global_tax_inclusive,
      ),
      'tax_category' => $this->global_tax_category,
    );

    $res = $this->post('/products', $body);

    if (is_wp_error($res)) {
      throw new Exception("Failed to create product: " . esc_html($res->get_error_message()));
    }

    if (wp_remote_retrieve_response_code($res) !== 200) {
      throw new Exception("Failed to create product: " . esc_html($res['body']));
    }

    return json_decode($res['body'], true);
  }

  /**
   * Update a product in the Dodo Payments API
   * 
   * @param string $dodo_product_id
   * @param WC_Product $product
   * @throws \Exception
   * @return void
   */
  public function update_product($dodo_product_id, $product)
  {
    $dodo_product = $this->get_product($dodo_product_id);

    if (!$dodo_product) {
      throw new Exception('Product (' . esc_html($dodo_product_id) . ') not found');
    }

    // ignore global options, respect the tax_category and tax_inclusive set
    // from the dashboard
    $body = array(
      'name' => $product->get_name(),
      'description' => $product->get_description(),
      'price' => array(
        'type' => 'one_time_price',
        'currency' => get_woocommerce_currency(),
        'price' => (int) $product->get_price() * 100, // fixme: assuming that the currency is INR or USD
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
   * Syncs the image for a product
   * 
   * @param WC_Product $product
   * @param string $dodo_product_id
   * @throws \Exception
   * @return void
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
   * Creates a payment in the Dodo Payments API
   * 
   * @param WC_Order $order
   * @param array{amount: mixed, product_id: string, quantity: mixed}[] $synced_products
   * @param string $return_url The URL to redirect to after the payment is completed
   * @throws \Exception
   * @return array{payment_id: string, payment_link: string}
   */
  public function create_payment($order, $synced_products, $return_url)
  {
    $request = array(
      'billing' => array(
        'city' => $order->get_billing_city(),
        'country' => $order->get_billing_country(),
        'state' => $order->get_billing_state(),
        'street' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
        'zipcode' => $order->get_billing_postcode(),
      ),
      'customer' => array(
        'email' => $order->get_billing_email(),
        'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
      ),
      'product_cart' => $synced_products,
      'payment_link' => true,
      'return_url' => $return_url,
    );

    $res = $this->post('/payments', $request);

    if (is_wp_error($res)) {
      throw new Exception("Failed to create payment: " . esc_html($res->get_error_message()));
    }

    if (wp_remote_retrieve_response_code($res) !== 200) {
      throw new Exception("Failed to create payment: " . esc_html($res['body']));
    }

    return json_decode($res['body'], true);
  }

  /**
   * Gets the upload url and image id for a product
   * 
   * @param string $dodo_product_id
   * @return array{url: string, image_id: string}
   * @throws \Exception
   */
  private function get_upload_url_and_image_id($dodo_product_id)
  {
    $res = $this->put("/products/{$dodo_product_id}/images?force_update=true", null);

    if (is_wp_error($res))
      throw new Exception('Failed to get upload url and image id for product ('
        . esc_html($dodo_product_id) . '): '
        . esc_html($res->get_error_message()));

    if ($res['response']['code'] !== 200)
      throw new Exception('Failed to get upload url and image id for product ('
        . esc_html($dodo_product_id) . '): '
        . esc_html($res['body']));

    return json_decode($res['body'], true);
  }

  private function put($path, $body)
  {
    return wp_remote_request(
      $this->get_base_url() . $path,
      array(
        'method' => 'PUT',
        'headers' => array(
          'Authorization' => 'Bearer ' . $this->api_key,
        )
      )
    );
  }

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
   * Sets an image id to a product
   * 
   * @param string $dodo_product_id
   * @param string $image_id
   * @return void
   * @throws \Exception
   */
  private function set_product_image_id($dodo_product_id, $image_id)
  {
    $res = $this->patch("/products/{$dodo_product_id}", array('image_id' => $image_id));

    if (is_wp_error($res))
      throw new Exception('Failed to assign image (' . esc_html($image_id)
        . ') to product (' . esc_html($dodo_product_id)
        . '): ' . esc_html($res->get_error_message()));

    if ($res['response']['code'] !== 200)
      throw new Exception('Failed to assign image (' . esc_html($image_id)
        . ') to product (' . esc_html($dodo_product_id)
        . '): ' . esc_html($res['body']));

    return;
  }

  /**
   * Get a product from the Dodo Payments API
   * 
   * @param string $dodo_product_id
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
   * }|false
   */
  public function get_product($dodo_product_id)
  {
    $res = $this->get("/products/{$dodo_product_id}");

    if (is_wp_error($res)) {
      error_log("Failed to get product ($dodo_product_id): " . $res->get_error_message());
      return false;
    }

    if (wp_remote_retrieve_response_code($res) === 404) {
      error_log("Product ($dodo_product_id) not found: " . $res['body']);
      return false;
    }

    return json_decode($res['body'], true);
  }

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

  private function get_base_url()
  {
    return $this->testmode ? 'https://test.dodopayments.com' : 'https://live.dodopayments.com';
  }
}
