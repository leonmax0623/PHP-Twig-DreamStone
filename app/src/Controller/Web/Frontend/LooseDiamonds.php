<?php

namespace DS\Controller\Web\Frontend;

use DS\Core\Controller\WebController;
use DS\Controller\Web\Frontend\Verification;
use DS\Core\Utils;
use DS\Model\Compare;
use DS\Model\Composite;
use DS\Model\Diamond;
use DS\Model\Favorite;
use DS\Model\MailTemplate;
use DS\Model\StaticPages;
use DS\Model\Vendor;
use DS\Model\Viewed;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\ObjectId;
use Slim\Http\Request;
use Slim\Http\Response;
use DS\Model\Shape;
use DS\Model\Color;
use DS\Model\Fancycolor;
use DS\Model\Cut;
use DS\Model\Clarity;
use DS\Model\Polish;
use DS\Model\Symmetry;
use DS\Model\Flourence;

/**
 * Class Front
 * @package DS\Controller\Web
 */
final class LooseDiamonds extends WebController
{

  /**
   * @var array
   */
  private $possibleSort = [
    ['code' => 'price', 'title' => 'Price ↑'],
    ['code' => '-price', 'title' => 'Price ↓'],
    ['code' => 'shape', 'title' => 'Shape ↑'],
    ['code' => '-shape', 'title' => 'Shape ↓'],
    ['code' => 'carat', 'title' => 'Carat ↑'],
    ['code' => '-carat', 'title' => 'Carat ↓'],
    ['code' => 'color', 'title' => 'Color ↑'],
    ['code' => '-color', 'title' => 'Color ↓'],
    ['code' => 'clarity', 'title' => 'Clarity ↑'],
    ['code' => '-clarity', 'title' => 'Clarity ↓'],
    ['code' => 'cut', 'title' => 'Cut ↑'],
    ['code' => '-cut', 'title' => 'Cut ↓'],
  ];


  /**
   * Home renderer
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   * @throws \Exception
   */
  public function indexAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $shapes = (new Shape($this->mongodb))->find();
    $colors = (new Color($this->mongodb))->find();
    $fancycolors = (new Fancycolor($this->mongodb))->find();
    $clarities = (new Clarity($this->mongodb))->find();

