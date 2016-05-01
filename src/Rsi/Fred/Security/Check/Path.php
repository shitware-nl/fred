<?php

namespace Rsi\Fred\Security\Check;

class Path extends \Rsi\Fred\Security\Check{

  public function check(){
    return !preg_match('/\\.\\.[\\\\\\/]/',\Rsi\Record::get($_SERVER,'PATH_INFO') . \Rsi\Record::get($_SERVER,'QUERY_STRING'));
  }

}