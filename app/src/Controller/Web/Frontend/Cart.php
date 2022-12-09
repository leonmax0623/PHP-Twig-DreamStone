<?php

namespace DS\Controller\Web\Frontend;

use DS\Model\Composite;
use DS\Model\Diamond;
use DS\Model\JewelryType;
use DS\Model\Order;
use MongoDB\BSON\Regex;
use Slim\Http\Request;
use Slim\Http\Response;
use mongodb\BSON\ObjectID;
use DS\Model\Cart as CartModel;
use DS\Model\Cart\Shipping;
use DS\Model\Tax;
use DS\Model\Coupon;
use DS\Model\Color;
use DS\Model\Clarity;
use DS\Model\User;
use DS\Model\Product;
use DS\Model\MailTemplate;
use DS\Core\Utils;
use DS\Core\Controller\WebController;
use DS\Controller\Web\Frontend\Payment\AuthorizeNet;
use DS\Controller\Web\Frontend\Payment\Paypal;

/**
 * Class Front
 * @package DS\Controller\Web
 */
final class Cart extends WebController
{

  /**
   * Cart renderer
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   */
  public function getAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $user = $request->getAttribute('user');
    $cart = (new CartModel($this->mongodb))->getForUser($user);
    $JewelryType = new JewelryType($this->mongodb);
    $Product = new Product($this->mongodb);
    $Diamond = new Diamond($this->mongodb);
//    $Composite = new Composite($this->mongodb);
    $subtotal = 0;
    foreach ($cart->products as &$product) {
      $subtotal += $product->price * $product->qty;
      $product->jewelrytype = $JewelryType->getOneWhere(['_id' => $product->jewelrytype_id]);
      $product->images = $Product->getImages($product);
    }
    foreach ($cart->diamonds as &$product) {
      $subtotal += $Diamond->getPrice($product);
    }
    foreach ($cart->composite as &$product) {
      $product->diamond->title = $Diamond->getTitle($product->diamond);
      $product->diamond->price = $Diamond->getPrice($product->diamond);
      $product->diamond->permalink = $Diamond->getPermalink($product->diamond);

      $Product->populate($product->product);
      $product->product->permalink = $Product->getPermalink($product->product);

//      $product->permalink = $Composite->getPermalink((object) [
//        'product' => $product->product,
//        'diamond' => $product->diamond,
//      ]);
      $product->price = $product->product->price + $product->diamond->price;

      $subtotal += $product->price;
    }
    $oldSubtotal = $subtotal;
    if ($cart->coupon) {
      $subtotal = (new Coupon($this->mongodb))->applyDiscount($cart->coupon, $subtotal);
      $cart->coupon = (new Coupon($this->mongodb))->findOne(['code' => $cart->coupon]);
    } else {
      $cart->coupon = null;
    }

    $shipping = (new Shipping($this->mongodb, ['address' => 'USA', 'price' => $subtotal]))->getDetails();
    $shippingPrice = empty($cart->products) && empty($cart->diamonds) && empty($cart->composite)
      ? 0
      : $shipping->price;
    $tax = (new Tax($this->mongodb))->getDetails();

    list($orderDate, $shipsDate) =
      $this->getShippingDetails(array_merge($cart->products, array_map(function($composite){
        return $composite->product; // check only products date
      }, $cart->composite)), -5);
//      $this->getShippingDetails($cart->diamonds, -5); // not specified

    $total = ($subtotal + $shippingPrice + $tax->price) * (1 - $cart->bankDiscount * 0.01);

