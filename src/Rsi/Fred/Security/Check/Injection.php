<?php

namespace Rsi\Fred\Security\Check;

class Injection extends \Rsi\Fred\Security\Check{

  public $chars = ['\'','"','<','>'];

  public function check(){
    $query =
      urldecode($this->component('router')->pathInfo . \Rsi\Record::get($_SERVER,'QUERY_STRING')) .
      implode(array_keys($_POST));
    foreach($this->chars as $char) if(strpos($query,$char) !== false) return false;
    return true;
  }

}