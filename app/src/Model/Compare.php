<?php
namespace DS\Model;

use mongodb\BSON\ObjectID;

/**
 * Class Compare
 */
class Compare
{
  private $mongodb;
  private $limit;
  private $expiration;

  private $availableGroups = ['products', 'diamonds'];
  private $name = 'compares';
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
      } catch (\Exception $e) {}
    }
  }

  public function get($group)
  {
    if (!in_array($group, $this->availableGroups))
      return [];

    $ids = $this->data->{$group};
    if (empty($ids))
      return [];

    $or = array_map(function($id){ return ['_id' => new ObjectId($id)]; }, $ids);

    switch ($group) {
      case 'products':
        $Product = new Product($this->mongodb);
        return array_map(function($product) use ($Product){
          $Product->populate($product);
          $product->permalink = $Product->getPermalink($product);

          return $product;
        }, $Product->allWhere(['$or' => $or]));

      case 'diamonds':
        $Diamond = new Diamond($this->mongodb);
        $diamonds = $Diamond->find(['$and' => [
          ['isEnabled' => true],
          ['priceInternal' => ['$exists' => true, '$gt' => 0]],
          ['$or' => $or],
        ]]);

        $Diamond->stringifyValues($diamonds);

        $vendors = (new Vendor($this->mongodb))->getVendors();
        foreach($vendors as $vendor) $vendorNames[$vendor->code] = $vendor;

        $diamonds = array_filter($diamonds, function ($diamond) use ($vendorNames) {
          $vendor = $vendorNames[strtolower($diamond->vendor)];
          return !(empty($vendor) ? false : $vendor->isEnabled == false) && $diamond->isEnabled;
        });

        if (count($this->data->diamonds) !== count($diamonds)) {
          $this->data->diamonds = array_map(function($diamond){ return $diamond->_id; }, $diamonds);
          setcookie($this->name, json_encode($this->data), time() + $this->expiration, '/');
        }

        return array_map(function($product) use($Diamond){
          $product->shape = (new Shape($this->mongodb))->getOneWhere(['_id' => $product->shape_id]);
          $product->color = (new Color($this->mongodb))->getOneWhere(['_id' => $product->color_id]);
          $product->clarity = (new Clarity($this->mongodb))->getOneWhere(['_id' => $product->clarity_id]);
          $product->cut = (new Cut($this->mongodb))->getOneWhere(['_id' => $product->cut_id]);
          $product->polish = (new Polish($this->mongodb))->getOneWhere(['_id' => $product->polish_id]);
          $product->symmetry = (new Symmetry($this->mongodb))->getOneWhere(['_id' => $product->symmetry_id]);
          if (!empty($product->flourence_id)) $product->flourence = (new Flourence($this->mongodb))->getOneWhere(['_id' => $product->flourence_id]);
          if (!empty($product->girdle_id)) $product->girdle = (new Girdle($this->mongodb))->getOneWhere(['_id' => $product->girdle_id]);
          if (!empty($product->culet_id)) $product->culet = (new Culet($this->mongodb))->getOneWhere(['_id' => $product->culet_id]);
          $product->permalink = $Diamond->getPermalink($product);
          $product->price = $Diamond->getPrice($product);

          return $product;
        }, $diamonds);
    }
    return [];
  }

  public function isCompare(string $group, string $id)
  {
    return in_array($group, $this->availableGroups) && in_array($id, $this->data->{$group});
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