<?php

namespace DS\Controller\Web\Frontend;

use DS\Core\Controller\WebController;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;
use Google\Cloud\RecaptchaEnterprise\V1\RecaptchaEnterpriseServiceClient;
use Google\Cloud\RecaptchaEnterprise\V1\Event;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\TokenProperties\InvalidReason;

/**
 * Class Verification
 * @package DS\Controller\Web
 */
final class Verification extends WebController
{
  /**
   * Default controller construct
   *
   * @param Container $c Slim App Container
   * @throws \Interop\Container\Exception\ContainerException
   */
  public function __construct(Container $c)
  {
    parent::__construct($c);

    $googleApplicationCredentials = $this->settings['recaptcha']['GOOGLE_APPLICATION_CREDENTIALS'];
    putenv("GOOGLE_APPLICATION_CREDENTIALS=$googleApplicationCredentials");
  }

  /**
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   * @throws \Exception
   */
  public function getScoreAction(Request $request, Response $response, $args)
  {
    $this->request = $request;

    $d = $request->getParsedBody();

    if (empty($d['gToken'])) {
      return $response->withJson([
        'score' => false,
      ]);
    }

    $token = $d['gToken'];
    $siteKey = $this->settings['recaptcha']['siteKey'];
    $project = $this->settings['recaptcha']['projectId'];
    $rScore = [];
    try {
      $rScore = $this->createAssessment($siteKey, $token, $project);
    } catch (\Exception $e) {
      $this->logger->info($e->getCode() . ': ' . $e->getMessage());
    }

    return $response->withJson($rScore);
  }

  public function getResultByToken($token)
  {
    $siteKey = $this->settings['recaptcha']['siteKey'];
    $project = $this->settings['recaptcha']['projectId'];
    $rScore = [];
    // $this->logger->info(json_encode([$siteKey, $token, $project]));
    try {
      $rScore = $this->createAssessment($siteKey, $token, $project);
    } catch (\Exception $e) {
      $this->logger->info($e->getCode() . ': ' . $e->getMessage());
    }

    if (!empty($rScore['score'])) {
      return $rScore['score'] >= 0.8;
    }

    return false;
  }

  /**
   * Create an assessment to analyze the risk of a UI action.
   * @param string $siteKey The key ID for the reCAPTCHA key (See https://cloud.google.com/recaptcha-enterprise/docs/create-key)
   * @param string $token The user's response token for which you want to receive a reCAPTCHA score. (See https://cloud.google.com/recaptcha-enterprise/docs/create-assessment#retrieve_token)
   * @param string $project Your Google Cloud project ID
   */
  public function createAssessment(string $siteKey, string $token, string $project)
  {
    $client = new RecaptchaEnterpriseServiceClient();
    $projectName = $client->projectName($project);

    $event = (new Event())
      ->setSiteKey($siteKey)
      ->setToken($token);

    $assessment = (new Assessment())
      ->setEvent($event);

    try {
      $response = $client->createAssessment($projectName, $assessment);

      // You can use the score only if the assessment is valid,
      // In case of failures like re-submitting the same token, getValid() will return false
      if ($response->getTokenProperties()->getValid() == false) {
        return [
          'error' => 'The CreateAssessment() call failed because the token was invalid for the following reason: '
            . InvalidReason::name($response->getTokenProperties()->getInvalidReason()),
        ];
      } else {
        return [
          'score' => $response->getRiskAnalysis()->getScore(),
        ];
        // Optional: You can use the following methods to get more data about the token
        // Action name provided at token generation.
        // printf($response->getTokenProperties()->getAction() . PHP_EOL);
        // The timestamp corresponding to the generation of the token.
        // printf($response->getTokenProperties()->getCreateTime()->getSeconds() . PHP_EOL);
        // The hostname of the page on which the token was generated.
        // printf($response->getTokenProperties()->getHostname() . PHP_EOL);
      }
    } catch (\Exception $e) {
      return [
        'error' => 'CreateAssessment() call failed with the following error: '
          . $e->getCode() . ' - ' . $e->getMessage(),
      ];
    }
  }
}
