<?php

namespace DS\Model;

use DS\Core\Model\MongoModel;

/**
 * Class User
 * @package App\Model
 */
class Gemstone extends MongoModel {

  /**
   * @var string Collection name
   */
  protected $collection = 'gemstone';

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'certificateNumber' => ['index' => 1, 'unique' => true],
    'updated' => ['index' => 1],
    'gemstoneshape' => ['index' => 1],
    'gemstonecolor' => ['index' => 1],
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    '$jsonSchema' => [
      'bsonType' => 'object',
      'required' => ['certificateNumber'],
      'properties' => [
        'gemstoneshape' => [
          'bsonType' => 'objectId'
        ],
        'gemstonecolor' => [
          'bsonType' => 'objectId'
        ],
        'updated' => [
          'bsonType' => 'date'
        ],
      ]
    ]
  ];


}