<?php
namespace DS\Model;

use DS\Core\Model\MongoModel;

/**
 * Class User
 * @package App\Model
 */
class GemstoneColor extends MongoModel
{
  /**
   * @var array
   */
  public $search_fields = [
    'code', 'title', 'desc'
  ];

  /**
   * @var string Collection name
   */
  protected $collection = 'gemstonecolor';

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
    'desc'        => ['$type' => 'string'],
  ];
}