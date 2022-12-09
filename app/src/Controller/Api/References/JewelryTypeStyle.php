<?php

namespace DS\Controller\Api\References;

use DS\Core\Controller\ApiController;
use DS\Model\JewelryTypeStyle as JewelryTypeStyleModel;
use DS\Model\Product;
use MongoDB\BSON\ObjectId;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class Jewelry Type
 * @package DS\Controller\Api
 */
final class JewelryTypeStyle extends ApiController
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
    $this->model = new JewelryTypeStyleModel($c->mongodb);
  }

  public function allAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $query = $this->request->getQueryParams();
    if (empty($query['jewelrytype_id']))
      return $this->errorResponse($response, ApiController::UNKNOWN_ENTITY_ID, 'unknown entity id');

    $filter = $this->filterFromQuery();
    if ($filter['page']) $filter['offset'] = ($filter['page'] - 1) * $filter['limit'];

    return $response->withJson($this->model->all(
      $filter['limit'],
      $filter['offset'],
      ['jewelrytype_id' => new ObjectId($query['jewelrytype_id'])]
    ));
  }

  public function createAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $query = $this->request->getQueryParams();
    if (empty($query['jewelrytype_id']))
      return $this->errorResponse($response, ApiController::UNKNOWN_ENTITY_ID, 'unknown entity id');

    $d = json_decode($request->getParsedBodyParam('json', '{}'), true);
    $d['jewelrytype_id'] = new ObjectId($query['jewelrytype_id']);

    $id = $this->model->create($d, $this->request->getQueryParams());

    $uploadedFiles = $request->getUploadedFiles();
    if (!empty($uploadedFiles) && !empty($uploadedFiles['file'])) {
      if ($uploadedFiles['file']->getError() === UPLOAD_ERR_OK) {
        $this->doUploadFile($d, $uploadedFiles, $id);
      } else {
        return $this->errorResponse($response, ApiController::UPLOAD_ISSUE, 'cannot upload file');
      }
    }

    return $response->withJson($this->model->getOneWhere(['_id' => $id]));
  }

  public function deleteAction(Request $request, Response $response, $args)
  {
    if (!$this->model->isExistWhere(['_id' => $args['id']]))
      return $this->errorResponse($response, ApiController::UNKNOWN_ENTITY_ID, 'unknown entity id');

    (new Product($this->mongodb))->updateMany(
      ['jewelrytypestyle_id' => new ObjectId($args['id'])],
      ['$set' => [
        'jewelrytypestyle_id' => []
      ]]
    );

    $this->model->deleteWhere(['_id' => $args['id']]);

    return $this->emptyJson($response);
  }

  protected function doUploadFile(&$d, array $uploadedFiles, $entity_id = null): void
  {
    $basePath = "";
    if ($this->settings['images']['from_root'])
      $basePath .= $_SERVER['DOCUMENT_ROOT'];

    $basePath .= $this->settings['images']['categories']['filesystem'] . $entity_id . '/';
    $webPath = $this->settings['images']['categories']['web'] . $entity_id . '/';
    if (!file_exists($basePath))
      mkdir($basePath);

    if (!$entity_id || empty($uploadedFiles))
      return;

    foreach ($uploadedFiles as $f) {
      $filename = $f->getClientFilename();
      $f->moveTo($basePath . $filename);
      $this->model->updateWhere(['image' => $webPath . $filename], ['_id' => $entity_id]);
      return;
    }
  }
}
