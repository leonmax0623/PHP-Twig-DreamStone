<?php

namespace DS\Controller\Web\Frontend;

use DS\Core\Controller\WebController;
use DS\Model\Page as PageModel;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class Front
 * @package DS\Controller\Web
 */
final class Page extends WebController {

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

    $pages = (new PageModel($this->mongodb))->find(['isEnabled' => true]);

    return $this->render($response, 'pages/frontend/pages/index.twig', [
      'pages' => $pages
    ]);
  }

  /**
   *  Filter display
   *
   * @param Request $request
   * @param Response $response
   * @param $args
   * @return \Psr\Http\Message\ResponseInterface
   */
  public function pageAction(Request $request, Response $response, $args) {
    // ugly way for accessing request attributes
    $this->request = $request;

    $page = (new PageModel($this->mongodb))->getOneWhere(['url' => $args['url'], 'isEnabled' => true]);

    if (!$page) {
      return $this->render($response, 'pages/frontend/jewelry/details/404.twig', ['text' => 'Not found']);
    }

    return $this->render($response, 'pages/frontend/pages/page.twig', [
      'page' => $page
    ]);
  } 
}