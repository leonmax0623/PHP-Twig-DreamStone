<?php

namespace DS\Controller\Api;

use DS\Core\Controller\ApiController;
use DS\Core\DataMapper\DataMapper;
use DS\Core\Utils;
use DS\Model\Diamond;
use DS\Model\Import as ImportModel;
use DS\Model\Vendor;
use DS\Vendors\Idex;
use DS\Vendors\Rapaport;
use DS\Vendors\Independent;
use DS\Vendors\Belgiumny;
use DS\Vendors\Belgiumdia;
use DS\Vendors\DiamondFoundry;
use DS\Vendors\HariKrishna;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class User
 * @package DS\Controller\Api
 */
final class Import extends ApiController
{
  private $processed = 0;
  private $success = 0;
  private $errors = [];
  private $broken = 0;
  private $rowId = null;

  /**
   * Download listing from w as csv file and check, processing and inserting received data
   * into inner database
   *
   * @param Request $request
   * @param Response $response
   * @param $args
   * @return Response
   * @throws \Exception
   */
  public function rapaportAction(Request $request, Response $response, $args)
  {
    set_time_limit(300);
    $this->logger->info('Start importing listing from rapaport');

    $import = new ImportModel($this->mongodb);

    if ($import->importRunNow('rapaport')) {
      $msg = 'Start import of listing from rapaport skipped: other import already run';
      $this->logger->warn($msg);
      return $this->errorResponse($response, 1001, $msg);
    }

    try {
      $this->rowId = $import->startNewImport('rapaport');

      $Rapaport = new Rapaport($this->settings['import']['rapaport']);
      $Rapaport->authorize();
      $fileName = $this->loadFile($Rapaport, 'rapaport/', $Rapaport->readRemoteFileName());

      $vendor = (new Vendor($this->mongodb))->findOne(['code' => 'rapaport']);
      $this->parseListingCsv($fileName, $vendor);
      $import->stopImport($this->rowId);
      $this->logger->info('Rapaport import ended');
    } catch (\Exception $e) {
      $this->logger->error('Rapaport import failed');
      $this->logger->error($e->getMessage());

      $import->stopImport($this->rowId, ImportModel::STATUS_ERROR);

      return $this->errorResponse($response, 1002, 'Rapaport import failed');
    }

    return $this->emptyJson($response);
  }

  public function idexAction(Request $request, Response $response, $args)
  {
    set_time_limit(180);
    $this->logger->info('Start importing listing from idex');

    $import = new ImportModel($this->mongodb);

    if ($import->importRunNow('idex')) {
      $msg = 'Start import of listing from idex skipped: other import already run';
      $this->logger->warn($msg);
      return $this->errorResponse($response, 1001, $msg);
    }

    try {
      $this->rowId = $import->startNewImport('idex');

      $Idex = new Idex($this->settings['import']['idex']);
      $zipName = $this->loadFile($Idex, 'idex/', $Idex->readRemoteFileName());
      $fileName = Utils::unzipFile($zipName);

      $vendor = (new Vendor($this->mongodb))->findOne(['code' => 'idex']);
      $this->parseListingXml($fileName, $vendor);
      $import->stopImport($this->rowId);
      $this->logger->info('Idex import ended');
    } catch (\Exception $e) {
      $this->logger->error('Idex import failed');
      $this->logger->error($e->getMessage());

      $import->stopImport($this->rowId, ImportModel::STATUS_ERROR);

      return $this->errorResponse($response, 1002, 'Idex import failed');
    }

    return $this->emptyJson($response);
  }

