<?php

namespace Rsi;

/**
 *  Basic object.
 *
 *  By making use of the magic getter and setter it is not necessary to define a specific getter and setter for every property
 *  upfront. All properties can be used as normal properties (that is: without using getXxx() and setXxx() functions). A
 *  property which first was public can be made private/protected, after which it is possible to use a specific getter and/or
 *  setter. Private properties can also be published, which makes it unnecessary to create a specic getter and/or setter.
 */
class Object{

  const HIDDEN = 0; //!<  Property is hidden.
  const READABLE = 1; //!<  Property is readable.
  const WRITEABLE = 2; //!<  Property is writeable.
  const READWRITE = 3; //!<  Property is readable and writeable.

  protected $_published = []; //!<  Published properties (key = name of property, value = visibility).

  /**
   *  Publish a property (or hide it again).
   *  @param string|array $property  Name of the property, or an array with name-visibility pairs.
   *  @param int $visibility  Visibility for the property (when a name is given). See the constants for possibilities.
   */
  protected function publish($property,$visibility = self::READABLE){
    if(!is_array($properties = $property)) $properties = [$property => $visibility];
    elseif(!Record::assoc($properties)) $properties = array_fill_keys($properties,$visibility);
    $this->_published = array_merge($this->_published,$properties);
  }
  /**
   *  Check if a property exists (public or published).
   *  @param string $property  Name of the property.
   *  @return bool  True if the property exists.
   */
  public function propertyExists($property){
    return property_exists($this,$property) || array_key_exists($property,$this->_published);
  }
  /**
   *  Configureren the object.
   *  This will also alter protected and read-only properties. If a specific setter exists it will be used.
   *  @param array $config  Array with the configuration (key-value pairs).
   */
  protected function configure($config){
    if($config) foreach($config as $key => $value)
      if(property_exists($this,$key)) $this->$key = $value;
      elseif(method_exists($this,$func_name = 'set' . ucfirst($key))) call_user_func([$this,$func_name],$value);
      elseif(property_exists($this,$property = '_' . $key) && !method_exists($this,'get' . ucfirst($key))) $this->$property = $value;
  }
  /**
   *  Return all constants.
   *  @param string $prefix  Only constants starting with this prefix.
   *  @return array  Key = constant name (without prefix), value = constant value.
   */
  public function constants($prefix = null){
    $reflect = new \ReflectionClass($this);
    $length = strlen($prefix);
    $constants = [];
    foreach($reflect->getConstants() as $name => $value) if(!$prefix || substr($name,0,$length) == $prefix)
      $constants[substr($name,$length)] = $value;
    return $constants;
  }
  /**
   *  Default getter if no specific setter is defined, and the property is also not published (readable).
   *  @param string $key  Name of the property.
   *  @return mixed  Value.
   */
  protected function _get($key){
    throw new \Exception("Can't get property '$key'");
  }
  /**
   *  Default setter if no specific setter is defined, and the property is also not published (writeable).
   *  @param string $key  Name of the property.
   *  @param mixed $value  Value for the property.
   */
  protected function _set($key,$value){
    throw new \Exception("Can't set property '$key'");
  }
  /**
   *  Get one or more properties.
   *  Evaluation order:
   *  - public property.
   *  - specific setter.
   *  - published property.
   *  - default _get() function.
   *  @see _get()
   *  @param string|array $key  Name of the property, or an array with keys.
   *  @return mixed  Value(s).
   */
  public function get($key){
    if(is_array($key)){
      $result = [];
      foreach($key as $sub) $result[$sub] = $this->get($sub);
      return $result;
    }
    if(property_exists($this,$key)) return $this->$key;
    if(method_exists($this,$func_name = 'get' . ucfirst($key))) return call_user_func([$this,$func_name]);
    if(array_key_exists($key,$this->_published) && ($this->_published[$key] & self::READABLE)){
      $property = '_' . $key;
      return $this->$property;
    }
    return $this->_get($key);
  }
  /**
   *  Set one or more properties.
   *  Evaluation order:
   *  - public property.
   *  - specific setter.
   *  - published property.
   *  - default _set() function.
   *  @see _set()
   *  @param string|array $key  Name of the property, or an assoc.array with key-value pairs.
   *  @param mixed $value  Value(s).
   */
  public function set($key,$value = null){
    if(is_array($key)) foreach($key as $sub => $value) $this->set($sub,$value);
    elseif(property_exists($this,$key)) $this->$key = $value;
    elseif(method_exists($this,$func_name = 'set' . ucfirst($key))) call_user_func([$this,$func_name],$value);
    elseif(array_key_exists($key,$this->_published) && ($this->_published[$key] & self::WRITEABLE)){
      $property = '_' . $key;
      $this->$property = $value;
    }
    else $this->_set($key,$value);
  }

  public function __get($key){
    return $this->get($key);
  }

  public function __set($key,$value){
    $this->set($key,$value);
  }

}