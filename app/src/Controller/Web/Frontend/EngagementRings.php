<?php

namespace DS\Controller\Web\Frontend;

use DS\Core\Controller\WebController;
use DS\Model\Composite;
use DS\Model\Favorite;
use DS\Model\JewelryType;
use DS\Model\Metal;
use DS\Model\Product;
use DS\Model\RingStyle;
use DS\Model\Shape;
use DS\Model\Viewed;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\ObjectId;
use Slim\Http\Request;
use Slim\Http\Response;
use DS\Model\Compare;

/**
 * Class Front
 * @package DS\Controller\Web
 */
final class EngagementRings extends WebController {

  /**
   * @var array
   */
  private $possibleSort = [
      ['code' => 'price_down', 'title' => 'High to low'],
      ['code' => 'price_up', 'title' => 'Low to high']
    ];

  /**
   *  Home renderer
   *
   * @param Request $request
   * @param Response $response
   * @param $args
   * @return \Psr\Http\Message\ResponseInterface
   * @throws \Exception
   */
  public function indexAction(Request $request, Response $response, $args) {
    // ugly way for accessing request attributes
    $this->request = $request;

    $ringstyles = (new RingStyle($this->mongodb))->find();
    $metals = (new Metal($this->mongodb))->find();

    return $this->render($response, 'pages/frontend/engagement_rings/index.twig', [
      'metals' => $metals,
      'ringstyles' => $ringstyles,
      'isEngagementRingsSection' => true
    ]);
  }

  /**
   * Display product compare
   *
   * @param Request $request
   * @param Response $response
   * @param $args
   * @return \Psr\Http\Message\ResponseInterface
   * @throws \Exception
   */
  public function compareEngagementRingsAction(Request $request, Response $response, $args)
    {
      $this->request = $request;

      $Compare = new Compare($this->mongodb);
      $products = $Compare->get('products');

      $Product = new Product($this->mongodb);
      $products = array_map(function($product) use($Product) {
        $product->title = $Product->getTitle($product);
        $product->withAttributes = $Product->getSelectedAttributes($product);
        $product->images = $Product->getImages($product);
        return $product;
      }, $products);

      return $this->render($response, 'pages/frontend/jewelry/compare/index.twig', [
        'products' => $products,
        'viewedCount' => (new Viewed($this->mongodb))->count(),
        'compareCountP' => $Compare->count('products'),
        'g_event' => 'view_item_list',
      ]);
  }

