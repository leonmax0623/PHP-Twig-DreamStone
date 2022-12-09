<?php

namespace DS\Controller\Web\Frontend;

use mongodb\BSON\ObjectID;
use mongodb\BSON\Decimal128;
use DS\Core\Controller\WebController;
use DS\Model\Category;
use DS\Model\Metal;
use DS\Model\BirthStone;
use DS\Model\JewelryStones;
use DS\Model\JewelryPearl;
use DS\Model\Diamond;
use DS\Model\Product;
use DS\Model\Viewed;
use DS\Model\Favorite;
use DS\Model\Shape;
use DS\Model\Color;
use DS\Model\Cut;
use DS\Model\Clarity;
use DS\Model\Polish;
use DS\Model\Symmetry;
use DS\Model\Flourence;
use Slim\Http\Request;
use Slim\Http\Response;
//
use DS\Model\Vendor;

/**
 * Class Search
 * @package DS\Controller\Web
 */
final class Search extends WebController
{

  /**
   * @var array
   */
  private $possibleSort = [
    ['code' => 'price_down', 'title' => 'High to low'],
    ['code' => 'price_up', 'title' => 'Low to high']
  ];

  /**
   * Filter display
   *
   * @param Request $request
   * @param Response $response
   * @param $args
   * @return \Psr\Http\Message\ResponseInterface
   * @throws \Exception
   */
  public function suggestionsAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $params = $request->getQueryParams();
    $search = isset($params['s']) ? $params['s'] : '';

    $options = [
      'limit' => isset($params['limit']) ? (int) $params['limit'] : 50,
      // 'skip' => isset($params['offset']) ? (int) $params['offset'] : null,
    ];

    // Product Suggestions
    // db.getCollection('product').createIndex( { title: "text", description: "text", sku: "text", url: "text" } )

    $Product = new Product($this->mongodb);
    $Category = new Category($this->mongodb);

    $Products = $Product->find([
      '$text' => [
        '$search' => $search,
      ],
    ], $options);

    $allCategories = $Category->find();

    $productSuggestions = array_map(function ($product) use ($allCategories) {
      $productCategoryId = $product->category_id->__toString();
      $category = array_values(array_filter($allCategories, function ($category) use ($productCategoryId) {
        return $category->_id->__toString() === $productCategoryId;
      }));
      if (!empty($category)) {
        $product->category = $category[0];
      } else {
        $product->category = (object) ['url' => ''];
        $this->logger->error('undefined product category: ' . $productCategoryId);
      }

      return [
        'category' => $product->category->title,
        'title' => $product->title,
        'images' => $product->images,
        'sku' => $product->sku,
        'description' => $product->description,
        'url' => $this->router->pathFor('jewelry-Details', [
          'filter' => $product->category->url,
          'product' => $product->url,
        ]),
        'price' => (float) ($product->retail_price ?? $product->price)->__toString(),
      ];
    }, $Products);


    // Diamond Suggestions
    // db.getCollection('diamond').createIndex( { stockNumber: "text", certificateNumber: "text" } )

    $vendors = (new Vendor($this->mongodb))->getVendors();

    $disabledVendors = array_filter($vendors, function ($vendor) {
      return $vendor->isEnabled === false;
    });

    $Diamond = new Diamond($this->mongodb);

    $Diamonds = $Diamond->find([
      '$and' => [
        ['$text' => ['$search' => $search]],
        ['isEnabled' => true],
        ['priceInternal' => ['$exists' => true, '$gt' => 0]],
        ['vendor' => ['$nin' => array_values(array_map(function ($vendor) {
          return $vendor->code;
        }, $disabledVendors))]],
      ],
    ], $options);

    foreach ($vendors as $vendor) $vendorNames[$vendor->code] = $vendor;

    $Diamonds = array_filter($Diamonds, function ($diamond) use ($vendorNames) {
      $vendor = $vendorNames[strtolower($diamond->vendor)] ?? null;
      return !(empty($vendor) ? false : $vendor->isEnabled === false);
    });