    return $this->render($response, 'pages/frontend/cart/index.twig', [
      'step' => 0,
      'products' => $cart->products,
      'diamonds' => $cart->diamonds,
      'composite' => $cart->composite,
      'coupon' => $cart->coupon ? $cart->coupon->code : '',
      'subtotal' => $subtotal,
      'discount' => max($oldSubtotal - $subtotal, 0),
      'bankDiscount' => max($cart->bankDiscount, 0),
      'shipping' => $shippingPrice,
      'orderDate' => $orderDate,
      'shipsDate' => $shipsDate,
      'tax' => $tax->price,
      'total' => $total,
      'isCartSection' => true,
    ]);
  }

  private function getShippingDetails($products, $timezone_offset = 0) {
    $Product = new Product($this->mongodb);

    $orderDate = time() + 3600 * $timezone_offset;
    $shipsDate = '';
    foreach ($products as &$product) {
      $shippingDetails = $Product->getShippingDetails($product, $timezone_offset);
      if (!$shipsDate) {
        $orderDate = $shippingDetails['orderBy'];
        $shipsDate = $shippingDetails['shipsBy'];
      }
      if ($shippingDetails['shipsBy'] > $shipsDate) {
        $shipsDate = $shippingDetails['shipsBy'];
      }
    }

    return [$orderDate, $shipsDate];
  }

  public function getCountAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $user = $request->getAttribute('user');
    $cart = (new CartModel($this->mongodb))->getForUser($user);
    $count = 0;
    foreach ($cart->products as $product) {
      $count += $product->qty;
    }
    $count += count($cart->diamonds);
    $count += count($cart->composite);

    return $response->withJson(['count' => $count]);
  }

  public function addAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $d = $request->getParsedBody();
    if (
      empty($d['group'])
      || ($d['group'] !== 'composite' && empty($d['product_id']))
    ) {
      $code = 404;
      $msg = 'unrecognized product';
      $this->logger->warn($code . ': ' . $msg);
      return $response->withJson(['error' => ['code' => $code, 'msg' => $msg]]);
    }

    $payload = [];
    if ($d['group'] === 'composite') {
      switch ($d['source']) {
        case 'builder':
          $Composite = new Composite($this->mongodb);
          $composite = $Composite->getDetails();
          if (!isset($composite->product) || !isset($composite->diamond)) {
            $code = 404;
            $msg = 'unrecognized composite product';
            $this->logger->warn($code . ': ' . $msg);
            return $response->withJson(['error' => ['code' => $code, 'msg' => $msg]]);
          }
          $payload['product'] = (object)[
            '_id' => $composite->product->_id,
            'withAttributes' => $composite->product->withAttributes,
          ];
          $payload['diamond'] = (object)[
            '_id' => $composite->diamond->_id,
          ];
          $Composite->flush();
          break;
        case 'favorites':
          $payload['product'] = $d['product'];
          $payload['diamond'] = $d['diamond'];
          break;
        default:
          $code = 404;
          $msg = 'undefined source';
          $this->logger->warn($code . ': ' . $msg);
          return $response->withJson(['error' => ['code' => $code, 'msg' => $msg]]);
      }
    } else {
      $payload['_id'] = $d['product_id'];
    }
    if ($d['group'] === 'products') {
      $payload['withAttributes'] = empty($d['withAttributes']) ? [] : $d['withAttributes'];
    }

    $user = $request->getAttribute('user');
    (new CartModel($this->mongodb))->addForUser((object)$payload, $d['group'], $user);

    return $response->withHeader('Content-Type', 'application/json')->write('{}');
  }

  public function updateAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $d = $request->getParsedBody();
    if (empty($d['product_id']) || empty($d['group']) || empty($d['qty'])) {
      $code = 404;
      $msg = 'unrecognized product';
      $this->logger->warn($code . ': ' . $msg);
      return $response->withJson(['error' => ['code' => $code, 'msg' => $msg]]);
    }
    if ($d['qty'] < 1 || 999 < $d['qty']) {
      $code = 404;
      $msg = 'wrong qty';
      $this->logger->warn($code . ': ' . $msg);
      return $response->withJson(['error' => ['code' => $code, 'msg' => $msg]]);
    }

    if (!empty($d['withAttributes'])) {
      // TODO: check is product (with current product_id and attributes) exist in database
    } else {
      $d['withAttributes'] = [];
    }

    $user = $request->getAttribute('user');
    (new CartModel($this->mongodb))->updateForUser((object)[
      '_id' => $d['product_id'],
      'withAttributes' => $d['withAttributes'],
      'qty' => $d['qty']
    ], $d['group'], $user);

    return $response->withHeader('Content-Type', 'application/json')->write('{}');
  }

  public function deleteAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $d = $request->getParsedBody();
    if (
      empty($d['group'])
      || ($d['group'] !== 'composite' && empty($d['product_id']))
    ) {
      $code = 404;
      $msg = 'unrecognized product';
      $this->logger->warn($code .' ": "' . $msg);
      return $response->withJson(['error' => ['code' => $code, 'msg' => $msg]]);
    }

    $payload = [];
    if ($d['group'] === 'composite') {
      if (empty($d['composite']['product']) || empty($d['composite']['diamond'])) {
        $code = 404;
        $msg = 'unrecognized composite product';
        $this->logger->warn($code . ': ' . $msg);
        return $response->withJson(['error' => ['code' => $code, 'msg' => $msg]]);
      }
      $product = $d['composite']['product'];
      $diamond = $d['composite']['diamond'];
      $payload['product'] = (object)[
        '_id' => $product['_id'],
        'withAttributes' => empty($product['withAttributes']) ? [] : $product['withAttributes'],
      ];
      $payload['diamond'] = (object)[
        '_id' => $diamond['_id'],
      ];
    } else {
      $payload['_id'] = $d['product_id'];
    }
    if ($d['group'] === 'products') {
      $payload['withAttributes'] = empty($d['withAttributes']) ? [] : $d['withAttributes'];
    }

    $user = $request->getAttribute('user');
    (new CartModel($this->mongodb))->removeForUser((object)$payload, $d['group'], $user);

    return $response->withHeader('Content-Type', 'application/json')->write('{}');
  }

  public function postCouponAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $d = $request->getParsedBody();
    if (empty($d['code'])) {
      $code = 404;
      $msg = 'empty coupon';
      $this->logger->warn($code . ': ' . $msg);
      return $response->withJson(['error' => ['code' => $code, 'msg' => $msg]]);
    }

    $coupon = (new Coupon($this->mongodb))->findOne([
      'code' => new Regex('^' . $d['code'] . '$', 'i'),
      'count' => ['$gt' => 0]
    ]);
    if (empty($coupon)) {
      $code = 404;
      $msg = "coupon doesn't exist";
      $this->logger->warn($code . ': ' . $msg);
      return $response->withJson(['error' => ['code' => $code, 'msg' => $msg]]);
    }

    $user = $request->getAttribute('user');
    (new CartModel($this->mongodb))->updateCouponForUser($coupon->code, $user);

    return $response->withHeader('Content-Type', 'application/json')->write('{}');
  }

  public function deleteCouponAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $user = $request->getAttribute('user');
    (new CartModel($this->mongodb))->deleteCouponForUser($user);

    return $response->withHeader('Content-Type', 'application/json')->write('{}');
  }

  /**
   * Cart renderer
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   */
  public function paymentMethodAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $user = $request->getAttribute('user');
    if (!$user) {
      return $response->withRedirect('/user/login?returnUrl=' . $request->getUri()->getPath());
    }

    $cart = (new CartModel($this->mongodb))->getForUser($user);
    if (empty($cart->products) && empty($cart->diamonds) && empty($cart->composite)) {
      return $response->withRedirect('/cart');
    }

    if ($request->getMethod() === 'POST') {
      $d = $request->getParsedBody();

      if (!empty($d['payment_method'])) {
        (new CartModel($this->mongodb))->setPaymentMethodForUser($d['payment_method'], $user);
      }

      $returnUrl = $request->getQueryParam('returnUrl');
      if ($returnUrl) {
        return $response->withRedirect($returnUrl);
      }
    }

    $subtotal = 0;
    foreach ($cart->products as &$product) {
      $subtotal += $product->price * $product->qty;
    }
    foreach ($cart->diamonds as &$product) {
      $subtotal += $product->priceInternal;
    }
    foreach ($cart->composite as &$product) {
      $subtotal += $product->product->price;
      $subtotal += $product->diamond->priceInternal;
    }
    $oldSubtotal = $subtotal;
    if ($cart->coupon) {
      $subtotal = (new Coupon($this->mongodb))->applyDiscount($cart->coupon, $subtotal);
      $cart->coupon = (new Coupon($this->mongodb))->findOne(['code' => $cart->coupon]);
    } else {
      $cart->coupon = null;
    }
    $shipping = (new Shipping($this->mongodb, ['address' => 'USA', 'price' => $subtotal]))->getDetails();
    $tax = (new Tax($this->mongodb))->getDetails();

    list($orderDate, $shipsDate) =
      $this->getShippingDetails(array_merge($cart->products, array_map(function($composite){
        return $composite->product; // check only products date
      }, $cart->composite)), -5);
