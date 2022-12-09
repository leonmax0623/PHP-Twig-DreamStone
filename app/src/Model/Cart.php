<?php

namespace DS\Model;

use MongoDB\BSON\ObjectId;
use DS\Core\Model\MongoModel;
use DS\Core\Utils;
use DS\Model\Cart\Cookie;

/**
 * Class Cart
 * @package App\Model
 */
class Cart extends MongoModel {
  private $availableGroups = ['products', 'diamonds', 'composite'];

  /**
   * @var string Collection name
   */
  protected $collection = 'cart';

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
    'coupon' => ['$type' => 'string'],
  ];

  public function getForUser($user = null)
  {
    $cart = (object)[
      'coupon' => '',
      'bankDiscount' => 0,
    ];
    foreach ($this->availableGroups as $availableGroup) {
      $cart->{$availableGroup} = [];
    }

    if ($user) {
      $cartDatabase = $this->findOne(['user_id' => $user->_id]);
      if ($cartDatabase) {
        $cart->coupon = $cartDatabase->coupon;
        $cart->bankDiscount = $cartDatabase->bankDiscount;
        $cart->paymentMethod = $cartDatabase->paymentMethod ?? [];
        $cart->billingInfo = $cartDatabase->billingInfo ?? [];
        foreach ($this->availableGroups as $availableGroup)
          if (!empty($cartDatabase->{$availableGroup}))
            $cart->{$availableGroup} = $cartDatabase->{$availableGroup};
      }
    } else {
      $cartCookie = (new Cookie())->get();
      foreach ($this->availableGroups as $availableGroup) {
        $cart->{$availableGroup} = $cartCookie->{$availableGroup};
      }
      $cart->coupon = $cartCookie->coupon;
    }

    foreach ($this->availableGroups as $availableGroup) {
      if (!empty($cart->{$availableGroup})) {
        $cart->{$availableGroup} = $this->getDetails($cart->{$availableGroup}, $availableGroup, $user);
      }
    }

    return $cart;
  }

  public function onLogin($userId)
  {
    $isChanged = false;
    $Cookie = new Cookie();
    $cart = (object)[
      'coupon' => '',
      'bankDiscount' => 0,
    ];
    $cartCookie = $Cookie->get();
    foreach ($this->availableGroups as $availableGroup) {
      $cart->{$availableGroup} = $cartCookie->{$availableGroup};
    }
    $cart->coupon = $cartCookie->coupon;

    $filter = ['user_id' => new ObjectId($userId)];
    $cartDatabase = $this->findOne($filter);
    if ($cartDatabase) {
      foreach ($this->availableGroups as $availableGroup) {
        if (!isset($cartDatabase->{$availableGroup}))
          $cartDatabase->{$availableGroup} = [];

        foreach ($cart->{$availableGroup} as $product) {
          if ($availableGroup !== 'composite') {
            $foundProduct = array_filter($cartDatabase->{$availableGroup}, function($p) use($product){
              return $p->_id === $product->_id;
            });
          }
          if (empty($foundProduct)) {
            $cartDatabase->{$availableGroup}[] = $product;
            $isChanged = true;
          } else {
            $key = array_keys($foundProduct)[0];
            if ($cartDatabase->{$availableGroup}[$key]->qty < $product->qty) {
              $cartDatabase->{$availableGroup}[$key] = $product;
              $isChanged = true;
            }
          }
        }
      }
      if ($cartDatabase->coupon) {
        $isChanged = true;
      } elseif ($cart->coupon) {
        $cartDatabase->coupon = $cart->coupon;
      }
      $this->updateOne($filter, ['$set' => $cartDatabase]);
    } else {
      $cartDatabase = $cart;
      $cartDatabase->user_id = new ObjectId($userId);
      $this->insertOne($cartDatabase);
    }

    $Cookie->flush();

    return $isChanged;
  }

  public function addForUser($product, $group, $user = null)
  {
    if ($user) {
      $filter = ['user_id' => $user->_id];
      $cartDatabase = $this->findOne($filter);
      if ($cartDatabase) {
        if (empty($cartDatabase->{$group})) {
          $cartDatabase->{$group} = [];
        }

        if ($group !== 'composite') {
          $added = false;
          foreach ($cartDatabase->{$group} as &$value) {
            if (Utils::isEqualProducts($value, $product)) {
              $added = true;
              if ($group === 'products') // can not be more than one the same diamond
                $value->qty++;
            }
          }
          if (!$added) {
            $product->qty = 1;
            $cartDatabase->{$group}[] = $product;
          }
        } else {
          $added = false;
          foreach ($cartDatabase->{$group} as &$value) {
            if (
              Utils::isEqualProducts($value->diamond, $product->diamond)
              && Utils::isEqualProducts($value->product, $product->product)
            ) {
              $added = true;
            }
          }
          if (!$added) {
            $cartDatabase->{$group}[] = $product;
          }
        }

        $this->updateOne($filter, ['$set' => [
          $group => $cartDatabase->{$group}
        ]]);
      } else {
        if ($group !== 'composite') {
          $product->qty = 1;
        }
        $cartDatabase = (object) [];
        $cartDatabase->user_id = $user->_id;
        $cartDatabase->coupon = '';
        $cartDatabase->bankDiscount = 0;
        $cartDatabase->{$group} = [$product];
        $this->insertOne($cartDatabase);
      }
    } else {
      (new Cookie())->addProduct($product, $group);
    }
  }

  public function updateForUser($product, $group, $user = null)
  {
    if ($user) {
      $filter = ['user_id' => $user->_id];
      $cartDatabase = $this->findOne($filter);
      if ($cartDatabase && !empty($cartDatabase->{$group})) {
        foreach ($cartDatabase->{$group} as $key => $value)
          if (Utils::isEqualProducts($value, $product))
            $cartDatabase->{$group}[$key]->qty = $product->qty;

        $this->updateOne($filter, ['$set' => [
          $group => $cartDatabase->{$group}
        ]]);
      }
    } else {
      (new Cookie())->updateProduct($product, $group);
    }
  }

  public function removeForUser($product, $group, $user = null)
  {
    if ($user) {
      $filter = ['user_id' => $user->_id];
      $cartDatabase = $this->findOne($filter);
      if ($cartDatabase && !empty($cartDatabase->{$group})) {
        $cartDatabase->{$group} = array_filter($cartDatabase->{$group}, function($value) use($product, $group){
          if ($group === 'composite') {
            return !Utils::isEqualProducts($value->diamond, $product->diamond)
              || !Utils::isEqualProducts($value->product, $product->product);
          } else {
            return !Utils::isEqualProducts($value, $product);
          }
        });
        $this->updateOne($filter, ['$set' => [
          $group => array_values($cartDatabase->{$group})
        ]]);
      }
    } else {
      (new Cookie())->removeProduct($product, $group);
    }
  }

  public function flush($user)
  {
    if ($user)
      $this->deleteWhere(['user_id' => $user->_id]);
  }

  public function updateCouponForUser($couponCode, $user = null)
  {
    if ($user) {
      $filter = ['user_id' => $user->_id];
      $cartDatabase = $this->findOne($filter);
      if ($cartDatabase)
        $this->updateOne($filter, ['$set' => ['coupon' => $couponCode]]);
    } else {
      (new Cookie())->updateCoupon($couponCode);
    }
  }

  public function deleteCouponForUser($user = null)
  {
    if ($user) {
      $filter = ['user_id' => $user->_id];
      $cartDatabase = $this->findOne($filter);
      if ($cartDatabase)
        $this->updateOne($filter, ['$set' => ['coupon' => '']]);
    } else {
      (new Cookie())->deleteCoupon();
    }
  }

  private function getDetails($cartProducts, $group, $user = null)
  {
    $result = [];
    if ($group === 'composite') {
      foreach ($cartProducts as $cartProduct) {
        $detailsProducts = $this->getDetails([$cartProduct->product], 'products', $user);
        $detailsDiamonds = $this->getDetails([$cartProduct->diamond], 'diamonds', $user);
        if (empty($detailsProducts) || empty($detailsDiamonds))
          $this->removeForUser($cartProduct, $group, $user);
        else
          $result[] = (object) ['product' => $detailsProducts[0], 'diamond' => $detailsDiamonds[0]];
      }
      return $result;
    }
    $Entity = $group === 'products' ? new Product($this->mongodb) : new Diamond($this->mongodb);
    $rawProducts = $Entity->find([
      '$or' => array_map(function($cartProduct){ return ['_id' => new ObjectID($cartProduct->_id)]; }, $cartProducts)
    ]);
    $Entity->stringifyValues($rawProducts);

    $databaseProducts = [];
    foreach ($rawProducts as $rawProduct) {
      if ($group === 'products') {
        $rawProduct->category = (new Category($this->mongodb))->getOneWhere(['_id' => $rawProduct->category_id]);
        $attributes = [];
        foreach ($rawProduct->attributes as $attribute) {
          $attributes[$attribute->name] = [];
          foreach ($attribute->values as $value) {
            $attributes[$attribute->name][$value->name] = $value;
          }
        }
        $rawProduct->tempAttributes = $attributes;
      }
      if ($group === 'diamonds' && !empty($rawProduct->shape_id)) {
        $rawProduct->shape = (new Shape($this->mongodb))->getOneWhere(['_id' => $rawProduct->shape_id]);
      }
      $databaseProducts[$rawProduct->_id] = $rawProduct;
    }

    foreach ($cartProducts as $cartProduct) {
      if (!isset($databaseProducts[$cartProduct->_id])) {
        $this->removeForUser($cartProduct, $group, $user);
        continue;
      }
      $product = clone $databaseProducts[$cartProduct->_id];
      if (!empty($cartProduct->qty)) {
        $product->qty = $cartProduct->qty;
      }
      if ($group === 'products') {
        $product->withAttributes = [];
        foreach((array)$cartProduct->withAttributes as $key => $value) {
          if (!isset($product->tempAttributes[$key][$value])) continue;
          $product->withAttributes[$key] = $value;
          $attr = $product->tempAttributes[$key][$value];
          $product->price += $attr->price;
          $product->images = $Entity->getImages($product);
        }
        unset($product->tempAttributes);
      }
      $result[] = $product;
    }

    return $result;
  }

  public function setPaymentMethodForUser($paymentMethod, $user)
  {
    $filter = ['user_id' => $user->_id];
    $cartDatabase = $this->findOne($filter);
    if ($cartDatabase) {
      $this->updateOne($filter, ['$set' => [
          'paymentMethod' => (string) $paymentMethod,
          'bankDiscount' => $paymentMethod == 'transfer' ? 1.5 : 0,
      ]]);
    }
  }

  public function setBillingInfoForUser($billingInfo, $user)
  {
    $filter = ['user_id' => $user->_id];
    $cartDatabase = $this->findOne($filter);
    if ($cartDatabase) {
      $this->updateOne($filter, ['$set' => [
          'billingInfo' => (array) $billingInfo,
      ]]);
    }
  }

}