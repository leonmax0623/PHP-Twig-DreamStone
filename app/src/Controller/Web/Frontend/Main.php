<?php

namespace DS\Controller\Web\Frontend;

use DS\Core\Controller\WebController;
use Slim\Http\Request;
use DS\Model\Shape;
use DS\Model\Viewed;
use Slim\Http\Response;
use DS\Model\Product;

/**
 * Class Front
 * @package DS\Controller\Web
 */
final class Main extends WebController
{
  /**
   * Home renderer
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   */
  public function homeAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    // Add a debug bar message
    if (!empty($this->debugbar)) {
      $this->debugbar['messages']->info('Welcome to front controller');
    }

    // Log an info
    $this->logger->info("Home page action dispatched");
    $shapes = (new Shape($this->mongodb))->find();

    $Viewed = new Viewed($this->mongodb);

    // Render
    return $this->render($response, 'pages/frontend/home/index.twig', [
      'shapes' => $shapes,
      'viewed' => [
        'products' => $Viewed->get('products'),
        'diamonds' => $Viewed->get('diamonds'),
      ],
    ]);
  }

  /**
   * Loose Diamonds renderer
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   */
  public function looseDiamondsAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    // Render
    return $this->render($response, 'pages/frontend/loose_diamonds/index.twig');
  }

  /**
   * Wedding Rings renderer
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   */
  public function weddingRingsAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    // Render
    return $this->render($response, 'pages/frontend/wedding_rings/index.twig');
  }

  /**
   * Gemstones renderer
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   */
  public function gemstonesAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    // Render
    return $this->render($response, 'pages/frontend/gemstones/index.twig');
  }

  /**
   * Education renderer
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   */
  public function educationAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    // Render
    return $this->render($response, 'pages/frontend/education/index.twig');
  }

  /**
   * WishList renderer
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   */
  public function viewedAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $Viewed = new Viewed($this->mongodb);

    $products = $Viewed->get('products');

    $Product = new Product($this->mongodb);
    $products = array_map(function ($product) use ($Product) {
      $product->withAttributes = $Product->getSelectedAttributes($product);
      $product->images = $Product->getImages($product);
      $product->title = $Product->getTitle($product);
      return $product;
    }, $products);

    return $this->render($response, 'pages/frontend/viewed/index.twig', [
      'products' => $products,
      // 'products' => $Viewed->get('products'),
      'diamonds' => $Viewed->get('diamonds'),
    ]);
  }

  /**
   * WishList renderer
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   */
  public function WishListAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    // Render
    return $this->render($response, 'pages/frontend/wish_list/index.twig');
  }

  /**
   * Order renderer
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   */
  public function OrderAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    // Render
    return $this->render($response, 'pages/frontend/order/index.twig');
  }
}
