<?php

namespace DS\Model;

use DS\Core\Model\MongoModel;
use DS\Model\Cart\Cookie;
use MongoDB\BSON\ObjectId;

/**
 * Class User
 * @package App\Model
 */
class Coupon extends MongoModel
{
  /**
   * @var string Collection name
   */
  protected $collection = 'coupon';

  /**
   * @var array
   */
  public $search_fields = [
    'code',
    'type',
    'value',
  ];

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'code' => ['index' => 1, 'unique' => true],
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    'code' => ['$type' => 'string'],
    'type' => ['$type' => 'string'],
    'value' => ['$type' => 'int'],
    'count' => ['$type' => 'int'],
  ];

  public function applyDiscount($couponCode, $subtotal)
  {
    $coupon = $this->findOne(['code' => $couponCode]);
    if (empty($coupon)) {
      return $subtotal;
    }

    if ($coupon->type == 'fixed') {
      $subtotal = max($subtotal - $coupon->value, 0);
    } else if ($coupon->type == 'percent') {
      $subtotal *= 1 - $coupon->value * 0.01;
    }

    return $subtotal;
  }
}
