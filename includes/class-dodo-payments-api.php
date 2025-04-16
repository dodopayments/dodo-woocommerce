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

  public function upload_image_for_product($product, $dodo_product_id)
  {
    $image_id = $product->get_image_id();

    if (!$image_id) {
      error_log('Product has no image');
      return;
    }

    $image_path = get_attached_file($image_id);
    if (!$image_path) {
      error_log('Could not find image file');
      return;
    }

    $image_contents = file_get_contents($image_path);
    if ($image_contents === false) {
      error_log('Could not read image file');
      return;
    }

    ['url' => $upload_url, 'image_id' => $image_id] = $this->get_upload_url_and_image_id($dodo_product_id);
    $response = wp_remote_request($upload_url, array(
      'method' => 'PUT',
      'body' => $image_contents,
    ));

    if (is_wp_error($response)) {
      error_log('Failed to upload image: ' . $response->get_error_message());
      return;
    }

    if (wp_remote_retrieve_response_code($response) !== 200) {
      error_log('Failed to upload image: ' . wp_remote_retrieve_body($response));
      return;
    }

    $this->set_product_image_id($dodo_product_id, $image_id);

    return;
  }

  /**
   * Summary of get_upload_url_and_image_id
   * @param string $dodo_product_id
   * @return array{url: string, image_id: string}
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
      error_log($res->getMessage());

    if ($res['response']['code'] !== 200)
      error_log($res['body']);

    return json_decode($res['body'], true);
  }

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
      error_log($res->getMessage());

    if ($res['response']['code'] !== 200)
      error_log($res['body']);

    return $res;
  }

  public function get_product($dodo_product_id)
  {
    $url = $this->get_base_url() . '/products/' . $dodo_product_id;

    $res = wp_remote_get($url);

    if (is_wp_error($res) || $res['response']['code'] === 404) {
      return null;
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

  public function get_base_url()
  {
    return $this->testmode ? 'https://test.dodopayments.com' : 'https://live.dodopayments.com';
  }
}
