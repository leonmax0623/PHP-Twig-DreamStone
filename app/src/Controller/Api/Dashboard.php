<?php

namespace DS\Controller\Api;

use DS\Core\Controller\ApiController;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class Dashboard
 * @package DS\Controller\Api
 */
final class Dashboard extends ApiController
{
  /**
   * Return short system state
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   * @throws \Exception
   */
  public function getAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    return $this->renderJson($response,
      [
        'categories_total' => (new \DS\Model\Category($this->mongodb))->count(),
        'products_total' => (new \DS\Model\Product($this->mongodb))->count(),
        'faqs_total' => (new \DS\Model\FAQs($this->mongodb))->count(),
        'admins_total' => (new \DS\Model\Admin($this->mongodb))->count(),

      ]);
  }
}