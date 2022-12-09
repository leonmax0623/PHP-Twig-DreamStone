<?php

namespace DS\Core\Controller;

use DebugBar\StandardDebugBar;
use DS\Core\Model\Currency;
use Monolog\Logger;
use Slim\Container;
use Slim\Flash\Messages;
use Slim\Http\Response;
use Slim\Views\Twig;

/**
 * Class WebController
 * Base Web controller class with functions shared by all web controller implementations
 *
 * @package DS\Core\Controller
 */
class WebController extends BaseController
{
  /**
   * @var Twig App View
   */
  protected $view;

  /**
   * @var \SlimSession\Helper App session
   */
  protected $session;

  /**
   * @var Logger Monolog Instance
   */
  protected $logger;

  /**
   * @var Messages Slim Flash Message Instance
   */
  protected $flash;

  /**
   * @var MongoDB MongoDb instance
   */
  protected $mongodb;

  /**
   * @var Memcached Memcached instance
   */
  protected $memchached;

  /**
   * @var array Default data passed throught view renderer
   */
  protected $defaultData;

  /**
   * @var StandardDebugBar Debug Bar Instance if enabled
   */
  protected $debugbar;

  /**
   * Default controller construct
   *
   * @param Container $c Slim App Container
   * @throws \Interop\Container\Exception\ContainerException
   */
  public function __construct(Container $c)
  {
    parent::__construct($c);

    $this->view         = $c->get('view');
    $this->logger       = $c->get('logger');
    $this->flash        = $c->get('flash');
    $this->mongodb      = $c->get('mongodb');
    $this->memcached    = $c->get('memcached');
    $this->mailer       = $c->get('mailer');
    $this->router       = $c->get('router');

    $this->session =  new \SlimSession\Helper();

    if ($this->settings['debugbar']['enabled'] === true) {
      $this->debugbar = $c->get('debugbar');
    }

    //Default data to pass trought twig tpl
    $this->defaultData = array(
      'settings'  => $this->settings
    );
  }

  /**
   * Render twig template with merged datas
   *
   * @param Response $response Slim Response
   * @param string   $tpl      Twig template to load
   * @param array    $data     Data to send into loaded view
   *
   * @return \Psr\Http\Message\ResponseInterface
   */
  protected function render(Response $response, $tpl, $data = array())
  {
    $datas = $data + $this->defaultData;
    $datas['user'] = $this->request->getAttribute('user');
    if ($datas['user']) {
      $datas['user']->email_hash = hash('sha256', $datas['user']->email);
    }
    $datas['templates'] = $this->request->getAttribute('templates');

    $currency = filter_input(INPUT_COOKIE, 'currency');
    if ($currency === 'euro') $datas['currency'] = (new Currency($this->mongodb))->getDetails($currency);

    return $this->view->render($response, $tpl, $datas);
  }

  /**
   * Current request is valid?
   *
   * @return bool
   */
  protected function isValid()
  {
    if (is_null($this->request->getAttribute('isValid')))
      return true;

    return $this->request->getAttribute('isValid');
  }

  /**
   * Mark current request as invalid
   *
   * @return bool
   */
  protected function setInvalid()
  {
    $this->request = $this->request->withAttributes(['isValid' => false]);
  }

  /**
   * Return validator what do check - request is valid?
   *
   * @return League\JsonGuard\Validator
   */
  protected function getValidator()
  {
    return $this->request->getAttribute('Validator');
  }

  /**
   * Add error to common controller errors storage
   *
   * @param string $field
   * @param string $message
   * @return array|mixed
   */
  protected function addError(string $field, string $message)
  {
    $errors = $this->getErrors();
    $errors[] = [
      'dataPath' => '/' . $field,
      'message' => $message
    ];

    $this->request = $this->request->withAttributes(['errors' => $errors, 'isValid' => false]);
  }

  /**
   * Return array of errors for curent request
   *
   * @return array
   */
  protected function getErrors()
  {
    if (is_null($this->request->getAttribute('errors')))
      return [];

    return $this->request->getAttribute('errors');
  }
}
