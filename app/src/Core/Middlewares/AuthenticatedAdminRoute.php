<?php

namespace DS\Core\Middlewares;

use DS\Model\Admin as AdminModel;
use DS\Model\Token as TokenModel;

/**
 * AuthenticatedAdminRoute for Slim.
 */
class AuthenticatedAdminRoute
{
  protected $c = null;

  /**
   * Create new AuthenticatedRoute service provider.
   *
   */
  public function __construct(\Slim\Container $c)
  {
    $this->c = $c;
  }

  /**
   * AuthenticatedRoute middleware invokable class.
   *
   * @param \Psr\Http\Message\ServerRequestInterface $request PSR7 request
   * @param \Psr\Http\Message\ResponseInterface $response PSR7 response
   * @param $next
   * @return \Psr\Http\Message\ResponseInterface
   * @throws \Interop\Container\Exception\ContainerException
   * @throws \Exception
   */
  public function __invoke($request, $response, $next)
  {
    $token = $this->c->request->getHeader('token');

    if (count($token) > 0)
      $token = $token[0];

    if (empty($token))
      $token = $request->getQueryParam('token');

    if (empty($token) || strlen($token) != $this->c['settings']['token']['length'])
      return $response->withStatus(401);

    $tokenModel = new TokenModel($this->c->mongodb);

    if (!$admin = $tokenModel->findOne([
      'value' => $token,
      'created' => ['$gt' => new \MongoDB\BSON\UTCDateTime()]
    ]))
      return $response->withStatus(403);

    $adminModel = new AdminModel($this->c->mongodb);

    $tokenModel->updateOne(
      ['_id' => $admin->_id],
      ['$set' => [
        'created' => new \MongoDB\BSON\UTCDateTime(time() * 1000 + 60000 * 120 /* 120 min */)
      ]]
    );

    $adminProfile = $adminModel->findOne(['_id' => $admin->user_id]);
    if ($adminProfile->role != 'Admin') {
      return $response->withStatus(403);
    }

    return $next($request->withAttributes([
      'token' => $token,
      'admin' => $adminProfile,
    ]), $response);
  }
}
