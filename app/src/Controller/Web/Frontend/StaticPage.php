<?php

namespace DS\Controller\Web\Frontend;

use DS\Core\Controller\WebController;
use DS\Model\StaticPages;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class Front
 * @package DS\Controller\Web
 */
final class StaticPage extends WebController {

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

    $staticpages = (new StaticPages($this->mongodb))->find();

    return $this->render($response, 'pages/frontend/staticpages/index.twig', [
      'staticpages' => $staticpages,
      'isStaticPagesSection' => true
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

    $staticpages = (new StaticPages($this->mongodb))->find();

   if (!empty($args['filter'])) {
      $filter = $args;
    }
    return $this->render($response, 'pages/frontend/staticpages/page.twig', [
      'staticpages' => $staticpages,
      'filter' => $filter,
      'isStaticPagesSection' => true
    ]);
  } 
}