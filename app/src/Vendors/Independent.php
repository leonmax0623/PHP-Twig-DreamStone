<?php

namespace DS\Vendors;

class Independent
{
  private $dir;

  public function __construct($credentials) {
    $this->dir = $credentials['dir'];
  }

  public function readRemoteFileName() {
    foreach (scandir($this->dir) as $filename) {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        if (in_array($extension, ['csv', 'CSV']))
            return $this->dir . '/' . $filename;
    }

    return '';
  }

}