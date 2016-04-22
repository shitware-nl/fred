<?php

namespace Rsi\Fred\Log\Handler;

class Console extends \Rsi\Fred\Log\Handler{

  public $date = null;

  public function add($prio,$message,$context){
    if(($date = date('Y-m-d')) != $this->date){
      print("------------------------- $date -------------------------\n");
      $this->date = $date;
    }
    print(date('H:i:s') . " [$prio] $message\n");
    if(($prio >= \Rsi\Fred\Log::ERROR) && ($filename = \Rsi\Record::get($context,'filename')))
      print("  $filename" . (($line_no = \Rsi\Record::get($context,'lineNo')) ? ":$line_no" : '') . "\n");
  }

}