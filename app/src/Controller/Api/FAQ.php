<?php

namespace DS\Controller\Api;

use DS\Core\Controller\ApiController;
use DS\Model\FAQs as FAQModel;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class FAQ
 * @package DS\Controller\Api
 */
final class FAQ extends ApiController
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
    $this->model = new FAQModel($c->mongodb);
  }

  public function createQuestionAction(Request $request, Response $response, $args)
  {
    return $response->withJson([
      '_id' => $this->model->createSubdocument($args['faqid'], 'questions', $request->getParsedBody())
    ]);
  }

  public function getQuestionAction(Request $request, Response $response, $args)
  {
    return $response->withJson($this->model->getSubdocumentsById('questions', $args['id']));
  }

  public function updateQuestionAction(Request $request, Response $response, $args)
  {
    $d = $request->getParsedBody();

    if (empty($d))
      return $this->errorResponse($response, ApiController::NOTHING_TO_UPDATE,
        'at least one field should be changed');

    $entity_id = $args['id'];

    if (!$this->model->getSubdocumentsById('questions', $args['id']))
      return $this->errorResponse($response, ApiController::UNKNOWN_ENTITY_ID, 'unknown entity id');

    $this->model->updateSubdocumentsById('questions', $args['id'], $d);

    return $response->withJson(['_id' => $entity_id]);
  }

  public function deleteQuestionAction(Request $request, Response $response, $args)
  {
    $this->model->deleteSubdocumentsById('questions', $args['id']);

    return $this->emptyJson($response);
  }
}
