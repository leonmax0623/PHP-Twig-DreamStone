<?php

namespace DS\Model\Cart;

use DS\Core\Utils;

/**
 * Class Cookie
 * @package App\Model
 */
class Cookie {
  private $availableGroups = ['products', 'diamonds', 'composite'];
  private $name = 'cart';
  private $data;

  public function __construct()
  {
    $this->data = (object)[];
    foreach ($this->availableGroups as $availableGroup) {
      $this->data->{$availableGroup} = [];
    }
    $this->data->coupon = '';

    $cookie = filter_input(INPUT_COOKIE, $this->name);
    if ($cookie) {
      try {
        $json = json_decode($cookie);
        foreach ($this->availableGroups as $availableGroup) {
          if (!empty($json->{$availableGroup}) && is_array($json->{$availableGroup})) {
            foreach ($json->{$availableGroup} as $item) {
              switch ($availableGroup) {
                case 'products':
                  if (strlen($item->_id) === 24 && !empty($item->qty)) {
                    $item->qty = (int) $item->qty;
                    if (0 < $item->qty && $item->qty < 1000)
                      $this->data->{$availableGroup}[] = $item;
                  }
                  break;
                case 'diamonds':
                  if (strlen($item->_id) === 24) {
                    $this->data->{$availableGroup}[] = $item;
                  }
                  break;
                case 'composite':
                  if ($item->product && $item->diamond) {
                    $this->data->{$availableGroup}[] = $item;
                  }
                  break;
              }
            }
          }
        }
        if (!empty($json->coupon)) {
          $this->data->coupon = $json->coupon;
        }
      } catch (\Exception $e) {}
    }
  }

  private function persist()
  {
    setcookie($this->name, json_encode($this->data), 0, '/');
  }

  public function flush()
  {
    setcookie($this->name, null, -1, '/');
  }

  public function get($group = '')
  {
    return $group ? $this->data->{$group} : $this->data;
  }

  public function addProduct($product, $group)
  {
    switch ($group) {
      case 'products':
        $added = false;
        foreach ($this->data->{$group} as &$value) {
          if (Utils::isEqualProducts($value, $product)) {
            $added = true;
            $value->qty++;
          }
        }
        if (!$added) {
          $product->qty = 1;
          $this->data->{$group}[] = $product;
        }
        break;
      case 'diamonds':
        $added = false;
        foreach ($this->data->{$group} as &$value) {
          if (Utils::isEqualProducts($value, $product)) {
            $added = true;
          }
        }
        if (!$added) {
          $this->data->{$group}[] = $product;
        }
        break;
      case 'composite':
        $this->data->{$group}[] = $product;
        break;
    }
    $this->persist();
  }

  public function updateProduct($product, $group)
  {
    foreach ($this->data->{$group} as $key => $value)
      if (Utils::isEqualProducts($value, $product))
        $this->data->{$group}[$key]->qty = $product->qty;
    $this->persist();
  }

  public function removeProduct($product, $group)
  {
    $this->data->{$group} = array_filter($this->data->{$group}, function($value) use($product, $group){
      if ($group === 'composite') {
        return !Utils::isEqualProducts($value->diamond, $product->diamond)
          || !Utils::isEqualProducts($value->product, $product->product);
      } else {
        return !Utils::isEqualProducts($value, $product);
      }
    });
    $this->data->{$group} = array_values($this->data->{$group});
    $this->persist();
  }

  public function updateCoupon($couponCode)
  {
    $this->data->coupon = $couponCode;
    $this->persist();
  }

  public function deleteCoupon()
  {
    $this->data->coupon = '';
    $this->persist();
  }
}
