<?php

namespace DS\Model;

use MongoDB\BSON\ObjectId;
use DS\Core\Model\MongoModel;
use DS\Model\Cart\Cookie;

/**
 * Class Order
 * @package App\Model
 */
class Order extends MongoModel
{

  /**
   * @var string Collection name
   */
  protected $collection = 'order';

  /**
   * @var array
   */
  public $search_fields = [
    //    'number', // NOTE: number should be searched for as number
    'billingInfo.shipping_first_name',
    'billingInfo.shipping_last_name',
    'billingInfo.shipping_phone',
    'billingInfo.billing_first_name',
    'billingInfo.billing_last_name',
    'billingInfo.billing_phone',
    'diamonds.certificateNumber',
    'composite.diamond.certificateNumber',
  ];

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'user_id' => ['index' => 1],
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    'user_id' => ['$type' => 'objectId'],
    'products' => ['$type' => 'array'],
    'coupon' => ['$type' => ['object', 'null']],
  ];

  public function getByUser($user)
  {
    return $this->find(['user_id' => $user->_id]);
  }

  public function getByNumber(int $number, $user = null)
  {
    $find = ['number' => $number];
    if ($user) $find['user_id'] = $user->_id;

    return $this->findOne($find);
  }

  public function add($user, $document)
  {
    $document->user_id = $user->_id;

    // Disabling reserved diamonds in the stock

    if (count($document->diamonds)) {
      $Diamond = new Diamond($this->mongodb);
      foreach ($document->diamonds as $diamond) {
        $Diamond->updateWhere(['isEnabled' => false], ['_id' => $diamond->_id]);
      }
    }

    if (count($document->composite)) {
      $Diamond = new Diamond($this->mongodb);
      foreach ($document->composite as $composite) {
        $Diamond->updateWhere(['isEnabled' => false], ['_id' => $composite->diamond->_id]);
      }
    }

    return $this->insertOne($document)->getInsertedId();
  }

  public function getNextNumber()
  {
    $fakeStart = 184583;
    $documents = $this->find([], ['limit' => 1, 'sort' => ['number' => -1]]);
    if (!$documents) {
      return $fakeStart;
    }

    return $documents[0]->number + 1;
  }
}
