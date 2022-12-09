<?php
namespace DS\Model;

use DS\Core\Model\MongoModel;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\ObjectId;

/**
 * Class DiamondPrice
 * @package App\Model
 */
class DiamondPrice extends MongoModel
{
  /**
   * @var string Collection name
   */
  protected $collection = 'diamondPrice';

  /**
   * @var array
   */
  public $search_fields = [
    'code',
    'min',
    'max',
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
    'min' => ['$type' => 'decimal'],
    'max' => ['$type' => 'decimal'],
    'value' => ['$type' => 'int'],
  ];

  public function getRate($priceExternal)
  {
    $min['$lte'] = new Decimal128($priceExternal);
    $max['$gte'] =  new Decimal128($priceExternal);
    $price = $this->findOne(['min' => $min, 'max' => $max]);

    return $price ? $priceExternal->__toString()*(1+$price->value/100) : $priceExternal;
  }

  /**
   * Create record in table based on passed array
   *
   * @param $items
   * @param $queryParams
   * @return mixed
   * @throws \Exception
   */
  public function create($items, $queryParams)
  {
    if (!$items || !is_array($items))
      throw new \RuntimeException('parameter "$items" should be array');

    $items = $this->prepare($items);

    return $this->insertOne($items)->getInsertedId();
  }

  /**
   * Update record in table based on passed array
   *
   * @param array $items
   * @param array $where
   * @return mixed
   * @throws \Exception
   */
  public function updateWhere(array $items, array $where)
  {
    $items = $this->prepare($items);

    if (empty($items))
      return 0;

    $r = $this->updateMany(['_id' => new ObjectId($where['_id'])], ['$set' => $items]);

    return $r->getModifiedCount();
  }

  /**
   * Prepare items to insert into database
   *
   * @param array $items
   * @return array $items
   */
  private function prepare(array $items)
  {
    if (!$items || !is_array($items))
      return $items;

    foreach (['min', 'max'] as $i)
      if (isset($items[$i]))
        $items[$i] = empty($items[$i]) ? null : new Decimal128($items[$i]);
    return $items;
  }

  public function RangeCrossed($items)
  {
    $min= new Decimal128($items['min']);
    $max=  new Decimal128($items['max']);

    $price = $this->findOne(['$or' =>[
      ['min' => ['$lte' => $min], 'max' => ['$gte' => $min]],
      ['min' => ['$lte' => $max], 'max' => ['$gte' => $max]],
      ['min' => ['$lte' => $max, '$gte' => $min], 'max' => ['$gte' => $min, '$lte' => $max]],
    ]]);
    return boolval($price);
  }
}
