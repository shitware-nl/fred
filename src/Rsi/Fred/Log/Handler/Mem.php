<?php

namespace Rsi\Fred\Log\Handler;

class Mem extends \Rsi\Fred\Log\Handler{

  public $messages = [];
  public $maxSize = 1024; //!<  Maximum number of messages to store (empty = unlimited).
  public $save = null; //!<  Number of logs to save.
  public $filename = null; //!<  Filename to store logs.
  public $mode = 0666;

  public function add($prio,$message,$context){
    if($this->maxSize && (count($this->messages) >= $this->maxSize))
      $this->messages = array_slice($this->messages,$this->maxSize >> 1);
    $this->messages[] = ['time' => microtime(true)] + compact('prio','message','context');
  }

  protected function init(){
    parent::init();
    if($this->save) $this->_log->fred->event->listen(\Rsi\Fred::EVENT_HALT,[$this,'save']);
  }

  public function save(){
    $log = [
      'start' => $this->_log->fred->startTime,
      'end' => microtime(true),
      'file' => \Rsi\Record::get($_SERVER,'PHP_SELF'),
      'mem' => memory_get_peak_usage(true),
      'get' => $_GET,
      'post' => $_POST,
      'session' => $_SESSION,
      'messages' => $this->messages
    ];
    if($this->_log->fred->has('db')){
      $db = $this->_log->fred->db;
      $log['db'] = ['count' => $db->queryCount,'time' => $db->queryTime];
    }
    if(!$this->filename) $this->filename = \Rsi\File::tempDir() . 'log.dat';
    $logs = array_slice(\Rsi\File::unserialize($this->filename,[]) ?: [],1 - $this->save);
    $logs[] = $log;
    \Rsi\File::serialize($this->filename,$logs,$this->mode);
  }

}