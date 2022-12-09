<?php

namespace DS\Controller\Api;

use MongoDB\BSON\Decimal128;
use MongoDB\BSON\ObjectId;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;
use DS\Core\Controller\ApiController;
use DS\Model\Attribute as AttributeModel;
use DS\Model\Product;
use DS\Model\Category;

/**
 * Class Attribute
 * @package DS\Controller\Api
 */
final class Attribute extends ApiController
{
  /**
   * Default controller construct
   * @param Container $c Slim App Container
   *
   * @throws \Interop\Container\Exception\ContainerException
   */
  public function __construct(Container $c)
  {
      parent::__construct($c);
      $this->model = new AttributeModel($c->mongodb);
  }

  public function createValueAction(Request $request, Response $response, $args)
  {
    $d = $request->getParsedBody();
    $id = $this->model->createSubdocument($args['attribute_id'], 'values', $d);

    if (!empty($d['isEnabled'])) {
      // add new Attribute's value into Products and Categories
      $find = ['attributes._id' => new ObjectId($args['attribute_id'])];
      $update = ['$push' => ['attributes.$.values' => [
        '_id' => new ObjectId($id),
        'name' => $d['name'],
        'isEnabled' => false,
        'price' => new Decimal128('0'),
      ]]];
      (new Category($this->mongodb))->updateMany($find, $update);
      $update['$push']['attributes.$.values']['isDefault'] = false;
      $update['$push']['attributes.$.values']['includeDefaultImages'] = false;
      $update['$push']['attributes.$.values']['images'] = [];
      (new Product($this->mongodb))->updateMany($find, $update);
    }

    return $response->withJson(['_id' => $id]);
  }

  public function getValueAction(Request $request, Response $response, $args)
  {
    return $response->withJson($this->model->getSubdocumentsById('values', $args['id']));
  }

  public function updateValueAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $d = $request->getParsedBody();
    if (empty($d))
      return $this->errorResponse($response, ApiController::NOTHING_TO_UPDATE,
        'at least one field should be changed');

    $value = $this->model->getSubdocumentsById('values', $args['id']);
    if (!$value)
      return $this->errorResponse($response, ApiController::UNKNOWN_ENTITY_ID, 'unknown entity id');

    $this->model->updateSubdocumentsById('values', $args['id'], $d);

    $find = ['attributes._id' => new ObjectId($args['attribute_id'])];
    $Product = new Product($this->mongodb);
    $Category = new Category($this->mongodb);
    if (isset($d['isEnabled'])) {
      $update = $d['isEnabled']
        // add new Attribute's value into Products and Categories
        ? ['$push' => ['attributes.$.values' => [
          '_id' => new ObjectId($args['id']),
          'name' => empty($d['name']) ? $value->name : $d['name'],
          'isEnabled' => false,
          'price' => new Decimal128('0'),
        ]]]
        // delete Attribute's value from Products and Categories
        : ['$pull' => ['attributes.$.values' => [
          '_id' => new ObjectId($args['id'])
        ]]];
      $Category->updateMany($find, $update);
      if ($d['isEnabled']) {
        $update['$push']['attributes.$.values']['isDefault'] = false;
        $update['$push']['attributes.$.values']['includeDefaultImages'] = false;
        $update['$push']['attributes.$.values']['images'] = [];
      }
      $Product->updateMany($find, $update);
    } else {
      // update Attribute's value's name in Products and Categories
      $update = ['$set' => ['attributes.$.values.$[value].name' => $d['name']]];
      $filter = ['arrayFilters' => [['value._id' => new ObjectId($args['id'])]]];
      $Product->updateMany($find, $update, $filter);
      $Category->updateMany($find, $update, $filter);
    }

    return $response->withJson(['_id' => $args['id']]);
  }

  public function deleteValueAction(Request $request, Response $response, $args)
  {
    $this->model->deleteSubdocumentsById('values', $args['id']);

    // delete Attribute's value from Products and Categories
    $find = ['attributes._id' => new ObjectId($args['attribute_id'])];
    $update = ['$pull' => ['attributes.$.values' => ['_id' => new ObjectId($args['id'])]]];
    (new Product($this->mongodb))->updateMany($find, $update);
    (new Category($this->mongodb))->updateMany($find, $update);

    return $this->emptyJson($response);
  }

  public function updateAction(Request $request, Response $response, $args)
  {
    $d = $request->getParsedBody();

    $attribute = $this->model->findById($args['id']);
    if (!$attribute)
      return $this->errorResponse($response, ApiController::UNKNOWN_ENTITY_ID, 'unknown entity id');

    $Product = new Product($this->mongodb);
    $Category = new Category($this->mongodb);
    if (isset($d['isEnabled']) && !$d['isEnabled'] && $attribute->isEnabled) {
      // delete Attribute from Products and Categories
      $Product->deleteSubdocumentsById('attributes', $args['id']);
      $Category->deleteSubdocumentsById('attributes', $args['id']);
    }
    if (!empty($d['name']) && $d['name'] !== $attribute->name) {
      // update Attribute's name in Products and Categories
      $Product->updateSubdocumentsById('attributes', $args['id'], ['name' => $d['name']]);
      $Category->updateSubdocumentsById('attributes', $args['id'], ['name' => $d['name']]);
    }

    return parent::updateAction($request, $response, $args);
  }

  public function deleteAction(Request $request, Response $response, $args)
  {
    if (!$this->model->isExistWhere(['_id' => $args['id']]))
      return $this->errorResponse($response, ApiController::UNKNOWN_ENTITY_ID, 'unknown entity id');

    // delete Attribute from Products and Categories
    (new Product($this->mongodb))->deleteSubdocumentsById('attributes', $args['id']);
    (new Category($this->mongodb))->deleteSubdocumentsById('attributes', $args['id']);

    return parent::deleteAction($request, $response, $args);
  }

}
