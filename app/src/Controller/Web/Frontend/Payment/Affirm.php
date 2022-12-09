<?php

namespace DS\Controller\Web\Frontend\Payment;

use DS\Model\Cart;
use DS\Model\Composite;
use DS\Model\Diamond;
use DS\Model\Product;
use DS\Model\Order;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;
use mongodb\BSON\ObjectID;
use DS\Core\Controller\WebController;

final class Affirm extends WebController
{
    private $sandbox_public_key = "RC4NMYWRDSHO6RUW";
    private $sandbox_private_key = "WY5AFJslIc6SYYEQNaD7G2Ypnrk0gAXY";
    private $sandbox_js_script = "https://cdn1-sandbox.affirm.com/js/v2/affirm.js";

    private $live_public_key = "YOUR_LIVE_PUBLIC_API_KEY";
    private $live_private_key = "YOUR_LIVE_PRIVATE_API_KEY";
    private $live_js_script = "https://cdn1.affirm.com/js/v2/affirm.js";

    private $sandbox_base_url = "https://sandbox.affirm.com/api/v2/";
    private $live_base_url = "https://api.affirm.com/api/v2/";

    private $user_confirmation_url = 'http://ds.quadecco.org/payment/affirm/successful';
    private $user_cancel_url = 'http://ds.quadecco.org/payment/affirm/cancelled';

    private $useSandbox = true;

    public function __construct(Container $c)
    {
        parent::__construct($c);

        $s = $this->settings['affirm'];

        $this->sandbox_public_key = $s['sandbox'] ? $s['dev']['public_key'] : $s['prod']['public_key'];
        $this->sandbox_private_key = $s['sandbox'] ? $s['dev']['private_key'] : $s['prod']['private_key'];
        $this->sandbox_js_script = $s['sandbox'] ? $s['dev']['js_script'] : $s['prod']['js_script'];

        $this->live_public_key = $s['sandbox'] ? $s['dev']['public_key'] : $s['prod']['public_key'];
        $this->live_private_key = $s['sandbox'] ? $s['dev']['private_key'] : $s['prod']['private_key'];
        $this->live_js_script = $s['sandbox'] ? $s['dev']['js_script'] : $s['prod']['js_script'];

        $this->sandbox_base_url = $s['sandbox'] ? $s['dev']['base_url'] : $s['prod']['base_url'];
        $this->live_base_url = $s['sandbox'] ? $s['dev']['base_url'] : $s['prod']['base_url'];

        $this->user_confirmation_path = $s['sandbox'] ? $s['dev']['confirmation_path'] : $s['prod']['confirmation_path'];
        $this->user_cancel_path = $s['sandbox'] ? $s['dev']['cancel_path'] : $s['prod']['cancel_path'];

        $this->useSandbox = $s['sandbox'];
    }

