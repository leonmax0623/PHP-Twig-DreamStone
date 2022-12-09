<?php

namespace DS\Vendors;

class Belgiumdia
{
  private $feedUrl;

  public function __construct($credentials)
  {
    $this->feedUrl = $credentials['feedUrl'];
  }

  public function readRemoteFileName()
  {
    return 'belgiumdia_' . bin2hex(random_bytes(16)) . '.json';
  }

  public function downloadFile($fileName)
  {
    $fp = fopen($fileName, 'wb');
    if (!$fp)
      throw new \Exception('Cannot create file ' . $fileName);

    $request = curl_init($this->feedUrl);

    curl_setopt($request, CURLOPT_FILE, $fp);    // Ask cURL to write the contents to a file
    curl_setopt($request, CURLOPT_HEADER, 0);    // set to 0 to eliminate header info from response
    curl_setopt($request, CURLOPT_TIMEOUT, 300); // set timeout to 5 mins
    curl_exec($request);
    curl_close($request);

    fclose($fp);
  }
}
