<?php

namespace DS\Core\Controller;

use Interop\Container\Exception\ContainerException;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\Client;
use Monolog\Logger;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class ApiController
 * Base API controller class with functions shared by all API controller implementations
 *
 * @package DS\Core\Controller
 */
class ApiController extends BaseController
{
  const ERROR_UNKNOWN_ADMIN = 2001;
  const ALREADY_EXISTS = 2002;
  const NOTHING_TO_UPDATE = 5001;
  const UPLOAD_ISSUE = 5002;
  const INCORRECT_DATA = 5003;
  const UNKNOWN_ENTITY_ID = 5004;
  const NOT_UNIQUE_URL = 5005;
  const NOT_UNIQUE_TITLE = 5006;
  const NOT_UNIQUE_CODE = 5007;


  /**
   * @var Logger Monolog Instance
   */
  protected $logger;

  /**
   * @var Client
   */
  protected $mongodb;

  /**
   * @var array Decoded token array
   */
  protected $token;

  /**
   * @var array|null|object The authenticated user
   */
  protected $user;

  /**
   * Model associated with this controller
   *
   * @var
   */
  protected $model;

  /**
   * Default controller construct
   *
   * @param Container $c Slim App Container
   * @throws ContainerException
   */
  public function __construct(Container $c)
  {
    parent::__construct($c);

    $this->logger = $c->get('logger');
    $this->mongodb = $c->get('mongodb');
    $this->mailer = $c->get('mailer');
  }

  /**
   * Get one entity
   *
   * @param Request $request
   * @param Response $response
   * @param          $args
   *
   * @return Response
   *
   * @throws ContainerException
   */
  public function getAction(Request $request, Response $response, $args)
  {
    return $response->withJson(
      $this->model->getOneWhere(['_id' => $args['id']])
    );
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

    return $response->withJson($this->model->all(
      $filter['limit'],
      $filter['offset'],
      empty($filter['search']) ? null : array_fill_keys($this->model->search_fields,
        new Regex($filter['search'], 'i'))
    ));
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

    if (!$this->model->isExistWhere(['_id' => $entity_id]))
      return $this->errorResponse($response, ApiController::UNKNOWN_ENTITY_ID, 'unknown entity id');

    if (!empty($uploadedFiles['file']) && $uploadedFiles['file']->getError() !== UPLOAD_ERR_OK) {
      return $this->errorResponse($response, ApiController::UPLOAD_ISSUE, 'cannot upload file');
    }

    $this->model->updateWhere($d, ['_id' => $entity_id]); // NOTE: should be before doUploadFile
    if (!empty($uploadedFiles)) {
      $this->doUploadFile($d, $uploadedFiles, $entity_id);
    }

    return $response->withJson(
      $this->model->getOneWhere(['_id' => $entity_id])
    );
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
    // ugly way for accessing request attributes
    $this->request = $request;

    if ('multipart/form-data' == $request->getMediaType())
      $d = json_decode($request->getParsedBodyParam('json', '{}'), true);
    else
      $d = $request->getParsedBody();

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


  /**
   * Delete one controller
   *
   * @param Request $request
   * @param Response $response
   * @param          $args
   *
   * @return Response
   *
   */
  public function deleteAction(Request $request, Response $response, $args)
  {
    if (!$this->model->isExistWhere(['_id' => $args['id']]))
      return $this->errorResponse($response, ApiController::UNKNOWN_ENTITY_ID, 'unknown entity id');

    $this->model->deleteWhere(['_id' => $args['id']]);

    return $this->emptyJson($response);
  }

  /**
   * Duplicate
   *
   * @param Request $request
   * @param Response $response
   * @param          $args
   *
   * @return Response
   *
   */
  public function duplicateAction(Request $request, Response $response, $args)
  {
    if (!$this->model->isExistWhere(['_id' => $args['id']]))
      return $this->errorResponse($response, ApiController::UNKNOWN_ENTITY_ID, 'unknown entity id');

    $id = $this->model->duplicate($args['id']);

    return $response->withJson(['_id' => $id->__toString()]);
  }


  /**
   * @return array
   */
  protected function filterFromQuery()
  {
    $query = $this->request->getQueryParams();

    return [
      'limit' => empty($query['limit']) ? null : (int)$query['limit'],
      'offset' => empty($query['offset']) ? null : (int)$query['offset'],
      'search' => empty($query['search']) ? null : $query['search'],
      'status' => empty($query['status']) ? null : $query['status'],
      'page' => empty($query['page']) ? null : $query['page'],
    ];
  }

  /**
   * @param $d
   * @param array $uploadedFiles
   * @param null $entity_id
   */
  protected function doUploadFile(&$d, array $uploadedFiles, $entity_id = null): void
  {
    // one uploaded file here $uploadedFiles['file']
  }

  /**
   * Render a JSON response
   *
   * @param Response $response Slim App Response
   * @param mixed $data The data
   * @param int $status The HTTP status code.
   * @param int $encodingOptions Json encoding options
   *
   * @return Response
   */
  protected function renderJson(Response $response, $data, $status = null, $encodingOptions = 0)
  {
    return $response->withJson($data, $status, $encodingOptions);
  }

  /**
   * Render a JSON response what contain error object
   *
   * @param Response $response Slim App Response
   * @param int $code Internal error code
   * @param string $msg Human-friendly error description
   *
   * @return Response
   */
  protected function errorResponse(Response $response, $code, $msg)
  {
    $this->logger->addWarning($code . ": " . $msg);

    return $response->withJson(["error" => ["code" => $code, "msg" => $msg]]);
  }

  /**
   * Quick render a empty JSON {}
   *
   * @param Response $response Slim App Response
   *
   * @return Response
   */
  protected function emptyJson(Response $response)
  {
    return $response->withHeader('Content-Type', 'application/json')->write('{}');
  }

  /**
   * Send to client (browser?) $text, end connection (client is disconnected) but not break code execution
   *
   * @param string $text
   * @return void
   */
  protected function endConnection(string $text)
  {
    while (ob_get_level())
      ob_end_clean();

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Content-Type: application/json");
    header('Connection: close');
    ignore_user_abort(true);
    set_time_limit(0);
    ob_start();
    echo($text);
    $size = ob_get_length();
    header("Content-Length: $size");
    ob_end_flush();
    flush();
  }

}