  public function independentAction(Request $request, Response $response, $args)
  {
    set_time_limit(300);
    $Vendor = new Vendor($this->mongodb);
    $vendor = $Vendor->findOne(['code' => $args['id']]);
    if (!$vendor)
      return $this->errorResponse($response, ApiController::INCORRECT_DATA, 'not found');

    $this->logger->info('Start importing listing from ' . $args['id']);

    $import = new ImportModel($this->mongodb);

    if ($import->importRunNow($args['id'])) {
      $msg = 'I. Start import of listing from ' . $args['id'] . ' skipped: other import already run';
      $this->logger->warn($msg);
      return $this->errorResponse($response, 1001, $msg);
    }

    try {
      $dir = __DIR__ . '/../../../../private' . $this->settings['import']['independent']['filesystem'] . '/' . $vendor->folder;
      if (!file_exists($dir))
        return $this->errorResponse($response, ApiController::INCORRECT_DATA, 'dir is absent');

      $Independent = new Independent(['dir' => $dir]);
      $fileName = $Independent->readRemoteFileName();
      if (!$fileName)
        return $this->errorResponse($response, ApiController::INCORRECT_DATA, 'file is absent');

      $this->rowId = $import->startNewImport($args['id']);
      $path = explode('/', $fileName);
      $information = 'I. the last import started at ' . gmdate('Y-m-d H:i:sP');
      $information .= "\n" . 'filename: ' . end($path);

      $this->parseListingCsv($fileName, $vendor);

      $information .= "\n" . 'processed lines: ' . $this->processed;
      $information .= "\n" . 'imported records: ' . $this->success;
      if (!empty($this->errors)) {
        $information .= "\n" . 'import errors:';
        foreach ($this->errors as $i => $error) {
          if ($i === 10) {
            $information .= "\n" . 'and ' . (count($this->errors) - 10) . ' more';
            break;
          }
          $information .= "\n" . $error;
        }
      }
      $import->stopImport($this->rowId);
      $Vendor->updateOne(
        ['code' => $args['id']],
        ['$set' => ['information' => $information]]
      );
      $this->logger->info($args['id'] . ' import ended');
    } catch (\Exception $e) {
      $this->logger->error($args['id'] . ' import failed');
      $this->logger->error($e->getMessage());

      $import->stopImport($this->rowId, ImportModel::STATUS_ERROR);

      return $this->errorResponse($response, 1002, $args['id'] . ' import failed');
    }

    return $this->emptyJson($response);
  }

  public function belgiumnyAction(Request $request, Response $response, $args)
  {
    set_time_limit(300);
    $args['id'] = 'belgiumny';
    $Vendor = new Vendor($this->mongodb);
    $vendor = $Vendor->findOne(['code' => $args['id']]);
    if (!$vendor)
      return $this->errorResponse($response, ApiController::INCORRECT_DATA, 'not found');

    $this->logger->info('B. Start importing listing from ' . $args['id']);

    $import = new ImportModel($this->mongodb);

    if ($import->importRunNow($args['id'])) {
      $msg = 'B. Start import of listing from ' . $args['id'] . ' skipped: other import already run';
      $this->logger->warn($msg);
      return $this->errorResponse($response, 1001, $msg);
    }

    try {
      $Belgiumny = new Belgiumny($this->settings['import']['belgiumny']);
      $fileName = $this->loadFile($Belgiumny, 'belgiumny/', $Belgiumny->readRemoteFileName());
      if (!$fileName)
        return $this->errorResponse($response, ApiController::INCORRECT_DATA, 'file is absent');

      $this->rowId = $import->startNewImport($args['id']);
      $path = explode('/', $fileName);
      $information = 'the last import started at ' . gmdate('Y-m-d H:i:sP');
      $information .= "\n" . 'filename: ' . end($path);

      $this->parseListingJson($fileName, $vendor);

      $information .= "\n" . 'processed lines: ' . $this->processed;
      $information .= "\n" . 'imported records: ' . $this->success;
      if (!empty($this->errors)) {
        $information .= "\n" . 'import errors:';
        foreach ($this->errors as $i => $error) {
          if ($i === 10) {
            $information .= "\n" . 'and ' . (count($this->errors) - 10) . ' more';
            break;
          }
          $information .= "\n" . $error;
        }
      }
      $import->stopImport($this->rowId);
      $Vendor->updateOne(
        ['code' => $args['id']],
        ['$set' => ['information' => $information]]
      );
      $this->logger->info($args['id'] . ' import ended');
    } catch (\Exception $e) {
      $this->logger->error($args['id'] . ' import failed');
      $this->logger->error($e->getMessage());

      $import->stopImport($this->rowId, ImportModel::STATUS_ERROR);

      return $this->errorResponse($response, 1002, $args['id'] . ' import failed');
    }

    return $this->emptyJson($response);
  }

