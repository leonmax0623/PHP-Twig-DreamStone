<?php

namespace DS\Controller\Web\Frontend;

use DS\Core\Controller\WebController;
use DS\Controller\Web\Frontend\Verification;
use DS\Core\Utils;
use MongoDB\BSON\Timestamp;
use Slim\Http\Request;
use Slim\Http\Response;
use DS\Model\User as UserModel;
use DS\Model\Cart;
use DS\Model\Favorite;
use DS\Model\MailTemplate;

/**
 * Class Front
 * @package DS\Controller\Web
 */
final class User extends WebController
{

  /**
   * Do registration new user in system
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   * @throws \Exception
   */
  public function registerAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $d = $request->getParsedBody();

    if (empty($d['g-recaptcha-response'])) {
      return $response->withJson(['result' => false]);
    }

    $Verification = (new Verification($this->c));

    $r = $Verification->getResultByToken($d['g-recaptcha-response']);

    // $this->logger->info('register recaptcha: ' . $r);

    if (!$r) {
      $this->setInvalid();
    }

    if ($this->isValid()) {
      if ($d['password'] !== $d['password2'])
        $this->addError('password', 'Entered passwords not match');
    }

    if ($this->isValid()) {
      if ((new UserModel($this->mongodb))->findOne(['email' => $d['email']]))
        $this->addError('email', 'Sorry this email is already registered');
    }

    if ($this->isValid()) {
      (new UserModel($this->mongodb))->insertOne([
        'first_name' => $d['first_name'],
        'last_name' => $d['last_name'],
        'email' => $d['email'],
        'password' => Utils::hashPassword($d['password']),
        'sex' => $d['sex'] ?? '',
        'created' => new Timestamp(0, time()),
        'customer_id' => '',
        'company' => '',
        'phone' => '',
        'address' => '',
        'address2' => '',
        'city' => '',
        'state' => '',
        'country' => '',
        'zip' => '',
      ]);

      $User = (new UserModel($this->mongodb))->findOne(['email' => $d['email']]);

      // sending "registered user" mail to admin
      $this->sendUserRegisteredEmail($User);
      $this->sendUserRegisteredEmailToUser($User, $request);
    }

