<?php

namespace DS\Vendors;

class Rapaport
{
  private $username;
  private $password;
  private $authUrl;
  private $feedUrl;

  private $authTicket;

  public function __construct($credentials)
  {
    $this->username = $credentials['username'];
    $this->password = $credentials['password'];
    $this->authUrl = $credentials['authUrl'];
    $this->feedUrl = $credentials['feedUrl'];
  }

  public function authorize()
  {
    $post_string = 'username=' . $this->username . '&password=' . $this->password;

    $request = curl_init($this->authUrl);
    curl_setopt($request, CURLOPT_HEADER, 0);             // set to 0 to eliminate header info from response
    curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);     // Returns response data instead of TRUE(1)
    curl_setopt($request, CURLOPT_POSTFIELDS, $post_string);    // use HTTP POST to send form data
    curl_setopt($request, CURLOPT_SSL_VERIFYPEER, FALSE); // uncomment this line if you get no gateway response.
    $this->authTicket = curl_exec($request);
    curl_close($request);
  }

  public function readRemoteFileName() {
    foreach (get_headers($this->feedUrl . $this->authTicket) as $header) {
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

  public function downloadFile($fileName) {
    $fp = fopen($fileName, 'wb');
    if (!$fp)
      throw new \Exception('Cannot create file ' . $fileName);

    $request = curl_init($this->feedUrl . $this->authTicket);
    curl_setopt($request, CURLOPT_FILE, $fp);           // Ask cURL to write the contents to a file
    curl_setopt($request, CURLOPT_HEADER, 0);     // set to 0 to eliminate header info from response
    curl_setopt($request, CURLOPT_TIMEOUT, 300);  // set timeout to 5 mins
    curl_exec($request);
    curl_close($request);
    fclose($fp);
  }

}