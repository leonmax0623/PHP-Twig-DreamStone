<?php

namespace DS\Model;

use DS\Core\Model\MongoModel;

/**
 * Class User
 * @package App\Model
 */
class RingStyle extends MongoModel {
  /**
   * @var string Collection name
   */
  protected $collection = 'ringstyle';

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
    'image' => ['$type' => 'string'],
    'description' => ['$type' => 'string']
  ];
}