    return $this->loginAction($request, $response, $args, true);
  }


  /**
   * Do login exists user in system
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   * @throws \Exception
   */
  public function loginAction(Request $request, Response $response, $args, $verified = false)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $hash = $request->getQueryParam('h');
    $UserModel = new UserModel($this->mongodb);
    $message = '';
    if (!empty($hash)) {
      $user = $UserModel->findOne(['hash' => $hash]);

      if (empty($user)) {
        $this->addError('hash', 'Unknown user');
      } else {
        $password = bin2hex(random_bytes(4));
        $UserModel->updateOne(['hash' => $hash], ['$set' => [
          'hash' => '',
          'password' => Utils::hashPassword($password),
        ]]);

        // sending "new password" mail to the user
        $this->sendNewPasswordEmailToUser($user, $request, $password);
        $message = "A new password has been sent to your email, please check. Use that temporary password and your email to login here. After login you'll be able to go to your profile and change the password.";
      }
    }

    if ($request->getMethod() == 'POST') {
      $d = $request->getParsedBody();

      if (empty($d['g-recaptcha-response'])) {
        return $response->withJson(['result' => false]);
      }

      $Verification = (new Verification($this->c));

      $r = $Verification->getResultByToken($d['g-recaptcha-response']);

      // $this->logger->info('login recaptcha: ' . $r);

      if (!$r && !$verified) {
        $this->setInvalid();
      }

      if ($this->isValid()) {
        $user = $UserModel->findOne(['email' => $d['email']]);

        if (!$user || !password_verify($d['password'], $user->password))
          $this->addError('email', 'Unknown e-mail address or password');
        else {
          $this->session->isLogged = true;
          $this->session->token = Utils::newGUID();
          $this->session->user_id = $user->_id;
          $this->session->user = $user;

          (new Favorite($this->mongodb, $user->_id))->onLogin();

          $returnUrl = $request->getQueryParam('returnUrl');
          $isChanged = (new Cart($this->mongodb))->onLogin($user->_id);
          if ($returnUrl === '/cart' && !$isChanged) // don't show cart again if not changed
            $returnUrl = '/payment-method';

          if ($returnUrl)
            return $response->withRedirect($returnUrl);

          $this->request = $this->request->withAttribute('user', $user);
        }
      }
    } else {
      $this->setInvalid();
    }

    return $this->render($response, 'pages/frontend/user/login.twig', [
      'isValid' => $this->isValid(),
      'errors' => $this->getErrors(),
      'message' => $message,
      'post' => $_POST,
      'get' => $_GET,
    ]);
  }


  /**
   * Do logout
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   * @throws \Exception
   */
  public function logoutAction(Request $request, Response $response, $args)
  {
    $this->session->clear();

    return $response->withRedirect('/');
  }


  /**
   * View/edit self profile
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   * @throws \Exception
   */
  public function profileAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    if ($request->getMethod() == 'POST') {
      $d = $request->getParsedBody();
      $user = $this->session->user;

      if ($this->isValid()) {
        if (!empty($d['current_password'])) {
          if (!password_verify($d['current_password'], $user->password))
            $this->addError('current_password', 'Wrong current password entered');

          if ($d['password'] != $d['password2'] || empty($d['password']))
            $this->addError('password', 'New password should be not empty and equal to conform password');

          if ($this->isValid()) {
            $d['password'] = Utils::hashPassword($d['password']);
            unset($d['current_password']);
            unset($d['password2']);
          }
        }

        if ($this->isValid()) {
          $UserModel = new UserModel($this->mongodb);

          $UserModel->updateOne(['_id' => $user->_id], ['$set' => $d]);
          $this->session->user = $UserModel->findOne(['_id' => $user->_id]);
        }
      }

      $_POST['email'] = $this->session->user->email;
    } else {
      foreach (['first_name', 'last_name', 'email'] as $key)
        $_POST[$key] = $this->session->user->$key;
    }

    return $this->render($response, 'pages/frontend/user/profile.twig', [
      'isValid' => $this->isValid(),
      'errors' => $this->getErrors(),
      'post' => $_POST,
      'method' => $request->getMethod(),
      'isUserSection' => true,
    ]);
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
  public function forgotPasswordAction(Request $request, Response $response, $args)
  {
    $this->request = $request;

    $d = $request->getParsedBody();

    if (empty($d['gToken'])) {
      return $response->withJson(['result' => false]);
    }

    $Verification = (new Verification($this->c));

    $r = $Verification->getResultByToken($d['gToken']);

    // $this->logger->info('forgot recaptcha: ' . $r);

    if (!$r) {
      $this->setInvalid();
    }

    if ($this->isValid()) {
      $User = (new UserModel($this->mongodb))->findOne(['email' => $d['userEmail']]);
      if (empty($User))
        $this->addError('email', 'Sorry this email is not registered');

      $hash = bin2hex(random_bytes(16));
      (new UserModel($this->mongodb))->updateOne([
        'email' => $d['userEmail'],
      ], [
        '$set' => ['hash' => $hash],
      ]);

      // sending "forgot password" mail to the user
      $this->sendForgotPasswordEmailToUser($User, $request, $hash);
    }

    $returnUrl = $request->getQueryParam('returnUrl') ?? '/user/login';

    return $response->withRedirect($returnUrl);
  }

  public function sendUserRegisteredEmail($User)
  {
    $template = (new MailTemplate($this->mongodb))->findOne(['type' => 'admin_user_registered']);
    $bodyTemplate = $template->body;
    $bodyData = [
      '%userEmail%' => $User->email,
      '%userFirstname%' => $User->first_name,
      '%userLastname%' => $User->last_name,
    ];
    $email = $this->settings['mailer']['adminMail'];
    $subject = $template->subject;
    $mailResult = $this->mailer->send($bodyTemplate, $bodyData, function ($message) use ($email, $subject) {
      $message->to($email);
      $message->subject($subject);
    });
  }

  public function sendUserRegisteredEmailToUser($User, $request)
  {
    $uri = $request->getUri()->getBaseUrl();
    $diamondHref = $uri . '/loose-diamonds/search?v=g&carat_min=1.00&color_id=J%2CI%2CH%2CG%2CF%2CE%2CD&clarity_id=SI2%2CSI1%2CVS2%2CVS1%2CVVS2%2CVVS1%2CIF%2CFL';
    $ringsHref = $uri . '/engagement-rings/search?builder=1';
    // $contactHref = $uri . '/pages/contact-us';
    $contactHref = 'https://dreamstone.com/' . '/pages/contact-us';
    // $myAccountHref = $uri . '/user/login?returnUrl=/user/profile';
    $myAccountHref = 'https://dreamstone.com/' . '/user/login?returnUrl=/user/profile';
    // $policyHref = $uri . '/pages/privacy';
    $policyHref = 'https://dreamstone.com/' . '/pages/privacy';
    // $bandsHref = $uri . '/wedding-rings';
    // $jewelryHref = $uri . '/jewelry';

    $userEmail = $User->email;
    $template = (new MailTemplate($this->mongodb))->findOne(['type' => 'user_registered']);

    $bodyTemplate = $template->body;
    $bodyData = [
      '%userEmail%' => $User->email,
      '%userFirstname%' => $User->first_name,
      '%userLastname%' => $User->last_name,
      '%uri%' => 'https://dreamstone.com/' . 'user/login',
      '%diamondHref%' => $diamondHref,
      '%ringsHref%' => $ringsHref,
      '%contactHref%' => $contactHref,
      '%myAccountHref%' => $myAccountHref,
      '%policyHref%' => $policyHref,
    ];
    $subject = $template->subject;
    $mailResult = $this->mailer->send($bodyTemplate, $bodyData, function ($message) use ($userEmail, $subject) {
      $message->to($userEmail);
      $message->subject($subject);
    });
  }

  public function sendForgotPasswordEmailToUser($User, $request, $hash)
  {
    $uri = $request->getUri()->getBaseUrl();
    $diamondHref = $uri . '/loose-diamonds/search?v=g&carat_min=1.00&color_id=J%2CI%2CH%2CG%2CF%2CE%2CD&clarity_id=SI2%2CSI1%2CVS2%2CVS1%2CVVS2%2CVVS1%2CIF%2CFL';
    $ringsHref = $uri . '/engagement-rings/search?builder=1';
    // $contactHref = $uri . '/pages/contact-us';
    $contactHref = 'https://dreamstone.com/' . '/pages/contact-us';
    // $myAccountHref = $uri . '/user/login?returnUrl=/user/profile';
    $myAccountHref = 'https://dreamstone.com/' . '/user/login?returnUrl=/user/profile';
    // $policyHref = $uri . '/pages/privacy';
    $policyHref = 'https://dreamstone.com/' . '/pages/privacy';
    // $bandsHref = $uri . '/wedding-rings';
    // $jewelryHref = $uri . '/jewelry';

    $userEmail = $User->email;
    $template = (new MailTemplate($this->mongodb))->findOne(['type' => 'user_forgot_password']);

    $bodyTemplate = $template->body;
    $bodyData = [
      '%userEmail%' => $User->email,
      '%userFirstname%' => $User->first_name,
      '%userLastname%' => $User->last_name,
      '%uri%' => 'https://dreamstone.com' . '/user/login?h=' . $hash . '&returnUrl=/',
      '%diamondHref%' => $diamondHref,
      '%ringsHref%' => $ringsHref,
      '%contactHref%' => $contactHref,
      '%myAccountHref%' => $myAccountHref,
      '%policyHref%' => $policyHref,
    ];
    $subject = $template->subject;
    $mailResult = $this->mailer->send($bodyTemplate, $bodyData, function ($message) use ($userEmail, $subject) {
      $message->to($userEmail);
      $message->subject($subject);
    });
  }

  public function sendNewPasswordEmailToUser($User, $request, $password)
  {
    $uri = $request->getUri()->getBaseUrl();
    $diamondHref = $uri . '/loose-diamonds/search?v=g&carat_min=1.00&color_id=J%2CI%2CH%2CG%2CF%2CE%2CD&clarity_id=SI2%2CSI1%2CVS2%2CVS1%2CVVS2%2CVVS1%2CIF%2CFL';
    $ringsHref = $uri . '/engagement-rings/search?builder=1';
    // $contactHref = $uri . '/pages/contact-us';
    $contactHref = 'https://dreamstone.com/' . '/pages/contact-us';
    // $myAccountHref = $uri . '/user/login?returnUrl=/user/profile';
    $myAccountHref = 'https://dreamstone.com/' . '/user/login?returnUrl=/user/profile';
    // $policyHref = $uri . '/pages/privacy';
    $policyHref = 'https://dreamstone.com/' . '/pages/privacy';
    // $bandsHref = $uri . '/wedding-rings';
    // $jewelryHref = $uri . '/jewelry';

    $userEmail = $User->email;
    $template = (new MailTemplate($this->mongodb))->findOne(['type' => 'user_new_password']);

    $bodyTemplate = $template->body;
    $bodyData = [
      '%userEmail%' => $User->email,
      '%userFirstname%' => $User->first_name,
      '%userLastname%' => $User->last_name,
      '%uri%' => 'https://dreamstone.com/' . 'user/login?returnUrl=/',
      '%diamondHref%' => $diamondHref,
      '%ringsHref%' => $ringsHref,
      '%contactHref%' => $contactHref,
      '%myAccountHref%' => $myAccountHref,
      '%policyHref%' => $policyHref,
      '%newPassword%' => $password,
    ];
    $subject = $template->subject;
    $mailResult = $this->mailer->send($bodyTemplate, $bodyData, function ($message) use ($userEmail, $subject) {
      $message->to($userEmail);
      $message->subject($subject);
    });
  }
}
