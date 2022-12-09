<?php

namespace DS\Core;

use DebugBar\Bridge\MonologCollector;
use DebugBar\DataCollector\ConfigCollector;
use DebugBar\StandardDebugBar;
use DS\Core\Twig\AssetTwigExtension;
use DS\Core\Twig\PriceTwigExtension;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Slim\App;
use Slim\Container;
use Slim\Flash\Messages;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;
use Twig\Extension\DebugExtension;
use DS\Core\Mail\Mailer;
use Twig\Extension\StringLoaderExtension;

/**
 * Class Dependencies
 * Load and setup dependencies into Slim DI Container from provided App object
 *
 * @package DS\Core
 */
class Dependencies
{
  /**
   * @var Container Slim DI Container
   */
  protected $dic;

  /**
   * Dependencies constructor.
   *
   * @param App $app Slim App Instance
   */
  public function __construct(App $app)
  {
    $this->dic = $app->getContainer();
  }

  /**
   * Load all dependencies
   */
  public function loadAll()
  {
    $this->loadTwig();
    $this->loadFlash();
    $this->loadDebugBar();
    $this->loadMonolog();
    $this->loadMongoDB();
    $this->loadMailer();
    $this->loadMemcached();
  }

  /**
   * Load Twig Slim view
   */
  public function loadTwig()
  {
    /**
     * @param Container $c
     *
     * @return Twig
     */
    $this->dic['view'] = function (Container $c) {
      $settings = $c->get('settings');
      $view = new Twig($settings['view']['template_path'], $settings['view']['twig']);
      // Add extensions
      $view->addExtension(new TwigExtension($c->get('router'), $c->get('request')->getUri()));
      $view->addExtension(new DebugExtension());
      $view->addExtension(new AssetTwigExtension($c));
      $view->addExtension(new PriceTwigExtension($c));
      $view->addExtension(new StringLoaderExtension());
      $view->getEnvironment()->addGlobal('currentPath', $c->get('request')->getUri()->getPath());

      return $view;
    };
  }

  /**
   * Load Mailer instance
   */
  public function loadMailer()
  {
    /**
     * @param Container $c
     *
     * @return Twig
     */
    $this->dic['mailer'] = function (Container $c) {
      $settings = $c->get('settings')['mailer'];

      $mailer = new \PHPMailer\PHPMailer\PHPMailer();
      $mailer->CharSet = "UTF-8";
      $mailer->SMTPDebug = 0;
      $mailer->isSMTP();
      $mailer->Host = $settings['host'];
      $mailer->SMTPAuth = true;
      $mailer->Username = $settings['username'];
      $mailer->Password = $settings['password'];
      $mailer->SetFrom($settings['username'], $settings['fromname']);
      $mailer->isHTML(true);

      return new Mailer($c->view, $mailer);
    };
  }

  /**
   * Load Slim Flash Message
   */
  public function loadFlash()
  {
    /**
     * @param Container $c
     *
     * @return Messages
     */
    $this->dic['flash'] = function (Container $c) {
      return new Messages();
    };
  }

  /**
   * Load Debug Bar Service if enabled
   */
  public function loadDebugBar()
  {
    if ($this->dic->get('settings')['debugbar']['enabled'] === true) {
      /**
       * @param Container $c
       *
       * @return StandardDebugBar
       */
      $this->dic['debugbar'] = function (Container $c) {
        $debugbar = new StandardDebugBar();

        // Add settings array to Config Collector
        if ($c->get('settings')['debugbar']['collectors']['config'] === true) {
          $debugbar->addCollector(new ConfigCollector($c->get('settings')->all()));
        }

        return $debugbar;
      };
    }
  }

  /**
   * Load Monolog Service
   */
  public function loadMonolog()
  {
    /**
     * @param \Slim\Container $c
     *
     * @return Logger
     */
    $this->dic['logger'] = function (Container $c) {
      $settings = $c->get('settings');
      $logger = new Logger($settings['logger']['name']);
      $logger->pushProcessor(new UidProcessor());
      if (PHP_SAPI === 'cli') {
        $filename = $settings['logger']['filename'];
      } else {
        $filename = $settings['logger']['filename'];
      }
      $logger->pushHandler(new StreamHandler($settings['logger']['path'] . $filename, Logger::DEBUG));

      // Add Monolog instance to Debug Bar Data Collector
      if ($settings['debugbar']['enabled'] === true && $settings['debugbar']['collectors']['monolog'] === true) {
        $c->get('debugbar')->addCollector(
          new MonologCollector($logger)
        );
      }

      return $logger;
    };
  }

  /**
   * Load Mongo DB
   */
  public function loadMongoDB()
  {
    /**
     * @param Container $c
     *
     * @return \MongoDB\Client
     */
    $this->dic['mongo_client'] = function (Container $c) {
      $settings = $c->get('settings');
      $mongo = new \MongoDB\Client(
        'mongodb://' . $settings['mongo']['host'] . ':' . $settings['mongo']['port'],
        $settings['mongo']['options'],
        $settings['mongo']['driverOptions']
      );

      return $mongo;
    };

    /**
     * @param Container $c
     *
     * @return \MongoDB\Database
     */
    $this->dic['mongodb'] = function (Container $c) {
      return $c->get('mongo_client')->selectDatabase($c->get('settings')['mongo']['default_db']);
    };
  }

  /**
   * Load Memcached Service
   */
  public function loadMemcached()
  {
    /**
     * @param \Slim\Container $c
     *
     * @return Logger
     */
    $this->dic['memcached'] = function (Container $c) {

      $m = new \Memcached();
      $m->addServer('localhost', 11211);

      return $m;
    };
  }
}
