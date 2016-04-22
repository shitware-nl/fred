<?php

namespace Rsi\Fred\Log\Handler;

class Mail extends File{

  public $threshold = \Rsi\Fred\Log::ERROR;
  public $interval = 60; //!<  Minimal mail interval (minutes).
  public $deadline = 24 * 60; //!<  Maximum mail interval (minutes).
  public $to = null;
  public $subject = 'Log';
  public $maxSize = 10240; //!<  Maximum number of bytes to mail.

  public function send(){
    $result = null;
    try{
      if(($extra = strlen($message = file_get_contents($this->filename)) - $this->maxSize) > 0)
        $message = substr($message,0,$this->maxSize) . "\n\nand $extra more bytes";
      if($result = $this->_log->fred->mail->send(null,$this->to ?: ini_get('sendmail_from'),$this->subject,$message)){
        file_put_contents($this->filename,null);
        file_put_contents($this->timeFilename,date('c'));
      }
    }
    catch(\Rsi\Fred\Exception $e){
      if($this->_log->fred->debug) throw $e;
      $result = false;
    }
    return $result;
  }

  public function add($prio,$message,$context){
    parent::add($prio,$message,$context);
    $age = time() - $this->_log->filemtime($this->timeFilename);
    if((($prio >= $this->threshold) && ($age >= $this->interval * 60)) || ($age > $this->deadline * 60)) $this->send();
  }

  protected function getFilename(){
    if(!parent::getFilename()) $this->_filename = \Rsi\File::tempDir() . "maillog-{$this->name}.tmp";
    return $this->_filename;
  }

  protected function getTimeFilename(){
    return $this->filename . '.time';
  }

}