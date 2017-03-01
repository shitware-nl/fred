<?php

namespace Rsi\Fred;

class Log extends Component{

  const EMERGENCY = 7; //!<  System is unusable
  const ALERT = 6; //!<  Action must be taken immediately
  const CRITICAL = 5; //!<  Critical conditions
  const ERROR = 4; //!<  Error conditions
  const WARNING = 3; //!<  Warning conditions
  const NOTICE = 2; //!<  Normal, but significant, condition
  const INFO = 1; //!<  Informational message
  const DEBUG = 0; //!<  Debug-level message

  public $handlers = []; //!<  Array with handlers (key = name, value = config).
  public $ignore = []; //!<  Reg-exp's for concatination of IP + '|' + user agent + '|' + message to ignore.

  protected $_busy = false;
  protected $_ignore = false;
  protected $_queue = [];

  public function handler($name){
    if(!is_array($this->handlers[$name])) $this->handlers[$name] = [];
    if(!array_key_exists(null,$config =& $this->handlers[$name])){
      $class_name = \Rsi\Record::get($config,'className',__CLASS__ . '\\Handler\\' . ucfirst($name));
      $config[null] = new $class_name($this,$config,$name);
    }
    return $config[null];
  }

  public function has($name){
    return array_key_exists($name,$this->handlers) ? $this->handler($name) : false;
  }

  public function add($prio,$message,$filename = null,$line_no = null,$context = null){
    if(!$this->_busy) try{
      $this->_busy = true;
      if($this->ignore){
        $subject = implode('|',[$this->component('security')->remoteAddr,\Rsi\Record::get($_SERVER,'HTTP_USER_AGENT','null'),$message]);
        foreach($this->ignore as $filter) if(preg_match($filter,$subject)) return false;
      }
      if(is_object($message)){
        $context = $filename;
        $filename = $message->getFile();
        $line_no = $message->getLine();
        $message = $message->getMessage();
      }
      $context = is_array($filename)
        ? $filename
        : array_merge($context ?: [],array_filter(['filename' => $filename,'lineNo' => $line_no]));
      $parsed = [];
      $this->_ignore = true;
      foreach($this->handlers as $name => $config){
        if($prio >= \Rsi\Record::get($config,'prio',self::DEBUG)) try{
          if($context && !$parsed) foreach($context as $key => $value){
            if(substr($key,0,1) == '@') $value = call_user_func($value,$key = substr($key,1));
            $parsed[$key] = $value;
          }
          $this->handler($name)->add($prio,$message,$parsed);
        }
        catch(\Rsi\Exception $e){
          if($this->_fred->debug) throw $e;
        }
      }
    }
    finally{
      $this->_busy = $this->_ignore = false;
      if($this->_queue) call_user_func_array([$this,'add'],array_shift($this->_queue));
    }
    elseif(!$this->_ignore) $this->_queue[] = func_get_args();
  }

  public function _get($key){
    return $this->handler($key);
  }

  public function __call($func_name,$params){
    array_unshift($params,constant('self::' . strtoupper($func_name)));
    call_user_func_array([$this,'add'],$params);
  }

  public function __invoke($message,$filename = null,$line_no = null,$context = null){
    $this->add(self::DEBUG,$message,$filename,$line_no,$context);
  }

}