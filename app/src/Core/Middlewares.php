<?php

namespace DS\Core;

use PhpMiddleware\PhpDebugBar\PhpDebugBarMiddleware;
use Slim\App;
use Slim\Container;
use Zeuxisoo\Whoops\Provider\Slim\WhoopsMiddleware;

/**
 * Class Middlewares
 * Register Slim middlewares into provided App object
 *
 * @package DS\Core
 */
class Middlewares
{
  /**
   * @var App Slim App instance
   */
  private $app;

  /**
   * @var Container Slim DI Container
   */
  private $dic;

  /**
   * Middlewares constructor.
   *
   * @param App $app Slim App Instance
   */
  public function __construct(App $app)
  {
    $this->app = $app;
    $this->dic = $this->app->getContainer();
  }

  /**
   * Load all Middlewares
   */
  public function loadAll()
  {
    $this->loadLazyCORS();
    $this->loadTrailingSlash();
    $this->loadLimitAPIbyContentType();
    $this->loadWhoopsErrorHandler();
    $this->loadDebugBar();
    $this->loadSession();
    // $this->loadCaseInsensetiveRoutes();
  }

  /**
   * The simple solution what  enable lazy CORS
   */
  public function loadLazyCORS()
  {
    $this->app->add(function ($req, $res, $next) {
      return $next(
        $req,
        $res
          ->withHeader('Access-Control-Allow-Origin', '*')
          ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, token')
          ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      );
    });
  }

  /**
   * Middleware to normalize the trailing slash of the uri path.
   * By default removes the slash so, for example, /post/23/ is converted to /post/23. Useful if you have problems with the router.
   */
  public function loadTrailingSlash()
  {
    $this->app->add(function ($request, $response, $next) {
      $uri = $request->getUri();
      $path = $uri->getPath();
      if ($path != '/' && substr($path, -1) == '/') {
        // permanently redirect paths with a trailing slash
        // to their non-trailing counterpart
        $uri = $uri->withPath(substr($path, 0, -1));

        if ($request->getMethod() == 'GET') {
          return $response->withRedirect((string)$uri, 301);
        } else {
          return $next($request->withUri($uri), $response);
        }
      }

      return $next($request, $response);
    });
  }

  /**
   * For API we accept only requests with header Content-Type: application/json
   */
  public function loadLimitAPIbyContentType()
  {
    $settings = $this->dic->get('settings');
    if ($settings['limitAPI']['byContentType']) {
      $this->app->add(function ($req, $res, $next) {
        $path = $req->getUri()->getPath();
        if (
          strpos($path, '/api/') !== false
          && $req->getMethod() !== 'OPTIONS'
          && strpos($path, '/storage/') === false
          && strpos($path, '/pdf') === false
          && strpos($path, '/products/export') === false
        ) {
          $headerValue = $req->getHeader('Content-Type');

          if (
            count($headerValue) > 0 && (strpos($headerValue[0], 'application/json') !== false
              || strpos($headerValue[0], 'multipart/form-data') !== false)
          )
            return $next($req, $res);

          return $res->withStatus(400);
        }

        return $next($req, $res);
      });
    }
  }

  /**
   * Load PHP whoops error if enabled
   * or load JSON or standard Slim error output
   *
   * @see https://github.com/zeuxisoo/php-slim-whoops
   * @see https://filp.github.io/whoops/
   */
  public function loadWhoopsErrorHandler()
  {
    $settings = $this->dic->get('settings');
    if ($settings['displayErrorDetails'] === true) {
      if ($settings['error']['whoops'] === true) {
        $settings['debug'] = true; // Needed by WhoopsMiddleware
        $this->app->add(new WhoopsMiddleware($this->app));
      } elseif ($settings['error']['json'] === true) {
        $this->dic['errorHandler'] = function ($c) {
          return function ($request, $response, $exception) use ($c) {
            $data = [
              'error' => [
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => explode("\n", $exception->getTraceAsString()),
              ]
            ];

            return $response->withJson($data, 500);
          };
        };
      }
    }
  }

  /**
   * Load Debug Bar Javascript Renderer if enabled
   */
  public function loadDebugBar()
  {
    if ($this->dic->get('settings')['debugbar']['enabled'] === true) {
      $this->app->add(new PhpDebugBarMiddleware(
        $this->dic->get('debugbar')->getJavascriptRenderer('/phpdebugbar')
      ));
    }
  }

  /**
   * The simple solution what  enable lazy CORS
   */
  public function loadSession()
  {
    $this->app->add(new \Slim\Middleware\Session([
      'name' => 'dssess',
      'autorefresh' => true,
      'lifetime' => '20 minutes'
    ]));
  }

  public function loadCaseInsensetiveRoutes()
  {
    $this->app->add(function ($request, $response, $next) {
      $uri = $request->getUri();
      $uri = $uri->withPath(strtolower($uri->getPath()));
      $request = $request->withUri($uri);

      return $next($request, $response);
    });
  }
}
