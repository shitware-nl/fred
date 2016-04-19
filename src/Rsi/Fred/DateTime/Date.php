<?php

namespace Rsi\Fred\DateTime;

class Date extends DateTime{

  public static function create($value,$format = null){
    return parent::create(substr($value,0,10),$format ?: '!Y-m-d');
  }

  public function __toString(){
    return $this->format('Y-m-d');
  }

}