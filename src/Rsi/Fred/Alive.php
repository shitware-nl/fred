<?php

namespace Rsi\Fred;

class Alive extends Component{

  public $maxSessionTime = null; //!<  Maximum duration of a session (minutes; empty = infinite).
  public $maxInactiveTime = null; //!<  Maximum period of inactivity (minutes; empty = infinite).

  protected $_lastAlive = null;

  public function clientConfig(){
    return array_merge(parent::clientConfig(),['interval' => $this->interval]);
  }

  protected function init(){
    parent::init();
    if(!$this->session->start) $this->session->start = time();
    $this->_lastAlive = $this->session->alive;
    $this->session->alive = time();
  }
  /**
   *  Register a component to be pinged.
   *  @param string $name  Name of the component.
   */
  public function register($name){
    if(!in_array($name,$ping = $this->session->ping ?: [])) $ping[] = $name;
    $this->session->ping = $ping;
  }
  /**
   *  Ping the session to see its status.
   *  @return string  URL to navigate to (empty = no action required).
   */
  public function ping(){
    $this->session->alive = $this->_lastAlive; //restore - ignore this call
    if(
      (!$this->maxSessionTime || (time() - $this->session->start <= $this->maxSessionTime * 60)) &&
      (!$this->maxInactiveTime || (time() - $this->session->alive <= $this->maxInactiveTime * 60))
    ){
      if($ping = $this->session->ping) foreach($ping as $name) $this->_fred->component($name)->ping();
      return null;
    }
    $this->component('user')->id = null;
    $this->session->start = false;
    return $this->router->reverse($this->front->defaultControllerName);
  }

  protected function getInterval(){
    $interval = ini_get('session.gc_maxlifetime') / 90; //seconds -> minutes, and 2/3 of that
    if($this->maxSessionTime) $interval = min($interval,$this->maxSessionTime / 10);
    if($this->maxInactiveTime) $interval = min($interval,$this->maxInactiveTime / 10);
    return max(1,$interval);
  }

}