<?php

namespace Rsi\Fred;

/**
 *  Basic component class.
 */
class Component extends \Rsi\Object{

  public $filemtimeTtl = 0;

  protected $_fred = null;
  protected $_config = null;
  protected $_name = null;

  protected $_components = []; //!<  Local components (key = component name, value = component).
  protected $_session = null;

  public function __construct($fred,$config = null){
    $this->_fred = $fred;
    $this->_config = $config;
    $this->publish('fred');
    $this->init();
  }

  protected function init(){
    $this->configure($this->_config);
  }
  /**
   *  Public configuration.
   *  @return array  Public configuration for this component in key => value pairs.
   */
  public function clientConfig(){
    if($config = $this->config('client')) foreach($config as $key => &$value)
      if(is_string($value)) $value = $this->component('trans')->str($value);
    unset($value);
    return $config ?: [];
  }
  /**
   *  Retrieve a config value.
   *  @param string|array $key  Key to look at. An array indicates a nested key. If the array is ['foo' => ['bar' => 'acme']],
   *    then the nested key for the 'acme' value will be ['foo','bar'].
   *  @param mixed $default  Default value if the key does not exist.
   *  @return mixed  Found value, or default value if the key does not exist.
   */
  public function config($key,$default = null){
    return \Rsi\Record::get($this->_config,$key,$default);
  }
  /**
   *  Ping function.
   */
  public function ping(){
  }
  /**
   *  Filemtime with session cache.
   *  @param string $filename  File to get modification time for.
   *  @return int  Last modification time. False if file does not exists.
   */
  public function filemtime($filename){
    $check_time = time();
    if(
      ($file_time = \Rsi\Record::get($file_times = $this->session->fileTimes ?: [],$filename)) &&
      ($file_time['check'] >= $check_time - $this->filemtimeTtl)
    ) return $file_time['time'];
    $file_times[$filename] = ['time' => $time = \Rsi\File::mtime($filename),'check' => $check_time];
    $this->session->fileTimes = $file_times;
    return $time;
  }
  /**
   *  Get a component (local or default).
   *  @param string $name  Name of the component.
   */
  public function component($name){
    if(array_key_exists($name,$this->_components)) return $this->_components[$name];
    return $this->_fred->may($name) ?: false;
  }

  protected function getSession(){
    if(!$this->_session) $this->_session = new Component\Session(get_called_class());
    return $this->_session;
  }

}