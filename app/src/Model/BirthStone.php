<?php

namespace DS\Model;

use DS\Core\Model\MongoModel;

/**
 * Class User
 * @package App\Model
 */
class BirthStone extends MongoModel {

  /**
   * @var string Collection name
   */
  protected $collection = 'birthstone';

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
    'date' => ['$type' => 'string']
  ];
}