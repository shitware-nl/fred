<?php

namespace Rsi\Fred\Component;

class Session{

  public $_namespace = [];

  public function __construct($namespace){
    $this->_namespace = explode('\\',$namespace);
  }

  protected function key($key){
    return array_merge($this->_namespace,is_array($key) ? $key : [$key]);
  }

  public function get($key){
    return \Rsi\Record::get($_SESSION,$this->key($key));
  }

  public function set($key,$value){
    return \Rsi\Record::set($_SESSION,$this->key($key),$value);
  }

  public function __get($key){
    return $this->get($key);
  }

  public function __set($key,$value){
    $this->set($key,$value);
  }

}