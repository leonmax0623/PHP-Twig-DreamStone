<?php

namespace DS\Core\Model\Shipping;

use FedEx\TrackService\Request;
use FedEx\TrackService\ComplexType;
use FedEx\TrackService\SimpleType;

/**
 * Class Fedex
 * Docs: https://www.fedex.com/us/developer/webhelp/ws/2019/US/index.htm
 *
 * @package DS\Core\Model\Shipping
 */
class Fedex
{
  private $key; // FEDEX_KEY
  private $password; // FEDEX_PASSWORD
  private $account; // FEDEX_ACCOUNT_NUMBER
  private $meter; // FEDEX_METER_NUMBER

  public function __construct($settings)
  {
    $credentials = $settings['sandbox'] ? $settings['dev'] : $settings['prod'];
    $this->key = $credentials['key'];
    $this->password = $credentials['password'];
    $this->account = $credentials['account'];
    $this->meter = $credentials['meter'];
  }

  /**
   * Get status by tracking number
   *
   * Available statuses:
   * https://www.fedex.com/us/developer/WebHelp/ws/2017/html/WebServicesHelp/WSDVG_US_CA/wsdvg/Tracking_Status.htm
   *
   * Mock Tracking Numbers:
   * https://www.fedex.com/us/developer/webhelp/ws/2019/US/index.htm#t=wsdvg%2FAppendix_F_Test_Server_Mock_Tracking_Numbers.htm
   * 449044304137821 = Shipment information sent to FedEx
   * 149331877648230 = Tendered
   * 020207021381215 = Picked Up
   * 403934084723025 = Arrived at FedEx location
   * 920241085725456 = At local FedEx facility
   * 568838414941 = At destination sort facility
   * 039813852990618 = Departed FedEx location
   * 231300687629630 = On FedEx vehicle for delivery
   * 797806677146 = International shipment release
   * 377101283611590 = Customer not available or business closed
   * 852426136339213 = Local Delivery Restriction
   * 797615467620 = Incorrect Address
   * 957794015041323 = Unable to Deliver
   * 076288115212522 = Returned to Sender/Shipper
   * 581190049992 = International Clearance delay
   * 122816215025810 = Delivered
   * 843119172384577 = Hold at Location
   * 070358180009382 = Shipment Canceled
   *
   * @param $numbers
   * @return array|null
   */
  public function track($numbers)
  {
    // example: https://github.com/JeremyDunn/php-fedex-api-wrapper/blob/master/examples/track-by-id.php
    if (empty($numbers)) return [];

    $trackRequest = new ComplexType\TrackRequest();
    $trackRequest->WebAuthenticationDetail->UserCredential->Key = $this->key;
    $trackRequest->WebAuthenticationDetail->UserCredential->Password = $this->password;
    $trackRequest->ClientDetail->AccountNumber = $this->account;
    $trackRequest->ClientDetail->MeterNumber = $this->meter;

    $trackRequest->Version->ServiceId = 'trck';
    $trackRequest->Version->Major = 16;
    $trackRequest->Version->Intermediate = 0;
    $trackRequest->Version->Minor = 0;

    $trackRequest->SelectionDetails = [];
    foreach ($numbers as $number) {
      $SelectionDetails = new ComplexType\TrackSelectionDetail();
      $SelectionDetails->PackageIdentifier->Value = $number;
      $SelectionDetails->PackageIdentifier->Type = SimpleType\TrackIdentifierType::_TRACKING_NUMBER_OR_DOORTAG;
      $trackRequest->SelectionDetails[] = $SelectionDetails;
    }

    $trackReply = (new Request())->getTrackReply($trackRequest);

    return array_map(function($CompletedTrackDetails){
      /*
       * [CreationTime, Code, Description, Location] if exist or
       * [Location] if no results
       */
      return $CompletedTrackDetails['TrackDetails'][0]['StatusDetail'];
    }, $trackReply->toArray()['CompletedTrackDetails']);
  }
}