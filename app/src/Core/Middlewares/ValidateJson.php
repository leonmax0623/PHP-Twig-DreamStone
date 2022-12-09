<?php

namespace DS\Core\Middlewares;

/**
 * ValidateJson for Slim.
 */
class ValidateJson
{
  protected $schemePath = null;
  protected $c = null;

  /**
   * Create new ValidateJson service provider.
   *
   */
  public function __construct(\Slim\Container $c, $schemePath = '')
  {
    $this->c = $c;
    $this->schemePath = $schemePath;
  }

  /**
   * ValidateJson middleware invokable class.
   *
   * @param \Psr\Http\Message\ServerRequestInterface $request PSR7 request
   * @param \Psr\Http\Message\ResponseInterface $response PSR7 response
   * @param callable $next Next middleware
   *
   * @return \Psr\Http\Message\ResponseInterface
   */
  public function __invoke($request, $response, $next)
  {
    $shema_filename = __DIR__ . '/../JsonSchemes/v1/api'
      . str_replace('/', DIRECTORY_SEPARATOR, $request->getAttributes()['route']->getPattern())
      . DIRECTORY_SEPARATOR . strtolower($request->getMethod()) . '.json';

    if (file_exists($shema_filename)) {

      $dereferencer = \League\JsonReference\Dereferencer::draft4();
      $schema = $dereferencer->dereference(json_decode(file_get_contents($shema_filename)));

      try {
        if ('multipart/form-data' == $request->getMediaType())
          $json_request = json_decode($request->getParsedBodyParam('json', '{}'));
        else
          $json_request = json_decode($request->getBody()->getContents());
      } catch (\Exception $e) {
        $this->c->logger->addError("CRITICAL Error ValidateJson:", [$e->getMessage()]);
        return $response->withJson(["error" => ["code" => 406, "msg" => "cannot decode provided json"]]);
      }

      $validator = new \League\JsonGuard\Validator($json_request, $schema);

      if ($validator->fails()) {
        $e = $validator->errors();
        return $response->withJson(["error" => ["code" => 406, "msg" => "value by path {$e[0]->getDataPath()} contain error: {$e[0]->getMessage()}"]]);
      }
    }

    return $next($request, $response);
  }
}
