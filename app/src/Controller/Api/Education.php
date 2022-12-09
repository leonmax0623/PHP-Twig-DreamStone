<?php

namespace DS\Controller\Api;

use DS\Core\Controller\ApiController;
use DS\Model\Diamond;
use DS\Model\Education as EducationModel;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class Education
 * @package DS\Controller\Api
 */
final class Education extends ApiController
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
    $this->model = new EducationModel($c->mongodb);
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

    $records = $this->model->getTree($search);
    $parent_id = $request->getQueryParam('parent_id');

    if (!$parent_id)
      return $response->withJson(['records' => $records, 'total' => count($records)]);

    foreach ($records as $record)
      if ($record->_id === $parent_id)
        return $response->withJson(['records' => $record->children, 'total' => count($record->children)]);
      else
        foreach ($record->children as $child)
          if ($child->_id === $parent_id)
            return $response->withJson(['records' => $child->children, 'total' => count($child->children)]);

    return $response->withJson(['records' => [], 'total' => 0]);
  }

  public function createAction(Request $request, Response $response, $args)
  {
    $d = 'multipart/form-data' == $request->getMediaType()
      ? json_decode($request->getParsedBodyParam('json', '{}'), true)
      : $request->getParsedBody();

    $d['parent_id'] = empty($d['parent_id']) ? null : new ObjectId($d['parent_id']);

    return parent::createAction($request->withParsedBody($d), $response, $args);
  }

  public function updateAction(Request $request, Response $response, $args)
  {
    $d = 'multipart/form-data' == $request->getMediaType()
      ? json_decode($request->getParsedBodyParam('json', '{}'), true)
      : $request->getParsedBody();

    $d['parent_id'] = empty($d['parent_id']) ? null : new ObjectId($d['parent_id']);

    return parent::updateAction($request->withParsedBody($d), $response, $args);
  }

}