    return $this->render($response, 'pages/frontend/loose_diamonds/index.twig', [
      'shapes' => $shapes,
      'colors' => $colors,
      'clarities' => $clarities,
      'fancycolors' => $fancycolors,
      'isDiamondsSection' => true
    ]);
  }
  // BOOK FORM DIAMOND 
  public function bookFormAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $d = $request->getParsedBody();

    if (empty($d['gToken'])) {
      return $response->withJson(['result' => 0]);
    }

    $Verification = (new Verification($this->c));

    $r = $Verification->getResultByToken($d['gToken']);

    if (!$r) {
      return $response->withJson(['result' => 0]);
    }

    $title = 'Title';

    $name = '';
    $surName = '';
    $phoneCode = '';
    $phoneNumber = '';
    $email = $d['user_email'];
    $text = '';
    $calendar = '';
    $hour = '';
    $minutes = '';
    $twentyFourHours = '';
    $check1 = '';
    $check2 = '';
    $check3 = '';
    $pageFrom = '';

    if (isset($d['user_name'])) {
      $name = stripslashes($d['user_name']);
      $name = htmlspecialchars($name);
    }
    if (isset($d['user_surname'])) {
      $surName = stripslashes($d['user_surname']);
      $surName = htmlspecialchars($surName);
    }
    if (isset($d['phone_code'])) {
      $phoneCode = stripslashes($d['phone_code']);
      $phoneCode = htmlspecialchars($phoneCode);
    }
    if (isset($d['phone_number'])) {
      $phoneNumber = stripslashes($d['phone_number']);
      $phoneNumber = htmlspecialchars($phoneNumber);
    }
    if (isset($d['text'])) {
      $text = stripslashes($d['text']);
      $text = htmlspecialchars($text);
    }
    if (isset($d['calendar'])) {
      $calendar = stripslashes($d['calendar']);
      $calendar = htmlspecialchars($calendar);
    }
    if (isset($d['hour'])) {
      $hour = stripslashes($d['hour']);
      $hour = htmlspecialchars($hour);
    }
    if (isset($d['minutes'])) {
      $minutes = stripslashes($d['minutes']);
      $minutes = htmlspecialchars($minutes);
    }
    if (isset($d['twentyFourHours'])) {
      $twentyFourHours = stripslashes($d['twentyFourHours']);
      $twentyFourHours = htmlspecialchars($twentyFourHours);
    }
    if (isset($d['pageFrom'])) {
      $pageFrom = stripslashes($d['pageFrom']);
      $pageFrom = htmlspecialchars($pageFrom);
    }
    if (isset($d['check1'])) {
      $check1 = stripslashes($d['check1']);
      $check1 = htmlspecialchars($check1);
    }
    if (isset($d['check2'])) {
      $check2 = stripslashes($d['check2']);
      $check2 = htmlspecialchars($check2);
    }
    if (isset($d['check3'])) {
      $check3 = stripslashes($d['check3']);
      $check3 = htmlspecialchars($check3);
    }

    $templateChecks = '<p>Appointment location: %allCheckAttributes%<br /></p>';
    $checkStrings = [];

    if ($check1 || $check2 || $check3) {
      $templateCheck = '';
      $checks = [];

      if (!empty($check1)) {
        $checks['%location1%'] = $check1;
      };
      if (!empty($check2)) {
        $checks['%location2%'] = $check2;
      };
      if (!empty($check3)) {
        $checks['%location3%'] = $check3;
      };

      $checks[] = str_replace(array_keys($checks), array_values($checks), $templateCheck);
      $allCheckAttributes = join("; ", $checks);

      $dataCheck = [
        '%allCheckAttributes%' => $allCheckAttributes,
      ];
      $checkStrings[] = str_replace(array_keys($dataCheck), array_values($dataCheck), $templateChecks);
      $checksStrings = join($checkStrings);
    }

    $email1 = $this->settings['mailer']['adminMail'];
    $email2 = $email;
    $template = (new MailTemplate($this->mongodb))->findOne(['type' => 'diamond_book_form']);
    foreach ([$email1, $email2] as $toEmail) {
      $mailResult = $this->mailer->send($template->body, [
        '%name%' => $name,
        '%surname%' => $surName,
        '%email%' => $email,
        '%phoneCode%' => $phoneCode,
        '%phoneNumber%' => $phoneNumber,
        '%calendar%' => $calendar,
        '%hour%' => $hour,
        '%minutes%' => $minutes,
        '%twentyFourHours%' => $twentyFourHours,
        '%text%' => $text,
        '%location%' => $checksStrings ?? '',
        '%pageFrom%' => $pageFrom,

      ], function ($message) use ($toEmail) {
        $message->to($toEmail);
        $message->subject('Diamond Book Form');
      });
    }

    return $response->withJson(['result' => !!$mailResult]);
  }

  // MATCH FORM DIAMOND 
  public function matchFormAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $d = $request->getParsedBody();

    if (empty($d['gToken'])) {
      return $response->withJson(['result' => false]);
    }

    $Verification = (new Verification($this->c));

    $r = $Verification->getResultByToken($d['gToken']);

    if (!$r) {
      return $response->withJson(['result' => false]);
    }

    $name = '';
    $email = $d['user_match_email'];
    $text = '';
    $pageFromMatch = '';

    if (isset($d['user_match_name'])) {
      $name = stripslashes($d['user_match_name']);
      $name = htmlspecialchars($name);
    }
    if (isset($d['match_text'])) {
      $text = stripslashes($d['match_text']);
      $text = htmlspecialchars($text);
    }
    if (isset($d['pageFromMatch'])) {
      $pageFromMatch = stripslashes($d['pageFromMatch']);
      $pageFromMatch = htmlspecialchars($pageFromMatch);
    }

    $email1 = $this->settings['mailer']['adminMail'];
    $email2 = $email;
    $template = (new MailTemplate($this->mongodb))->findOne(['type' => 'diamond_match_form']);
    foreach ([$email1, $email2] as $toEmail) {
      $mailResult = $this->mailer->send($template->body, [
        '%name%' => $name,
        '%email%' => $email,
        '%text%' => $text,
        '%pageFromMatch%' => $pageFromMatch,

      ], function ($message) use ($toEmail) {
        $message->to($toEmail);
        $message->subject('Diamond Match Form');
      });
    }

    return $response->withJson(['result' => !!$mailResult]);
  }

  // Contact us
  public function contactFormAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $d = $request->getParsedBody();

    if (empty($d['gToken'])) {
      return $response->withJson(['result' => false]);
    }

    $Verification = (new Verification($this->c));

    $r = $Verification->getResultByToken($d['gToken']);

    if (!$r) {
      return $response->withJson(['result' => false]);
    }

    $name = '';
    $email = $d['user_contact_email'];
    $phone = '';
    $text = '';

    if (isset($d['user_contact_name'])) {
      $name = stripslashes($d['user_contact_name']);
      $name = htmlspecialchars($name);
    }
    if (isset($d['user_contact_phone'])) {
      $phone = stripslashes($d['user_contact_phone']);
      $phone = htmlspecialchars($phone);
    }
    if (isset($d['contact_text'])) {
      $text = stripslashes($d['contact_text']);
      $text = htmlspecialchars($text);
    }

    $email1 = $this->settings['mailer']['adminMail'];
    $email2 = 'info@dreamstone.com';
    $template = (new MailTemplate($this->mongodb))->findOne(['type' => 'contact-us-form']);
    foreach ([$email1, $email2] as $toEmail) {
      $mailResult = $this->mailer->send($template->body, [
        '%name%' => $name,
        '%email%' => $email,
        '%text%' => $text,
        '%phone%' => $phone,

      ], function ($message) use ($toEmail) {
        $message->to($toEmail);
        $message->subject('Contact us form');
      });
    }

    return $response->withJson(['result' => !!$mailResult]);
  }

  // SHARE FORM DIAMOND 
  public function shareFormAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $d = $request->getParsedBody();

    if (empty($d['gToken'])) {
      return $response->withJson(['result' => false]);
    }

    $Verification = (new Verification($this->c));

    $r = $Verification->getResultByToken($d['gToken']);

    if (!$r) {
      return $response->withJson(['result' => false]);
    }

    // $reEmail = $d['recipient_share_email'];
    $reEmail = '';
    $name = '';
    $email = $d['user_share_email'];
    $text = '';
    $pageFromShare = '';

    if (isset($d['recipient_share_email'])) {
      $reEmail = stripslashes($d['recipient_share_email']);
      $reEmail = htmlspecialchars($reEmail);
    }
    if (isset($d['user_share_name'])) {
      $name = stripslashes($d['user_share_name']);
      $name = htmlspecialchars($name);
    }
    if (isset($d['share_text'])) {
      $text = stripslashes($d['share_text']);
      $text = htmlspecialchars($text);
    }
    if (isset($d['pageFromShare'])) {
      $pageFromShare = stripslashes($d['pageFromShare']);
      $pageFromShare = htmlspecialchars($pageFromShare);
    }

    $email1 = $this->settings['mailer']['adminMail'];
    $email2 = $email;
    $email3 = $reEmail;
    $template = (new MailTemplate($this->mongodb))->findOne(['type' => 'diamond_share_form']);
    foreach ([$email1, $email2, $email3] as $toEmail) {
      $mailResult = $this->mailer->send($template->body, [
        '%reEmail%' => $reEmail,
        '%reEmail%' => $reEmail,
        '%reEmail%' => $reEmail,
        '%name%' => $name,
        '%userEmail%' => $email,
        '%text%' => $text,
        '%pageFromShare%' => $pageFromShare,

      ], function ($message) use ($toEmail) {
        $message->to($toEmail);
        $message->subject('Diamond Share Form');
      });
    }

    return $response->withJson(['result' => !!$mailResult]);
  }

  // MAIL FORM DIAMOND 
  public function mailFormAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $d = $request->getParsedBody();

    if (empty($d['gToken'])) {
      return $response->withJson(['result' => false]);
    }

    $Verification = (new Verification($this->c));

    $r = $Verification->getResultByToken($d['gToken']);

    if (!$r) {
      return $response->withJson(['result' => false]);
    }

    $name = '';
    $email = $d['user_mail_email'];
    $text = '';
    $pageFromMail = '';

    if (isset($d['user_mail_name'])) {
      $name = stripslashes($d['user_mail_name']);
      $name = htmlspecialchars($name);
    }
    if (isset($d['mail_text'])) {
      $text = stripslashes($d['mail_text']);
      $text = htmlspecialchars($text);
    }
    if (isset($d['pageFromMail'])) {
      $pageFromMail = stripslashes($d['pageFromMail']);
      $pageFromMail = htmlspecialchars($pageFromMail);
    }

    $email1 = $this->settings['mailer']['adminMail'];
    $email2 = $email;
    $template = (new MailTemplate($this->mongodb))->findOne(['type' => 'diamond_mail_form']);
    foreach ([$email1, $email2] as $toEmail) {
      $mailResult = $this->mailer->send($template->body, [
        '%name%' => $name,
        '%userEmail%' => $email,
        '%text%' => $text,
        '%pageFromMail%' => $pageFromMail,

      ], function ($message) use ($toEmail) {
        $message->to($toEmail);
        $message->subject('Diamond Mail Form');
      });
    }

    return $response->withJson(['result' => !!$mailResult]);
  }

  // SKU FORM DIAMOND 
  public function skuFormAction(Request $request, Response $response, $args)
  {
    $this->request = $request;
    $d = $request->getParsedBody();

    if (empty($d['gToken'])) {
      return $response->withJson(['result' => false]);
    }

    $Verification = (new Verification($this->c));

    $r = $Verification->getResultByToken($d['gToken']);

    if (!$r) {
      return $response->withJson(['result' => false]);
    }

    $name = '';
    $email = $d['user_sku_email'];
    $pageFromSku = '';
    $certificateURL = '';

    if (isset($d['user_sku_name'])) {
      $name = stripslashes($d['user_sku_name']);
      $name = htmlspecialchars($name);
    }
    if (isset($d['pageFromSku'])) {
      $pageFromSku = stripslashes($d['pageFromSku']);
      $pageFromSku = htmlspecialchars($pageFromSku);
    }
    if (isset($d['certificateURL'])) {
      $certificateURL = stripslashes($d['certificateURL']);
      $certificateURL = htmlspecialchars($certificateURL);
    }
    $email1 = $this->settings['mailer']['adminMail'];
    $email2 = $email;
    $template = (new MailTemplate($this->mongodb))->findOne(['type' => 'diamond_sku_form']);


    foreach ([$email1, $email2] as $toEmail) {
      $mailResult = $this->mailer->send($template->body, [
        '%name%' => $name,
        '%userEmail%' => $email,
        '%pageFromSku%' => $pageFromSku,
        '%certificateURL%' => $certificateURL,

      ], function ($message) use ($toEmail) {
        $message->to($toEmail);
        $message->subject('Diamond Sku Form');
      });
    }
    return $response->withJson(['result' => !!$mailResult]);
  }

  // Request-image FORM DIAMOND 
  public function RequestImageFormAction(Request $request, Response $response, $args)
  {
    $this->request = $request;
    $d = $request->getParsedBody();

    if (empty($d['gToken'])) {
      return $response->withJson(['result' => false]);
    }

    $Verification = (new Verification($this->c));

    $r = $Verification->getResultByToken($d['gToken']);

    if (!$r) {
      return $response->withJson(['result' => false]);
    }

    $email = $d['user_request_email'];
    $pageFrom = '';

    if (isset($d['pageFrom'])) {
      $pageFrom = stripslashes($d['pageFrom']);
      $pageFrom = htmlspecialchars($pageFrom);
    }

    $email1 = $this->settings['mailer']['adminMail'];
    $email2 = '';
    $template = (new MailTemplate($this->mongodb))->findOne(['type' => 'diamond_request_image_form']);
    $template2 = (new MailTemplate($this->mongodb))->findOne(['type' => 'diamond_request_image_to_user_form']);
    foreach ([$email1, $email2] as $toEmail) {
      $mailResult = $this->mailer->send($template->body, [
        '%userEmail%' => $email,
        '%pageFrom%' => $pageFrom,
      ], function ($message) use ($toEmail) {
        $message->to($toEmail);
        $message->subject('Diamond Request image Form');
      });
    }
    foreach ([$email2, $email] as $toEmail) {
      $mailResult = $this->mailer->send($template2->body, [
        '%userEmail%' => $email,
        '%pageFrom%' => $pageFrom,
      ], function ($message) use ($toEmail) {
        $message->to($toEmail);
        $message->subject('Diamond Request image Form');
      });
    }

    return $response->withJson(['result' => !!$mailResult]);
  }

  // subscription FORM
  public function subscriptionFormAction(Request $request, Response $response, $args)
  {
    $this->request = $request;
    $d = $request->getParsedBody();

    if (empty($d['gToken'])) {
      return $response->withJson(['result' => false]);
    }

    $Verification = (new Verification($this->c));

    $r = $Verification->getResultByToken($d['gToken']);

    if (!$r) {
      return $response->withJson(['result' => false]);
    }

    $name = '';
    $email = $d['subscription_email'];

    $diamondHref = 'https://dreamstone.com/loose-diamonds/search?v=g&carat_min=1.00&color_id=J%2CI%2CH%2CG%2CF%2CE%2CD&clarity_id=SI2%2CSI1%2CVS2%2CVS1%2CVVS2%2CVVS1%2CIF%2CFL';
    $ringsHref = 'https://dreamstone.com/engagement-rings/search?builder=1';
    $contactHref = 'https://dreamstone.com/' . '/pages/contact-us';
    $myAccountHref = 'https://dreamstone.com/' . '/user/login?returnUrl=/user/profile';
    $policyHref = 'https://dreamstone.com/' . '/pages/privacy';

    if (isset($d['subscription_name'])) {
      $name = stripslashes($d['subscription_name']);
      $name = htmlspecialchars($name);
    }

    $email1 = $this->settings['mailer']['adminMail'];
    $email2 = $email;

    $template = (new MailTemplate($this->mongodb))->findOne(['type' => 'subscription_form']);

    foreach ([$email1, $email2] as $toEmail) {
      $mailResult = $this->mailer->send($template->body, [
        '%userFirstname%' => $name,
        '%uri%' => 'https://dreamstone.com/' . '/user/login',
        '%diamondHref%' => $diamondHref,
        '%ringsHref%' => $ringsHref,
        '%contactHref%' => $contactHref,
        '%myAccountHref%' => $myAccountHref,
        '%policyHref%' => $policyHref,
      ], function ($message) use ($toEmail) {
        $message->to($toEmail);
        $message->subject('DreamStone Email Signup');
      });
    }

    return $response->withJson(['result' => !!$mailResult]);
  }

  // custom FORM
  public function customFormAction(Request $request, Response $response, $args)
  {
    $this->request = $request;
    $d = $request->getParsedBody();

    if (empty($d['gToken'])) {
      return $response->withJson(['result' => false]);
    }

    $Verification = (new Verification($this->c));

    $r = $Verification->getResultByToken($d['gToken']);

    if (!$r) {
      return $response->withJson(['result' => false]);
    }

    // download img
    // $files = $request->getUploadedFiles();
    // $file = array_shift($files);
    // $fileNameOriginal = $file->getClientFilename();

    // var_dump($fileNameOriginal);
    // die;

    $name = '';
    $surName = '';
    $phoneCode = '';
    $phoneNumber = '';
    $email = $d['user_email'];
    $text = '';
    $ring_info = '';
    $price_range = '';
    $stone_shape = '';

    if (isset($d['user_name'])) {
      $name = stripslashes($d['user_name']);
      $name = htmlspecialchars($name);
    }
    if (isset($d['user_surname'])) {
      $surName = stripslashes($d['user_surname']);
      $surName = htmlspecialchars($surName);
    }
    if (isset($d['phone_code'])) {
      $phoneCode = stripslashes($d['phone_code']);
      $phoneCode = htmlspecialchars($phoneCode);
    }
    if (isset($d['phone_number'])) {
      $phoneNumber = stripslashes($d['phone_number']);
      $phoneNumber = htmlspecialchars($phoneNumber);
    }
    if (isset($d['text'])) {
      $text = stripslashes($d['text']);
      $text = htmlspecialchars($text);
    }
    if (isset($d['ring_info'])) {
      $ring_info = stripslashes($d['ring_info']);
      $ring_info = htmlspecialchars($ring_info);
    }
    if (isset($d['price_range'])) {
      $price_range = stripslashes($d['price_range']);
      $price_range = htmlspecialchars($price_range);
    }
    if (isset($d['stone_shape'])) {
      $stone_shape = stripslashes($d['stone_shape']);
      $stone_shape = htmlspecialchars($stone_shape);
    }

    $email1 = 'tanya.kovalenko@quadecco.com';
    $email2 = '';
    $template = (new MailTemplate($this->mongodb))->findOne(['type' => 'customer_form']);
    foreach ([$email1, $email2] as $toEmail) {
      $mailResult = $this->mailer->send($template->body, [
        '%name%' => $name,
        '%surname%' => $surName,
        '%email%' => $email,
        '%phoneCode%' => $phoneCode,
        '%phoneNumber%' => $phoneNumber,
        '%ring_info%' => $ring_info,
        '%price_range%' => $price_range,
        '%stone_shape%' => $stone_shape,
        '%text%' => $text,
      ], function ($message) use ($toEmail) {
        $message->to($toEmail);
        $message->subject('Custom Form');
      });
    }

    return $response->withJson(['result' => !!$mailResult]);
  }

  /**
   * Filter display
   *
   * @param Request $request
   * @param Response $response
   * @param $args
   * @return \Psr\Http\Message\ResponseInterface
   * @throws \Exception
   */
  public function searchAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;
    $user = $request->getAttribute('user');
    $userId = $user ? $user->_id : null;

    $shapes = (new Shape($this->mongodb))->find();
    $colors = (new Color($this->mongodb))->find();
    $cuts = (new Cut($this->mongodb))->find();
    $clarities = (new Clarity($this->mongodb))->find();
    $polishes = (new Polish($this->mongodb))->find();
    $symmetries = (new Symmetry($this->mongodb))->find();
    $flourences = (new Flourence($this->mongodb))->find();
    $vendors = (new Vendor($this->mongodb))->getVendors();

    // if (empty($vendors))
    //   return $this->render($response, 'pages/frontend/loose_diamonds/search.twig', []);

    $Diamond = new Diamond($this->mongodb);

    $filter = [
      'price_min' => 0,
      'price_max' => 0,
      'color_min' => 0,
      'color_max' => 0,
      'depth_min' => 0,
      'depth_max' => 0,
      'table_min' => 0,
      'table_max' => 0,
      'carat_min' => 0,
      'carat_max' => 0,
      'shape_id' => null,
      'color_id' => null,
      'cut_id' => null,
      'clarity_id' => null,
      'realview' => 0,
      'lab' => '',
      'natural' => '',
      'polish_id' => null,
      'symmetry_id' => null,
      'flourence_id' => null,
      'ratio_min' => 0,
      'ratio_max' => 0,
      'offset' => 0,
      'limit' => 10,
      'ships_by' => '',
      'sort_by' => '',
    ];

    $getVars = $request->getQueryParams();

    foreach ($getVars as $key => $value)
      if (array_key_exists($key, $filter))
        $filter[$key] = $value;

    if (
      empty($filter['sort_by']) ||
      !in_array($filter['sort_by'], array_map(function ($item) {
        return $item['code'];
      }, $this->possibleSort))
    ) {
      $filter['sort_by'] = $this->possibleSort[0]['code'];
    }

    $possibleShips = [
      ['code' => '', 'title' => 'Any date'],
      ['code' => '3', 'title' => date('l, F jS', time() + 86400 * 3)],
      ['code' => '5', 'title' => date('l, F jS', time() + 86400 * 5)],
      ['code' => '9', 'title' => date('l, F jS', time() + 86400 * 9)],
    ];
    if (
      empty($filter['ships_by']) ||
      !in_array($filter['ships_by'], array_map(function ($item) {
        return $item['code'];
      }, $possibleShips))
    ) {
      $filter['ships_by'] = $possibleShips[0]['code'];
    }

    if (!empty($args['filter'])) {
      $filter_input = explode('_', $args['filter']);

      if (
        count($filter_input) == 2
        && isset($filter_input[0])
        && array_key_exists($filter_input[0], $filter)
      ) {
        $filter[$filter_input[0]] = str_replace('-', ' ', $filter_input[1]);
      }
    }

    $disabledVendors = array_filter($vendors, function ($vendor) {
      return $vendor->isEnabled === false;
    });

    $and = [
      ['isEnabled' => true],
      ['priceInternal' => ['$exists' => true, '$gt' => 0]],
      ['vendor' => ['$nin' => array_values(array_map(function ($vendor) {
        return $vendor->code;
      }, $disabledVendors))]],
    ];

    if ($shape = $Diamond->FilterByCollectionId($shapes, 'shape_id', $filter['shape_id'])) $and[] = $shape;
    if ($cut = $Diamond->FilterByCollectionId($cuts, 'cut_id', $filter['cut_id'])) $and[] = $cut;
    if ($clarity = $Diamond->FilterByCollectionId($clarities, 'clarity_id', $filter['clarity_id'])) $and[] = $clarity;
    if ($color = $Diamond->FilterByCollectionId($colors, 'color_id', $filter['color_id'])) $and[] = $color;
    if ($symmetry = $Diamond->FilterByCollectionId($symmetries, 'symmetry_id', $filter['symmetry_id'])) $and[] = $symmetry;
    if ($flourence = $Diamond->FilterByCollectionId($flourences, 'flourence_id', $filter['flourence_id'])) $and[] = $flourence;
    if ($filter['realview']) $and[] = ['imageExternal' => ['$ne' => '']];
    if ($filter['lab']) $and[] = ['$or' => array_map(function ($lab) {
      return ['lab' => $lab];
    }, explode(',', $filter['lab']))];
    if ($filter['natural'] !== 'all') $and[] = ['isNatural' => $filter['natural'] !== 'lab'];
    if ($polish = $Diamond->FilterByCollectionId($polishes, 'polish_id', $filter['polish_id'])) $and[] = $polish;
    if ($price = $Diamond->MinMaxOptions($filter['price_min'], $filter['price_max'], 'priceInternal')) $and[] = $price;
    if ($depth = $Diamond->MinMaxOptions($filter['depth_min'], $filter['depth_max'], 'depth')) $and[] = $depth;
    if ($table = $Diamond->MinMaxOptions($filter['table_min'], $filter['table_max'], 'table')) $and[] = $table;
    if ($carat = $Diamond->MinMaxOptions($filter['carat_min'], $filter['carat_max'], 'weight')) $and[] = $carat;
    if ($ratio = $Diamond->MinMaxOptions($filter['ratio_min'], $filter['ratio_max'], 'ratio')) $and[] = $ratio;
    if ($filter['ships_by'] === '3') {
      $and[] = ['city' => 'New York'];
    } elseif ($filter['ships_by'] === '5') {
      $and[] = ['country' => 'USA'];
      $and[] = ['city' => ['$ne' => 'New York']];
    }

    $isBuilder = !empty($request->getQueryParam('builder'));
    if ($isBuilder) {
      $composite = (new Composite($this->mongodb))->getDetails();
      if (!empty($composite->product->builder_compatible)) {
        $or = [];
        $shapeIds = [];
        foreach ($composite->product->builder_compatible as $compatibleShape) {
          $shapeIds[] = $compatibleShape->shape_id;
          $or[] = ['$and' => [
            ['shape_id' => new ObjectId($compatibleShape->shape_id)],
            ['weight' => ['$gte' => new Decimal128($compatibleShape->weight_min)]],
            ['weight' => ['$lte' => new Decimal128($compatibleShape->weight_max)]],
          ]];
        }
        if (!empty($shapeIds)) {
          $shapes = array_values(array_filter($shapes, function ($shape) use ($shapeIds) {
            return in_array($shape->_id->__toString(), $shapeIds);
          }));
        }
        $and[] = ['$or' => $or];
      }
    }

    // Memcached
    // if (!($diamondsTotal = $this->memcached->get(hash('md5', json_encode($and))))) {
    //   $diamondsTotal = $Diamond->countDocuments(['$and' => $and]);
    //   $this->memcached->set(hash('md5', json_encode($and)), $diamondsTotal, 12 * 60 * 60);
    // }

    $find = [
      ['$match' => ['$and' => $and]],
    ];
    $find = $this->includeSorting($find, $filter['sort_by']); // NOTE: sort should be before limit
    $find[] = ['$skip' => (int) $filter['offset']];
    $find[] = ['$limit' => (int) $filter['limit']];
    $diamonds = $Diamond->aggregate($find);

    $shapeNames = $colorNames = $cutNames = $clarityNames = $polishNames =
      $symmetryNames = $flourenceNames = $vendorNames = [];
    foreach ($shapes as $shape) $shapeNames[$shape->_id->__toString()] = $shape;
    foreach ($colors as $color) $colorNames[$color->_id->__toString()] = $color;
    foreach ($cuts as $cut) $cutNames[$cut->_id->__toString()] = $cut;
    foreach ($clarities as $clarity) $clarityNames[$clarity->_id->__toString()] = $clarity;
    foreach ($polishes as $polish) $polishNames[$polish->_id->__toString()] = $polish;
    foreach ($symmetries as $symmetry) $symmetryNames[$symmetry->_id->__toString()] = $symmetry;
    foreach ($flourences as $flourence) $flourenceNames[$flourence->_id->__toString()] = $flourence;
    foreach ($vendors as $vendor) $vendorNames[$vendor->code] = $vendor;

    $Favorite = new Favorite($this->mongodb, $userId);
    $Compare = new Compare($this->mongodb);

    $diamondsResult = array_map(function ($diamond) use (
      $Diamond,
      $Favorite,
      $Compare,
      $shapeNames,
      $colorNames,
      $cutNames,
      $clarityNames,
      $polishNames,
      $symmetryNames,
      $flourenceNames,
      $vendorNames
    ) {
      $diamond->vendor = $diamond->vendor ?? null;
      $vendor = $vendorNames[strtolower($diamond->vendor)] ?? null;
      return array_merge((array)$diamond, [
        'title' => $Diamond->getTitle($diamond),
        'permalink' => $Diamond->getPermalink($diamond),
        'price' => $Diamond->getPrice($diamond),
        'isFavorite' => $Favorite->isFavorite('diamonds', $diamond),
        'isCompare' => $Compare->isCompare('diamonds', $diamond->_id),
        'shape' => isset($diamond->shape_id) && isset($shapeNames[$diamond->shape_id]) ? $shapeNames[$diamond->shape_id] : '',
        'color' => isset($diamond->color_id) && isset($colorNames[$diamond->color_id]) ? $colorNames[$diamond->color_id] : '',
        'cut' => isset($diamond->cut_id) && isset($cutNames[$diamond->cut_id]) ? $cutNames[$diamond->cut_id] : '',
        'clarity' => isset($diamond->clarity_id) && isset($clarityNames[$diamond->clarity_id]) ? $clarityNames[$diamond->clarity_id] : '',
        'polish' => isset($diamond->polish_id) && isset($polishNames[$diamond->polish_id]) ? $polishNames[$diamond->polish_id] : '',
        'symmetry' => isset($diamond->symmetry_id) && isset($symmetryNames[$diamond->symmetry_id]) ? $symmetryNames[$diamond->symmetry_id] : '',
        'flourence' => isset($diamond->flourence_id) && isset($flourenceNames[$diamond->flourence_id]) ? $flourenceNames[$diamond->flourence_id] : '',
        'imageExternal' => !empty($vendor->showImages) ? $diamond->imageExternal : '',
        'showCerts' => !empty($vendor->showCerts),
        'showImages' => !empty($vendor->showImages),
        'isLocal' => !empty($vendor->isLocal),
        'length' => $Diamond->getLength($diamond),
        'width' => $Diamond->getWidth($diamond),
        'vendorEnabled' => !(empty($vendor) ? false : $vendor->isEnabled == false),
      ]);
    }, $diamonds);

    if (isset($getVars['json'])) {
      if (!count($diamonds)) {
        return $response->withJson(['finish' => true, 'total' => $diamondsTotal, 'items' => []]);
      } else {
        $viewMode = isset($getVars['v']) ? $getVars['v'] : 'g';
        $template = 'pages/frontend/loose_diamonds/result.twig'; // 'g' and default
        switch ($viewMode) {
          case 't':
            $template = 'pages/frontend/loose_diamonds/result_inline.twig';
            break;
          case 'g3':
            $template = 'pages/frontend/loose_diamonds/result_g360.twig';
            break;
        }
        return $response->withJson([
          'finish' => false,
          'total' => $diamondsTotal,
          'items' => array_map(function ($diamond) use ($getVars, $template, $user) {
            return $this->view->fetch($template, [
              'diamond' => $diamond,
              'params' => $getVars,
              'user' => $user
            ]);
          }, $diamondsResult),
        ]);
      }
    }

    // Memcached
    if (!($filterMinMax = $this->memcached->get('filterMinMax'))) {
      $filterMinMax = [
        'price_min' => $Diamond->FindMinFilter('priceInternal'),
        'price_max' => $Diamond->FindMaxFilter('priceInternal'),
        'carat_min' => $Diamond->FindMinFilter('weight'),
        'carat_max' => $Diamond->FindMaxFilter('weight'),
        'depth_min' => $Diamond->FindMinFilter('depth'),
        'depth_max' => $Diamond->FindMaxFilter('depth'),
        'table_min' => $Diamond->FindMinFilter('table'),
        'table_max' => $Diamond->FindMaxFilter('table'),
        'ratio_min' => $Diamond->FindMinFilter('ratio'),
        'ratio_max' => $Diamond->FindMaxFilter('ratio'),
      ];
      $this->memcached->set('filterMinMax', $filterMinMax, 12 * 60 * 60);
    }

    $data = [
      'isBuilder' => $isBuilder,
      'diamonds' => $diamondsResult,
      'shapes' => $shapes,
      'colors' => $colors,
      'cuts' => $cuts,
      'clarities' => $clarities,
      'polishes' => $polishes,
      'symmetries' => $symmetries,
      'flourences' => $flourences,
      'filter' => $filter,
      'price_min' => $filterMinMax['price_min'],
      'price_max' => $filterMinMax['price_max'],
      'carat_min' => $filterMinMax['carat_min'],
      'carat_max' => $filterMinMax['carat_max'],
      'depth_min' => $filterMinMax['depth_min'],
      'depth_max' => $filterMinMax['depth_max'],
      'table_min' => $filterMinMax['table_min'],
      'table_max' => $filterMinMax['table_max'],
      'ratio_min' => $filterMinMax['ratio_min'],
      'ratio_max' => $filterMinMax['ratio_max'],
      'isDiamondsSection' => true,
      'params' => $getVars,
      'shape' => $args['filter'],
      'viewMode' => isset($getVars['v']) ? $getVars['v'] : 'g',
      'isMobile' => Utils::isMobile(),
      'possibleSort' => $this->possibleSort,
      'possibleShips' => $possibleShips,
      'viewedCount' => (new Viewed($this->mongodb))->count(),
      'compareCount' => (new Compare($this->mongodb))->count(),
      'g_event' => 'view_search_results',
    ];

    if ($isBuilder)
      $data['composite'] = $composite;

    return $this->render($response, 'pages/frontend/loose_diamonds/search.twig', $data);
  }

  private function includeSorting($find, $sort_by)
  {
    $sortField = $sort_by[0] === '-' ? substr($sort_by, 1) : $sort_by;
    $sortDirection = $sort_by[0] === '-' ? -1 : 1;

    switch ($sortField) {
      case 'carat':
        $sortField = 'weight';
        break;

      case 'clarity':
        $find[] = ['$lookup' => [
          'from' => 'clarity',
          'localField' => 'clarity_id',
          'foreignField' => '_id',
          'as' => 'temp.clarity',
        ]];
        $sortField = 'temp.clarity.code';
        break;

      case 'color':
        $find[] = ['$lookup' => [
          'from' => 'color',
          'localField' => 'color_id',
          'foreignField' => '_id',
          'as' => 'temp.color',
        ]];
        $sortField = 'temp.color.code';
        break;

      case 'cut':
        $find[] = ['$lookup' => [
          'from' => 'cut',
          'localField' => 'cut_id',
          'foreignField' => '_id',
          'as' => 'temp.cut',
        ]];
        $sortField = 'temp.cut.code';
        break;

      case 'price':
        $sortField = 'priceInternal';
        break;

      case 'shape':
        $find[] = ['$lookup' => [
          'from' => 'shape',
          'localField' => 'shape_id',
          'foreignField' => '_id',
          'as' => 'temp.shape',
        ]];
        $sortField = 'temp.shape.code';
        break;

      default:
        $sortField = 'priceInternal';
    }

    $find[] = [
      '$sort' => [
        $sortField => $sortDirection,
        '_id' => -1,
      ],
    ];

    return $find;
  }

  private function FilterByCollectionId($array, $collection_id, $filter)
  {
    if (!empty($filter)) {
      $inparam = explode(',', $filter);
      if (in_array('all', $inparam))
        $and[] = [$collection_id => ['$ne' => null]];
      else {
        $or = [];
        foreach ($array as $val)
          if (in_array($val->code, $inparam))
            $or[] = [$collection_id => $val->_id];
        return ['$or' => $or];
      }
    }
    return null;
  }

  private function MinMaxOptions($min, $max, $field)
  {
    if (
      (!empty($min) && is_numeric($min))
      || (!empty($max) && is_numeric($max))
    ) {
      $price = [];
      if (!empty($min) && is_numeric($min))
        $price['$gte'] = new Decimal128($min);
      if (!empty($max) && is_numeric($max))
        $price['$lte'] = new Decimal128($max);
      return [$field => $price];
    }
    return null;
  }

  private function FindMinFilter($Diamond, $field)
  {
    $lowest = $Diamond->find(empty($and) ? [] : ['$and' => $and], ['sort' => [$field => 1], 'limit' => 1]);
    return empty($lowest[0]->$field) ? 0 : (float)$lowest[0]->$field->__toString();
  }

  private function FindMaxFilter($Diamond, $field)
  {
    $highest = $Diamond->find(empty($and) ? [] : ['$and' => $and], ['sort' => [$field => -1], 'limit' => 1]);
    return empty($highest) ? 0 : (float)$highest[0]->$field->__toString();
  }
  /**
   * Display diamond compare
   *
   * @param Request $request
   * @param Response $response
   * @param $args
   * @return \Psr\Http\Message\ResponseInterface
   * @throws \Exception
   */
  public function compareAction(Request $request, Response $response, $args)
  {
    // return $this->render($response, 'pages/frontend/loose_diamonds/compare.twig', ['isDiamondsSection' => true]);
    // ugly way for accessing request attributes
    $this->request = $request;

    $Compare = new Compare($this->mongodb);
    $diamonds = $Compare->get('diamonds');

    return $this->render($response, 'pages/frontend/loose_diamonds/compare/index.twig', [
      'diamonds' => $diamonds,
      'viewedCount' => (new Viewed($this->mongodb))->count(),
      'compareCount' => $Compare->count('diamonds'),
      'g_event' => 'view_search_results',
    ]);
  }

  /**
   * Display diamond details
   *
   * @param Request $request
   * @param Response $response
   * @param $args
   * @return \Psr\Http\Message\ResponseInterface
   */
  public function detailsAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;
    $user = $request->getAttribute('user');
    $userId = $user ? $user->_id : null;

    $diamondSef = $args['diamond'];

    if (!empty($diamondSef)) {
      $diamondFilter = explode('-', $diamondSef, 11);
      if (count($diamondFilter) > 2) {
        $diamondFilter = end($diamondFilter);
        $diamondFilter = explode('_', $diamondFilter, 2);
      } else {
        $diamondFilter = explode('_', $diamondSef, 2);
      }
    }

    $find = ['isEnabled' => true];
    if ($diamondFilter[0]) $find['certificateNumber'] = $diamondFilter[0];
    if (!empty($diamondFilter[1])) $find['stockNumber'] = str_replace('!', '/', $diamondFilter[1]);

    $notFoundParams = ['text' => 'Unfortunately this diamond is no longer available.'];
    if (empty($find))
      return $this->render($response, 'pages/frontend/loose_diamonds/details/404.twig', $notFoundParams);

    $Diamond = new Diamond($this->mongodb);
    $diamond = $Diamond->getOneWhere($find);
    if ($diamond && !empty($diamond->priceInternal)) {
      $Diamond->populate($diamond);
    } else {
      return $this->render($response, 'pages/frontend/loose_diamonds/details/404.twig', $notFoundParams);
    }

    if ($diamond->isEnabled && !empty($diamond->vendor)) {
      $vendor = (new Vendor($this->mongodb))->getOneWhere(['code' => strtolower($diamond->vendor)]);
    }

    if ((empty($vendor) ? false : $vendor->isEnabled == false) || !$diamond->isEnabled) {
      $notFoundParams['diamond'] = $diamond;
      $notFoundParams['colors'] = join(',', array_map(function ($color) {
        return $color->code;
      }, (new Color($this->mongodb))->getSimilar($diamond->color)));
      $notFoundParams['clarities'] = join(',', array_map(function ($clarity) {
        return $clarity->code;
      }, (new Clarity($this->mongodb))->getSimilar($diamond->clarity)));
      return $this->render($response, 'pages/frontend/loose_diamonds/details/404.twig', $notFoundParams);
    }

    if (!empty($diamond->imageExternal) && strpos($diamond->imageExternal, '//dna.')) {
      $diamond->dna = $diamond->imageExternal;
      $diamond->imageExternal = '';
    }
    if (empty($vendor->showImages)) {
      $diamond->imageExternal = '';
    }
    $diamond->shippingDetails = $Diamond->getShippingDetails($diamond, -5);

    $Viewed = new Viewed($this->mongodb);
    $Viewed->add('diamonds', $diamond->_id);

    $diamond->produced = $diamond->isNatural ? 'Natural' : 'Lab Grown';
    $diamond->title = $Diamond->getTitle($diamond);
    $diamond->permalink = $Diamond->getPermalink($diamond, $request->getUri()->getBaseUrl());
    $diamond->price = $Diamond->getPrice($diamond);
    // $diamond->imageExternal = $request->getUri()->getBaseUrl() . $diamond->imageExternal;

    $productJson = clone $diamond;
    $productJson->group = 'diamonds';

    $isBuilder = true;
    $data = [
      'isFavorite' => (new Favorite($this->mongodb, $userId))->isFavorite('diamonds', $diamond),
      'isBuilder' => $isBuilder,
      'product' => $diamond,
      'productJson' => json_encode($productJson, JSON_HEX_QUOT),
      'similar' => $Diamond->getSimilar($diamond),
      'viewed' => [
        'products' => $Viewed->get('products'),
        'diamonds' => $Viewed->get('diamonds', $diamond->_id),
      ],
      'showCerts' => !empty($vendor->showCerts),
      'showImages' => !empty($vendor->showImages),
      'isLocal' => !empty($vendor->isLocal),
      'staticpages' => (new StaticPages($this->mongodb))->find(),
      'isDiamondsSection' => true,
      'params' => $request->getQueryParams(),
      'g_event' => 'view_item',
    ];

    if ($isBuilder)
      $data['composite'] = (new Composite($this->mongodb))->getDetails();

    return $this->render($response, 'pages/frontend/loose_diamonds/details/index.twig', $data);
  }
}
