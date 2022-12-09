<?php

namespace DS\Model;

use DS\Core\Model\MongoModel;

/**
 * Class User
 * @package App\Model
 */
class User extends MongoModel
{

  /**
   * @var string Collection name
   */
  protected $collection = 'user';

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'email' => ['index' => 1, 'unique' => true],
    'password' => ['index' => 1],
  ];

  /**
   * @var array
   */
  public $search_fields = [
    'first_name',
    'last_name',
    'email',
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    'first_name' => ['$type' => 'string'],
    'last_name' => ['$type' => 'string'],
    'email' => ['$type' => 'string'],
    'password' => ['$type' => 'string'],
    'sex' => ['$type' => 'string'],
    'created' => ['$type' => 'timestamp'],
    'customer_id' => ['$type' => 'string'],
    'company' => ['$type' => 'string'],
    'phone' => ['$type' => 'string'],
    'address' => ['$type' => 'string'],
    'address2' => ['$type' => 'string'],
    'city' => ['$type' => 'string'],
    'state' => ['$type' => 'string'],
    'country' => ['$type' => 'string'],
    'zip' => ['$type' => 'string'],
  ];
}
