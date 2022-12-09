<?php

namespace DS\Model;

use mongodb\BSON\ObjectID;

/**
 * Class Viewed
 */
class Viewed
{
  private $mongodb;
  private $limit;
  private $expiration;

  private $availableGroups = ['products', 'diamonds'];
  private $name = 'viewed';
  private $data;

  public function __construct($mongodb, int $limit = 10, int $expiration = 86400)
  {
    $this->mongodb = $mongodb;
    $this->limit = $limit;
    $this->expiration = $expiration;

    $this->data = (object)[];
    foreach ($this->availableGroups as $availableGroup) {
      $this->data->{$availableGroup} = [];
    }

    $cookie = filter_input(INPUT_COOKIE, $this->name);
    if ($cookie) {
      try {
        $json = json_decode($cookie);
        foreach ($this->availableGroups as $availableGroup) {
          if (!empty($json->{$availableGroup}) && is_array($json->{$availableGroup})) {
            foreach ($json->{$availableGroup} as $item) {
              if (strlen($item) === 24)
                $this->data->{$availableGroup}[] = $item;
            }
          }
        }
      } catch (\Exception $e) {
      }
    }
  }

  public function add($group, $_id)
  {
    if (!in_array($group, $this->availableGroups))
      return;

    if ($_id instanceof ObjectID) {
      $_id = $_id->__toString();
    }

    $items = $this->data->{$group};
    if (in_array($_id, $items)) {
      $items = array_values(array_diff($items, [$_id]));
    }
    $items[] = $_id;

    if (count($items) > $this->limit) {
      $items = array_slice($items, -1 * $this->limit);
    }
    $this->data->{$group} = $items;

    setcookie($this->name, json_encode($this->data), time() + $this->expiration, '/');
  }

  public function get($group, $except_id = '')
  {
    if (!in_array($group, $this->availableGroups))
      return [];

    if ($except_id) {
      if ($except_id instanceof ObjectID) {
        $except_id = $except_id->__toString();
      }
      $ids = in_array($except_id, $this->data->{$group})
        ? array_values(array_diff($this->data->{$group}, [$except_id]))
        : $this->data->{$group};
    } else {
      $ids = $this->data->{$group};
    }

    if (empty($ids)) {
      return [];
    }

    $or = array_map(function ($id) {
      return ['_id' => new ObjectId($id)];
    }, $ids);

    switch ($group) {
      case 'products':
        $Product = new Product($this->mongodb);
        return array_map(function ($product) use ($Product) {
          $Product->populate($product);
          $product->withAttributes = $Product->getSelectedAttributes($product);
          $product->images = $Product->getImages($product);
          $product->permalink = $Product->getPermalink($product);
          return $product;
        }, $Product->allWhere(['$or' => $or]));

      case 'diamonds':
        $Diamond = new Diamond($this->mongodb);
        $diamonds = $Diamond->allWhere(['$or' => $or]);

        $vendors = (new Vendor($this->mongodb))->getVendors();
        foreach ($vendors as $vendor) $vendorNames[$vendor->code] = $vendor;

        $diamonds = array_filter($diamonds, function ($diamond) use ($vendorNames) {
          $diamond->vendor = $diamond->vendor ?? null;
          $vendor = $vendorNames[strtolower($diamond->vendor)] ?? null;
          return !empty($diamond->priceInternal)
            && !(empty($vendor) ? false : $vendor->isEnabled == false)
            && $diamond->isEnabled;
        });

        $unset = false;
        foreach ($diamonds as $key => $diamond) {
          $vendor = $vendorNames[strtolower($diamond->vendor)] ?? null;
          if ((empty($vendor) ? false : $vendor->isEnabled == false) || !$diamond->isEnabled) {
            $this->data->diamonds = array_diff($this->data->diamonds, [$diamond->_id]);
            unset($diamonds[$key]);
            $unset = true;
          } else {
            $diamond->vendorEnabled = true;
          }
        }

        if ($unset) {
          $this->data->diamonds = array_values($this->data->diamonds);
          $diamonds = array_values($diamonds);
          setcookie($this->name, json_encode($this->data), time() + $this->expiration, '/');
        }

        return array_map(function ($product) use ($Diamond) {
          if (!empty($product->shape_id))
            $product->shape = (new Shape($this->mongodb))->getOneWhere(['_id' => $product->shape_id]);
          $product->permalink = $Diamond->getPermalink($product);
          $product->price = $Diamond->getPrice($product);

          return $product;
        }, $diamonds);
    }
    return [];
  }

  public function count($group = '')
  {
    if ($group && !in_array($group, $this->availableGroups))
      return 0;

    $overallCount = 0;
    foreach ($this->availableGroups as $availableGroup) {
      $count = count($this->data->{$availableGroup});
      if ($group === $availableGroup)
        return $count;
      $overallCount += $count;
    }
    return $overallCount;
  }
}
