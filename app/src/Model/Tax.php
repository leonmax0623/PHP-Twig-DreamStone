<?php
namespace DS\Model;

use DS\Core\Model\MongoModel;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\ObjectId;

/**
 * Class User
 * @package App\Model
 */
class Tax extends MongoModel
{
  /**
   * @var string Collection name
   */
  protected $collection = 'tax';

  public $search_fields = [
    'code',
    'name',
  ];

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'code' => ['index' => 1]
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    'code' => ['$type' => 'string'],
    'name' => ['$type' => 'string'],
    'value' => ['$type' => ['decimal', 'null']],
  ];

  public function updateWhere(array $items, array $where)
  {
    $data = [
      'value' => $items['value'] || $items['value'] === '0' || $items['value'] === 0
        ? new Decimal128($items['value'])
        : null
    ];
    if (isset($items['states'])) {
      foreach ($items['states'] as &$state) {
        $state['value'] = $state['value'] || $state['value'] === '0' || $state['value'] === 0
          ? new Decimal128($state['value'])
          : null;
      }
      $data['states'] = $items['states'];
    }

    $r = $this->updateMany(['_id' => new ObjectId($where['_id'])], ['$set' => $data]);

    return $r->getModifiedCount();
  }

  public function getDetails($data = [])
  {
    $tax = (object)['price' => 0];
    if (empty($data['country']) || empty($data['price']))
      return $tax;

    $country = $this->findOne(['code' => $data['country']]);
    if (!$country)
      return $tax;

    $taxPercent = $country->value;
    if (!empty($data['state']) && !empty($country->states))
      foreach ($country->states as $state)
        if ($state->code === $data['state'])
          $taxPercent = $state->value;

    $tax->price = $taxPercent ? $data['price'] * (float) $taxPercent->__toString() / 100 : 0;
    return $tax;
  }
}
