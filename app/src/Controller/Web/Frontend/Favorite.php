<?php

namespace DS\Controller\Web\Frontend;

use DS\Core\Controller\WebController;
use Slim\Http\Request;
use Slim\Http\Response;
use DS\Model\Favorite as FavoriteModel;
use DS\Model\Category;
use DS\Model\Metal;
use DS\Model\Composite;
use DS\Model\Diamond;
use DS\Model\Product;

/**
 * Class Front
 * @package DS\Controller\Web
 */
final class Favorite extends WebController
{

  /**
   * Get all favorite products for current user
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   * @throws \Exception
   */
  public function allAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $params = $request->getQueryParams();
    $user = $request->getAttribute('user');
    $userId = $user ? $user->_id : null;
    $filter = [
      'limit' => empty($params['limit']) ? 10 : (int) $params['limit'],
      'skip' => empty($params['offset']) ? 0 : (int) $params['offset'],
    ];

    $Product = new Product($this->mongodb);
    $Diamond = new Diamond($this->mongodb);
    $Composite = new Composite($this->mongodb);

    $currentFavorites = array_slice(
      (new FavoriteModel($this->mongodb, $userId))->getCurrentFavorites(),
      $filter['skip'],
      $filter['limit']
    );

    $metals = [];
    foreach ((new Metal($this->mongodb))->allWhere() as $metal) $metals[$metal->_id] = $metal;

    function getProductDetails($product, $metals, $Product) {
      if ($product->metal_id && isset($metals[$product->metal_id]))
        $product->metal = $metals[$product->metal_id];

      $product->images = $Product->getImages($product);
      return $product;
    }

    $output = [];
    foreach ($currentFavorites as $favorite) {
      switch ($favorite->group) {
        case 'products':
          $p = $Product->getOneWhere(['_id' => $favorite->item->_id]);
          if ($p) {
            $p->group = $favorite->group;
            $p->withAttributes = empty($favorite->item->withAttributes) ? [] : (array) $favorite->item->withAttributes;
            $Product->populate($p);
            $p->title = $Product->getTitle($p);
            $p->permalink = $Product->getPermalink($p);
            $p->price = $Product->getPrice($p);
            $output[] = getProductDetails($p, $metals, $Product);
          }
          break;
        case 'diamonds':
          $d = $Diamond->getOneWhere(['_id' => $favorite->item->_id]);
          if ($d) {
            $d->group = $favorite->group;
            $Diamond->populate($d);
            $d->title = $Diamond->getTitle($d);
            $d->permalink = $Diamond->getPermalink($d);
            $d->price = $Diamond->getPrice($d);
            $output[] = $d;
          }
          break;
        case 'composites':
          $p = $Product->getOneWhere(['_id' => $favorite->item->product->_id]);
          $d = $Diamond->getOneWhere(['_id' => $favorite->item->diamond->_id]);
          if ($p && $d) {
            $p->withAttributes = empty($favorite->item->product->withAttributes) ? [] : (array) $favorite->item->product->withAttributes;
            $Product->populate($p);
            $p->price = $Product->getPrice($p);

            $Diamond->populate($d);
            $d->title = $Diamond->getTitle($d);
            $d->price = $Diamond->getPrice($d);

            $output[] = (object) [
              'group' => $favorite->group,
              'permalink' => $Composite->getPermalink((object) ['product' => $p, 'diamond' => $d]),
              'product' => getProductDetails($p, $metals, $Product),
              'diamond' => $d,
            ];
          }
          break;
      }
    }

    if (isset($params['json'])) {
      return $response->withJson(empty($output) ? [
        'finish' => true,
      ] : [
        'finish' => false,
        'items' => array_map(function($product) {
          return $this->view->fetch('pages/frontend/wish_list/result.twig', [
            'product' => $product,
          ]);
        }, $output),
      ]);
    }

    return $this->render($response, 'pages/frontend/wish_list/index.twig', [
      'products' => $output,
      'filter' => $filter,
      'isWishListSection' => true,
    ]);
  }

  /**
   * Add to favorite for current user
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   * @throws \Exception
   */
  public function addAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $d = $request->getParsedBody();
    if (empty($d['group'])) {
      $code = 404;
      $msg = 'group is empty';
      $this->logger->warn($code . ": " . $msg);
      return $response->withJson(['error' => ['code' => $code, 'msg' => $msg]]);
    }

    $user = $request->getAttribute('user');
    if (!$user) {
      return $response->withJson(['error' => ['code' => 401, 'msg' => 'Unauthorized']]);
    }

    return $response->withJson([
      '_id' => (new FavoriteModel($this->mongodb, $user->_id))->addItem($d['group'], (object) $d['item']),
    ]);
  }

  /**
   * Add to favorite for current user
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   * @throws \Exception
   */
  public function deleteAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $d = $request->getParsedBody();
    if (empty($d['group'])) {
      $code = 404;
      $msg = 'group is empty';
      $this->logger->warn($code . ': ' . $msg);
      return $response->withJson(['error' => ['code' => $code, 'msg' => $msg]]);
    }

    $user = $request->getAttribute('user');
    if (!$user) {
      return $response->withJson(['error' => ['code' => 401, 'msg' => 'Unauthorized']]);
    }

    (new FavoriteModel($this->mongodb, $user->_id))->deleteItem($d['group'], (object) $d['item']);

    return $response->withHeader('Content-Type', 'application/json')->write('{}');
  }

}