//      $this->getShippingDetails($cart->diamonds, -5); // not specified

    $total = ($subtotal + $shipping->price + $tax->price) * (1 - $cart->bankDiscount * 0.01);

    // Render
    return $this->render($response, 'pages/frontend/cart/payment_method.twig', [
      'step' => 2,
      'coupon' => $cart->coupon ? $cart->coupon->code : '',
      'subtotal' => $subtotal,
      'discount' => max($oldSubtotal - $subtotal, 0),
      'bankDiscount' => max($cart->bankDiscount, 0),
      'shipping' => $shipping->price,
      'orderDate' => $orderDate,
      'shipsDate' => $shipsDate,
      'tax' => $tax->price,
      'total' => $total,
      'products' => $cart->products,
      'diamonds' => $cart->diamonds,
      'composite' => $cart->composite,
    ]);
  }

  /**
   * Cart renderer
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   */
  public function billingAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $user = $request->getAttribute('user');
    if (!$user) {
      return $response->withRedirect('/user/login?returnUrl=' . $request->getUri()->getPath());
    }

    $Cart = new CartModel($this->mongodb);
    $cart = $Cart->getForUser($user);
    if (empty($cart->products) && empty($cart->diamonds) && empty($cart->composite)) {
      return $response->withRedirect('/cart');
    }
    if (!$cart->billingInfo) {
      $cart->billingInfo = [
        'shipping_country' => 'US',
        'billing_country' => 'US',
      ];
    }

    if ($request->getMethod() === 'POST') {
      $d = $request->getParsedBody();
      if (!empty($d)) {
        $d['same_billing_address'] = isset($d['same_billing_address']);
        $Cart->setBillingInfoForUser($d, $user);
        $cart->billingInfo = $d;
      }
      $returnUrl = $request->getQueryParam('returnUrl');
      if ($returnUrl) {
        return $response->withRedirect($returnUrl);
      }
    }

    $subtotal = 0;
    foreach ($cart->products as &$product) {
      $subtotal += $product->price * $product->qty;
    }
    foreach ($cart->diamonds as &$product) {
      $subtotal += $product->priceInternal;
    }
    foreach ($cart->composite as &$product) {
      $subtotal += $product->product->price;
      $subtotal += $product->diamond->priceInternal;
    }
    $oldSubtotal = $subtotal;
    if ($cart->coupon) {
      $Coupon = new Coupon($this->mongodb);
      $subtotal = $Coupon->applyDiscount($cart->coupon, $subtotal);
      $cart->coupon = $Coupon->findOne(['code' => $cart->coupon]);
    } else {
      $cart->coupon = null;
    }
    $shipping = (new Shipping($this->mongodb, ['address' => 'USA', 'price' => $subtotal]))->getDetails();
    $tax = (new Tax($this->mongodb))->getDetails();

    list($orderDate, $shipsDate) =
      $this->getShippingDetails(array_merge($cart->products, array_map(function($composite){
        return $composite->product; // check only products date
      }, $cart->composite)), -5);
