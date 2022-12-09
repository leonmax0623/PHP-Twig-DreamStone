<?php
namespace DS\Model;

use DS\Core\Model\MongoModel;

/**
 * Class User
 * @package App\Model
 */
class Color extends MongoModel
{
  /**
   * @var string Collection name
   */
  protected $collection = 'color';

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

  public function getSimilar(object $color)
  {
    $colors = $this->allWhere();
    $similar = [];
    foreach ($colors as $i => $item) {
      if ($color->_id === $item->_id) {
        if (isset($colors[$i - 1])) $similar[] = $colors[$i - 1];
        $similar[] = $colors[$i];
        if (isset($colors[$i + 1])) $similar[] = $colors[$i + 1];
      }
    }
    return $similar;
  }
}