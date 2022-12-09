<?php
$mem_var = new Memcached();
$mem_var->addServer("localhost", 11211);
$response = $mem_var->get("Bilbo");
if ($response) {
  echo $response;
} else {
  echo "Adding Keys (K) for Values (V), You can then grab Value (V) for your Key (K) \m/ (-_-) \m/ ";
  $mem_var->set("Bilbo", "Here s Your (Ring) Master stored in MemCached (^_^)") or die(" Keys Couldn't be Created : Bilbo Not Found :'( ");
}
?>