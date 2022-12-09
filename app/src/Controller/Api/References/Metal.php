<?php

namespace DS\Controller\Api\References;

use DS\Core\Controller\ApiController;

/**
 * Class Metal
 * @package DS\Controller\Api
 */
final class Metal extends ApiController
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
    $this->model = new \DS\Model\Metal ($c->mongodb);
  }
}