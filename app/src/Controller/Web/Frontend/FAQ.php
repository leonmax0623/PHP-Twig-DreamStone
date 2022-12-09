<?php

namespace DS\Controller\Web\Frontend;

use DS\Core\Controller\WebController;
use DS\Model\FAQs;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class Front
 * @package DS\Controller\Web
 */
final class FAQ extends WebController {

  /**
   *  Home renderer
   *
   * @param Request $request
   * @param Response $response
   * @param $args
   * @return \Psr\Http\Message\ResponseInterface
   * @throws \Exception
   */
  public function aboutAction(Request $request, Response $response, $args) {
    // ugly way for accessing request attributes
    $this->request = $request;

    $faqs = (new FAQs($this->mongodb))->find();

    return $this->render($response, 'pages/frontend/about/index.twig',
                         ['faqs' => $faqs,
                         'isAboutSection' => true ]);
  }
  
}