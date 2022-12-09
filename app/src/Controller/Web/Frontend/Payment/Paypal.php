<?php

namespace DS\Controller\Web\Frontend\Payment;

use DS\Model\Order;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;
use mongodb\BSON\ObjectID;
use DS\Model\Cart;
use DS\Model\User;
use DS\Core\Controller\WebController;

/**
 * Class Front
 * @package DS\Controller\Web
 */
final class Paypal extends WebController
{

  const EMAIL = 'sb-ohf2c2749357@business.example.com';

  /** Production Postback URL */
  // const VERIFY_URI = 'https://ipnpb.paypal.com/cgi-bin/webscr';
  const VERIFY_URI = 'https://www.paypal.com/cgi-bin/webscr';

  /** Sandbox Postback URL */
  // const SANDBOX_VERIFY_URI = 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';
  const SANDBOX_VERIFY_URI = 'https://www.sandbox.paypal.com/cgi-bin/webscr';

  /** Response from PayPal indicating validation was successful */
  const VALID = 'VERIFIED';
  /** Response from PayPal indicating validation failed */
  const INVALID = 'INVALID';

  const SUCCESS_URL = 'http://ds.quadecco.org/payment/paypal/successful';
  const CANCEL_URL = 'http://ds.quadecco.org/payment/paypal/cancelled';
  const NOTIFY_URL = 'http://ds.quadecco.org/payment/paypal/notify';

  public function __construct(Container $c)
  {
    parent::__construct($c);

    $s = $this->settings['paypal'];

    $this->email = $s['sandbox'] ? $s['dev']['email'] : $s['prod']['email'];
    $this->verify_uri = $s['sandbox'] ? $s['dev']['verify_uri'] : $s['prod']['verify_uri'];

    $this->success_path = $s['sandbox'] ? $s['dev']['success_path'] : $s['prod']['success_path'];
    $this->cancel_path = $s['sandbox'] ? $s['dev']['cancel_path'] : $s['prod']['cancel_path'];
    $this->notify_path = $s['sandbox'] ? $s['dev']['notify_path'] : $s['prod']['notify_path'];
  }

  /**
   * Determine endpoint to post the verification data to.
   *
   * @return string
   */
  public function getPaypalUri()
  {
    return $this->verify_uri;
  }

  public function paymentAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $params = $request->getQueryParams();

    $d = $request->getParsedBody();

    $paypalConfig = [
      'email' => $this->email,
      'success_url' => $request->getUri()->getBaseUrl() . $this->success_path,
      'cancel_url' => $request->getUri()->getBaseUrl() . $this->cancel_path,
      'notify_url' => $request->getUri()->getBaseUrl() . $this->notify_path,
    ];

    if (!isset($d["txn_id"]) && !isset($d["txn_type"])) {
      $paypalData = [];

      if (!empty($params['orderId'])) {
        $Order = (new Order($this->mongodb))->findOne(['_id' => new ObjectID($params['orderId'])]);
        $paypalData['cmd'] = '_xclick';
        $paypalData['no_note'] = '1';
        $paypalData['item_number'] = '1';
        $paypalData['business'] = $paypalConfig['email'];
        $paypalData['return'] = stripslashes($paypalConfig['success_url'] . '?orderId=' . $params['orderId']);
        $paypalData['cancel_return'] = stripslashes($paypalConfig['cancel_url'] . '?orderId=' . $params['orderId']);
        $paypalData['notify_url'] = stripslashes($paypalConfig['notify_url']);
        $paypalData['item_name'] = 'Order ' . $params['orderId'];
        $paypalData['amount'] = $Order->amount->total;
        $paypalData['currency_code'] = 'USD';
        $paypalData['custom'] = $params['orderId'];

        $queryString = http_build_query($paypalData);

        // Redirect to IPN
        header('location:' . $this->getPaypalUri() . '?' . $queryString);
        exit();
      }
    }

    return $response->withJson($paypalData);
  }

  public function addPayment($paymentResult)
  {
    $orderId = new ObjectID($paymentResult['custom']);
    $Order = new Order($this->mongodb);
    $currentOrder = $Order->findOne(['_id' => $orderId]);
    $Order->updateWhere([
      'payments' => empty($currentOrder->payments)
        ? [$paymentResult]
        : array_merge($currentOrder->payments, [$paymentResult]),
      'status' => (isset($paymentResult['payment_status']) && $paymentResult['payment_status'] == 'Completed')
        ? 'Paid'
        : $currentOrder->status,
      'paid' => (isset($paymentResult['payment_status']) && $paymentResult['payment_status'] == 'Completed')
        ? 'Paid'
        : 'Failed',
    ], ['_id' => $orderId]);

    return true;
  }

  public function verifyTransaction($paymentData)
  {
    $req = 'cmd=_notify-validate';
    foreach ($paymentData as $key => $value) {
      $value = urlencode(stripslashes($value));
      $value = preg_replace('/(.*[^%^0^D])(%0A)(.*)/i', '${1}%0D%0A${3}', $value); // IPN fix
      $req .= "&$key=$value";
    }

    try {
      $ch = curl_init($this->getPaypalUri());
      curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
      curl_setopt($ch, CURLOPT_SSLVERSION, 6);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
      curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));

      $res = curl_exec($ch);

      if (!$res) {
        $errno = curl_errno($ch);
        $errstr = curl_error($ch);
        curl_close($ch);

        throw new Exception("cURL error: [$errno] $errstr");
      }

      $info = curl_getinfo($ch);

      // Check the http response
      $httpCode = $info['http_code'];
      if ($httpCode != 200) {
        throw new Exception("PayPal responded with http code $httpCode");
      }

      curl_close($ch);
    } catch (Exception $e) {
      $this->logger->error("PAYMENT PAYPAL - VERIFICATION ERROR: " . $e->getMessage());
    }

    // $this->logger->warn("PAYMENT PAYPAL - VERIFICATION:\n" . $res);

    return $res === self::VALID;
  }

  function checkTxnid($txnid)
  {

    return true;
  }

  public function paymentSuccessful(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    // $this->logger->warn("PAYMENT PAYPAL - SUCCESS:\n");

    $user = $request->getAttribute('user');
    $Cart = new Cart($this->mongodb);

    $Cart->flush($user);

    $orderId = $request->getQueryParam('orderId');

    return $response->withRedirect('/confirmation?orderId=' . $orderId);
  }

  public function paymentCancelled(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    // $this->logger->warn("PAYMENT PAYPAL - CANCELLED:\n");

    $orderId = $request->getQueryParam('orderId');

    return $response->withRedirect('/payment?orderId=' . $orderId);
  }

  public function paymentNotify(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    if ($request->getMethod() === 'POST') {
      $d = $request->getParsedBody();

      // $this->logger->warn("PAYMENT PAYPAL - NOTIFIED:\n" . json_encode($d));

      $transactionVerified = $this->verifyTransaction($d);
      $transactionChecked = $this->checkTxnid($d['txn_id']);

      if ($transactionVerified && $transactionChecked) {
        if ($this->addPayment($d) !== false) {
          // $this->logger->warn("PAYMENT PAYPAL - PAYMENT:\n" . json_encode($d));

          return $response->withStatus(200);
        }
      }
    }
  }
}
