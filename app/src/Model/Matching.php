<?php

namespace DS\Model;

use DS\Core\Model\MongoModel;

/**
 * Class Matching
 * @package App\Model
 */
class Matching extends MongoModel
{
  /**
   * @var string Collection name
   */
  protected $collection = 'matching';

  /**
   * @var array Indexes
   */
  // protected $indexes = [
  //   '_id' => ['index' => 1]
  // ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    // '_id' => ['$type' => 'objectId'],
    'items' => ['$type' => 'array'],
  ];
}
