<?php

namespace DS\Controller\Api;

use DS\Core\Controller\ApiController;
use DS\Model\Diamond;
use DS\Model\Vendor as VendorModel;
use MongoDB\BSON\Regex;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class Vendor
 * @package DS\Controller\Api
 */
final class Vendor extends ApiController
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
    $this->model = new VendorModel($c->mongodb);
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

    $Diamond = new Diamond($this->mongodb);
    $res = $this->model->all($filter['limit'], $filter['offset'], $search);
    $res['records'] = array_map(function($vendor) use($Diamond){
      $vendor->count = $Diamond->countDocuments(['vendor' => $vendor->code]);
      $vendor->countDisabled = $Diamond->countDocuments(['vendor' => $vendor->code, 'isEnabled' => false]);
      return $vendor;
    }, $res['records']);

    return $response->withJson($res);
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

    $this->logger->info("VENDOR PARAMS:\n" . json_encode($d));

    $code = strtolower(preg_replace('/\s*/', '', $d['name']));
    // TODO: code, type, value etc should be validated by JSON Schema
    if ($this->model->isExistWhere(['code' => $code])) {
      return $this->errorResponse($response, ApiController::NOT_UNIQUE_CODE, 'such vendor already exists');
    }
    if (empty($d['folder'])) {
      return $this->errorResponse($response, ApiController::INCORRECT_DATA, 'folder should not be empty');
    }

    $request = $request->withParsedBody(array_merge($d, [
      'type' => 'independent',
      'code' => $code,
    ]));

    return parent::createAction($request, $response, $args);
  }

}
