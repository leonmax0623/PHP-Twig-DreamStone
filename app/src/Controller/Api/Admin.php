<?php

namespace DS\Controller\Api;

use DS\Core\Controller\ApiController;
use DS\Model\Group;
use DS\Model\Admin as AdminModel;
use DS\Model\Token as TokenModel;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class Admin
 * @package DS\Controller\Api
 */
final class Admin extends ApiController
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
    $this->model = new AdminModel($c->mongodb);
  }
  public function createAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $d = 'multipart/form-data' == $request->getMediaType()
      ? json_decode($request->getParsedBodyParam('json', '{}'), true)
      : $request->getParsedBody();

    if (empty($d['role'])) {
      $d['role'] = 'Product Manager';
    }

    return $this->model->isExistWhere(['email' => $d['email']])
      ? $this->errorResponse($response, ApiController::ALREADY_EXISTS, 'email already exists')
      : parent::createAction($request, $response, $args);
  }

  /**
   * Do login exists admin in system
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   * @throws \Exception
   */
  public function loginAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $d = $request->getParsedBody();

    $admin = (new AdminModel($this->mongodb))->findOne(['email' => $d['email']]);

    if (!$admin || !password_verify($d['password'], $admin->password))
      return $this->errorResponse($response, ApiController::ERROR_UNKNOWN_ADMIN, 'Unknown e-mail address or password');

    $token = uniqid('DS', true);

    (new TokenModel($this->mongodb))->insertOne([
      'value' => $token,
      'user_id' => $admin->_id,
      'created' => new \MongoDB\BSON\UTCDateTime(time() * 1000 + 1200000 /* 20 min */)
    ]);

    $r =       [
      'token' => $token,
      'user_id' => $admin->_id->__toString(),
      'role' => isset($admin->role) ? $admin->role : 'Product Manager',
    ];

    return $this->renderJson($response, $r);
  }

  public function getProfileAction(Request $request, Response $response, $args)
  {
    $args['id'] = $request->getAttribute('admin')->_id;

    return $this->getAction($request, $response, $args);
  }

  public function updateProfileAction(Request $request, Response $response, $args)
  {
    $args['id'] = $request->getAttribute('admin')->_id;

    return $this->updateAction($request, $response, $args);
  }
}
