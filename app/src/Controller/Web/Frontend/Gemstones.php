<?php

namespace DS\Controller\Web\Frontend;

use DS\Core\Controller\WebController;
use Slim\Http\Request;
use Slim\Http\Response;
use DS\Model\GemstoneShape;
use DS\Model\GemstoneColor;



/**
 * Class Front
 * @package DS\Controller\Web
 */
final class Gemstones extends WebController {
  
  /**
   * @var array
   */
  private $possibleSort = [
    ['code' => 'price_down', 'title' => 'High to low'],
    ['code' => 'price_up', 'title' => 'Low to high']
  ];

  
  /**
   * Home renderer
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   */
  public function indexAction(Request $request, Response $response, $args) {
    // ugly way for accessing request attributes
    $this->request = $request;

    $gemstoneshapes = (new GemstoneShape($this->mongodb))->find();
    $gemstonecolors = (new GemstoneColor($this->mongodb))->find();
    

    return $this->render($response, 'pages/frontend/gemstones/index.twig',
                         ['gemstoneshapes' => $gemstoneshapes,
                           'gemstonecolors' => $gemstonecolors,
                           'isGemstonesSection' => true
                           ]);
  }

  /**
   * Filter display
   *
   * @param Request $request
   * @param Response $response
   * @param $args
   * @return \Psr\Http\Message\ResponseInterface
   */
  public function searchAction(Request $request, Response $response, $args) {
    // ugly way for accessing request attributes
    $this->request = $request;

    $gemstoneshapes = (new GemstoneShape($this->mongodb))->find();
    $gemstonecolors = (new GemstoneColor($this->mongodb))->find();

    $filter = [
      'price_min' => 0,
      'price_max' => 100,
      'carat_min' => 0.3,
      'carat_max' => 3.0,
      'gemstoneshape' => null,
      'gemstonecolor' => null,
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
      $response, 'pages/frontend/gemstones/search.twig',
      [
        'gemstoneshapes' => $gemstoneshapes,
        'gemstonecolors' => $gemstonecolors,
        'filter' => $filter,
        'isGemstonesSection' => true
      ]);
  }

  /**
   * Display gemstones details
   *
   * @param Request $request
   * @param Response $response
   * @param $args
   * @return \Psr\Http\Message\ResponseInterface
   */
  public function detailsAction(Request $request, Response $response, $args) {
    // ugly way for accessing request attributes
    $this->request = $request;

    return $this->render($response, 'pages/frontend/gemstones/details.twig', ['isGemstonesSection' => true]);
  }
}