  public function belgiumdiaAction(Request $request, Response $response, $args)
  {
    set_time_limit(300);
    $args['id'] = 'apibelgiumdia';

    $this->logger->info('Start importing listing from ' . $args['id']);

    $import = new ImportModel($this->mongodb);

    if ($import->importRunNow($args['id'])) {
      $msg = 'Start import of listing from ' . $args['id'] . ' skipped: other import already run';
      $this->logger->warn($msg);
      return $this->errorResponse($response, 1001, $msg);
    }

    try {
      $Belgiumdia = new Belgiumdia($this->settings['import']['belgiumdia']);
      $fileName = $this->loadFile($Belgiumdia, 'belgiumdia/', $Belgiumdia->readRemoteFileName());
      if (!$fileName)
        return $this->errorResponse($response, ApiController::INCORRECT_DATA, 'file is absent');

      $this->rowId = $import->startNewImport($args['id']);
      $path = explode('/', $fileName);
      $information = 'B. the last import started at ' . gmdate('Y-m-d H:i:sP');
      $information .= "\n" . 'filename: ' . end($path);

      $Vendor = new Vendor($this->mongodb);

      // Natural Diamond
      $vendor = $Vendor->findOne(['code' => 'apibelgiumdia']);

      if (!$vendor)
        return $this->errorResponse($response, ApiController::INCORRECT_DATA, 'not found');

      $this->parseListingJson($fileName, $vendor);

      $information .= "\n" . 'processed lines: ' . $this->processed;
      $information .= "\n" . 'imported records: ' . $this->success;
      if (!empty($this->errors)) {
        $information .= "\n" . 'import errors:';
        foreach ($this->errors as $i => $error) {
          if ($i === 10) {
            $information .= "\n" . 'and ' . (count($this->errors) - 10) . ' more';
            break;
          }
          $information .= "\n" . $error;
        }
      }
      $import->stopImport($this->rowId);
      $Vendor->updateOne(
        ['code' => $args['id']],
        ['$set' => ['information' => $information]]
      );
      $this->logger->info($args['id'] . ' import ended');
    } catch (\Exception $e) {
      $this->logger->error($args['id'] . ' import failed');
      $this->logger->error($e->getMessage());

      $import->stopImport($this->rowId, ImportModel::STATUS_ERROR);

      return $this->errorResponse($response, 1002, $args['id'] . ' import failed');
    }

    return $this->emptyJson($response);
  }

