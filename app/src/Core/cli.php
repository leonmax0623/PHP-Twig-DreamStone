<?php

require __DIR__ . '/../../../vendor/autoload.php';

use DS\Core\Dependencies;
use Slim\App;
use Symfony\Component\Console\Application;
use DS\Commands\ImportShippingStatuses;
use DS\Commands\ImportVendors;

// get settings
$settings = require __DIR__ . '/Settings.php';
if (file_exists(__DIR__ . '/../../../../../../local/settings.php')) {
  $local_settings = require __DIR__ . '/../../../../../../local/settings.php';
  $settings = array_replace_recursive($settings, $local_settings);
}

// create Slim app
$app = new App($settings);
(new Dependencies($app))->loadAll();

// create and run Console app
$application = new Application();
$application->add(new ImportShippingStatuses($app->getContainer()));
$application->add(new ImportVendors($app->getContainer()));
$application->run();
