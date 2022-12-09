<?php

namespace DS\Model;

use DS\Core\Model\MongoModel;

/**
 * Class User
 * @package App\Model
 */
class WeddingMen extends MongoModel {
  /**
   * @var string Collection name
   */
  protected $collection = 'weddingmen';

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
    'desc' => ['$type' => 'string']
  ];
}