  public function belgiumdialabAction(Request $request, Response $response, $args)
  {
    set_time_limit(300);
    $args['id'] = 'apibelgiumdialabgrown';

    $this->logger->info('Start importing listing from ' . $args['id']);

    $import = new ImportModel($this->mongodb);

    if ($import->importRunNow($args['id'])) {
      $msg = 'Start import of listing from ' . $args['id'] . ' skipped: other import already run';
      $this->logger->warn($msg);
      return $this->errorResponse($response, 1001, $msg);
    }

    try {
      $Belgiumdia = new Belgiumdia($this->settings['import']['belgiumdia']);
      $fileName = $this->loadFile($Belgiumdia, 'belgiumdia/', $Belgiumdia->readRemoteFileName());
      if (!$fileName)
        return $this->errorResponse($response, ApiController::INCORRECT_DATA, 'file is absent');

      $this->rowId = $import->startNewImport($args['id']);
      $path = explode('/', $fileName);
      $information = 'B. the last import started at ' . gmdate('Y-m-d H:i:sP');
      $information .= "\n" . 'filename: ' . end($path);

      $Vendor = new Vendor($this->mongodb);

      // Lab Grown
      $vendor = $Vendor->findOne(['code' => 'apibelgiumdialabgrown']);

      if (!$vendor) {
        return $this->errorResponse($response, ApiController::INCORRECT_DATA, 'not found');
      }

      $this->parseListingJson($fileName, $vendor);

      $information .= "\n" . 'processed lines: ' . $this->processed;
      $information .= "\n" . 'imported records: ' . $this->success;
      if (!empty($this->errors)) {
        $information .= "\n" . 'import errors:';
        foreach ($this->errors as $i => $error) {
          if ($i === 10) {
            $information .= "\n" . 'and ' . (count($this->errors) - 10) . ' more';
            break;
          }
          $information .= "\n" . $error;
        }
      }
      $import->stopImport($this->rowId);
      $Vendor->updateOne(
        ['code' => $args['id']],
        ['$set' => ['information' => $information]]
      );
      $this->logger->info($args['id'] . ' import ended');
    } catch (\Exception $e) {
      $this->logger->error($args['id'] . ' import failed');
      $this->logger->error($e->getMessage());

      $import->stopImport($this->rowId, ImportModel::STATUS_ERROR);

      return $this->errorResponse($response, 1002, $args['id'] . ' import failed');
    }

    return $this->emptyJson($response);
  }

  public function hkAction(Request $request, Response $response, $args)
  {
    set_time_limit(600);
    $args['id'] = 'apiharikrishna';

    $this->logger->info('Start importing listing from ' . $args['id']);

    $import = new ImportModel($this->mongodb);

    if ($import->importRunNow($args['id'])) {
      $msg = 'Start import of listing from ' . $args['id'] . ' skipped: other import already run';
      $this->logger->warn($msg);
      return $this->errorResponse($response, 1001, $msg);
    }

    try {
      $HariKrishna = new HariKrishna($this->settings['import']['harikrishna']);
      $fileName = $this->loadFile($HariKrishna, 'harikrishna/', $HariKrishna->readRemoteFileName());
      if (!$fileName)
        return $this->errorResponse($response, ApiController::INCORRECT_DATA, 'file is absent');

      $this->rowId = $import->startNewImport($args['id']);
      $path = explode('/', $fileName);
      $information = 'HK. the last import started at ' . gmdate('Y-m-d H:i:sP');
      $information .= "\n" . 'filename: ' . end($path);

      $Vendor = new Vendor($this->mongodb);

      $vendor = $Vendor->findOne(['code' => 'apiharikrishna']);

      if (!$vendor)
        return $this->errorResponse($response, ApiController::INCORRECT_DATA, 'not found');

      $this->parseListingJson($fileName, $vendor);

      $information .= "\n" . 'processed lines: ' . $this->processed;
      $information .= "\n" . 'imported records: ' . $this->success;
      if (!empty($this->errors)) {
        $information .= "\n" . 'import errors:';
        foreach ($this->errors as $i => $error) {
          if ($i === 10) {
            $information .= "\n" . 'and ' . (count($this->errors) - 10) . ' more';
            break;
          }
          $information .= "\n" . $error;
        }
      }
      $import->stopImport($this->rowId);
      $Vendor->updateOne(
        ['code' => $args['id']],
        ['$set' => ['information' => $information]]
      );
      $this->logger->info($args['id'] . ' import ended');
    } catch (\Exception $e) {
      $this->logger->error($args['id'] . ' import failed');
      $this->logger->error($e->getMessage());

      $import->stopImport($this->rowId, ImportModel::STATUS_ERROR);

      return $this->errorResponse($response, 1002, $args['id'] . ' import failed');
    }

    return $this->emptyJson($response);
  }

