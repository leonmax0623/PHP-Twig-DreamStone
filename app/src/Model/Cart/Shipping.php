<?php

namespace DS\Model\Cart;

use DS\Model\Options;

/**
 * Class Shipping
 * @package App\Model
 */
class Shipping {

  private $address;
  private $price;

  public function __construct($mongodb, $data)
  {
    $this->mongodb = $mongodb;
    $this->address = $data['address'];
    $this->price = $data['price'];
  }

  public function getDetails()
  {
    $options = (new Options($this->mongodb))->findOne(['name' => 'shipping']);
    if (isset($options->value->price)) {
      $price = $options->value->price;
    } else {
      $price = 10; // impossible to be here, TODO: add warning to log
    }

    return (object)[
      'price' => 0, // TODO: calculate
    ];
  }
}
