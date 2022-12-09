<?php
namespace DS\Model;

use DS\Core\Model\MongoModel;

/**
 * Class User
 * @package App\Model
 */
class Options extends MongoModel
{
  /**
   * @var string Collection name
   */
  protected $collection = 'options';

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'name'    => ['index' => 1, 'unique' => true]
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    'name'       => ['$type' => 'string'],
    'value'        => ['$type' => 'object']
  ];
}