    public function paymentAction(Request $request, Response $response, $args)
    {
        // ugly way for accessing request attributes
        $this->request = $request;

        $params = $request->getQueryParams();

        $d = $request->getParsedBody();

        if (!empty($params['orderId'])) {
            $Order = (new Order($this->mongodb))->findOne(['_id' => new ObjectID($params['orderId'])]);

            $items = [];

            if (count($Order->diamonds)) {
                $Diamond = new Diamond($this->mongodb);
                foreach ($Order->diamonds as $diamond) {
                    $Diamond->populate($diamond);
                    $items[] = [
                        'title' => $Diamond->getTitle($diamond),
                        'sku' => $diamond->certificateNumber . '/' . $diamond->stockNumber,
                        'price' => $Diamond->getPrice($diamond),
                        'qty' => 1,
                        'item_url' => $Diamond->getPermalink($diamond),
                    ];
                }
            }

            if (count($Order->products)) {
                $Product = new Product($this->mongodb);
                foreach ($Order->products as $product) {
                    $Product->populate($product);
                    $items[] = [
                        'title' => $Product->getTitle($product),
                        'sku' => $product->sku,
                        'price' => $Product->getPrice($product),
                        'qty' => $product->qty,
                        'item_url' => $Product->getPermalink($product),
                    ];
                }
            }

            if (count($Order->composite)) {
                $Composite = new Composite($this->mongodb);
                foreach ($Order->composite as $composite) {
                    $product = $Composite->getProductDetails($composite->product);
                    $diamond = $Composite->getDiamondDetails($composite->diamond);
                    $items[] = [
                        'title' => $product->title . " " . $diamond->title,
                        'sku' => $product->sku . " " . $diamond->certificateNumber . '/' . $diamond->stockNumber,
                        'price' => $product->price + $diamond->price,
                        'qty' => 1,
                        'item_url' => $Composite->getPermalink($composite),
                    ];
                }
            }

            // Render
            return $this->render($response, 'pages/frontend/cart/payment/affirm.twig', [
                'orderId' => $params['orderId'],
                'public_api_key' => $this->useSandbox ? $this->sandbox_public_key : $this->live_public_key,
                'js_script' => $this->useSandbox ? $this->sandbox_js_script : $this->live_js_script,
                'user_confirmation_url' => $request->getUri()->getBaseUrl() . $this->user_confirmation_path . '?orderId=' . $params['orderId'],
                'user_cancel_url' => $request->getUri()->getBaseUrl() . $this->user_cancel_path . '?orderId=' . $params['orderId'],
                'amount' => $Order->amount->total,
                'items' => $items,
                'shipping' => [
                    'first_name' => $Order->billingInfo->shipping_first_name,
                    'last_name' => $Order->billingInfo->shipping_last_name,
                    'address' => $Order->billingInfo->shipping_address,
                    'address2' => $Order->billingInfo->shipping_address2,
                    'company' => $Order->billingInfo->shipping_company,
                    'city' => $Order->billingInfo->shipping_city,
                    'state' => $Order->billingInfo->shipping_state,
                    'zip' => $Order->billingInfo->shipping_zip,
                    'country' => $Order->billingInfo->shipping_country,
                ],
                'billing' => [
                    'first_name' => empty($Order->billingInfo->billing_first_name) ? $Order->billingInfo->shipping_first_name : $Order->billingInfo->billing_first_name,
                    'last_name' => empty($Order->billingInfo->billing_last_name) ? $Order->billingInfo->shipping_last_name : $Order->billingInfo->billing_last_name,
                    'address' => empty($Order->billingInfo->billing_address) ? $Order->billingInfo->shipping_address : $Order->billingInfo->billing_address,
                    'address2' => empty($Order->billingInfo->billing_address2) ? $Order->billingInfo->shipping_address2 : $Order->billingInfo->billing_address2,
                    'company' => empty($Order->billingInfo->billing_company) ? $Order->billingInfo->shipping_company : $Order->billingInfo->billing_company,
                    'city' => empty($Order->billingInfo->billing_city) ? $Order->billingInfo->shipping_city : $Order->billingInfo->billing_city,
                    'state' => empty($Order->billingInfo->billing_state) ? $Order->billingInfo->shipping_state : $Order->billingInfo->billing_state,
                    'zip' => empty($Order->billingInfo->billing_zip) ? $Order->billingInfo->shipping_zip : $Order->billingInfo->billing_zip,
                    'country' => empty($Order->billingInfo->billing_country) ? $Order->billingInfo->shipping_country : $Order->billingInfo->billing_country,
                ],
            ]);
        }
    }

    public function addPayment($paymentResult)
    {
        if (!isset($paymentResult['order_id'])) {
            return false;
        }

        $orderId = new ObjectID($paymentResult['order_id']);
        $Order = new Order($this->mongodb);
        $currentOrder = $Order->findOne(['_id' => $orderId]);
        if ($currentOrder) {
            $orderStatus = (isset($paymentResult['status'])
                && $paymentResult['status'] == 'authorized'
                && $paymentResult['amount'] >= round(100 * $currentOrder->amount->total))
                ? 'Paid'
                : $currentOrder->status;
            $orderPaid = (isset($paymentResult['status'])
                && $paymentResult['status'] == 'authorized'
                && $paymentResult['amount'] >= round(100 * $currentOrder->amount->total))
                ? 'Paid'
                : 'Failed';
            $Order->updateWhere([
                'payments' => empty($currentOrder->payments)
                    ? [$paymentResult]
                    : array_merge($currentOrder->payments, [$paymentResult]),
                'status' => $orderStatus,
                'paid' => $orderPaid ?? 'Failed',
            ], ['_id' => $orderId]);
        }

        return true;
    }

