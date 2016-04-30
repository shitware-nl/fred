<?php

namespace Rsi\Fred;

/**
 *  Entity manager component.
 */
class Entity extends Component{

  public $classNames = []; //!<  Classnames for item types (key = type, value = class name).
  public $defaultNamespace = '\\'; //!<  Namespace to look for an item class (ucfirst(id)) when not found in classNames.

  protected $_entities = [];

  /**
   *  Retrieve an entity, or create a new if nonexistence.
   *  @param string $type  Entity type.
   *  @param string $id,...  Entity ID, and other parameters - passed when creating a new entity.
   *  @return object  Entity.
   */
  public function item($type,$id){
    \Rsi\Record::add($this->_entities,$type,[]);
    if(array_key_exists($id,$this->_entities[$type])) return $this->_entities[$type][$id];
    $this->component('log')->debug("Creating entity $type:$id",__FILE__,__LINE__);
    $reflect = new \ReflectionClass(\Rsi\Record::get($this->classNames,$type,$this->defaultNamespace . '\\' . ucfirst($type)));
    $params = func_get_args();
    $params[0] = $this->_fred;
    return $this->_entities[$type][$id] = $reflect->newInstanceArgs($params);
  }
  /**
   *  Check if an entity already exists.
   *  @param string $type  Entity type.
   *  @param string $id  Entity ID.
   *  @return bool  True if the entity exists.
   */
  public function has($type,$id){
    return array_key_exists($type,$this->_entities) && array_key_exists($id,$this->_entities[$type]);
  }
  /**
   *  Flush entities.
   *  @param string $type  Entity type (empty to flush al entities).
   *  @param string $id  Entity ID (empty to flush all entities of this type).
   */
  public function flush($type = null,$id = null){
    $this->component('log')->debug(__CLASS__ . "::flush($type" . ($id ? ",$id" : '') . ')',__FILE__,__LINE__);
    if($id) unset($this->_entities[$type][$id]);
    elseif($type) unset($this->_entities[$type]);
    else $this->_entities = [];
  }

  public function __call($func_name,$params){
    return call_user_func_array([$this,'item'],array_merge([$func_name],$params));
  }

  public function __invoke($type,$id){
    return $this->item($type,$id);
  }

}