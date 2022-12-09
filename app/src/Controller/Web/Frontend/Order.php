<?php

namespace DS\Controller\Web\Frontend;

use DS\Core\Controller\WebController;
use Slim\Http\Request;
use Slim\Http\Response;
use DS\Model\Order as OrderModel;

/**
 * Class Front
 * @package DS\Controller\Web
 */
final class Order extends WebController
{
  /**
   * My Orders renderer
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   */
  public function allAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $user = $request->getAttribute('user');

    return $this->render($response, 'pages/frontend/my_orders/index.twig', [
      'orders' => (new OrderModel($this->mongodb))->getByUser($user),
      'isOrdersSection' => true,
    ]);
  }

  /**
   * My Order renderer
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   */
  public function getAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $user = $request->getAttribute('user');
    $order = (new OrderModel($this->mongodb))->getByNumber($args['number'], $user);
    if (!$order) {
      return $response->withRedirect('/my-orders');
    }

    $order->products = array_map(function($product){
      $product->withAttributes = (array)$product->withAttributes;
      return $product;
    }, $order->products);

    $order->composite = array_map(function($composite){
      $composite->product->withAttributes = (array)$composite->product->withAttributes;
      return $composite;
    }, $order->composite);

    return $this->render($response, 'pages/frontend/my_orders/single/index.twig', [
      'order' => $order,
    ]);
  }

}