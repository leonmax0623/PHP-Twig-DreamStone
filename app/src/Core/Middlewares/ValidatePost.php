<?php

namespace DS\Core\Middlewares;

use DS\Core\Utils;
use League\JsonGuard\Validator;
use League\JsonReference\Dereferencer;

/**
 * ValidatePost for Slim.
 */
class ValidatePost
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
    $shema_filename = __DIR__ . '/../JsonSchemes/api/v1'
      . str_replace('/', DIRECTORY_SEPARATOR, $request->getAttributes()['route']->getPattern())
      . DIRECTORY_SEPARATOR . strtolower($request->getMethod()) . '.json';

    $this->c->logger->addInfo("For POST format check used JSON scheme: ", [$shema_filename]);

    if (file_exists($shema_filename)) {

      $dereferencer = Dereferencer::draft4();
      $schema = $dereferencer->dereference(json_decode(file_get_contents($shema_filename)));

      try {
        $json_request = (object)$_POST;
      } catch (\Exception $e) {
        $guid = Utils::newGUID();

        $this->c->logger->addError("CRITICAL Error ValidateJson:", [$guid, $e->getMessage()]);
        return $response->redirect('/errors/500?guid=' . $guid);
      }

      $validator = new Validator($json_request, $schema);

      if ($validator->fails()) {
        return $next($request->withAttributes(['isValid' => false, 'validator' => $validator, 'errors' => $validator->errors()]), $response);
      }
    }

    return $next($request->withAttributes(['isValid' => true, 'validator' => null, 'errors' => []]), $response);
  }
}
