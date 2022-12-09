<?php
namespace DS\Model;

use DS\Core\Model\MongoModel;

/**
 * Class User
 * @package App\Model
 */
class Polish extends MongoModel
{
  /**
   * @var string Collection name
   */
  protected $collection = 'polish';

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'code'    => ['index' => 1, 'unique' => true]
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    'code'       => ['$type' => 'string'],
    'desc'        => ['$type' => 'string']
  ];
}