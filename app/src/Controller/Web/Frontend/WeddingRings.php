<?php

namespace DS\Controller\Web\Frontend;

use DS\Core\Controller\WebController;
use DS\Model\Metal;
use DS\Model\WeddingWomen;
use DS\Model\WeddingMen;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class Front
 * @package DS\Controller\Web
 */
final class WeddingRings extends WebController {

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

    $weddingmens = (new WeddingMen($this->mongodb))->find();
    $weddingwomens = (new WeddingWomen($this->mongodb))->find();
    $metals = (new Metal($this->mongodb))->find();

    return $this->render(
      $response, 'pages/frontend/wedding_rings/index.twig',
      ['metals' => $metals, 'weddingwomens' => $weddingwomens, 'weddingmens' => $weddingmens, 'isWeddingRingsSection' => true]);
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
  public function searchAction(Request $request, Response $response, $args) {
    // ugly way for accessing request attributes
    $this->request = $request;

    $weddingmens = (new WeddingMen($this->mongodb))->find();
    $weddingwomens = (new WeddingWomen($this->mongodb))->find();
    $metals = (new Metal($this->mongodb))->find();

    $filter = [
      'weddingmen' => null,
      'weddingwomen' => null,
      'metal' => null,
      'price_min' => 0,
      'price_max' => 100,
      'sort_by' => null,
      'offset' => 0,
      'limit' => 10,
    ];

    $getVars = $request->getQueryParams();

    if (!empty($args['filter'])) {
      $filter_input = explode('_', $args['filter']);

      if (count($filter_input) == 2
        && isset($filter_input[0])
        && array_key_exists($filter_input[0], $filter)) {
        $filter[$filter_input[0]] = str_replace('-', ' ', $filter_input[1]);
      }
    }

    foreach ($getVars as $key => $value)
      if (array_key_exists($key, $filter))
        $filter[$key] = $value;

    if (!isset($filter['sort_by']))
      $filter['sort_by'] = $this->possibleSort[0]['code'];


    return $this->render(
      $response, 'pages/frontend/wedding_rings/search.twig',
      [
        'metals' => $metals,
        'weddingmens' => $weddingmens,
        'weddingwomens' => $weddingwomens,
        'possibleSort' => $this->possibleSort,
        'filter' => $filter,
        'isWeddingRingsSection' => true
      ]);
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

    return $this->render($response, 'pages/frontend/wedding_rings/details.twig', ['isWeddingRingsSection' => true]);
  }
}