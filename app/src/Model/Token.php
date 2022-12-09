<?php

namespace DS\Model;

use DS\Core\Model\MongoModel;

/**
 * Class User
 * @package App\Model
 */
class Token extends MongoModel {

  /**
   * @var string Collection name
   */
  protected $collection = 'token';

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'value' => ['index' => 1, 'unique' => true],
    'user_id' => ['index' => 1 ],
    'created' => ['index' => 1 ]
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    'value' => ['$type' => 'string'],
    'user_id' => ['$type' => 'objectId'],
    'created' => ['$type' => 'date']
  ];
}