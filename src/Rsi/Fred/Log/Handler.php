<?php

namespace Rsi\Fred\Log;

abstract class Handler extends \Rsi\Object{

  protected $_log;
  protected $_config = null;
  protected $_name = null;

  public function __construct($log,$config,$name = null){
    $this->_log = $log;
    $this->_name = $name;
    $this->publish('name');
    $this->configure($this->_config = $config);
    $this->init();
  }

  protected function init(){
  }

  abstract public function add($prio,$message,$context);

}