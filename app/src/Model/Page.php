<?php
namespace DS\Model;

use DS\Core\Model\MongoModel;

/**
 * Class User
 * @package App\Model
 */
class Page extends MongoModel
{
  /**
   * @var string Collection name
   */
  protected $collection = 'page';

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'url'  => ['index' => 1, 'unique' => true],
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    'isEnabled' => ['$type' => 'bool'],
    'title' => ['$type' => 'string'],
    'description' => ['$type' => 'string'],
    'keywords' => ['$type' => 'string'],
    'url' => ['$type' => 'string'],
    'content' => ['$type' => 'string'],
    'images' => ['$type' => 'array'],
  ];
}