<?php
namespace DS\Model;

use DS\Core\Model\MongoModel;

/**
 * Class Content
 * @package App\Model
 */
class Content extends MongoModel
{
  /**
   * @var array
   */
  public $search_fields = [
    'name'
  ];

  /**
   * @var string Collection name
   */
  protected $collection = 'content';

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'name' => ['index' => 1],
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    'name' => ['$type' => 'string'],
    'items' => ['$type' => 'array']
  ];
}