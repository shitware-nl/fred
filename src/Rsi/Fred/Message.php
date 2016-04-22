<?php

namespace Rsi\Fred;

class Message extends Component{

  public function add($type,$message,$tags = null){
    if($message = $tags === false ? $message : $this->_fred->trans->str($message,$tags)){
      $messages = $this->session->messages ?: [];
      $messages[$message] = $type;
      $this->session->messages = $messages;
    }
  }

  public function retrieve($type = null,$clear = true){
    $messages = $this->session->messages ?: [];
    $result = $type ? array_filter($messages,function($message_type) use ($type){ return $message_type == $type; }) : $messages;
    if($clear) $this->session->messages = array_diff_key($messages,$result);
    return $result;
  }

  public function _get($key){
    return $this->retrieve($key);
  }

  public function _set($key,$value){
    $this->add($key,$value);
  }

  public function __call($func_name,$params){
    $this->add($func_name,array_shift($params),array_shift($params));
  }

  public function __invoke($message,$tags = null){
    $this->add('message',$message,$tags);
  }

}