    public function paymentSuccessful(Request $request, Response $response, $args)
    {
        // ugly way for accessing request attributes
        $this->request = $request;

        if ($request->getMethod() === 'POST') {
            $d = $request->getParsedBody();

            // $this->logger->warn("PAYMENT AFFIRM - SUCCESS:\n" . json_encode($d));

            if (!empty($d["checkout_token"])) {
                $result = (array)json_decode($this->auth($d["checkout_token"]));

                // $this->logger->warn("PAYMENT AFFIRM - AUTH:\n" . json_encode($result));

                if ($this->addPayment($result) !== false) {
                    // $this->logger->warn("PAYMENT AFFIRM - PAYMENT:\n" . json_encode($result));
                }
            }
        }

        $orderId = $request->getQueryParam('orderId');

        return $response->withRedirect('/confirmation?orderId=' . $orderId);
    }

    public function paymentCancelled(Request $request, Response $response, $args)
    {
        // ugly way for accessing request attributes
        $this->request = $request;

        // $this->logger->warn("PAYMENT AFFIRM - CANCELLED:\n");

        return $response->withRedirect('/payment');
    }

    public function auth($checkout_token)
    {
        $endpoint = "charges/";
        $method = "POST";
        $data = array("checkout_token" => $checkout_token);

        return $this->request($endpoint, $method, $data, $this->useSandbox ? 'sandbox' : 'live');
    }

    public function void($charge_id)
    {
        $endpoint = "charges/" . $charge_id . "/void";
        $method = "POST";
        $data = "";

        return $this->request($endpoint, $method, $data, $this->useSandbox ? 'sandbox' : 'live');
    }

    public function capture($charge_id)
    {
        $endpoint = "charges/" . $charge_id . "/capture";
        $method = "POST";
        $data = "";

        return $this->request($endpoint, $method, $data, $this->useSandbox ? 'sandbox' : 'live');
    }

    public function refund($charge_id, $amount)
    {
        $endpoint = "charges/" . $charge_id . "/refund";
        $method = "POST";
        $data = array('amount' => $amount);

        return $this->request($endpoint, $method, $data, $this->useSandbox ? 'sandbox' : 'live');
    }

    public function read($charge_id)
    {
        if ($charge_id) {
            $endpoint = "charges/" . $charge_id;
        } else {
            $endpoint = "charges/?limit=2";
        }

        $method = "GET";
        $data = "";

        return $this->request($endpoint, $method, $data, $this->useSandbox ? 'sandbox' : 'live');
    }

    public function update($carrier, $tracking, $order_id, $charge_id)
    {
        $endpoint = "charges/" . $charge_id . "/update";
        $method = "POST";
        $data = array('shipping_carrier' => $carrier, 'shipping_confirmation' => $tracking, 'order_id' => $order_id);

        return $this->request($endpoint, $method, $data, $this->useSandbox ? 'sandbox' : 'live');
    }

    public function request($a, $b, $c, $d)
    {
        if ($d === "live") {
            $public_key = $this->live_public_key;
            $private_key = $this->live_private_key;
            $base_url = $this->live_base_url;
        } else {
            $public_key = $this->sandbox_public_key;
            $private_key = $this->sandbox_private_key;
            $base_url = $this->sandbox_base_url;
        }

        $url = $base_url . $a;
        $json = json_encode($c);
        $header = array('Content-Type: application/json', 'Content-Length: ' . strlen($json));
        $keypair = $public_key . ":" . $private_key;

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $b);
        curl_setopt($curl, CURLOPT_USERPWD, $keypair);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        http_response_code($status);
        return $response;
    }
}