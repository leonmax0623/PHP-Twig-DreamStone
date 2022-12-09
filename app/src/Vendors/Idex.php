<?php

namespace DS\Vendors;

class Idex
{
  private $feedUrl;

  const ABBREVIATIONS = [
    'id' => 'IDEX Online Item ID',
    'sr' => 'Supplier Stock Reference',
    'cut' => 'Cut', // Shape
    'ct' => 'Carat',
    'col' => 'Color',
    'nfc' => 'Natural Fancy Color',
    'nfci' => 'Natural Fancy Color Intensity',
    'nfco' => 'Natural Fancy Color Overtone',
    'tc' => 'Treated Color',
    'cl' => 'Clarity',
    'mk' => 'Make (Cut Grade)', // Cut
    'lab' => 'Grading Lab',
    'cn' => 'Certificate Number',
    'cp' => 'Certificate Path',
    'ip' => 'Image Path',
    'or' => 'Online Report',
    'ap' => 'Asking Price Per Carat',
    'tp' => 'Total Price',
    'pol' => 'Polish',
    'sym' => 'Symmetry',
    'mes' => 'Measurements',
    'dp' => 'Total Depth',
    'tb' => 'Table Width',
    'cr' => 'Crown Height',
    'pv' => 'Pavilion Depth',
    'gd' => 'Girdle From / To',
    'cs' => 'Culet Size',
    'cc' => 'Culet Condition',
    'gr' => 'Graining',
    'fl' => 'Fluorescence Intensity',
    'fc' => 'Fluorescence Color',
    'en' => 'Enhancement',
    'sup' => 'Supplier',
    'cty' => 'Country',
    'st' => 'State / Region',
    'rm' => 'Remarks',
    'tel' => 'Phone',
    'psr' => 'Matching Pair Stock Reference',
    'eml' => 'Email',
    'idxl' => 'Reference to IDEX Price Report'
  ];

  public function __construct($credentials)
  {
    $this->feedUrl = $credentials['feedUrl'];
  }

  public function readRemoteFileName() {
    // Content-Disposition: attachment; filename=full.200520095153182.zip
    foreach (get_headers($this->feedUrl) as $header) {
      $signature = 'filename=';
      $fileNameLength = 46;
      $startPos = strpos($header, $signature);
      if ($startPos > 0) {
        $remoteFileName = substr($header, $startPos + strlen($signature), $fileNameLength);
        return $remoteFileName;
      }
    }

    return null;
  }

  public function downloadFile($zipFile) {
    if (!file_put_contents($zipFile, fopen($this->feedUrl, 'r')))
      throw new \Exception('Cannot create file ' . $zipFile);
  }

}
