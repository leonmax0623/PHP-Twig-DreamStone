<?php

namespace DS\Controller\Api;

use DS\Core\Controller\ApiController;
use DS\Model\Settings as SettingsModel;

/**
 * Class Settings
 * @package DS\Controller\Api
 */
final class Settings extends ApiController
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
        $this->model = new SettingsModel($c->mongodb);
    }

}
