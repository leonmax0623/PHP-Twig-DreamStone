<?php

namespace DS\Controller\Web\Frontend\Payment;

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
use DS\Core\Controller\WebController;
use Slim\Container;

final class AuthorizeNet extends WebController
{
  private $apiLoginId = "5KP3u95bQpv"; // !!!! Sample
  private $transactionKey = "346HZ32z3fP4hTG2"; // !!!! Sample

  public function __construct(Container $c)
  {
    parent::__construct($c);

    $s = $this->settings['authorizenet'];

    $this->apiLoginId = $s['sandbox'] ? $s['dev']['apiLoginId'] : $s['prod']['apiLoginId'];
    $this->transactionKey = $s['sandbox'] ? $s['dev']['transactionKey'] : $s['prod']['transactionKey'];
  }

  public function processRequest($paymentData, $orderData, $billingData, $userData)
  {
    /* Create a merchantAuthenticationType object with authentication details
    retrieved from the constants file */
    $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
    $merchantAuthentication->setName($this->apiLoginId);
    $merchantAuthentication->setTransactionKey($this->transactionKey);

    // Set the transaction's refId
    $refId = 'ref' . time();

    // Create the payment data for a credit card
    $creditCard = new AnetAPI\CreditCardType();
    $creditCard->setCardNumber($paymentData['number']);
    $creditCard->setExpirationDate($paymentData['year'] . '-' . $paymentData['month']);
    $creditCard->setCardCode($paymentData['cid']);

    // Add the payment data to a paymentType object
    $paymentOne = new AnetAPI\PaymentType();
    $paymentOne->setCreditCard($creditCard);

    // Create order information
    $order = new AnetAPI\OrderType();
    $order->setInvoiceNumber(crc32($orderData['id']));
    $order->setDescription($orderData['description']);

    // Set the customer's Bill To address
    $customerAddress = new AnetAPI\CustomerAddressType();
    $customerAddress->setFirstName($billingData['first_name']);
    $customerAddress->setLastName($billingData['last_name']);
    $customerAddress->setCompany($billingData['company']);
    $customerAddress->setAddress($billingData['address']);
    $customerAddress->setCity($billingData['city']);
    $customerAddress->setState($billingData['state']);
    $customerAddress->setZip($billingData['zip']);
    $customerAddress->setCountry($billingData['country']);

    // Set the customer's identifying information
    $customerData = new AnetAPI\CustomerDataType();
    $customerData->setType($userData['type']);
    $customerData->setId(crc32($userData['id']));
    $customerData->setEmail($userData['email']);

    // Add values for transaction settings
    $duplicateWindowSetting = new AnetAPI\SettingType();
    $duplicateWindowSetting->setSettingName("duplicateWindow");
    $duplicateWindowSetting->setSettingValue("60");

    // Add some merchant defined fields. These fields won't be stored with the transaction,
    // but will be echoed back in the response.
    // $merchantDefinedField1 = new AnetAPI\UserFieldType();
    // $merchantDefinedField1->setName("customerLoyaltyNum");
    // $merchantDefinedField1->setValue("1128836273");
    // $merchantDefinedField2 = new AnetAPI\UserFieldType();
    // $merchantDefinedField2->setName("favoriteColor");
    // $merchantDefinedField2->setValue("blue");

    // Create a TransactionRequestType object and add the previous objects to it
    $amount = $orderData['amount'];
    $transactionRequestType = new AnetAPI\TransactionRequestType();
    $transactionRequestType->setTransactionType("authOnlyTransaction");
    $transactionRequestType->setAmount($amount);
    $transactionRequestType->setOrder($order);
    $transactionRequestType->setPayment($paymentOne);
    $transactionRequestType->setBillTo($customerAddress);
    $transactionRequestType->setCustomer($customerData);
    $transactionRequestType->addToTransactionSettings($duplicateWindowSetting);
    // $transactionRequestType->addToUserFields($merchantDefinedField1);
    // $transactionRequestType->addToUserFields($merchantDefinedField2);

    // Assemble the complete transaction request
    $request = new AnetAPI\CreateTransactionRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setRefId($refId);
    $request->setTransactionRequest($transactionRequestType);

    // Create the controller and get the response
    $controller = new AnetController\CreateTransactionController($request);

    $s = $this->settings['authorizenet'];

    $envMode = $s['sandbox'] ?
      \net\authorize\api\constants\ANetEnvironment::SANDBOX :
      \net\authorize\api\constants\ANetEnvironment::PRODUCTION;

    $response = $controller->executeWithApiResponse($envMode);

    return $response;
  }

  public function processResponse($paymentResponse)
  {
    $paymentData = null;

    if ($paymentResponse != null) {
      // Check to see if the API request was successfully received and acted upon
      $tresponse = $paymentResponse->getTransactionResponse();
      if ($paymentResponse->getMessages()->getResultCode() == "Ok") {
        // Since the API request was successful, look for a transaction response
        // and parse it to display the results of authorizing the card
        if ($tresponse != null && $tresponse->getMessages() != null) {
          $paymentData['status'] = 'paid';

          $paymentData['transaction_id'] = $tresponse->getTransId();
          $paymentData['transaction_data'] = [
            'response_code' => $tresponse->getResponseCode(),
            'message_code' => $tresponse->getMessages()[0]->getCode(),
            'auth_code' => $tresponse->getAuthCode(),
            'description' => $tresponse->getMessages()[0]->getDescription(),
          ];
        } else {
          $paymentData['status'] = 'error';
          if ($tresponse->getErrors() != null) {
            $paymentData['error'] = [
              'code' => $tresponse->getErrors()[0]->getErrorCode(),
              'message' => $tresponse->getErrors()[0]->getErrorText(),
            ];
          }
        }
      } else {
        $paymentData['status'] = 'failed';

        if ($tresponse != null && $tresponse->getErrors() != null) {
          $paymentData['error'] = [
            'code' => $tresponse->getErrors()[0]->getErrorCode(),
            'message' => $tresponse->getErrors()[0]->getErrorText(),
          ];
        } else {
          $paymentData['error'] = [
            'code' => $paymentResponse->getMessages()->getMessage()[0]->getCode(),
            'message' => $paymentResponse->getMessages()->getMessage()[0]->getText(),
          ];
        }
      }
    }

    return $paymentData;
  }
}
