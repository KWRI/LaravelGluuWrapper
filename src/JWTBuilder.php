<?php

namespace KWRI\LaravelGluuWrapper;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256 as HS256;
use Lcobucci\JWT\Signer\Rsa\Sha256 as RS256;

class JWTBuilder
{

  protected $builder;
  protected $algo;
  protected $secret;
  protected $payloads = [];

  public function __construct($algo = 'none')
  {
      $this->builder = new Builder;
      $this->setAlgorithm($algo);
  }

  // client_secret setter
  public function setSecret($secret)
  {
    $this->secret = $secret;
  }

  //algorithm setter
  public function setAlgorithm($algo)
  {
    $this->algo = $algo;
  }

  //checker for client secret is empty or not
  public function hasSecret()
  {
    return $this->secret != null && $this->secret != '';
  }

  //function for sign
  protected function sign()
  {
    if ($this->algo != 'none') {
      $signer = $this->getSigner();
      if ($signer && $this->hasSecret()) {
        $this->builder->sign($signer, $this->secret);
      }
    }
  }

  public function getSigner()
  {
    $signer = null;

    switch (strtoupper($this->algo)) {
        case "HS256":
          $signer = new HS256();
          break;
    }
    return $signer;
  }

  //add payload item by key and value pair
  public function addPayload($key, $value)
  {
      $this->payloads[$key] = $value;
  }

  //add payload items from array
  public function addPayloads($values)
  {
      $this->payloads = array_merge($this->payloads, $values);
  }

  //go generate JWT !
  public function generate()
  {
    foreach ($this->payloads as $key => $val) {
      $this->builder->with($key, $val);
    }

    $this->sign();

    return $this->builder->getToken();
  }
}
