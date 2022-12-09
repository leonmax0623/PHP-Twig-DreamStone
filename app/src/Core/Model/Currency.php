<?php

namespace DS\Core\Model;

use DS\Model\Settings;

/**
 * Class Currency
 * @package App\Model
 */
class Currency extends MongoModel
{
  /**
   * @var \MongoDB\Database
   */
  protected $mongodb;

  public function __construct($mongo)
  {
    $this->mongodb = $mongo;
  }

  private function getDollar()
  {
    return (object) [
      'name' => 'dollar',
      'sign' => '$',
      'rate' => 1
    ];
  }

  private function getEuro()
  {
    return (object) [
      'name' => 'euro',
      'sign' => '€',
      'rate' => (float) (new Settings($this->mongodb))->getOneWhere(['slug' => 'exchange_rate'])->value
    ];
  }

  public function getDetails($currency = '')
  {
    switch ($currency) {
      case 'dollar':
        return $this->getDollar();
      case 'euro':
        return $this->getEuro();
      default:
        return null;
    }
  }

}