<?php

namespace Rsi\Fred\Component;

class Session extends \Rsi\Object{

  public $_namespace = [];

  public function __construct($namespace){
    $this->_namespace = explode('\\',$namespace);
  }

  protected function key($key){
    return array_merge($this->_namespace,is_array($key) ? $key : [$key]);
  }

  protected function _get($key){
    return \Rsi\Record::get($_SESSION,$this->key($key));
  }

  protected function _set($key,$value){
    return \Rsi\Record::set($_SESSION,$this->key($key),$value);
  }

}