  public function diamondfoundryAction(Request $request, Response $response, $args)
  {
    set_time_limit(300);
    $args['id'] = 'diamondfoundry';
    $Vendor = new Vendor($this->mongodb);
    $vendor = $Vendor->findOne(['code' => $args['id']]);
    if (!$vendor)
      return $this->errorResponse($response, ApiController::INCORRECT_DATA, 'not found');

    $this->logger->info('D. Start importing listing from ' . $args['id']);

    $import = new ImportModel($this->mongodb);

    if ($import->importRunNow($args['id'])) {
      $msg = 'D. Start import of listing from ' . $args['id'] . ' skipped: other import already run';
      $this->logger->warn($msg);
      return $this->errorResponse($response, 1001, $msg);
    }

    try {
      $DiamondFoundry = new DiamondFoundry($this->settings['import']['diamondfoundry']);
      $fileName = $this->loadFile($DiamondFoundry, 'diamondfoundry/', $DiamondFoundry->readRemoteFileName());
      if (!$fileName)
        return $this->errorResponse($response, ApiController::INCORRECT_DATA, 'file is absent');

      $this->rowId = $import->startNewImport($args['id']);
      $path = explode('/', $fileName);
      $information = 'D. the last import started at ' . gmdate('Y-m-d H:i:sP');
      $information .= "\n" . 'filename: ' . end($path);

      $this->parseListingJson($fileName, $vendor);

      $information .= "\n" . 'processed lines: ' . $this->processed;
      $information .= "\n" . 'imported records: ' . $this->success;
      if (!empty($this->errors)) {
        $information .= "\n" . 'import errors:';
        foreach ($this->errors as $i => $error) {
          if ($i === 10) {
            $information .= "\n" . 'and ' . (count($this->errors) - 10) . ' more';
            break;
          }
          $information .= "\n" . $error;
        }
      }
      $import->stopImport($this->rowId);
      $Vendor->updateOne(
        ['code' => $args['id']],
        ['$set' => ['information' => $information]]
      );
      $this->logger->info($args['id'] . ' import ended');
    } catch (\Exception $e) {
      $this->logger->error($args['id'] . ' import failed');
      $this->logger->error($e->getMessage());

      $import->stopImport($this->rowId, ImportModel::STATUS_ERROR);

      return $this->errorResponse($response, 1002, $args['id'] . ' import failed');
    }

    return $this->emptyJson($response);
  }

  /**
   *
   */
  private function loadFile(&$Provider, $subdir, $remoteFileName)
  {
    $dir = $this->getImportDir() . $subdir;
    if (!file_exists($dir)) mkdir($dir, 0777, true);
    $fileName = $dir . $remoteFileName;
    if (file_exists($fileName))
      $this->logger->info(' this file already exists. download skipped');
    else {
      $this->logger->info(' start file download');
      $Provider->downloadFile($fileName);
    }
    return $fileName;
  }

  /**
   * @param $fileName
   */
  private function parseListingCsv($fileName, $vendor)
  {
    $this->logger->info(' listing downloaded, start parsing');

    if (($handle = fopen($fileName, "r")) !== FALSE) {
      try {
        $columns = fgetcsv($handle, 3000, ",");

        (new Diamond($this->mongodb))->updateMany(['vendor' => $vendor->code], ['$set' => ['isEnabled' => false]]);

        $lineNumber = 1; // first line is header
        while (($data = fgetcsv($handle, 3000, ",")) !== FALSE) {
          $array = array_combine($columns, $data);
          $this->parseRecord($array, $vendor, ++$lineNumber);
        }
      } catch (\Exception $e) {
        $this->logger->error(' parse failed');
        $this->logger->error(' ' . $e->getMessage());
      } finally {
        fclose($handle);
      }
    }

    (new ImportModel($this->mongodb))->updateStatus($this->rowId, $this->processed, $this->broken);
    $this->logger->info('  parsing ended');
  }

