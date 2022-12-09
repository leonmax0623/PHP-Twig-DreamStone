<?php

namespace DS\Model;

use DS\Core\Model\MongoModel;
use MongoDB\BSON\ObjectId;

/**
 * Class User
 * @package App\Model
 */
class JewelryType extends MongoModel {

  private $generalType = '5d1a07eca5b0a50b5862fe28'; // no sense to get dynamically, we have it in jewelrytype.json
  private $blocked = ['5d1a07eca5b0a50b5862fe28', '5d1a07eca5b0a50b5862fe29'];

  /**
   * @var string Collection name
   */
  protected $collection = 'jewelrytype';

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'code' => ['index' => 1, 'unique' => true]
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    'code' => ['$type' => 'string'],
    'title' => ['$type' => 'string'],
    'desc' => ['$type' => 'string'],
    'keywords' => ['$type' => 'string']
  ];

  /**
   * @param string $id
   * @return bool
   */
  public function isEditable($id)
  {
    return !in_array($id, $this->blocked);
  }

  /**
   * @return string $id
   */
  public function getGeneralTypeId()
  {
    return $this->generalType;
  }

  public function getEditableTypes()
  {
    $res = $this->find(['$and' => array_map(function($id){
      return ['_id' => ['$ne' => new ObjectId($id)]];
    }, $this->blocked)]);
    $this->stringifyValues($res);
    return $res;
  }
}
