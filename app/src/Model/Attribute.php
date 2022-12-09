<?php
namespace DS\Model;

use DS\Core\Model\MongoModel;

/**
 * Class User
 * @package App\Model
 */
class Attribute extends MongoModel
{
  /**
   * @var string Collection name
   */
  protected $collection = 'attribute';

  /**
   * @var array
   */
  public $search_fields = [
    'name',
  ];

  /**
   * @var array Indexes
   */
  protected $indexes = [];

  /**
   * @var array Required fields
   */
  protected $validator = [
    'name' => ['$type' => 'string'],
    'isEnabled' => ['$type' => 'bool'],
    'values' => ['$type' => 'array'],
  ];

  public function create($items, $queryParams)
  {
    if (!$items || !is_array($items))
      throw new \Exception('parameter "$items" should be array');

    $items['values'] = [];
    return $this->insertOne($items)->getInsertedId();
  }

}
