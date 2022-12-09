<?php

namespace DS\Controller\Web\Frontend;

use DS\Core\Controller\WebController;
use Slim\Http\Request;
use Slim\Http\Response;
use DS\Model\Education as EducationModel;

/**
 * Class Front
 * @package DS\Controller\Web
 */
final class Education extends WebController {

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

    $education = (new EducationModel($this->mongodb))->find(['isEnabled' => true, 'parent_id' => null]);

    return $this->render($response, 'pages/frontend/education/index.twig', [
      'education' => $education,
      'isEducationSection' => true
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

    $tree = (new EducationModel($this->mongodb))->getTree(['isEnabled' => true]);
    $parent = null;
    foreach ($tree as $item)
      if ($item->url === $args['filter'])
        $parent = $item;

    if (empty($parent))
      return $this->render($response->withStatus(404), 'pages/frontend/404.twig');

    $filter = ['first' => $args['filter']];
    if (!empty($args['secondfilter'])) {
      $filter['second'] = $args['secondfilter'];
    }
    if (!empty($args['thirdfilter'])) {
      $filter['third'] = $args['thirdfilter'];
    }
    return $this->render($response, 'pages/frontend/education/page.twig', [
      'education' => $tree,
      'filter' => $filter,
      'isEducationSection' => true
    ]);
  }
}