  private function parseListingXml($xmlFile, $vendor)
  {
    $this->logger->info('  listing downloaded, start parsing');

    $primEL = 'item';
    $xml = new \XMLReader();
    $xml->open($xmlFile);

    while ($xml->read() && $xml->name != $primEL) {;
    } // finding first primary element to work with

    $lineNumber = 0;
    while ($xml->name == $primEL) { // looping through elements
      // loading element data into simpleXML object
      $element = new \SimpleXMLElement($xml->readOuterXML());

      $record = [];
      foreach ($element->attributes() as $key => $value) {
        if (isset(Idex::ABBREVIATIONS[$key])) // TODO: move to mapper
          $record[Idex::ABBREVIATIONS[$key]] = (string) $value;
      }
      $this->parseRecord($record, $vendor, ++$lineNumber);

      $xml->next($primEL); // moving pointer
      unset($element); // clearing current element
    }

    $xml->close();
  }

  /**
   * @param $fileName
   */
  private function parseListingJson($fileName, $vendor)
  {
    $this->logger->info(' listing downloaded, start parsing');

    $jsonFile = file_get_contents($fileName);

    if ($jsonFile !== false) {
      try {
        (new Diamond($this->mongodb))->updateMany(['vendor' => $vendor->code], ['$set' => ['isEnabled' => false]]);

        $jsonData = json_decode($jsonFile, true);

        $lineNumber = 0; // first line is header
        switch ($vendor->code) {
          case 'belgiumny':
            if (!empty($jsonData['Stock'])) {
              foreach ($jsonData['Stock'] as $data) {
                // Temporary solution for certificate number detection
                if (empty($data['Certificate']) && !empty($data['CertificateLink'])) {
                  $data['Certificate'] = basename($data['CertificateLink'], '.pdf');
                }

                $this->parseRecord($data, $vendor, ++$lineNumber);
              }
            }
            break;
          case 'apibelgiumdia':
            if (!empty($jsonData['Stock'])) {
              foreach ($jsonData['Stock'] as $data) {
                // Include only "Natural Diamond"
                if ($data['Diamond_Type'] != 'Natural Diamond') {
                  continue;
                }
                // Temporary solution for certificate number detection
                if (empty($data['Certificate']) && !empty($data['CertificateLink'])) {
                  $data['Certificate'] = basename($data['CertificateLink'], '.pdf');
                }

                // Calculating the total price from per carat price
                $data['Total_Price'] = $data['Buy_Price'] * $data['Weight'];

                $this->parseRecord($data, $vendor, ++$lineNumber);
              }
            }
            break;
          case 'apibelgiumdialabgrown':
            if (!empty($jsonData['Stock'])) {
              foreach ($jsonData['Stock'] as $data) {
                // Include only "Lab Grown"
                if ($data['Diamond_Type'] != 'Lab Grown') {
                  continue;
                }
                // Temporary solution for certificate number detection
                if (empty($data['Certificate']) && !empty($data['CertificateLink'])) {
                  $data['Certificate'] = basename($data['CertificateLink'], '.pdf');
                }

                // Calculating the total price from per carat price
                $data['Total_Price'] = $data['Buy_Price'] * $data['Weight'];

                $this->parseRecord($data, $vendor, ++$lineNumber);
              }
            }
            break;
          case 'apiharikrishna':
            if (!empty($jsonData)) {
              foreach ($jsonData as $data) {
                $this->parseRecord($data, $vendor, ++$lineNumber);
              }
            }
            break;
          case 'diamondfoundry':
            if (!empty($jsonData)) {
              foreach ($jsonData as $data) {
                // Preparing and converting fields
                if (!empty($data['lot_id'])) {
                  // $data['netsuite_id'] = (string) $data['lot_id'];
                  $data['certificateNumber'] = (string) $data['lot_id'];
                  $data['stockNumber'] = (string) $data['lot_id'];
                }
                if (count($data['digital_assets'])) {
                  if ($data['digital_assets'][0]['kind'] == 'video') {
                    $data['digital_assets/0/url'] = $data['digital_assets'][0]['url'];
                  }
                  if ($data['digital_assets'][0]['kind'] == 'image') {
                    $data['digital_assets/0/url'] = $data['digital_assets'][0]['url'];
                  }
                }
                if (count($data['prices']) && !empty($data['prices'][0])) {
                  $data['priceExternal'] = $data['prices'][0]['amount_usd'];
                }
                if (!empty($data['length_mm']) && !empty($data['width_mm']) && !empty($data['depth_mm'])) {
                  $data['measurements'] = $data['length_mm'] . 'x' . $data['width_mm'] . 'x' . $data['depth_mm'];
                }
                unset($data['digital_assets']);
                unset($data['prices']);

                $this->parseRecord($data, $vendor, ++$lineNumber);
              }
            }
            break;
          default:
            if (!empty($jsonData)) {
              foreach ($jsonData as $data) {
                $this->parseRecord($data, $vendor, ++$lineNumber);
              }
            }
        }
      } catch (\Exception $e) {
        $this->logger->error(' parse failed');
        $this->logger->error(' ' . $e->getMessage());
      }
    }

    (new ImportModel($this->mongodb))->updateStatus($this->rowId, $this->processed, $this->broken);
    $this->logger->info('  parsing ended');
  }

