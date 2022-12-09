<?php

namespace DS\Controller\Api;

use DS\Core\Controller\ApiController;
use DS\Model\Content as ContentModel;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class Content
 * @package DS\Controller\Api
 */
final class Content extends ApiController
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
    $this->model = new ContentModel($c->mongodb);
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

    $res = $this->model->allWhere(['name' => $args['area']]);
    $records = empty($res[0]->items) ? [] : $res[0]->items;

    return $response->withJson(['records' => $records]);
  }

  public function getAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $item = $this->model->getSubdocumentsById('items', $args['id']);

    return $response->withJson($item);
  }

  public function updateAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $uploadMode = 'multipart/form-data' == $request->getMediaType();

    if ($uploadMode)
      $d = json_decode($request->getParsedBodyParam('json', '{}'), true);
    else
      $d = $request->getParsedBody();

    $uploadedFiles = $request->getUploadedFiles();

    if (empty($d) && empty($uploadedFiles))
      return $this->errorResponse($response, ApiController::NOTHING_TO_UPDATE,
        'at least one field should be changed'.($uploadMode ? ' or file upload' : ''));

    $entity_id = $args['id'];

    if (!empty($uploadedFiles['file']) && $uploadedFiles['file']->getError() !== UPLOAD_ERR_OK)
      return $this->errorResponse($response, ApiController::UPLOAD_ISSUE, 'cannot upload file');

    $this->model->updateSubdocumentsById('items', $entity_id, [ // NOTE: should be before doUploadFile
      'content' => $d['content'],
      'images' => $d['images'],
    ]);
    if (!empty($uploadedFiles))
      $this->doUploadFile($d, $uploadedFiles, $entity_id);

    return $this->emptyJson($response);
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

    $basePath .= $this->settings['images']['content']['filesystem'];
    if (!file_exists($basePath))
      mkdir($basePath);

    $basePath .= $entity_id . '/';
    $webPath = $this->settings['images']['content']['web'] . $entity_id . '/';

    if (!file_exists($basePath))
      mkdir($basePath);

    $item = $this->model->getSubdocumentsById('items', $entity_id);

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

    $this->model->updateSubdocumentsById('items', $entity_id, [
      'images' => $item->images
    ]);
  }

  public function resetAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $item = $this->model->getSubdocumentsById('items', $args['id']);
    if ($item) {
      $filepath = $this->settings['view']['template_path'] . '/' . $item->file;
      if (file_exists($filepath)) {
        $this->model->updateSubdocumentsById('items', $args['id'], [
          'content' => file_get_contents($filepath)
        ]);
      }
    }

    return $this->emptyJson($response);
  }

}
