<?php

class Dodo_Payments_API
{
  private bool $testmode;
  private string $api_key;

  public function __construct(bool $testmode, string $api_key)
  {
    $this->testmode = $testmode;
    $this->api_key = $api_key;
  }

  /**
   * Creates a product
   * 
   * @param WC_Product $product
   * @return array|WP_Error
   */
  public function create_product($product)
  {
    $body = array(
      'name' => $product->get_name(),
      'price' => array(
        'type' => 'one_time_price',
        'currency' => get_woocommerce_currency(),
        'price' => (int) $product->get_price() * 100, // warn: considering that the currency is INR or USD
        'discount' => 0, // todo: update defaults
        'purchasing_power_parity' => false // todo: update defaults
      ),
      'tax_category' => 'digital_products' // todo: update default
    );

    return $this->post('/products', $body);
  }

  /**
   * Syncs the image for a product
   * 
   * @param WC_Product $product
   * @param string $dodo_product_id
   * @throws \Exception
   * @return void
   */
  public function upload_image_for_product($product, $dodo_product_id)
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
      throw new Exception('Failed to upload image: ' . $response->get_error_message());
    }

    if (wp_remote_retrieve_response_code($response) !== 200) {
      throw new Exception('Failed to upload image: ' . wp_remote_retrieve_body($response));
    }

    $this->set_product_image_id($dodo_product_id, $image_id);

    return;
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
    $res = wp_remote_request(
      $this->get_base_url() . '/products/' . $dodo_product_id . '/images?force_update=true',
      array(
        'method' => 'PUT',
        'headers' => array(
          'Authorization' => 'Bearer ' . $this->api_key,
        ),
      )
    );

    if (is_wp_error($res))
      throw new Exception("Failed to get upload url and image id for product ($dodo_product_id): " . $res->get_error_message());

    if ($res['response']['code'] !== 200)
      throw new Exception("Failed to get upload url and image id for product ($dodo_product_id): " . $res['body']);

    return json_decode($res['body'], true);
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
    $res = wp_remote_request(
      $this->get_base_url() . '/products/' . $dodo_product_id,
      array(
        'method' => 'PATCH',
        'headers' => array(
          'Authorization' => 'Bearer ' . $this->api_key,
          'Content-Type' => 'application/json',
        ),
        'body' => json_encode(array('image_id' => $image_id)),
      )
    );

    if (is_wp_error($res))
      throw new Exception("Failed to assign image ($image_id) to product ($dodo_product_id): " . $res->get_error_message());

    if ($res['response']['code'] !== 200)
      throw new Exception("Failed to assign image ($image_id) to product ($dodo_product_id): " . $res['body']);

    return;
  }

  public function get_product($dodo_product_id)
  {
    $res = $this->get("/products/{$dodo_product_id}");

    if (is_wp_error($res)) {
      error_log("Failed to get product ($dodo_product_id): " . $res->get_error_message());
      return false;
    }

    if (wp_remote_retrieve_response_code($res) === 404) {
      return false;
    }

    return json_decode($res['body']);
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
    return wp_remote_post(
      $this->get_base_url() . $path,
      array(
        'headers' => array(
          'Authorization' => 'Bearer ' . $this->api_key,
        ),
      )
    );

  }

  public function get_base_url()
  {
    return $this->testmode ? 'https://test.dodopayments.com' : 'https://live.dodopayments.com';
  }
}
