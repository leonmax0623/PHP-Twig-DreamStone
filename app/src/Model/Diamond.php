<?php

namespace DS\Model;

use DS\Core\Model\AbstractProduct;
use MongoDB\BSON\Decimal128;
use mongodb\BSON\ObjectID;

/**
 * Class User
 * @package App\Model
 */
class Diamond extends AbstractProduct
{

  /**
   * @var string Collection name
   */
  protected $collection = 'diamond';

  public $search_fields = [
    'certificateNumber',
    'stockNumber',
  ];

  /**
   * @var array textIndex
   */
  protected $textIndex = [
    'certificateNumber' => 'text',
    'stockNumber' => 'text',
  ];

  /**
   * @var array Indexes
   */
  protected $indexes = [
    'certificateNumber' => ['index' => 1, 'unique' => true],
    'updated' => ['index' => 1],
    'shape' => ['index' => 1],
    'color' => ['index' => 1],
    'clarity' => ['index' => 1],
    'cut' => ['index' => 1],
    'polish' => ['index' => 1],
    'symmetry' => ['index' => 1],
    'flourence' => ['index' => 1],
    'girdle' => ['index' => 1],
    'culet' => ['index' => 1],
  ];

  /**
   * @var array Required fields
   */
  protected $validator = [
    '$jsonSchema' => [
      'bsonType' => 'object',
      'required' => ['certificateNumber'],
      'properties' => [
        'isEnabled' => ['bsonType' => 'bool'],
        'isNatural' => ['bsonType' => 'bool'],
        'isChangedManually' => ['bsonType' => 'bool'],
        'shape_id' => ['bsonType' => 'objectId'], // required
        'color_id' => ['bsonType' => 'objectId'], // required
        'clarity_id' => ['bsonType' => 'objectId'], // required
        'cut_id' => ['bsonType' => 'objectId'], // required
        'polish_id' => ['bsonType' => 'objectId'], // required
        'symmetry_id' => ['bsonType' => 'objectId'], // required
        'flourence_id' => ['bsonType' => ['objectId', 'null']], // optional
        'girdle_id' => ['bsonType' => ['objectId', 'null']], // optional
        'culet_id' => ['bsonType' => ['objectId', 'null']], // optional
        'fancycolor_id' => ['bsonType' => ['objectId', 'null']], // optional
        'seller' => ['bsonType' => 'string'],
        'weight' => ['bsonType' => 'decimal'],
        'measurements' => ['bsonType' => 'string'],
        'ratio' => ['bsonType' => ['decimal', 'null']], // optional
        'lab' => ['bsonType' => 'string'], // required
        'stockNumber' => ['bsonType' => 'string'],
        'priceExternal' => ['bsonType' => 'decimal'], // required
        'priceInternal' => ['bsonType' => 'decimal'],
        'table' => ['bsonType' => ['decimal', 'null']], // optional
        'depth' => ['bsonType' => ['decimal', 'null']], // optional
        'certificateNumber' => ['bsonType' => 'string'], // required
        'certificateURL' => ['bsonType' => 'string'],
        'imageExternal' => ['bsonType' => 'string'],
        'videoExternal' => ['bsonType' => 'string'],
        'country' => ['bsonType' => 'string'],
        'state' => ['bsonType' => 'string'],
        'city' => ['bsonType' => 'string'],
        'vendor' => ['bsonType' => 'string'],
        'updated' => ['bsonType' => 'date']
      ]
    ]
  ];

  public function getTitle(object $diamond)
  {
    $title = [];
    if (!empty($diamond->shape)) {
      $title[] = $diamond->shape->code;
    }
    $title[] = 'Cut';
    if (!empty($diamond->produced)) {
      $title[] = $diamond->produced; // NOTE: can be Natural or Lab Grown
    }
    $title[] = 'Diamond';
    if (!empty($diamond->weight)) {
      array_unshift($title, $diamond->weight . ' Carat -');
    }
    return join(' ', $title);
  }

  public function getPermalink(object $diamond, string $origin = '')
  {
    (new Diamond($this->mongodb))->populate($diamond);
    $diamondSef = join('-', [
      ($diamond->shape ? $diamond->shape->code : 'Round') . '-shape',
      round('' . $diamond->weight . '', 2) . '-carat',
      ($diamond->color ? $diamond->color->code : 'K') . '-color',
      ($diamond->clarity ? $diamond->clarity->code : 'SI2') . '-clarity',
      $diamond->cut->code . '-cut',
    ]);
    $diamondCode = $diamond->certificateNumber . '_' . urlencode(str_replace('/', '!', $diamond->stockNumber ?? ''));

    return $origin . '/loose-diamonds/item/' . urlencode($diamondSef) . '-' . $diamondCode;
  }

