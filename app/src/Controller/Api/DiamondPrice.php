<?php

namespace DS\Controller\Api;

use DS\Core\Controller\ApiController;
use DS\Model\DiamondPrice as DiamondPriceModel;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class DiamondPrice
 * @package DS\Controller\Api
 */
final class DiamondPrice extends ApiController
{
  /**
   * Default controller construct
   * @param \Slim\Container $c Slim App Container
   *
   * @throws \Interop\Container\Exception\ContainerException
   */
  public function __construct(\Slim\Container $c)
  {
    parent::__construct($c);
    $this->model = new DiamondPriceModel($c->mongodb);
  }

  /**
   * Get all entities
   *
   * @param Request $request
   * @param Response $response
   * @param $args
   *
   * @return Response
   * @throws \Exception
   */
  public function treeAction(Request $request, Response $response, $args)
  {
    return parent::allAction($request, $response, $args);
  }

  /**
   * Get all entities
   *
   * @param Request $request
   * @param Response $response
   * @param $args
   *
   * @return Response
   * @throws \Exception
   */
  public function allAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $filter = $this->filterFromQuery();

    $search = empty($filter['search']) ? [] : array_fill_keys($this->model->search_fields, new Regex($filter['search'], 'i'));
    if ($filter['page']) $filter['offset'] = ($filter['page'] - 1) * $filter['limit'];

    return $response->withJson($this->model->all(
      $filter['limit'],
      $filter['offset'],
      $search
    ));
  }

  /**
   * Create new entity
   *
   * @param Request $request
   * @param Response $response
   * @param $args
   *
   * @return Response
   *
   */
  public function createAction(Request $request, Response $response, $args)
  {
    $d = 'multipart/form-data' == $request->getMediaType()
      ? json_decode($request->getParsedBodyParam('json', '{}'), true)
      : $request->getParsedBody();

    $this->logger->info("Diamond price PARAMS:\n" . json_encode($d));

    // TODO: code, type, value etc should be validated by JSON Schema
    if ($this->model->isExistWhere(['code' => $d['code']])) {
      return $this->errorResponse($response, ApiController::NOT_UNIQUE_CODE, 'such code already exists');
    }

    if ($this->model->RangeCrossed($d)) {
      return $this->errorResponse($response, ApiController::NOT_UNIQUE_CODE, 'range can not crossed other range');
    }

    return parent::createAction($request, $response, $args);
  }

  /**
   * Update exists entity
   *
   * @param Request $request
   * @param Response $response
   * @param $args
   *
   * @return Response
   *
   * @throws ContainerException
   */
  public function updateAction(Request $request, Response $response, $args)
  {
    $d = 'multipart/form-data' == $request->getMediaType()
      ? json_decode($request->getParsedBodyParam('json', '{}'), true)
      : $request->getParsedBody();

    $price = $this->model->findById($args['id']);

    $find = [
      '_id' => ['$ne' => new ObjectId($args['id'])]
    ];

    if (
      isset($d['code']) &&
      $price->code !== $d['code'] &&
      $this->model->isExistWhere(array_merge($find, ['code' => $d['code']]))
    ) {
      return $this->errorResponse($response, ApiController::NOT_UNIQUE_CODE, 'such code already exists');
    }

    return parent::updateAction($request, $response, $args);
  }

}