//      $this->getShippingDetails($cart->diamonds, -5); // not specified

    $total = ($subtotal + $shipping->price + $tax->price) * (1 - $cart->bankDiscount * 0.01);
    $countries = (new Tax($this->mongodb))->allWhere();

    // Render
    return $this->render($response, 'pages/frontend/cart/billing_shipping.twig', [
      'step' => 3,
      'billingInfo' => $cart->billingInfo,
      'coupon' => $cart->coupon ? $cart->coupon->code : '',
      'subtotal' => $subtotal,
      'discount' => max($oldSubtotal - $subtotal, 0),
      'bankDiscount' => max($cart->bankDiscount, 0),
      'shipping' => $shipping->price,
      'orderDate' => $orderDate,
      'shipsDate' => $shipsDate,
      'tax' => $tax->price,
      'total' => $total,
      'paymentMethod' => $cart->paymentMethod,
      'products' => $cart->products,
      'diamonds' => $cart->diamonds,
      'composite' => $cart->composite,
      'countries' => $countries,
    ]);
  }

  /**
   * Cart renderer
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   */
  public function paymentAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $user = $request->getAttribute('user');
    if (!$user) {
      return $response->withRedirect('/user/login?returnUrl=' . $request->getUri()->getPath());
    }

    $Cart = new CartModel($this->mongodb);
    $cart = $Cart->getForUser($user);
    if (empty($cart->products) && empty($cart->diamonds) && empty($cart->composite)) {
      return $response->withRedirect('/cart');
    }

    if ($request->getMethod() === 'POST' || $cart->paymentMethod === 'affirm' || $cart->paymentMethod === 'paypal' || $cart->paymentMethod === 'transfer' || $cart->paymentMethod === 'phone') {
      $d = $request->getParsedBody();
      // TODO: collect payment info

      $subtotal = 0;
      foreach ($cart->products as $product) {
        $subtotal += $product->price * $product->qty;
      }
      foreach ($cart->diamonds as $product) {
        $subtotal += $product->priceInternal;
      }
      foreach ($cart->composite as &$product) {
        $subtotal += $product->product->price;
        $subtotal += $product->diamond->priceInternal;
      }
      if ($cart->coupon) {
        $subtotal = (new Coupon($this->mongodb))->applyDiscount($cart->coupon, $subtotal);
        $cart->coupon = (new Coupon($this->mongodb))->findOne(['code' => $cart->coupon]);
      } else {
        $cart->coupon = null;
      }
      $shipping = (new Shipping($this->mongodb, ['address' => 'USA', 'price' => $subtotal]))->getDetails();
      $tax = (new Tax($this->mongodb))->getDetails([
        'country' => $cart->billingInfo->shipping_country,
        'state' => $cart->billingInfo->shipping_state,
        'price' => $subtotal
      ]);

      list($orderDate, $shipsDate) = $this->getShippingDetails(array_merge($cart->products, array_map(function($composite){
        return $composite->product; // check only products date
      }, $cart->composite)), -5);
//      list($orderDate, $shipsDate) = $this->getShippingDetails($cart->diamonds, -5); // not specified

      $Order = new Order($this->mongodb);
      $order = $cart;
      $order->created = $orderDate;
      $order->amount = (object) [
        'subtotal' => round($subtotal, 2, PHP_ROUND_HALF_UP),
        'shipping' => round($shipping->price, 2, PHP_ROUND_HALF_UP),
        'tax' => round($tax->price, 2, PHP_ROUND_HALF_UP),
        'total' => round($subtotal + $shipping->price + $tax->price, 2, PHP_ROUND_HALF_UP),
      ];
      $order->shipping = [
        'date' => $shipsDate,
        'provider' => 'FedEx',
        'tracking' => ['number' => '', 'status' => ''],
      ];
      $order->number = $Order->getNextNumber();
      $order->status = 'Pending';
      $order->paid = 'Unpaid';
      $order->ip = Utils::getClientIp();
      $orderId = $Order->add($user, $order);

      if ($orderId->__toString()) {
        $Cart->flush($user);
        // sending "created order" mail to admin
        $this->sendOrderCreatedEmail($order, $request->getUri()->getHost());
        $this->sendOrderCreatedEmailToUser($order, $request->getUri()->getHost());
      }

      $paymentResult = [];

      switch ($cart->paymentMethod) {
        case 'card':
          // AuthorizeNet
          $paymentData = [
            'number' => $d['number'],
            'month' => $d['month'],
            'year' => $d['year'],
            'cid' => $d['cid'],
          ];
          $orderData = [
            'id' => $orderId->__toString(),
            'description' => 'Order ' . $orderId->__toString(),
            'amount' => $order->amount->total,
          ];
          $billingData = [
            'first_name' => $order->billingInfo->shipping_first_name,
            'last_name' => $order->billingInfo->shipping_last_name,
            'company' => $order->billingInfo->shipping_company,
            'address' => $order->billingInfo->shipping_address,
            'address2' => $order->billingInfo->shipping_address2,
            'city' => $order->billingInfo->shipping_city,
            'state' => $order->billingInfo->shipping_state,
            'zip' => $order->billingInfo->shipping_zip,
            'country' => $order->billingInfo->shipping_country,
          ];
          $userData = [
            'type' => 'individual',
            'id' => $user->_id->__toString(),
            'email' => $user->email,
          ];

          // $this->logger->warn('PAYMENT AUTHORIZENET DATA: ' . json_encode([$paymentData, $orderData, $billingData, $userData]));
          $paymentResponse = (new AuthorizeNet($this->c))->processRequest($paymentData, $orderData, $billingData, $userData);
          $paymentResult = (new AuthorizeNet($this->c))->processResponse($paymentResponse);
          // $this->logger->warn('PAYMENT AUTHORIZENET RESULT: ' . json_encode($paymentResult));
          break;

        case 'affirm':
          // Affirm
          return $response->withRedirect('/payment/affirm?orderId=' . $orderId->__toString());
          break;

        case 'paypal':
          // PayPal
          return $response->withRedirect('/payment/paypal?orderId=' . $orderId->__toString());
          break;
        
        case 'transfer':
          // Wire transfer
          return $response->withRedirect('/confirmation?orderId=' . $orderId->__toString());
          break;
      
        case 'phone':
          //By phone
          return $response->withRedirect('/confirmation?orderId=' . $orderId->__toString());
          break;

        default:
          break;
      }

      $Order->updateWhere([
        'payments' => [$paymentResult],
        'status' => (isset($paymentResult['status']) && $paymentResult['status'] == 'paid') ? 'Paid' : $order->status,
        'paid' => (isset($paymentResult['status']) && $paymentResult['status'] == 'paid') ? 'Paid' : $order->paid,
      ], ['_id' => $orderId]);

      $returnUrl = $request->getQueryParam('returnUrl');
      if ($returnUrl) {
        return $response->withRedirect($returnUrl . '?orderId=' . $orderId->__toString());
      }
    }

    $subtotal = 0;
    foreach ($cart->products as &$product) {
      $subtotal += $product->price * $product->qty;
    }
    foreach ($cart->diamonds as &$product) {
      $subtotal += $product->priceInternal;
    }
    foreach ($cart->composite as &$product) {
      $subtotal += $product->product->price;
      $subtotal += $product->diamond->priceInternal;
    }
    $oldSubtotal = $subtotal;
    if ($cart->coupon) {
      $subtotal = (new Coupon($this->mongodb))->applyDiscount($cart->coupon, $subtotal);
      $cart->coupon = (new Coupon($this->mongodb))->findOne(['code' => $cart->coupon]);
    } else {
      $cart->coupon = null;
    }
    $shipping = (new Shipping($this->mongodb, ['address' => 'USA', 'price' => $subtotal]))->getDetails();
    $tax = (new Tax($this->mongodb))->getDetails([
      'country' => $cart->billingInfo->shipping_country,
      'state' => $cart->billingInfo->shipping_state,
      'price' => $subtotal
    ]);

    list($orderDate, $shipsDate) =
      $this->getShippingDetails(array_merge($cart->products, array_map(function($composite){
        return $composite->product; // check only products date
      }, $cart->composite)), -5);
