<?php

// To help the built-in PHP dev server, check if the request was actually for
// something which should probably be served as a static file
if (PHP_SAPI === 'cli-server' && $_SERVER['SCRIPT_FILENAME'] !== __FILE__) {
  return false;
}


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('UTC');

require __DIR__ . '/../vendor/autoload.php';

session_start();

$bootstap = new \DS\Core\Bootstrap\HttpBootstrap();
$bootstap->run();