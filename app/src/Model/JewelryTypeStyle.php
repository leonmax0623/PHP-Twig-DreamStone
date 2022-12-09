<?php

namespace DS\Model;

use DS\Core\Model\MongoModel;
use MongoDB\BSON\ObjectId;

/**
 * Class User
 * @package App\Model
 */
class JewelryTypeStyle extends MongoModel {

  /**
   * @var string Collection name
   */
  protected $collection = 'jewelrytypestyle';

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'jewelrytype_id' => ['index' => 1]
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    'jewelrytype_id' => ['$type' => 'objectId'],
    'title' => ['$type' => 'string'],
    'image' => ['$type' => 'string'],
  ];

}
