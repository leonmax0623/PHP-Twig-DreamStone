<?php
namespace DS\Model;

use DS\Core\Model\MongoModel;

/**
 * Class User
 * @package App\Model
 */
class Education extends MongoModel
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
  protected $collection = 'education';

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'url'  => ['index' => 1],
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    'isEnabled' => ['$type' => 'bool'],
    'parent_id' => ['$type' => ['objectId', 'null']],
    'name' => ['$type' => 'string'],
    'url' => ['$type' => 'string'],
    'content' => ['$type' => 'string'],
  ];

  public function getTree($filter) {
    $education = $this->allWhere($filter);

    $parents = $children = [];
    foreach ($education as $item) { // parent
      if (!$item->parent_id) {
        $parents[$item->_id] = $item;
        $parents[$item->_id]->children = [];
      }
    }
    foreach ($education as $item) { // child
      if ($item->parent_id && isset($parents[$item->parent_id])) {
        $children[$item->_id] = $item;
        $children[$item->_id]->children = [];
      }
    }
    foreach ($education as $item) { // sub
      if ($item->parent_id && isset($children[$item->parent_id])) {
        $children[$item->parent_id]->children[] = $item;
      }
    }

    foreach ($children as $item) {
      $parents[$item->parent_id]->children[] = $item;
    }

    return array_values($parents);
  }
}