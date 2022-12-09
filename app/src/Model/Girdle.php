<?php
namespace DS\Model;

use DS\Core\Model\MongoModel;

/**
 * Class User
 * @package App\Model
 */
class Girdle extends MongoModel
{
  /**
   * @var string Collection name
   */
  protected $collection = 'girdle';

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
}

/*
 0 = "THK - VTK"
 1 = "MED - THK"
 2 = "MED - STK"
 3 = "THN - STK"
 4 = "MED"
 5 = "THN - MED"
 6 = "STK"
 7 = "VTN - STK"
 8 = "VTN - MED"
 9 = "STK - THK"
 10 = "ETN - STK"
 11 = "THN - THK"
 12 = "THN - VTK"
 13 = "VTN - THK"
 14 = "VTN - VTK"
 15 = "ETN - THK"
 16 = "STK - VTK"
 17 = "VTK"
 18 = "THK"
 19 = "MED - VTK"
 20 = "THN"
 21 = "ETN - MED"
 22 = "THK - ETK"
 23 = "VTK - ETK"
 24 = "STK - ETK"
 25 = "ETN - VTK"
 26 = "MED - ETK"
 27 = "VTN - ETK"
 28 = "THN - ETK"
 29 = "ETK"
 30 = "ETN - ETK"
*/