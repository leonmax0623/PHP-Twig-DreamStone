<?php
namespace DS\Core\Bootstrap;

use DS\Core\Dependencies;
use DS\Core\Middlewares;
use DS\Core\Routes;
use Slim\App;

/**
 * Class HttpBootstrap
 * Load and setup Slim App and DIC
 *
 * @package DS\Core\Bootstrap
 */
class HttpBootstrap
{
    /**
     * @var App Slim App instance
     */
    protected $app;

    /**
     * @var array Settings
     */
    protected $settings;

    /**
     * @var \DS\Core\Dependencies Dependencies instance
     */
    protected $dicDependencies;

    /**
     * @var \DS\Core\Middlewares Middlewares instance
     */
    protected $middlewares;

    /**
     * @var \DS\Core\Routes Routes instance
     */
    protected $routes;

    /**
     * Bootstrap construct
     */
    public function __construct()
    {
        // Instantiate the app
        $this->loadSettings();
        $this->app = new App($this->settings);
    }

    /**
     * Setup and Run
     */
    public function run()
    {
        // Set up dependencies
        $this->loadDependencies();

        // Register middleware
        $this->loadMiddlewares();

        // Register routes
        $this->loadRoutes();

        // Run!
        $this->app->run();
    }

    /**
     * Load settings array from settings.php
     * Merge with local.settings.php if exist
     */
    protected function loadSettings()
    {
        $this->settings = require __DIR__ . '/../Settings.php';

        if (file_exists(__DIR__ . '/../../../../../../../local/settings.php')) {
            $local_settings = require __DIR__ . '/../../../../../../../local/settings.php';
            $this->settings = array_replace_recursive($this->settings, $local_settings);
        }
    }

    /**
     * Set Up Dependencies
     */
    protected function loadDependencies()
    {
        $this->dicDependencies = new Dependencies($this->app);
        $this->dicDependencies->loadAll();
    }

    /**
     * Add Middlewares
     */
    protected function loadMiddlewares()
    {
        $this->middlewares = new Middlewares($this->app);
        $this->middlewares->loadAll();
    }

    /**
     * Register Routes
     */
    protected function loadRoutes()
    {
        $this->routes = new Routes($this->app);
        $this->routes->loadAll();
    }
}