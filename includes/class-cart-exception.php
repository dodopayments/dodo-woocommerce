<?php

/**
 * This exception will be shown to the customer via the
 * `wp_add_notice` function.
 * 
 * @since 0.2.0
 */
class CartException extends Exception
{
  public function __construct($message, $code = 0, Exception $previous = null)
  {
    parent::__construct($message, $code, $previous);
  }
}