<?php
namespace DS\Model;

use DS\Core\Model\MongoModel;

/**
 * Class User
 * @package App\Model
 */
class Vendor extends MongoModel
{
  /**
   * @var array
   */
  public $search_fields = [
    'name'
  ];

  /**
   * @var string Collection name
   */
  protected $collection = 'vendor';

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'type' => ['index' => 1],
    'code' => ['index' => 1, 'unique' => true]
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    'type' => ['$type' => 'string'], // rapaport, idex, independent
    'code' => ['$type' => 'string'],
    'name' => ['$type' => 'string'],
    'isEnabled' => ['$type' => 'bool'],
    'isNatural' => ['$type' => 'bool'],
    'showCerts' => ['$type' => 'bool'],
    'showImages' => ['$type' => 'bool'],
    'isLocal' => ['$type' => 'bool'],
    'folder' => ['$type' => 'string'],
    'fields' => ['$type' => 'array']
  ];

  public function getEnabledVendors() {
    return $this->find(['isEnabled' => true]);
  }

  public function getVendors() {
    return $this->find();
  }
}