<?php

namespace DS\Core\Middlewares;

use DS\Models\Users;
use DS\Models\Tokens;

/**
 * AuthenticatedUserRoute for Slim.
 */
class AuthenticatedUserRoute
{
    protected $c = null;

    /**
     * Create new AuthenticatedUserRoute service provider.
     *
     */
    public function __construct(\Slim\Container $c)
    {
        $this->c = $c;
    }

    /**
     * AuthenticatedUserRoute middleware invokable class.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request PSR7 request
     * @param \Psr\Http\Message\ResponseInterface $response PSR7 response
     * @param $next
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Interop\Container\Exception\ContainerException
     */
    public function __invoke($request, $response, $next)
    {
      $session = new \SlimSession\Helper();

        if (!$session->isLogged)
          return $response->withRedirect('/user/login/?returnUrl='.$request->getUri()->getPath());
        else
          return $next($request, $response);
    }
}
