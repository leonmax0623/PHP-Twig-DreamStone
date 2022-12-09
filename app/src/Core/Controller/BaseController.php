<?php
namespace DS\Core\Controller;

use Slim\Container;

/**
 * Class BaseController
 * Base controller what contain common functions and properties
 *
 * @package DS\Core\Controller
 */
class BaseController
{
  /**
   * @var Slim App Container
   */
  protected $c;

  /**
   * @var Slim\Http\Request
   */
  protected $request;

  /**
   * @var array Settings array
   */
  protected $settings;

  /**
   * Default base controller construct
   * @param Container $c Slim App Container
   *
   * @throws \Interop\Container\Exception\ContainerException
   */
  public function __construct(\Slim\Container $c)
  {
    $this->c = $c;
    $this->request = $c->request;
    $this->settings = $c->get('settings');
  }
}