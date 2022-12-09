<?php

namespace DS\Core\Model;

use DS\Model\Options;

/**
 * Class AbstractProduct
 * @package App\Model
 */
abstract class AbstractProduct extends MongoModel
{

  /**
   * @param object $product
   * @return string
   */
  abstract public function getTitle(object $product);

  /**
   * @param object $product
   * @param string $origin
   * @return string
   */
  abstract public function getPermalink(object $product, string $origin = '');

  /**
   * @param object $product
   * @return string
   */
  abstract public function getPrice(object $product);

  /**
   * @param object $product
   */
  abstract public function populate(object &$product);

  /**
   * @param object $product
   * @param int $timezone_offset
   * @return array
   */
  public function getShippingDetails(object $product, int $timezone_offset = 0)
  {
    $localTime = time() + 3600 * $timezone_offset; // EST = -5

    if (empty($product->shipping_time)) {
      $options = (new Options($this->mongodb))->findOne(['name' => 'shipping']);
      $days = isset($product->group) && isset($options->value->defaultDeliveryTime->{$product->group})
        ? $options->value->defaultDeliveryTime->{$product->group}
        : 0;
    } else {
      $days = $product->shipping_time;
    }

    return [
      'orderBy' => $localTime,
      'shipsBy' => $localTime + $days * 86400,
      'days' => $days
    ];
  }

}