<?php

namespace DS\Model;

/**
 * Class Composite
 * @package App\Model
 */
class Composite {
  private $cookieName = 'composite';

  private $cookie;

  public function __construct($mongo)
  {
    $this->mongodb = $mongo;

    try {
      $this->cookie = json_decode(filter_input(INPUT_COOKIE, $this->cookieName));
      if (!$this->cookie) $this->cookie = (object) [];
    } catch (\Exception $e) {
      $this->cookie = (object) [];
    }
  }

  public function getDetails($filter = ['product' => [], 'diamond' => []]) {
    $composite = (object) [];
    if (!empty($filter['product']['_id']) && !empty($filter['diamond']['_id'])) {
      $this->cookie->products = (object) $filter['product'];
      $this->cookie->diamonds = (object) $filter['diamond'];
      setcookie($this->cookieName, json_encode($this->cookie), 0, '/');
    }

    if (!empty($this->cookie->products->_id)) {
      $product = $this->getProductDetails($this->cookie->products);
      if ($product) {
        $composite->product = $product;
      }
    }

    if (!empty($this->cookie->diamonds->_id)) {
      $diamond = $this->getDiamondDetails($this->cookie->diamonds);
      if ($diamond) {
        $composite->diamond = $diamond;
      }
    }

    $composite->permalink = !empty($composite->product) && !empty($composite->diamond)
      ? $this->getPermalink($composite)
      : '/builder';

    return $composite;
  }

  public function getProductDetails($cookieProduct)
  {
    $Product = new Product($this->mongodb);
    $product = $Product->getOneWhere(['_id' => $cookieProduct->_id]);
    if ($product) {
      $Product->populate($product);
      $product->withAttributes = [];
      if (!empty($cookieProduct->withAttributes)) // set withAttributes
        $product->withAttributes = (array) $cookieProduct->withAttributes;
      $product->title = $Product->getTitle($product);
      $product->price = $Product->getPrice($product);
      $product->permalink = $Product->getPermalink($product);
      $product->images = $Product->getImages($product);
    }
    return $product;
  }

  public function getDiamondDetails($cookieDiamond)
  {
    $Diamond = new Diamond($this->mongodb);
    $diamond = $Diamond->getOneWhere(['_id' => $cookieDiamond->_id]);
    if ($diamond) {
      $Diamond->populate($diamond);
      $diamond->title = $Diamond->getTitle($diamond);
      $diamond->price = $Diamond->getPrice($diamond);
      $diamond->permalink = $Diamond->getPermalink($diamond);
    }
    return $diamond;
  }

  public function getPermalink(object $composite, string $origin = '')
  {
    $getVars = [
      'did=' . $composite->diamond->_id,
      'pid=' . $composite->product->_id,
    ];
    if (!empty($composite->product->withAttributes)) {
      $attr = [];
      foreach ($composite->product->withAttributes as $key => $value) {
        $attr[] = urlencode($key . '=' . $value);
      }
      $getVars[] = 'pwa=' . urlencode(join('&', $attr));
    }

    return $origin . '/builder?' . join('&', $getVars);
  }

  public function getShippingDetails(object $composite, int $timezone_offset = 0)
  {
    return (new Product($this->mongodb))->getShippingDetails($composite->product, $timezone_offset);
  }

  public function flush()
  {
    setcookie($this->cookieName, null, -1, '/');
  }

}