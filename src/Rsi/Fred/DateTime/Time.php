<?php

namespace Rsi\Fred\DateTime;

class Time extends DateTime{

  public static function create($value,$format = null){
    if(!is_numeric($value)) return parent::create(substr($value,0,8),$format ?: 'H:i:s');
    $result = new static();
    $result->setTime(0,0,$value);
    return $result;
  }

  public function __toString(){
    return $this->format('U');
  }

}