  public function searchAction(Request $request, Response $response, $args) {
    // ugly way for accessing request attributes
    $this->request = $request;

    $ringType = (new JewelryType($this->mongodb))->getOneWhere(['code' => 'engagement-rings']);
    if (!$ringType)
      return $this->render($response, 'pages/frontend/jewelry/details/404.twig', ['text' => 'Rings is not found']);

    $Product = new Product($this->mongodb);
    $ringstyles = (new RingStyle($this->mongodb))->find();
    $metals = (new Metal($this->mongodb))->find();

    $filter = [
      'metal' => [],
      'ringstyle' => [],
      'shape' => null,
      'price_min' => 0,
      'price_max' => 0,
      'sort_by' => null,
      'offset' => 0, // TODO
      'limit' => 10,
    ];

    $params = $request->getQueryParams();
    foreach ($params as $key => $value)
      if (array_key_exists($key, $filter))
        $filter[$key] = $value;

    $and = [
      ['jewelrytype_id' => new ObjectID($ringType->_id)],
    ];

    if (!empty($filter['sort_by'])) {
      $possibleSort = array_map(function($direction){ return $direction['code']; }, $this->possibleSort);
      if (!in_array($filter['sort_by'], $possibleSort)) {
        $filter['sort_by'] = null;
      }
    }

    if (!empty($filter['ringstyle'])) {
      $inparam = explode(',', $filter['ringstyle']);
      $or = [];
      foreach ($ringstyles as $ringstyle)
        if (in_array($ringstyle->code, $inparam))
          $or[] = ['ringstyle_id' => new ObjectID($ringstyle->_id)];
      $and[] = ['$or' => $or];
    }

    if (!empty($filter['metal']) && $filter['metal'] !== 'All metals') {
      $inparam = explode(',', $filter['metal']);
      $or = [];
      foreach ($metals as $metal)
        if (in_array($metal->code, $inparam))
          $or[] = ['metal_id' => new ObjectID($metal->_id)];
      $and[] = ['$or' => $or];
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

    $user = $request->getAttribute('user');
    $Favorite = new Favorite($this->mongodb, $user ? $user->_id : null);
    $isBuilder = !empty($request->getQueryParam('builder'));

    // $Compare = new Compare($this->mongodb);

    $shapeIds = [];
    if ($isBuilder) {
      $and[] = ['is_for_builder' => true];
      $composite = (new Composite($this->mongodb))->getDetails();
      if (!empty($composite->diamond)) {
        $shapeIds[] = $composite->diamond->shape_id;
        $and[] = ['$or' => [
          ['builder_compatible' => null],
          ['builder_compatible' => ['$elemMatch' => [
            'shape_id' => new ObjectId($composite->diamond->shape_id),
            'weight_min' => ['$lte' => new Decimal128($composite->diamond->weight)],
            'weight_max' => ['$gte' => new Decimal128($composite->diamond->weight)],
          ]]],
        ]];
      }
    }
    $shapes = (new Shape($this->mongodb))->find();
    if ($filter['shape']) {
      $shapeIds = []; // clear builder value
      $shapeCodes = explode(',', $filter['shape']);
      foreach ($shapes as $shape)
        if (in_array($shape->code, $shapeCodes))
          $shapeIds[] = $shape->_id->__toString();
    }

    $find = [
      ['$match' => ['$and' => $and]],
    ];
    if (empty($filter['sort_by'])) {
      $find[] = ['$addFields' => ['sortField' => ['$cond' => [
          ['$ifNull' => ['$order', false]],
          1,
          0
      ]]]];
      $find[] = ['$sort' => ['sortField' => -1, 'order' => 1]];
    } else {
      $sort = explode('_', $filter['sort_by']);
      $find[] = ['$sort' => [$sort[0] => $sort[1] === 'up' ? 1 : -1]];
    }
    $find[] = ['$skip' => (int) $filter['offset']];
    $find[] = ['$limit' => (int) $filter['limit']];
    $products = array_map(function($product) use($Product, $Favorite, $shapeIds) {
      $Product->populate($product);
      $product->withAttributes = $Product->getSelectedAttributes($product);
      $product->title = $Product->getTitle($product);
      $product->permalink = $Product->getPermalink($product);
      $product->images = $Product->getImages($product, $shapeIds);
      $product->isFavorite = $Favorite->isFavorite('products', $product);
      // $product->isCompare = $Compare->isCompare('products', $product);
      return $product;
    }, $Product->aggregate($find));
    $productsTotal = $Product->countDocuments(['$and' => $and]);

    $metalIds = [];
    foreach ($metals as $metal) {
      $metalIds[$metal->code] = implode('', explode(' ', $metal->code));
    }

    if (isset($params['json'])) {
      if (!count($products)) {
        return $response->withJson([
          'finish' => true,
          'total' => $productsTotal,
        ]);
      } else {
        return $response->withJson([
          'finish' => false,
          'total' => $productsTotal,
          'items' => array_map(function ($product) use ($params) {
            return $this->view->fetch('pages/frontend/jewelry/result.twig', [
              'product' => $product,
              'params' => $params,
            ]);
          }, $products),
        ]);
      }
    }

    $data = [
      'isBuilder' => $isBuilder,
      'products' => $products,
      'total' => $productsTotal,
      'viewedCount' => (new Viewed($this->mongodb))->count(),
      'ringstyles' => $ringstyles,
      'metals' => $metals,
      'metal_ids' => $metalIds,
      'shapes' => $shapes,
      'possibleSort' => $this->possibleSort,
      'price_min' => $price_min,
      'price_max' => $price_max,
      'filter' => $filter,
      'isEngagementRingsSection' => true,
      'params' => $params,
      'compareCountP' => (new Compare($this->mongodb))->count(),
      'g_event' => 'view_search_results',
    ];

    if ($isBuilder)
      $data['composite'] = (new Composite($this->mongodb))->getDetails();

    return $this->render($response, 'pages/frontend/engagement_rings/search.twig', $data);
  }

  /**
   * Display ring details
   *
   * @param Request $request
   * @param Response $response
   * @param $args
   * @return \Psr\Http\Message\ResponseInterface
   */
  public function detailsAction(Request $request, Response $response, $args) {
    // ugly way for accessing request attributes
    $this->request = $request;

    return $this->render($response, 'pages/frontend/engagement_rings/details.twig', ['isEngagementRingsSection' => true]);
  }
}