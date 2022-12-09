<?php
namespace DS\Model;

use DS\Core\Model\MongoModel;

/**
 * Class User
 * @package App\Model
 */
class StaticPages extends MongoModel
{
  /**
   * @var string Collection name
   */
  protected $collection = 'staticpages';

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'title'  => ['index' => 1, 'unique' => true],
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
      'title'       => ['$type' => 'string'],
      'url'        => ['$type' => 'string'],
      'content'        => ['$type' => 'string'],
      'description'        => ['$type' => 'string'],
      'keywords'        => ['$type' => 'string'],
    ];
}