<?php

namespace DS\Model;

use DS\Core\Model\MongoModel;
use DS\Core\Utils;

/**
 * Class Admin
 * @package App\Model
 */
class Admin extends MongoModel {

  /**
   * @var array
   */
  public $search_fields = [
    'email'
  ];

  /**
   * @var string Collection name
   */
  protected $collection = 'admin';

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'email' => ['index' => 1, 'unique' => true],
    'password' => ['index' => 1 ],
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    'email' => ['$type' => 'string'],
    'password' => ['$type' => 'string'],
  ];

  /**
   * Update record in table based on passed array
   *
   * @param array $items
   * @param array $where
   * @return mixed
   * @throws \Exception
   */
  public function updateWhere(array $items, array $where)
  {
    if ($items && !empty($items['password']) && $items['password'] != '*****')
      $items['password'] = Utils::hashPassword($items['password']);

    return parent::updateWhere($items, $where);
  }

  /**
   * Create record in table based on passed array
   *
   * @param array $items
   * @param $queryParams
   * @return mixed
   * @throws \Exception
   */
  public function create($items, $queryParams)
  {
    if ($items && !empty($items['password']))
      $items['password'] = Utils::hashPassword($items['password']);

    return parent::create($items, $queryParams);
  }
}