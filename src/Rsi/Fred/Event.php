<?php

namespace Rsi\Fred;

/**
 *  Event handler component.
 */
class Event extends Component{

  protected $_events = [];
  /**
   *  Start listening to an event.
   *  @param string $name  Name of the event (usually with a class name prefix).
   *  @param function $callback  Callback function (first parameter must be the sender; others are event specific).
   */
  public function listen($name,$callback){
    \Rsi\Record::add($this->_events,$name,[]);
    $this->_events[$name][] = $callback;
  }
  /**
   *  Trigger an event.
   *  @param string $name  Name of the event (usually with a class name prefix).
   *  @param object $sender,...  The initiator of the event, and other parameters for the callback function.
   *  @return mixed  Return value if one of the callbacks returned something other than null (this will also directly stop the
   *    chain). Returns null if none of the callbacks functions (if any) returned anything other than null.
   */
  public function trigger($name,$sender = null){
    $params = func_get_args();
    if($log = $this->_fred->has('log')) $log->debug(__CLASS__ . "::trigger('$name',...)",__FILE__,__LINE__);
    if(array_key_exists($name,$this->_events)){
      array_shift($params);
      foreach($this->_events[$name] as $callback)
        if(($result = call_user_func_array($callback,$params)) !== null) return $result;
    }
    return null;
  }

}