<?php

namespace DS\Controller\Web\Frontend;

use DS\Model\Composite as CompositeModel;
use DS\Core\Controller\WebController;
use DS\Model\Favorite;
use Slim\Http\Request;
use Slim\Http\Response;
use DS\Model\Diamond;
use DS\Model\Product;
use DS\Model\Vendor;

/**
 * Class Front
 * @package DS\Controller\Web
 */
final class Composite extends WebController {

  /**
   * Display details
   *
   * @param Request $request
   * @param Response $response
   * @param $args
   * @return \Psr\Http\Message\ResponseInterface
   */
  public function detailsAction(Request $request, Response $response, $args)
  {
    $getVars = $request->getQueryParams();
    $filter = [
      'product' => [],
      'pendant' => [],
      'diamond' => [],
    ];
    if (!empty($getVars['pid'])) {
      $filter['product']['_id'] = $getVars['pid'];
      if (!empty($getVars['pwa'])) {
        $filter['product']['withAttributes'] = [];
        foreach (explode('&', $getVars['pwa']) as $attr) {
          $parts = explode('=', urldecode($attr));
          $filter['product']['withAttributes'][$parts[0]] = $parts[1];
        }
      }
    }
    if (!empty($getVars['nid'])) {
      $filter['pendant']['_id'] = $getVars['nid'];
    }
    if (!empty($getVars['did'])) {
      $filter['diamond']['_id'] = $getVars['did'];
    }

    $Composite = new CompositeModel($this->mongodb);
    $composite = $Composite->getDetails($filter);
    if (!isset($composite->diamond) || (!isset($composite->product) && !isset($composite->pendant)))
      return $response->withRedirect('/jewelry/all?builder=1');

    // ugly way for accessing request attributes
    $this->request = $request;
    $user = $request->getAttribute('user');
    $userId = $user ? $user->_id : null;

    $Diamond = new Diamond($this->mongodb);
    $Product = new Product($this->mongodb);

    $product = $composite->product;
    $product->shippingDetails = $Product->getShippingDetails($product, -5);

    $diamond = $composite->diamond;
    $diamond->shippingDetails = $Diamond->getShippingDetails($diamond, -5);

    $composite->shippingDetails = $Composite->getShippingDetails($composite, -5);

    $compositeJson = clone $composite;
    $compositeJson->group = 'composite';

    $vendor = (new Vendor($this->mongodb))->getOneWhere(['code' => strtolower($composite->diamond->vendor)]);

    $data = [
      'isBuilder' => true,
      'composite' => $composite,
      'isFavorite' => (new Favorite($this->mongodb, $userId))->isFavorite('composites', $composite),
      'compositeJson' => json_encode($compositeJson),
      'showImages' => $vendor->showImages,
    ];
    

    return $this->render($response, 'pages/frontend/composite/complete/index.twig', $data);
  }
}