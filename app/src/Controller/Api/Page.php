<?php

namespace DS\Controller\Api;

use DS\Core\Controller\ApiController;
use DS\Model\Page as PageModel;
use MongoDB\BSON\ObjectId;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class Pages
 * @package DS\Controller\Api
 */
final class Page extends ApiController
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
    $this->model = new PageModel($c->mongodb);
  }

  public function createAction(Request $request, Response $response, $args)
  {
    $d = 'multipart/form-data' == $request->getMediaType()
      ? json_decode($request->getParsedBodyParam('json', '{}'), true)
      : $request->getParsedBody();

    // TODO: url etc should be validated by JSON Schema
    if ($this->model->isExistWhere(['url' => $d['url']]))
      return $this->errorResponse($response, ApiController::NOT_UNIQUE_URL, 'such url already exists');

    return parent::createAction($request, $response, $args);
  }

  public function updateAction(Request $request, Response $response, $args)
  {
    $d = 'multipart/form-data' == $request->getMediaType()
      ? json_decode($request->getParsedBodyParam('json', '{}'), true)
      : $request->getParsedBody();

    // TODO: url etc should be validated by JSON Schema
    if (isset($d['url']) && $this->model->isExistWhere(['url' => $d['url'], '_id' => ['$ne' => new ObjectId($args['id'])]]))
      return $this->errorResponse($response, ApiController::NOT_UNIQUE_URL, 'such url already exists');

    return parent::updateAction($request, $response, $args);
  }

  /**
   * @param $d
   * @param array $uploadedFiles
   * @param null $entity_id
   * @throws \Exception
   */
  protected function doUploadFile(&$d, array $uploadedFiles, $entity_id = null): void
  {
    $basePath = "";

    if ($this->settings['images']['from_root'])
      $basePath .= $_SERVER['DOCUMENT_ROOT'];

    $basePath .= $this->settings['images']['pages']['filesystem'];
    if (!file_exists($basePath))
      mkdir($basePath);

    $basePath .= $entity_id . '/';
    $webPath = $this->settings['images']['pages']['web'] . $entity_id . '/';

    if (!file_exists($basePath))
      mkdir($basePath);

    $item = $this->model->getOneWhere(['_id' => $entity_id]);

    if (!$item->images)
      $item->images = [];

    foreach ($uploadedFiles as $key => $f) {
      $filename = str_replace(' ', '_', $f->getClientFilename());

      if (in_array($webPath . $filename, array_map(function($image){
        return $image->value;
      }, $item->images))) continue;

      $f->moveTo($basePath . $filename);
      $item->images[] = (object) ['type' => 'img', 'value' => $webPath . $filename];
    }

    $this->model->updateWhere(['images' => $item->images], ['_id' => $entity_id]);
  }
}