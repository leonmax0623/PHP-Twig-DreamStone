<?php

namespace DS\Controller\Api;

use DS\Core\Controller\ApiController;
use DS\Model\Clarity;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class User
 * @package DS\Controller\Api
 */
final class ReferenceBooks extends ApiController
{
  /**
   * Return logged in user informations
   *
   * @param Request $request
   * @param Response $response
   * @param          $args
   *
   * @return Response
   */
  public function reloadAction(Request $request, Response $response, $args)
  {
    $clarity = new Clarity($this->mongodb);

    return $this->renderJson($response, []);
  }
}