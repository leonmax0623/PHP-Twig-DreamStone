<?php
namespace DS\Model;

use DS\Core\Model\MongoModel;
use MongoDB\BSON\ObjectId;

/**
 * Class User
 * @package App\Model
 */
class FAQs extends MongoModel
{
  /**
   * @var array
   */
  public $search_fields = [
    'category',
    'questions.name',
    'questions.text',
  ];

  /**
   * @var string Collection name
   */
  protected $collection = 'faqs';

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'category'    => ['index' => 1, 'unique' => true]
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    'category'       => ['$type' => 'string'],
  ];
}