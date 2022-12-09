<?php

namespace DS\Controller\Api;

use DS\Core\Controller\ApiController;
use DS\Model\StaticPages;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class Pages
 * @package DS\Controller\Api
 */
final class StaticPage extends ApiController
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
    $this->model = new \DS\Model\StaticPages ($c->mongodb);
  }

  /**
   * Create new entity
   *
   * @param Request $request
   * @param Response $response
   * @param          $args
   *
   * @return Response
   *
   */
  public function createAction(Request $request, Response $response, $args)
  {
    $d = 'multipart/form-data' == $request->getMediaType()
      ? json_decode($request->getParsedBodyParam('json', '{}'), true)
      : $request->getParsedBody();
    // TODO: title etc should be validated by JSON Schema
    if ($this->model->isExistWhere([
      'title' => $d['title']
    ]))
      return $this->errorResponse($response, ApiController::NOT_UNIQUE_TITLE, 'such title already exists');

    return parent::createAction($request, $response, $args);
  }
}