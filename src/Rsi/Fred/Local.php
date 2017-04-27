<?php

namespace Rsi\Fred;

class Local extends Component{

  public $dateFormat = 'd-m-Y';
  public $timeFormat = 'G:i';
  public $dateTimeFormat = '[date], [time]';

  public $decimalPoint = ',';
  public $thousandsSeparator = ' ';
  public $delimiter = ';';

  protected function init(){
    parent::init();
    $this->dateTimeFormat = strtr($this->dateTimeFormat,['[date]' => $this->dateFormat,'[time]' => $this->timeFormat]);
  }

  public function clientConfig(){
    return array_merge(parent::clientConfig(),[
      'dateFormat' => $this->dateFormat,
      'timeFormat' => $this->timeFormat,
      'dateTimeFormat' => $this->dateTimeFormat,
      'decimalPoint' => $this->decimalPoint,
      'thousandsSeparator' => $this->thousandsSeparator,
      'delimiter' => $this->delimiter
    ]);
  }

  public function formatDate($value){
    if(!$value) return null;
    if(!is_object($value)) $value = DateTime\Date::create($value,'Y-m-d');
    return $value->format($this->dateFormat);
  }

  public function convertDate($value){
    return DateTime\Date::create($value,$this->dateFormat);
  }

  public function formatTime($value){
    if(\Rsi::nothing($value)) return $value;
    if(!is_object($value)) $value = DateTime\Time::create($value);
    return $value->format($this->timeFormat);
  }

  public function convertTime($value){
    return DateTime\Time::create($value,$this->timeFormat);
  }

  public function formatDateTime($value){
    if(\Rsi::nothing($value)) return $value;
    if(!is_object($value)) $value = DateTime\DateTime::create($value);
    return $value->format($this->dateTimeFormat);
  }

  public function convertDateTime($value){
    return DateTime\DateTime::create($value,$this->dateTimeFormat);
  }

  public function formatInt($value){
    if(\Rsi::nothing($value)) return $value;
    return number_format($value,0,$this->decimalPoint,$this->thousandsSeparator);
  }

  public function convertInt($value){
    return (int)str_replace($this->thousandsSeparator,'',$value);
  }

  public function formatNumber($value,$decimals = 0,$trim = null){
    if(\Rsi::nothing($value)) return $value;
    $value = number_format($value,$decimals,$this->decimalPoint,$this->thousandsSeparator);
    if($decimals && ($trim !== null))
      while(($trim++ < $decimals) && !substr($value,-1)) $value = substr($value,0,-1);
    return rtrim($value,$this->decimalPoint);
  }

  public function convertNumber($value,$decimals = 0){
    return round(strtr($value,[$this->thousandsSeparator => '',$this->decimalPoint => '.']),$decimals);
  }

  public function formatSpellOut($value,$locale = null,$format = null){
    return \Rsi\Str::transform((new \NumberFormatter($locale,\NumberFormatter::SPELLOUT))->format($value),$format);
  }

  public function formatRoman($value){
    return \Rsi::romanNumber($value) ?: $value;
  }

  public function formatBytes($value,$decimals = 1){
    return \Rsi::formatBytes($value,$decimals,$this->decimalPoint,$this->thousandsSeparator);
  }

  public function convertBytes($value){
    return \Rsi::shorthandBytes($value);
  }

}