    $diamondSuggestions = array_map(function ($diamond) use ($vendorNames, $Diamond) {
      $vendor = $vendorNames[strtolower($diamond->vendor)] ?? null;
      return [
        'category' => 'Diamond',
        'title' => ($diamond->weight ?? '') . ' ' . $diamond->certificateNumber,
        'description' => '', //$diamond->description,
        'url' => $Diamond->getPermalink($diamond),
        'sku' => $diamond->certificateNumber,
        'weight' => ($diamond->weight ?? '') . ' ',
        'price' => (float) $diamond->priceInternal->__toString(),
        'vendorEnabled' => !(empty($vendor) ? false : $vendor->isEnabled == false),
        'isEnabled' => $diamond->isEnabled,
        'image' => (!empty($diamond->vendor) && !empty($vendorNames[$diamond->vendor]->showImages)) ? $diamond->imageExternal : '',
      ];
    }, $Diamonds);

    $suggestions = array_merge([], $productSuggestions, $diamondSuggestions);

    return $response->withJson($suggestions);
  }

  protected function searchProducts($argFilter, $params, $user)
  {
    $search = isset($params['s']) ? $params['s'] : '';

    $Category = new Category($this->mongodb);
    $Product = new Product($this->mongodb);

    $currentCategory = $Category->getOneWhere(['url' => $argFilter ?? '']);
    $metals = (new Metal($this->mongodb))->find();
    $jewelrystones = (new JewelryStones($this->mongodb))->find();
    $jewelrypearls = (new JewelryPearl($this->mongodb))->find();
    $categories = $Category->find(['level' => 0]);
    $birthstones = (new BirthStone($this->mongodb))->find();

    $filter = [
      'jewelrytype' => '',
      'jewelrystone' => [],
      'jewelrypearl' => [],
      'birthstone' => [],
      'metal' => [],
      'price_min' => 0,
      'price_max' => 0,
      'sort_by' => null,
      'offset' => 0, // TODO
      'limit' => 50, // TODO
    ];

    foreach ($params as $key => $value)
      if (array_key_exists($key, $filter))
        $filter[$key] = $value;

    $subcategories = [];
    $and = [];
    if (!empty($argFilter) && $argFilter != 'all') { // jewelry = all categories
      $catId = new ObjectID($currentCategory->_id);
      $filter['jewelrytype'] = $currentCategory->url;
      if ($currentCategory->subcategories) {
        $subcategories = $Category->allWhere(['parent_id' => $catId]);
        $or = [['category_id' => $catId]];
        foreach ($Category->getAllSubcategoriesIds($catId) as $_id) {
          $or[] = ['category_id' => $_id];
        }
        $and[] = ['$or' => $or];
      } else {
        $and[] = ['category_id' => $catId];
      }
    }

    if (!empty($filter['sort_by'])) {
      $possibleSort = array_map(function ($direction) {
        return $direction['code'];
      }, $this->possibleSort);
      if (!in_array($filter['sort_by'], $possibleSort)) {
        $filter['sort_by'] = null;
      }
    }

    if (!empty($filter['metal']) && $filter['metal'] !== 'All metals') {
      $inparam = explode(',', $filter['metal']);
      $metal = array_filter($metals, function ($metal) use ($inparam) {
        return in_array($metal->code, $inparam);
      });
      $or = [];
      foreach ($metal as $m) {
        $or[] = ['metal_id' => new ObjectID($m->_id)];
      }
      $and[] = ['$or' => $or];
    }

    if (!empty($filter['jewelrystone'])) {
      $inparam = explode(',', $filter['jewelrystone']);
      if (in_array('all', $inparam))
        $and[] = ['jewelrystone_id' => ['$ne' => null]];
      else {
        $or = [];
        foreach ($jewelrystones as $jewelrystone)
          if (in_array($jewelrystone->code, $inparam))
            $or[] = ['jewelrystone_id' => $jewelrystone->_id];
        $and[] = ['$or' => $or];
      }
    }

    if (!empty($filter['jewelrypearl'])) {
      $inparam = explode(',', $filter['jewelrypearl']);
      if (in_array('all', $inparam))
        $and[] = ['jewelrypearl_id' => ['$ne' => null]];
      else {
        $or = [];
        foreach ($jewelrypearls as $jewelrypearl)
          if (in_array($jewelrypearl->code, $inparam))
            $or[] = ['jewelrypearl_id' => $jewelrypearl->_id];
        $and[] = ['$or' => $or];
      }
    }

    if (!empty($filter['birthstone'])) {
      $inparam = explode(',', $filter['birthstone']);
      if (in_array('all', $inparam))
        $and[] = ['birthstone_id' => ['$ne' => null]];
      else {
        $or = [];
        foreach ($birthstones as $birthstone)
          if (in_array($birthstone->code, $inparam))
            $or[] = ['birthstone_id' => $birthstone->_id];
        $and[] = ['$or' => $or];
      }
    }

    $cheapest = $Product->find(empty($and) ? [] : ['$and' => $and], ['sort' => ['price' => 1], 'limit' => 1]);
    $price_min = empty($cheapest) ? 0 : (float)$cheapest[0]->price->__toString();
    $expensive = $Product->find(empty($and) ? [] : ['$and' => $and], ['sort' => ['price' => -1], 'limit' => 1]);
    $price_max = empty($expensive) ? 0 : (float)$expensive[0]->price->__toString();

    if (
      (!empty($filter['price_min']) && is_numeric($filter['price_min']))
      || (!empty($filter['price_max']) && is_numeric($filter['price_max']))
    ) {
      $price = [];
      if (!empty($filter['price_min']) && is_numeric($filter['price_min']))
        $price['$gte'] = new Decimal128($filter['price_min']);
      if (!empty($filter['price_max']) && is_numeric($filter['price_max']))
        $price['$lte'] = new Decimal128($filter['price_max']);
      $and[] = ['price' => $price];
    }

    $options = [];
    if (!empty($filter['sort_by'])) {
      $sort = explode('_', $filter['sort_by']);
      $options['sort'] = [$sort[0] => $sort[1] === 'up' ? 1 : -1];
    }
    $options['limit'] = (int) $filter['limit'];
    $options['skip'] = (int) $filter['offset'];

    $Favorite = new Favorite($this->mongodb, $user ? $user->_id : null);
    $products = $Product->find(['$text' => ['$search' => $search]], $options);
    $Product->stringifyValues($products);
    $products = array_map(function ($product) use ($Product, $Favorite) {
      $Product->populate($product);
      $product->withAttributes = $Product->getSelectedAttributes($product);
      $product->title = $Product->getTitle($product);
      $product->permalink = $Product->getPermalink($product);
      $product->images = $Product->getImages($product);
      $product->isFavorite = $Favorite->isFavorite('products', $product);
      return $product;
    }, $products);

    $metalIds = [];
    foreach ($metals as $metal) {
      $metalIds[$metal->code] = implode('', explode(' ', $metal->code));
    }

    return [
      'products' => $products,
      'viewedCount' => (new Viewed($this->mongodb))->count(),
      'metals' => $metals,
      'metal_ids' => $metalIds,
      'birthstones' => $birthstones,
      'jewelrystones' => $jewelrystones,
      'jewelrypearls' => $jewelrypearls,
      'categories' => $categories,
      'subcategories' => $subcategories,
      'possibleSort' => $this->possibleSort,
      'price_min' => $price_min,
      'price_max' => $price_max,
      'filter' => $filter,
    ];
  }

  public function searchDiamonds($argFilter, $params)
  {
    $search = isset($params['s']) ? $params['s'] : '';

    $shapes = (new Shape($this->mongodb))->find();
    $colors = (new Color($this->mongodb))->find();
    $cuts = (new Cut($this->mongodb))->find();
    $clarities = (new Clarity($this->mongodb))->find();
    $polishes = (new Polish($this->mongodb))->find();
    $symmetries = (new Symmetry($this->mongodb))->find();
    $flourences = (new Flourence($this->mongodb))->find();

    $vendors = (new Vendor($this->mongodb))->getVendors();

    $Diamond = new Diamond($this->mongodb);

    $filter = [
      'price_min' => 0,
      'price_max' => 0,
      'color_min' => 0,
      'color_max' => 0,
      'depth_min' => 0,
      'depth_max' => 0,
      'table_min' => 0,
      'table_max' => 0,
      'carat_min' => 0,
      'carat_max' => 0,
      'shape_id' => null,
      'color_id' => null,
      'cut_id' => null,
      'clarity_id' => null,
      'polish_id' => null,
      'symmetry_id' => null,
      'flourence_id' => null,
      'ration_min' => 0,
      'ration_max' => 5,
      'offset' => 0,
      'limit' => 50,
    ];

    foreach ($params as $key => $value)
      if (array_key_exists($key, $filter))
        $filter[$key] = $value;

    if (!empty($argFilter)) {
      $filter_input = explode('_', $argFilter);

      if (
        count($filter_input) == 2
        && isset($filter_input[0])
        && array_key_exists($filter_input[0], $filter)
      ) {
        $filter[$filter_input[0]] = str_replace('-', ' ', $filter_input[1]);
      }
    }

    $and = [];

    if ($shape = $Diamond->FilterByCollectionId($shapes, 'shape_id', $filter['shape_id']))
      $and[] = $shape;

    if ($cut = $Diamond->FilterByCollectionId($cuts, 'cut_id', $filter['cut_id']))
      $and[] = $cut;

    if ($clarity = $Diamond->FilterByCollectionId($clarities, 'clarity_id', $filter['clarity_id']))
      $and[] = $clarity;

    if ($color = $Diamond->FilterByCollectionId($colors, 'color_id', $filter['color_id']))
      $and[] = $color;

    if ($symmetry = $Diamond->FilterByCollectionId($symmetries, 'symmetry_id', $filter['symmetry_id']))
      $and[] = $symmetry;

    if ($polish = $Diamond->FilterByCollectionId($polishes, 'polish_id', $filter['polish_id']))
      $and[] = $polish;

    $price_min = $Diamond->FindMinFilter('priceInternal');
    $price_max = $Diamond->FindMaxFilter('priceInternal');

    $carat_min = $Diamond->FindMinFilter('weight');
    $carat_max = $Diamond->FindMaxFilter('weight');

    $depth_min = $Diamond->FindMinFilter('depth');
    $depth_max = $Diamond->FindMaxFilter('depth');

    $table_min = $Diamond->FindMinFilter('table');
    $table_max = $Diamond->FindMaxFilter('table');

    if ($price = $Diamond->MinMaxOptions($filter['price_min'], $filter['price_max'], 'priceInternal'))
      $and[] = $price;

    if ($depth = $Diamond->MinMaxOptions($filter['depth_min'], $filter['depth_max'], 'depth'))
      $and[] = $depth;

    if ($table = $Diamond->MinMaxOptions($filter['table_min'], $filter['table_max'], 'table'))
      $and[] = $table;

    if ($carat = $Diamond->MinMaxOptions($filter['carat_min'], $filter['carat_max'], 'weight'))
      $and[] = $carat;

    foreach ($params as $key => $value)
      if (array_key_exists($key, $filter))
        $filter[$key] = $value;

    if (!isset($filter['sort_by']))
      $filter['sort_by'] = $this->possibleSort[0]['code'];

    $options = [];
    if (!empty($filter['sort_by'])) {
      $sort = explode('_', $filter['sort_by']);
      $options['sort'] = [$sort[0] => $sort[1] === 'up' ? 1 : -1];
    }
    $options['limit'] = $filter['limit'];

    $disabledVendors = array_filter($vendors, function ($vendor) {
      return $vendor->isEnabled === false;
    });

    $diamonds = array_map(function ($diamond) use ($Diamond) {
      return $diamond;
    }, (array) $Diamond->find([
      '$and' => [
        ['isEnabled' => true],
        ['$text' => ['$search' => $search]],
        ['priceInternal' => ['$exists' => true, '$gt' => 0]],
        ['vendor' => ['$nin' => array_values(array_map(function ($vendor) {
          return $vendor->code;
        }, $disabledVendors))]]
      ]
    ], $options));

    $Diamond->stringifyValues($diamonds);

    $shapeNames = $colorNames = $cutNames = $clarityNames = $polishNames =
      $symmetryNames = $flourenceNames = [];

    foreach ($shapes as $shape) $shapeNames[$shape->_id->__toString()] = $shape;
    foreach ($colors as $color) $colorNames[$color->_id->__toString()] = $color;
    foreach ($cuts as $cut) $cutNames[$cut->_id->__toString()] = $cut;
    foreach ($clarities as $clarity) $clarityNames[$clarity->_id->__toString()] = $clarity;
    foreach ($polishes as $polish) $polishNames[$polish->_id->__toString()] = $polish;
    foreach ($symmetries as $symmetry) $symmetryNames[$symmetry->_id->__toString()] = $symmetry;
    foreach ($flourences as $flourence) $flourenceNames[$flourence->_id->__toString()] = $flourence;
    foreach ($vendors as $vendor) $vendorNames[$vendor->code] = $vendor;

    // $Favorite = new Favorite($this->mongodb, $user ? $user->_id : null);
    // $Compare = new Compare($this->mongodb);

    $res = array_map(function ($diamond) use (
      $Diamond,
      $shapeNames,
      $colorNames,
      $cutNames,
      $clarityNames,
      $polishNames,
      $symmetryNames,
      $flourenceNames,
      $vendorNames
    ) {
      $vendor = $vendorNames[strtolower($diamond->vendor)] ?? null;
      return array_merge((array)$diamond, [
        'title' => $Diamond->getTitle($diamond),
        'permalink' => $Diamond->getPermalink($diamond),
        'price' => $Diamond->getPrice($diamond),
        'shape' => isset($diamond->shape_id) && isset($shapeNames[$diamond->shape_id]) ? $shapeNames[$diamond->shape_id] : '',
        'color' => isset($diamond->color_id) && isset($colorNames[$diamond->color_id]) ? $colorNames[$diamond->color_id] : '',
        'cut' => isset($diamond->cut_id) && isset($cutNames[$diamond->cut_id]) ? $cutNames[$diamond->cut_id] : '',
        'clarity' => isset($diamond->clarity_id) && isset($clarityNames[$diamond->clarity_id]) ? $clarityNames[$diamond->clarity_id] : '',
        'polish' => isset($diamond->polish_id) && isset($polishNames[$diamond->polish_id]) ? $polishNames[$diamond->polish_id] : '',
        'symmetry' => isset($diamond->symmetry_id) && isset($symmetryNames[$diamond->symmetry_id]) ? $symmetryNames[$diamond->symmetry_id] : '',
        'flourence' => isset($diamond->flourence_id) && isset($flourenceNames[$diamond->flourence_id]) ? $flourenceNames[$diamond->flourence_id] : '',
        'imageExternal' => (!empty($diamond->vendor) && !empty($vendorNames[$diamond->vendor]->showImages)) ? $diamond->imageExternal : '',
        'vendorEnabled' => !(empty($vendor) ? false : $vendor->isEnabled == false),
        // 'showCerts' => !empty($vendorNames[$diamond->vendor]->showCerts),
      ]);
    }, $diamonds);

    return [
      // 'diamonds' => $diamonds,
      'diamonds' => $res,
      'shapes' => $shapes,
      'colors' => $colors,
      'cuts' => $cuts,
      'clarities' => $clarities,
      'polishes' => $polishes,
      'symmetries' => $symmetries,
      'flourences' => $flourences,
      'filter' => $filter,
      'price_min' => $price_min,
      'price_max' => $price_max,
      'carat_min' => $carat_min,
      'carat_max' => $carat_max,
      'depth_min' => $depth_min,
      'depth_max' => $depth_max,
      'table_min' => $table_min,
      'table_max' => $table_max,
    ];
  }

  /**
   * Filter display
   *
   * @param Request $request
   * @param Response $response
   * @param $args
   * @return \Psr\Http\Message\ResponseInterface
   * @throws \Exception
   */
  public function searchAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $argFilter = $args['filter'] ?? [];
    $params = $request->getQueryParams();
    $user = $request->getAttribute('user');

    $resultsProducts = $this->searchProducts($argFilter, $params, $user);
    $resultsDiamonds = $this->searchDiamonds($argFilter, $params);

    $results = array_merge([
      'isSearchSection' => true,
      'filter' => $argFilter,
      'params' => [],
    ], $resultsProducts, $resultsDiamonds);

    return $this->render(
      $response,
      'pages/frontend/search/search.twig',
      $results
    );
  }
}
