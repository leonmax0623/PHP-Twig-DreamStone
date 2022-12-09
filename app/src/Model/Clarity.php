<?php
namespace DS\Model;

use DS\Core\Model\MongoModel;
use MongoDB\BSON\ObjectId;

/**
 * Class User
 * @package App\Model
 */
class Clarity extends MongoModel
{
  /**
   * @var string Collection name
   */
  protected $collection = 'clarity';

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

  public function getSimilar(object $clarity)
  {
    $clarities = $this->allWhere();
    $similar = [];
    foreach ($clarities as $i => $item) {
      if ($clarity->_id === $item->_id) {
        if (isset($clarities[$i - 1])) $similar[] = $clarities[$i - 1];
        $similar[] = $clarities[$i];
        if (isset($clarities[$i + 1])) $similar[] = $clarities[$i + 1];
      }
    }
    return $similar;
  }
}