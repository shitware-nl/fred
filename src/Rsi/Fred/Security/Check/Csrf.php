<?php

namespace Rsi\Fred\Security\Check;

class Csrf extends \Rsi\Fred\Security\Check{

  public $tokenLengthMin = 32;
  public $tokenLengthMax = 64;

  public function check(){
    $request = $this->component('request');
    if(!$request->action || (($token = $request->csrfToken) === $this->token)) return true;
    if(in_array($token,$invalid_tokens = $this->session->invalidTokens ?: [])) return null;
    $invalid_tokens[] = $token;
    $this->session->invalidTokens = $invalid_tokens;
    return false;
  }

  public function clientConfig(){
    return array_merge(parent::clientConfig(),['token' => $this->token]);
  }

  protected function getToken(){
    $tokens = $this->session->tokens ?: [];
    if(!array_key_exists($name = $this->component('request')->viewControllerName ?: $this->component('router')->controllerName,$tokens)){
      $tokens[$name] = \Rsi\Str::random(rand($this->tokenLengthMin,$this->tokenLengthMax));
      $this->session->tokens = $tokens;
    }
    return $tokens[$name];
  }

}