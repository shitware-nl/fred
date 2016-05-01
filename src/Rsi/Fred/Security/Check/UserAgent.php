<?php

namespace Rsi\Fred\Security\Check;

class UserAgent extends \Rsi\Fred\Security\Check{

  public $illegal = ['<script','{','}']; //!<  Array containing illegal characters / snippets (reg-ex).

  public function check(){
    $user_agent = preg_replace('/\\d+/','*',\Rsi\Record::get($_SERVER,'HTTP_USER_AGENT'));
    if(preg_match('/(' . implode('|',$this->illegal) . ')/i',$user_agent)) return false;
    $orig = $this->session->userAgent;
    if($orig !== null) return $user_agent === $orig;
    $this->session->userAgent = $user_agent;
    return true;
  }

}