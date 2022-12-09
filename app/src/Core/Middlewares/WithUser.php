<?php

namespace DS\Core\Middlewares;

use DS\Model\User;
use SlimSession\Helper;

/**
 * WithUser for Slim.
 */
class WithUser
{
    protected $c = null;

    /**
     * Create new WithUser service provider.
     *
     */
    public function __construct(\Slim\Container $c)
    {
        $this->c = $c;
    }

    /**
     * WithUser middleware invokable class.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request PSR7 request
     * @param \Psr\Http\Message\ResponseInterface $response PSR7 response
     * @param $next
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Interop\Container\Exception\ContainerException
     */
    public function __invoke($request, $response, $next)
    {
      return $next($request->withAttribute('user', $this->get()), $response);
    }

    public function get()
    {
      $session = new Helper();

      return $session->user
        ? (new User($this->c->mongodb))->findOne(['_id' => $session->user->_id])
        : null;
    }
}
