<?php

namespace DS\Controller\Web\Backend;

use DS\Core\Controller\WebController;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class Back
 * @package DS\Controller\Web
 */
final class Dashboard extends WebController
{
  /**
   * Dashboard renderer
   *
   * @param Request $request Slim Request
   * @param Response $response Slim Response
   * @param array $args Arguments array (GET / POST / ...)
   *
   * @return Response
   */
  public function dashboardAction(Request $request, Response $response, $args)
  {
    return $this->render($response, 'pages/backend/dashboard.twig');
  }
}