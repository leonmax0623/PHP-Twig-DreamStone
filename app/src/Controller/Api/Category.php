<?php

namespace DS\Controller\Api;

use DS\Core\Controller\ApiController;
use DS\Model\Category as CategoryModel;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class Category
 * @package DS\Controller\Api
 */
final class Category extends ApiController
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
    $this->model = new CategoryModel($c->mongodb);
  }

  /**
   * Get all entities
   *
   * @param Request $request
   * @param Response $response
   * @param          $args
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
   * @param          $args
   *
   * @return Response
   * @throws \Exception
   */
  public function allAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $filter = $this->filterFromQuery();
    $parent_id = $request->getQueryParam('parent_id');

    $search = empty($filter['search']) ? [] : array_fill_keys($this->model->search_fields, new Regex($filter['search'], 'i'));
    if ($filter['page']) $filter['offset'] = ($filter['page'] - 1) * $filter['limit'];

    if ($parent_id)
      $search['$and'] = ['parent_id' => new \MongoDB\BSON\ObjectID($parent_id)];
    else
      $search['$and'] = ['level' => 0];

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

    // TODO: url, title etc should be validated by JSON Schema
    if ($this->model->isExistWhere(['url' => $d['url']]))
      return $this->errorResponse($response, ApiController::NOT_UNIQUE_URL, 'such url already exists');

    if ($this->model->isExistWhere([
      'parent_id' => isset($d['parent_id']) ? new ObjectId($d['parent_id']) : null, // check on the same level
      'title' => $d['title']
    ]))
      return $this->errorResponse($response, ApiController::NOT_UNIQUE_TITLE, 'such title already exists');

    return parent::createAction($request, $response, $args);
  }

  /**
   * Update exists entity
   *
   * @param Request $request
   * @param Response $response
   * @param          $args
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

    $category = $this->model->findById($args['id']);

    $find = [
      '_id' => ['$ne' => new ObjectId($args['id'])]
    ];

    if (
      isset($d['url']) &&
      $category->url !== $d['url'] &&
      $this->model->isExistWhere(array_merge($find, ['url' => $d['url']]))
    )
      return $this->errorResponse($response, ApiController::NOT_UNIQUE_URL, 'such url already exists');

    if (
      isset($d['title']) &&
      $category->title !== $d['title'] &&
      $this->model->isExistWhere(array_merge($find, [
        'title' => $d['title'],
        'parent_id' => empty($category->parent_id) ? null : $category->parent_id // check on the same level
      ]))
    )
      return $this->errorResponse($response, ApiController::NOT_UNIQUE_TITLE, 'such title already exists');

    return parent::updateAction($request, $response, $args);
  }

  /**
   * @param $d
   * @param array $uploadedFiles
   * @param null $entity_id
   */
  protected function doUploadFile(&$d, array $uploadedFiles, $entity_id = null): void
  {
    $basePath = "";

    if ($this->settings['images']['from_root'])
      $basePath .= $_SERVER['DOCUMENT_ROOT'];

    $basePath .= $this->settings['images']['categories']['filesystem'].$entity_id.'/';
    $webPath = $this->settings['images']['categories']['web'].$entity_id.'/';

    if (!file_exists($basePath))
      mkdir($basePath);

    if ($entity_id) {
      $category = $this->model->getOneWhere(["_id" => $entity_id]);

      if (!$category->images)
        $category->images = [];

      foreach ($uploadedFiles as $f) {
        $filename = $f->getClientFilename();
        $f->moveTo($basePath.$filename);

        if (!in_array($webPath.$filename, $category->images))
          $category->images[] = $webPath.$filename;
      }

      $this->model->updateWhere([
                                  'images' => $category->images,
                                  'has_images' => count($category->images) > 0
                                ], ["_id" => $entity_id]);
    }
  }
}