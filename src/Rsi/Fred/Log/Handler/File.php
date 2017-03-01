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
        "Remote addr: {$_SERVER['REMOTE_ADDR']}\n" .
        (array_key_exists('HTTP_USER_AGENT',$_SERVER) ? "User agent: {$_SERVER['HTTP_USER_AGENT']}\n" : '')) .
        "Date/time: " . date('Y-m-d H:i:s') . "\n" .
        "Message: $message\n" .
        "Prio: $prio\n";
      foreach($context as $key => $value) $message .= ucfirst($key) . ': ' . print_r($value,true) . "\n";
      \Rsi\File::mkdir(\Rsi\File::dirname($this->filename));
      file_put_contents($this->filename,$message . $this->separator,FILE_APPEND);
    }
    catch(\Exception $e){
      if($this->_log->fred->debug) throw $e;
    }
  }
  /**
   *  Delete the file for the next period (when rotating logs).
   */
  public function deleteNext(){
    if($this->format){
      $time = time();
      foreach([60,3600,86400] as $delta) if(($next = \Rsi\Str::replaceDate($this->format,$time + $delta)) != $this->filename){
        \Rsi\File::unlink($next);
        break;
      }
    }
  }

  protected function getFilename(){
    if($this->_filename === null){
      $this->_filename = \Rsi\Record::get($this->_config,'filename');
      if(!$this->_filename) $this->_filename = \Rsi\Str::replaceDate($this->format);
    }
    return $this->_filename;
  }

}