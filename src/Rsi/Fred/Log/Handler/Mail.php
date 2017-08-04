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
    if($size = filesize($this->filename)) try{
      $message = file_get_contents($this->filename,false,null,max(0,$extra = $size - $this->maxSize));
      \Rsi\File::write($this->filename,null);
      \Rsi\File::write($this->timeFilename,date('c'),0666);
      if($extra > 0) $message = "Skipped $extra bytes\n\n" . $message;
      $result = $this->_log->fred->mail->send(null,$this->to ?: ini_get('sendmail_from'),$this->subject,$message);
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