<?php

namespace DS\Model;

use DS\Core\Model\MongoModel;
use mongodb\BSON\ObjectID;

/**
 * Class User
 * @package App\Model
 */
class Favorite extends MongoModel {
  private $availableGroups = ['products', 'diamonds', 'composites'];
  private $cookieName = 'favorites';

  private $userId;
  private $cookie;

  /**
   * @var string Collection name
   */
  protected $collection = 'favorite';

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'user_id' => ['index' => 1],
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    'user_id' => ['$type' => 'objectId'],
    'group' => ['$type' => 'string'],
    'item' => ['$type' => 'object'],
  ];


  public function __construct($mongo, $userId = null)
  {
    $this->userId = $userId;
    $this->cookie = filter_input(INPUT_COOKIE, $this->cookieName);

    parent::__construct($mongo);
  }

  public function getCurrentFavorites() {
    return $this->userId ? $this->getDatabaseFavorites() : $this->getCookieFavorites();
  }

  private function getCookieFavorites() {
    $favorites = [];
    if (!$this->cookie)
      return $favorites;

    try {
      $json = json_decode($this->cookie);
      foreach ($this->availableGroups as $availableGroup) {
        if (empty($json->{$availableGroup}) || !is_array($json->{$availableGroup}))
          continue;

        foreach ($json->{$availableGroup} as $item) {
          $favorites[] = $this->renderCookieItem($availableGroup, $item);
        }
      }
    } catch (\Exception $e) {}

    return $favorites;
  }

  private function renderCookieFields($item) {
    $favorite = (object) [
      '_id' => $item->_id,
      'withAttributes' => empty($item->withAttributes) ? [] : (array) $item->withAttributes, // to sort
    ];
    ksort($favorite->withAttributes);
    $favorite->withAttributes = (object) $favorite->withAttributes;
    return $favorite;
  }

  private function renderCookieItem($group, $item) {
    return (object) [
      'group' => $group,
      'item' => $group === 'composites'
        ? (object) [
          'product' => $this->renderCookieFields($item->product),
          'diamond' => $this->renderCookieFields($item->diamond)
        ]
        : $this->renderCookieFields($item),
    ];
  }

  private function renderDatabaseFields($item) {
    $favorite = (object) [
      '_id' => new ObjectID($item->_id),
      'withAttributes' => empty($item->withAttributes) ? [] : (array) $item->withAttributes, // to sort
    ];
    ksort($favorite->withAttributes);
    $favorite->withAttributes = (object) $favorite->withAttributes;
    return $favorite;
  }

  private function renderDatabaseItem($group, $item) {
    return [
      'user_id' => new ObjectID($this->userId),
      'group' => $group,
      'item' => $group === 'composites'
        ? (object) [
          'product' => $this->renderDatabaseFields((object) $item->product),
          'diamond' => $this->renderDatabaseFields((object) $item->diamond)
        ]
        : $this->renderDatabaseFields($item),
    ];
  }

  private function getDatabaseFavorites() {
    return $this->allWhere(['user_id' => $this->userId]);
  }

  public function onLogin()
  {
    foreach ($this->getCookieFavorites() as $item) {
      $favorite = $this->renderDatabaseItem($item->group, $item->item);
      if (!$this->isExistWhere($favorite))
        $this->insertOne($favorite);
    }

    setcookie($this->cookieName, null, -1, '/');
  }

  public function isFavorite(string $group, object $item)
  {
    if ($this->userId) {
      return $this->isExistWhere($this->renderDatabaseItem($group, $item));
    }

    foreach ($this->getCookieFavorites() as $cookieFavorite) {
      if ($group !== $cookieFavorite->group)
        continue;

      $existItem = $cookieFavorite->item;
      $currentItem = $this->renderCookieItem($group, $item)->item;
      if ($group === 'composites') {
        if (
          $currentItem->product->_id === $existItem->product->_id
          && (array) $currentItem->product->withAttributes == (array) $existItem->product->withAttributes
          && $currentItem->diamond->_id === $existItem->diamond->_id
          && (array) $currentItem->diamond->withAttributes == (array) $existItem->diamond->withAttributes
        ) {
          return true;
        }
      } else {
        if (
          $currentItem->_id === $existItem->_id
          && (array) $currentItem->withAttributes == (array) $existItem->withAttributes
        ) {
          return true;
        }
      }
    }

    return false;
  }

  public function addItem(string $group, object $item)
  {
    if (!$this->userId)
      return '';

    $favorite = $this->renderDatabaseItem($group, $item);
    $result = $this->getOneWhere($favorite);
    if ($result)
      return $result->_id;

    $result = $this->insertOne($favorite);
    return $result->getInsertedId()->__toString();
  }

  public function deleteItem(string $group, object $item)
  {
    if ($this->userId) {
      $this->deleteWhere($this->renderDatabaseItem($group, $item));
    }
  }

}