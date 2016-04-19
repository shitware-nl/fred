<?php

namespace Rsi\Fred\DateTime;

class DateTime extends \DateTime{

  public static function create($value,$format = null){
    if(!$value) return null;
    $value = parent::createFromFormat($format ?: 'Y-m-d H:i:s',$value);
    if(!$value) return false;
    $result = new static();
    $result->setTimestamp($value->getTimestamp());
    return $result;
  }

  public function add($interval = 1){
    if(is_numeric($interval)) $interval = 'P' . $interval . 'D';
    if(is_string($interval)) $interval = new \DateInterval($interval);
    parent::add($interval);
    return $this;
  }

  public function sub($interval = 1){
    if(is_numeric($interval)) $interval = 'P' . $interval . 'D';
    if(is_string($interval)) $interval = new \DateInterval($interval);
    parent::sub($interval);
    return $this;
  }

  public function setDate($year,$month,$day){
    parent::setDate($year ?: $this->year,$month ?: $this->month,$day ?: $this->day);
    return $this;
  }

  public function setTime($hour,$minute,$second = 0){
    parent::setTime($hour === null ? $this->hour : $hour,$minute === null ? $this->minute : $minute,$second === null ? $this->second : $second);
    return $this;
  }

  public function __get($key){
    $trim = false;
    switch(rtrim($key,'s')){
      case 'date': $key = 'Y-m-d'; break;
      case 'year': $key = 'Y'; break;
      case 'month': $key = 'n'; break;
      case 'day': $key = 'j'; break;
      case 'dayOfWeek': $key = 'w'; break;
      case 'week': $key = 'o\\WW'; break;
      case 'time': $key = 'H:i:s'; break;
      case 'hour': $key = 'G'; break;
      case 'minute': $key = 'i'; $trim = true; break;
      case 'second': $key = 's'; $trim = true; break;
    }
    $value = $this->format($key);
    return $trim ? (ltrim($value,'0') ?: 0) : $value;
  }

  public function __set($key,$value){
    $year = $month = $day = $hour = $minute = $second = null;
    switch(rtrim($key,'s')){
      case 'year':   $year   = $value; break;
      case 'month':  $month  = $value; break;
      case 'day':    $day    = $value; break;
      case 'hour':   $hour   = $value; break;
      case 'minute': $minute = $value; break;
      case 'second': $second = $value; break;
      default: throw new \Exception("Undefined property '$key'");
    }
    if($year || $month || $day) $this->setDate($year,$month,$day);
    if(($hour !== null) || ($minute !== null) || ($second !== null)) $this->setTime($hour,$minute,$second);
  }

  public function __toString(){
    return $this->format('Y-m-d H:i:s');
  }

}