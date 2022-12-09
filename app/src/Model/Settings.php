<?php
namespace DS\Model;

use DS\Core\Model\MongoModel;

/**
 * Class Settings
 * @package App\Model
 */
class Settings extends MongoModel
{
  /**
   * @var string Collection name
   */
  protected $collection = 'settings';

  public $search_fields = [
    'name',
  ];

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'slug'    => ['index' => 1, 'unique' => true]
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    'slug' => ['$type' => 'string'],
    'name' => ['$type' => 'string'],
    'value' => ['$type' => 'string'],
    'description' => ['$type' => 'string']
  ];
}