<?php

namespace DS\Model;

use DS\Core\Model\AbstractProduct;
use mongodb\BSON\ObjectID;
use mongodb\BSON\Decimal128;

/**
 * Class Product
 * @package App\Model
 */
class Product extends AbstractProduct
{
  /**
   * @var array
   */
  public $search_fields = [
    'sku',
    'title',
    'url',
    'description'
  ];

  public $videoFormats = ['mp4', 'mov', 'ogg', 'wmv', 'wma', 'webm', 'avi'];

  /**
   * @var string Product name
   */
  protected $collection = 'product';

  /**
   * @var array textIndex
   */
  protected $textIndex = [
    'title' => 'text',
    'sku' => 'text',
    'url' => 'text',
    'description' => 'text',
  ];

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'price' => ['index' => 1],
    'category_id' => ['index' => 1],
    'metal_id' => ['index' => 1],
    'jewelrystone_id' => ['index' => 1],
    'jewelrytype_id' => ['index' => 1],
    'jewelrytypestyle_id' => ['index' => 1],
    'birthstone_id' => ['index' => 1],
    'ringstyle_id' => ['index' => 1],
    'is_diamond' => ['index' => 1],
    'is_pearl' => ['index' => 1],
    'is_birthstone' => ['index' => 1],
    'order' => ['index' => 1],
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    'title' => ['$type' => 'string'],
    'sku' => ['$type' => 'string'],
    'purchase_price' => ['$type' => ['decimal', 'null']],
    'price' => ['$type' => 'decimal'],
    'retail_price' => ['$type' => ['decimal', 'null']],
    'url' => ['$type' => 'string'],
    'description' => ['$type' => 'string'],
    'category_id' => ['$type' => ['objectId']],
    'metal_id' => ['$type' => ['objectId', 'null']],
    'jewelrystone_id' => ['$type' => ['objectId', 'null']],
    'jewelrytype_id' => ['$type' => ['objectId', 'null']],
    'jewelrytypestyle_id' => ['$type' => 'array'],
    'jewelrypearl_id' => ['$type' => ['objectId', 'null']],
    'birthstone_id' => ['$type' => ['objectId', 'null']],
    'ringstyle_id' => ['$type' => ['objectId', 'null']],
    'is_diamond' => ['$type' => 'bool'],
    'is_pearl' => ['$type' => 'bool'],
    'is_birthstone' => ['$type' => 'bool'],
    'template' => ['$type' => ['string', 'null']],
    'attributes' => ['$type' => ['array']],
    'images' => ['$type' => 'array'], // type: img/html
    'customer_images' => ['$type' => 'array'], // type: img/html
    'information' => ['$type' => ['array', 'null']],
    'order' => ['$type' => ['decimal', 'null']],
    'shipping_time' => ['$type' => ['int', 'null']],
    'banner_show' => ['$type' => 'bool'],
    'banner_image' => ['$type' => 'string'],
    'banner_html' => ['$type' => 'string'],
    'is_for_builder' => ['$type' => 'bool'],
    'builder_compatible' => ['$type' => ['array', 'null']],
  ];

  public function getAttributes(object $product)
  {
    return array_map(function ($attribute) {
      $attribute->values = array_values(array_filter($attribute->values, function ($value) {
        return $value->isEnabled;
      }));
    }, $product->attributes);
  }

  public function getSelectedAttributes(object $product, $urlParams = [])
  {
    $withAttributes = [];
    foreach ($product->attributes as &$attribute) {
      if (isset($urlParams[$attribute->name]))
        foreach ($attribute->values as &$value)
          if ($urlParams[$attribute->name] === $value->name)
            $withAttributes[$attribute->name] = $value->name;
      if (!isset($withAttributes[$attribute->name]))
        foreach ($attribute->values as &$value)
          if (!empty($value->isDefault))
            $withAttributes[$attribute->name] = $value->name;
    }
    return $withAttributes;
  }

  public function getTitle(object $product)
  {
    $metals = [];
    foreach ($product->attributes as $attribute)
      if (strtolower($attribute->name) === 'metal')
        $metals = $attribute->values;

    $attributeName = '';
    foreach ($metals as $metal)
      if (!empty($metal->isDefault))
        $attributeName = $metal->name;

    if (!empty($product->withAttributes))
      foreach ($product->withAttributes as $key => $value)
        if (strtolower($key) === 'metal')
          $attributeName = $value;

    return strtoupper($product->title) . ($attributeName ? ' in ' . $attributeName :  '');
  }

  public function getPermalink(object $product, string $origin = '')
  {
    $url = $product->type === 'jewelry'
      ? '/jewelry/' . $product->category->url // NOTE: category is required, so it exists
      : '/' . $product->type . '/item';

    $params = [];
    if (!empty($product->withAttributes))
      foreach ($product->withAttributes as $name => $value)
        $params[] = urlencode($name) . '=' . urldecode($value);

    return $origin . $url . '/' . $product->url .
      (empty($params) ? '' : '?' . join('&', $params));
  }

  public function getPrice(object $product)
  {
    $price = $product->price;

    foreach ($product->attributes as $attribute)
      if (isset($product->withAttributes[$attribute->name]))
        foreach ($attribute->values as $value)
          if ($value->name === $product->withAttributes[$attribute->name])
            $price += $value->price;

    return $price;
  }

  public function getRetailPrice(object $product)
  {
    $price = $product->retail_price;
    if (!$price)
      return $price;

    foreach ($product->attributes as $attribute)
      if (isset($product->withAttributes[$attribute->name]))
        foreach ($attribute->values as $value)
          if ($value->name === $product->withAttributes[$attribute->name])
            $price += $value->price;

    return $price;
  }

  public function getVideos(object $product)
  {
    return array_values(array_filter($product->images, function ($image) {
      return in_array($this->getExtension($image->value), $this->videoFormats);
    }));
  }

  public function getImages(object $product, array $shapeIds = [], $forcedAll = false)
  {
    $defaultImages = [];
    foreach ($product->images as $image) {
      if (in_array($this->getExtension($image->value), $this->videoFormats)) // skip videos
        continue;

      if (empty($image->shapes)) { // accept, if no shapes
        $defaultImages[] = $image;
        continue;
      }

      if (empty($product->builder_show_shapes)) // skip if shapes are turned off for current product
        continue;

      if ($forcedAll) { // accept, if forced
        $defaultImages[] = $image;
        continue;
      }

      if (empty($shapeIds)) // skip if shape is not requested
        continue;

      foreach ($image->shapes as $shape)
        if (in_array($shape->_id, $shapeIds)) { // accept if shape id is exist in array of available ids
          array_unshift($defaultImages, $image);
          break;
        }
    }

    if (empty($product->withAttributes))
      return $defaultImages;

    $includeDefault = false;
    $valueImages = [];
    foreach ($product->attributes as &$attribute) {
      if (!isset($product->withAttributes[$attribute->name])) continue;
      if ($attribute->includeDefaultImages) $includeDefault = true;
      foreach ($attribute->values as &$value) {
        if ($product->withAttributes[$attribute->name] !== $value->name)
          continue;
        if (!empty($value->includeDefaultImages))
          $includeDefault = true;
        foreach ($value->images as $image) {
          if (empty($image->shapes)) {
            $valueImages[] = $image;
          } else {
            if (empty($product->builder_show_shapes))
              continue;
            if ($forcedAll) {
              $valueImages[] = $image;
              continue;
            }
            if (empty($shapeIds))
              continue;
            foreach ($image->shapes as $shape)
              if (in_array($shape->_id, $shapeIds)) {
                array_unshift($valueImages, $image);
                break;
              }
          }
        }
      }
    }

    return $includeDefault ? array_merge($valueImages, $defaultImages) : $valueImages;
  }

  public function populate(object &$product)
  {
    $product->type = $product->jewelrytype_id
      ? (new JewelryType($this->mongodb))->findById($product->jewelrytype_id)->code
      : 'jewelry'; // NOTE: impossible, but we are processing

    if (!empty($product->category_id))
      $product->category = (new Category($this->mongodb))->getOneWhere(['_id' => $product->category_id]);

    if ($product->type === 'engagement-rings' && !empty($product->ringstyle_id))
      $product->ringstyle = (new RingStyle($this->mongodb))->getOneWhere(['_id' => $product->ringstyle_id]);

    foreach ($product->attributes as &$attribute) {
      $attribute->values = array_values(array_filter($attribute->values, function ($value) {
        return $value->isEnabled;
      }));
    }

    $Matching = new Matching($this->mongodb);

    $matchingItems = $Matching->getOneWhere(["items" => ['$elemMatch' => ['$eq' => $product->_id]]]);

    $matchingProducts = [];
    if (!empty($matchingItems)) {
      foreach ($matchingItems->items as $matchingItem) {
        if ($matchingItem != $product->_id) {
          $matchingProduct = $this->getOneWhere(['_id' => $matchingItem]);
          if ($matchingProduct) {
            $matchingProduct->type = $matchingProduct->jewelrytype_id
              ? (new JewelryType($this->mongodb))->findById($matchingProduct->jewelrytype_id)->code
              : 'jewelry'; // NOTE: impossible, but we are processing

            if (!empty($matchingProduct->category_id))
              $matchingProduct->category = (new Category($this->mongodb))->getOneWhere(['_id' => $matchingProduct->category_id]);

            if ($matchingProduct->type === 'engagement-rings' && !empty($matchingProduct->ringstyle_id))
              $matchingProduct->ringstyle = (new RingStyle($this->mongodb))->getOneWhere(['_id' => $matchingProduct->ringstyle_id]);

            foreach ($matchingProduct->attributes as &$attribute) {
              $attribute->values = array_values(array_filter($attribute->values, function ($value) {
                return $value->isEnabled;
              }));
            }
            $matchingProduct->category = (new Category($this->mongodb))->findById($matchingProduct->category_id);
            $matchingProduct->jewelryType = (new JewelryType($this->mongodb))->findById($matchingProduct->jewelrytype_id);
            $matchingProduct->permalink = $this->getPermalink($matchingProduct);
            $matchingProducts[] = $matchingProduct;
          }
        }
      }

      if (!empty($matchingProducts)) {
        $product->matchingItems = $matchingItems->items;
        $product->matchingProducts = $matchingProducts;
      }
    } else {
      $product->matchingItems = [];
      $product->matchingProducts = [];
    }
  }

  /**
   * Create record in table based on passed array
   *
   * @param $items
   * @param $queryParams
   * @return mixed
   * @throws \Exception
   */
  public function create($items, $queryParams)
  {
    if (!$items || !is_array($items))
      throw new \RuntimeException('parameter "$items" should be array');

    if (empty($items['jewelrytype_id']))
      $items['jewelrytype_id'] = (new JewelryType($this->mongodb))->getGeneralTypeId();

    $items = $this->prepare($items);
    $items['url'] = $this->generateUniqueUrl($items['url']);

    foreach ([
      'metal_id', 'jewelrystone_id', 'jewelrypearl_id', 'birthstone_id', 'ringstyle_id',
      'purchase_price', 'retail_price', 'template', 'information', 'builder_compatible',
    ] as $i) if (empty($items[$i])) $items[$i] = null;

    foreach (['banner_image', 'banner_html'] as $i)
      if (empty($items[$i])) $items[$i] = '';

    foreach (['is_for_builder', 'is_diamond', 'is_pearl', 'is_birthstone', 'banner_show'] as $i)
      if (empty($items[$i])) $items[$i] = false;

    $items['shipping_time'] = isset($items['shipping_time']) ? (int)$items['shipping_time'] : null;

    (new Category($this->mongodb))->updateWhere(
      ['items' => $this->mongoCol->count(['category_id' => $items['category_id']]) + 1],
      ['_id' => $items['category_id']]
    );

    return $this->insertOne($items)->getInsertedId()->__toString();
  }

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
    $items = $this->prepare($items);

    $product = $this->findById($where['_id']);
    if (
      isset($items['category_id']) &&
      $product->category_id->__toString() !== $items['category_id']->__toString()
    ) {
      (new Category($this->mongodb))->updateWhere( // decrement old
        ['items' => $this->mongoCol->count(['category_id' => $product->category_id]) - 1],
        ['_id' => $product->category_id]
      );
      (new Category($this->mongodb))->updateWhere( // increment new
        ['items' => $this->mongoCol->count(['category_id' => $items['category_id']]) + 1],
        ['_id' => $items['category_id']]
      );
    }

    if (isset($items['url']) && $items['url'] !== $product->url) {
      $items['url'] = $this->generateUniqueUrl($items['url']);
    }

    // TODO: remove from disk removed images

    if (isset($items['shipping_time'])) {
      $items['shipping_time'] = $items['shipping_time'] === '' ? null : (int)$items['shipping_time'];
    }

    if (empty($items))
      return 0;

    $r = $this->updateMany(['_id' => new ObjectId($where['_id'])], ['$set' => $items]);

    return $r->getModifiedCount();
  }

  /**
   * Prepare items to insert into database
   *
   * @param array $items
   * @return array $items
   */
  private function prepare(array $items)
  {
    if (!$items || !is_array($items))
      return $items;

    if (!empty($items['jewelrytypestyle_id']))
      $items['jewelrytypestyle_id'] = array_map(function ($item) {
        return empty($item) ? null : new ObjectId($item);
      }, $items['jewelrytypestyle_id']);

    foreach ([
      'category_id', 'metal_id', 'jewelrystone_id', 'jewelrytype_id',
      'jewelrypearl_id', 'birthstone_id', 'ringstyle_id',
    ] as $i)
      if (isset($items[$i])) $items[$i] = $items[$i] ? new ObjectId($items[$i]) : null;

    foreach (['purchase_price', 'price', 'retail_price', 'order'] as $i)
      if (isset($items[$i])) $items[$i] = $items[$i] ? new Decimal128($items[$i]) : null;

    foreach (['template'] as $i) // title, sku, url, description - skipped because required
      if (isset($items[$i]) && empty($items[$i])) $items[$i] = null;

    foreach (['information', 'images'] as $i)
      if (isset($items[$i]) && empty($items[$i])) $items[$i] = [];

    foreach (['banner_image', 'banner_html'] as $i)
      if (isset($items[$i]) && empty($items[$i])) $items[$i] = '';

    foreach (['is_for_builder', 'is_diamond', 'is_pearl', 'is_birthstone', 'banner_show'] as $i)
      if (isset($items[$i])) $items[$i] = (bool) $items[$i];

    if (!empty($items['attributes']))
      $items['attributes'] = $this->prepareAttributes($items['attributes']);

    if (!empty($items['builder_compatible']))
      $items['builder_compatible'] = array_map(function ($shape) {
        return (object) [
          'shape_id' => new ObjectID($shape['shape_id']),
          'weight_min' => new Decimal128($shape['weight_min']),
          'weight_max' => new Decimal128($shape['weight_max']),
        ];
      }, $items['builder_compatible']);

    return $items;
  }

  private function prepareAttributes(array $attributes)
  {
    foreach ($attributes as &$attribute) {
      if (is_array($attribute)) {
        $attribute['_id'] =  new ObjectId($attribute['_id']);
        foreach ($attribute['values'] as &$value) {
          $value['_id'] =  new ObjectId($value['_id']);
          $value['price'] =  new Decimal128($value['price']);
        }
      } else {
        $attribute->_id =  new ObjectId($attribute->_id);
        foreach ($attribute->values as &$value) {
          $value->_id =  new ObjectId($value->_id);
          $value->price =  new Decimal128($value->price);
        }
      }
    }
    return $attributes;
  }

  /**
   * @param $value
   * @return string
   */
  private function generateUniqueUrl($value)
  {
    $url = $value;
    $Product = $this->findOne(['url' => $url]);
    $i = 1;
    while (!empty($Product)) {
      $url = $value . $i++;
      $Product = $this->findOne(['url' => $url]);
    }
    return $url;
  }


  /**
   * Delete record based on passed Where array
   *
   * @param array $where
   * @return mixed
   * @throws \Exception
   */
  public function deleteWhere(array $where)
  {
    if (isset($where['_id']) && !$where['_id'] instanceof ObjectId)
      $where['_id'] = new ObjectId($where['_id']);

    $products = $this->find($where);
    if (empty($products)) return null;

    $categoryIds = [];
    $files = [];
    foreach ($products as $product) {
      $files = array_merge($files, $product->images);
      if (!in_array($product->category_id, $categoryIds))
        $categoryIds[] = $product->category_id;
    }
    $res = parent::deleteWhere($where);

    foreach ($categoryIds as $categoryId) (new Category($this->mongodb))->updateWhere(
      ['items' => $this->mongoCol->count(['category_id' => $categoryId])],
      ['_id' => $categoryId]
    );

    foreach ($files as $webpath) {
      // TODO: delete file on server
    }

    return $res;
  }

  /**
   * Update record in table based on passed array
   *
   * @param array $items
   * @param string $_id
   * @return mixed
   * @throws \Exception
   */
  public function duplicate($_id)
  {
    $Product = $this->findById($_id);
    if (empty($Product)) return null;

    (new Category($this->mongodb))->updateWhere(
      ['items' => $this->mongoCol->count(['category_id' => $Product->category_id]) + 1],
      ['_id' => $Product->category_id]
    );

    if (count($Product->images)) {
      // TODO: copy files
    }

    $Product->_id = new ObjectId();
    $Product->url = $this->generateUniqueUrl($Product->url);

    return $this->insertOne($Product)->getInsertedId();
  }

  /**
   * Get product's meta for social sharing
   *
   * @param object $product
   * @param string $origin
   * @return array
   */
  public function getMeta(object $product, string $origin)
  {
    $metaImage = '';
    foreach ($product->images as $image) {
      if ($image->type === 'img') {
        $metaImage = $origin . $image->value;
        break;
      }
    }
    return [
      'title' => $product->title,
      'url' => $this->getPermalink($product, $origin),
      'description' => $product->description,
      'image' => $metaImage,
      'creator' => 'Dream Stone',
      'created' => date("Y-m-d H:i:s"),
      'updated' => date("Y-m-d H:i:s"),
    ];
  }

  /**
   * Get similar products
   *
   * @param object $product
   * @param int $limit
   * @return array
   * @throws \Exception
   */
  public function getSimilar(object $product, int $limit = 10)
  {
    $products = $this->aggregate([
      ['$match' => [
        '_id' => ['$ne' => new ObjectId($product->_id)],
        'jewelrytype_id' => new ObjectId($product->jewelrytype_id),
      ]],
      ['$sample' => ['size' => $limit]],
    ]);

    return array_map(function ($product) {
      $this->populate($product);
      $product->permalink = $this->getPermalink($product);
      return $product;
    }, $products);
  }

  private function getExtension($fileName)
  {
    return mb_strtolower(substr(strrchr($fileName, '.'), 1));
  }

  public function getToExport($search = [], $origin = '')
  {
    $fields = [
      '_id' => 'Id',
      'title' => 'Title',
      'sku' => 'SKU',
      'purchase_price' => 'Purchase Price',
      'price' => 'Price',
      'retail_price' => 'Retail Price',
      'url' => 'URL', // NOTE: get details
      'description' => 'Description',
      'category_id' => 'Category', // get value by id
      'metal_id' => 'Metal', // get value by id
      'jewelrystone_id' => 'Jewelrystone', // get value by id
      'jewelrytype_id' => 'Jewelrytype', // get value by id
      'jewelrytypestyle_id' => 'Jewelrytypestyle', // get value by id
      'jewelrypearl_id' => 'Jewelrypearl', // get value by id
      'birthstone_id' => 'Birthstone', // get value by id
      'ringstyle_id' => 'Ringstyle', // get value by id
      'is_diamond' => 'is Diamond', // render yes/no
      'is_pearl' => 'is Pearl', // render yes/no
      'is_birthstone' => 'is Birthstone', // render yes/no
      'template' => 'Template',
      'attributes' => 'Attributes',
      'images' => 'Images', // NOTE: get details
      'customer_images' => 'Customer Images', // NOTE: get details
      'information' => 'Information',
      'shipping_time' => 'Shipping Time',
      'banner_show' => 'Show Banner', // render yes/no
      'banner_image' => 'Banner Image', // NOTE: get details
      'banner_html' => 'Banner HTML',
      'is_for_builder' => 'is for Builder', // render yes/no
      'builder_compatible' => 'builder compatible',
    ];

    $ringTypeId = '';
    $names = [];
    foreach ((new Category($this->mongodb))->find() as $category)
      $names['category_id'][$category->_id->__toString()] = $category->title;
    foreach ((new Metal($this->mongodb))->find() as $metal)
      $names['metal_id'][$metal->_id->__toString()] = $metal->code;
    foreach ((new JewelryStones($this->mongodb))->find() as $jewelrystone)
      $names['jewelrystone_id'][$jewelrystone->_id->__toString()] = $jewelrystone->title;
    foreach ((new JewelryType($this->mongodb))->find() as $jewelrytype) {
      $names['jewelrytype_id'][$jewelrytype->_id->__toString()] = $jewelrytype->code;
      if ($jewelrytype->code === 'rings') $ringTypeId = $jewelrytype->_id->__toString();
    }
    foreach ((new JewelryTypeStyle($this->mongodb))->find() as $jewelrytypestyle)
      $names['jewelrytypestyle_id'][$jewelrytypestyle->_id->__toString()] = $jewelrytypestyle->title;
    foreach ((new JewelryPearl($this->mongodb))->find() as $jewelrypearl)
      $names['jewelrypearl_id'][$jewelrypearl->_id->__toString()] = $jewelrypearl->title;
    foreach ((new BirthStone($this->mongodb))->find() as $birthstone)
      $names['birthstone_id'][$birthstone->_id->__toString()] = $birthstone->title;
    foreach ((new RingStyle($this->mongodb))->find() as $ringstyle)
      $names['ringstyle_id'][$ringstyle->_id->__toString()] = $ringstyle->code;

    $list = [array_values($fields)];
    foreach ($this->allWhere($search) as $i => &$row) {
      $listItem = [];
      foreach (array_keys($fields) as &$key) {
        if (empty($row->$key)) {
          continue;
        }
        $cell = $row->$key;
        if ($key === 'url') { // render url
          $listItem[] = $origin . ($row->jewelrytype_id === $ringTypeId ? '/engagement-rings/item' : '/jewelry/all') . '/' . $cell;
          continue;
        }
        if (in_array($key, ['is_diamond', 'is_pearl', 'is_birthstone', 'banner_show', 'is_for_builder'])) {
          $listItem[] = empty($cell) ? 'no' : 'yes'; // render yes/no
          continue;
        }
        if (in_array($key, [
          'category_id', 'metal_id', 'jewelrystone_id', 'jewelrytype_id', 'jewelrytypestyle_id', 'jewelrypearl_id',
          'birthstone_id', 'ringstyle_id'
        ])) {
          $listItem[] = $names[$key][is_array($cell) ? json_encode($cell) : $cell] ?? '';
          continue;
        }
        if (in_array($key, ['images', 'customer_images'])) {
          $images = [];
          foreach ($cell as $image)
            if ($image->type === 'img')
              $images[] = $origin . $image->value;
          $listItem[] = join("\n", $images);
          continue;
        }
        if ($key === 'attributes') {
          $listItem[] = json_encode(array_map(function ($attribute) use ($origin) {
            unset($attribute->_id);
            $attribute->values = array_map(function ($value) use ($origin) {
              unset($value->_id);
              $images = [];
              foreach ($value->images as $image)
                if ($image->type === 'img')
                  $images[] = $origin . $image->value;
              $value->images = join("\n", $images);
              return $value;
            }, $attribute->values);
            return $attribute;
          }, $cell));
          continue;
        }
        if (is_array($cell)) { // information
          $listItem[] = json_encode($cell);
          continue;
        }
        if ($key === 'banner_image') {
          $listItem[] = $cell ? ($origin . $cell) : '';
          continue;
        }
        $listItem[] = $cell;
      }
      $list[] = $listItem;
    }
    return $list;
  }
}
