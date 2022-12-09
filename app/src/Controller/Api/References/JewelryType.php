<?php

namespace DS\Controller\Api\References;

use DS\Core\Controller\ApiController;
use DS\Model\JewelryType as JewelryTypeModel;
use DS\Model\JewelryTypeStyle;
use DS\Model\Product;
use MongoDB\BSON\ObjectId;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class Jewelry Type
 * @package DS\Controller\Api
 */
final class JewelryType extends ApiController
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
    $this->model = new JewelryTypeModel($c->mongodb);
  }

  /**
   * Get all entities
   *
   * @param Request $request
   * @param Response $response
   * @param          $args
   *
   * @return Response
   */
  public function allAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $filter = $this->filterFromQuery();
    if ($filter['page']) $filter['offset'] = ($filter['page'] - 1) * $filter['limit'];

    $JewelryTypeStyle = new JewelryTypeStyle($this->mongodb);
    $res = $this->model->all($filter['limit'], $filter['offset']);
    $res['records'] = array_map(function ($jewelrytype) use ($JewelryTypeStyle) {
      $jewelrytype->styles_count = $JewelryTypeStyle->countDocuments([
        'jewelrytype_id' => new ObjectId($jewelrytype->_id)
      ]);
      return $jewelrytype;
    }, $res['records']);

    return $response->withJson($res);
  }

  public function deleteAction(Request $request, Response $response, $args)
  {
    if (!$this->model->isEditable($args['id']))
      return $this->errorResponse($response, ApiController::INCORRECT_DATA, 'this type cannot be deleted');

    if (!$this->model->isExistWhere(['_id' => $args['id']]))
      return $this->errorResponse($response, ApiController::UNKNOWN_ENTITY_ID, 'unknown entity id');

    (new Product($this->mongodb))->updateMany(
      ['jewelrytype_id' => new ObjectId($args['id'])],
      ['$set' => [
        'jewelrytype_id' => new ObjectId($this->model->getGeneralTypeId()),
        'jewelrytypestyle_id' => []
      ]]
    );

    (new JewelryTypeStyle($this->mongodb))->deleteMany(['jewelrytype_id' => new ObjectId($args['id'])]);

    $this->model->deleteWhere(['_id' => $args['id']]);

    return $this->emptyJson($response);
  }
}