//      $this->getShippingDetails($cart->diamonds, -5); // not specified

    $total = ($subtotal + $shipping->price + $tax->price) * (1 - $cart->bankDiscount);

    // Render
    return $this->render($response, 'pages/frontend/cart/payment.twig', [
      'step' => 4,
      'coupon' => $cart->coupon ? $cart->coupon->code : '',
      'subtotal' => $subtotal,
      'discount' => max($oldSubtotal - $subtotal, 0),
      'bankDiscount' => max($cart->bankDiscount, 0),
      'shipping' => $shipping->price,
      'orderDate' => $orderDate,
      'shipsDate' => $shipsDate,
      'tax' => $tax->price,
      'total' => $total,
      'paymentMethod' => $cart->paymentMethod,
      'products' => $cart->products,
      'diamonds' => $cart->diamonds,
      'composite' => $cart->composite,
      'billingInfo' => $cart->billingInfo
    ]);
  }

  /**
   * Cart renderer
   *
   * @param Request $request
   * @param Response $response
   * @param array $args
   *
   * @return Response
   */
  public function confirmationAction(Request $request, Response $response, $args)
  {
    // ugly way for accessing request attributes
    $this->request = $request;

    $user = $request->getAttribute('user');
    if (!$user) {
      return $response->withRedirect('/cart');
    }

    $Order = (new Order($this->mongodb))->findOne(['_id' => new ObjectID($request->getQueryParam('orderId'))]);

    // Render
    return $this->render(
      $response,
      'pages/frontend/cart/confirmation.twig',
      [
        'step' => 5,
        'orderNumber' => $Order->number ?? null,
      ]
    );
  }

  public function sendOrderCreatedEmail($order, $host)
  {
    $user = (new User($this->mongodb))->findOne(['_id' => new ObjectID($order->user_id)]);
    $template = (new MailTemplate($this->mongodb))->findOne(['type' => 'admin_order_created']);
    $bodyTemplate = $template->body;

    // for diamonds
    $diamonds = $order->diamonds;
    $templateDiamond = (new MailTemplate($this->mongodb))->findOne(['type' => 'order_created_diamonds']);
    $diamondStrings = [];
    if($diamonds) {
      foreach ($order->diamonds as $diamond) {
        $roundDiamPrice = $diamond->priceInternal;

        $colorObj = $diamond->color = (new Color($this->mongodb))->getOneWhere(['_id' => $diamond->color_id]);
        $color = $colorObj->code;

        $clarityObj = $diamond->clarity = (new Clarity($this->mongodb))->getOneWhere(['_id' => $diamond->clarity_id]);
        $clarity = $clarityObj->code;

        $dataDiamond = [
          '%shapeCode%' => $diamond->shape->code,
          '%diamondPrice%' => round($roundDiamPrice, 0),
          '%diamondSku%' => $diamond->certificateNumber . '/' . $diamond->stockNumber,
          '%shapeCarat%' => $diamond->weight,
          '%lab%' => $diamond->lab,
          '%color%' => $color,
          '%clarity%' => $clarity,
        ];
        $diamondStrings[] = str_replace(array_keys($dataDiamond), array_values($dataDiamond), $templateDiamond->body);
      }
      $diamondsString = join($diamondStrings);
    }

    // for products
    $products = $order->products;
    $templateProduct = (new MailTemplate($this->mongodb))->findOne(['type' => 'order_created_products']);
    $productStrings = [];
    if($products) {
      foreach ($order->products as $product) {
        $templateAttributes = "";
        $attributes = [];
        
        if (!empty($product->withAttributes['Size'])) { $attributes['%Size%'] = $product->withAttributes['Size']; };
        if (!empty($product->withAttributes['Metal'])) { $attributes['%Metal%'] = $product->withAttributes['Metal']; };
        if (!empty($product->withAttributes['Size & Metal'])) { $attributes['%Size & Metal%'] = $product->withAttributes['Size & Metal']; };
        $attributes[] = str_replace(array_keys($attributes), array_values($attributes), $templateAttributes);
        $allAttributes = join($attributes);

        $roundProdPrice = $product->price;

        $dataProduct = [
          '%productCategoryTitle%' => $product->category->title,
          '%productTitle%' => $product->title,
          '%productSku%' => $product->sku,
          '%productPrice%' => round($roundProdPrice, 0),
          '%productDesc%' => $product->description,
          '%productwithAttributes%' => $allAttributes,
        ];
        $productStrings[] = str_replace(array_keys($dataProduct), array_values($dataProduct), $templateProduct->body);
      }
      $productsString = join($productStrings);
    }

    // for composite
    $composites = $order->composite;
    $templateComposites = (new MailTemplate($this->mongodb))->findOne(['type' => 'order_created_composites']);
    $compositeStrings = [];
    if($composites) {
      foreach ($order->composite as $composit) {
        $templateAttributesComp = "";
        $attributesComp = [];
        if (!empty($composit->product->withAttributes['Size'])) { $attributesComp['%Size%'] = $composit->product->withAttributes['Size']; };
        if (!empty($composit->product->withAttributes['Metal'])) { $attributesComp['%Metal%'] = $composit->product->withAttributes['Metal']; };
        if (!empty($composit->product->withAttributes['Size & Metal'])) { $attributesComp['%Size & Metal%'] = $composit->product->withAttributes['Size & Metal']; };
        $attributesComp[] = str_replace(array_keys($attributesComp), array_values($attributesComp), $templateAttributesComp);
        $allAttributesComp = join($attributesComp);

        $roundCompDiamPrice = $composit->diamond->priceInternal;
        $roundCompProdPrice = $composit->product->price;

        $colorObj = $composit->diamond->color = (new Color($this->mongodb))->getOneWhere(['_id' => $composit->diamond->color_id]);
        $color = $colorObj->code;

        $clarityObj = $composit->diamond->clarity = (new Clarity($this->mongodb))->getOneWhere(['_id' => $composit->diamond->clarity_id]);
        $clarity = $clarityObj->code;

        $dataComposite = [
          '%shapeCode%' => $composit->diamond->shape->code,
          '%diamondPrice%' => round($roundCompDiamPrice, 0),
          '%diamondSku%' => $composit->diamond->certificateNumber . '/' . $composit->diamond->stockNumber,
          '%shapeCarat%' => $composit->diamond->weight,
          '%productCategoryTitle%' => $composit->product->category->title,
          '%productTitle%' => $composit->product->title,
          '%productSku%' => $composit->product->sku,
          '%productPrice%' => round($roundCompProdPrice, 0),
          '%productDesc%' => $composit->product->description,
          '%productwithAttributes%' => $allAttributesComp,
          '%lab%' => $composit->diamond->lab,
          '%color%' => $color,
          '%clarity%' => $clarity,
        ];
        $compositeStrings[] = str_replace(array_keys($dataComposite), array_values($dataComposite), $templateComposites->body);
      }
      $compositesStrings = join($compositeStrings);
    }

    $orderTotalRound = $order->amount->total;

    $bodyData = [
      '%userFirstname%' => $user->first_name,
      '%userLastname%' => $user->last_name,
      '%orderLink%' => $host . '/my-orders/' . $order->number,
      '%orderNumber%' => $order->number,
      '%orderAddress%' => $order->billingInfo->shipping_address,
      '%orderAddress2%' => $order->billingInfo->shipping_address2,
      '%orderCity%' => $order->billingInfo->shipping_city,
      '%orderState%' => $order->billingInfo->shipping_state,
      '%orderZip%' => $order->billingInfo->shipping_zip,
      '%corderCountry%' => $order->billingInfo->shipping_country,
      '%orderEmail%' => $user->email,
      '%orderPhoneNumber%' => $order->billingInfo->shipping_phone,
      '%diamonds%' => $diamondsString ?? '',
      '%products%' => $productsString ?? '',
      '%composites%' => $compositesStrings ?? '',
      '%coupon%' => $order->coupon ?? 'No',
      '%bankDiscount%' => $order->bankDiscount ?? 'No',
      '%paymentMethod%' => $order->paymentMethod,
      '%amount%' => round($orderTotalRound, 0),
      '%tax%' => $order->amount->tax,
    ];

    $email = $this->settings['mailer']['adminMail'];
    $subject = $template->subject;
    $mailResult = $this->mailer->send($bodyTemplate, $bodyData, function ($message) use ($email, $subject) {
      $message->to($email);
      $message->subject($subject);
    });
  }

  public function sendOrderCreatedEmailToUser($order, $host) 
  {
    $user = (new User($this->mongodb))->findOne(['_id' => new ObjectID($order->user_id)]);
    $template = (new MailTemplate($this->mongodb))->findOne(['type' => 'user_order_created']);
    $bodyTemplate = $template->body;

    // for diamonds
    $diamonds = $order->diamonds;
    $templateDiamond = (new MailTemplate($this->mongodb))->findOne(['type' => 'order_created_diamonds']);
    $diamondStrings = [];

    if($diamonds) {
      foreach ($order->diamonds as $diamond) {
        $roundDiamPrice = $diamond->priceInternal;
        $colorObj = $diamond->color = (new Color($this->mongodb))->getOneWhere(['_id' => $diamond->color_id]);
        $color = $colorObj->code;

        $clarityObj = $diamond->clarity = (new Clarity($this->mongodb))->getOneWhere(['_id' => $diamond->clarity_id]);
        $clarity = $clarityObj->code;

        $dataDiamond = [
          '%shapeCode%' => $diamond->shape->code,
          '%diamondPrice%' => round($roundDiamPrice, 0),
          '%diamondSku%' => $diamond->certificateNumber . '/' . $diamond->stockNumber,
          '%shapeCarat%' => $diamond->weight,
          '%lab%' => $diamond->lab,
          '%color%' => $color,
          '%clarity%' => $clarity,
        ];

        $diamondStrings[] = str_replace(array_keys($dataDiamond), array_values($dataDiamond), $templateDiamond->body);
      }
      $diamondsString = join($diamondStrings);
    }
    
    // for products
    $products = $order->products;
    $templateProduct = (new MailTemplate($this->mongodb))->findOne(['type' => 'order_created_products']);
    $productStrings = [];
    if($products) {
      foreach ($order->products as $product) {
        $templateAttributes = "";
        $attributes = [];
        
        if (!empty($product->withAttributes['Size'])) { $attributes['%Size%'] = $product->withAttributes['Size']; };
        if (!empty($product->withAttributes['Metal'])) { $attributes['%Metal%'] = $product->withAttributes['Metal']; };
        if (!empty($product->withAttributes['Size & Metal'])) { $attributes['%Size & Metal%'] = $product->withAttributes['Size & Metal']; };
        $attributes[] = str_replace(array_keys($attributes), array_values($attributes), $templateAttributes);
        $allAttributes = join($attributes);

        $roundProdPrice = $product->price;

        $dataProduct = [
          '%productCategoryTitle%' => $product->category->title,
          '%productTitle%' => $product->title,
          '%productSku%' => $product->sku,
          '%productPrice%' => round($roundProdPrice, 0),
          '%productDesc%' => $product->description,
          '%productwithAttributes%' => $allAttributes,
        ];
        $productStrings[] = str_replace(array_keys($dataProduct), array_values($dataProduct), $templateProduct->body);
      }
      $productsString = join($productStrings);
    }

    // for composite
    $composites = $order->composite;
    $templateComposites = (new MailTemplate($this->mongodb))->findOne(['type' => 'order_created_composites']);
    $compositeStrings = [];
    if($composites) {
      foreach ($order->composite as $composit) {
        $templateAttributesComp = "";
        $attributesComp = [];
        if (!empty($composit->product->withAttributes['Size'])) { $attributesComp['%Size%'] = $composit->product->withAttributes['Size']; };
        if (!empty($composit->product->withAttributes['Metal'])) { $attributesComp['%Metal%'] = $composit->product->withAttributes['Metal']; };
        if (!empty($composit->product->withAttributes['Size & Metal'])) { $attributesComp['%Size & Metal%'] = $composit->product->withAttributes['Size & Metal']; };
        $attributesComp[] = str_replace(array_keys($attributesComp), array_values($attributesComp), $templateAttributesComp);
        $allAttributesComp = join($attributesComp);

        $roundCompDiamPrice = $composit->diamond->priceInternal;
        $roundCompProdPrice = $composit->product->price;

        $colorObj = $composit->diamond->color = (new Color($this->mongodb))->getOneWhere(['_id' => $composit->diamond->color_id]);
        $color = $colorObj->code;

        $clarityObj = $composit->diamond->clarity = (new Clarity($this->mongodb))->getOneWhere(['_id' => $composit->diamond->clarity_id]);
        $clarity = $clarityObj->code;

        $dataComposite = [
          '%shapeCode%' => $composit->diamond->shape->code,
          '%diamondPrice%' => round($roundCompDiamPrice, 0),
          '%diamondSku%' => $composit->diamond->certificateNumber . '/' . $composit->diamond->stockNumber,
          '%shapeCarat%' => $composit->diamond->weight,
          '%productCategoryTitle%' => $composit->product->category->title,
          '%productTitle%' => $composit->product->title,
          '%productSku%' => $composit->product->sku,
          '%productPrice%' => round($roundCompProdPrice, 0),
          '%productDesc%' => $composit->product->description,
          '%productwithAttributes%' => $allAttributesComp,
          '%lab%' => $composit->diamond->lab,
          '%color%' => $color,
          '%clarity%' => $clarity,
        ];
        $compositeStrings[] = str_replace(array_keys($dataComposite), array_values($dataComposite), $templateComposites->body);
      }
      $compositesStrings = join($compositeStrings);
    }

    $orderTotalRound = $order->amount->total;

    $bodyData = [
      '%userFirstname%' => $user->first_name,
      '%userLastname%' => $user->last_name,
      // '%orderLink%' => $host . '/my-orders/' . $order->number,
      '%orderLink%' => 'https://dreamstone.com' . '/my-orders/' . $order->number,
      '%orderNumber%' => $order->number,
      '%orderAddress%' => $order->billingInfo->shipping_address,
      '%orderAddress2%' => $order->billingInfo->shipping_address2,
      '%orderCity%' => $order->billingInfo->shipping_city,
      '%orderState%' => $order->billingInfo->shipping_state,
      '%orderZip%' => $order->billingInfo->shipping_zip,
      '%corderCountry%' => $order->billingInfo->shipping_country,
      '%orderEmail%' => $user->email,
      '%orderPhoneNumber%' => $order->billingInfo->shipping_phone,
      '%diamonds%' => $diamondsString ?? '',
      '%products%' => $productsString ?? '',
      '%composites%' => $compositesStrings ?? '',
      '%coupon%' => $order->coupon ?? 'No',
      '%bankDiscount%' => $order->bankDiscount ?? 'No',
      '%paymentMethod%' => $order->paymentMethod,
      '%amount%' => round($orderTotalRound, 0),
      '%tax%' => $order->amount->tax,
    ];

    $email = $user->email;
    $subject = $template->subject;
    $mailResult = $this->mailer->send($bodyTemplate, $bodyData, function ($message) use ($email, $subject) {
      $message->to($email);
      $message->subject($subject);
    });
  }

}