  private function parseRecord($record, $vendor, $lineNumber)
  {
    $errorText = 'diamond in line ' . $lineNumber . ' skipped: ';
    $this->processed++;

    if ($this->processed % 2000 == 0) {
      $this->logger->info("    already parsed {$this->processed} records..");
      (new ImportModel($this->mongodb))->updateStatus($this->rowId, $this->processed, $this->broken);
    }

    $DataMapper = new DataMapper($this->mongodb, $vendor->code);
    $certificateNumber = $DataMapper->getValue($record, 'certificateNumber');
    if (empty($certificateNumber)) {
      $this->errors[] = $errorText . 'certificateNumber is missing';
      $this->broken++;
      return;
    }

    $Diamond = new Diamond($this->mongodb);
    $diamond = $Diamond->getOneWhere(['certificateNumber' => $certificateNumber]);
    if (!empty($diamond)) {
      if ($diamond->isChangedManually) {
        $this->errors[] = $errorText . 'changed manually';
        return;
      }
      // if ($diamond->vendor !== $DataMapper->getCode()) {
      //   $this->errors[] = $errorText . 'we already have it from another vendor (' . $diamond->vendor . ')';
      //   return;
      // }
    }

    try {
      $record = $DataMapper->convertToMongoFormat($record);
      if (empty($record)) {
        $this->errors[] = $errorText . 'record is broken';
        $this->broken++;
      }
      if (!is_string($record)) {
        $record['vendor'] = $vendor->code;
        $record['isNatural'] = $vendor->isNatural;
        $record['certificateNumber'] = (string) $record['certificateNumber'];
        $Diamond->findOneAndUpdate(
          ['certificateNumber' => $record['certificateNumber']],
          ['$set' => $record],
          ['upsert' => true]
        );
        $this->success++;
      } else {
        $this->errors[] = $errorText . $record;
        $this->broken++;
      }
    } catch (\Exception $e) {
      $this->errors[] = $errorText . $e->getCode() . ' - ' . $e->getMessage();
      $this->broken++;
    }
  }

  /**
   * @param $fileName
   * @return string
   */
  private function getImportDir()
  {
    $dir = __DIR__ . '/../../../../private/import/';
    if (!file_exists($dir)) mkdir($dir, 0777, true);
    return $dir;
  }
}
