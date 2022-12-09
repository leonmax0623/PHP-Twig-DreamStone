<?php
namespace DS\Model;

use DS\Core\Model\MongoModel;

/**
 * Class User
 * @package App\Model
 */
class Cut extends MongoModel
{
  /**
   * @var string Collection name
   */
  protected $collection = 'cut';

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'code'    => ['index' => 1]
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    'code'       => ['$type' => 'string'],
    'desc'        => ['$type' => 'string']
  ];
}