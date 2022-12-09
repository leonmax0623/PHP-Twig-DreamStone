<?php

namespace DS\Core\Middlewares;

use DS\Model\Content;

/**
 * WithTemplates for Slim.
 */
class WithTemplates
{
    protected $c = null;

    /**
     * Create new WithTemplates service provider.
     *
     */
    public function __construct(\Slim\Container $c)
    {
        $this->c = $c;
    }

    /**
     * WithTemplates middleware invokable class.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request PSR7 request
     * @param \Psr\Http\Message\ResponseInterface $response PSR7 response
     * @param $next
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Interop\Container\Exception\ContainerException
     */
    public function __invoke($request, $response, $next)
    {
      return $next($request->withAttribute('templates', $this->get()), $response);
    }

    public function get()
    {
      $templates = [];
      foreach ((new Content($this->c->mongodb))->find() as $template) {
        $templates[$template->name] = [];
        foreach ($template->items as $item) {
          $templates[$template->name][$item->name] = $item->content;
        }
      }

      return $templates;
    }
}
