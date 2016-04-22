<?php

namespace Rsi\Fred\Log\Handler;

class File extends \Rsi\Fred\Log\Handler{

  public $format = null;
  public $separator = "\n*****\n\n";

  protected $_filename = null;

  public function add($prio,$message,$context){
    if($this->filename) try{
      $message =
        "\n\n" . (\Rsi::commandLine() ? '' :
        "Request: {$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']} {$_SERVER['SERVER_PROTOCOL']}\n" .
        "Remote addr: {$_SERVER['REMOTE_ADDR']}\n") .
        "Date/time: " . date('Y-m-d H:i:s') . "\n" .
        "Message: $message\n" .
        "Prio: $prio\n";
      foreach($context as $key => $value) $message .= ucfirst($key) . ': ' . print_r($value,true) . "\n";
      \Rsi\File::mkdir(dirname($this->filename),0777,true);
      file_put_contents($this->filename,$message . $this->separator,FILE_APPEND);
    }
    catch(\Exception $e){
      if($this->_log->fred->debug) throw $e;
    }
  }

  protected function getFilename(){
    if($this->_filename === null){
      $this->_filename = \Rsi\Record::get($this->_config,'filename');
      if(!$this->_filename){
        preg_match_all('/\\[.+?\\]/',$this->_filename = $this->format,$matches);
        foreach($matches[0] as $match) $this->_filename = str_replace($match,date(substr($match,1,-1)),$this->_filename);
      }
    }
    return $this->_filename;
  }

}