  public function getPrice(object $product)
  {
    return empty($product->priceInternal) ? 0 : ceil($product->priceInternal * 100) / 100;
  }

  public function getLength(object $product)
  {
    if (empty($product->measurements))
      return '';

    $measurements = preg_split('/(x|-)/', $product->measurements);
    return empty($measurements[0]) ? '' : trim($measurements[0]);
  }

  public function getWidth(object $product)
  {
    if (empty($product->measurements))
      return '';

    $measurements = preg_split('/(x|-)/', $product->measurements);
    return empty($measurements[1]) ? '' : trim($measurements[1]);
  }
  public function getHeight(object $product)
  {
    if (empty($product->measurements))
      return '';

    $measurements = preg_split('/(x|-)/', $product->measurements);
    return empty($measurements[2]) ? '' : trim($measurements[2]);
  }

  public function populate(object &$diamond)
  {
    if (!empty($diamond->color_id))
      $diamond->color = (new Color($this->mongodb))->getOneWhere(['_id' => $diamond->color_id]);

    if (!empty($diamond->clarity_id))
      $diamond->clarity = (new Clarity($this->mongodb))->getOneWhere(['_id' => $diamond->clarity_id]);

    if (!empty($diamond->flourence_id))
      $diamond->flourence = (new Flourence($this->mongodb))->getOneWhere(['_id' => $diamond->flourence_id]);

    if (!empty($diamond->girdle_id))
      $diamond->girdle = (new Girdle($this->mongodb))->getOneWhere(['_id' => $diamond->girdle_id]);

    if (!empty($diamond->polish_id))
      $diamond->polish = (new Polish($this->mongodb))->getOneWhere(['_id' => $diamond->polish_id]);

    if (!empty($diamond->shape_id))
      $diamond->shape = (new Shape($this->mongodb))->getOneWhere(['_id' => $diamond->shape_id]);

    if (!empty($diamond->symmetry_id))
      $diamond->symmetry = (new Symmetry($this->mongodb))->getOneWhere(['_id' => $diamond->symmetry_id]);

    if (!empty($diamond->cut_id))
      $diamond->cut = (new Cut($this->mongodb))->getOneWhere(['_id' => $diamond->cut_id]);

    if (!empty($diamond->culet_id))
      $diamond->culet = (new Culet($this->mongodb))->getOneWhere(['_id' => $diamond->culet_id]);

    if (isset($diamond->measurements)) {
      $measurements = preg_split('/(x|-)/', $diamond->measurements);
      $diamond->length = empty($measurements[0]) ? '' : trim($measurements[0]);
      $diamond->width = empty($measurements[1]) ? '' : trim($measurements[1]);
      $diamond->height = empty($measurements[2]) ? '' : trim($measurements[2]);
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

    $items = $this->prepare($items);

    return $this->insertOne($items)->getInsertedId();
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

    if (empty($items))
      return 0;

    $items['isChangedManually'] = true;
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

    foreach (['shape_id', 'color_id', 'clarity_id', 'cut_id', 'polish_id', 'symmetry_id', 'flourence_id', 'girdle_id', 'culet_id'] as $i)
      if (isset($items[$i])) $items[$i] = $items[$i] ? new ObjectId($items[$i]) : null;

    $decimal = ['weight', 'depth', 'priceExternal', 'priceInternal', 'ratio', 'table'];

    foreach ($items as $k => $v)
      if (in_array($k, $decimal) && !empty($v))
        $items[$k] = new Decimal128($items[$k]);
    return $items;
  }

  /**
   * Get similar diamonds
   *
   * @param object $diamond
   * @param int $limit
   * @return array
   * @throws \Exception
   */
  public function getSimilar(object $diamond, int $limit = 10)
  {
    $diamond->weight = $diamond->weight ?? 0;
    $carat = new Decimal128($diamond->weight);
    $carat_min = new Decimal128($diamond->weight - 0.2);
    $carat_max = new Decimal128($diamond->weight + 0.2);
    $colors = array_map(function ($color) {
      return ['color_id' => new ObjectID($color->_id)];
    }, (new Color($this->mongodb))->getSimilar($diamond->color));
    $clarities = array_map(function ($clarity) {
      return ['clarity_id' => new ObjectID($clarity->_id)];
    }, (new Clarity($this->mongodb))->getSimilar($diamond->clarity));

    $vendors = (new Vendor($this->mongodb))->getVendors();

    $and = [ // Task #57727: For example is User is viewing 1 carat Round diamond, H color, VS2 clarity
      ['isEnabled' => true],
      ['$or' => $vendors],
      ['_id' => ['$ne' => new ObjectId($diamond->_id)]],
      ['shape_id' => new ObjectId($diamond->shape->_id)], // SAME SHAPE
      ['$or' => [
        ['$and' => [ // Same color and clarity with different carat size with + / - 0.20 carats
          ['weight' => ['$gte' => $carat_min, '$lte' => $carat_max]],
          ['color_id' => new ObjectId($diamond->color->_id)],
          ['clarity_id' => new ObjectId($diamond->clarity->_id)],
        ]], // example 1.20 carat, H Color, VS2 clarity
        ['$and' => [ // Same clarity with different color with + / - one color grade
          ['weight' => $carat],
          ['$or' => $colors],
          ['clarity_id' => new ObjectId($diamond->clarity->_id)],
        ]], // example, 1 carat G Color, VS2 clarity or 1 Carat H color VS2 clarity
        ['$and' => [ // Same color with different clarity with + / - one clarity grade
          ['weight' => $carat],
          ['color_id' => new ObjectId($diamond->color->_id)],
          ['$or' => $clarities],
        ]], // example, 1 carat, H color, Si1 clarity, or VS1 clarity
      ]]
    ];
    $products = $this->find(['$and' => $and], ['limit' => $limit]);
    $this->stringifyValues($products);

    foreach ($vendors as $vendor) $vendorNames[$vendor->code] = $vendor;

    $products = array_filter($products, function ($diamond) use ($vendorNames) {
      $vendor = $vendorNames[strtolower($diamond->vendor)];
      return !(empty($vendor) ? false : $vendor->isEnabled == false) && $diamond->isEnabled;
    });

    return array_map(function ($product) {
      $product->shape = (new Shape($this->mongodb))->getOneWhere(['_id' => $product->shape_id]);
      $product->color = (new Color($this->mongodb))->getOneWhere(['_id' => $product->color_id]);
      $product->clarity = (new Clarity($this->mongodb))->getOneWhere(['_id' => $product->clarity_id]);
      $product->permalink = $this->getPermalink($product);
      $product->price = $this->getPrice($product);
      return $product;
    }, $products);
  }

  public function FilterByCollectionId($array, $collection_id, $filter)
  {
    if (!empty($filter)) {
      $inparam = explode(',', $filter);
      if (in_array('all', $inparam)) {
        $and[] = [$collection_id => ['$ne' => null]];
        return $and;
      } else {
        $or = [];
        foreach ($array as $val) {
          if (in_array($val->code, $inparam)) {
            $or[] = [$collection_id => $val->_id];
          }
        }
        return ['$or' => $or];
      }
    }
    return null;
  }

  public function MinMaxOptions($min, $max, $field)
  {
    if (
      (!empty($min) && is_numeric($min))
      || (!empty($max) && is_numeric($max))
    ) {
      $price = [];
      if (!empty($min) && is_numeric($min))
        $price['$gte'] = new Decimal128($min);
      if (!empty($max) && is_numeric($max))
        $price['$lte'] = new Decimal128($max);
      return [$field => $price];
    }
    return null;
  }

  public function FindMinFilter($field)
  {
    $lowest = $this->find(empty($and) ? [$field => ['$ne' => null]] : [$field => ['$ne' => null], '$and' => $and], ['sort' => [$field => 1], 'limit' => 1]);
    return empty($lowest[0]->$field) ? 0 : (float)$lowest[0]->$field->__toString();
  }

  public function FindMaxFilter($field)
  {
    $highest = $this->find(empty($and) ? [$field => ['$ne' => null]] : [$field => ['$ne' => null], '$and' => $and,], ['sort' => [$field => -1], 'limit' => 1]);
    return empty($highest) ? 0 : (float)$highest[0]->$field->__toString();
  }

  public function getToExport($search = [], $origin = '', $limit = null, $skip = null, $fillKeys = true)
  {
    $fields = [
      '_id' => 'Id',
      'url' => 'URL', // render url
      'imageExternal' => 'ImagesURL',
      'videoExternal' => 'VideosURL',
      'lab' => 'CertificateLab', // required
      'certificateURL' => 'CertificateURL',
      'certificateNumber' => 'CertificateID', // required
      'priceInternal' => 'Price', // required
      'shape_id' => 'Shape', // required
      'weight' => 'Carat',
      'cut_id' => 'Cut', // required
      'color_id' => 'Color', // required
      'clarity_id' => 'Clarity', // required
      'flourence_id' => 'Fluorescence', // optional
      'polish_id' => 'Polish', // required
      'symmetry_id' => 'Symmetry', // required
      'TableWidth' => 'TableWidth', // absent
      'table' => 'TableWidthPercentage', // optional
      'girdle_id' => 'Girdle', // optional
      'GirdleThickness' => 'GirdleThickness', // absent
      'GirdleDiameter' => 'GirdleDiameter', // absent
      'culet_id' => 'Culet', // optional
      'CuletSize' => 'CuletSize', // absent
      'CuletAngle' => 'CuletAngle', // absent
      'CrownHeight' => 'CrownHeight', // absent
      'CrownHeightPercentage' => 'CrownHeightPercentage', // absent
      'CrownAngle' => 'CrownAngle', // absent
      'PavilionDepth' => 'PavilionDepth', // absent
      'PavilionDepthPercentage' => 'PavilionDepthPercentage', // absent
      'PavilionAngle' => 'PavilionAngle', // absent
      'depth' => 'DepthPercentage', // optional
      'ratio' => 'LengthToWidthRatio', // optional
      'measurements' => 'Measurements',
      'GirdleToTableDistance' => 'GirdleToTableDistance', // absent
      'StarLength' => 'StarLength', // absent
      'StarLengthPercentage' => 'StarLengthPercentage', // absent
      'GirdleToCuletDistance' => 'GirdleToCuletDistance', // absent
      'LowerHalfLength' => 'LowerHalfLength', // absent
      'LowerHalfLengthPercentage' => 'LowerHalfLengthPercentage', // absent
      'ShippingDays' => 'ShippingDays', // absent
      'WirePrice' => 'WirePrice', // absent
      //      'isEnabled' => ['bsonType' => 'bool'],
      //      'isNatural' => ['bsonType' => 'bool'],
      //      'isChangedManually' => ['bsonType' => 'bool'],
      //      'fancycolor_id' => ['bsonType' => ['objectId', 'null']], // optional
      //      'seller' => ['bsonType' => 'string'],
      //      'stockNumber' => ['bsonType' => 'string'],
      //      'priceExternal' => ['bsonType' => 'decimal'],
      //      'country' => ['bsonType' => 'string'],
      //      'state' => ['bsonType' => 'string'],
      //      'city' => ['bsonType' => 'string'],
      //      'vendor' => ['bsonType' => 'string'],
      //      'updated' => ['bsonType' => 'date'],
    ];

    $names = [];
    foreach ((new Shape($this->mongodb))->find() as $shape)
      $names['shape_id'][$shape->_id->__toString()] = $shape->code;
    foreach ((new Cut($this->mongodb))->find() as $cut)
      $names['cut_id'][$cut->_id->__toString()] = $cut->code;
    foreach ((new Color($this->mongodb))->find() as $color)
      $names['color_id'][$color->_id->__toString()] = $color->code;
    foreach ((new Clarity($this->mongodb))->find() as $clarity)
      $names['clarity_id'][$clarity->_id->__toString()] = $clarity->code;
    foreach ((new Flourence($this->mongodb))->find() as $flourence)
      $names['flourence_id'][$flourence->_id->__toString()] = $flourence->code;
    foreach ((new Polish($this->mongodb))->find() as $polish)
      $names['polish_id'][$polish->_id->__toString()] = $polish->code;
    foreach ((new Symmetry($this->mongodb))->find() as $symmetry)
      $names['symmetry_id'][$symmetry->_id->__toString()] = $symmetry->code;
    foreach ((new Girdle($this->mongodb))->find() as $girdle)
      $names['girdle_id'][$girdle->_id->__toString()] = $girdle->code;
    foreach ((new Culet($this->mongodb))->find() as $culet)
      $names['culet_id'][$culet->_id->__toString()] = $culet->code;

    $list = [[]];
    foreach ($this->allWhere($search, $limit, $skip) as $i => &$row) {
      foreach ($fields as $key => $field) {
        if (!$i && $fillKeys) $list[$i][] = $field; // fill keys
        if ($key === 'url') { // render url
          $list[$i + $fillKeys][] = $this->getPermalink($row, $origin);
          continue;
        }
        if (in_array($key, [
          'shape_id', 'cut_id', 'color_id', 'clarity_id', 'flourence_id',
          'polish_id', 'symmetry_id', 'girdle_id', 'culet_id'
        ])) {
          $list[$i + $fillKeys][] = $names[$key][$row->{$key}] ?? '';
          continue;
        }
        $list[$i + $fillKeys][] = empty($row->{$key}) ? '' : $row->{$key}; // fill '' for non-existent or false
      }
    }
    return $list;
  }
}
