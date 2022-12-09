<?php

namespace DS\Controller\Api;

use DS\Core\Controller\ApiController;
use DS\Model\Tax as TaxModel;

/**
 * Class Tax
 * @package DS\Controller\Api
 */
final class Tax extends ApiController
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
        $this->model = new TaxModel($c->mongodb);
    }

}
